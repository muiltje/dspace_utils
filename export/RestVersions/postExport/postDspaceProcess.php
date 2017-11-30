<?php

/*
 * This script reads the digobject export file to find the latest digital special collections items.
 * the export file is called digobjects.[8digitdate].xml.
 * So the dump of November 25 2013 is digobjects.20131125.xml.
 * 
 * Unfortunately, that doesn't work if we are processing BNB batches
 * so during that time we run this script the morning after
 */
require_once '/home/dspace/utils/newexport/init.php';

$bDebug = 0;
$sShortOpts = "d";
$aOptions = getopt($sShortOpts);
if (isset($aOptions['d'])) {
	$bDebug = 1;
}

wlog("\n" . '======= Starting Post DSpace Process ==========', '');

/**
 * If we start at 23:00, we can use the export made at 19:30;
 * and the derivatives should be ready by then
 */
$sToday = date("Ymd");
$sYesterday = date("Ymd", strtotime("1 day ago"));
$sExportDir = EXPORTBASE;
$sExportFileName = 'digobjects.' . $sYesterday . '.xml';
//$sExportFile = $sExportDir . $sExportFileName;
//use if you need to process an older file
$sManualFileName = 'resttest_digobjects.20170407.xml';
$sExportFile = $sExportDir . $sManualFileName;

//echo 'doing ' . $sExportFile . "\n";

$sReaderUrl = READER_URL;

//echo 'checking exportfile ' . $sExportFile . "\n";
$sLine = 'checking exportfile ' . $sExportFile;
wlog($sLine, 'INF');

$sWebserviceUrl = 'http://uws4.library.uu.nl/digworkflow/workflowservice/';

//array for items that have a new status
//only these items will be mentioned in the email
$aItemsForMail = array();


//open file
if (file_exists($sExportFile)) {
	$aNewItems = array();

	$sReadFile = file_get_contents($sExportFile);
	$xml = new SimpleXMLElement($sReadFile);
	if (is_object($xml)) {
		$aNewItems = getItems($xml);
	} else {
		echo 'cannot read';
	}

	$sCountLine = 'found ' . count($aNewItems) . ' items in the export file';
//	echo $sCountLine . "\n";
	wlog($sCountLine, 'INF');

	/**
	 * Separate loop for debugging and testing.
	 * We will often do this using older items, which have already been published
	 * and had their status set. So we skip all those checks.
	 * 
	 */
	if ($bDebug === 1) {
		if (count($aNewItems) > 0) {
			foreach ($aNewItems as $itemnumber => $aOneItem) {
				$nCollectionId = $aOneItem['collection'];
				$sCollectionGroup = 'Overig';
				if ($nCollectionId == 259) {
					$sCollectionGroup = 'Kaarten';
				}
				$aItemsForMail[$sCollectionGroup][$itemnumber] = array(
					'alephsys' => $aOneItem['aleph'],
					'url' => $aOneItem['fulluri'],
					'handle' => $aOneItem['handle'],
					'collection' => $aOneItem['collection'],
				);
			}
		}
	} 
	else {
		if (count($aNewItems) > 0) {
			$nNewCounter = 0;
			foreach ($aNewItems as $itemnumber => $aOneItem) {
				//print_r($aOneItem);
				//first check old status; if that is 'done' there is no need to do anything else
				//we also don't have to do anything if processtype == b (batch imports like BNB)
				//this only works for items with an itemnumber above 10000
				//also check if itemnumber is exactly 6 digits
				if (strlen($itemnumber) == 6 && intval($itemnumber) > 10000) {
					$aProcessData = getStatus($itemnumber, $sWebserviceUrl);
					$currentstatus = $aProcessData['status'];
					$processtype = $aProcessData['processtype'];
					if ($currentstatus == 'klaar') {
						//nothing to do
						wlog($itemnumber . ' klaar', 'INF');
					} elseif ($processtype == 'b') {
						//nothing to do
						wlog($itemnumber . ' batch', 'INF');
					} else {
						//check if the item has been published on the website
						wlog($itemnumber . ' standaard', 'INF');
						if ($aOneItem['aleph'] == 'xxx') {
							//invalid or missing aleph number
							//maybe mail somebody?
						} else {
							//check if the item has been published
							$sSiteUrl = $sReaderUrl . str_replace('/', '-', $aOneItem['handle']);
							$sWebsiteText = getWebsite($sSiteUrl);
							$sCheck = checkPageNums($sWebsiteText);
							if ($sCheck == 'n') {
								wlog($itemnumber . ' not published', 'INF');
							}
							else {
								wlog($itemnumber . ' nieuw', 'INF');
								$sSendResult = sendStatus($itemnumber, $sWebserviceUrl);
								wlog($sSendResult, 'INF');
								$nCollectionId = $aOneItem['collection'];
								$sCollectionGroup = 'Overig';
								if ($nCollectionId == 259) {
									$sCollectionGroup = 'Kaarten';
								}
								//$aItemsForMail[$itemnumber] = $aOneItem['aleph'];
								$aItemsForMail[$sCollectionGroup][$itemnumber] = array(
									'alephsys' => $aOneItem['aleph'],
									'url' => $aOneItem['fulluri'],
									'handle' => $aOneItem['handle'],
									'collection' => $aOneItem['collection'],
								);
								$nNewCounter++;
							}
						}
					}
				}
			}
			$sLine = 'I see ' . $nNewCounter . ' items to mail about ' . "\n";
			wlog($sLine, 'INF');
		}
	}
} else {
	$sLine = $sExportFile . ' does not exist';
	//echo $sLine . "\n";
	wlog($sLine, 'INF');
}

//echo count($aItemsForMail) . "\n";
//print_r($aItemsForMail);

//send email if there are any items to be mailed about
if (count($aItemsForMail) > 0) {
	sendCataloguerEmails($aItemsForMail);
	sendScannerEmail($aItemsForMail);
}

/**
 * Get all items that match the given repositories
 * For now that's only Digital Special Collections
 * @param string $xml
 * @param array $wantedrepositories
 * @return array
 */
function getItems($xml) {
	$aItems = array();


	$records = $xml->xpath('dspace_record');
	foreach ($records as $record) {
		$itemfield = $record->xpath('dc.identifier.digitization');
		if (count($itemfield) > 0) {
			$itemnumber = (string) $itemfield[0];

			$alephnumber = '';

			//get Alephnumber
			//this can be in source.alephid or in relation.ispartofalephid
			$sourcealephfield = $record->xpath('dc.source.alephid');
			$relationalephfield = $record->xpath('dc.relation.ispartofalephid');
			if (count($sourcealephfield) > 0) {
				$alephnumber = (string) $sourcealephfield[0];
			} elseif (count($relationalephfield) > 0) {
				$alephnumber = (string) $relationalephfield[0];
			} else {
				$alephnumber = 'xxx';
			}

			$urifield = $record->xpath('dc.identifier.uri');
			$sFullUri = (string) $urifield[0];
			$handle = substr($sFullUri, 22);

			//get collection; items are processed by different cataloguers
			//based on their collection
			$collectionfield = $record->xpath('dspace_collection/@id');
			$sCollection =  (string) $collectionfield[0];
			//$sCollection = $collectionfield[0]->attributes['id'];
			//$sCollection = (string) $record->dspace_collection->id;

			$aItems[$itemnumber] = array(
				'aleph' => $alephnumber,
				'handle' => $handle,
				'fulluri' => $sFullUri,
				'collection' => $sCollection,
			);
		}
	}

	return $aItems;
}

function getStatus($itemnumber, $webserviceurl) {
	$aResult = array();

	$url = $webserviceurl . 'status/' . $itemnumber;
	try {
		$fetchresult = file_get_contents($url);
		$resultfixed = str_replace(array('&lt;', '&gt;'), array('<', '>'), $fetchresult);
		$xml = new SimpleXMLElement($resultfixed);
		$statusfield = $xml->xpath('//currentstatus');
		$status = (string) $statusfield[0];
		$processtypefield = $xml->xpath(('//processtype'));
		$processtype = (string) $processtypefield[0];

		$aResult['status'] = $status;
		$aResult['processtype'] = $processtype;
	} catch (Exception $e) {
		$aResult['error'] = 'get went wrong: ' . $e->getMessage();
	}

	return $aResult;
}

function getWebsite($sUrl) {
	$aParams = array('http' => array('method' => 'GET'));
	$oCtx = stream_context_create($aParams);

	try {
		$fp = fopen($sUrl, 'rb', false, $oCtx);
		$sResult = stream_get_contents($fp);
	} catch (Exception $e) {
		$sResult = 'something went wrong: ' . $e->getMessage();
	}

	return $sResult;
}

function checkPageNums($sText) {
	$sPattern = 'http666';

	$sProperPages = 'y';

	if (strpos($sText, $sPattern)) {
		$sProperPages = 'n';
	}

	return $sProperPages;
}

/**
 *
 * @param string $itemnumber
 * @param string $webserviceurl
 * @return string $result
 */
function sendStatus($itemnumber, $webserviceurl) {
	$url = $webserviceurl . 'finished';

	$data_array = array('itemnumber' => $itemnumber);
	$data = http_build_query($data_array);
	$params = array('http' => array('method' => 'PUT', 'content' => $data, 'header' => 'Content-type: application/x-www-form-urlencoded'));
	$ctx = stream_context_create($params);

	try {
		$fp = fopen($url, 'rb', false, $ctx);
		$result = stream_get_contents($fp);
	} catch (Exception $e) {
		$result = 'put went wrong: ' . $e->getMessage();
	}

	return $result;
}


function sendCataloguerEmails($aMailItems) {
	$mailtext = '';
	$sMapResult = '';
	$sMainResult = '';

	if (count($aMailItems) == 1) {
		$mailtext .= 'Er is een nieuw item waarvoor een link in Aleph gemaakt moet worden: ';
	} else {
		$mailtext .= 'Er zijn nieuwe items waarvoor een link in Aleph gemaakt moet worden: ';
	}
	$mailtext .= "\n";

	$aNumbersSeen = array();
	$sFrom = 'm.muilwijk@uu.nl';
	
	if (count($aMailItems['Kaarten']) > 0) {
		$sMapMailText = $mailtext;
		foreach ($aMailItems['Kaarten'] as $aOneItem) {
			$alephnumber = $aOneItem['alephsys'];
			if ($alephnumber != 'xxx' && (!(in_array($alephnumber, $aNumbersSeen)))) {
				$sMapMailText .= $alephnumber . "\n";
				$aNumbersSeen[] = $alephnumber;
			}
		}
		$sMapTo = 'm.muilwijk@uu.nl';
		//$sMapTo = 'p.pestman@uu.nl';
		$sMapSubject = 'Nieuwe link voor kaarten';
		//$sMapCC = 'F.H.deGoojer@uu.nl, a.j.deman@uu.nl, m.muilwijk@uu.nl';
		$sMapHeaders = 'To:' . $sMapTo . "\r\n";
		$sMapHeaders .= 'From: ' . $sFrom. "\r\n";
		//$sMapHeaders .= 'CC: ' . $sMapCC . "\r\n";
		$sMapResult = mail($sMapTo, $sMapSubject, $sMapMailText, $sMapHeaders);
	}

	if (count($aMailItems['Overig']) > 0) {
		$sMainMailText = $mailtext;
		foreach ($aMailItems['Overig'] as $aOneItem) {
			$alephnumber = $aOneItem['alephsys'];
			if ($alephnumber != 'xxx' && (!(in_array($alephnumber, $aNumbersSeen)))) {
				$sMainMailText .= $alephnumber . "\n";
				$aNumbersSeen[] = $alephnumber;
			}
		}
		$sMainTo = 'm.muilwijk@uu.nl';
		//$sMainTo = 'F.H.deGoojer@uu.nl';
		$sMainSubject = 'Nieuwe link voor Aleph';
		//$sMainCC = 'p.pestman@uu.nl, a.j.deman@uu.nl, m.muilwijk@uu.nl';
		$sMainHeaders = 'To:' . $sMainTo . "\r\n";
		$sMainHeaders .= 'From: ' . $sFrom. "\r\n";
		//$sMainHeaders .= 'CC: ' . $sMainCC . "\r\n";
		$sMainResult = mail($sMainTo, $sMainSubject, $sMainMailText, $sMainHeaders);
	}
	
	$aResult = array($sMapResult, $sMainResult);
	
	return $aResult;
}

function sendScannerEmail($aMailItems) {
	$mailtext = '';

	if (count($aMailItems) == 1) {
		$mailtext .= 'Er is een nieuw item op de website gezet: ';
	} else {
		$mailtext .= 'Er zijn nieuwe items op de website gezet: ';
	}

	foreach ($aMailItems as $aItems) {
		foreach ($aItems as $aOneItem) {
			$sHandle = $aOneItem['handle'];
			$mailtext .= $sHandle . "\n";
		}
	}

	$from = 'm.muilwijk@uu.nl';
	//$to = 'c.vanderstappen@uu.nl, m.muilwijk@uu.nl';
	$to = 'm.muilwijk@uu.nl';
	$subject = 'Nieuwe items in DSpace';
	$message = $mailtext;

	$headers = 'To:' . $to . "\r\n";
	$headers .= 'From: ' . $from . "\r\n";

	$result = mail($to, $subject, $message, $headers);

	return $result;
}
