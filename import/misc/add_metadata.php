<?php

require_once '../import_init.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'Handle.php';
$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();
$aItemIds = array();

$nRepositorySetFieldId = 390;
$sRepSetValue = 'kaartenvannederland';
/*
 * Get the handles or item numbers
 * This bit may be different every time
 */
$sImportFiles = 'KH_FilesToDo.php';
require_once $sImportFiles;
foreach ($aProblemFiles as $aOneItem) {
	$sShowHandle = $aOneItem['handle'];
	$sHandle = str_replace('-', '/', $sShowHandle);
	//$aItemIds[] = $sHandle;
	$aHandleData = $oHandle->getItemId($sHandle);
	if (isset($aHandleData['itemid'])) {
		//$aItemIds[] = $aHandleData;
		$nItemId = $aHandleData['itemid'];
		$aItemIds[] = $nItemId;
		$oItemMetadata->addMetadataValue($nItemId, $nRepositorySetFieldId, $sRepSetValue);
		$sToday = date('Y-m-d H:i:s');
		$oItem->updateLastModified($nItemId, $sToday);

	}
}

echo count($aItemIds) . ' itemids found' . "\n";

/*
$nStart = 80;
$nNumber = 20;
$nEnd = $nStart+$nNumber;

for ($i=$nStart; $i<$nEnd; $i++) {
	$nItemId = $aItemIds[$i];
	$oItemMetadata->addMetadataValue($nItemId, $nRepositorySetFieldId, $sRepSetValue);
	$sToday = date('Y-m-d H:i:s');
	$oItem->updateLastModified($nItemId, $sToday);
}
echo "done \n";
 * 
 */