<?php

/*
 * Two months before the embargo on a dissertation ends, send email to the author.
 * 
  */
require_once 'alert_init.php';


$bSendAlert = 0;
$bSync = 0;
$bRetro = 0;
$bVerbose = 0;
$sEhandle = '';
$bMailMode = 'test';


$sShortOpts = "asvrh";
$aLongOpts = array('ehandle::');
$aOptions = getopt($sShortOpts, $aLongOpts);

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
if (isset($aOptions['r'])) {
    $bRetro = 1;
}
if (isset($aOptions['v'])) {
    $bVerbose = 1;
}
if (isset($aOptions['ehandle']) && $aOptions['ehandle'] != '') {
    $sEhandle = $aOptions['ehandle'];
}

if (ENV == 'prod') {
    $bMailMode = 'send';
}

$oAux = new AuxTables();
$oHandle = new Handle();

$sToday = date('Y-m-d');
$sPubType = 'diss';

if ($sEhandle != '') {
    //get itemid for handle
    //$oHandle = new Handle();
    $aHandleData = $oHandle->getItemIdDb($sEhandle);
    $nItemId = $aHandleData['itemid'];
    
    //get metadatavalue for itemid
    $oItemMetadata = new ItemMetadata();
    $aMetaData = $oItemMetadata->getMetadataValue($nItemId, 193);
    $sEmbargoDate = $aMetaData['values'][0];
    
    echo 'Handle ' . $sEhandle . ' has embargo date ' . $sEmbargoDate . "\n";
}
elseif ($bSync == 1) {
    $aFutureEmbargos = $oAux->findFutureEmbargoAlert();
    $sLine = count($aFutureEmbargos) . ' future embargos ' ;
	logVerbose($sLine, $bVerbose);
	echo $sLine . "\n";
    
    $aIgdissEmailData = findIgdissEmailData();
    $sLine = count($aIgdissEmailData) . ' igdiss records';
    logVerbose($sLine, $bVerbose);
    echo $sLine . "\n";
	
	
	$aFutureEmbargoHandles = array();
    foreach ($aFutureEmbargos as $aOneEmbargo) {
        $sItemHandle = $aOneEmbargo['handle'];
        $sFoundEmail = $aOneEmbargo['email'];
        $aFutureEmbargoHandles[$sItemHandle] = $sFoundEmail;
    }
    
    $aRecordsToUpdate = findIgdissEmail($aIgdissEmailData, $aFutureEmbargoHandles);
    $sLine = count($aRecordsToUpdate) . ' records to update';
    logVerbose($sLine, $bVerbose);
	echo $sLine . "\n";
	print_r($aRecordsToUpdate);
   
	$aSyncResult = $oAux->syncEmbargoTable($aRecordsToUpdate, $sPubType);
    if (isset($aSyncResult['errors'])) {
        foreach ($aSyncResult['errors'] as $sError) {
            wlog($sError, 'INF');
        }
    }
    else {
        wlog('ubuaux_embargo_alert has been synced for dissertations', 'INF');
    }
	
	//now sync the printing on demand data
    $aPodData = findIgdissPodData();
	//print_r($aPodData);
    if (empty($aPodData)) {
        $sPodLine = 'no diss with pod';
        wlog($sPodLine, 'INF');
    }
    else {
        foreach ($aPodData as $aOneItem) {
            if (isset($aOneItem['handle'])) {
				$sHandle = $aOneItem['handle'];
				$nPrice = $aOneItem['price_pod'];
				$aItemIdData = $oHandle->getItemIdDb($sHandle);
				//print_r($aItemIdData);
				if (isset($aItemIdData['itemid'])) {
					$nItemId = $aItemIdData['itemid'];
    
					$aItemPodData = array(
					    'handle' => $sHandle,
					    'price' =>  $nPrice,
						'itemid' => $nItemId,
					);
   
					//print_r($aItemPodData);
					$oAux->syncPodItem($aItemPodData);
					$sPodLine = 'podsynced ' . $sHandle;
					wlog($sPodLine, 'INF');
				}
			}
        }
    }
    

	
	
	
	echo "sync done \n";
} //end of bsync == 1



if ($sEhandle == '' && $bSendAlert == 1) {
    $aEmbargoData = $oAux->checkEmbargoAlert($sPubType);
	//print_r($aEmbargoData);
	
    if (isset($aEmbargoData['error'])) {
        wlog($aEmbargoData['error']);
    }
    else {
        $sLine = count($aEmbargoData) . ' embargoes found ';
        logVerbose($sLine, $bVerbose);
        //for each embargo we found, determine if we should send an alert
        //counter to make sure we don't send mail for every embargo during test
        $counter = 0;
        foreach ($aEmbargoData as $aOneEmbargo) {
            $sEmbargoAlertDate = $aOneEmbargo['date'];
           
            if ($sEmbargoAlertDate == $sToday || ($bRetro == 1 && $sEmbargoAlertDate < $sToday)) {
                $sLine = 'send email for number ' . $counter;
                logVerbose($sLine, $bVerbose);
                
                if ($bMailMode == 'send' && $bSendAlert == 1) {
                    sendEmail($bMailMode, $aOneEmbargo);
                }
                else { //for debug
                    if ($counter > 10 && $counter < 15 && $bSendAlert == 1) {
                       sendEmail($bMailMode, $aOneEmbargo);
                    }
                }
       
                $counter++;
            }
            
        }
        echo $counter . " embargoes found \n";
    }
} //end of if ehandle==''

function findIgdissEmailData()
{
 	$sIgdissServiceUrl = IGDISSSERVICE . '/emaildata';

	$sEmailDataFound = file_get_contents($sIgdissServiceUrl);
	$aEmailData = json_decode($sEmailDataFound, true);

	return $aEmailData;


}


function findIgdissEmail($aIgdissRecords, $aFutureEmbargoHandles)
{
    $aRecordsToUpdate = array();
     
    foreach ($aIgdissRecords as $aOneRecord) {
        $sHandle = $aOneRecord['handle'];
        if (array_key_exists($sHandle, $aFutureEmbargoHandles)) {
            if (isset($aOneRecord['email1'])) {
                $sOldEmail = $aFutureEmbargoHandles[$sHandle];
                $sNewEmail = $aOneRecord['email1'];
                $aRecordsToUpdate[$sHandle] = array('existingemail' => $sOldEmail, 'newemail' => $sNewEmail);
            }
        }
    }
    return $aRecordsToUpdate;
}


function findIgdissPodData()
{
	$sIgdissServiceUrl = IGDISSSERVICE . '/poddata';
	$sDataFound = file_get_contents($sIgdissServiceUrl);
	$aPodData = json_decode($sDataFound, true);
	
	return $aPodData;
}


/**
 * This is the alert mail.
 * 
 */
function sendEmail($bMailMode, $aItemEmbargoData) 
{
    $sAlertText = 'The embargo on the dissertation ' . $aItemEmbargoData['title'] 
        . ' will expire on ' . $aItemEmbargoData['embargodate'] . "\n" 
        . 'From then on, this dissertation will be freely available in the Utrecht University Repository and on the Internet.';
    $sAlertText .= "\n\n";
    $sAlertText .= 'Please contact us at support-igitur@uu.nl should you have any questions.';
    $sAlertText .= "\n\n --\n";
    $sAlertText .= "Utrecht University Library\n";
    $sAlertText .= "http://www.uu.nl/en/university-library/publishing \n";
    
    $sIntroText = 'The following email would be sent to ' . $aItemEmbargoData['email'];
    $sFrom = 'm.muilwijk@uu.nl';
    $sSubject = 'Doctoral thesis embargo alert - test';
    $sTo = 'm.muilwijk@uu.nl';
    
    if ($bMailMode == 'send') {
        $sFrom = 'repository@uu.nl';
        $sSubject = 'Doctoral thesis embargo alert';
        $sTo = $aItemEmbargoData['email'];
        $sHeaders = 'From:' . $sFrom . "\r\n";
        $sHeaders .= 'BCC: m.muilwijk@uu.nl' . "\r\n";
        
        //mail($sTo, $sSubject, $sAlertText, $sHeaders);
		
		$sLine = 'Mail sent to ' . $sTo;
		wlog($sLine, 'INF');
    }
    else {
        $sMessage = $sIntroText . "\n\n" . $sAlertText;
        $sHeaders = 'From:' . $sFrom . "\r\n";
        mail($sTo, $sSubject, $sMessage, $sHeaders);
		
		$sLine = 'Mail sent to ' . $sTo;
		wlog($sLine, 'INF');

    }

    return;
}

function usage()
{
    echo "usage: embargoalert.php [-asvrh][--ehandle] \n";
    echo "  -a: send embargo alerts \n";
    echo "  -s: sync the embargo alert table, do not send alerts \n";
    echo "  -v: verbose";
    echo "  -r: retro; get past embargos \n";
    echo "  -h: this help \n";
    echo " --e: get the embargo date for the given handle\n";
    
    exit();
}

function logVerbose($sLine, $bVerbose)
{
    if ($bVerbose == 1) {
        wlog($sLine, 'INF');
    }
    
    return 1;
}