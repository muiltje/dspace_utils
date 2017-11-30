<?php

$sBaseDirectory = '/bnb_export/disk07/';

$aItems = array();

$sPpnFile = 'bnb_twee_ppn.csv';
$fh = fopen($sPpnFile, "w");

//get the metadata files
for ($i=1; $i<=8; $i++) {
	$sFileName = 'MMSFUBU02_00000000' . $i . '_1_01_pakbon.xml';
	$sFilePath = $sBaseDirectory . $sFileName;
	
	if (file_exists($sFilePath)) {
		
		$aParsed = parseMetadataFile($sFilePath);
		foreach ($aParsed as $aOneItem) {
			$aItems[] = array(
				'source' => $sFilePath,
				'data' => $aOneItem,
			);
			$sLine = $aOneItem['ppn'] ."\n";
			fwrite($fh, $sLine);
		}
	}
	else {
		echo 'cannot find ' . $sFilePath;
	}
}
echo count($aItems);
print_r($aItems[7]);
fclose($fh);

function parseMetadataFile($sFilePath)
{
	$aData = array();
	
	$sText = file_get_contents($sFilePath);
	$sXML = new SimpleXMLElement($sText);
	
	$aPublications = $sXML->xpath('//Publication');
	foreach ($aPublications as $aOnePub) {
		$aPPNField = $aOnePub->xpath('@PPN');
		$sPPN = (string) $aPPNField[0];
		$aShelfMarkField = $aOnePub->xpath('@Shelfmark');
		$sShelfMark = (string) $aShelfMarkField[0];
		$aTitleField = $aOnePub->xpath('@Title');
		$sTitle = (string) $aTitleField[0];
		$aIdentifierField = $aOnePub->xpath('@ID');
		$sIdentifier = (string) $aIdentifierField[0];
		
		$aMicroFilm = $aOnePub->xpath('../@ShelfmarkMicrofilm');
		$sMicroFilmName = (string) $aMicroFilm[0];
		
		$aData[] = array(
			'ppn' => $sPPN,
			'shelfmark' => $sShelfMark,
			'title' => $sTitle,
			'identifier' => $sIdentifier,
			'microfilm' => $sMicroFilmName,
		);
		
		//$aData[] = (string) $aPPNField[0];
	}
	
	return $aData;
}