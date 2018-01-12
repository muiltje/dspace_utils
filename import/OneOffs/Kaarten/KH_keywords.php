<?php

require_once '../init.php';
$oItemMetadata = new ItemMetadata();

require_once 'KaartenHolland_Handles.php';

$sKeyword = 'Kaarten van Nederland';

$aToAdd = array();
foreach ($aKaartenVanNederland as $aOneMap) {
	if (isset($aOneMap['itemid'])) {
		$nItemId = $aOneMap['itemid'];
	
		$aKeywordData = $oItemMetadata->getMetadataValue($nItemId,  131);
		if (empty($aKeywordData)) {
			$aToAdd[] = $nItemId;
		}
		else {
			$sPresent = 'n';
			$aExistingKeywords = $aKeywordData['values'];
			foreach ($aExistingKeywords as $sSubject) {
				if ($sSubject == $sKeyword) {
					$sPresent = 'y';
				}
			}
			if ($sPresent == 'n') {
				$aToAdd[] = $nItemId;
			}
		}
	}
}

echo count($aToAdd) . ' items for which we should add it' . "\n";

/*
foreach ($aToAdd as $nItemId) {
	$oItemMetadata->addMetadataValue($nItemId, 131, $sKeyword);
}
 * 
 */

echo "done \n";