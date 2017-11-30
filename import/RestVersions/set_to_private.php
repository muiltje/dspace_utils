<?php
/* 
 * All items in the Quality Control Scan collection should be set to private / not discoverable
 * This scripts sees to that
 */

require_once 'import_init.php';

require_once CLASSES . 'Item.php';

$oItem = new Item();

//$nCollectionId = 778; //TEST!!!
$nCollectionId = 867; //PROD
$sLastModifiedDate = date('Y-m-d', strtotime("-2 day"));

//get recently added/modified items
$aItems = $oItem->getModifiedItems($sLastModifiedDate);

foreach ($aItems as $aOneItem) {
	//determine collection
	$nOwningCollection = $aOneItem['owning_collection'];

	//if in the wanted collection:
	if ($nOwningCollection == $nCollectionId) {
		$nItemId = $aOneItem['item_id'];
		//update item: set discoverable to f
		$oItem->setUndiscoverable($nItemId);
		wlog('set ' . $nItemId . ' to private', 'INF');
	}

}

