<?php
/**
 * A class to read and parse data from the digitization workflow webservice
 *
 * @author muilw101
 */
class RestDigWorkflow {
    
    private $sServiceUrl = '';
     
    public function __construct($sServiceUrl) {
        $this->sServiceUrl = $sServiceUrl;
    }

    /**
     * Get the total number of scans that should be present for a given item
     * 
     * @param string $sBookId
     * @return array
     */
    public function getTotalScans($sBookId)
    {
        $aTotalScans = array();
    
        $sTotalScansUrl = $this->sServiceUrl . '/totalpages/' . $sBookId;
    
        try {
            $totalscansdata = file_get_contents($sTotalScansUrl);
            $totalfixed = str_replace( array('&lt;','&gt;') ,array('<','>'),$totalscansdata);
    
            $xml = new SimpleXMLElement($totalfixed);
            if (is_object($xml)) {
                $totalpagesfield = $xml->xpath('//totalpages');
                $totalpages = (int) $totalpagesfield[0];
                $aTotalScans['total'] = $totalpages;
            }
            else {
                $aTotalScans['error'] = 'Could not get total pages: no XML';
            }
        }
        catch (Exception $e) {
            $aTotalScans['error'] = 'Could not get total pages';
       }
    
        return $aTotalScans;
    }

	/**
	 * Get data on quality control scans
	 * Not all items have these, so make sure to handle those cases
	 * 
	 * @param string $sBookId
	 * @return array
	 */
	public function getQCData($sBookId)
	{
		$aData = array();
		
		$sQcDataUrl = $this->sServiceUrl . '/qcdata/' . $sBookId;
		try {
			$sQcData = file_get_contents($sQcDataUrl);
			$sQcFixed = str_replace(array('&lt;','&gt;') ,array('<','>'),$sQcData);
			
			$sXml = new SimpleXMLElement($sQcFixed);
			$aFileNameField = $sXml->xpath('//qcfilenames');
			if (count($aFileNameField) > 0) {
				$sFileNames = (string) $aFileNameField[0];
				$aData['filenames'] = $sFileNames;
			}
			else {
				$aData['filenames'] = '';
			}
		}
		catch (Exception $exc) {
			//echo $exc->getTraceAsString();
			$aData['filenames'] = '';
			$aData['error'] = $exc->getMessage();
		}
		
		return $aData;
	}
	
	
    /**
     * Get the metadata for a given item
     * 
     * @param string $sBookId
     * @return array
     */
    public function getMetadata($sBookId)
    {
        $aData = array();
        $sMetadataUrl = $this->sServiceUrl . '/metadata/' . $sBookId;
        
        try {
            $sMetadata = file_get_contents($sMetadataUrl);
            $sMetadataFixed = str_replace(array('&lt;','&gt;') ,array('<','>'),$sMetadata);
            
            $aParsedData = $this->parseData($sMetadataFixed);
            if (isset($aParsedData['error'])) {
                $aData['error'] = $aParsedData['error'];
            }
            else {
                $aData['metadata'] = $aParsedData['metadata'];
            }
            
        }
        catch (Exception $e) {
            $aData['error'] = 'could not get metadata from digworkflow';
        }
        
        return $aData;
    }
    
    /**
     * Parse the metadata that you got from the workflowservice
     * 
     * @param string $sXmlString
     * @return array
     */
    private function parseData($sXmlString)
    {
        $aDebug = array();
        
        try {
            $sXML = new SimpleXMLElement($sXmlString);
            if (is_object($sXML)) {
                $aData = array();
                
                //first get typecontent, title and authors
                $typefield = $sXML->xpath('//type_content');
                $aData['type_content'] = (string) $typefield[0];
                
                
                $titlefield = $sXML->xpath('//title');
                $aData['title'] = (string) $titlefield[0];
                
                $authorfield = $sXML->xpath('//contributor_author');
                if (count($authorfield) > 0) {
                    foreach ($authorfield as $oneauthor) {
                        $aData['contributor_author'][] = (string) $oneauthor;
                    }
                }
                
                $aFields = array(
                    'date_issued',
                    'title_alternative',
                    'description_short',
                    'contributor_author',
                    'rights_impressum',
                    'publisher',
                    'rights_placeofpublication',
                    'rights_impressum',
                    'subject_discipline',
                    'subject_keywords',
                    'description_abstract',
                    'description_abstracteng',
                    'contributor_digitizer',
                    'publisher_publisherurl',
                    'rights_accessrights',
                    'source_location',
                    'source_signature',
                    'source_inventory',
                    'description_note',
                    'repository_ubu',
                    'source_alephid',
                    'relation_ispartofalephid',
                    'relation_ispartofvolume',
                    'relation_ispartofissue',
                    'relation_ispartofstartpage',
                    'relation_ispartofendpage',
                    'relation_ispartofother',
                );
                
                foreach ($aFields as $sFieldName) {
                    $find = '//' . $sFieldName;
                    $field = $sXML->xpath($find);
                    if (count($field) > 0) {
                        $aData[$sFieldName] = (string) $field[0];
                    }
                }
                
                $sequencefield = $sXML->xpath('//sequence');
                $aData['sequence'] = (string) $sequencefield[0];
                
                $aDebug['metadata'] = $aData;
            }
            else {
                $aDebug['error'] = 'workflow service did not return XML';
            }
        }
        catch (Exception $e) {
            $aDebug['error'] = 'could not parse workflow service data';
        }
        
        return $aDebug;
        
    }
    
}

