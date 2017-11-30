<?php

/**
 * For each item in the given collection(s):
 * find the current status (Open/Closed/Embargoed)
 * write it to a file (with time and date)
 * See if the item was already present in the previous file
 * if yes, get the status and compare to the current one
 * If the status has changed, log (and mail?)
 */

require_once 'init.php';

require_once CLASSES . 'FileAndDisk.php';
require_once CLASSES . 'Collection.php';

$oCollection = new Collection();
$oFandD = new FileAndDisk();
$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$nPureFieldId = 304;
$nDateEmbargoFieldId = 193;
$nAccessFieldId = 161;

$aRepCollections = array(587, 617); //PROD

$sThisMoment = date('Y-m-d_H');
$sMonitorLogFile = LOGPATH . 'pure_monitor_' . $sThisMoment . '.log';
$sInterval = '-1 hour';
$sPreviousMoment = date('Y-m-d_H', strtotime($sInterval));
$sPrevMonitorLogFile = LOGPATH . 'pure_monitor_' . $sPreviousMoment . '.log';

$aPreviousStates = array();
$ph = fopen($sPrevMonitorLogFile, "r");
while (($sReadLine = fgets($ph)) !== FALSE) {
	//$sFirstSpace = strpos($sReadLine, ' ');
	//$nFoundItemId = substr($sReadLine, 0, $sFirstSpace);
	$aLineParts = explode(' ', trim($sReadLine));
	$nFoundItemId = $aLineParts[0];
	//echo $nFoundItemId . ' - ';
	$sBaseRights = $aLineParts[4]; 
	//echo $sBaseRights;
	$sEmbargoDate = '';
	if ($sBaseRights == 'Open') {
		if (isset($aLineParts[10]) && substr($aLineParts[10], 0, 2) == '20') {
			//old embargo that hasn't been cleaned up
			$sEmbargoDate = $aLineParts[10];
		}
	}
	else {
		if (isset($aLineParts[9])) {
			//echo ' - ' . $aLineParts[9];
			$sEmbargoDate = $aLineParts[9];
		}
		//echo "\n";
	}
	$aPreviousStates[$nFoundItemId] = array('baseaccess' => $sBaseRights, 'embargo' => $sEmbargoDate);
}
fclose($ph);

//$aCurrentStates = array();
$fh = fopen($sMonitorLogFile, "w");
foreach ($aRepCollections as $nCollectionId) {
	//find all items
	$aItems = $oItem->getCollectionItems($nCollectionId);
	//$aItems = $oCollection->getCollectionItems($nCollectionId);
	foreach ($aItems as $aOneItem) {
		$nItemId = $aOneItem['item_id'];
		
		$sLogLine = $nItemId . ' has access rights ';
//		//get access rights
		$aRightsData = $oItemMetadata->getMetadataValue($nItemId, $nAccessFieldId);
		$sAccessRights = $aRightsData['values'][0];
		$sLogLine .= $sAccessRights;
		
		//get embargo date
		$sDateEmbargo = '';
		$aEmbargoData = $oItemMetadata->getMetadataValue($nItemId, $nDateEmbargoFieldId);
		if (!empty($aEmbargoData['values'])) {
			$sDateEmbargo = $aEmbargoData['values'][0];
			$sLogLine .= ' with embargo date ' . $sDateEmbargo;
		}
		
		//$aCurrentStates[$nItemId] = array('access' => $sAccessRights, 'embargo' => $sDateEmbargo);
		
		if (isset($aPreviousStates[$nItemId])) {
			$aPrevStateData = $aPreviousStates[$nItemId];
			
			//compare access rights
			$sPrevBaseRights = $aPrevStateData['baseaccess'];
			$aCurrentRights = explode(' ', $sAccessRights);
			if ($aCurrentRights[0] == $sPrevBaseRights) {
				//nothing changed
			}
			else {
				//there was a change in access rights
				$sLogLine .= ' CHANGED access rights from ' . $sPrevBaseRights . ' to ' . $sAccessRights;
				//echo ' CHANGED access rights from ' . $sPrevBaseRights . ' to ' . $sAccessRights . "\n";
			}
			
			//compare embargo dates
			$sPrevEmbargoDate = $aPrevStateData['embargo'];
			//echo 'prev embargo date is ' . $sPrevEmbargoDate . "\n";
				
			if (trim($sDateEmbargo) == trim($sPrevEmbargoDate)) {
				//nothing changed
			}
			else {
				$sLogLine .= ' CHANGED embargo date from  ' . $sPrevEmbargoDate . ' to ' . $sDateEmbargo;
				//echo 'embargo date changed from ' . $sPrevEmbargoDate . 'to ' . $sDateEmbargo . "\n";
			}
		}
		else {
			//item is new, so nothing to compare
		}
		
		$sLogLine .= "\n";
		//echo $sLogLine;
		fwrite($fh, $sLogLine);
	}
}
fclose($fh);

//clean up older logs
$nDelDays = 2;
$oFandD->cleanOlderFiles(LOGPATH, '/pure_monitor/', $nDelDays, 'do');

