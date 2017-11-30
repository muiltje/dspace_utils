<?php
/**
 * Find material meeting certain conditions
 * Add a specific licensing text to the items
 */


require_once '../init.php';

$sAccessRightsField = 'dc.rights.accessrights';
$sAccessRightsFieldId = 161;

$oItemMetadata = new ItemMetadata();

/*
$aItems = $oItemMetadata->findItemByMetadata($sAccessRightsFieldId, 'Restricted Access');

echo count($aItems['itemids']) . "\n";

$aItemIds = $aItems['itemids'];
$sOutputFile = 'RestrictedItems.php';
$sOutput = var_export($aItemIds, true);
file_put_contents($sOutputFile, $sOutput);

echo "done";
 * 
 */

/*
require_once 'RestrictedItems.php';
echo count($aRestrictedItemsFound) . "\n";

$sFile = 'RestrictedDigitized.csv';
//$sFirstLine = '"Item identifier";"Metadata found"' . "\n";
$fh = fopen($sFile, "a");
//fwrite($fh, $sFirstLine);

$nStart = 14500;
$nNumber = 68;
$nEnd = $nStart+$nNumber;

for ($i=$nStart; $i<$nEnd; $i++) {
	$nItemId = $aRestrictedItemsFound[$i];
	$aMetadata = $oItemMetadata->getAllMetadata($nItemId);
	$sDigitized = '';
	if (empty($aMetadata)) {
		$sLine = '"' . $nItemId . '";"no"' . "\n";
		fwrite($fh, $sLine);
	}
	else {
		foreach ($aMetadata as $aOneField) {
			$sKey = $aOneField['key'];
			if ($sKey == 'dc.identifier.digitization') {
				$sDigitized = 'y';
			}
		}
		if ($sDigitized == 'y') {
			$sLine = '"' .  $nItemId . '";"y"' . "\n";
			fwrite($fh, $sLine);
		}
	}
}
fclose($fh);
echo "done to $nEnd \n";
 * 
 */

/*
$aRestrictedDigitizedItems = array();
$nCounter = 0;
$sFile = 'RestrictedDigitized.csv';
$fh = fopen($sFile, "r");
while (($buffer = fgets($fh)) !== false) {
	$aData = explode(';', $buffer);
	$nItemId = $aData[0];
	$sState = $aData[1];
	if ($sState == 'no') {
		echo 'no data found for item ' . $nItemId . "\n";
	}
	else {
		$aRestrictedDigitizedItems[$nCounter] = preg_replace('/"/', '', $nItemId);
		$nCounter++;
	}
}
echo count($aRestrictedDigitizedItems) . ' restricted digitized items ' . "\n";

$sOutput = var_export($aRestrictedDigitizedItems, true);
$sOutputFile = 'RestrictedDigitized.php';
file_put_contents($sOutputFile, $sOutput);

echo 'now written to file' . "\n";
 * 
 */

require_once 'RestrictedDigitized.php';
$nStart = 0;
$nNumber = 10;
$nEnd = $nStart+$nNumber;
for ($i=$nStart; $i<$nEnd;$i++) {
	$nItemId = $aRestrictedDigitizedItems[$i];
	//set access rights to Open Access
	
	//add new license field
}
