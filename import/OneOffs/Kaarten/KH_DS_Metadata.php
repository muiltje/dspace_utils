<?php

require_once '../init.php';
require_once 'KaartenHolland_Handles.php';

$oItemMetadata = new ItemMetadata();

/*
 * id                           = handle (zie boven)
filename             = naam van de Tiff-image, die gehost gaat worden bij Georeferencer (TIFF-files worden geüpload, i.t.t. eerdere georefereerprojecten; wellicht dat deze kolom pas later kan worden toegevoegd aan het sheet; in de meeste gevallen zal het gaan om de eerste image; in geval van een digitaal gemonteerde wandkaart gaat het om de laatste image)
link                        = url naar de bladeraar (Dspace: dc. identifier. urlfulltext)
viewer                 = url naar zoomable viewer (volgens mij niet in Dspace of Aleph; optioneel en laten zitten als dit niet makkelijk te genereren is)
catalog                 = url naar de catalogus (Aleph; gezien de toekomstplannen wellicht niet verstandig die op te nemen; optioneel. Eventueel alternatief: JOP: Dspace: dc. identifier. urljumpoff)
title                       = titel van de kaart (Dspace: dc. title)
date                      = publicatiedatum (Dspace: dc. date. issued)
creator                 = maker van de kaart (volgens mij uit Aleph (veld 245 $c); of Dspace: dc. contributor. author (maar is dan gestandaardiseerd))
contributor        = naam graveur/medewerker (volgens mij voorheen uit Aleph (veld 245 $c; gedeelte na ‘;’))
publisher            = uitgever/impressum (Dspace: dc. rights. impressum)
width                   = breedte document (volgens mij uit Aleph (veld 300 $c, laatste getal met extra 0 erachter))
height                  = hoogte document (volgens mij uit Aleph (veld 300 $c, eerste getal met extra 0 erachter))
scale                     = schaal document metrisch (volgens mij uit Aleph (veld 255 $a))
shelfmark           = signatuur (Dspace: dc. source. signature)
dpi                         = dpi van de scan
 */

$aMapsWithMeta = array();

/*
$nTestId = 357795;
$sTestHandle = '1874/348688';

$nItemId = $nTestId;
$sHandle = $sTestHandle;
 * 
 */

foreach ($aKaartenVanNederland as $aOneMap) {
	$nItemId = $aOneMap['itemid'];
	$sHandle = $aOneMap['handle'];

	$aMapsWithMeta[$nItemId]['id'] = $sHandle;


	//generate reader link
	$sShowHandle = str_replace('/', '-', $sHandle);
	$sReaderLink = 'http://objects.library.uu.nl/reader/index.php?obj=' . $sShowHandle;
	$aMapsWithMeta[$nItemId]['link'] = $sReaderLink;

	$sLandingPage = 'http://hdl.handle.net/' . $sHandle;
	$aMapsWithMeta[$nItemId]['catalog'] = $sLandingPage;

	//get sequence - 244
	$sSequence = getFieldValue($nItemId, 244, $oItemMetadata);
	//derive wanted file name from sequence
	$sWanted = getFileToUse($sSequence);
	$aMapsWithMeta[$nItemId]['filename'] = $sWanted;

	//get title - 143
	$sTitle = getFieldValue($nItemId, 143, $oItemMetadata);
	$aMapsWithMeta[$nItemId]['title'] = $sTitle;

	//get date - 27
	$sDate = getFieldValue($nItemId, 27, $oItemMetadata);
	$aMapsWithMeta[$nItemId]['date'] = $sDate;

	$sPublished = '';
	//get impressum - 270
	$sImpressum = getFieldValue($nItemId, 270, $oItemMetadata);
	if ($sImpressum != '') {
		$sPublished = $sImpressum;
	}
	else {
		//get place (if there is no impressum) - 121
		$sPlace = getFieldValue($nItemId, 121, $oItemMetadata);
		//get publisher (if there is no impressum) - 76
		$sPublisher = getFieldValue($nItemId, 76, $oItemMetadata);
		$sPublished = $sPlace . ' : ' . $sPublisher;
	}
	$aMapsWithMeta[$nItemId]['publisher'] = $sPublished;

	//get shelfmark - 263
	$sShelfMark = getFieldValue($nItemId, 263, $oItemMetadata);
	$aMapsWithMeta[$nItemId]['shelfmark'] = $sShelfMark;

	//get sysnumber - 127 or 265
	$sAlephSys = '';
	$sSourceAleph = getFieldValue($nItemId, 127, $oItemMetadata);
	if ($sSourceAleph != '') {
		$sAlephSys = $sSourceAleph;
	}
	else {
		$sRelAleph = getFieldValue($nItemId, 265, $oItemMetadata);
		$sAlephSys = $sRelAleph;
	}
	$aMapsWithMeta[$nItemId]['alephsys'] = $sAlephSys;
}

//print_r($aMapsWithMeta);

$sOutput = var_export($aMapsWithMeta, true);
$sOutputFile = 'KH_HandlesWithDSMetadata.php';
file_put_contents($sOutputFile, $sOutput);



function getFieldValue($nItemId, $nMetadataFieldId, $oItemMetadata)
{
	$sValue = '';
	$aData = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
	if (!empty($aData['values'])) {
		$sValue = $aData['values'][0];
	}
	
	return $sValue;
}

function getFileToUse($sSequence)
{
	
	$sString = '';
	$aSequenceParts = explode('+', $sSequence);
	$nPartCount = count($aSequenceParts);
	
	if ($nPartCount > 4) {
		$nLast = $nPartCount-2;
		$sString = $aSequenceParts[$nLast];
	}
	else {
		$sString = $aSequenceParts[0];
	}

	$nPerc = strpos($sString, '%');
	$sFileName = substr($sString, $nPerc+1);
	
	return $sFileName;
}