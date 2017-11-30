<?php

/*
 * Make an export voor IDEAS/REPEC.
 * They can't handle OAI-PMH or our regular XML, so we do it their way
 */
require_once 'init.php';

$sRepecExportDir = EXPORTBASE;

$sBaseUrl = substr(HOME_URL,0,-7);

//@todo: rewrite this to use field names instead of fieldids, since the REST API returns names

$aMetadataFieldIds = array(
    5, //'author',
    27, // 'date.issued',
    31, // 'abstract',
    131, //keywords
    143, //'title',
    94, //ispartofseries
    162,  //'ispartofvolume',
    163, // 'ispartofissue',
    164, //ispartofstartpage
    165, //ispartofendpage
    72, //uri
);

$aMetadataKeys = array(
	'dc.contributor.author',
	'dc.date.issued',
	'dc.description.abstract',
	'dc.subject.keywords',
	'dc.title',
	'dc.relation.ispartofvolume',
	'dc.relation.ispartofissue',
	'dc.relation.ispartofstartpage',
	'dc.relation.ispartofendpage',
	'dc.identifier.uri',
);


$nExportDefId = 10;
$bDefIsFullDump = 0;

$oItem = new Item();
$oItemMetadata = new ItemMetadata();
$oRBitstream = new Bitstream();
//for debug
//$aFileNames = array();

/**
 * Get items.
 * They're no longer in a separate collection, so search on publisher
 */
//$aItems = $oItem->getExportItems($nExportDefId, $bDefIsFullDump, 0);
//publisher: 76; ispartofseries: 94
$aPublisherItemsFoundZero = $oItemMetadata->findItemByContainsMetadata(76, 'Tjalling C. Koopmans');
$aPublisherItemsFoundOne = $oItemMetadata->findItemByMetadata(76, 'Tjalling C. Koopmans Research Institute');
$aPublisherItemsFoundTwo = $oItemMetadata->findItemByMetadata(76, 'UU USE Tjalling C. Koopmans Institute');
$aPublisherItemsFoundThree = $oItemMetadata->findItemByMetadata(76, 'UU LEG USE Tjalling C. Koopmans Institute');
$aSeriesItemsFoundOne = $oItemMetadata->findItemByMetadata(94, 'Tjalling C. Koopmans Institute Discussion Paper Series');
$aSeriesItemsFoundTwo = $oItemMetadata->findItemByMetadata(94, 'Discussion paper series / Tjalling C. Koopmans Research Institute');

$aItems = array();

$aGroupZero = $aPublisherItemsFoundZero['itemids'];
$aGroupOne = $aPublisherItemsFoundOne['itemids'];
$aGroupTwo = $aPublisherItemsFoundTwo['itemids'];

$aGroupThree = array();
if (!empty($aPublisherItemsFoundThree['itemids'])) {
	$aGroupThree = $aPublisherItemsFoundThree['itemids'];
}
$aGroupFour = array();
if (!empty($aSeriesItemsFoundOne['itemids'])) {
	$aGroupFour = $aSeriesItemsFoundOne['itemids'];
}
$aGroupFive = $aSeriesItemsFoundTwo['itemids'];

foreach ($aGroupZero as $nItemId) {
    if (!in_array($nItemId, $aItems)) {
        $aItems[] = $nItemId;
    }
}
foreach ($aGroupOne as $nItemId) {
    if (!in_array($nItemId, $aItems)) {
        $aItems[] = $nItemId;
    }
}
foreach ($aGroupTwo as $nItemId) {
    if (!in_array($nItemId, $aItems)) {
        $aItems[] = $nItemId;
    }
}
foreach ($aGroupThree as $nItemId) {
    if (!in_array($nItemId, $aItems)) {
        $aItems[] = $nItemId;
    }
}
foreach ($aGroupFour as $nItemId) {
    if (!in_array($nItemId, $aItems)) {
        $aItems[] = $nItemId;
    }
}
foreach ($aGroupFive as $nItemId) {
    if (!in_array($nItemId, $aItems)) {
        $aItems[] = $nItemId;
    }
}

//print_r($aItems);
$aNewFileNames = array();

if (isset($aItems['error'])) {
    
}
else {
    foreach ($aItems as $nItemId) {
		$aMetadata = $oItemMetadata->getAllMetadata($nItemId);
		//print_r($aMetadata);
		
		$aDataToUse = array();
		
		foreach ($aMetadata as $aOneField) {
			$sFieldName = $aOneField['key'];
			if (in_array($sFieldName, $aMetadataKeys)) {
				$aDataToUse[$sFieldName][] = $aOneField['value'];
			}
		}
		
		$aAllAuthors = parseAuthors($aDataToUse['dc.contributor.author']);
		$sTitle = $aDataToUse['dc.title'][0];
		$sAbstract = '';
		if (isset($aDataToUse['dc.description.abstract'])) {
			$sAbstract = $aDataToUse['dc.description.abstract'][0];
		}
		$sDateIssued = $aDataToUse['dc.date.issued'][0];
		$sStartPage = 0;
		if (isset($aDataToUse['dc.relation.ispartofstartpage'])) {
			$sStartPage = $aDataToUse['dc.relation.ispartofstartpage'][0];
		}
		$sEndPage = 0;
		if (isset($aDataToUse['dc.relation.ispartofendpage'])) {
			$sEndPage = $aDataToUse['dc.relation.ispartofendpage'][0];
		}
		$sVolume = '00';
		if (isset($aDataToUse['dc.relation.ispartofvolume'])) {
			$sVolume = $aDataToUse['dc.relation.ispartofvolume'][0];
		}
		$sIssue = '00';
		if (isset($aDataToUse['dc.relation.ispartofissue'])) {
			$sIssue = $aDataToUse['dc.relation.ispartofissue'][0];
		}
		$sSeries = '';
		if (isset($aDataToUse['dc.relation.ispartofseries'])) {
			$sSeries = $aDataToUse['dc.relation.ispartofseries'];
		}
		$sLongUri = $aDataToUse['dc.identifier.uri'][0];
		$sUri = substr($sLongUri, 21);
		$aKeywords = array();
		if (isset($aDataToUse['dc.subject.keywords'])) {
			$aKeywords = $aDataToUse['dc.subject.keywords'];
		}
		
        //get bitstreams
		echo $nItemId . "\n";
		$aItemBsData = $oRBitstream->getItemBitstreamsRest($nItemId);
	//	print_r($aItemBsData);
		//if (empty($aItemsBsData)) {
		//	//second attempt, in case of time out
		//	$aDataTwo = $oRBitstream->getItemBitstreamsRest($nItemId);
		//	$aItemsBsData = $aDataTwo;
		//}
		$aBitstreamsFound = $aItemBsData['bitstreams'];
		foreach ($aBitstreamsFound as $aOneBitstream) {
			$sBitstreamName = $aOneBitstream['name'];
			$sBitstreamFormat = $aOneBitstream['mimeType'];
			$sBundleName = $aOneBitstream['bundleName'];
			
			if ($sBundleName == 'ORIGINAL') {
				$aBitstreams[] = array(
					'name' => $sBitstreamName,
					'format' => $sBitstreamFormat,
				);
			}
		}

	    $sFileName = sprintf("%02d",$sVolume) . sprintf("%02d",$sIssue);
        if (strpos($sVolume, '-')) {
            $sFileName = preg_replace('/-/', '', $sVolume);
        }
        elseif ($sVolume == '00') {
            if (strpos($sIssue, '-')) {
                $sFileName = preg_replace('/-/', '', sprintf("%02d",$sIssue));
            }
            elseif (preg_match('/^\d{1,2}$/', $sSeries)) {
                $sFileName = sprintf("%02d",$sSeries) . sprintf("%02d",$sIssue);
            }
            else {
                $sFileName = '';
            }
        }
        elseif ($sIssue == '00') {
            //try to get info from bitstream name
            $sFileName = getFileNameFromBitstream($aBitstreams);
        }
        else {
            $sFileName = sprintf("%02d",$sVolume) . sprintf("%02d",$sIssue);
        }

         
        if ($sFileName == '') {
            $sDebugLine = 'I cannot export item ' . $nItemId . '; volume is ' 
                    . $sVolume . '; issue is ' . $sIssue 
                    . ' and series is ' . $sSeries;
            wlog($sDebugLine. 'INF');
        }
        else {
            $aNewFileNames[] = $sFileName;
            
            $sExportFile = $sRepecExportDir .  $sFileName . '.rdf';
            
            $fh = fopen($sExportFile, "w");
            fwrite($fh, "Template-Type: ReDIF-Paper 1.0 \n");
            
            foreach ($aAllAuthors as $aOneAuthor) {
                $sAuthorLines = 'Author-Name: ' . $aOneAuthor['authorname'] . "\n";
                $sAuthorLines .= 'Author-X-Name-First: ' . $aOneAuthor['authorfirstname'] . "\n";
                $sAuthorLines .= 'Author-X-Name-Last: ' . $aOneAuthor['authorlastname'] . "\n";
                fwrite($fh, $sAuthorLines);
            }
            
            $sTitleLine = 'Title: ' . $sTitle . "\n";
            fwrite($fh, $sTitleLine);
            
            $sAbstractLine = 'Abstract: ' . $sAbstract . "\n";
            fwrite($fh, $sAbstractLine);
            
            if ($sStartPage != 0 && $sEndPage != 0) {
                $nPages = ($sEndPage-$sStartPage)+1;
                $sPageLine = 'Length: ' . $nPages . ' pages' . "\n";
                fwrite($fh, $sPageLine);
            }
            
            $sDateLine = 'Creation-Date: ' . $sDateIssued . "\n";
            fwrite($fh, $sDateLine);
            
			if (!empty($aBitstreams)) {
				//link to pdf
				$sPdfLine = 'File-URL: ';
				$sPdfLine .= $sBaseUrl . '/bitstream/handle';
				$sPdfLine .= $sUri . '/' . $aBitstreams[0]['name'] . "\n";
				fwrite($fh, $sPdfLine);
            
				
				$sFormatLine = 'File-Format: ';
				$sFormatLine .= $aBitstreams[0]['format'] . "\n";
				fwrite($fh, $sFormatLine);
			}
            
            $sNumberLine = 'Number: ';
            $sNumberLine .= substr($sFileName, 0, 2);
            $sNumberLine .= '-';
            $sNumberLine .= substr($sFileName,2, 2);
            $sNumberLine .= "\n";
            fwrite($fh, $sNumberLine);
            
            if (!empty($aKeywords)) {
                $sKeywordLine = 'Keywords: ';
                $sLine = '';
                foreach ($aKeywords as $sKeyword) {
                    $sLine .= $sKeyword . ', ';
                }
                $sKeywordLine .= substr($sLine,0, -2) . "\n";
                fwrite($fh, $sKeywordLine);
            }
            
            $sHandleLine = 'Handle: RePEc:use:tkiwps:' . $sFileName . "\n";
            fwrite($fh, $sHandleLine);
            
            fclose($fh);
        }
    }
}





//========================

function parseAuthors($aAuthorFields)
{
    $aAuthor = array();
    
	foreach ($aAuthorFields as $sName) {
		$aParts = explode(',', $sName);
        $sLastName = $aParts[0];
        $sFirstName = $aParts[1];
        $aAuthor[] = array(
            'authorname' => $sFirstName . ' ' . $sLastName,
            'authorlastname' => $sLastName,
            'authorfirstname' => $sFirstName,
        );
	}
    
    return $aAuthor;
}

function getFileNameFromBitstream($aBitstreams)
{
    $sFileName = '';
    foreach ($aBitstreams as $aOneBit) {
        if (preg_match('/\d{2}-\d{2}/', $aOneBit['name'])) {
            $sDash = strpos($aOneBit['name'], '-');
            $sFirst = substr($aOneBit['name'], 0, $sDash);
            $sSecond = substr($aOneBit['name'], $sDash+1);
            $sFileName = sprintf("%02d", $sFirst) . sprintf("%02d", $sSecond);
             return $sFileName;
        }
    }
    
    return $sFileName;
}

/*
function sendFileNameToService($sExportFile)
{
    $sServiceUrl = INDEX_QUEUE;
    $data_array = array('filename'=>$sExportFile);
    $data = http_build_query($data_array);
    $params = array('http' => array('method' => 'POST', 'content' => $data, 'header'  => 'Content-type: application/x-www-form-urlencoded'));
    $ctx = stream_context_create($params);

    try {
        $fp = fopen($sServiceUrl, 'rb', false, $ctx);
        $sResult = stream_get_contents($fp);
        $sLine = $sExportFile . ' posted to webservice';
        wlog($sLine, 'INF');
    }
    catch (Exception $e) {
        $sLine = 'Post to webservice went wrong: ' . $e->getMessage();
        wlog($sLine, 'INF');
        $sResult = $e->getMessage();
    }
    
    return $sResult;
}
 * 
 */
?>
