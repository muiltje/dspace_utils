<?php

require_once '../init.php';
ini_set("auto_detect_line_endings", true);

$nDOIMetadataFieldId = 60;

/*
$aTestDois = array(
	'https://dx.doi.org/10.3945/ajcn.114.106450',
	'https://dx.doi.org/10.2307/257025',
	'https://dx.doi.org/10.1186/s13046-015-0129-6'
);
 * 
 */

$oItemMetadata = new ItemMetadata();
$aPresent = array();

$sFile = 'SciHub_Utrecht.csv';
$fh = fopen($sFile, "r");
while (($aData = fgetcsv($fh, 1000, ";")) !== FALSE) {
	$sDOI = $aData[2];
	$aResult = $oItemMetadata->findItemByMetadata($nDOIMetadataFieldId, $sDOI);
	if (!empty($aResult)) {
		$aPresent[$sDOI] = $aResult;
	}
}


//foreach ($aTestDois as $sDOI) {
//	$aResult = $oItemMetadata->findItemByMetadata($nDOIMetadataFieldId, $sDOI);
//	$aPresent[] = $aResult;
//}

print_r($aPresent);
