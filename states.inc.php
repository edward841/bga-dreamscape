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
 * states.inc.php
 *
 * Dreamscape game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: $this->checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

// Named constants
if (!defined('END_GAME'))
{
    define('START_GAME', 1);
    define('TRAVEL_PHASE', 2);
    define('TRAVEL_HELPER', 3);
    define('CREATION_PHASE', 20);
    define('CREATION_HELPER', 21);
    define('EMERGENCE', 30);
    define('FINAL_CREATION_PHASE', 40);
    define('FINAL_CREATION_HELPER', 41);
    define('END_GAME', 99);
}

$machinestates = [

    // The initial state. Please do not modify.

    START_GAME => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => TRAVEL_PHASE]
    ),

    // Note: ID=2 => your first state

//    2 => [
//        "name" => "playerTurn",
//        "description" => clienttranslate('${actplayer} must play a card or pass'),
//        "descriptionmyturn" => clienttranslate('${you} must play a card or pass'),
//        "type" => "activeplayer",
//        "args" => "argPlayerTurn",
//        "possibleactions" => [
//            // these actions are called from the front with bgaPerformAction, and matched to the function on the game.php file
//            "actPlayCard", 
//            "actPass",
//        ],
//        "transitions" => ["playCard" => 3, "pass" => 3]
//    ],
//
//    3 => [
//        "name" => "nextPlayer",
//        "description" => '',
//        "type" => "game",
//        "action" => "stNextPlayer",
//        "updateGameProgression" => true,
//        "transitions" => ["endGame" => END_GAME, "nextPlayer" => 2]
//    ],
//
    TRAVEL_PHASE => [
        "name" => "travelPhase",
        "description" => clienttranslate('${actplayer} must do their travel phase'),
        "descriptionmyturn" => clienttranslate('${you} must do your travel phase'),
        "type" => "activeplayer",
//        "args" => "argTravelPhase", // Information is definitely needed here, about just about everything.
        "possibleactions" => ["actMoveSleeper", "actCollectShard", "actLocationAbility", "actCardAbility", "actPass"],
        "transitions" => ["" => TRAVEL_HELPER]
    ],

    TRAVEL_HELPER => [
        "name" => "travelHelper",
        "description" => '', 
        "type" => "game",
        "action" => "stTravelHelper",
        "updateGameProgression" => true,
        "transitions" => ["nextPlayer" => TRAVEL_PHASE, "nextPhase" => CREATION_PHASE]
    ],

    CREATION_PHASE => [
        "name" => "creationPhase",
        "description" => clienttranslate('${actplayer} must do their creation phase'),
        "descriptionmyturn" => clienttranslate('${you} must do your creation phase'),
        "type" => "activeplayer",
//        "args" => "argCreationPhase",
        "possibleactions" => ["actPlaceElement", "actPlaceDreamer", "actDiscardShard", "actCardAbility", "actPass"],
        "transitions" => ["" => CREATION_HELPER]
    ],
    
    CREATION_HELPER => [
        "name" => "creationHelper",
        "description" => '', 
        "type" => "game",
        "action" => "stCreationHelper",
        "updateGameProgression" => true,
        "transitions" => ["nextPlayer" => CREATION_PHASE, "nextPhase" => EMERGENCE]
    ],

    EMERGENCE => [
        "name" => "emergence",
        "description" => '',
        "type" => "game",
        "action" => "stEmergence",
        "transitions" => ["nextRound" => TRAVEL_PHASE, "finalStages" => FINAL_CREATION_PHASE]
    ],

    FINAL_CREATION_PHASE => [
        "name" => "finalCreationPhase",
        "description" => clienttranslate('${actplayer} must use the last of their shards'),
        "descriptionmyturn" => clienttranslate('${you} must use the last of your shards'),
        "type" => "activeplayer",
        "possibleactions" => ["actPlaceElement", "actDiscardShard", "actPass"], 
        "transitions" => ["" => FINAL_CREATION_HELPER]
    ],

    FINAL_CREATION_HELPER => [
        "name" => "finalCreationHelper",
        "description" => '',
        "type" => "game",
        "action" => "stFinalCreationHelper",
        "updateGameProgression" => true,
        "transitions" => ["nextPlayer" => FINAL_CREATION_PHASE, "endGame" => END_GAME]
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    END_GAME => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    ],

];



