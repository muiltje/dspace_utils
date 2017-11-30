<?php
/*
 * Find newly imported items (including those in the pool)
 * and check their author fields. If the field contains a DAI,
 * split off that DAI and save it in an authority field. Then save
 * the authorname without DAI. 
 */
require_once 'import_init.php';

require_once (CLASSES . 'ItemMetadata.php');

$oItemMetadata = new ItemMetadata();

$aContributorFields = array(5, 166, 221);

$sStartLine = 'START author_split for ' . date('Ymd');
wlog($sStartLine, 'INF');


foreach ($aContributorFields as $nFieldId) {
    $aItemsToCorrect = $oItemMetadata->findUncorrectedAuthors($nFieldId);

    $sLine = count($aItemsToCorrect) . ' items to correct for field ' . $nFieldId;
    //echo $sLine . "\n";
    wlog($sLine, 'INF');
    
    foreach ($aItemsToCorrect as $aOneItem) {
        $sOrgAuthorValue = $aOneItem['text_value'];
        $nValueId = $aOneItem['metadata_value_id'];
        $sOrgAuthorityValue = $aOneItem['authority'];
        $aSplitValues = splitAuthorString($sOrgAuthorValue);
        $sNewAuthorValue = $aSplitValues['authorname'];
        $sNewAuthorityValue = $sOrgAuthorityValue;
        $nConfidence = 600;
        
   
        //if there already is an authority value, do not overwrite it
        if ($sOrgAuthorityValue != NULL && $sOrgAuthorityValue != '') {
            $sNewAuthorityValue = $sOrgAuthorityValue;
        }
        else {
            $sNewAuthorityValue = $aSplitValues['DAI'];
            $nConfidence = 600;
            //in case the new authority value is also empty
            if ($sNewAuthorityValue == '') {
                $nConfidence = -1;
            }
        }
        
        $aNewValues = array(
            'metadata_value_id' => $nValueId,
            'author_name' => $sNewAuthorValue,
            'authority' => $sNewAuthorityValue,
            'confidence' => $nConfidence,
        );
        
        //show result for debug
        //print_r($aNewValues);
        
        $aResult = $oItemMetadata->updateAuthorRecord($aNewValues);
        //print_r($aResult);
        //wlog($aResult['sql'], 'INF');
    }
    
}

$sEndLine = 'END of author_split';
wlog($sEndLine, 'INF');


function splitAuthorString($sValue)
{
        $aParts = explode('|', $sValue);
        $sName = $aParts[0];
        $sDAI = $aParts[1];
        
        $aSplitValues = array('authorname' => $sName, 'DAI' => $sDAI);
        
        return $aSplitValues;
}
