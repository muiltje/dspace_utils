<?php

require_once '../import_init.php';

require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'Handle.php';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();

$nCollectionID = 617;
$nTypeContentFieldId = 146;
$nRightsFieldId = 161;
$nEmbargoDateFieldId = 193;
$nPureIdentifierFieldId = 304;
$nTitleFieldId = 143;
$nAuthorFieldId = 5;

$aItems = $oItem->getCollectionItems($nCollectionID);

echo count($aItems) . ' items in this collection ' . "\n";

$aEmbargoedDisses = array();

foreach ($aItems as $aOneItem) {
	$nItemId = $aOneItem['item_id'];
	
	//find contenttype
	$aContentTypeData = $oItemMetadata->getMetadataValue($nItemId, $nTypeContentFieldId);
	if (!empty($aContentTypeData)) {
		$sTypeContent = $aContentTypeData['values'][0];
		
		//if it's a dissertation, see if is embargoed
		if ($sTypeContent == 'Dissertation') {
			//$aEmbargoedDisses[] = $aOneItem;
			$aRightsData = $oItemMetadata->getMetadataValue($nItemId, $nRightsFieldId);
			if (!empty($aRightsData)) {
				$sRights = $aRightsData['values'][0];
				if ($sRights == 'Embargoed Access') {
					$aTitleData = $oItemMetadata->getMetadataValue($nItemId, $nTitleFieldId);
					$sTitle = $aTitleData['values'][0];
					
					$aAuthorData = $oItemMetadata->getMetadataValue($nItemId, $nAuthorFieldId);
					$sAuthor = $aAuthorData['values'][0];
					
					$aEmbargoData = $oItemMetadata->getMetadataValue($nItemId, $nEmbargoDateFieldId);
					$sEmbargoDate = $aEmbargoData['values'][0];
					
					$aPureData = $oItemMetadata->getMetadataValue($nItemId, $nPureIdentifierFieldId);
					//note: there can be more than one value, we want all of them
					$sPureIdentifiers = '';
					if (!empty($aPureData['values'])) {
						foreach ($aPureData['values'] as $sValue) {
							$sPureIdentifiers .= $sValue;
						}
					}
					
					$aHandleData = $oHandle->getHandle($nItemId);
					$sHandle = $aHandleData['handle'];
					
					$aEmbargoedItem = array(
						'handle' => $sHandle,
						'pure_identifiers' => $sPureIdentifiers,
						'title' => $sTitle,
						'author' => $sAuthor,
						'embargo_date' => $sEmbargoDate,
					);
					$aEmbargoedDisses[] = $aEmbargoedItem; 
				}
			}
		}
	}

}

//print_r($aEmbargoedDisses[5]);
echo count($aEmbargoedDisses) . ' embargoed disses ' . "\n";

$sFile = 'embargoed_disses.csv';
$fh = fopen($sFile, "w");
$sFirstLine = 'Handle; Titel; Auteur; Embargodatum; Pure identifiers' . "\n";
fwrite($fh, $sFirstLine);

foreach ($aEmbargoedDisses as $aOneDiss) {
	$sLine = $aOneDiss['handle'] . ';' 
		. $aOneDiss['title'] . ';' 
		. $aOneDiss['author'] . ';'
		. $aOneDiss['embargo_date'] . ';' 
		. $aOneDiss['pure_identifiers']	. "\n";
	fwrite($fh, $sLine);
}
fclose($fh);

echo "done \n";