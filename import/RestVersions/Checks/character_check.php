<?php
require_once '/home/dspace/utils/newimport/import_init.php';
require_once (CLASSES . 'Item.php');
require_once (CLASSES . 'ItemMetadata.php');
require_once (CLASSES . 'FixEncoding.php');

$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oEncoding = new FixEncoding();


$aFieldsToMakeSafe = array(31 => 230, 143 => 229, 131 => 131);
$aControlChars = array("\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",);
$aWrongChars = array(
    "Ã¿" => "ÿ",
    "Ã©" => "é",
    "Ã§" => "ç",
    "Ã¯" => "ï",
    "Ã«" => "ë",
    "Ã¨" => "è",
    "Ã¤" => "ä",
    "Ã¶" => "ö",
    "â " => "'",
    "Ã³" => "ó",
    "Ã¡" => "á",
    "Ã¼" => "ü",
    "â " => "–",
    "Ã±" => "ñ",
    );

//retro
//$sStartDate = '2013-11-27';
//$sEndDate = '2014-02-03';
//$aModifiedItems = $oItem->getModifiedBetweenItems($sStartDate, $sEndDate);
//recent items
$sLastModifiedDate = date('Y-m-d', strtotime("-1 month"));
$aModifiedItems = $oItem->getModifiedItems($sLastModifiedDate);

/*
 * Go through abstract and title fields of items that have been added
 * or modified in the last month. Check if there are any problematic characters
 * in those fields.
 * Use the list of "dangerous" characters to check;
 * correct the fields with the FixEncoding class and send mail that you did 
 * that. Use "original" version of fields to store unchanged version.
 */
$aCorrectedItems = array();

//check each field
foreach ($aModifiedItems as $aItem) {
    $nItemId = $aItem['item_id'];
    foreach ($aFieldsToMakeSafe as $nFieldId => $nOriginal) {
        $aMetadata = $oItemMetadata->getMetadataValue($nItemId, $nFieldId);
        if (isset($aMetadata['values']) && !empty($aMetadata['values'])) {
             foreach ($aMetadata['values'] as $sValue) {
                $bValueNeedsCorrecting = 'n';
                //foreach ($aControlChars as $sChar) {
                 foreach ($aWrongChars as $sChar => $sGoodChar) {
                    if (strpos($sValue, $sChar)) {
                        echo 'suspicious character found in field ' . $nFieldId . ' for item ' . $nItemId . "\n";
                        $bValueNeedsCorrecting = 'y';
                     }
                 }
                 if ($bValueNeedsCorrecting == 'y') {
                     //if ($nFieldId != $nOriginal) {
                        //$oItemMetadata->addMetadataValue($nItemId, $nOriginal, $sValue, -1);
                    //}
                    //echo 'old value is ' . $sValue . "\n";
                    $sNewValue = strtr($sValue, $aWrongChars);
                    //echo 'new value is ' . $sNewValue . "\n";
                    $oItemMetadata->updateMetadataValue($nItemId, $nFieldId, $sNewValue, -1);
                    $aCorrectedItems[] = array('item' => $nItemId, 'field' => $nFieldId);
                 }

            }
        }
    }
}

if (!empty($aCorrectedItems)) {
    $sSubject = 'DSpace metadata correction';
    $sEmailFrom = NOREPLY;
    $sEmailTo = 'm.muilwijk@uu.nl';
    
    $sMailText = "Some metadata were corrected because of troublesome characters \n";
    
    foreach ($aCorrectedItems as $aItem) {
        if ($aItem['field'] == 31) {
            $sMailText .= 'Abstract ';
        }
        elseif ($aItem['field'] == 131) {
            $sMailText .= 'Keyword ';
        }
        else {
            $sMailText .= 'Title ';
        }
        $sMailText .= 'for item number ' . $aItem['item'] . "\n";
    }
    
    $sHeaders = 'From:' . $sEmailFrom . "\r\n";    
    
    mail($sEmailTo, $sSubject, $sMailText, $sHeaders);

}



?>
