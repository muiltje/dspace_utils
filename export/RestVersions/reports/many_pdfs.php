<?php

/**
 * Find items with multiple pdfs in the UUrepository collection
 * For each of these items get version and accessrights fields
 * Also get the names for each pdf
 */

require_once '../init.php';
require_once CLASSES . 'RestCollection.php';

$oCollection = new RestCollection();
$oBitstream = new RestBitstream();
$oItemMetadata = new RestItemMetadata();

$nCollectionId = 587;

//first determine how many items there are in this collection
$aCollectionData = $oCollection->getCollectionData($nCollectionId);
$nTotalNumberOfCollectionItems = $aCollectionData['numberItems'];
echo 'This collection holds ' . $nTotalNumberOfCollectionItems . ' items';
echo "\n";

$nTimes = floor($nTotalNumberOfCollectionItems/100);
//echo 'I will do ' . $nTimes . ' requests';
//echo "\n";

$aItemsFound = array();
/*
 * There will be timeouts for some of the requests, so check if you have a result
 * If not, try again 
 */
for ($i=0; $i<=$nTimes; $i++) {
	$nOffset = $i*100;
	$aFirstTry = $oCollection->getCollectionItemSubset($nCollectionId, $nOffset);
	if (!empty($aFirstTry)) {
		$aItemsFound[] = $aFirstTry;
	}
	else {	
		$aSecondTry = $oCollection->getCollectionItemSubset($nCollectionId, $nOffset);
		if (!empty($aSecondTry)) {
			$aItemsFound[] = $aSecondTry;
		}
		else {
			echo 'Problem with subset ' . $nOffset . "\n";
		}
		
	}
}

//@todo: add an option to only get the items in the collection that were
//added or changed in the last 3 months

$sOutputFile = 'items_with_many_files.csv';
$fh = fopen($sOutputFile, "a");

$aItemsToCheck = array();
foreach ($aItemsFound as $aOneSubset) {
	foreach ($aOneSubset as $aOneItem) {
		$nItemId = $aOneItem['id'];
		$sHandle = $aOneItem['handle'];
		$aBitstreams = $oBitstream->getItemBitstreamsRest($nItemId);
		//normally there should be 2 bitstreams: a license and a text
		//only if we have more than 2 bitstreams should we look at them further
		if (count($aBitstreams) > 2) {
			foreach ($aBitstreams as $aOneBitstream) {
				$sBundle = $aOneBitstream['bundleName'];
				$sFileName = $aOneBitstream['name'];
				$nBitstreamId = $aOneBitstream['id'];
			}
			//we may want to check if this is an item from Pure
			//if it isn't, we skip it because it will be some legacy item
			$aMetaData = $oItemMetadata->getAllMetadata($nItemId);
			$aPureIdentifiers = array();
			//@todo: get version and accessrights
			if (!empty($aMetaData)) {
				foreach ($aMetaData as $aOneMetadata) {
					$sKey = $aOneMetadata['key'];
					if ($sKey == 'dc.identifier.pure') {
						$aPureIdentifiers[] = $aOneMetadata['value'];
					}
				}
			}
			else {
				wlog('Could not find metadata for item ' . $nItemId, 'ERROR');
			}
			
			if (!empty($aPureIdentifiers)) {
				$aItemsToCheck[] = array('item' => $nItemId, 'handle' => $sHandle);
				$sLine = $sHandle . "\n";
				fwrite($fh, $sLine);
			}
		}
	}
}
echo 'found so far: ' . count($aItemsToCheck) . ' items to check';
echo "\n";
fclose($fh);

/*
 * Experience shows that if you do all requests right after each other, 
 * some will get a server error as response. 
 * A test run gave us 347 successful requests out of 466.
 * So we'll give up on automating this for now and do it by hand
 */
/*
$nStart = 300;
$aItemsFound = $oCollection->getCollectionItemSubset($nCollectionId, $nStart);
//print_r($aItems);
foreach ($aItemsFound as $aOneItem) {
	$nItemId = $aOneItem['id'];
	$sHandle = $aOneItem['handle'];
	$sBitstreams = $oBitstream->getItemBitstreamsRest($nItemId);
	$aBitstreams = json_decode($sBitstreams, true);
	//normally there should be 2 bitstreams: a license and a text
	//only if we have more than 2 bitstreams should we look at them further
	if (count($aBitstreams) > 2) {
		foreach ($aBitstreams as $aOneBitstream) {
			$sBundle = $aOneBitstream['bundleName'];
			$sFileName = $aOneBitstream['name'];
			$nBitstreamId = $aOneBitstream['id'];
		}
		//we may want to check if this is an item from Pure
		//if it isn't, we skip it because it will be some legacy item
		$aMetaData = $oItemMetadata->getAllMetadata($nItemId);
		$aPureIdentifiers = array();
		foreach ($aMetaData as $aOneMetadata) {
			$sKey = $aOneMetadata['key'];
			if ($sKey == 'dc.identifier.pure') {
				$aPureIdentifiers[] = $aOneMetadata['value'];
			}
		}
		if (!empty($aPureIdentifiers)) {
			$aItemsToCheck[] = array('item' => $nItemId, 'handle' => $sHandle);
		}
	}
}
echo 'found so far: ' . count($aItemsToCheck) . ' items to check';
echo "\n";

$sOutputFile = 'items_with_many_files.csv';
$fh = fopen($sOutputFile, "a");
foreach ($aItemsToCheck as $aOneCheck) {
	$sLine = $aOneCheck['handle'] . "\n";
	fwrite($fh, $sLine);
} 
fclose($fh);
 * 
 */

echo "\n";