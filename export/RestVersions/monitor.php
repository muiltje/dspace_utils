<?php

/* 
 * Monitor the state of certain items and report on it
 */

require_once 'init.php';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oHandle = new Handle();

/*
 * We are interested in items that have a last-modified of a day ago
 */
$sLastModifiedDate = date('Y-m-d H:i:s', strtotime("-1 day"));
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

/*
 * We only really care about UUR/UMCUR items
 */
$aRepCollections = array(587, 617); 

/*
 * For all relevant items, check URN, DAI?, access rights?
 * Check if it is in the OAI index?
 */

//print_r($aModifiedItems);

foreach ($aModifiedItems as $aOneItem) {
    $nItemId = $aOneItem['item_id'];
    
    //check if item is in archive and not withdrawn
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
        $nOwningCollection = $aOneItem['owning_collection'];
        if (in_array($nOwningCollection, $aRepCollections)) {
			//check URN
			$sUrnCheckResult = checkURN($nItemId, $oItemMetadata, $aMetadataFields);
			//echo $sUrnCheckResult;
			wlog($sUrnCheckResult, 'INF');
			
			//check OAI
			$sOaiResult = checkOAI($nItemId, $oHandle);
			//echo $sOaiResult . "\n";
			wlog($sOaiResult, 'INF');
			
			//check type content
			$aTypeContentResult = checkContentType($nItemId, $oItemMetadata, $aMetadataFields);
			if (isset($aTypeContentResult['error']) || isset($aTypeContentResult['invalid'])) {
				mailTypeContent($aTypeContentResult);
			}
		
		}
	}
	else {
		//log if withdrawn or not in archive
		wlog('Item ' . $nItemId . ' is not active', 'INF');
	}
}

function checkURN($nItemId, $oItemMetadata, $aMetadataFields)
{
	$nUrnFieldId = $aMetadataFields['identifier_urnnbn'];

	$sUrnLogLine = 'Item ' . $nItemId . ' has ';
	$aUrnData = $oItemMetadata->getMetadataValue($nItemId, $nUrnFieldId);
	if (!empty($aUrnData['values'])) {
		$sUrn = $aUrnData['values'][0];
		$sUrnLogLine .= 'URN ' . $sUrn;
	}
	else {
		$sUrnLogLine .= 'no URN-NBN';
	}

	return $sUrnLogLine;
}



function checkOAI($nItemId, $oHandle)
{
	$sCheckResult = '';
	
	$aHandleData = $oHandle->getHandle($nItemId);
	$sHandleId = $aHandleData['handleid'];
	$sOAIUrl = 'http://dspace.library.uu.nl/oai/dare?verb=GetRecord&metadataPrefix=nl_didl&identifier=oai:dspace.library.uu.nl:1874/' . $sHandleId;
	$sResult = file_get_contents($sOAIUrl);
	
	$sXML = new SimpleXMLElement($sResult);
	$aErrorField = $sXML->xpath('//error');
	if (count($aErrorField) > 0) {
		$sCheckResult = $nItemId . ' NOT in dare oai';
	}
	else {
		$sCheckResult = $nItemId . ' found in DARE OAI';
	}
	
	return $sCheckResult;
}

function checkContentType($nItemId, $oItemMetadata, $aMetadataFields)
{
	$aCheckResults = array();
	
	$nTypeContentFieldId = $aMetadataFields['type_content'];
	$aTypeData = $oItemMetadata->getMetadataValue($nItemId, $nTypeContentFieldId);
	if (empty($aTypeData['values'])) {
		//we have a problem
		$aCheckResults['error'] = 'No type content found for ' . $nItemId;
	}
	elseif (preg_match('/atira/', $aTypeData['values'][0])) {
		//invalid type
		$aCheckResults['invalid'] = 'Found ' . $aTypeData['values'][0] . ' for ' . $nItemId;
	}
	else {
		//no problem
		$aCheckResults['ok'] = 'No problems';
	}
	
	return $aCheckResults;
}


function mailTypeContent($aTypeContentResult)
{
	$sMailText = '';
	$sSubject = '';
	
	if (isset($aTypeContentResult['error'])) {
		$sMailText .= $aTypeContentResult['error'];
		$sSubject = 'Missing type.content in DSpace';
	}
	if (isset($aTypeContentResult['invalid'])) {
		$sMailText .= $aTypeContentResult['invalid'];
		$sSubject = 'Invalid type.content in DSpace';
	}
	
	$sTo = 'm.muilwijk@uu.nl';
	$sFrom = 'm.muilwijk@uu.nl';
	
	$sHeaders = 'To:' . $sTo . "\r\n";
    $sHeaders .= 'From: ' . $sFrom . "\r\n";
 
    mail($sTo, $sSubject, $sMailText, $sHeaders);

}