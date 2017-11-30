<?php


require_once '../init.php';

$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();

$aItems = $oItemMetadata->tempGet();

foreach ($aItems as $nItemId) {
	$aHandleData = $oHandle->getHandle($nItemId);
	echo $aHandleData['handle'] . "\n";
}
