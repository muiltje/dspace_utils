<?php

/* 
 * Send an email to the authors of an item that has been newly added to DSpace
 * 
 * For this we will need:
 * an aux table like ubuaux_notify_profile to keep track of the handles for
 * which an email has been sent.
 * 
 * Initially, fill this table with all handles of 'Metis' items
 * 
 * What this script does:
 * get recently modified items in the two "repository" collections
 * for each item:
 *  - check if the handle is in the aux table
 *  - if it is, do nothing
 *  - else
 *  - determine the kind of mail to send, by checking SherpaRomeo
 *  - compose the email
 *  - send the email
 *  - write the handle to the aux table
 * 
 */

/*
 * creating the aux table
 * 
 * CREATE TABLE ubuaux_notify_author (
 *  handle varchar(16) NOT NULL,
 *  maildate timestamp DEFAULT current_timestamp
 * );
 */


require_once 'import_init.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'Handle.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'SherpaRomeo.php';

require_once 'mail_texts.php';

$sHomeUrl = BASEURL;

$oItem = new Item();
$oHandle = new Handle();
$oAux = new AuxTables();
$oItemMetadata = new ItemMetadata();
$oSherpa = new SherpaRomeo();

$aCollections = array(587, 617); 
//$aCollections = array(587);

$sLastModifiedDate = date('Y-m-d', strtotime("-1 day"));

$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

//echo count($aModifiedItems) . "\n";
//exit();


foreach ($aModifiedItems as $aOneItem) {
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
        $nItemId = $aOneItem['item_id'];
        
        //check if it is in one of the collections
        $nOwningCollection = $aOneItem['owning_collection'];
        
        if (in_array($nOwningCollection, $aCollections)) {
            $aHandleData = $oHandle->getHandle($nItemId);
            $sHandle= $aHandleData['handle'];
            
            $aCheckResult = $oAux->checkAuthorNotification($sHandle);
            //echo $sHandle;
            //print_r($aCheckResult);
            //echo "\n";
            if (empty($aCheckResult)) {
                //echo 'send mail about ' . $sHandle . "\n";
                wlog('send mail about ' . $sHandle, 'INF');
                
                $nProvenanceFieldId = 33;
                
                //get all provenance fields
                //for each see if they consist of an email address and nothing else
                //put all email addresses in an array, to filter out double adddresses
                $aEmails = array();
                $aProvenanceData = $oItemMetadata->getMetadataValue($nItemId, $nProvenanceFieldId);
                if (isset($aProvenanceData['error'])) {
                    wlog($aProvenanceData['error'], 'WARN');
                }
                //print_r($aProvenanceData);
                
                foreach ($aProvenanceData['values'] as $sProvenanceText) {
                    //if it doesn't start with 'updated by' or 'made available'
                    if (substr($sProvenanceText, 0, 7) != 'Updated' && substr($sProvenanceText, 0, 4) != 'Made') {
                        if (preg_match('/@uu.nl$/', $sProvenanceText)) {
                            //$sEmails .= $sProvenanceText . ',';
                            if (!in_array($sProvenanceText, $aEmails)) {
                                $aEmails[] = $sProvenanceText;
                            }
                        }
                    }
                }
                
                if (!empty($aEmails)) {
                    
                    //determine the type of publication we're dealing with
                    $aMailData = getMailData($nItemId, $oItemMetadata);
                    $sMailType = $aMailData['mailtype'];
                    $sISSN = $aMailData['issn'];
                    $sTitle = $aMailData['title'];
                    $sUrl = $sHomeUrl . '/handle/' . $sHandle;
                        
                    $sEmailText = '';
                    $sStandardText = makeStandardText($sTitle, $sUrl, $sMailType);
                    
                    if ($sMailType == 'diss') {
                        $sEmailText = $sStandardText . $sDissText . $sFooterText;
                    }
                    elseif ($sMailType == 'openaccess') {
                        $sEmailText = $sStandardText . $sOAText . $sFooterText;
                    }
                    elseif ($sMailType == 'journal') {
                        $aSherpaInfo = checkSherpaRomeo($sISSN, $oSherpa);
                        $sSRText = $aSherpaInfo['outputtext'];
                        $sEmailText = $sStandardText . $sSRNotOpenText
                                . $sSRText . $sSRNotOpenFurther 
                                . $sVersionTable . $sFooterText;
                    }
                    else {
                        $sEmailText = $sStandardText . $sClosedAccessText 
                                . $sVersionTable . $sFooterText;
                    }
                    
                    
                    $sEmails = '';
                    foreach ($aEmails as $sMail) {
                        $sEmails .= $sMail . ',';
                    }
                    $sToEmail = preg_replace('/,$/', '', $sEmails);

                    //DEBUG!
                    //if (ENVIRONMENT == 'test') {
                        $sEmailText .= '<p>Deze email zou verstuurd worden naar '
                                . $sToEmail . '</p>';
                    //}
                    
                  
                    //$sTo = $sToEmail;
					$sTo = 'm.muilwijk@uu.nl';
                    //if we expect a bulk import from Pure, comment out this line
                    $sMailSent = sendMail($sEmailText, $sTo);
                    
                    $oAux->saveAuthorNotification($sHandle);
                    
                } //end of "if $aEmails not empty"
                else {
                    $sLine = 'No email addresses found for ' . $nItemId;
                    wlog($sLine, 'INF');
                }
            } //end of "if $aCheckResult is empty"

        } //end of "if owning collection is in array
    } //end of "in archive, not withdrawn"
}

function getMailData($nItemId, $oItemMetadata)
{
    $aMailData = array();
    $sMailType = 'other';
       
  	$aMetadata = $oItemMetadata->getAllMetadata($nItemId);
	$aMetadataKeys = array(
		'dc.type.content',
		'dc.rights.accessrights',
		'dc.relation.ispartofissn',
		'dc.title',
	);
	$aDataToUse = array();
	foreach ($aMetadata as $aOneField) {
		$sFieldName = $aOneField['key'];
		if (in_array($sFieldName, $aMetadataKeys)) {
			$aDataToUse[$sFieldName] = $aOneField['value'];
		}
	}
  
	$sTypeContent = $aDataToUse['dc.type.content'];
	$sRights = $aDataToUse['dc.rights.accessrights'];
	$sIssn = '';
	if (isset($aDataToUse['dc.relation.ispartofissn'])) {
		$sIssn = $aDataToUse['dc.relation.ispartofissn'];
	}
	$sTitle = $aDataToUse['dc.title'];
	
    //is it a dissertation
    if ($sTypeContent == 'Dissertation') {
        $sMailType = 'diss';
    }
    elseif ($sRights == 'Open Access (free)') { //if not, is it open access
        $sMailType = 'openaccess';        
    }
    elseif ($sIssn != '') {//if not, is there an issn
        $sMailType = 'journal';
    }
    else { //if not, there are no permissions
        $sMailType = 'other';
    }
    
    //return $sMailType;
    $aMailData['mailtype'] = $sMailType;
    $aMailData['issn'] = $sIssn;
    $aMailData['title'] = $sTitle;
    
    return $aMailData;
}

/*
function getField($nItemId, $nMetadataFieldId, $oItemMetadata) 
{
    $sFieldValue = '';
    $aData = $oItemMetadata->getMetadataValue($nItemId, $nMetadataFieldId);
    if (!empty($aData['values'])) {
        $sFieldValue = $aData['values'][0];
    }
    if (isset($aData['error'])) {
        wlog($aData['error'], 'ERR');
    }
    
    return $sFieldValue;
}
 * 
 */

function makeStandardText($sTitle, $sUrl, $sMailType)
{
    $sStandardText = '<p>Dear Researcher,</p>';
    if ($sMailType == 'diss') {
        $sStandardText .= '<p>Your doctoral thesis has been published ';
    }
    else {
        $sStandardText .= '<p>The full text of your publication has recently been archived ';
    }
    $sStandardText .= 'in the Utrecht University Repository, the digital repository for Utrecht University publications:</p>';
    $sStandardText .= '<p>' . $sTitle . '</p>';
    $sStandardText .= '<p>' . $sUrl . '</p>';
    $sStandardText .= '<p>Publications in the Utrecht University Repository '
            . ' are visible through NARCIS, the national portal for access to the '
            . ' repositories of all the Dutch universities, KNAW, NWO and a '
            . ' number of research institutes.</p> ';
    
    
    return $sStandardText;
}


function checkSherpaRomeo($sISSN, $oSherpa)
{
    $sSherpaResult = $oSherpa->checkSherpaRomeo($sISSN);
    
    return $sSherpaResult;
}

function sendMail($sEmailText, $sTo)
{
    $sHeaders = 'MIME-Version: 1.0' . "\r\n";
    $sHeaders .= 'Content-type: text/html; charset=utf-8' . "\r\n";
    $sHeaders .= 'From: repository@uu.nl' . "\r\n";
    
    //if (ENVIRONMENT == 'prod') {
    //    $sHeaders .= 'BCC: m.muilwijk@uu.nl';
    //}
    
    $sSubject = 'Your publication has been added to the archive';
    
    $sResult = mail('m.muilwijk@uu.nl', $sSubject, $sEmailText, $sHeaders);
    return $sResult;
}