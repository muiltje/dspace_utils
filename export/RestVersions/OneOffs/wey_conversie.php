<?php
require_once '../init.php';

$nItemId = 226299;
$sHandleId = '222541';
$sHandle = '1874-' . $sHandleId;

$sIndexPage = '468';
$sInputFile = 'weyerman_' . $sHandleId . '_' . $sIndexPage . '.html';
$sNewFile = 'new_weyerman_' . $sHandleId . '_' . $sIndexPage . '.html';

$oBitstream = new RestBitstream();
$oItemMetadata = new RestItemMetadata();

$aSequenceData = $oItemMetadata->getMetadataValue($nItemId, 244);
$sSequence = $aSequenceData['values'][0];
$aPages = getSequencePages($sSequence);


$sOutputText = '';

$fh = fopen($sInputFile, "r");

while (!feof($fh)) {
	$sReadLine = fgets($fh); 
	if (preg_match('/href/', $sReadLine)) {
		$sLinkStart = strpos($sReadLine, '<A href');
		$sStartOfLine = substr($sReadLine, 0, $sLinkStart);
		//do something clever for the link
		$sPageStart = strpos($sReadLine, 'Pagina=');
		$sPotentialNumber = substr($sReadLine, $sPageStart+7, 3);
		$nPageNumber = preg_replace('/[^0-9]/', '', $sPotentialNumber);
				
		//get the file name that goes with this page number
		$sFileName = $aPages[$nPageNumber];
		//now get the bitstream for this file name
		$nBitstreamId = $oBitstream->getBitstreamId($sFileName);
		$aBitstreamInternal = $oBitstream->getBitstreamInternal($nBitstreamId);
		$nInternalId = $aBitstreamInternal['internal_id'];
		$sPageLink = getPageLink($nInternalId);
		//http://objects.library.uu.nl/reader/index.php?obj=1874-222542&lan=nl#page//68/17/58/68175851439472402325495186336876754113.jpg/mode/1up
		$sUrl = 'http://objects.library.uu.nl/reader/index.php?obj=' . $sHandle;
		$sUrl .= '&lan=nl#page//' . $sPageLink . '.jpg/mode/1up';
		
		$sNewLine = $sStartOfLine . '<a href="' . $sUrl . '">' . $nPageNumber . '</a></td></tr>' . "\n";
		$sOutputText .= $sNewLine;
	}
	else {
		$sOutputText .= $sReadLine;
	}
}
fclose($fh);

//echo $sOutputText;

file_put_contents($sNewFile, $sOutputText);



function getSequencePages($sSequence)
{
	$aPages = array();
	
	$aOriginalData = explode('+', $sSequence);
    foreach ($aOriginalData as $sOneOriginalPage) {
        $nPipe = strpos($sOneOriginalPage, '|');
        $nPercent = strpos($sOneOriginalPage, '%');
        //$nSequence = substr($sOneOriginalPage, 0, $nPipe);
        $sFileName = substr($sOneOriginalPage, $nPercent+1);
        $nPageNameLength = $nPercent - $nPipe - 1;
        $sPageName = substr($sOneOriginalPage, $nPipe+1, $nPageNameLength);
    
        $aPages[$sPageName] = $sFileName;
	}
	
	return $aPages;
}


function getPageLink($sInternalId)
{
	$nDigitsPerLevel = 2;
    $nDirectoryLevels = 3;
    
    $sPageLink = '';
    //derive subdirs from internal id
    for ($i=0; $i<$nDirectoryLevels; $i++) {
        $nextstart = $nDigitsPerLevel * $i;
        $sPageLink .= substr($sInternalId, $nextstart, $nDigitsPerLevel) . '/';
    }
    $sPageLink .= $sInternalId;
    
    return $sPageLink;

}