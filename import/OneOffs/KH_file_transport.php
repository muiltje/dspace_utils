<?php

require_once '../init.php';
require_once 'KH_FilesToDo.php';

$oBitstream = new Bitstream();
$aHandlesDone = array();

$nStartMoment = microtime(true);

$sFTPServer = 'utrecht-ftp.iiifhosting.com';
$sFTPUser = 's21910';
$sFTPPwd = 'ULw5F4jW';

$sConnectionId = ftp_connect($sFTPServer);
if ($sConnectionId) {
	$sLogin = ftp_login($sConnectionId, $sFTPUser, $sFTPPwd);
	if ($sLogin) {
		ftp_pasv($sConnectionId, true);
		echo 'Yes, we are logged in ' . "\n";


		$sCounterFile = 'KH_Counter.txt';
		$nStart = trim(file_get_contents($sCounterFile));
		$nNumber = 50;
		$nEnd = $nStart+$nNumber;
		//for ($i=$nStart; $i<$nEnd;$i++) {
		//foreach ($aProblemFiles as $aItem) {
		foreach ($aLastAdditional as $aItem) {	
			//$aItem = $aFilesToDo[$i];
			$sFileName = trim($aItem['filename']);
			$sHandle = $aItem['handle'];
			//echo 'file ' . $sFileName . ' for item ' . $aItem['handle'] . "\n";
	
			$aBitstreamsFound = $oBitstream->getAllBitstreamsOfThatName($sFileName);
			$aBitstream = $aBitstreamsFound[0];
	
			//find the path to this bitstream
			$sInternalId = $aBitstream['internal_id'];
			$sStoreNumber = $aBitstream['store_number'];
			$sFilePath = $oBitstream->getBitstreamPath($sInternalId, $sStoreNumber);
			//echo 'I would upload ' . $sFilePath . ' to the FTP' . "\n";
			$sUpload = ftp_put($sConnectionId, $sFileName, $sFilePath, FTP_BINARY);
			if (!$sUpload) {
				$sErrorLine = 'FTP put failed for ' . $sFileName;
				echo $sErrorLine . "\n";
				wlog($sErrorLine, 'ERR');
			}
			else {
				$sLine = 'Uploaded ' . $sFileName . ' for handle ' . $sHandle . ' to FTP';
				//echo $sLine . "\n";
				wlog($sLine, 'INF');
				
				//now find the thumbnail jpg
				//this will be in /manifestation/thumbs with the same subdirs as the tif
				$aPathParts = explode('/', $sFilePath);
				$sDirOne = $aPathParts[3];
				$sDirTwo = $aPathParts[4];
				$sDirThree = $aPathParts[5];
				$sThumbNailPath = '/manifestation/reader/' . $sDirOne . '/' . $sDirTwo
					. '/' . $sDirThree . '/' . $sInternalId . '.jpg';
	
				$sTrimmedFileName = substr($sFileName, 0, -4);
				$sThumbDestinationDir = '/dspace_queue/temp/' . $sHandle;
				if (!is_dir($sThumbDestinationDir)) {
					mkdir($sThumbDestinationDir);
				}
				$sThumbDestPath = $sThumbDestinationDir . '/' . $sTrimmedFileName . '.jpg';
				//echo 'I would copy ' . $sThumbNailPath . ' to ' . $sThumbDestPath . "\n";
				copy($sThumbNailPath, $sThumbDestPath);
				
				$aHandlesDone[] = $sHandle;
			}
		}
		$fh = fopen($sCounterFile, "w");
		fwrite($fh, $nEnd);
		fclose($fh);

		mailResult($aHandlesDone);
	
		ftp_close($sConnectionId);
	}
	else {
		//echo 'No log in' . "\n";
		wlog('No FTP login', 'ERR');
		exit();
	}
	
}
else {
	//echo 'No FTP connection ' . "\n";
	wlog('No FTP connection', 'ERR');
	exit();
}




$nEndMoment = microtime(true);
$nTimeSpent = $nEndMoment-$nStartMoment;
echo 'spent ' . $nTimeSpent . "\n";







function mailResult($aHandlesDone)
{
	$sMailText = 'Voor onderstaande handles zijn de TIFs geupload: ' . "\n";
	foreach ($aHandlesDone as $sHandle) {
		$sMailText .= $sHandle . "\n";
	}
	
	$from = 'm.muilwijk@uu.nl';
	$to = 'm.muilwijk@uu.nl';
	$subject = 'TIFs geupload naar Georeferencer';
	$message = $sMailText;

	$headers = 'To:' . $to . "\r\n";
	$headers .= 'From: ' . $from . "\r\n";

	mail($to, $subject, $message, $headers);
	
	return 1;
}



/*
ini_set("auto_detect_line_endings", true);

$sBaseFile = 'Zet_NL_opdekaart_september2017_definitief.csv';

$counter = 0;
$fh = fopen($sBaseFile, "r");
while (($buffer = fgets($fh)) !== false) {
	$aData = explode(';', $buffer);

	$sHandle = $aData[0];
	$sFileName = $aData[1];
	$aFilesToDo[$counter] = array('handle' => $sHandle, 'filename' => $sFileName);
	
	$counter++;
}

$sOutputFile = 'KH_FilesToDo.php';
$sOutput = var_export($aFilesToDo, true);
file_put_contents($sOutputFile, $sOutput);
 * 
 */



