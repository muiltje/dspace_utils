<?php

ini_set("auto_detect_line_endings", true);
require_once '../init.php';

$oHandle = new Handle();
$oItemMetadata = new ItemMetadata();

$sBaseFile = 'KaartenHolland_workingcopy.csv';
$aHandles = array();


$fh = fopen($sBaseFile, "r");
while (($buffer = fgets($fh)) !== false) {
	$aData = explode(';', $buffer);
	$sIdentifier = $aData[6];
	
	if (preg_match('/^1874/', $sIdentifier)) {
		$aItemDetails = getItemId($sIdentifier, $oHandle);
		$aHandles[] = $aItemDetails;
	}
	elseif (preg_match('/^02/', $sIdentifier)) {
		$aHandleDetails = getHandle($sIdentifier, $oItemMetadata, $oHandle);
		$aHandles[] = $aHandleDetails;
	}
}

echo count($aHandles) . ' handles' . "\n";

$sOutput = var_export($aHandles, true);
$sOutputFile = 'KaartenHolland_Handles.php';
file_put_contents($sOutputFile, $sOutput);



function getHandle($sIdentifier, $oItemMetadata, $oHandle)
{
	$aItemData = $oItemMetadata->findItemByMetadata(71, $sIdentifier);

	$aHandle = array();
	if (!empty($aItemData)) {
		foreach ($aItemData['itemids'] as $nItemId) {
			$aHandle['itemid'] = $nItemId;
			$aHandleData = $oHandle->getHandle($nItemId);
			$sHandle = $aHandleData['handle'];
			$aHandle['handle'] = $sHandle;
		}
	}
	
	return $aHandle;
}


function getItemId($sHandle, $oHandle)
{
	$aItemData = $oHandle->getItemId($sHandle);
	$nItemId = $aItemData['itemid'];
	
	$aHandleDetails = array('handle' => $sHandle, 'itemid' => $nItemId);
	
	return $aHandleDetails;
}