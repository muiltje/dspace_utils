<?php

require_once 'init.php';

require_once (CLASSES . 'Item.php');
require_once (CLASSES . 'ItemMetadata.php');
require_once (CLASSES . 'Bitstream.php');
require_once (CLASSES . 'ResourcePolicy.php');

/**
 * Remove superfluous (double) anon read policies
 */
wlog('==== Dedouble policies start ====', 'INF');

$oItem = new Item();
$oResourcePolicy = new ResourcePolicy();
$oBitstream = new Bitstream();

$aPoliciesToDelete = array();

//get all items modified since the given date
//use this if you only want recently modified items
//or use the other version with a lastmodifiedenddate of today
/*
 * 'two days ago'
 */
$sLastModifiedDate = date('Y-m-d', strtotime("-2 days"));
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

if (isset($aModifiedItems['error'])) {
    echo count($aModifiedItems) . ' items modified since ' . $sLastModifiedDate . "\n";

    foreach ($aModifiedItems as $aOneItem) {
        $nItemId = $aOneItem['item_id'];
        //get all policies for these items
        $aItemPolicies = $oResourcePolicy->getResourcePolicies(2, $nItemId);
    
        if (isset($aItemPolicies['error'])) {
            wlog($aItemPolicies['error'], 'INF');
        }
        else {
            $aAnonItemRead = checkAnon($aItemPolicies);
            //if an item has more than one anon read policy, continue
            if (count($aAnonItemRead) > 1) {
                //echo 'item stuff for item ' . $nItemId . "\n";
                //echo "\n";
                $aPoliciesToDelete[] = findPoliciesToRemove($aAnonItemRead);
                //print_r($aPoliciesToDelete);
                //echo "\n";
            }
    
            //get all bundles for these items
            $aBundles = $oBundle->getItemBundles($nItemId);
            foreach ($aBundles as $aOneBundle) {
                $nBundleId = $aOneBundle['bundle_id'];
                //get all policies for these bundles
                $aBundlePolicies = $oResourcePolicy->getResourcePolicies(1, $nBundleId);
                $aAnonBundleRead = checkAnon($aBundlePolicies);
                //if a bundle has more than one anon read policy, continue
                if (count($aAnonBundleRead) > 1) {
                    $aPoliciesToDelete[] = findPoliciesToRemove($aAnonBundleRead);
                }
        
                //get all bitstreams for these bundles
                $aBitstreams = $oBitstream->getBundleBitstreams($nBundleId);
                foreach ($aBitstreams as $aOneBitstream) {
                    $nBitstreamId = $aOneBitstream['bitstream_id'];
                    //get all policies for these bitstreams
                    $aBitstreamPolicies = $oResourcePolicy->getResourcePolicies(0, $nBitstreamId);
                    $aAnonBitstreamRead = checkAnon($aBitstreamPolicies);
                    //if a bitstream has more than one anon read policy, continue
                    if (count($aAnonBitstreamRead) > 1) {
                        $aPoliciesToDelete[] = findPoliciesToRemove($aAnonBitstreamRead);
                    }
                }
            }
        }
    }
}
$nDeleteCount = 0;
foreach ($aPoliciesToDelete as $aDeletePolicies) {
    foreach ($aDeletePolicies as $aPolicy) {
        //just to make sure
        if ($aPolicy['startdate'] == '') {
            $nDeleteCount++;
            $nPolicyId = $aPolicy['policyid'];
            $del = $oResourcePolicy->deleteResourcePolicyDb($nPolicyId);
            
            if ($del == 'n') {
               $sMessage = 'could not delete resource policy ' . $nPolicyId;
               wlog($sMessage, 'INF');
               $mail = sendMail($sMessage);
            }
        }
    }
}
//$line = $nDeleteCount . ' policies deleted';
//wlog($line, 'INF');
wlog('==== Dedouble policies end ====', 'INF');


/**
 * Get policy_id and start_date for all anon read permissions
 * 
 * @param type $aPolicies 
 */
function checkAnon($aPolicies)
{
    $aDoublePolicies = array();
    
    foreach ($aPolicies as $aPolicy) {
        $nActionId = $aPolicy['action_id'];
        $nEpersonGroup = $aPolicy['epersongroup_id'];
        $nPolicyId = $aPolicy['policy_id'];
        $sStartDate = $aPolicy['start_date'];
        
        if ($nActionId == 0 && $nEpersonGroup == 0) {
            $aDoublePolicies[] = array(
                'policyid' => $nPolicyId,
                'startdate' => $sStartDate,
            );
        }
    }
    
    return $aDoublePolicies;
}


/**
 * We have found more than one anon read policy for the resource
 * Now we want to know which of these we can delete
 * @param type $aAnonPolicies 
 */
function findPoliciesToRemove($aAnonPolicies)
{
    $aPoliciesToRemove = array();
    $aPoliciesToKeep = array();
    $aTemp = array();
    
    //if we have a policy with a start date, keep it
    foreach ($aAnonPolicies as $aAPolicy) {
        $sStartDate = $aAPolicy['startdate'];
        if ($sStartDate != '') {
            $aPoliciesToKeep = $aAPolicy;
        }
        else {
            $aTemp[] = $aAPolicy;
        }
    }
    
    //if at this point there is a policy to keep, the rest can be removed
    if (count($aPoliciesToKeep) > 0) {
        $aPoliciesToRemove = $aTemp;
    }
    else {
        //keep the first policy in the temp array, the rest can be removed
        $aPoliciesToKeep = $aTemp[0];
        for ($i=1; $i<count($aTemp); $i++) {
            $aPoliciesToRemove[] = $aTemp[$i];
        }
    }
    
    return $aPoliciesToRemove;
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
