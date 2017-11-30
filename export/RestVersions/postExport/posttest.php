<?php

require_once '/home/dspace/utils/newexport/init.php';

$sReaderUrl = READER_URL;


//check if an item really is on the website

/*
$sGoodItem = '1874-237256'; //an item we know to be on the website
$sGoodTest = getWebsite($sReaderUrl . $sGoodItem);
$sGoodPages = checkPageNums($sGoodTest);
echo $sGoodItem . ' gives ' . $sGoodPages . "\n";

$sBadItem = '1874-288022'; //an item we know doesn't work
$sBadTest = getWebsite($sReaderUrl . $sBadItem);
$sBadPages = checkPageNums($sBadTest);
echo $sBadItem . ' gives ' . $sBadPages . "\n";
 * 
 */

$sExportDir = EXPORTBASE;
$sExportFileName = 'digobjects.20131218.xml';
$sExportFile = $sExportDir . $sExportFileName;
//$sWebserviceUrl = 'http://uws2.library.uu.nl/digworkflow/workflowservice/';


if (file_exists($sExportFile)) {
    $aNewItems = array();

    $sReadFile = file_get_contents($sExportFile);
    $xml = new SimpleXMLElement($sReadFile);
    if (is_object($xml)) {
        $aNewItems = getItems($xml);
        
        foreach ($aNewItems as $itemnumber => $aOneItem) {
            if (strlen($itemnumber) == 6 && intval($itemnumber) > 10000) {
                //$currentstatus = getStatus($itemnumber, $sWebserviceUrl);
                //if ($currentstatus == 'klaar') {
                    //nothing to do
                    //wlog($itemnumber . ' klaar', 'INF');
                //}
                //else {
                
                    if ($aOneItem['aleph'] == 'xxx') {
                        //invalid or missing aleph number
                    }
                    else {
                        //check if the item has been published
                        $sSiteUrl = $sReaderUrl . str_replace('/', '-', $aOneItem['handle']);
                        echo 'checking ' . $sSiteUrl . "\n";
                        $sWebsiteText = getWebsite($sSiteUrl);
                        $sCheck = checkPageNums($sWebsiteText);
                        if ($sCheck == 'n') {
                            //not published
                            //maybe mail administrator?
                            echo $aOneItem['handle'] . ' was not published' . "\n";
                        }
                        else {
                            //now we can set the status and send the mail
                            echo 'yes ' . $aOneItem['handle'] . " has been published \n";
                        }
                    }
 
                //}
            }
        }
    }
    else {
        echo 'cannot read';
    }
}

echo "\n";


function getItems($xml)
{
    $aItems = array();
   
    
    $records = $xml->xpath('dspace_record');
    foreach ($records as $record) {
        $itemfield = $record->xpath('DC.identifier.digitization');
        if (count($itemfield) > 0) {
            $itemnumber = (string) $itemfield[0];
                    
            $alephnumber = '';
            //get Alephnumber
            //this can be in source.alephid or in relation.ispartofalephid
            $sourcealephfield = $record->xpath('DC.source.alephid');
            $relationalephfield = $record->xpath('DC.relation.ispartofalephid');
            if (count($sourcealephfield) > 0) {
                $alephnumber = (string) $sourcealephfield[0];
            }
            elseif (count($relationalephfield) > 0) {
                $alephnumber = (string) $relationalephfield[0];
            }
            else {
                $alephnumber = 'xxx';
            }
            
            $urifield = $record->xpath('DC.identifier.uri');
            $sFullUri = (string) $urifield[0];
            $handle = substr($sFullUri, 22);
            
            $aItems[$itemnumber] = array(
                'aleph' => $alephnumber, 
                'handle' => $handle);
            
            //if ($alephnumber != 'xxx') {
            //  	$aItems[$itemnumber] = $alephnumber;
       	    //}
        }

    }
    
      return $aItems;
}

function getStatus($itemnumber, $webserviceurl)
{
   
    $url = $webserviceurl . 'status/' . $itemnumber;
    try {
        $fetchresult = file_get_contents($url);
        $resultfixed=str_replace( array('&lt;','&gt;') ,array('<','>'),$fetchresult);  
        $xml = new SimpleXMLElement($resultfixed);
        $statusfield = $xml->xpath('//currentstatus');
        $status = (string) $statusfield[0];
        $result = $status;
        return $result;
     }
     catch (Exception $e) {
        $result = 'get went wrong: ' . $e->getMessage();
        return $result;
     }

    return $result;
}


function getWebsite($sUrl)
{
    $aParams = array('http' => array('method' => 'GET'));
    $oCtx = stream_context_create($aParams);

    try {
	$fp = fopen($sUrl, 'rb', false, $oCtx);
	$sResult = stream_get_contents($fp);
     }
    catch (Exception $e) {
        $sResult = 'something went wrong: ' . $e->getMessage();
   }
    
    return $sResult;
}

function checkPageNums($sText)
{
    $sPattern = 'http666';
    
    $sProperPages = 'y';
    
    if (strpos($sText, $sPattern)) {
        $sProperPages = 'n';
    }
    
    return $sProperPages;
}

?>
