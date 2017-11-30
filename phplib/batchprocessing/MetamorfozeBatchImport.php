<?php

require_once 'BatchImport.php';

class MetamorfozeBatchImport implements BatchImport {
	
	private $sAlephServiceUrl;
	
	function __construct($sAlephServiceUrl) {
		$this->sAlephServiceUrl = $sAlephServiceUrl;
	}
	
	
	public function getFileData($sDocumentPath)
	{
		$aFiles = $this->getPages($sDocumentPath);
		
		return $aFiles;
	}
	
	public function getIdentifiers($sDirectoryName)
	{
		$sKbItemIdentifier = $sDirectoryName;
		$sAlephSysNumber = $this->findAlephSysNumber($sKbItemIdentifier);
		
		$aIdentifiers = array(
			'kb_identifier' => $sKbItemIdentifier,
			'aleph_sysnumber' => $sAlephSysNumber,
		);
		
		return $aIdentifiers;
	}
	
	
	public function getMetadata($aIdentifiers)
	{
		$sAlephSys = $aIdentifiers['aleph_sysnumber'];
		$sRawData = $this->findAlephData($sAlephSys);
		$aParsedData = $this->parseAlephData($sRawData);
		
		return $aParsedData;
	}
	
	private function findAlephSysNumber($sKbIdentifier)
	{
		$sDataFile = '/home/dspace/utils/newimport/batchimports/preparation/BNBTweeItems.php';
		require_once $sDataFile;
		
		$aItem = $aAllBnbTwoItems[$sKbIdentifier];
		$sAlephSys = $aItem['alephsys'];
		
		return $sAlephSys;
	}
	
	private function findAlephData($sAlephSys)
	{
		$sUrl = $this->sAlephServiceUrl . $sAlephSys;
		$sAlephResponse = file_get_contents($sUrl);
		
		return $sAlephResponse;
	}
	
	/**
	 * 
	 * @param type $sRawData
	 */
	private function parseAlephData($sRawData)
	{
		$aMetadata = array();
        $sErrorMessage = '';

        //check if we get XML back
        $xml = new SimpleXMLElement($sRawData);
        if (is_object($xml)) {

            //check if we don't have an error message
            $errorfield = $xml->xpath('//error');
            if (count($errorfield) > 0) {
                $sErrorMessage = 'ongeldig alephnummer';
             }
            else {
                //we need: document type; geographical area; title; author; year; impressum;
                //standardised publisher and place names; source inventory
                $sTitle = '';
                $sTitleAlternative = '';
                $sAuthors = '';
                $sImpressum = '';
                $sPublisher = '';
                $sPlace = '';

                $titlefield = $xml->xpath('//varfield[@id="245"]/subfield[@label="a"]');
                $sFoundTitle =  (string) $titlefield[0];

                $bHasSubtitle = 'n';
                $bHasAlternativeTitle = 'n';
                if (substr($sFoundTitle, -1) == ':') {
                    $bHasSubtitle = 'y';
                    $sTitle = substr($sFoundTitle, 0, -1);
                }
                elseif (substr($sTitle, -1) == '=') {
                    $bHasAlternativeTitle = 'y';
                    $sTitle = substr($sFoundTitle, 0, -1);
                }
                else {
                    //title may end with a / or with a \
                    $sTitle_Unslash = preg_replace('/\/$/', '', $sFoundTitle);
					$sTitle = preg_replace('/\\\$/', '', $sTitle_Unslash);
                }
                
                /*
                 * NEW 21 subtitle and alternative title both in subfield b
                 * if subfield a ends with : subfield b holds subtitle
                 * if subfield a ends with = subfield b holds alternative title
                 * subtitle may also contain an alternative title:
                 * 24510 $$aDit is een titel:$$bmet een ondertitel = This is a title : with a subtitle
                 *     
                 */
                //get rest of 245
                //b is added to title
                $subtitlefield = $xml->xpath('//varfield[@id="245"]/subfield[@label="b"]');
                
                if (count($subtitlefield) > 0) {
                    //do we have a subtitle
                    if ($bHasSubtitle == 'y') {
                        $sSubtitle = (string) $subtitlefield[0];
                        //does the subtitle contain a =
                        if (strpos($sSubtitle, '=')) {
                            $aParts = explode('=', $sSubtitle);
                            //part may end with /
                            $sTitle .= ': ' . preg_replace('/\/$/', '', $aParts[0]);
                            
                            //part may end with /
                            $sTitleAlternative = preg_replace('/\/$/', '', $aParts[1]); //string has alternative title plus its subtitle if any
                        }
                        else {
                            //no =, just subtitle
                            //subtitle may end with /
                            $sTitle .= ': ' . preg_replace('/\/$/', '', $sSubtitle); 
                        }
                    }
                    elseif ($bHasAlternativeTitle == 'y') {
                        //else we have an alternative title
                        //it may end with a /
                        $sFoundTitleAlternative = (string) $subtitlefield[0];
                        $sTitleAlternative = preg_replace('/\/$/', '', $sFoundTitleAlternative);
                    }
                }

				/*
                 * NEW MARC 21 Whole name is now in subfield a
                 */
                //authors: 100a and h; there can be several
                $authorfield = $xml->xpath('//varfield[@id="100"]');
                if (count($authorfield) > 0) {
                    $sAuthorLine = '';
                    foreach ($authorfield as $author) {
                        $aname = $author->xpath('subfield[@label="a"]');
                        $fname = (string) $aname[0];
                        $name = preg_replace('/,$/', '', $fname);
                        $sAuthorLine .= $name . '| ';
                    }
                    $sAuthors = preg_replace('/\| $/', '', $sAuthorLine);
                }

                //impressum
				/**
				 * For titles catalogued before 2016 (in Aleph) this is in 260.
				 * For titles catalogued later (in Worldcat) this is in 264
				 */
                $placefield_one = $xml->xpath('//varfield[@id="260"]/subfield[@label="a"]');
				$placefield_two = $xml->xpath('//varfield[@id="264"]/subfield[@label="a"]');
                $place = '';
					
                if (count($placefield_one) > 0) {
                    $sFoundplace = (string) $placefield_one[0];
                    $place = utf8_decode($sFoundplace);
                }
				elseif (count($placefield_two) > 0) {
					$sFoundplace = (string) $placefield_two[0];
					$place = utf8_decode($sFoundplace);
				}
				
                $publfield_one = $xml->xpath('//varfield[@id="260"]/subfield[@label="b"]');
				$publfield_two = $xml->xpath('//varfield[@id="264"]/subfield[@label="b"]');
                $publ = '';
                if (count($publfield_one) > 0) {
                    $sFoundpubl = (string) $publfield_one[0];
                    //remove , from foundpubl
                    $sStrippedpubl = preg_replace('/,$/', '', $sFoundpubl);
                    $publ = utf8_decode($sStrippedpubl);
                }
				elseif (count($placefield_two) > 0) {
                    $sFoundpubl = (string) $publfield_two[0];
                    //remove , from foundpubl
                    $sStrippedpubl = preg_replace('/,$/', '', $sFoundpubl);
                    $publ = utf8_decode($sStrippedpubl);
				}
                
				if ($place != '') {
                    $sImpressum = $place;
                    if ($publ != '') {
                        $sImpressum .= ' ' . $publ;
                    }
                }
                elseif ($publ != '') {
                    $sImpressum = $publ;
                }

                //year: 260c
				/**
				 * Year: 
				 * Used to be 260c (and still is for titles catalogued before 2016)
				 * 264 is the field to use according to the new standards,
				 * so newly catalogued titles will have 264c
				 */
                $yearfound = '';
				$yearfield_one = $xml->xpath('//varfield[@id="260"]/subfield[@label="c"]');
				$yearfield_two = $xml->xpath('//varfield[@id="264"]/subfield[@label="c"]');
				if (count($yearfield_one) > 0) {
					$yearfound = (string) $yearfield_one[0];
				}
				elseif (count($yearfield_two) > 0) {
					$yearfound = (string) $yearfield_two[0];
				}
				else {
					$yearfound = 0;
				}
				
                //handle year fields that have non-numeric characters in them
                //if $year contains a -, take the first part (the second part may be empty)
				$sSingleYear = $yearfound;
                if (preg_match('/-/', $yearfound)) {
                    $years = explode('-', $yearfound);
                    $sSingleYear = $years[0];
                }
                //strip every non-numeric character
                $sYear = preg_replace('/[^0-9]/', '', $sSingleYear);

                //paging, as indication of what to expect
                $sAlephPaging = '';
                $alephpagingfield = $xml->xpath('//varfield[@id="300"]/subfield[@label="a"]');
                if (count($alephpagingfield) > 0) {
                    $sFoundAlephPaging = (string) $alephpagingfield[0];
                    //strip closing ;
                    $sAlephPaging = preg_replace('/;$/', '', $sFoundAlephPaging);
                }


                /*
                 * MARC 21 field 900: looks like that remains the same
                 */
                //standardised name of the publisher/printer
                $publisherlastname = '';
                $publisherlastnamefield = $xml->xpath('//varfield[@id="900"]/subfield[@label="a"]');
                if (count($publisherlastnamefield) > 0) {
                    $sFoundpublisherlastname = (string) $publisherlastnamefield[0];
                    $publisherlastname = utf8_decode($sFoundpublisherlastname);
                }
                $publisherfirstname = '';
                $publisherfirstnamefield = $xml->xpath('//varfield[@id="900"]/subfield[@label="h"]');
                if (count($publisherfirstnamefield) > 0) {
                    $sFoundpublisherfirstname = (string) $publisherfirstnamefield[0];
                    $publisherfirstname = utf8_decode($sFoundpublisherfirstname);
                }

                if ($publisherlastname != '') {
                    $sPublisher = $publisherlastname;
                    if ($publisherfirstname != '') {
                        $sPublisher .= ', ' . $publisherfirstname;
                    }
                }

                //standardised pplace of publication
                $standardpubplacefield = $xml->xpath('//varfield[@id="900"]/subfield[@label="t"]');
                if (count($standardpubplacefield) > 0) {
                	$sFoundPlace = (string) $standardpubplacefield[0];
                	$sPlace = utf8_decode($sFoundPlace);
	}


                $aMetadata['type_content'] = 'Book';
                $aMetadata['contributor_author'] = $sAuthors;
                $aMetadata['title'] = $sTitle;
                $aMetadata['title_alternative'] = $sTitleAlternative;
                $aMetadata['date_issued'] = $sYear;
                $aMetadata['rights_impressum'] = $sImpressum; //the 260 field
                $aMetadata['publisher'] = $sPublisher; //the 900 field
                $aMetadata['rights_placeofpublication'] = $sPlace;
            }
        }
        else {
            $sErrorMessage = 'geen verbinding met Aleph';
        }
 		if ($sErrorMessage != '') {
			$aMetadata['error'] = $sErrorMessage;
		}
		
		return $aMetadata;
	}
	
	
	
	private function getPages($sDocumentPath)
	{
		$aPages = array();
		$dhscans = opendir($sDocumentPath . '/');
		while (($sScanInfile = readdir($dhscans)) !== false) {
			if ($sScanInfile != '.' && $sScanInfile != '..') {
				$sPageSub = substr($sScanInfile, 5, 3);
				$nPageNumber = (int) $sPageSub;
				$sFileType = substr($sScanInfile, -3);
				if ($sFileType == 'tif') {
					$sFileSize = filesize($sDocumentPath . '/' . $sScanInfile);
					$sFileChecksum = md5_file($sDocumentPath . '/' . $sScanInfile);
					$aPages['tifs'][$nPageNumber] = array(
						'name' => $sScanInfile,
						'filesize' => $sFileSize,
						'checksum' => $sFileChecksum,
					);
				}
				elseif ($sFileType == 'htm') {
					$aPages['htms'][$nPageNumber] = $sScanInfile;
				}
			}
		}
		closedir($dhscans);
		
		return $aPages;
	}
}

