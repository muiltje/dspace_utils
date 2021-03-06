<?php

/* 
 * Find all Digitized Book items that have been published before a certain year.
 * Get their current accessrights
 * If they are currently Restricted Access:
 *	- make Open Access
 *  - set date modified
 * 
 * the addResourcePolicies.php script should now set the anonymous read policies
 * 
 */

require_once '../import_init.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$nCollectionId = 257;
$nCutOffYear = 1877;

$aCollectionItems = $oItem->getCollectionItems($nCollectionId);

$nDateIssuedFieldId = 27;
$nRightsFieldId = 161;

$nCounter = 0;
foreach ($aCollectionItems as $key => $aOneItem) {
    $nItemId = $aOneItem['item_id'];
	
	$aDateIssuedData = $oItemMetadata->getMetadataValue($nItemId, $nDateIssuedFieldId);
	//print_r($aDateIssuedData);
		
	$nYear = (int) ($aDateIssuedData['values'][0]);
	if ($nYear < $nCutOffYear) {
		//echo 'Open Access ' . $nYear;
		$aRightsData = $oItemMetadata->getMetadataValue($nItemId, $nRightsFieldId);
		$sAccessRights = $aRightsData['values'][0];
		if ($sAccessRights == 'Restricted Access') {
			//echo "\n" .  'We can set this to Open ';
			echo $nItemId . ' can be set to Open' . "\n";
			$oItemMetadata->updateMetadataValue($nItemId, $nRightsFieldId, 'Open Access (free)');
			$sToday = date('Y-m-d H:i:s');
			$oItem->updateLastModified($nItemId, $sToday);
			$nCounter++;
		}
	}
	else {
		//echo 'Restricted: ' . $nYear;
	}
}

echo 'We can open ' . $nCounter . ' items ' . "\n";
