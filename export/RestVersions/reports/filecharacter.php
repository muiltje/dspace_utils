<?php

/* 
 * Find items which have bitstreams with commas in their file names
 */

require_once '../init.php';

require_once (CLASSES . 'Bundle.php');

$oBitstream = new Bitstream();
$oBundle = new Bundle();
$oHandle = new Handle();

$sStringToSearch = ',';

$aBitstreams = $oBitstream->getBitstreamsByNameContains($sStringToSearch);

$aHandlesToCheck = array();

foreach ($aBitstreams as $key => $aOneBitstream) {
	$nBitstreamId = $aOneBitstream['bitstream_id'];
	$bDeleted = $aOneBitstream['deleted'];
	
	if ($bDeleted == 'f') {
		$nBundleId = $oBitstream->getBitstreamBundle($nBitstreamId);
		if ($nBundleId != 0) {
			$nItemId = $oBundle->getBundleItem($nBundleId);
			//if ($key == 3) {
			//	echo $nItemId;
			//}
			if ($nItemId != 0) {
				//get the handle
				$aHandleData = $oHandle->getHandle($nItemId);
				if (empty($aHandleData)) {
					echo 'there is no handle for item ' . $nItemId . "\n";
				}
				else {
					$sHandle = $aHandleData['handle'];
					$aHandlesToCheck[] = $sHandle;
				}
			}
			else {
				//orphaned bundle
			}
		}
		else {
			//orphaned bitstream
		}
	}
	else {
		//nothing to do
	}
}


echo count($aHandlesToCheck) . ' handles to check ' . "\n";

$sOutput = var_export($aHandlesToCheck, true);
$sOutputFile = 'fileswithproblems.php';
file_put_contents($sOutputFile, $sOutput);

echo "written \n";
