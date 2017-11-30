<?php

/* 
 * Find theses in the given collection whose embargo will end in the given number of days.
 * For each of these, get the supervisor email address from the scrol database
 * Send an email to the supervisor
 */

/**
 * To Do first: 
 *  - update functions in AuxTables
 *  - update database: 
 *      alter table ubuaux_embargo_alert add column pubtype varchar(10);
 *      update ubuaux_embargo_alert set pubtype='diss';
 *  - update embargoalert.php
 */


require_once 'alert_init.php';
require_once CLASSES . 'Item.php';

$oAux = new AuxTables();
$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$aThesisCollectionsToDo = array(98);

$bSendAlert = 0;
$bSync = 0;
$bVerbose = 0;
$sEhandle = '';

$sShortOpts = "asvh";
$aOptions = getopt($sShortOpts);

if (isset($aOptions['h'])) {
    usage();
}

if (isset($aOptions['a'])) {
    $bSendAlert = 1;
}
if (isset($aOptions['s'])) {
    $bSync = 1; 
    //$bSendAlert = 0;
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}

//if (ENV == 'prod') {
    $bMailMode = 'test';
//}
    
$sPubType = 'thesis';
$sToday = date('Y-m-d');    

/**
 * Sync: 
 * in DSpace find all theses with an embargo date in the future
 * for all of them, find the scrolid
 * in Scrol find all theses with a supervisor email address 
 * for all of those, see if their scrolid belongs to an item with a future embargo
 * if yes, add or update the supervisor email address in ubuaux_embargo_alert
 */
if ($bSync == 1) {
    $aFutureEmbargos = $oAux->findFutureEmbargoAlert($sPubType);
    //print_r($aFutureEmbargos);
    $sLine = count($aFutureEmbargos) . ' future embargos ' ;
    wlog($sLine, 'INF');
    //echo $sLine . "\n";

    $aEmbargoScrolIds = array();
    foreach ($aFutureEmbargos as $aOneEmbargoItem) {
        //skip disses
        if ($aOneEmbargoItem['pubtype'] != 'diss') {
            $nItemId = $aOneEmbargoItem['item_id'];
            $sOldEmail = $aOneEmbargoItem['email'];
            $sHandle = $aOneEmbargoItem['handle'];
            
            //check if it is the right collection
            $aItemData = $oItem->getItemData($nItemId);
            $nOwningCollection = $aItemData['owning_collection'];
            if (in_array($nOwningCollection, $aThesisCollectionsToDo)) {
                $aIdentifierOtherData = $oItemMetadata->getMetadataValue($nItemId, 71);
                //print_r($aIdentifierOtherData);
                if (!empty($aIdentifierOtherData['values'])) {
                    $sScrolId = $aIdentifierOtherData['values'][0];
                    $aEmbargoScrolIds[$sScrolId] = array('foundemail' => $sOldEmail, 'handle' => $sHandle);
                }
            }
        }
    }
    //print_r($aEmbargoScrolIds);
 
    $aThesisWithEmail = findThesisEmailData();
    //print_r($aThesisWithEmail);
    //$sLine = count($aThesisWithEmail) . ' theses with an email address ';
    //echo $sLine . "\n";

	$aRecordsToUpdate = findEmailToProcess($aEmbargoScrolIds, $aThesisWithEmail);
    //print_r($aRecordsToUpdate);
    $sLine = count($aRecordsToUpdate) . ' records to update';
    
    //echo $sLine . "\n";
    logVerbose($sLine, $bVerbose);
    $aSyncResult = $oAux->syncEmbargoTable($aRecordsToUpdate, $sPubType);
    if (isset($aSyncResult['errors'])) {
        foreach ($aSyncResult['errors'] as $sError) {
            //echo $sError . "\n";
            wlog($sError, 'INF');
        }
    }
    else {
        wlog('ubuaux_embargo_alert has been synced for theses', 'INF');
        //echo 'ubuaux_embargo_alert has been synced for theses' . "\n";
    }
    
    //echo "sync done \n";
} //end of bsync == 1

if ($bSendAlert == 1) {
    $aEmbargoData = $oAux->checkEmbargoAlert($sPubType);
    
    if (isset($aEmbargoData['error'])) {
        wlog($aEmbargoData['error']);
    }
    else {
        $sLine = count($aEmbargoData) . ' embargoes found ';
        logVerbose($sLine, $bVerbose);
		//echo $sLine;
        //for each embargo we found, determine if we should send an alert
        //counter to make sure we don't send mail for every embargo during test
        $counter = 0;
        foreach ($aEmbargoData as $aOneEmbargo) {
            $sEmbargoAlertDate = $aOneEmbargo['date'];
            //echo $sEmbargoAlertDate . "\n";
			
			//special case for DGK, so check which collection this item is in
			$aOneEmbargo['case'] = 'other';
			$nEmbargoItemId = $aOneEmbargo['item_id'];
			$aItemBaseData = $oItem->getItemData($nEmbargoItemId);
			$nOwningCollection = $aItemBaseData['owning_collection'];
			if ($nOwningCollection == 98) {
				$aOneEmbargo['case'] = 'DGK';
			}
			else {
				$aOneEmbargo['case'] = 'other';
			}
			
			//print_r($aOneEmbargo);

			
			
            if ($sEmbargoAlertDate == $sToday) {
                $sLine = 'send email for number ' . $counter;
                logVerbose($sLine, $bVerbose);
				echo $sLine;
                
                if ($bMailMode == 'send') {
                    //sendEmail($bMailMode, $aOneEmbargo);
                }
                else { //for debug
                    //if ($counter < 10 && $bSendAlert == 1) {
                        sendEmail($bMailMode, $aOneEmbargo);
                    //}
                }
       
                $counter++;
            }
            
        }
        //echo $counter . " embargoes found \n";
        $sLine = $counter . " embargoes found \n";
        logVerbose($sLine, $bVerbose);
    }
} //end of if sendalert == 1


function findThesisEmailData()
{
	$sServiceUrl = SCROLSERVICE . '/embargoemail';
	$aDataFound = file_get_contents($sServiceUrl);
	$aThesisEmailData = json_decode($aDataFound, true);
	
	return $aThesisEmailData;
}

    
function findEmailToProcess($aEmbargoScrolIds, $aThesisWithEmail)
{
    $aRecordsToUpdate = array();
    
    foreach ($aThesisWithEmail as $aOneThesis) {
        $sScrolId = $aOneThesis['scrol_id'];
		if (array_key_exists($sScrolId, $aEmbargoScrolIds)) {
            $sNewEmail = $aOneThesis['supervisoremail'];
            $sHandle = $aEmbargoScrolIds[$sScrolId]['handle'];
            $sOldEmail = $aEmbargoScrolIds[$sScrolId]['foundemail'];
            $aRecordsToUpdate[$sHandle] = array('existingemail' => $sOldEmail, 'newemail' => $sNewEmail);
        }
    }
    
    return $aRecordsToUpdate;
}

function logVerbose($sLine, $bVerbose)
{
    if ($bVerbose == 1) {
        wlog($sLine, 'INF');
    }
    
    return 1;
}

/**
 * This is the alert mail.
 * 
 */
function sendEmail($bMailMode, $aItemEmbargoData) 
{
    $sAlertText = 'The embargo on the thesis ' . $aItemEmbargoData['title'] 
        . ' will expire on ' . $aItemEmbargoData['embargodate'] . "\n" 
        . 'From then on, this thesis will be freely available in the Student Theses Archive and on the Internet.';
    $sAlertText .= "\n\n";
    $sAlertText .= 'Please contact us at library@uu.nl should you have any questions.';
    $sAlertText .= "\n\n --\n";
    $sAlertText .= "Utrecht University Library\n";
    $sAlertText .= "http://www.uu.nl/en/university-library \n";
    
    $sIntroText = 'The following email would be sent to ' . $aItemEmbargoData['email'];
	if ($aItemEmbargoData['case'] == 'DGK') {
		$sIntroText .= ' with a CC to DGK';
	}
    $sFrom = 'm.muilwijk@uu.nl';
    $sSubject = 'Student thesis embargo alert - test';
    $sTo = 'm.muilwijk@uu.nl, g.vandongen@uu.nl';
    
    
    if ($bMailMode == 'send') {
        $sFrom = 'repository@uu.nl';
        $sSubject = 'Doctoral thesis embargo alert';
        $sTo = $aItemEmbargoData['email'];
        $sHeaders = 'From:' . $sFrom . "\r\n";
		
		if ($aItemEmbargoData['case'] == 'DGK') {
			$sHeaders .= 'CC: blackboard.vet@uu.nl' . "\r\n";
		}
		
        $sHeaders .= 'BCC: m.muilwijk@uu.nl' . "\r\n";
        
        //mail($sTo, $sSubject, $sAlertText, $sHeaders);
		wlog('Mail sent to ' . $sTo, 'INF');
    }
    else {
        $sMessage = $sIntroText . "\n\n" . $sAlertText;
        $sHeaders = 'From:' . $sFrom . "\r\n";
        mail($sTo, $sSubject, $sMessage, $sHeaders);
		
		wlog('Testmail sent', 'INF');
    }

    return;
}

function usage()
{
    echo "usage: embargoalert.php [-asvh] \n";
    echo "  -a: send embargo alerts \n";
    echo "  -s: sync the embargo alert table, do not send alerts \n";
    echo "  -v: verbose";
    echo "  -h: this help \n";
    
    exit();
}