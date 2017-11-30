<?php

require_once '../init.php';

$sDissContentType = 'Dissertation';
$sContentTypeFieldId = 146;
$sDateEmbargoFieldId = 193;
$sDateIssuedFieldId = 27;
$sDateAvailableFieldId = 25;
$sPodServiceFieldId = 279;

/**
 * Get all items for the given contenttype and the given date.issued
 * For each item, determine if it has an embargo
 * If it does, calculate the amount of time between date.issued and date.embargo
 */

$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$sYear = '2016';

$aThisYearsItems = array(); //added this year
$aThisYearsTheses = array(); //issued this year
$aThisYearsThesesAddedThisYear = array();



$aAllDisses = $oItemMetadata->findItemByMetadata($sContentTypeFieldId, $sDissContentType);
echo count($aAllDisses['itemids']) . "\n";


foreach ($aAllDisses['itemids'] as $nFoundItemId) {
	$sFoundDateAvailable = '';
    $sFoundDateIssued = '';
    
	
	$aDateAvailableData = $oItemMetadata->getMetadataValue($nFoundItemId, $sDateAvailableFieldId);
    if (!empty($aDateAvailableData)) {
        $sFoundDateAvailable = $aDateAvailableData['values'][0];
    }
    $aDateIssuedData = $oItemMetadata->getMetadataValue($nFoundItemId, $sDateIssuedFieldId);
    if (!empty($aDateIssuedData)) {
        $sFoundDateIssued = $aDateIssuedData['values'][0];
    }
    
    if (substr($sFoundDateAvailable, 0, 4) == $sYear) {
		//find the collection, so we can split the numbers
		$aItemData = $oItem->getItemData($nFoundItemId);
		$nOwningCollection = $aItemData['owning_collection'];

        $aThisYearsItems[$nOwningCollection][] = array(
           'itemid' => $nFoundItemId, 
           'dateissued' => $sFoundDateIssued,
           'dateavailable' => $sFoundDateAvailable,
			'collection' => $nOwningCollection,
        );
    }
	//we also want to find dissertations that were added in the first three months of the next year
	for ($i=1; $i<4;$i++) {
		$sNext = ($sYear+1) . '-' . '0' . $i;
		if (substr($sFoundDateAvailable, 0, 7) == $sNext) {
			$aItemData = $oItem->getItemData($nFoundItemId);
			$nOwningCollection = $aItemData['owning_collection'];

			$aThisYearsItems[$nOwningCollection][] = array(
				'itemid' => $nFoundItemId, 
				'dateissued' => $sFoundDateIssued,
				'dateavailable' => $sFoundDateAvailable,
				'collection' => $nOwningCollection,
			);
		}
	}
	
	
    if (substr($sFoundDateIssued, 0, 4) == $sYear) {
		$aItemData = $oItem->getItemData($nFoundItemId);
		$nOwningCollection = $aItemData['owning_collection'];

        $aThisYearsTheses[$nOwningCollection][] = array(
           'itemid' => $nFoundItemId, 
           'dateissued' => $sFoundDateIssued,
           'dateavailable' => $sFoundDateAvailable,
			'collection' => $nOwningCollection,
         );
    }
    
    if ((substr($sFoundDateAvailable, 0, 4) == $sYear) && (substr($sFoundDateIssued, 0, 4) == $sYear)) {
        $aThisYearsThesesAddedThisYear[$nOwningCollection][] = $nFoundItemId;
    }
	
    
}
    
$aLongEmbargos = array();
$aShortEmbargos = array();
$aPodItems = array();

foreach ($aThisYearsItems as $nOwningCollection => $aItems) {
	foreach ($aItems as $aOneItem) {
		$nItemId = $aOneItem['itemid'];
		$sDateIssued = $aOneItem['dateissued'];
	
		$aEmbargoData = $oItemMetadata->getMetadataValue($nItemId, $sDateEmbargoFieldId);
		if (!empty($aEmbargoData)) {
			$sEmbargoDate = $aEmbargoData['values'][0];
        
			if (strlen($sDateIssued) == 4) {
				$sDateIssued .= '-01-01';
			}
        
			$dEmbargoStart = strtotime($sDateIssued);
			$dEmbargoEnd = strtotime($sEmbargoDate);
			$dDiff = $dEmbargoEnd - $dEmbargoStart;
			$nMonths = idate('m', $dDiff);
			//echo $nMonths . "\n\n";
        
			if ($nMonths > 7) {
				$aLongEmbargos[$nOwningCollection][] = $nItemId;
			}
			else {
				$aShortEmbargos[$nOwningCollection][] = $nItemId;
			}
        
			$aPodData = $oItemMetadata->getMetadataValue($nItemId, $sPodServiceFieldId);
			if (!empty($aPodData)) {
				if ($aPodData['values'][0] == 'yes') {
					$aPodItems[$nOwningCollection][] = $nItemId;
				}
			}
		}
		
	}
}

echo 'UUR' . "\n";
echo 'Toegevoegd dit jaar: ' . count($aThisYearsItems[587]) . "\n";
echo 'Uitgekomen dit jaar UUR: ' . count($aThisYearsTheses[587]) . "\n";
echo 'Uitgekomen en toegevoegd in dit jaar UUR: ' . count($aThisYearsThesesAddedThisYear[587]) . "\n";
echo 'Kort embargo: ' . count($aShortEmbargos[587]) . "\n";
echo 'Lang embargo: ' . count($aLongEmbargos[587]) . "\n";
echo 'Met POD: ' . count($aPodItems[587]) . "\n";

echo "\n";

echo 'UMCU' . "\n";
echo 'Toegevoegd dit jaar: ' . count($aThisYearsItems[617]) . "\n";
echo 'Uitgekomen dit jaar UMCU: ' . count($aThisYearsTheses[617]) . "\n";
echo 'Uitgekomen en toegevoegd in dit jaar UMCUs: ' . count($aThisYearsThesesAddedThisYear[617]) . "\n";
echo 'Kort embargo: ' . count($aShortEmbargos[617]) . "\n";
echo 'Lang embargo: ' . count($aLongEmbargos[617]) . "\n";
echo 'Met POD: ' . count($aPodItems[617]) . "\n";

echo "\n";

echo 'Totaal' . "\n";
echo 'Toegevoegd dit jaar: ' . (count($aThisYearsItems[587]) + count($aThisYearsItems[617])) . "\n";
echo 'Uitgekomen dit jaar: ' . (count($aThisYearsTheses[587])+count($aThisYearsTheses[617])) . "\n";
echo 'Uitgekomen en toegevoegd in dit jaar: ' . (count($aThisYearsThesesAddedThisYear[587])+count($aThisYearsThesesAddedThisYear[617])) . "\n";
echo 'Kort embargo: ' . (count($aShortEmbargos[587])+count($aShortEmbargos[617])) . "\n";
echo 'Lang embargo: ' . (count($aLongEmbargos[587])+count($aLongEmbargos[617])) . "\n";
echo 'Met POD: ' . (count($aPodItems[587])+count($aPodItems[617])) . "\n";

