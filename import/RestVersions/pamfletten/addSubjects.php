<?php

/* 
 * Take the list VanSomerenToHandle and for each item in it find the subjects
 * in SubjectsPerPamphlet; add these subjects to DSpace
 */

require_once '../import_init.php';

require_once CLASSES . 'Handle.php';
require_once CLASSES . 'ItemMetadata.php';

$oHandle = new Handle();
$oItemMetadata = new ItemMetadata();

$nKeywordFieldId = 131;
$nKeywordOriginalFieldId = 261;

include 'VanSomerenToHandle.php';
include 'SubjectsPerPamphlet.php';
include 'LinkedPamphlets.php';

foreach ($saVanSomerenHandles as $sVSName => $sHandle) {
	$aItemData = $oHandle->getItemId($sHandle);
	$nItemId = $aItemData['itemid'];
	// echo $nItemId . "\n";
	
	$sKeyWords = '';
	if(!empty($aSubjectsPerPamphlet[$sVSName])) {
		$aSubjectData = $aSubjectsPerPamphlet[$sVSName];
		foreach ($aSubjectData as $sKeyword) {
			//echo $sKeyword . ' - '; 
			$sKeyWords .= $sKeyword . ', ';
		}
	}
    $sKeywordLine = substr($sKeyWords, 0, -2);
	
	if ($sKeywordLine != '') {
		echo $sKeywordLine . "\n";
		$oItemMetadata->addMetadataValue($nItemId, $nKeywordOriginalFieldId, $sKeyWords);
	}
	
	
	if (!empty($aRefinedPamphlets[$sVSName])) {
		$aPamphletData =  $aRefinedPamphlets[$sVSName];
		$sLinkedSubjectLine = '';
		if ($aPamphletData['ThingsDBPedia'] != '') {
			$sLinkedSubjectLine .= $aPamphletData['ThingsDBPedia'];
			if ($aPamphletData['Persons DBPedia'] != '') {
				$sLinkedSubjectLine .= ', ' . $aPamphletData['Persons DBPedia'];
			}
		}
		else {
			if ($aPamphletData['Persons DBPedia'] != '') {
				$sLinkedSubjectLine .= $aPamphletData['Persons DBPedia'];
			}
		}
		if ($sLinkedSubjectLine != '') {
			echo $sLinkedSubjectLine . "\n";
			$oItemMetadata->addMetadataValue($nItemId, $nKeywordFieldId, $sLinkedSubjectLine);
		}
	}
	
	echo "\n";
			

}
