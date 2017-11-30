<?php

/*
 * Use this script to compare two exports.
 * Export one is an export file from production (i.e. "the correct one")
 * Export two is an export file created by newexport.php (i.e. "the one to be tested")
 * And we have a list of items that we know should be in both exports
 */

$sCorrectExport = '/array7/dspace-test/export/omega_save/dsdump.20130630.xml';
$sNewExport = '/home/dspace/utils/newexport/exports/omega.20130724.xml';
$aItemIdList = array('8834', '11044', '16540', '20608', '25851', '279072', '279116', '279118');

$sTestReport = '/home/dspace/utils/newexport/exports/testreport_20130724.txt';

//collections: Diergeneeskunde, Geneeskunde, Letteren, Digitized Books

$sReadCorrectFile = file_get_contents($sCorrectExport);
$oCorrXml = new SimpleXMLElement($sReadCorrectFile);

$sReadNewFile = file_get_contents($sNewExport);
$oNewXml = new SimpleXMLElement($sReadNewFile);

$oReport = fopen($sTestReport, "w");

foreach ($aItemIdList as $nItemId) {
    fwrite($oReport, $nItemId . "\n");
    
    $sPresent = checkItemPresence($oNewXml, $nItemId);
    $sLine = $nItemId . ' : ' . $sPresent . "\n";
    fwrite($oReport, $sLine);
    
    $aCorrectElements = getCorrectElements($oCorrXml, $nItemId);
    //print_r($aCorrectElements);
    $aTestedElements = testElements($oNewXml, $aCorrectElements, $nItemId);
    if (isset($aTestedElements['missing']) && count($aTestedElements['missing']) > 0) {
        fwrite($oReport, "Missing elements! \n");
        foreach ($aTestedElements['missing'] as $sMissingElement) {
            fwrite($oReport, $sMissingElement . "\n");
        }
    }
    else {
        fwrite($oReport, "All elements are present \n");
    }
    //print_r($aTestedElements);
    fwrite($oReport, "\n");
}




function checkItemPresence($oNewXml, $nItemId)
{
    $sPattern = '//dspace_record[@id="' . $nItemId . '"]';
    $sRecordField = $oNewXml->xpath($sPattern);
    
    if (count($sRecordField) > 0) {
        return 'found';
    }
    else {
        return 'not found';
    }
}

function getCorrectElements($oCorrXml, $nItemId)
{
    $aElements = array();
    
    $sPattern = '//dspace_record[@id="' . $nItemId . '"]';
    $oAllFields = $oCorrXml->xpath($sPattern);
    $aFields = $oAllFields[0];
    
    foreach ($aFields as $sOneField => $sValue) {
        $aElements[] = $sOneField; 
    }
    
    return $aElements;
}

function testElements($oNewXml, $aCorrectElements, $nItemId)
{
    $aTestedElements = array();
     
    foreach ($aCorrectElements as $sElement) {
         //find the item
        $sPattern = '//dspace_record[@id="' . $nItemId . '"]/' . $sElement;
        $oRecordField = $oNewXml->xpath($sPattern);
  
        if (count($oRecordField) > 0) {
            $aTestedElements[$sElement] = 'found';
        }
        else {
            $aTestedElements[$sElement] = 'not found';
            $aTestedElements['missing'][] = $sElement;
        }
    }
    
    return $aTestedElements;
}


?>
