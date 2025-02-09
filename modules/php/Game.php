<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Dreamscape implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);
namespace Bga\Games\Dreamscape;
require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

class Game extends \Table
{
	/**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "my_first_global_variable" => 10,
            "my_second_global_variable" => 11,

			"location_max_shards" => 12, 		
			"current_round_number" => 20,
			"final_round_number" => 21,
			
			"my_first_game_variant" => 100,
            "my_second_game_variant" => 101,
        ]);        


    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas()
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "dreamscape";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
			$query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s', '%d')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
				addslashes($player["player_avatar"]),
				4
            ]);
		}

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar, action_points) VALUES %s",
                implode(",", $query_values)
            )
		);

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // TODO: Setup the initial game situation here.
		// Set up round structure: current round and number of rounds
		$this->setGameStateInitialValue("current_round_number", 1);
		$this->setGameStateInitialValue("final_round_number", 3);
		$this->setGameStateInitialValue("location_max_shards", count($players) + 1);	

		// Set up for the custom turn order using a custom_order field in the player DB
		$this->DbQuery("UPDATE `player` SET `custom_order`=`player_no`, `sleeper`=`player_no`");

		$this->setupElements();	

		// Activate first player once everything has been initialized and ready.
		$this->gamestate->changeActivePlayer(intval(array_keys($players)[0]));
	}

	// Override the activeNextPlayer method to use the custom_order field instead of the standard player_no
	public function activeNextPlayer()
	{
		$current_player = (int)$this->getActivePlayerId();	
		$turn_order = $this->getCollectionFromDb("SELECT player_id, custom_order FROM player", true);
		
		// Determine the number of the next person in order
		// It is current number + 1 except after the last player comes 1
		$target_order = ($turn_order[$current_player] % $this->getPlayersNumber()) + 1;

		foreach ($turn_order as $id => $order)
		{
			if ($order == $target_order)
			{
				$this->gamestate->changeActivePlayer($id);
				break;
			}
		}
	}

	/////////////////////////////////////////////////////////////////////////////////
	//
	//	Game State Actions	
	//
	public function gameStateHelper($activePlayerForNextPhase, $nextPhaseTransition="nextPhase")
	{
		$player_id = (int)$this->getActivePlayerId();
		$this->giveExtraTime($player_id);

		// If anyone has not yet had a travel phase, give them a chance.
		// If everyone has completed their travel phase, instead move on to the creation phase.
		$previous_player_no = $this->getUniqueValueFromDb("SELECT custom_order FROM player WHERE player_id=$player_id");
		if ((int)$previous_player_no < $this->getPlayersNumber())
		{
			$this->activeNextPlayer();
			$this->gamestate->nextState("nextPlayer");	
		}
		else
		{
			if ($activePlayerForNextPhase)
				$this->activeNextPlayer();
			$this->gamestate->nextState($nextPhaseTransition);
		}
	}

	// Very straightforward game type state.
	// 1. Always make the next player active player
	// 2. If the phase isnt over, give the next player a chance. 
	// 3. Otherwise move on to the next phase	
	public function stTravelHelper()
	{
		$this->gameStateHelper(true);
	}

	public function stCreationHelper()
	{
		// TODO I am quite certain the travel and creation helpers will be distinct.
		// For now, they are functionally the same but I separated them here to make it clear they probably should be separate
		// If this is still here when game logic is complete, then they can clearly be consolidated to one function
		
		// Now it is slightly distinct from stTravelHelper: It does not set the next player for the next phase
		$this->gameStateHelper(false);
	}
	
	public function stEmergence() 
	{
		// Restore step
		
		// Initiative step: Determine turn order for next round
		// TODO Determine turn order and modify the custom_order fields to reflect new order
		// For now we just shift the turn order by one for proof of concept.
		$this->DbQuery("UPDATE `player` SET `custom_order`=`custom_order`%" . $this->getPlayersNumber() . "+1");
		
		// WARNING: You need to use changeActivePlayer not activeNextPlayer here
		// 		(activeNextPlayer is just current player + 1, which probably won't be correct since we just adjusted all the custom_order fields)
		$this->gamestate->changeActivePlayer($this->getUniqueValueFromDb("SELECT `player_id` FROM `player` WHERE `custom_order`=1"));

		// If there are still rounds to play (we are not on round 6 yet), then start another round.
		// If it is the final round (round 6), then move on to the game's final stages.
		if ($this->getGameStateValue("current_round_number") < $this->getGameStateValue("final_round_number"))
		{
			$this->incGameStateValue("current_round_number", 1);
			$this->gamestate->nextState("nextRound");
		}
		else
			$this->gamestate->nextState("finalStages");	
	}

	public function stFinalCreationHelper()
	{
		$this->gameStateHelper(false, "endGame");
	}

	/////////////////////////////////////////////////////////////////////////////////
	//
	//	Arg Functions:	
	//
	public function argTravelPhase()
	{
		// General info
		$playerId = $this->getActivePlayerId();
		$playerRow = $this->getObjectFromDB("SELECT sleeper, used_location, action_points FROM player WHERE player_id='$playerId'");
		$shardsInHands = $this->getCollectionFromDB("SELECT element_id, element_color FROM element WHERE element_player_id='$playerId' AND element_type='shard' AND element_zone='hands'", true);
		$possibleMoves = array();
		
		// Collect Shard: The player's current location must have shards, and the player must have less than two shards of that particular color in their hand 
		// By default assume you cannot collect the shard. If the move is allowed, set it to true later
		$possibleMoves["collectShard"] = false;
		$currentLocationsShards = $this->getCollectionFromDB("SELECT element_id, element_color, element_q FROM element WHERE element_type='shard' AND element_zone='dreamworld' AND element_p='".$playerRow['sleeper']."'");
		// First ensure the current location has shards available...
		if (count($currentLocationsShards) > 0)
		{
			if (count($shardsInHands) < 2)
				// No need to worry about having two of the matching color already, so you can collect shard
				$possibleMoves["collectShard"] = true;
			else
			{
				// Determine target color (what color is the rightmost shard?)
				$matchingShards = 0;
				$targetColor;
				foreach ($currentLocationsShards as $shardInfo)  
				{
					if ($shardInfo['element_q'] == count($currentLocationsShards))
					{
						$targetColor = $shardInfo['element_color'];
						break;
					}
				}
				
				// Now count how many shards the player has in their hands of that color
				foreach ($shardsInHands as $color)
					if ($color == $targetColor) $matchingShards++;

				if ($matchingShards < 2)
					$possibleMoves['collectShard'] = true;	
			}
		}

		// Move sleeper: Determine which locations are adjacent to the sleeper
		$adjacencies = [1 => [2,4], 2 => [1,3,5], 3 => [2,6], 4 => [1,5], 5 => [2,4,6], 6 => [3,5]];
		$possibleMoves['sleeper'] = $adjacencies[(int) $playerRow['sleeper']];

		// Key movements: Check if any of the available moves are free based on the keystone
		$keystones = $this->getCollectionFromDB("SELECT element_id, element_p FROM element WHERE element_type='shard' AND element_zone='dreamworld' AND element_q='1' AND element_color IN ('".implode("' , '", array_values($shardsInHands))."')", true);
		$possibleMoves['keystones'] = array_map('intval', array_values($keystones));

		// Use current location ability: Determine whether the player can use the current location's ability
		$possibleMoves["locationAbility"] = !$playerRow["used_location"];

		// Use player dreamcard abilities: Determine what dreamcard abilities the player can currently use

		return array("possibleMoves" => $possibleMoves);
	}

	public function argCreationPhase()
	{
		return array();
	}


	///////////////////////////////////////////////////////////////////////////////
	//
	//	Auto-wired Actions:
	//
	public function actMoveSleeper(int $newLocation)
	{
		$playerId = $this->getActivePlayerId();
		$args = $this->argTravelPhase();
		if (!in_array($newLocation, $args['possibleMoves']['sleeper']))
			return;

		$this->DbQuery("UPDATE player SET sleeper='$newLocation' WHERE player_id='$playerId'");

		if (!in_array($newLocation, $args['possibleMoves']['keystones']))
			$this->travelPhaseActionCleanup($playerId);
	}

	/**
	 * actCollectShard is one of the actions a player can perform during the travel phase. The player gains the rightmost shard available at their current collection and adds it to their hands.
	 */
	public function actCollectShard()
	{
		$playerId = intval($this->getActivePlayerId());
		$location = $this->getUniqueValueFromDB("SELECT sleeper FROM player WHERE player_id='$playerId'");

		// For shards on the board, zone is dreamworld, p is the location number, and q is the index (1 is leftmost)
		$availableShards = $this->getCollectionFromDB("SELECT element_id, element_q FROM element WHERE element_zone='dreamworld' AND element_p='$location'", true);	
		
		// If there are no shards, then this action cannot be completed.
		if (count($availableShards) == 0)
		{
			return;
		}
		
		// Determine which shard is the rightmost one (i.e. greatest q)
		foreach ($availableShards as $id => $index)
		{
			if ((int) $index == count($availableShards))
			{
				$this->debug("HELLO!\n");
				$this->DbQuery("UPDATE element SET element_zone='hands', element_player_id='$playerId', element_p='0', element_q='0' WHERE element_id='$id'");			
				break;
			}
		}

		// Decrement action points. End the player's turn if they used all of their action points.
		$this->travelPhaseActionCleanup($playerId);
	}
	
	// TODO Implement abilities...
	public function actLocationAbility()
	{
		$playerId = $this->getActivePlayerId();
		$playerRow = $this->getObjectFromDB("SELECT sleeper, used_location FROM player WHERE player_id='$playerId'");
		if ((int) $playerRow['used_location'])
			return;

		// TODO: Perform the ability of the players current location

		$this->DbQuery("UPDATE player SET used_location='1' WHERE player_id='$playerId'");
	}

	public function actCardAbility()
	{

	}
	
	public function actPass(): void
    {
        // Retrieve the active player ID.
        //$player_id = (int)$this->getActivePlayerId();

		// No transition necessary since there is only one transition out of active player states
		$this->gamestate->nextState();
	}

	public function actPlaceElement()
	{

	}

	public function actDiscardShards($shardIds)
	{
		$this->discardShards($shardIds);
	}

	///////////////////////////////////////////////////////////////////////////////
	//
	//	Helper/Utility functions	
	//
	public function setupElements()
	{
		// Create initial supplies of the elements: initialize drawbag and number of trees.
		$initialElements = ["green" => 20, "blue" => 28, "gray" => 23, "brown"=> 23, "white" => 15];

		$initialElements["totalShards"] = array_sum($initialElements);
		$initialElements["trees"] = $this->getPlayersNumber() * 3;
		$this->globals->set("elements", $initialElements);

		// Draw shards for the board
		//$sql = "INSERT INTO `element` (element_type, element_color, element_zone, element_player_id, element_p, element_q, element_r, element_z) VALUES ";
		$sql = "INSERT INTO `element` (element_type, element_color, element_zone, element_p, element_q) VALUES ";
		$sqlShards = array();

		$boardShards = $this->drawShards((int)$this->getGameStateValue('location_max_shards') * 6);
		for ($location = 1; $location <= 6; $location++)
		{
			for ($slot = 1; $slot <= (int)$this->getGameStateValue('location_max_shards'); $slot++)
			{
				$sqlShards[] = "('shard', '".array_pop($boardShards)."', 'dreamworld', '$location', '$slot')";
			}
		}
		$sql .= implode(',', $sqlShards);
		$this->DbQuery($sql);
	}

	public function drawShards(int $quantity)
	{
		if ($quantity <= 0)
			return array();

		$elements = $this->globals->get("elements");
		$outputShards = array();
		$colors = ["green", "blue", "gray", "brown", "white"];
		
		while ($quantity-- > 0)
		{
			$index = \bga_rand(1, $elements["totalShards"]);
			foreach ($colors as $color)
			{
				$index -= $elements[$color];
				if ($index <= 0)
				{
					array_push($outputShards, $color);
					$elements[$color]--;
					$elements["totalShards"]--;
					break;
				}
			}
		}	

		$this->globals->set("elements", $elements);	
		return $outputShards;
	}

	// Return the specified shards back to the bag. Regardless of whose turn it is and where the shards are.
	// Not quite the same as the players discard shards action
	private function discardShards($shardIds)
	{
		// If the input is not an array but a single value, make an array with just that value
		if (!is_array($shardIds))
			$shardIds = array($shardIds);
		else if (count($shardIds) == 0)
			return;
		
		// Remove the shards from the element table
		$domain = "('".implode("' , '", $shardIds)."')";
		$shards = $this->getCollectionFromDB("SELECT element_id, element_color FROM element WHERE element_id IN $domain", true);
		$this->DbQuery("DELETE FROM element WHERE element_id IN $domain");

		// Add the shards back into the bag
		$unusedElements = $this->globals->get("elements");
		$unusedElements["totalShards"] += count($shards);
		foreach (array_values($shards) as $color)
		{
			$unusedElements[$color]++;	
		}
		$this->globals->set("elements", $unusedElements);	
	}

	// Decrement action points. End the player's turn if they used all of their action points.
	private function travelPhaseActionCleanup($playerId)
	{
		// get players current action points and subtract one for the action they just used.	
		$actionPoints = (int) $this->getUniqueValueFromDB("SELECT action_points FROM player WHERE player_id='$playerId'") - 1;
		// If the player used all their action points, reset it to 4 for next round.
		if ($actionPoints == 0)
			$actionPoints = 4;

		// Update the database to reflect the new value
		$this->DbQuery("UPDATE player SET action_points='$actionPoints' WHERE player_id='$playerId'");

		// If the player used all their action points, move on to the next state.
		if ($actionPoints == 4)
		{
			$this->gamestate->nextState();
		}
	}


		/**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }
}
