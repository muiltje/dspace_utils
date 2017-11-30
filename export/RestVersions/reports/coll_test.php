<?php

require_once '../init.php';
require_once CLASSES . 'RestCollection.php';

$oCollection = new RestCollection();

$nCollectionId = 69;

$aCollectionData = $oCollection->getCollectionData($nCollectionId);
$nTotalNumberOfCollectionItems = $aCollectionData['numberItems'];
echo 'This collection holds ' . $nTotalNumberOfCollectionItems . ' items';
echo "\n";

//$aItems = $oCollection->getCollectionItems($nCollectionId, $nTotalNumberOfCollectionItems);
//echo count($aItems);
//print_r($aItems);

$aItemsWithMetadata = $oCollection->getCollectionItemMetadata($nCollectionId, $nTotalNumberOfCollectionItems);

//print_r($aItemsWithMetadata[13]);

$aTestItem = $aItemsWithMetadata[13];
$aMetadata = $aTestItem['metadata'];
$sDateIssued = '';
$sDateAvailable = '';
foreach ($aMetadata as $aField) {
	$sFieldName = $aField['key'];
	if ($sFieldName == 'dc.date.issued') {
		$sDateIssued = $aField['value'];
	}
	if ($sFieldName == 'dc.date.available') {
		$sDateAvailable = $aField['value'];
	}
}
echo 'found ' . $sDateIssued . ' and ' . $sDateAvailable . "\n";