<?php
/*
 * Background downloading of a digitised item.
 * This will run on the server and start when it sees a "download this" file.
 * That file can only be generated from dspace-admin, so not by end-users.
 * 
 */

require_once 'init.php';

$oBitstream = new Bitstream();

$sDownloadTextFile = '/tmp/download.txt';

//check if there is a "download this" file
if (file_exists($sDownloadTextFile)) {
    
    //get the item number (this is what will be in the file)
    $sItemIdSeen = file_get_contents($sDownloadTextFile);
    $nItemId = trim($sItemIdSeen);
		
	echo $nItemId . "\n";

	//create the downloaddirectory
    $sDestinationDirectory = '/dspace_queue/temp/download_' . $nItemId;
    echo $sDestinationDirectory . "\n";
    
    //if downloaddirectory exists, we've done this book before, so exit
    if (is_dir($sDestinationDirectory)) {
        echo 'This already exists' . "\n";
		exit();
    }
    else {
        mkdir($sDestinationDirectory);
		
		$aBitstreamData = $oBitstream->getItemBitstreamsRest($nItemId);
		$aBitstreams = $aBitstreamData['bitstreams'];
		//print_r($aBitstreams);
		foreach ($aBitstreams as $aOneBitstream) {
			$nBitstreamId = $aOneBitstream['id'];
			$sBitstreamName = $aOneBitstream['name'];
			
			//exclude the license, until we have a proper one
			if ($sBitstreamName != 'license.txt') {
				$aBitstreamInternal = $oBitstream->getBitstreamInternal($nBitstreamId);
				$nInternalId = $aBitstreamInternal['internal_id'];
				$nStoreNumber = $aBitstreamInternal['store_number'];
				$sSourcePath = $oBitstream->getBitstreamPath($nInternalId, $nStoreNumber);
			
				$sDestinationPath = $sDestinationDirectory . '/' . $sBitstreamName;
				//since we may not have the actual files on the test system, we'll add a check
				if (file_exists($sSourcePath)) {
					//$sText = 'I would copy ' . $sSourcePath . ' to ' . $sDestinationPath;
					//echo $sText . "\n";
					copy($sSourcePath, $sDestinationPath);
				}
				else {
					$sText = 'I would copy ' . $sSourcePath . ' to ' . $sDestinationPath;
					//echo $sText . "\n";
					wlog($sText, 'INF');
				}                
			}
		}
	}
}