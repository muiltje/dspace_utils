<?php

/* 
 * Find all dissertations added before a given date
 */

require_once '../init.php';

$sDissContentType = 'Dissertation';
$sContentTypeFieldId = 146;
$sDateIssuedFieldId = 27;
$sDateAvailableFieldId = 25;

$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$nBeforeYear = 2015;

$aAllDisses = $oItemMetadata->findItemByMetadata($sContentTypeFieldId, $sDissContentType);
echo count($aAllDisses['itemids']) . "\n";

$aItemsBeforeDate = array();

foreach ($aAllDisses['itemids'] as $nFoundItemId) {
    $sFoundDateAvailable = '';
    $sFoundDateIssued = '';
    $aDateAvailableData = $oItemMetadata->getMetadataValue($nFoundItemId, $sDateAvailableFieldId);
    if (!empty($aDateAvailableData)) {
        $sFoundDateAvailable = $aDateAvailableData['values'][0];
    }
    $aDateIssuedData = $oItemMetadata->getMetadataValue($nFoundItemId, $sDateIssuedFieldId);
    if (!empty($aDateIssuedData)) {
        $sFoundDateIssued = $aDateIssuedData['values'][0];
    }
    
    $sYearAvailable = substr($sFoundDateAvailable, 0, 4);
    $nAvYear = (int) $sYearAvailable;
    if ($nAvYear < $nBeforeYear) {
        $aItemsBeforeDate[] = $nFoundItemId;
    }
    
    /*
    if ((int) substr($sFoundDateAvailable, 0, 4) < $nBeforeYear || (int) substr($sFoundDateIssued, 0, 4) < $nBeforeYear) {
        $aItemsBeforeDate = array(
           'itemid' => $nFoundItemId, 
           'dateissued' => $sFoundDateIssued,
           'dateavailable' => $sFoundDateAvailable,
        );
    }
     * 
     */
}

echo count($aItemsBeforeDate) . ' items before this year' . "\n";