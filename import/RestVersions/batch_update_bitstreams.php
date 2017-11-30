<?php
/* 
 * For digitized items with more than 300 bitstreams, you can't delete or add
 * bitstreams with the regular GUI.
 * For those cases we use this script, and the "itemupdate" function 
 * https://wiki.duraspace.org/display/DSDOC3x/Updating+Items+via+Simple+Archive+Format
 * 
 * [dspace]/bin/dspace itemupdate  -e darch@uu --addbitstreams 
 * Adds bitstreams listed in the contents file with the bitstream metadata cited there.
 * 
 * [dspace]/bin/dspace itemupdate  -e darch@uu --deletebitstreams 
 * This operation deletes bitstreams listed in the deletes_contents file.
 * 
 * optional arguments
 * --itemfield Specifies the metadata field that contains the item's identifier; Default value is "dc.identifier.uri" (
 * Make sure that the directory name matches whatever is in that itemfield,
 * so the updater knows which item to look for
 */
require_once 'import_init.php';
require_once CLASSES . 'Bitstream.php';

$sUpdateDirectory = IMPORTBASE . 'bijzcoll_update/';
$sTifDirectory = '/dspace_queue/temp/';


//see if there is an "add_this.txt" file
$sToday = date('Ymd');
$sAddFile = '/tmp/add_this' . $sToday . '.txt';

$aItemsToUpdate = array();

if (file_exists($sAddFile)) {
    echo 'yes, there is one';
    echo "\n";
    
    $fh = fopen($sAddFile, "r");
    while (($sLine = fgets($fh)) !== false) {
        $aLineParts = explode('-', $sLine);
        $sHandle = $aLineParts[0];
        $sFileName = trim($aLineParts[1]);
        $sBookIdentifier = '';
        if ($aLineParts[2]) {
            $sBookIdentifier = trim($aLineParts[2]);
        }
        
        echo 'add ' . $sFileName . ' to ';
        if ($sBookIdentifier != '') {
            echo 'item identified with ' . $sBookIdentifier;
            $aItemsToUpdate[$sBookIdentifier]['add'][] = $sFileName;
            $aItemsToUpdate[$sBookIdentifier]['handle'] = $sHandle;
        }
        else {
            echo 'handle ' . $sHandle;
        }
        echo "\n";
    }
    fclose($fh);
}

//see if there is an "delete_this.txt" file
$sDeleteFile = '/tmp/delete_this' . $sToday . '.txt';
if (file_exists($sDeleteFile)) {
    echo 'there is something to delete';
    echo "\n";
    $fh = fopen($sDeleteFile, "r");
    while (($sLine = fgets($fh)) !== false) {
        $aLineParts = explode('-', $sLine);
        $sHandle = $aLineParts[0];
        $sFileName = trim($aLineParts[1]);
        $sBookIdentifier = '';
        if ($aLineParts[2]) {
            $sBookIdentifier = trim($aLineParts[2]);
        }
        
        echo 'remove ' . $sFileName . ' from ';
        if ($sBookIdentifier != '') {
            echo 'item identified with ' . $sBookIdentifier;
            $aItemsToUpdate[$sBookIdentifier]['delete'][] = $sFileName;
            $aItemsToUpdate[$sBookIdentifier]['handle'] = $sHandle;
        }
        else {
            echo 'handle ' . $sHandle;
        }
        echo "\n";
    }
    fclose($fh);    
    
}

//we need to create a contents file and a dublin_core file like we do in normal import
//We only need the identifier.uri element in the dublin_core
//plus the files to be added in contents
foreach ($aItemsToUpdate as $sBookIdentifier => $aData) {
    $sItemDirectory = $sUpdateDirectory . 'update_' . $sBookIdentifier;
    if (!opendir($sItemDirectory)) {
        mkdir($sItemDirectory);
    }
    
    $sHandle = $aData['handle'];
    $sUri = 'http://hdl.handle.net/' . $sHandle;
    
    //dublin_core is the same whether we add or delete
    $sDublinCoreFile = $sItemDirectory . '/dublin_core.xml';
    $fh = fopen($sDublinCoreFile, "w");
    fwrite($fh, "<dublin_core>\n");
                    
    $sIdLines = '<dcvalue element="identifier" qualifier="other">' . $sBookIdentifier . '</dcvalue>' . "\n";
    $sIdLines .= '<dcvalue element="identifier" qualifier="digitization">' . $sBookIdentifier . '</dcvalue>' . "\n";
    $sIdLines .= '<dcvalue element="identifier" qualifier="uri">' . $sUri . '</dcvalue>' . "\n";
    fwrite($fh, $sIdLines);
    fwrite($fh, "</dublin_core>\n");
    fclose($fh);
    
    if (!empty($aData['add'])) {
        $aFilesToAdd = $aData['add'];
        
         $sContentsFile = $sItemDirectory . '/contents';
        $sLine = '';
        foreach ($aFilesToAdd as $sFileName) {
            //find the file and copy it to the itemdirectory
            $sSource = $sTifDirectory . $sFileName;
            $sDestination = $sItemDirectory . '/' . $sFileName;
            
            if (file_exists($sSource)) {
                copy($sSource, $sDestination);
                $sLine .= $sFileName . "\n";
            }
        }
        $fh = fopen($sContentsFile, "a");
        fwrite($fh, $sLine);
        fclose($fh);
    }
    
    if (!empty($aData['delete'])) {
        $aFilesToDelete = $aData['delete'];
        
         
        $sDeleteContentsFile = $sItemDirectory . '/delete_contents';
        
        $sLine = '';
        foreach ($aFilesToDelete as $sFileName) {
            //$sLine .= $sFileName . "\n";
            //to delete bitstreams, we need their bitstreamids
            $nBitstreamId = findBitstreamId($sFileName);
            $sLine .= $nBitstreamId . "\n";
        }
        $fh = fopen($sDeleteContentsFile, "a");
        fwrite($fh, $sLine);
        fclose($fh);
    }
}

//print_r($aItemsToUpdate);


$sBaseCommand = '/home/dspace/dspace/bin/dspace itemupdate';
$sBijzCollUpdater = 'm.muilwijk@library.uu.nl';

$sCmd = $sBaseCommand . ' -e ' . $sBijzCollUpdater . ' -s ' . $sUpdateDirectory;

$sAddCmd = $sCmd . ' --addbitstreams';
$sDeleteCmd = $sCmd . ' --deletebitstreams';

echo $sAddCmd;
echo $sDeleteCmd;

/*
exec($sAddCmd);
exec($sDeleteCmd);

//when we're all done, clean up all the "undo" files and directories
cleanUndo();

//also remove the tifs that we added
cleanAddedTifs();
 * 
 */

//we can't delete the addfile and deletefile because they're owned by nobody
//never mind, they're in /tmp so will be cleaned up anyway
//the update directories that we created can be left alone
//we can always clean them up manually if they take up too much space


function findBitstreamId($sFileName)
{
    $oBitstream = new Bitstream();
    $aBitstreamData = $oBitstream->getBitstreamByName($sFileName);
    
    $nBitstreamId = $aBitstreamData['bitstream_id'];
    
    return $nBitstreamId;
}

//http://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
function cleanUndo()
{
    $sStartDirectory = IMPORTBASE;
    
    $dhs = opendir($sStartDirectory);
    while (($infile = readdir($dhs)) !== false) {
        if (substr($infile, 0, 4) == 'undo') {
            //echo $infile . "\n";
            if (is_dir($sStartDirectory . $infile)) {
                //echo 'isdir' . "\n";
                $dhn = opendir($sStartDirectory . $infile);
                while (($next = readdir($dhn)) !== false) {
                    if ($next != '.' && $next != '..') {
                        $sNextFile = $sStartDirectory . $infile . '/' . $next;
                        echo "\n" . $sNextFile;
                        unlink($sNextFile);
                    }
                } 
                closedir($dhn);
                rmdir($sStartDirectory . $infile);
            }
            else {
                $sFile = $sStartDirectory . $infile;
                unlink($sFile);
            }
        }
    }
    
    closedir($dhs);
}

/**
 * go to /dspace_queue/temp and remove all tifs at that level
 * ignore subdirectories, they are used for another purpose
 */
function cleanAddedTifs()
{
    $sDirectory = '/dspace_queue/temp/';
    $dh = opendir($sDirectory);
    while (($infile = readdir($dh)) !== false) {
        if (strpos($infile, 'tif')) {
            unlink($sDirectory . $infile);
        }
    }
    closedir($dh);
    
}