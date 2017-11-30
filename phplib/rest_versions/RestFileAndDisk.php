<?php
/**
 * Functions having to do with files and disk space
 *
 * @author muilw101
 */
class RestFileAndDisk {
    
    /**
     * Get all files in the source directory
     * 
     * @param string $sSourceDirectory 
     * @return array with name, file size, checksum and mimetype
     */
    public function getItemFiles($sDirectory)
    {
        $aItemFiles = array();
         
        $dh = opendir($sDirectory);
        
        while (($infile = readdir($dh)) !== false) {
            if ($infile != '.' && $infile != '..') {
                //skip htm, since we don't upload those to dspace
                if (substr($infile, -3) != 'htm') {
                    //get details on each file
                    $sFile = $sDirectory . $infile;
                    
                    $aFileData = $this->getFileData($sFile);
                    $aItemFiles[] = array(
                        'name' => $infile,
                        'filesize' => $aFileData['filesize'],
                        'checksum' => $aFileData['checksum'],
                        'mimetype' => $aFileData['mimetype'],
                    );
                 
                }
            }
        }
        closedir($dh);

        
        return $aItemFiles;
    }
    
    /**
     * Get the total size of all files in the given directory
     * 
     * @param string $sDirectory
     * @return array with total size or error message
     */
    public function getTotalFileSize($sDirectory)
    {
        $aDebug = array();
        
        $nTotalSize = 0;
        
        try {
            $dh = opendir($sDirectory);
        
            while (($infile = readdir($dh)) !== false) {
                if ($infile != '.' && $infile != '..') {
                    //skip htm, since we don't upload those to dspace
                    if (substr($infile, -3) != 'htm') {
                        //get details on each file
                        $sFile = $sDirectory . '/' . $infile;
                        $aFileData = $this->getFileData($sFile);
                    
                        $nFileSize = $aFileData['filesize'];
                    
                        $nTotalSize += $nFileSize;
                    }
                }
            }
            closedir($dh);
            $aDebug['totalsize']  = $nTotalSize;
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not get total size of directory: ' . $e->getMessage();
        }
        return $aDebug;
    }
    
    /**
     * Get the amount of free diskspace for a given directory
     * 
     * @param string $sAssetStore
     * @return array
     */
    public function getFreeDiskSpace($sAssetStore)
    {
        $nBytesFree = $this->fetchFreeDiskSpace($sAssetStore);
        $sParsedData = $this->parseFreeDiskSpace($nBytesFree);
        
        return $sParsedData;
    }
    
    /**
     * Remove from the given directory all items that are more than the
     * given number of days old
     * 
     * @param string $sDirectory
     * @param int $nDaysDifference
     * @param string $sMode
     * @return array
     */
    public function cleanDirectory($sDirectory, $nDaysDifference, $sMode)
    {
        $aDebug = array();
        
        //check date of directory
        $nDirDate = filemtime($sDirectory);
        
        //date to compare with
        $nPast = mktime(0, 0, 0, date("m"), date("d")-$nDaysDifference);
        
        if ($nDirDate < $nPast) {
            
            $aEmpty = $this->emptyDirectory($sDirectory, $sMode);
            if (isset($aEmpty['error'])) {
                $aDebug['error'] = $aEmpty['error'];
            }
            else {
                $aDebug['dirempty'] = $aEmpty;
                if ($sMode == 'test') {
                    $aDebug['result'] = 'I would remove directory' . $sDirectory;
                }
                else {
                    $aDebug['result'] = rmdir($sDirectory);
                }
            }
            $aDebug['dir']  = $sDirectory;
        }
        else {
            $aDebug['result'] = 'too recent';
        }
        
        return $aDebug;
        
    }
    
    /**
	 * Remove from the given directory all items whose name matches the
	 * given patterns and that are more than the given number of days old
	 * 
	 * @param string $sDirectory
	 * @param string $sPattern
	 * @param int $nDaysDifference
	 * @param string $sMode
	 */
	public function cleanOlderFiles($sDirectory, $sPattern, $nDaysDifference, $sMode = 'test')
	{
		$aDebug = array();
		
		//date to compare with
        $nPast = mktime(0, 0, 0, date("m"), date("d")-$nDaysDifference);
		//$aDebug['past'] = $nPast;
      
		if (is_dir($sDirectory)) {
			$dh = opendir($sDirectory);
			while (($infile = readdir($dh)) !== false) {
                if (preg_match($sPattern, $infile)) {
					$sPath = $sDirectory . $infile;
					//date of the file
					$nFileTime = filemtime($sPath);
					//$aDebug[$infile] = $nFileTime;
					if ($nFileTime < $nPast) {
						if ($sMode == 'do') {
							unlink($sPath);
						}
						else {
							$aDebug[] = $sPath;
						}
					}
				}
			}
		}
		return $aDebug;
	}
	
    /**
     * Get file size and md5 checksum for the given file
     * 
     * @param string $sFile 
     * @return array with filesize, checksum, mimetype
     */
    private function getFileData($sFile)
    {
        $aFileData = array();
        
        $aFileData['filesize'] = filesize($sFile);
        $aFileData['checksum'] = md5_file($sFile);
        //$aFileData['mimetype'] = finfo_file($sFile, FILEINFO_MIME_TYPE);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $aFileData['mimetype'] = $finfo->file($sFile);
        
        return $aFileData;
    }

    /**
     * Get the amount of free disk space for the given directory
     * 
     * @param string $sAssetStore
     * @return int
     */
    private function fetchFreeDiskSpace($sAssetStore)
    {
        $nBytesFree = disk_free_space($sAssetStore);
        
        return $nBytesFree;
    }
    
    /**
     * Turn the given number of bytes into a human readable form
     * Found on http://php.net/manual/en/function.disk-free-space.php
     * 
     * @param int $nBytes
     * @return string
     */
    private function parseFreeDiskSpace($nBytes)
    {
        $aTypes = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        
        for ($i = 0; $nBytes >= 1024 && $i < ( count($aTypes ) -1 ); $nBytes /= 1024, $i++ );
        $sSize = (round($nBytes, 2) . " " . $aTypes[$i]);
        
        return $sSize;
    }
     
    
    
    /**
     * Recursively remove everything from the directory
     * 
     * @param string $sDirectory
     * @param string $sMode
     * @return array
     */
    private function emptyDirectory($sDirectory, $sMode)
    {
        $aDebug = array();
        
        try {
            if ($dh = @opendir($sDirectory)) {
                //$dh = pendir($sDirectory);
            
                while (($infile = readdir($dh)) !== false) {
                    if ($infile != '.' && $infile != '..') {
                        $sInPath = $sDirectory . '/' . $infile;
                        if (is_dir($sInPath)) {
                            $this->cleanDirectory($sInPath, 2, $sMode);
                        }
                        else {
                            if ($sMode == 'test') {
                                $aDebug['files'][] = $sInPath;
                            }   
                            else {
                                unlink($sInPath);
                            }
                        }
                    }
                }
                closedir($dh);
                $aDebug['success'] = 'success';
            }
            else {
                $aDebug['error'] = 'could not open ' . $sDirectory;
            }
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not empty directory ' . $sDirectory . ': ' . $e->getMessage();
        }
        
        return $aDebug;
    }
    
}

?>
