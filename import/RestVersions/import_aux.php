<?php
/**
 * Various functions that are use by all import scripts
 */

function makeSafeString($sValue)
{
    $aControlChars = array(
        "\x01" => ' ',
        "\x02" => ' ',
        "\x03" => ' ',
        "\x04" => ' ',
        "\x05" => ' ',
        "\x06" => ' ',
        "\x07" => ' ',
        "\x0B" => ' ',
        "\x0C" => ' ',
        "\x0E" => ' ',
        "\x14" => ' ',
        "\x19" => ' ',
    );
    
    $bUseValue = '';
    $bCheckUtf8 = mb_check_encoding($sValue);
    if ($bCheckUtf8) {
        $sUseValue = $sValue;
    }   
    else {
        $oEncoding = new FixEncoding();
        $sFixedString = $oEncoding->toUTF8($sValue);
        $sUseValue = $oEncoding->fixUTF8($sFixedString);            
    }
    
    $sControlledString = strtr($sUseValue, $aControlChars);
    $sAmpValue = preg_replace('/&/', '&amp;', $sControlledString);
    $sLessValue = preg_replace('/</', '&lt;', $sAmpValue);
    $sMoreValue = preg_replace('/>/', '&gt;', $sLessValue);
    
    return $sMoreValue;
 }

 

 

/**
 *
 * @param type $aFiles
 * @param type $sDateTime
 * @param type $sType
 * @param type $sSubmitter
 * @return string 
 */
function makeFileDescriptions($aFiles, $sDateTime, $sType, $sSubmitter)
{
        
    $sLine = '<dcvalue element="description" qualifier="provenance">';
    
    if ($sType == 'submit') {
        $sLine .= 'Submitted by ' . $sSubmitter . ' on ' . $sDateTime . "\n";
    }
    elseif ($sType == 'approve') {
        $sLine .= 'Approved for entry into archive by ' . $sSubmitter . ' on ' . $sDateTime . ' ';
    }
    elseif ($sType == 'avail') {
        $sLine .= 'Made available in DSpace on ' . $sDateTime . ' (GMT) ';
    }
    
    $nCount = 0;
    $aFilesToShow = array();
    $aSkipFiles = array('.', 'contents', '/', 'dublin_core.xml');
    foreach ($aFiles as $aFile) {
        if (!in_array($aFile['name'], $aSkipFiles)) {
            $aFilesToShow[] = $aFile;
            $nCount++;
        }
    }
    
    //$nCount = count($aFiles);
    $sLine .= 'No. of bitstreams: ' . $nCount;
    foreach ($aFilesToShow as $aOneFile) {
        $sLine .= "\n";
        $sLine .= makeSafeString($aOneFile['name']) . ': ' . $aOneFile['filesize'] . ' bytes, ';
        $sLine .= 'checksum: ' . $aOneFile['checksum'] . ' (MD5)';
    }
    
    $sLine .= "</dcvalue> \n";
    
    return $sLine;
}


function makeFormatElements($aFiles)
{
    $sFileBlock = '';
    
    $aSkipFiles = array('.', 'contents', '/', 'license.txt', 'dublin_core.xml');
    
    //skip contents file and license.txt
    foreach ($aFiles as $aFile) {
        if (!in_array($aFile['name'], $aSkipFiles)) {
            $sFileBlock .= '<dcvalue element="format" qualifier="extent">' . $aFile['filesize'] . '</dcvalue>' . "\n";
        }
    }
    
    foreach ($aFiles as $aFile) {
        if (!in_array($aFile['name'], $aSkipFiles)) {
            $sFileBlock .= '<dcvalue element="format" qualifier="mimetype">' . $aFile['mimetype'] . '</dcvalue>' . "\n";
        }
    }
    
    return $sFileBlock;
}


function findFiles($sSourceDirectory)
{
    $aFiles = array();
    
    $aHtm = array();
    $aOther = array();
    
    try {
        $dh = @opendir($sSourceDirectory);
        while (($infile = @readdir($dh)) !== false) {
            if ($infile != '.' && $infile != '..' && $infile != '/') {
                 if (substr($infile, -3) === 'htm') {
                    $aHtm[] = $infile;
                }
                elseif (strlen($infile) > 1) {
                    $aOther[] = $infile;
                }
                else {
                    
                }
                
            }
        }
        closedir($dh);
        
        asort($aOther);
        if (!empty ($aHtm)) {
            asort($aHtm);
        }
        
        $aFiles['htms'] = $aHtm;
        $aFiles['ft'] = $aOther;
    }
    catch (Exception $e) {
        return 'error: ' . $e->getMessage();
    }
    
    return $aFiles;
}


function copySourceFiles($sSourceDirectory, $sImportDirectory, $aFileNames, $bDevelop)
{
    $aCopyResult = array();
    
    try {
        foreach ($aFileNames as $aFile) {
            $sSource = $sSourceDirectory . $aFile;
            $sDestination = $sImportDirectory . $aFile;
            
            if ($bDevelop == 1) {
                echo 'linking ' . $sDestination. ' to ' . $sSource . "\n";
            }
            else {
                @symlink($sSource, $sDestination);
            }
        }
    }
    catch (Exception $e) {
        $aCopyResult['error'] = 'could not copy source files to import directory ' . $e->getMessage();
    }
    
    return $aCopyResult;
}



function makeContentsBlock($aFiles)
{
    $sContentsBlock = '';
    
    //skip contents itself and dublin_core.xml, but this time do include license.txt
    $aContentSkipFiles = array('.', 'contents', '/', 'dublin_core.xml');
    
    foreach ($aFiles as $aFile) {
        $sFileName = $aFile['name'];
        if (!in_array($sFileName, $aContentSkipFiles)) {
            $sContentsBlock .= $sFileName . "\n";
        }
    }
    
    return $sContentsBlock;
}

function doImport($sTodaysImportDir, $nCollectionId, $sEPerson, $bDoImport)
{
    $aImportResult = array();
    $sMapFile = $sTodaysImportDir . 'dspace.' . $nCollectionId . '.map';
    $sCollectionDirectory = $sTodaysImportDir . $nCollectionId;
    
    $sCmd = '/home/dspace/dspace/bin/dspace import --add';
    $sCmd .= ' --eperson=' . $sEPerson;
    $sCmd .= ' --collection=' . $nCollectionId;
    $sCmd .= ' --source=' . $sCollectionDirectory;
    $sCmd .= ' --mapfile=' . $sMapFile;
    //for debug:
    if ($bDoImport == 0) {
        $sCmd .= ' --test';
    }
    
    
    try {
        $aImportResult['result'] = exec($sCmd);
        //$aImportResult['result'] = 'I would try ' . $sCmd . "\n";
        
    }
    catch (Exception $e) {
        $aImportResult['error'] = 'could not import items for collection ' . $nCollectionId . ': ' . $e->getMessage();
    }
    
    return $aImportResult;
}


function sendErrorMails($sAddress, $sSubject, $sMessage)
{
    $sFrom = NOREPLY;
    $sToAddress = $sAddress;
    $sHeaders = 'From:' . $sFrom . "\r\n";
    
    
    $result = mail($sToAddress, $sSubject, $sMessage, $sHeaders);
    
    return $result;
}

function cleanImport($sImportDirectory, $nDays, $sMode)
{
    $oFandD = new FileAndDisk();
    
    $aCleanImport = array();
    
    $dh = opendir($sImportDirectory);
    if ($dh) {
        while (($infile = readdir($dh)) !== false) {
            if ($infile != '.' && $infile != '..') {
                $sPath = $sImportDirectory . $infile;
                $aCleanImport['paths'][] = $sPath; 
                if (is_dir($sPath)) {
                    $sDirectoryToClean = $sImportDirectory . $infile;
                    $aCleanImport['res'][] = $oFandD->cleanDirectory($sDirectoryToClean, $nDays, $sMode);
                }
            }
        }
    }
    
    return $aCleanImport;
}

/*
$aMsLatinChar = array(
    128=> 8364,
    130 => 8218,
    131 => 402,
    132 => 8222,
    133 => 8230, 
    134 => 8224,
    135 => 8225,
    136 => 710, 
    137 => 8240, 
    138 => 352,
    139 => 8249, 
    140 => 338, 
    145 => 8216, 
    146 => 8217,
    147 => 8220,
    148 => 8221,
    149 => 8226,
    150 => 8211,
    151 => 8212,
    152 => 732,
    153 => 8482,
    154 => 353,
    155 => 8250,
    156 => 339,
    159 => 376,
);
 * 
 */

?>
