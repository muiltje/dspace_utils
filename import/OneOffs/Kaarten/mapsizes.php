<?php

require_once '../init.php';
require_once CLASSES . 'Bundle.php';

$oBitstream = new Bitstream();
$oBundle = new Bundle();
$oHandle = new Handle();

$nTotalFortification = 0;
$nTotalWatermanagement = 0;

$sFileOne = 'FortificationHandles.txt';
$fh = fopen($sFileOne, "r");
while(($buffer = fgets($fh)) !== false) {
	$sGivenHandle = trim($buffer);
	//echo $sHandle . "\n";
	$sHandle = preg_replace('/-/', '/', $sGivenHandle);
	$aHandleData = $oHandle->getItemId($sHandle);
	//print_r($aHandleData);
	$nItemId = $aHandleData['itemid'];
	$aBundles = $oBundle->getItemBundles($nItemId);
	
	foreach ($aBundles as $aOneBundle) {
		$nBundleId = $aOneBundle['bundle_id'];
		$aBundleDetails = $oBundle->getBundleDetails($nBundleId);
		if (isset ($aBundleDetails['name']) && $aBundleDetails['name'] != 'LICENSE') {
			$aBundleBitstreams = $oBitstream->getBundleBitstreams($nBundleId);
			foreach ($aBundleBitstreams as $aOneBitstream) {
				$nBitstreamId = $aOneBitstream['bitstream_id'];   
				$aBitstreamData = $oBitstream->getBitstreamData($nBitstreamId);
				//print_r($aBitstreamData);
				$nByteSize = $aBitstreamData['size_bytes'];
				$nTotalFortification += $nByteSize;
			}
		}
	}
}
fclose($fh);
//echo $nTotalFortification . "\n";
$sFortificationSize = parseTotal($nTotalFortification);
echo $sFortificationSize . "\n";

$sFileTwo = 'WatermanagementHandles.txt';
$fht = fopen($sFileTwo, "r");
while(($buffer = fgets($fht)) !== false) {
	$sGivenHandle = trim($buffer);
	//echo $sHandle . "\n";
	$sHandle = preg_replace('/-/', '/', $sGivenHandle);
	$aHandleData = $oHandle->getItemId($sHandle);
	//print_r($aHandleData);
	$nItemId = $aHandleData['itemid'];
	$aBundles = $oBundle->getItemBundles($nItemId);
	
	foreach ($aBundles as $aOneBundle) {
		$nBundleId = $aOneBundle['bundle_id'];
		$aBundleDetails = $oBundle->getBundleDetails($nBundleId);
		if (isset ($aBundleDetails['name']) && $aBundleDetails['name'] != 'LICENSE') {
			$aBundleBitstreams = $oBitstream->getBundleBitstreams($nBundleId);
			foreach ($aBundleBitstreams as $aOneBitstream) {
				$nBitstreamId = $aOneBitstream['bitstream_id'];   
				$aBitstreamData = $oBitstream->getBitstreamData($nBitstreamId);
				//print_r($aBitstreamData);
				$nByteSize = $aBitstreamData['size_bytes'];
				$nTotalWatermanagement += $nByteSize;
			}
		}
	}
}
fclose($fht);
//echo $nTotalWatermanagement . "\n";
$sWaterSize = parseTotal($nTotalWatermanagement);
echo $sWaterSize . "\n";

$nTotalMapSize = $nTotalFortification + $nTotalWatermanagement;
$sTotalSize = parseTotal($nTotalMapSize);
echo 'Total is ' . $sTotalSize . "\n";


function parseTotal($nBytes)
{
        $aTypes = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        
        for ($i = 0; $nBytes >= 1024 && $i < ( count($aTypes ) -1 ); $nBytes /= 1024, $i++ );
        $sSize = (round($nBytes, 2) . " " . $aTypes[$i]);
        
        return $sSize;
    }