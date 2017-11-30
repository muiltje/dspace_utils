<?php

echo "je hebt bnb_een al gedaan \n";
exit();
/* 
 * Add the keyword "Behoud Nederlandse Boekproductie 3" to all BNB3 items
 */
require_once '../import_init.php';
include 'bnb_een_items.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$nStart = 5200;
//$nNumber = 300;
//$nEnd = $nStart + $nNumber;
$nEnd = 5584;

for ($i=$nStart; $i < $nEnd; $i++) {
	$sDigIdentifier = $aBNBDrieItemNumbers[$i];
	if ($sDigIdentifier != '') {
		$nItemId = findItem($sDigIdentifier, $oItemMetadata);
		if ($nItemId !== 0) {
			addField($nItemId, $oItemMetadata);
			//changeLastModified($nItemId, $oItem);
		}
		else {
			echo 'no item for ' . $sDigIdentifier . "\n";
		}
	}
	else {
		echo 'nothing for ' , $i . "\n";
	}
}
echo 'done to ' . $nEnd . "\n";

function findItem($sDigIdentifier, $oItemMetadata)
{
	$aItems = $oItemMetadata->findItemByMetadata(271, $sDigIdentifier);
	
	if (isset($aItems['itemids'])) {
		$nItemId = $aItems['itemids'][0];
		return $nItemId;
	}
	else {
		return 0;
	}
}


function addField($nItemId, $oItemMetadata)
{
	$sText = 'Behoud Nederlandse Boekproductie 1';
	//$oItemMetadata->addMetadataValue($nItemId, 131, $sText);
	$oItemMetadata->updateMetadataValue($nItemId, 131, $sText);
	return 'done';
	
}

function changeLastModified($nItemId, $oItem)
{
    //update last_modified
    $sToday = date('Y-m-d H:i:s');
    $oItem->updateLastModified($nItemId, $sToday);
   
    return;
}