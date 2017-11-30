<?php
require_once 'init.php';

require_once CLASSES . 'ResourcePolicy.php';
require_once CLASSES . 'Item.php';
require_once CLASSES . 'ItemMetadata.php';
require_once CLASSES . 'Bitstream.php';

/**
 * Get all items with last_modified yesterday or today and in_archive=yes
 * For each item:
 *  check if it has a date.embargo later than today
 *      if yes, check if it has resource policies for bitstreams
 *          if yes, update resource policies to date.embargo as start_date
 *          if no, add resource policies for anon read on bitstreams 
 *              with a start_date set to date.embargo
 *      if no, check if it has resource policies for bitstreams
 *          if no, check if it is Open Access 
 *              if yes, add resource policies for anon read on bitstreams
 * 
 * @todo: should we also set policies on bundles?
  * 
 */
$sStartLine = '==== Add resource policies start ' . date('Ymdh')  . '====';
wlog($sStartLine, 'INF');
echo $sStartLine . "\n";

//Get all items with last_modified yesterday
/*
 * 'a day ago'
 */
$sLastModifiedDate = date('Y-m-d', strtotime("-1 day"));
$oBitstream = new Bitstream();
$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oResourcePolicy = new ResourcePolicy();

$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

$aCheckedPolicies = array();

foreach ($aModifiedItems as $aOneItem) {
    if ($aOneItem['in_archive'] == 't' && $aOneItem['withdrawn'] == 'f') {
        $nItemId = $aOneItem['item_id'];
		
		$aAccessRights = $oItemMetadata->getMetadataValue($nItemId, 161);
		if (isset($aAccessRights['error'])) {
            $sLine = "can not get accessrights for $nItemId";
            wlog($sLine, 'INF');
        } 
        else {
            $sAccessRights = '';
            if (isset($aAccessRights['values'])) {
                $sAccessRights = $aAccessRights['values'][0];
            }
            
            //check if it has a date.embargo (field 193)
            $aDateEmbargo = $oItemMetadata->getMetadataValue($nItemId, 193);
            if (isset($aDateEmbargo['error'])) {
                wlog($aDateEmbargo['error'], 'INF');
            }
            else {
                $sClosed = 'y';
				if (isset($aDateEmbargo['values']) && count($aDateEmbargo['values']) > 0) {
                    $sDateEmbargoFound = $aDateEmbargo['values'][0];
                  
                    $sFixedDateEmbargo = '';
                    if (preg_match('/^20\d\d-\d\d-\d\d$/', $sDateEmbargoFound)) { 
                        $sFixedDateEmbargo = $sDateEmbargoFound;
                    }
                    elseif (preg_match('/^20\d\d-\d\d$/', $sDateEmbargoFound)) {
                        $sFixedDateEmbargo = $sDateEmbargoFound . '-01';
                    }
                    elseif (preg_match('/^20\d\d$/', $sDateEmbargoFound)) {
                        $sFixedDateEmbargo = $sDateEmbargoFound . '-01-01';
                    }
                    elseif ($sDateEmbargoFound == '') {
                        //just in case there is an empty date.embargo field
                        $sFixedDateEmbargo = '';
                    }
                    else {
                        $sFixedDateEmbargo = '999'; 
                        //if the date found is not well-formed,
                        //set 'fixed' date to something invalid
                        //to make sure the the status is set to Closed in the next step
				    }
					if ($sFixedDateEmbargo != '') {
                        $bValid = checkEmbargoDate($sFixedDateEmbargo);
                        if (!$bValid) {
                            $sClosed = 'y';
                        }
                        else {
                            $sDateEmbargo = $sFixedDateEmbargo;
							$sClosed = 'n';
                        }
                    }
                    //if the date.embargo is for 2050, this is equivalent to closed access
                    if (preg_match('/2050/', $sDateEmbargo)) {
                        $sClosed = 'y';
                    }
				}
				else {
                    //no date.embargo set
                    $sDateEmbargo = '';
                    $sClosed = 'n';
                }
				
				$aBitstreamIds = getBitstreamsRest($nItemId, $oBitstream);
				//print_r($aBitstreamIds);

				$aCheckedPolicies[$nItemId] = checkPolicies($aBitstreamIds, $sClosed, $sDateEmbargo, $oResourcePolicy);
			}
		}
	}
	//add a little pause to give rest the time to deal with all the requests?
	sleep(5);
}
//print_r($aCheckedPolicies);


$aErrorMessages = array();
foreach ($aCheckedPolicies as $nItemId => $aCheckedForOneItem) {
	$aProblems = $aCheckedForOneItem['problems'];
	if (!empty($aProblems)) {
		foreach ($aProblems as $nBitstreamId) {
			$sMessage = 'There was a problem finding policy data for bitstream ' . $nBitstreamId;
			wlog($sMessage, 'ERROR');
		}
	}
	
	$aMissingPolicies = $aCheckedForOneItem['missing'];
	foreach ($aMissingPolicies as $aOneNewPolicy) {
        $nResourceId = $aOneNewPolicy['resource'];
		$sStartDate = null;
		if ($aOneNewPolicy['startdate'] != '') {
	        $sStartDate = $aOneNewPolicy['startdate'];
		}
		$aPolicyData = array(
			'action' => 'READ',
			'epersonId' => -1,
			'groupId' => 0,
			'resourceId' => $nResourceId,
			'resourceType' => 'bitstream',
			'startDate' => $sStartDate
		);
		
		//echo 'I would add ';
		//print_r($aPolicyData);
		$aAddResult = $oResourcePolicy->addBitstreamPolicy($aPolicyData);
		if (empty($aAddResult)) {
			$sMessage = 'could not add resource policy for bitstream' . $nResourceId . ' of item ' . $nItemId;
            wlog($sMessage, 'INF');
			$aErrorMessages[] = $sMessage;
            //$mail = sendMail($sMessage);
		}
    }
	
	$aUpdatablePolicies = $aCheckedForOneItem['updatable'];
    //now the updates
    foreach ($aUpdatablePolicies as $aOneUpdatePolicy) {
        $nPolicyId = $aOneUpdatePolicy['policyid'];
        $sStartDate = $aOneUpdatePolicy['startdate'];
    
		
    	$update = $oResourcePolicy->updateBitstreamPolicy($sStartDate, $nPolicyId);
    
		//update is done directly on the datatabase, so we get the result in a different form
        if ($update == 'n') {
            $sMessage = 'could not update resource policy ' . $nPolicyId;
            wlog($sMessage, 'INF');
			$aErrorMessages[] = $sMessage;
            //$mail = sendMail($sMessage);
        }
    }
	
	$aRemovablePolicies = $aCheckedForOneItem['removable'];
    //and the errant policies that shouldn't be there
    foreach ($aRemovablePolicies as $aOneRemovePolicy) {
        $nPolicyId = $aOneRemovePolicy['policyid'];
		$nBitstreamId = $aOneRemovePolicy['bitstreamid'];
        wlog ('Removing policy ' . $nPolicyId, 'INF');
 
       $aDeleteResult = $oResourcePolicy->deleteBitstreamPolicy($nBitstreamId, $nPolicyId);
    
        if (empty($aDeleteResult)) {
            $sMessage = 'could not remove resource policy ' . $nPolicyId;
            wlog($sMessage, 'INF');
            //$mail = sendMail($sMessage);
			$aErrorMessages[] = $sMessage;
        }
    }
}

if (count($aErrorMessages) > 0) {
	$sMailMessage = 'Problems in addResourcePolicies' . "\n\n";
	foreach ($aErrorMessages as $sMessage) {
		$sMailMessage .= $sMessage . "\n";
	}
	sendMail($sMailMessage);
}



function checkEmbargoDate($sEmbargoDate)
{
    $sYear = substr($sEmbargoDate, 0, 4);
    $sMonth = substr($sEmbargoDate, 5, 2);
    $sDay = substr($sEmbargoDate, 8, 2);
    
    $bValid = checkdate($sMonth, $sDay, $sYear);
    
    return $bValid;
}

function findCommunity($nItemId, $oItem)
{
	$aCollComm = $oItem->getItemCollection($nItemId);
	$sCommunity = $aCollComm['parentCommunityList'][0]['name'];
	
	return $sCommunity;
}


function getBitstreamsRest($nItemId, $oBitstream)
{
	$aBitstreamIds = array();
	$aData = $oBitstream->getItemBitstreamsRest($nItemId);
	if (!empty($aData)) {
		$aBitstreams = $aData['bitstreams'];
		foreach ($aBitstreams as $aOneBitstream) {
			//skip license files
			if ($aOneBitstream['bundleName'] != 'LICENSE') {
				$nBitstreamId = $aOneBitstream['id'];
				$aBitstreamIds[] = $nBitstreamId;
			}
		}
	}
	return $aBitstreamIds;
}

/*
function getBitstreamsDb($nItemId, $oBundle, $oBitstream)
{
	$aBitstreamIds = array();
	
	$aItemBundles = $oBundle->getItemBundles($nItemId);
            
    if (count($aItemBundles) > 0 ) {
        foreach ($aItemBundles as $aOneBundle) {
            //check if there are resource policies for the bundles
            $nBundleId = $aOneBundle['bundle_id'];
            
            //we don't care about the license bundle, because that's always open
            $aBundleDetails = $oBundle->getBundleDetails($nBundleId);
            if (isset ($aBundleDetails['name']) && $aBundleDetails['name'] != 'LICENSE') {
 				//we no longer care for bundle policies, just bitstream
				//so let's get the bitstreams for this bundle
                $aBundleBitstreams = $oBitstream->getBundleBitstreams($nBundleId);
                foreach ($aBundleBitstreams as $aOneBitstream) {
                    $nBitstreamId = $aOneBitstream['bitstream_id'];
					$aBitstreamIds[] = $nBitstreamId;
				}
			}
		}
	}
  	return $aBitstreamIds;
}
 * 
 */

/**
 * Get all the policies for all bitstream-ids for an item
 * 
 * if date.embargo = 2050 && exists anon read: remove anon read
 * if date.embargo = 2050 && no anon read: do nothing
 * if date.embargo != 2050 && exists anon read: update policy
 * if date.embargo != 2050 && no anon read: add policy
 * if no date.embargo && exists anon read: do nothing
 * if no date.embargo && no anon read: add policy
 *
 * 
 * @param type $aBitstreamIds
 * @param type $sClosed
 * @param type $sDateEmbargo
 * @param type $oResourcePolicy
 */
function checkPolicies($aBitstreamIds, $sClosed, $sDateEmbargo, $oResourcePolicy)
{
	$aMissingPolicies = array();
    $aUpdatablePolicies = array();
    $aRemovablePolicies = array();
	$aProblems = array();

	foreach ($aBitstreamIds as $nBitstreamId) {
	
		//echo $nBitstreamId . "\n";
		//$sPolicyData = $oResourcePolicy->getBitstreamPolicies($nBitstreamId);
		//$aPolicies = json_decode($sPolicyData, true);
		$aPolicies = $oResourcePolicy->getBitstreamPolicies($nBitstreamId);
		
		if (!empty($aPolicies)) {
			$sAnonSet = checkAnonRead($aPolicies);
			if ($sAnonSet == 'n') {
				if ($sClosed == 'n') { //items that can have anonymous read access                            
					//add DateEmbargo, even if it's empty
					$aMissingPolicies[] = array(
						'resource'=>$nBitstreamId, 
						'startdate' => $sDateEmbargo
					);
				}
			}
			elseif ($sAnonSet == 'y' && $sDateEmbargo != '' && $sClosed == 'y') {
				//closed access items with anon read permissions.
				//this shouldn't happen, but check to make sure and remove
				//any errant policies
				foreach ($aPolicies as $aOnePolicy) {
					if ($aOnePolicy['groupId'] == 0 && $aOnePolicy['action'] == 'READ') {
						$nPolicyId = $aOnePolicy['id'];
						$aRemovablePolicies[] = array(
							'policyid' => $nPolicyId, 
							'bitstreamid' => $nBitstreamId,
						);
					}
				}
			}
			elseif ($sAnonSet == 'y' && $sDateEmbargo != '' && $Closed = 'n') {
				foreach ($aPolicies as $aOnePolicy) {
					if ($aOnePolicy['groupId'] == 0 && $aOnePolicy['action'] == 'READ') {
						$nPolicyId = $aOnePolicy['id'];
						$aUpdatablePolicies[] = array(
							'policyid' => $nPolicyId, 
							'startdate' => $sDateEmbargo,
							'bitstreamid' => $nBitstreamId,
						);
					}
				}
			}
			else {
				//$sAnonSet == n && $sClosed == 'y': no need to do anything,
				//this bitstream doesn't get a resource policy
			}
		}
		else {
			//we couldn't find anything about policies 
			$aProblems[] = $nBitstreamId;
		}
	
	}
	
    $aCheckedPolicies = array(
        'missing' => $aMissingPolicies, 
        'updatable' => $aUpdatablePolicies,
        'removable' => $aRemovablePolicies,
		'problems' => $aProblems,
    );
    
    return $aCheckedPolicies;
}


/**
 *
 * @param type $aPolicies
 * @return string 
 */
function checkAnonRead($aPolicies)
{
    $sAnonSet = 'n';
    
    $aPersons = array();
    
    foreach ($aPolicies as $aPolicy) {
		$nEpersonGroupId = $aPolicy['groupId'];
		$aPersons[] = $nEpersonGroupId;
    }
    
    if (in_array(0, $aPersons)) {
        $sAnonSet = 'y';
    }
    
    return $sAnonSet;
}

function sendMail($sMessage)
{
    $sFromAddress = DEVEMAIL;
    $sToAddress = DEVEMAIL;
    
    $sSubject = 'Probleem met resource policies';
    
     
    $sHeaders = 'From:' . $sFromAddress . "\r\n";
     
    mail($sToAddress, $sSubject, $sMessage, $sHeaders);
    
    return 1;
}