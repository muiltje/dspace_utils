<?php

/* 
 * Get all items for the given contenttype and the given date.issued
 */


require_once '../init.php';

$sDissContentType = 'Dissertation';
$nContentTypeFieldId = 146;
$nDateIssuedFieldId = 27;
$nDateAvailableFieldId = 25;
$nTitleFieldId = 143;
$nAuthorFieldId = 5;
$nUrlFieldId = 158;

$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$sYear = '2015';

$aThisYearsItems = array(); //added this year
$aThisYearsTheses = array(); //issued this year

$aAllDisses = $oItemMetadata->findItemByMetadata($nContentTypeFieldId, $sDissContentType);
echo count($aAllDisses['itemids']) . "\n";

foreach ($aAllDisses['itemids'] as $nFoundItemId) {
    $sFoundDateAvailable = '';
    $sFoundDateIssued = '';
    $aDateAvailableData = $oItemMetadata->getMetadataValue($nFoundItemId, $nDateAvailableFieldId);
    if (!empty($aDateAvailableData)) {
        $sFoundDateAvailable = $aDateAvailableData['values'][0];
    }
    $aDateIssuedData = $oItemMetadata->getMetadataValue($nFoundItemId, $nDateIssuedFieldId);
    if (!empty($aDateIssuedData)) {
        $sFoundDateIssued = $aDateIssuedData['values'][0];
    }
    
    if (substr($sFoundDateAvailable, 0, 4) == $sYear) {
        $aThisYearsItems[] = array(
           'itemid' => $nFoundItemId, 
           'dateissued' => $sFoundDateIssued,
           'dateavailable' => $sFoundDateAvailable,
        );
    }
    if (substr($sFoundDateIssued, 0, 4) == $sYear) {
        $aThisYearsTheses[] = array(
           'itemid' => $nFoundItemId, 
           'dateissued' => $sFoundDateIssued,
           'dateavailable' => $sFoundDateAvailable,
         );
    }
}

//$nThisYearItems = count($aThisYearsItems);
$nThisYearTheses = count($aThisYearsTheses);
//$nThisYearThesesAdded = count($aThisYearsThesesAddedThisYear);

//echo 'Toegevoegd dit jaar: ' . $nThisYearItems . "\n";
echo 'Uitgekomen dit jaar: ' . $nThisYearTheses . "\n";
//echo 'Uitgekomen en toegevoegd in dit jaar: ' . $nThisYearThesesAdded . "\n";

//$aDetailedTheses = array();
$sOutputFile = 'Dissertations_2015.csv';
$fh = fopen($sOutputFile, 'w');

$sFirstLine = "Titel \t Promovendus \t URL  \t Promotiedatum \n";

foreach ($aThisYearsTheses as $aOneItem) {
	$nItemId = $aOneItem['itemid'];
    $sDateIssued = $aOneItem['dateissued'];
	
	//get the title
	$aTitleData = $oItemMetadata->getMetadataValue($nItemId, $nTitleFieldId);
	$sTitle = $aTitleData['values'][0];
	
	//get the author
	$aAuthorData = $oItemMetadata->getMetadataValue($nItemId, $nAuthorFieldId);
	$sAuthor = $aAuthorData['values'][0];
	
	//get the full text URL
	$aUrlData = $oItemMetadata->getMetadataValue($nItemId, $nUrlFieldId);
	if (empty($aUrlData)) {
		echo 'No URL for ' . $nItemId . "\n";
		//this is a withdrawn item, ignore it
	}
	else {
		$sUrl = $aUrlData['values'][0];
	
		$sItemLine = "$sTitle \t $sAuthor \t $sUrl \t $sDateIssued \n";
		fwrite($fh, $sItemLine);
		/*
		$aDetailedTheses[] = array(
			'title' => $sTitle,
			'author' => $sAuthor,
			'url' => $sUrl,
			'date' => $sDateIssued,
		);
		 * 
		 */
	}
}

fclose($fh);

echo "done \n";


