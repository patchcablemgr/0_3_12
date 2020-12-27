<?php
define('QUADODO_IN_SYSTEM', true);
require_once '../includes/header.php';
$qls->Security->check_auth_page('operator.php');

if($_SERVER['REQUEST_METHOD'] == 'POST'){
	require_once '../includes/Validate.class.php';
	$validate = new Validate($qls);
	$validate->returnData['success'] = array();
	
	if ($validate->returnData['active'] == 'inactive') {
		echo json_encode($validate->returnData);
		return;
	}
	
	$data = json_decode($_POST['data'], true);
	validate($data, $validate, $qls);
	
	if (!count($validate->returnData['error'])){
		$action = $data['action'];
		$cabinetID = $data['cabinetID'];
		$cabinet = $qls->App->envTreeArray[$cabinetID];
		$cabinetSize = $cabinet['size'];
		$RUOrientation = $cabinet['ru_orientation'];
		$cabinetParentID = getCabinetParentID($cabinet['parent'], $qls);
		$topObject = $qls->SQL->fetch_assoc($qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $cabinetID)), array('RU', 'DESC'), array(0,1)));
		$bottomObj = $qls->SQL->fetch_assoc($qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $cabinetID), 'AND', 'parent_id' => array('=', 0)), array('RU', 'ASC'), array(0,1)));
		$bottomTemplate = $qls->App->templateArray[$bottomObj['template_id']];
		$bottomRUSize = $bottomTemplate['templateRUSize'];
		
		$topOccupiedRU = $topObject['RU'];
		$bottomTopMinRU = $topOccupiedRU;
		$bottomOccupiedRU = $bottomObj['RU'] + $bottomRUSize;
		$RUDiff = $cabinetSize - $bottomOccupiedRU;
		$TopBottomMinRU = ($cabinetSize - $RUDiff) + 1;
		
		if($action == 'adj') {
			
			$attrLocalCabinet = $data['side'] == 'adjCabinetSelectL' ? 'right_cabinet_id' : 'left_cabinet_id';
			$attrAdjCabinet = $data['side'] == 'adjCabinetSelectL' ? 'left_cabinet_id' : 'right_cabinet_id';
			$localCabinetID = $cabinetID;
			$adjCabinetID = $data['adjCabinetID'];
			
			// Build array to loop through
			$adjacencyArray = array(
				array($localCabinetID, $attrLocalCabinet),
				array($adjCabinetID, $attrAdjCabinet)
			);
			
			// Find and delete any existing adjacencies that this change supercedes
			foreach($adjacencyArray as $adjacencyEntry) {
				$adjacencyCabinetID = $adjacencyEntry[0];
				$adjacencyCabinetAttr = $adjacencyEntry[1];
				if(isset($qls->App->cabinetAdjacencyArray[$adjacencyCabinetID])) {
					$adjacencyEntry = $qls->App->cabinetAdjacencyArray[$adjacencyCabinetID];
					$rowID = $adjacencyEntry['id'];
					if($adjacencyEntry[$adjacencyCabinetAttr] == $adjacencyCabinetID) {
						$qls->SQL->delete('app_cabinet_adj', array('id' => array('=', $rowID)));
					}
				}
			}
			
			// Do not insert adjacency entry if the entry was cleared
			if($adjCabinetID != 0) {
				$qls->SQL->insert('app_cabinet_adj', array($attrLocalCabinet, $attrAdjCabinet), array($localCabinetID, $adjCabinetID));
				
				// Log history
				$localCab = $qls->App->envTreeArray[$localCabinetID]['nameString'];
				$adjCab = $qls->App->envTreeArray[$adjCabinetID]['name'];
				$actionString = 'Cabinet adjacency: <strong>'.$localCab.'/'.$adjCab.'</strong>';
				$qls->App->logAction(2, 1, $actionString);
				
			} else {
				
				// Log history
				$localCab = $qls->App->envTreeArray[$localCabinetID]['nameString'];
				$adjCabID = $qls->App->cabinetAdjacencyArray[$localCabinetID][$attrAdjCabinet];
				$adjCab = $qls->App->envTreeArray[$adjCabID]['name'];
				$actionString = 'Cabinet adjacency: <strong>'.$localCab.'/'.$adjCab.'</strong>';
				$qls->App->logAction(2, 3, $actionString);
				
			}
			
		} else if($action == 'path') {
			
			$localCabinetID = $cabinetID;
			$adjCabinetID = $data['value'];
			$pathID = $data['pathID'];

			$query = $qls->SQL->select('*', 'app_cable_path', array('id' => array('=', $pathID)));
			$cablePath = $qls->SQL->fetch_assoc($query);

			$attrAdjCabinet = $cablePath['cabinet_a_id'] == $localCabinetID ? 'cabinet_b_id' : 'cabinet_a_id';
			$qls->SQL->update('app_cable_path', array($attrAdjCabinet => $adjCabinetID), array('id' => array('=', $pathID)));
			
			// Log history
			$localCab = $qls->App->envTreeArray[$localCabinetID]['nameString'];
			$cablePath = $qls->App->cablePathArray[$pathID];
			$originalAdjCabinetID = ($cablePath['cabinet_a_id'] == $localCabinetID) ? $cablePath['cabinet_b_id'] : $cablePath['cabinet_a_id'];
			$originalAdjCab = ($originalAdjCabinetID == 0) ? 'Empty' : $qls->App->envTreeArray[$originalAdjCabinetID]['name'];
			$adjCab = $qls->App->envTreeArray[$adjCabinetID]['name'];
			$actionString = 'Cable path peer: From <strong>'.$localCab.'/'.$originalAdjCab.'</strong> to <strong>'.$adjCab.'</strong>';
			$qls->App->logAction(2, 2, $actionString);
			
		} else if($action == 'distance') {
			
			$pathID = $data['pathID'];
			// Convert distance from meters to millimeters
			$distance = $data['distance']*1000;
			$qls->SQL->update('app_cable_path', array('distance' => $distance), array('id' => array('=', $pathID)));
			
			// Log history
			$cablePath = $qls->App->cablePathArray[$pathID];
			$localCabID = ($cablePath['cabinet_a_id'] == 0) ? $cablePath['cabinet_b_id'] : $cablePath['cabinet_a_id'];
			$adjCabID = ($cablePath['cabinet_a_id'] == 0) ? $cablePath['cabinet_a_id'] : $cablePath['cabinet_b_id'];
			$localCab = $qls->App->envTreeArray[$localCabID]['nameString'];
			$adjCab = ($adjCabID == 0) ? 'Empty' : $qls->App->envTreeArray[$adjCabID]['name'];
			$originalLength = $qls->App->convertToHighestHalfMeter($cablePath['distance']);
			$newLength = $qls->App->convertToHighestHalfMeter($distance);
			$actionString = 'Cable path distance: <strong>'.$localCab.'/'.$adjCab.'</strong> from <strong>'.$originalLength.'m.</strong> to <strong>'.$newLength.'m.</strong>';
			$qls->App->logAction(2, 2, $actionString);
			
		} else if($action == 'notes') {
			
			$pathID = $data['pathID'];
			$notes = $data['value'];
			$qls->SQL->update('app_cable_path', array('notes' => $notes), array('id' => array('=', $pathID)));
			
			// Log history
			$cablePath = $qls->App->cablePathArray[$pathID];
			$localCabID = ($cablePath['cabinet_a_id'] == 0) ? $cablePath['cabinet_b_id'] : $cablePath['cabinet_a_id'];
			$adjCabID = ($cablePath['cabinet_a_id'] == 0) ? $cablePath['cabinet_a_id'] : $cablePath['cabinet_b_id'];
			$localCab = $qls->App->envTreeArray[$localCabID]['nameString'];
			$adjCab = ($adjCabID == 0) ? 'Empty' : $qls->App->envTreeArray[$adjCabID]['name'];
			$actionString = 'Cable path note: <strong>'.$localCab.'/'.$adjCab.'</strong>';
			$qls->App->logAction(2, 2, $actionString);
			
		} else if($action == 'delete') {
			
			$pathID = $data['pathID'];
			$qls->SQL->delete('app_cable_path', array('id' => array('=', $pathID)));
			
			// Log history
			$cablePath = $qls->App->cablePathArray[$pathID];
			$localCabID = ($cablePath['cabinet_a_id'] == 0) ? $cablePath['cabinet_b_id'] : $cablePath['cabinet_a_id'];
			$adjCabID = ($cablePath['cabinet_a_id'] == 0) ? $cablePath['cabinet_a_id'] : $cablePath['cabinet_b_id'];
			$localCab = $qls->App->envTreeArray[$localCabID]['nameString'];
			$adjCab = ($adjCabID == 0) ? 'Empty' : $qls->App->envTreeArray[$adjCabID]['name'];
			$actionString = 'Cable path: <strong>'.$localCab.'/'.$adjCab.'</strong>';
			$qls->App->logAction(2, 3, $actionString);
			
		} else if($action == 'new') {
			
			$validate->returnData['success']['entranceMax'] = $cabinetSize;
			$validate->returnData['success']['localCabinets'] = getChildCabinets($cabinetID, $cabinetParentID, $qls);
			
			$qls->SQL->insert('app_cable_path', array('cabinet_a_id', 'path_entrance_ru', 'distance'), array($cabinetID, $cabinetSize, 1000));
			$validate->returnData['success']['newID'] = $qls->SQL->insert_id();
			
			// Log history
			$cabinetName = $qls->App->envTreeArray[$cabinetID]['nameString'];
			$actionString = 'Blank cable path: <strong>'.$cabinetName.'</strong>';
			$qls->App->logAction(2, 1, $actionString);
			
		} else if($action == 'RU') {
			
			$RUSize = $data['RUSize'];
			$validate->returnData['success']['size'] = $RUSize;
			$validate->returnData['success']['originalSize'] = $cabinetSize;
			$occupiedRUs = $qls->App->getCabinetOccupiedRUs($cabinetID);
			
			if($RUSize < $occupiedRUs['orientationSpecificMin']) {
				$errMsg = 'Invalid RU size.';
				array_push($validate->returnData['error'], $errMsg);
			}
			
			if(!count($validate->returnData['error'])){
				
				$qls->SQL->update('app_env_tree', array('size' => $RUSize), array('id' => array('=', $cabinetID)));
				
				if($RUOrientation) {
					$RUDiff = $cabinetSize - $RUSize;
					foreach($qls->App->objectByCabinetArray[$cabinetID] as $objID) {
						$obj = $qls->App->objectArray[$objID];
						$objTemplateID = $obj['template_id'];
						$objTemplate = $qls->App->templateArray[$objTemplateID];
						if($objTemplate['templateType'] == 'Standard') {
							$objRU = $obj['RU'];
							$newObjRU = $objRU - $RUDiff;
							$qls->SQL->update('app_object', array('RU' => $newObjRU), array('id' => array('=', $objID)));
						}
					}
				}
				// Log history
				$cabinetName = $qls->App->envTreeArray[$cabinetID]['nameString'];
				$originalRUSize = $qls->App->envTreeArray[$cabinetID]['size'];
				$actionString = 'Cabinet RU: <strong>'.$cabinetName.'</strong> from <strong>'.$originalRUSize.'</strong> to <strong>'.$RUSize.'</strong>';
				$qls->App->logAction(2, 2, $actionString);
				
			}
		} else if($action == 'RUOrientation') {
			
			$RUOrientation = $data['value'];
			$qls->SQL->update('app_env_tree', array('ru_orientation' => $RUOrientation), array('id' => array('=', $cabinetID)));
			$validate->returnData['success']['RUOrientation'] = $RUOrientation;
			$validate->returnData['success']['RUData'] = $qls->App->getCabinetOccupiedRUs($cabinetID, $RUOrientation);
			
		} else if($action == 'get') {
			
			$cabinets = getChildCabinets($cabinetID, $cabinetParentID, $qls);
			
			$validate->returnData['success']['path'] = array();
			$result = $qls->SQL->select('*', 'app_cable_path', array('cabinet_a_id' => array('=', $cabinetID), 'OR', 'cabinet_b_id' => array('=', $cabinetID)));
			while ($row = $qls->SQL->fetch_assoc($result)) {
				$attrCabinet = $row['cabinet_a_id'] == $cabinetID ? 'cabinet_b_id' : 'cabinet_a_id';
				array_push($validate->returnData['success']['path'], array(
					'id' => $row['id'],
					'cabinetID' => $row[$attrCabinet],
					'distance' => $row['distance']*0.001,
					'entrance' => $row['path_entrance_ru'],
					'notes' => $row['notes']
				));
			}
			
			$validate->returnData['success']['allCabinets'] = $cabinets;
			$validate->returnData['success']['localCabinets'] = getLocalCabinets($cabinetID, $qls);
			
			//Gather adjacency data
			$result = $qls->SQL->select('*', 'app_cabinet_adj', 'left_cabinet_id = '.$cabinetID.' OR right_cabinet_id = '.$cabinetID);
			while($row = $qls->SQL->fetch_assoc($result)) {
				$attrAdjCabinetID = $row['left_cabinet_id'] == $cabinetID ? 'right_cabinet_id' : 'left_cabinet_id';
				$attrAdjCabinetKey = $row['left_cabinet_id'] == $cabinetID ? 'adjRight' : 'adjLeft';
				$validate->returnData['success'][$attrAdjCabinetKey]['cabinetID'] = $row[$attrAdjCabinetID];
				$validate->returnData['success'][$attrAdjCabinetKey]['entranceRU'] = $row['entrance_ru'];
			}
			
			$validate->returnData['success']['cabName'] = $cabinet['name'];
			$validate->returnData['success']['cabSize'] = $cabinetSize;
			$validate->returnData['success']['entranceMax'] = $cabinetSize;
			$validate->returnData['success']['RUOrientation'] = $RUOrientation;
			$validate->returnData['success']['RUData'] = $qls->App->getCabinetOccupiedRUs($cabinetID);
			
		} else if($action == 'updateCabinetRUMin') {
			$validate->returnData['success']['RUData'] = $qls->App->getCabinetOccupiedRUs($cabinetID);
		} else if($action == 'getFloorplan') {
			
			// Retrieve floorplan data
			$query = $qls->SQL->select('*', 'app_env_tree', array('id' => array('=', $cabinetID)));
			$floorplan = $qls->SQL->fetch_assoc($query);
			$floorplanImg = $floorplan['floorplan_img'];
			
			if(isset($floorplanImg)) {
				$validate->returnData['success']['floorplanImg'] = $floorplanImg;
			} else {
				$validate->returnData['success']['floorplanImg'] = false;
			}
			
			// Retrieve portName data
			$compatibilityArray = array();
			$query = $qls->SQL->select('*', 'app_object_compatibility');
			while($row = $qls->SQL->fetch_assoc($query)) {
				$compatibilityArray[$row['template_id']][$row['side']][$row['depth']] = $row;
			}
			
			// Retrieve object data
			$floorplanObjectData = array();
			if(isset($qls->App->objectByCabinetArray[$cabinetID])) {
				foreach($qls->App->objectByCabinetArray[$cabinetID] as $objectID) {
					$object = $qls->App->objectArray[$objectID];
					$objectTemplateID = $object['template_id'];
					$type = $qls->App->compatibilityArray[$objectTemplateID][0][0]['templateType'];
					$tempArray = array(
						'id' => $objectID,
						'type' => $type,
						'position_top' => $object['position_top'],
						'position_left' => $object['position_left'],
						'html' => $qls->App->floorplanObjDetails[$type]['html']
					);
					
					array_push($floorplanObjectData, $tempArray);
				}
			}
			
			$validate->returnData['success']['floorplanObjectData'] = $floorplanObjectData;
			
		} else if($action == 'getFloorplanObjectPeerTable') {
			// Retrieve floorplan data
			$floorplan = $qls->App->envTreeArray[$cabinetID];
			$floorplanImg = $floorplan['floorplan_img'];
			
			if(isset($floorplanImg)) {
				$validate->returnData['success']['floorplanImg'] = $floorplanImg;
			} else {
				$validate->returnData['success']['floorplanImg'] = false;
			}
			
			// Retrieve object data
			$floorplanObjectData = array();
			$floorplanObjectPeerTable = array();
			$query = $qls->SQL->select('*', 'app_object', array('env_tree_id' => array('=', $cabinetID)));
			while($object = $qls->SQL->fetch_assoc($query)) {
				$objectID = $object['id'];
				$objectName = $object['name'];
				$type = $qls->App->compatibilityArray[$object['template_id']][0][0]['templateType'];
				if($peerRecord = $qls->App->peerArrayWalljack[$objectID]) {
					foreach($peerRecord as $peer) {
						$peerEntryID = $peer['rowID'];
						$peerID = $peer['id'];
						$peerFace = $peer['face'];
						$peerDepth = $peer['depth'];
						$peerPort = $peer['port'];
						$selfPortID = $peer['selfPortID'];
						$portFlags = $qls->App->getPortFlags($objectID, 0, 0, $selfPortID);
						$peerTemplateID = $qls->App->objectArray[$peerID]['template_id'];
						$peerCompatibility = $qls->App->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth];
						$peerPortNameFormat = $peerCompatibility['portNameFormat'];
						$peerPortLayoutX = $peerCompatibility['portLayoutX'];
						$peerPortLayoutY = $peerCompatibility['portLayoutY'];
						$peerPortTotal = $peerPortLayoutX * $peerPortLayoutY;
						$peerPortNameFormat = json_decode($peerPortNameFormat, true);
						
						$peerPortName = $qls->App->generatePortName($peerPortNameFormat, $peerPort, $peerPortTotal);
						$objectPeerTableArray = array(
							'objID' => $objectID,
							'objName' => $objectName,
							'peerPortName' => $peerPortName.$portFlags,
							'peerEntryID' => $peerEntryID,
							'portID' => $peer['selfPortID']
						);
						array_push($floorplanObjectPeerTable, $objectPeerTableArray);
					}
				} else {
					if($type == 'walljack') {
						$peerPortName = 'None';
						$portID = null;
					} else {
						$portFlags = $qls->App->getPortFlags($objectID, 0, 0, 0);
						$peerPortName = 'NIC1'.$portFlags;
						$portID = 0;
					}
					$objectPeerTableArray = array(
						'objID' => $objectID,
						'objName' => $objectName,
						'peerPortName' => $peerPortName,
						'peerEntryID' => 0,
						'portID' => $portID
					);
					array_push($floorplanObjectPeerTable, $objectPeerTableArray);
				}
			}
			
			$validate->returnData['success']['floorplanObjectPeerTable'] = $floorplanObjectPeerTable;
		} else if($action == 'trunkPeer') {
			
			$valueArray = explode('-', $data['value']);
			$elementType = $valueArray[0];
			$elementID = $valueArray[1];
			$elementFace = $valueArray[2];
			$elementDepth = $valueArray[3];
			$elementPortIndex = $valueArray[4];
			$objectID = $data['objectID'];
			$objectFace = $data['objectFace'];
			$objectDepth = $data['objectDepth'];
	
			$query = $qls->SQL->select('*', 'app_object', array('id' => array('=', $objectID)));
			$object = $qls->SQL->fetch_assoc($query);
	
			$query = $qls->SQL->select('partitionFunction', 'app_object_compatibility', array('template_id' => array('=', $object['template_id'])));
			$partitionFunction = $qls->SQL->fetch_assoc($query);
			$objectEndpoint = $partitionFunction['partitionFunction'] == 'Endpoint' ? 1 : 0;
			
			$query = $qls->SQL->select('*', 'app_object', array('id' => array('=', $elementID)));
			$element = $qls->SQL->fetch_assoc($query);
			
			$query = $qls->SQL->select('partitionFunction', 'app_object_compatibility', array('template_id' => array('=', $element['template_id'])));
			$partitionFunction = $qls->SQL->fetch_assoc($query);
			$elementEndpoint = $partitionFunction['partitionFunction'] == 'Endpoint' ? 1 : 0;
			
			// Validate trunk peers are not both endpoints
			if($objectEndpoint == 1 and $elementEndpoint == 1) {
				$errMsg = 'Cannot trunk between two endpoints.';
				array_push($validate->returnData['error'], $errMsg);
			}
			
			// Validate endpoint peer is not connected or populated
			$endpointConnected = false;
			if($objectEndpoint == 1) {
				if(isset($qls->App->peerArrayStandard[$objectID][$objectFace][$objectDepth]) or isset($qls->App->populatedPortArray[$objectID][$objectFace][$objectDepth])) {
					$endpointConnected = true;
				}
			} else if ($elementEndpoint == 1) {
				if(isset($qls->App->peerArrayStandard[$elementID][$elementFace][$elementDepth]) or isset($qls->App->populatedPortArray[$elementID][$elementFace][$elementDepth])) {
					$endpointConnected = true;
				}
			}
			if($endpointConnected === true) {
				$errMsg = 'Cannot trunk endpoint that is populated or connected.';
				array_push($validate->returnData['error'], $errMsg);
			}
			
			// Validate trunk peers are not connected to each other
			if(isset($qls->App->inventoryArray[$objectID][$objectFace][$objectDepth])) {
				$peersConnected = false;
				foreach($qls->App->inventoryArray[$objectID][$objectFace][$objectDepth] as $objPort) {
					foreach($objPort as $objConnection) {
						if($objConnection['id'] == $elementID and $objConnection['face'] == $elementFace and $objConnection['depth'] == $elementDepth) {
							$peersConnected = true;
						}
					}
				}
				if($peersConnected) {
					$errMsg = 'Cannot trunk partitions that are connected to each other.';
					array_push($validate->returnData['error'], $errMsg);
				}
			}
			
			if(!count($validate->returnData['error'])) {
			
				$qls->SQL->delete('app_object_peer', array('a_id' => array('=', $objectID), 'AND', 'a_face' => array('=', $objectFace), 'AND', 'a_depth' => array('=', $objectDepth)));
				$qls->SQL->delete('app_object_peer', array('b_id' => array('=', $objectID), 'AND', 'b_face' => array('=', $objectFace), 'AND', 'b_depth' => array('=', $objectDepth)));
				$qls->SQL->delete('app_object_peer', array('a_id' => array('=', $elementID), 'AND', 'a_face' => array('=', $elementFace), 'AND', 'a_depth' => array('=', $elementDepth)));
				$qls->SQL->delete('app_object_peer', array('b_id' => array('=', $elementID), 'AND', 'b_face' => array('=', $elementFace), 'AND', 'b_depth' => array('=', $elementDepth)));
				$qls->SQL->insert(
					'app_object_peer',
					array(
						'a_id',
						'a_face',
						'a_depth',
						'a_endpoint',
						'b_id',
						'b_face',
						'b_depth',
						'b_endpoint'
					),
					array(
						$objectID,
						$objectFace,
						$objectDepth,
						$objectEndpoint,
						$elementID,
						$elementFace,
						$elementDepth,
						$elementEndpoint
					)
				);
			}
			
			$validate->returnData['success']['trunkFlatPath'] = $qls->App->buildTrunkFlatPath($elementID, $elementFace, $elementDepth);
			
		} else if($action == 'trunkFloorplanPeer') {
			
			$objectID = $data['objectID'];
			$templateID = $qls->App->objectArray[$objectID]['template_id'];
			$templateFunction = $qls->App->templateArray[$templateID]['templateFunction'];
			$value = $data['value'];
			
			$addArray = array();
			$deleteArray = array();
			$portIDArray = array();
			$peerPortArray = array();
			
			// Find Deletes
			if(isset($qls->App->peerArrayWalljack[$objectID])) {
				foreach($qls->App->peerArrayWalljack[$objectID] as $walljackPeerEntry) {
					array_push($portIDArray, $walljackPeerEntry['selfPortID']);
					$delete = true;
					foreach($value as $peer) {
						$peerArray = explode('-', $peer);
						$peerType = $peerArray[0];
						$peerID = $peerArray[1];
						$peerFace = $peerArray[2];
						$peerDepth = $peerArray[3];
						$peerPort = $peerArray[4];
						
						if($walljackPeerEntry['id'] == $peerID and $walljackPeerEntry['face'] == $peerFace and $walljackPeerEntry['depth'] == $peerDepth and $walljackPeerEntry['port'] == $peerPort) {
							$delete = false;
						}
					}
					
					if($delete) {
						array_push($deleteArray, $walljackPeerEntry);
					}
				}
			}
			
			// Find Adds/Deletes
			foreach($value as $peer) {
				$peerArray = explode('-', $peer);
				$peerType = $peerArray[0];
				$peerID = $peerArray[1];
				$peerFace = $peerArray[2];
				$peerDepth = $peerArray[3];
				$peerPort = $peerArray[4];
				$peerData = array(
					'id' => $peerID,
					'face' => $peerFace,
					'depth' => $peerDepth,
					'port' => $peerPort
				);
				
				$peerEntry = $qls->App->peerArrayStandardFloorplan[$peerID][$peerFace][$peerDepth][$peerPort];
				if($peerEntry) {
					if($peerEntry['id'] != $objectID) {
						array_push($deleteArray, $peerEntry);
					}
				} else {
					array_push($addArray, $peerData);
				}
			}
			
			// Delete peer entries
			foreach($deleteArray as $entry) {
				// Delete from inventory table
				if($inventoryPort = $qls->App->inventoryArray[$entry['id']][$entry['face']][$entry['depth']][$entry['port']]) {
					foreach($inventoryPort as $inventoryEntry) {
						$rowID = $inventoryEntry['rowID'];
						if($inventoryEntry['localEndID'] === 0 and $inventoryEntry['remoteEndID'] === 0) {
							// If this is an unmanaged connection, delete the entry
							$qls->SQL->delete('app_inventory', array('id' => array('=', $rowID)));
						} else {
							// If this is a managed connection, just clear the data
							$attrPrefix = $inventoryEntry['localAttrPrefix'];
							$set = array(
								$attrPrefix.'_object_id' => 0,
								$attrPrefix.'_object_face' => 0,
								$attrPrefix.'_object_depth' => 0,
								$attrPrefix.'_port_id' => 0
							);
							$qls->SQL->update('app_inventory', $set, array('id' => array('=', $rowID)));
							if(isset($qls->App->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']])) {
								$qls->App->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']]['id'] = 0;
								$qls->App->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']]['face'] = 0;
								$qls->App->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']]['depth'] = 0;
								$qls->App->inventoryArray[$inventoryEntry['id']][$inventoryEntry['face']][$inventoryEntry['depth']][$inventoryEntry['port']]['port'] = 0;
							}
						}
					}
					unset($qls->App->inventoryArray[$entry['id']][$entry['face']][$entry['depth']][$entry['port']]);
				}
				
				// Delete from populated port table
				if($populatedPortEntry = $qls->App->populatedPortArray[$entry['id']][$entry['face']][$entry['depth']][$entry['port']]) {
					$rowID = $populatedPortEntry['rowID'];
					$qls->SQL->delete('app_populated_port', array('id' => array('=', $rowID)));
					unset($qls->App->populatedPortArray[$entry['id']][$entry['face']][$entry['depth']][$entry['port']]);
				}
				
				// Delete from object peer table
				$qls->SQL->delete('app_object_peer', array('id' => array('=', $entry['rowID'])));
				unset($qls->App->peerArrayWalljack[$entry['selfID']]);
			}
			
			// If necessary, clear inventory table
			if($templateFunction == 'Endpoint') {
				if(isset($qls->App->inventoryArray[$objectID])) {
					foreach($qls->App->inventoryArray[$objectID] as $faceID => $face) {
						foreach($face as $depthID => $depth) {
							foreach($depth as $portID => $port) {
								$deleteEntry = false;
								foreach($port as $connection) {
									$rowID = $connection['rowID'];
									if($connection['localEndID'] == 0 and $connection['remoteEndID'] == 0) {
										// Delete entry if not managed cable
										$qls->SQL->delete('app_inventory', array('id' => array('=', $rowID)));
										$deleteEntry = true;
									} else {
										// Clear entry if managed cable
										$localAttrPrefix = $connection['localAttrPrefix'];
										$set = array(
											$localAttrPrefix.'_object_id' => 0,
											$localAttrPrefix.'_object_face' => 0,
											$localAttrPrefix.'_object_depth' => 0,
											$localAttrPrefix.'_port_id' => 0
										);
										$qls->SQL->update('app_inventory', $set, array('id' => array('=', $rowID)));
									}
								}
								if($deleteEntry) {
									unset($qls->App->inventoryArray[$objectID][$faceID][$depthID][$portID]);
								}
							}
						}
					}
				}
			}
			
			// Add peer entries
			foreach($addArray as $entry) {
				
				// Find first available port id
				$objectPortIndex = 0;
				$found = false;
				while($objectPortIndex < MAX_WALLJACK_PORTID and !$found) {
					if(!in_array($objectPortIndex, $portIDArray)) {
						array_push($portIDArray, $objectPortIndex);
						$found = true;
					} else {
						$objectPortIndex++;
					}
				}
				
				if($found) {
					$peerID = $entry['id'];
					$peerFace = $entry['face'];
					$peerDepth = $entry['depth'];
					$peerPortIndex = $entry['port'];
					
					$object = $qls->App->objectArray[$objectID];
					$objectTemplateID = $object['template_id'];
					$objectTemplate = $qls->App->templateArray[$objectTemplateID];
					$objectEndpoint = $objectTemplate['templateFunction'] == 'Endpoint' ? 1 : 0;
					
					$peer = $qls->App->objectArray[$peerID];
					$peerTemplateID = $peer['template_id'];
					$peerTemplate = $qls->App->templateArray[$peerTemplateID];
					$peerEndpoint = $peerTemplate['templateFunction'] == 'Endpoint' ? 1 : 0;
					
					$qls->SQL->insert(
						'app_object_peer',
						array(
							'a_id',
							'a_face',
							'a_depth',
							'a_port',
							'a_endpoint',
							'b_id',
							'b_face',
							'b_depth',
							'b_port',
							'b_endpoint',
							'floorplan_peer'
						),
						array(
							$objectID,
							0,
							0,
							$objectPortIndex,
							$objectEndpoint,
							$peerID,
							$peerFace,
							$peerDepth,
							$peerPortIndex,
							$peerEndpoint,
							1
						)
					);
				} else {
					$errMsg = 'Could not find available walljack port ID.';
					array_push($validate->returnData['error'], $errMsg);
				}
			}
				
			$trunkFlatPath = count($value) ? 'Yes' : 'No';
			$validate->returnData['success']['trunkFlatPath'] = $trunkFlatPath;
			
		} else if($action == 'clearTrunkPeer') {
			
			$objectID = $data['objectID'];
			$objectFace = $data['objectFace'];
			$objectDepth = $data['objectDepth'];
			
			$objectPeerEntry = $qls->App->peerArray[$objectID][$objectFace][$objectDepth];
			$peerID = $objectPeerEntry['peerID'];
			$peerFace = $objectPeerEntry['peerFace'];
			
			if($objectPeerEntry['floorplanPeer']) {
				$qls->SQL->delete('app_object_peer', array('a_id' => array('=', $objectID), 'AND', 'a_face' => array('=', $objectFace), 'AND', 'a_depth' => array('=', $objectDepth)));
				$qls->SQL->delete('app_object_peer', array('b_id' => array('=', $objectID), 'AND', 'b_face' => array('=', $objectFace), 'AND', 'b_depth' => array('=', $objectDepth)));
			} else {
				$objectPeerEntryID = $objectPeerEntry['id'];
				$qls->SQL->delete('app_object_peer', array('id' => array('=', $objectPeerEntryID)));
			}
			
			$validate->returnData['success']['trunkFlatPath'] = 'None';
			$validate->returnData['success']['peerID'] = $peerID;
			$validate->returnData['success']['peerFace'] = $peerFace;
			
		} else if($action == 'clearFloorplanTrunkPeer') {
			
			$objectID = $data['objectID'];
			
			$qls->SQL->delete('app_object_peer', array('a_id' => array('=', $objectID)));
			$validate->returnData['success']['trunkFlatPath'] = 'None';
			
		} else if($action == 'combinedTemplate') {
			
			$templateName = $data['name'];
			$categoryID = $data['category'];
			$parentObjID = $data['parentObjID'];
			
			$parentTemplateID = $qls->App->objectArray[$parentObjID]['template_id'];
			$childTemplateArray = array();
			foreach($qls->App->insertArray[$parentObjID] as $insertObj) {
				$insertTemplateID = $insertObj['template_id'];
				$insertName = $insertObj['name'];
				$encX = $insertObj['insertSlotX'];
				$encY = $insertObj['insertSlotY'];
				$parentFace = $insertObj['parent_face'];
				$parentDepth = $insertObj['parent_depth'];
				
				$workingArray = array(
					'templateID' => $insertTemplateID,
					'name' => $insertName,
					'encX' => $encX,
					'encY' => $encY,
					'parentFace' => $parentFace,
					'parentDepth' => $parentDepth
				);
				array_push($childTemplateArray, $workingArray);
			}
			
			$childTemplateJSON = json_encode($childTemplateArray);
			
			$qls->SQL->insert(
				'app_combined_templates',
				array(
					'templateName',
					'template_id',
					'templateCategory_id',
					'childTemplateData'
				),
				array(
					$templateName,
					$parentTemplateID,
					$categoryID,
					$childTemplateJSON
				)
			);
		}
	}
	echo json_encode($validate->returnData);
}

function validate($data, &$validate, &$qls){
	$actionArray = array(
		'get',
		'adj',
		'path',
		'distance',
		'notes',
		'delete',
		'new',
		'RU',
		'getFloorplan',
		'getFloorplanObjectPeerTable',
		'trunkPeer',
		'trunkFloorplanPeer',
		'clearTrunkPeer',
		'clearFloorplanTrunkPeer',
		'RUOrientation',
		'updateCabinetRUMin',
		'combinedTemplate'
	);
	$action = $data['action'];
	
	if($validate->validateInArray($action, $actionArray, 'process action')) {
		
		$cabinetIDValidationArray = array(
			'get',
			'getFloorplan',
			'getFloorplanObjectPeerTable',
			'updateCabinetRUMin',
			'adj',
			'RU',
			'new',
			'RUOrientation'
		);
		
		$cabinetValidationArray = array(
			'adj',
			'RUOrientation',
			'RU',
			'updateCabinetRUMin'
		);
		
		if(in_array($action, $cabinetIDValidationArray)) {
			
			//Validate cabinet ID
			$cabinetID = $data['cabinetID'];
			$validCabinetID = $validate->validateCabinetID($cabinetID);
		}
		
		if(in_array($action, $cabinetValidationArray)) {
			
			if($validCabinetID) {
				if(isset($qls->App->envTreeArray[$cabinetID])) {
					if($qls->App->envTreeArray[$cabinetID]['type'] != 'cabinet') {
						$errMsg = 'Not a cabinet.';
						array_push($validate->returnData['error'], $errMsg);
					}
				} else {
					$errMsg = 'Cabinet does not exist.';
					array_push($validate->returnData['error'], $errMsg);
				}
			}
		}
		
		if($action == 'adj') {
			
			//Validate side
			$sideArray = array('adjCabinetSelectL', 'adjCabinetSelectR');
			$validate->validateInArray($data['side'], $sideArray, 'adjacency side');
			
			//Validate adjacent cabinet ID
			$adjCabinetID = $data['adjCabinetID'];
			$validate->validateObjectID($adjCabinetID);
			if($adjCabinetID == $cabinetID) {
				$errMsg = 'Cabinet cannot be adjacent to itself.';
				array_push($validate->returnData['error'], $errMsg);
			}
		} else if($action == 'path') {
			
			//Validate path ID
			$pathID = $data['pathID'];
			$validate->validateObjectID($pathID);
		} else if($action == 'distance') {
			
			//Validate path ID
			$pathID = $data['pathID'];
			$validate->validateObjectID($pathID);
		} else if($action == 'entrance') {
			
			//Validate path ID
			$pathID = $data['pathID'];
			$validate->validateObjectID($pathID);
		} else if($action == 'notes') {
			
			//Validate path ID
			$pathID = $data['pathID'];
			$validate->validateObjectID($pathID);
		} else if($action == 'delete') {
			
			//Validate path ID
			$pathID = $data['pathID'];
			$validate->validateObjectID($pathID);
		} else if($action == 'trunkPeer') {
			
			// Validate Global ID
			$globalID = $data['value'];
			$validate->validateGlobalID($globalID);
			
			$globalIDArray = explode('-', $globalID);
			$elementID = $globalIDArray[1];
			$objectID = $data['objectID'];
			if ($objectID == $elementID){
				$errMsg = 'Cannot trunk port groups in the same object.';
				array_push($validate->returnData['error'], $errMsg);
			}
		} else if($action == 'trunkFloorplanPeer') {
			
			$value = $data['value'];
			$peerPortArray = array();
			
			// Validate peer port names are not similar
			foreach($value as $peer) {
				$peerArray = explode('-', $peer);
				$peerType = $peerArray[0];
				$peerID = $peerArray[1];
				$peerFace = $peerArray[2];
				$peerDepth = $peerArray[3];
				$peerPort = $peerArray[4];
				$peerIsValid = (isset($qls->App->objectArray[$peerID])) ? true : false;
				
				if($peerIsValid) {
					$peer = $qls->App->objectArray[$peerID];
					$peerTemplateID = $peer['template_id'];
					$peerPartIsValid = (isset($qls->App->compatibilityArray[$peerTemplateID][$peerFace][$peerDepth])) ? true : false;
				} else {
					$peerPartIsValid = false;
				}
				
				if($peerIsValid and $peerPartIsValid) {
					$peerObjPortName = $qls->App->generateObjectPortName($peerID, $peerFace, $peerDepth, $peerPort);
					$peerObjPortNameArray = explode('.', $peerObjPortName);
					$peerPortName = array_pop($peerObjPortNameArray);
					
					// Validate walljack portnames will be unique
					if(in_array($peerPortName, $peerPortArray)) {
						$errMsg = 'Cannot trunk floorplan object to multiple peer ports of the same name ('.$peerPortName.').';
						array_push($validate->returnData['error'], $errMsg);
					} else {
						array_push($peerPortArray, $peerPortName);
					}
					
					// Validate peer is not a connected or populated endpoint
					if($qls->App->templateArray[$peerTemplateID]['templateFunction'] == 'Endpoint') {
						if(isset($qls->App->populatedPortArray[$peerID][$peerFace][$peerDepth][$peerPort]) or isset($qls->App->inventoryArray[$peerID][$peerFace][$peerDepth][$peerPort])) {
							$errMsg = 'Cannot trunk floorplan object to endpoint ports which are connected.';
							array_push($validate->returnData['error'], $errMsg);
						}
					}
				}
			}
			
		} else if($action == 'clearTrunkPeer') {
			
		} else if($action == 'clearFloorplanTrunkPeer') {
			$objectID = $data['objectID'];
			$validate->validateObjectID($objectID);
		} else if($action == 'RU' or $action == 'new') {
			
			$result = $qls->SQL->select('*', 'app_env_tree', array('id' => array('=', $cabinetID)));
			if ($qls->SQL->num_rows($result) == 0) {
				$errMsg = 'Cabinet does not exist.';
				array_push($validate->returnData['error'], $errMsg);
			}
		} else if($action == 'RUOrientation') {
			
			// Validate RU Orientation Value
			$RUOrientation = $data['value'];
			$RUOrientationArray = array(0, 1);
			$validate->validateInArray($RUOrientation, $RUOrientationArray, 'RU orientation value');
			
		} else if($action == 'combinedTemplate') {
			
			// Validate template name
			$templateName = $data['name'];
			$validate->validateNameText($templateName, 'template name');
			
			// Validate category ID
			$categoryID = $data['category'];
			$validate->validateCategoryID($categoryID);
			
			// Validate parent template ID
			$parentTemplateID = $data['parentObjID'];
			$validate->validateID($parentTemplateID, 'parent object ID');
			
			$table = 'app_object_templates';
			$where = array('templateName' => array('=', $templateName));
			$errMsg = 'duplicate template name';
			$validate->validateDuplicate($table, $where, $errMsg);
			$table = 'app_combined_templates';
			$validate->validateDuplicate($table, $where, $errMsg);
			
		}
	}
}

function getCabinetParentID($cabinetParentID, &$qls){
	$query = $qls->SQL->select('*', 'app_env_tree', array('id' => array('=', $cabinetParentID)));
	$cabinetParent = $qls->SQL->fetch_assoc($query);
	if($cabinetParent['type'] == 'pod') {
		return getCabinetParentID($cabinetParent['parent'], $qls);
	}
	return $cabinetParent['id'];
}

function getChildCabinets($cabinetID, $cabinetParentID, &$qls, $parentName='', $childCabinets=array()){
	$query = $qls->SQL->select('*', 'app_env_tree', array('parent' => array('=', $cabinetParentID)));
	while($row = $qls->SQL->fetch_assoc($query)) {
		if($row['type'] == 'cabinet') {
			if($row['id'] != $cabinetID) {
				array_push($childCabinets, array('value' => $row['id'], 'text' => $parentName.'.'.$row['name']));
			}
		} else {
			$separator = $parentName == '' ? '' : '.';
			$parentName .= $separator.$row['name'];
			$childCabinets = getChildCabinets($cabinetID, $row['id'], $qls, $parentName, $childCabinets);
			$parentName = '';
		}
	}
	return $childCabinets;
}

function getLocalCabinets($cabinetID, &$qls){
	$localCabinetArray = array();
	$query = $qls->SQL->select('*', 'app_env_tree', array('id' => array('=', $cabinetID)));
	$cabinet = $qls->SQL->fetch_assoc($query);
	$cabinetParentID = $cabinet['parent'];
	$query = $qls->SQL->select('*', 'app_env_tree', array('parent' => array('=', $cabinetParentID)));
	while($row = $qls->SQL->fetch_assoc($query)) {
		if($row['id'] != $cabinetID) {
			array_push($localCabinetArray, array('value' => $row['id'], 'text' => $row['name']));
		}
	}
	array_push($localCabinetArray, array('text' => '----', 'children' => array(array('value' => 0, 'text' => 'Clear'))));
	return $localCabinetArray;
}

?>
