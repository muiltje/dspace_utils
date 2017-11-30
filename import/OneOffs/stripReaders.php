<?php

/* 
 * Strip all "anonymous read" resource policies from Reader bitstreams
 */

require_once '../import_init.php';

require_once CLASSES . 'ItemMetadata.php';
require_once (CLASSES . 'Bundle.php');
require_once (CLASSES . 'Bitstream.php');
require_once (CLASSES . 'ResourcePolicy.php');

$oItemMetadata = new ItemMetadata();
$oBundle = new Bundle();
$oBitstream = new Bitstream();
$oResourcePolicy = new ResourcePolicy();

$aReaderItems = array();
$aUUReaders = $oItemMetadata->findItemByMetadata(151, 'readers');
$aUvAReaders = $oItemMetadata->findItemByMetadata(151, 'readersuva');

foreach ($aUUReaders['itemids'] as $nItemId) {
    $aReaderItems[] = $nItemId;
}
foreach ($aUvAReaders['itemids'] as $nItemId) {
    $aReaderItems[] = $nItemId;
}

$aRemovablePolicies = array();

foreach ($aReaderItems as $nItemId) {
    //get bundles 
    $aItemBundles = $oBundle->getItemBundles($nItemId);
            
    if (count($aItemBundles) > 0 ) {
        foreach ($aItemBundles as $aOneBundle) {
            //check if there are resource policies for the bundles
            $nBundleId = $aOneBundle['bundle_id'];
            
            //we don't care about the license bundle, because that's always open
            $aBundleDetails = $oBundle->getBundleDetails($nBundleId);
            if (isset ($aBundleDetails['name']) && $aBundleDetails['name'] != 'LICENSE') {
                $aBundlePolicies = $oResourcePolicy->getResourcePolicies(1, $nBundleId);
                
                if (isset($aBundlePolicies['error'])) {
                    wlog('cannot get policies for bundle', 'INF');
                }
                else {
                    //check if anon permissions have been set for this bundle
                    $sAnonSet = checkAnonRead($aBundlePolicies);   
                    if ($sAnonSet == 'y') {
                        //remove anon read if it has been set
                        foreach ($aBundlePolicies as $aOnePolicy) {
                            if ($aOnePolicy['epersongroup_id'] == 0 && $aOnePolicy['action_id'] == 0) {
                                $nPolicyId = $aOnePolicy['policy_id'];
                                $aRemovablePolicies[] = array(
                                    'policyid' => $nPolicyId, 
                                );
                            }
                        } // end of "foreach bundle policy"
                    } //end of "if anon set for bundle"
                } //end of "do we have bundle policies"
                
                //that's this bundle's policies checked
                //now do the same for bitstreams
                $aBundleBitstreams = $oBitstream->getBundleBitstreams($nBundleId);
                foreach ($aBundleBitstreams as $aOneBitstream) {
                    $nBitstreamId = $aOneBitstream['bitstream_id'];
                    $aBitstreamPolicies = $oResourcePolicy->getResourcePolicies(0, $nBitstreamId);
                       
                    if (isset($aBitstreamPolicies['error'])) {
                        wlog('could not get policies for bitstream', 'INF');
                    }
                    else {
                        $sAnonSet = checkAnonRead($aBitstreamPolicies);
                        if ($sAnonSet == 'y') {
                            //remove anon read if it has been set
                            foreach ($aBitstreamPolicies as $aOnePolicy) {
                                if ($aOnePolicy['epersongroup_id'] == 0 && $aOnePolicy['action_id'] == 0) {
                                    $nPolicyId = $aOnePolicy['policy_id'];
                                    $aRemovablePolicies[] = array(
                                        'policyid' => $nPolicyId, 
                                    );
                                }
                            } // end of "for each bitstream policy"
                        } // end of "is anon set for bitstream"
                    } //end of "do we have bitstream policies"
                } //end of "foreach bitstream of this bundle"
            } // end of "is this the license bundle"
        } //end of "foreach bundle of this item"
    }
}

//echo count($aRemovablePolicies) . ' removable policies ' . "\n";

foreach ($aRemovablePolicies as $aOneRemovePolicy) {
    $nPolicyId = $aOneRemovePolicy['policyid'];
    //wlog('I would remove policy ' . $nPolicyId, 'INF');
    wlog ('Removing policy ' . $nPolicyId, 'INF');
 
    $remove = $oResourcePolicy->deleteResourcePolicy($nPolicyId);
    
    if ($remove == 'n') {
        $sMessage = 'could not remove resource policy ' . $nPolicyId;
        wlog($sMessage, 'INF');
    }
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
        $nEpersonGroupId = $aPolicy['epersongroup_id'];
        $aPersons[] = $nEpersonGroupId;
    }
    
    if (in_array(0, $aPersons)) {
        $sAnonSet = 'y';
    }
    
    return $sAnonSet;
}