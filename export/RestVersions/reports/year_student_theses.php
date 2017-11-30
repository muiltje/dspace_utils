<?php

require_once '../init.php';

$aStudentThesesCollections = array(56,69,98,99,107,111,112,137,);
$sDateIssuedFieldId = 27;
$sDateAvailableFieldId = 25;

$oItem = new RestItem();
$oItemMetadata = new RestItemMetadata();


/**
 * For each collection: count the total number
 * Then get the date available and count the items where that is 2014
 */

$nTotalNumber = 0;
$aThisYearByCollection = array();

foreach ($aStudentThesesCollections as $nCollectionId) {
    $nThisCollectionYearNumber = 0;

    $aCollectionItems = $oItem->getCollectionItems($nCollectionId);
    $nTotalNumber += count($aCollectionItems);
    //print_r($aCollectionItems[3]);
    
	foreach ($aCollectionItems as $aOneItem) {
        $nFoundItemId = $aOneItem['item_id'];
        $sFoundDateAvailable = '';
        $sFoundDateIssued = '';
        $aDateAvailableData = $oItemMetadata->getMetadataValue($nFoundItemId, $sDateAvailableFieldId);
        if (!empty($aDateAvailableData)) {
            $sFoundDateAvailable = $aDateAvailableData['values'][0];
        }
        $aDateIssuedData = $oItemMetadata->getMetadataValue($nFoundItemId, $sDateIssuedFieldId);
        if (!empty($aDateIssuedData)) {
            $sFoundDateIssued = $aDateIssuedData['values'][0];
        }
        
        if ((substr($sFoundDateAvailable, 0, 4) == '2014') || (substr($sFoundDateIssued, 0, 4) == '2014')) {
            $nThisCollectionYearNumber++;
        }
    }
    
    $aThisYearByCollection[$nCollectionId] = $nThisCollectionYearNumber;
	
}

echo 'Totaal aantal scripties: ' . $nTotalNumber . "\n";
print_r($aThisYearByCollection);
echo "\n";