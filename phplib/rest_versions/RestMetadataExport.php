<?php
require_once 'DatabaseConnector.php';
require_once '/home/dspace/utils/phplib/Classes/FixEncoding.php';
require_once 'RestItem.php';

/**
 * Functions for the export of DSpace items (to XML files)
 *
 * @author muilw101
 */
class RestMetadataExport {
    
    private $sBaseDirectory = '';
    private $dbconn;
    
    function __construct() {
        $this->sBaseDirectory = EXPORTBASE;
        
        $oDC = new DatabaseConnector();
        $this->dbconn = $oDC->getConnection();
        
     }
    
    /**
     * Find all export definitions
     * 
     * @return array
     */
    public function findAllExportDefinitions()
    {
        $aExportDefs = array();
        
        $query = "SELECT * FROM ubuaux_exportdef ORDER BY exportdef_id";
        
        $result = pg_query($this->dbconn, $query);
    
        while ($row = pg_fetch_assoc($result)) {
            $aExportDefs[] = $row;
        }
        
        return $aExportDefs;
    }
    
    /**
     * Find the details of a given export definition
     * 
     * @param int $nExportDefinitionId
     * @return array
     */
    public function findExportDefinitionDetails($nExportDefinitionId)
    {
        $aExportDetails = array();
        
        $query = "SELECT * FROM ubuaux_exportdef
          WHERE exportdef_id=" . $nExportDefinitionId;
         
        $result = pg_query($this->dbconn, $query);
    
        while ($row = pg_fetch_assoc($result)) {
            $aExportDetails = $row;
        }
        
        return $aExportDetails;
    }
    
    /**
     * This function is used to set the fulldump value to false,
     * after a fulldump has been performed for this export definition
     * 
     * @param int $nExportDefId 
     * @return array 
     */
    public function updateExportDefinition($nExportDefId)
    {
        $aDebug = array();
        
        $sql = "update ubuaux_exportdef set fulldump='f' where exportdef_id=" . $nExportDefId;
        
        try {
            pg_query($this->dbconn, $sql);
            $aDebug['success'] = 'success';
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not update ubuaux_expordef: ' . $e->getMessage();
        }
        
        
        return $aDebug;
    }
    
    /**
     * Write the data of a set of items to a file
	 * @todo: new version with the REST versions of Item and ItemMetadata
     * 
     * @param array $aExportItems
     * @param int $bIsFullDump
     * @param int $bWithdrawnItems
     * @param string $sExportDirectory
     * @param string $sExportFileName
     * @return array
     */
    public function exportMetadata($aExportItems,$bIsFullDump, $bWithdrawnItems, $sExportDirectory, $sExportFileName)
    {
        $aDebug = array();
        
        $oItem = new Item();
		$oItemMetadata = new ItemMetadata();
		
        $sToday = date("Ymd");
        $sCurrentExportFileName = $sExportFileName;
        if ($bWithdrawnItems == 1) {
            $sCurrentExportFileName .= '.withdrawn';
        }
        $sCurrentExportFileName .= '.' . $sToday;
        $sCurrentExportFileName .= '.xml';
        
        $open = $this->newExportFile($sCurrentExportFileName, $bIsFullDump);
        if ($open == 'n') { //could not open a new file
            $aDebug['error'] = 'could not open ' . $sCurrentExportFileName;
            return $aDebug;
        }
        else {
            foreach ($aExportItems as $aOneItem) {
                $nItemId = $aOneItem['item_id'];
                $sHandle = $aOneItem['handle'];
                $sExportXML = '';
                
                if ($bWithdrawnItems == 1) {
                    $sExportXML = '<dspace_record id="' . $nItemId . '" handle="' . $sHandle . '" withdrawn="t" />' . "\n";
                }
                else {
                    $aMetadataFetch = $oItemMetadata->getAllMetadata($nItemId);
					if (!empty($aMetadataFetch)) {
						$sExportXML = '<dspace_record id="' . $nItemId . '" handle="' . $sHandle . '">' . "\n";
                
						$sMetadataXml = $this->makeMetadataExportXML($aMetadataFetch);
						$sExportXML .= $sMetadataXml;
					
						$aCollectionData = $oItem->getItemCollection($nItemId);
						$aParentCollection = $aCollectionData['parentCollectionList'][0];
						$aParentCommunity = $aCollectionData['parentCommunityList'][0];
						//$aDebug['collectiondata'] = $aCollectionData;
                        
						$sExportXML .= '<dspace_collection id="' . $aParentCollection['id'] . 
                            '" place="1">' . $aParentCollection['name'] . '</dspace_collection>' . "\n";
						$sExportXML .= '<dspace_community id="' . $aParentCommunity['id'] . 
                            '" place="1">' . $aParentCommunity['name'] . '</dspace_community>' . "\n";
						$sExportXML .= '</dspace_record>' . "\n";
					}
					else {
						$aDebug['error'] = 'no metadata found for ' . $nItemId;
					}

                } //end of "if this is the export for withdrawn items    
                
				if ($sExportXML != '') {
					$this->writeItemToFile($sExportXML, $sCurrentExportFileName, $sExportDirectory);
				}
            }
            $this->closeExportFile($sCurrentExportFileName);
        }
        
        return $aDebug;
    }
    
    /**
    * Open a new file for data export
     * and write the opening XML tags
     * 
    * @param string $sFileName 
     * @param int $nFullDump
     * 
     * @return string 
    */
    private function newExportFile($sFileName, $nFullDump)
    {
        //start the XML
        $sOpeningXML = "<dspace>\n";
        $sOpeningXML .= '<fulldump>' . $nFullDump . '</fulldump>' . "\n";
        
        //determine the path to the file
        $sExportFile = $this->sBaseDirectory . $sFileName;
        
        //open the file
        try {
            $fh = fopen($sExportFile, "w");
        
            //write to the file
            fwrite($fh, $sOpeningXML);
        
            //close the connection
            fclose($fh);
            $result = 'y';
        }
        catch (Exception $e) {
            $result = 'n: ' . $e->getMessage();
        }
        return $result;
    }
    
    /**
     * Write the XML for an item to the given file
     * 
     * @param string $sExportXML
     * @param string $sFileName
     * @param string $sExportDirectory
     * @return string  
     */
    private function writeItemToFile($sExportXML, $sFileName, $sExportDirectory)
    {
        
        $sExportFile = $this->sBaseDirectory . $sFileName;
        if ($sExportDirectory != '') {
            if (preg_match('/\/$/', $sExportDirectory)) {
               $sExportFile = $sExportDirectory . $sFileName;
            }
            else {
                $sExportFile = $sExportDirectory . '/' . $sFileName;
            }
        }
        
        try {
            $fh = fopen($sExportFile, "a");
            fwrite($fh, $sExportXML);
            fclose($fh);
            $result = 'y';
        }
        catch (Exception $e) {
            $result = 'n: ' . $e->getMessage();
        }
        
        return $result;
        
    }
    
    /**
     * Add the closing line to the export file
     * 
     * @param string $sFileName
     * @return string
     */
    private function closeExportFile($sFileName)
    {
        $sClosingXml = "</dspace>\n";
        
        //determine the path to the file
        $sExportFile = $this->sBaseDirectory . $sFileName;
        
        try {
            //open the file
            $fh = fopen($sExportFile, "a");
            fwrite($fh, $sClosingXml);
        
            fclose($fh);
            $result = 'y';
        }
        catch (Exception $e) {
            $result = 'n: ' . $e->getMessage();
        }
        
        return $result;
    }
    
	
	private function makeMetadataExportXML($aMetadata)
	{
		$aIrrelevantFields = array(
			'dc.description.provenance',
			'dc.format.extent',
			'dc.format.mimetype',
			'dc.description.abstractoriginal'
		);
		
		$sExportXml = '';
        $sDateDisseminated = '';
		$nAuthorCounter = 0;
		
		foreach ($aMetadata as $aOneMeta) {
			$sKey = $aOneMeta['key'];
			$sValue = $aOneMeta['value'];
		
			if (!in_array($sKey, $aIrrelevantFields)) {
				if ($sKey == 'dc.contributor.author') {
					$sLine = $this->processAuthorName($sValue, $nAuthorCounter);
					$sExportXml .= $sLine . "\n";
					$nAuthorCounter++;
				}
				else {
					$sLine = '<' . $sKey . ' place="0">';
					if ($sKey == 'dc.date.created') {
						$sDateDisseminated = $sValue;
						$sLine .= $sValue;
					}
					elseif ($sKey == 'dc.date.issued' && $sDateDisseminated == '') {
						$sDateDisseminated = $sValue;
						$sLine .= $sValue;
					}
					elseif ($sKey == 'dc.description.abstract' || $sKey == 'dc.title') {
						$textvalue_one = mb_convert_encoding($sValue, "UTF-8", "auto");
						$textvalue_two = $this->stripControlChars($textvalue_one);
						$sTextValue = $this->makeSafeString($textvalue_two);
						$sLine .= $sTextValue;
					}
					elseif ($sKey == 'dc.subject.courseuu') {
						//silly hack for courses with an & in their name
						//this is because the consuming site can't deal with ampersands
						$sTextValue = preg_replace('/&/', 'and', $sValue);
						$sLine .= $sTextValue;
					}
					else {
						$sTextValue = $this->makeSafeString($sValue);
						$sPattern = '/(&#x[A-F0-9]{4})/';
						$sReplacement = '${1};';
						$sUtfValue = preg_replace($sPattern, $sReplacement, $sTextValue);
						$sLine .= $sUtfValue;
					}
					$sLine.= '</' . $sKey . '>';
					$sExportXml .= $sLine . "\n";
				}
			}
		}
		$sExportXml.= '<DC.date.disseminated place="1">' . $sDateDisseminated . '</DC.date.disseminated>' . "\n";
		
		return $sExportXml;
	}
	
	
    
    /**
     * Get the name and place info for an author
     * 
     * @param array $aAuthorData
     * @return string
     */    
    private function processAuthorName($sName, $nAuthorCounter)
    {
        //metadatavalues for metadata_field_id=5:
        //we don't have a real DAI for the items we export, but the receiving
		//software expects one, so we just use the name
		
        $sPlace = $nAuthorCounter;
        $sDAI = preg_replace('/\"/', '', $sName);
                
        $sFamName = '';
        $sGivenName = '';
        $sSingleName = ''; //this can happen with Special Collections
        $sInitials = '';

        $comma = strpos($sName, ',');
        if ($comma) {
            $sFamName = substr($sName, 0, $comma);
            $sGivenName = substr($sName, $comma+2);
            //if there any quotes in GivenName, remove the quoted part for initials
            $sUnquotedGivenName = preg_replace('/".*"/', '', $sGivenName);
            $sInitials = preg_replace('/[ \.]/', '', $sUnquotedGivenName);
        }
        else {
            $sSingleName = $sName;
        }
 
        $sLine = '<DC.contributor.author ';
        $sLine .= 'dai="' . $this->makeSafeString($sDAI) . '" ';
        $sLine .= 'lastname="' . $this->makeSafeString($sFamName) . '" ';
        $sLine .= 'initials="qqq' . $this->makeSafeString($sInitials) . '" ';
        $sLine .= 'place="' . $sPlace . '">';
        if ($sSingleName != '') {
            $sLine .= $this->makeSafeString($sSingleName);
        }
        else {
            $sLine .= $this->makeSafeString($sFamName) . ', ' . $this->makeSafeString($sGivenName);
        }
        $sLine .= '</DC.contributor.author>';
        
        return $sLine;
    }

    
    /**
     * Fix utf-8 encoding and escape ampersand and angle brackets
     * @param type $sValue
     * @return type
     */
    private function makeSafeString($sValue)
    {
        $sProcessedString = $sValue;
        
        $bCheckUtf8 = mb_check_encoding($sValue);
        if ($bCheckUtf8) {
            $sProcessedString = $sValue;
        }
        else {
            $oEncoding = new FixEncoding();
            $sFixedString = $oEncoding->toUTF8($sValue);
            $sProcessedString = $oEncoding->fixUTF8($sFixedString);            
        }
        
        //only escape & if not followed by #
        //I would want to change that to: escape & if it's not 
        //followed by # and not part of a htmlentity
        $sUnAmpValue = html_entity_decode($sProcessedString, ENT_NOQUOTES | 'ENT_XML1', 'UTF-8'); //@todo: check if this leaves a valid entity
        $sAmpValue = preg_replace('/&([^#])/', '&amp;${1}', $sUnAmpValue);
        $sImprovedAmpValue = preg_replace('/&amp;mp/', '&amp;', $sAmpValue);
        $sLessValue = preg_replace('/</', '&lt;', $sImprovedAmpValue);
        $sMoreValue = preg_replace('/>/', '&gt;', $sLessValue);
        
        return $sMoreValue;
    }
    
    private function stripControlChars($sValue)
    {
        $aControlChars = array(
            "\x01",
            "\x02",
            "\x03",
            "\x04",
            "\x05",
            "\x06",
            "\x07",
            "\x14",
            "\x19",
            "\x0E",
         );
        
        //$aSemiControlChars = array("\x0C" => 'fi', "\x0B" => 'ff',);
        $sStrippedValue = str_replace($aControlChars, '', $sValue);
        $sFiValue = str_replace("\x0C", 'fi', $sStrippedValue);
        $sFfValue = str_replace("\x0B", 'ff', $sFiValue);
        $sImprovedValue = $sFfValue;
        
          
        return $sImprovedValue;
    }
    
    
}
