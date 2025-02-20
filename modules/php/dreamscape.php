<?php
// All logic for working with the dreamscape. Placing/moving/removing elements from the dreamscape
//
// Dreamscape coordinate system: each element on dreamscape is one row in the element table where
// 		element_zone: 'dreamscape'
// 		element_player_id: foreign key of player table, indicates which player has that element
// 		element_type: one of "shard", "tree", "dreamer"
// 		element_q, element_r, element_s: Cube coordinate system (q, r, s) consistent with "https://www.redblobgames.com/grids/hexagons/". (q, r, s) are constrained by q + r + s = 0. The center of the board is (0, 0, 0). Thus the valid domain is all q, r, s \in {-2, -1, 0, 1, 2} such that their sum is zero.
// 		element_z: The height of the element. 0 is the "ground floor," and increases by one for each level above. (e.g. a stack of shards tree on green on gray would mean gray z is 0, green z is 1, and tree z is 2)
//
// 		P.S. Sometimes the s will be ommitted, effectively converting the coordinate system from cube to axial. This will typically be done to reduce the amount of data being passed to the client


/////////////////////////////////////////////////
//	Constants
//
const UNIT_VECTORS = [[+1, 0, -1], [+1, -1, 0], [0, -1, +1], [-1, 0, +1], [-1, +1, 0], [0, +1, -1]];

/////////////////////////////////////////////////
//	Auto-wired actions
//

// For now, invalid moves are simple early exits. TODO check that this is the proper way to handle invalid moves
// Assumes destination is simple array of [q, r] such as [2,0]. TODO ensure this works with everything else
function actPlaceElement($elementId, $destination)
{
	// Get details about the element in question
	$elementInfo = $this->getObjectFromDB("SELECT element_zone, element_player_id, element_type type FROM element WHERE element_id='$elementId'");
	$currentPlayer = $this->getActivePlayerId();

	// Check that the element in question is indeed in the active players hands 
	if ($elementInfo['element_zone'] != 'hands' || $elementInfo['element_player_id'] != $currentPlayer)
		throw new \BgaSystemException( "Impossible move" );	

	// Verify the move is valid	
	$validMoves = argCreationPhase();
	if ($elementInfo['type']=='shard' && !in_array($destination, $validMoves['empty spaces']) && !in_array($destination, $validMoves['available shards']))
		throw new BgaSystemException( "Impossible move" );
	else if ($elementInfo['type']=='tree' && !in_array($destination, $validMoves['available shards']))
		throw new BgaSystemException( "Impossible move" );
	else if ($elementInfo['type']=='dreamer') // For now all dreamer moves are invalid. TODO implement dreamer moves
		throw new BgaSystemException( "Impossible move" );

	list($q, $r) = $destination;
	$s = getS($q, $r);
	$this->DbQuery("UPDATE element SET element_zone='dreamscape', element_q='$q', element_r='$r', element_s='$s', element_z='".count($validMoves[$q][$r])+1 ."' WHERE element_id='$elementId'");	
}

/////////////////////////////////////////////////
//	Arg function
//
function getPossibleMovesCreation()
{
	// Prepare moves array
	$validMoves = array();

	// Valid moves is organized based on what is currently in each space:
	// 'empty space': empty space adjacent to a shard (valid space for shard)
	// 'available shards': shards with nothing on top of them (valid space for all elements)
	// 'occupied shards': shards with a tree or dreamer on top of them (can be used for moving dreamer)
	$validMoves['empty space'] = array();
	$validMoves['available shards'] = array();
	$validMoves['occupied shards'] = array();

	// Get necessary data 
	$currentPlayer = $this->getActivePlayerId();
	$dreamscapeMap = mapDreamscape($currentPlayer);
		
	foreach ($dreamscapeMap as $q => $rest)
	{
		foreach ($rest as $r => $stack)
		{
			$height = count($stack);
			if ($height == 0)
			{
				// We are looking at an unoccupied space.
				// Determine if any neighboring spaces have a shard
				foreach (UNIT_VECTORS as list($Q, $R, $S))
				{
					// If the neighboring coordinate is not in the map, skip it
					if (!$dreamscapeMap[$q + $Q][$r + $R] ?? true)
						continue;

					if ($dreamscapeMap[$q + $Q][$r + $R])
					{
						$validMoves['empty space'][] = [$q, $r];
						break;
					}
				}
			}
			else if ($stack[$height] == 'tree')
			{
				// We are looking at an occupied shard
				$validMoves['occupied shards'][] = [$q, $r];
			}
// for now dreamer is not implemented. TODO implement dreamer			
//			else if ($stack[$height] == 'dreamer')
//			{
//				// We are looking at an occupied shard (occupied by the dreamer)
//				$validMoves['occupied shards'][] = [$q, $r];
//				$validMoves['dreamer'] = [$q, $r];
//			}
			else
			{
				// We are looking at an available shard
				$validMoves['available shards'][] = [$q, $r];
			}
		}
	}

	return $validMoves;
}

/////////////////////////////////////////////////
//	General Utils
//

//function isValidCoordinate($q, $r, $s, $z)
//{
//	return abs($q) <= 2 && abs($r) <= 2 && abs($s) <= 2 && $q + $r + $s == 0 && $z >= 0;
//}

// q+r+s=0 => s=-q-r
function getS($q, $r)
{
	return -$q - $r;
}

// Iterate through all the elements on the given player's dreamscape and create a map to decide what moves are valid
// Map is a 3 dimensional array: [p=>[q=>[z=>descriptor]]] i.e. the first level is p, the second q, and the third is z (depth)
// Coordinate system is effectively converted from cube to axial in this map
// The resulting map will have an entry for every valid space on the dreamscape. Spaces with no elements will be empty arrays. Spaces with elements will have an array mapping z to a descriptor of the element (colors for shards, type for other types)
function mapDreamscape($playerId)
{
	// First create an empty map
	$dreamscapeMap = array();
	for ($q = -2; $q <= 2; $q++)
	{
		$dreamscapeMap[$q] = array();
		for ($r = -2; $r <= 2; $r++)
		{
			if (abs(getS($q, $r)) > 2)
				continue;
			$dreamscapeMap[$q][$r] = array();
		}
	}
	
	// Map the dreamscape using the raw data that is $rawDreamscape
	$rawDreamscape = $this->getObjectListFromDB("SELECT * FROM element WHERE element_zone='dreamscape' AND element_player_id='$playerId'");
	foreach ($rawDreamscape as $e)
	{
		$descriptor = $e['element_type'] == 'shard' ? $e['element_color'] : $e['element_type'];
		$dreamscapeMap[$e['element_q']][$e['element_r']][$e['element_z']] = $descriptor;
	}

	return $dreamscapeMap;
}
