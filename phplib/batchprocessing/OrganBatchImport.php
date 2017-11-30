<?php
require_once 'BatchImport.php';

class OrganBatchImport implements BatchImport {
	
	private $sServiceUrl = '';
	
	function __construct($sServiceUrl) {
		//$this->sServiceUrl = 'http://uws9.library.uu.nl/digworkflow/organservice';
		$this->sServiceUrl = $sServiceUrl;
	}
	
	public function getFileData($sDocumentPath)
	{
		$aFiles = $this->getPages($sDocumentPath);
		
		return $aFiles;
	}
	
	public function getIdentifiers($sDirectoryName)
	{
		$aDirNameParts = explode('_', $sDirectoryName);
		$sOrganId = $aDirNameParts[0];
		$sDocumentId = $aDirNameParts[1];
		
		$aIdentifiers = array(
			'organ_id' => $sOrganId,
			'doc_id' => $sDocumentId,
		);
		
		return $aIdentifiers;
	}
	
	public function getMetadata($aIdentifiers)
	{
		$aMetadata = array();

		$sOrganId = $aIdentifiers['organ_id'];
		$sDocumentId = $aIdentifiers['doc_id'];
		
		$aOrganDataFound = $this->getOrganData($sOrganId);
		if (isset($aOrganDataFound['error'])) {
			$aMetadata['error'] = 'no data found for organ ' . $sOrganId;
			return $aMetadata;
		}
		else {
			$aDocumentDataFound = $this->getDocumentData($sDocumentId);
			if (isset($aDocumentDataFound['error'])) {
				$aMetadata['error'] = 'no data found for document ' . $sDocumentId;
				return $aMetadata;
			}
			else {
				$aOrganForDSpace = $this->parseOrganData($aOrganDataFound);
				$aDocumentForDspace = $this->parseDocumentData($aDocumentDataFound);
		
				foreach ($aOrganForDSpace as $sFieldName => $aField) {
					$aMetadata[$sFieldName] = $aField;
				}
				foreach ($aDocumentForDspace as $sFieldName => $aField) {
					$aMetadata[$sFieldName] = $aField;
				}
				
				//organ documents don't have a title, so we add one here
				$sTitle = 'Organ document ' . $sDocumentId;
				$aMetadata['title'] = $sTitle;
				
				//add an identifier
				$sIdentifierOther = $sOrganId . '_' . $sDocumentId;
				$aMetadata['identifier_other'] = $sIdentifierOther;
				
				return $aMetadata;
			}
	
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

	
	private function getOrganData($sOrganId)
	{
		$sUrl = $this->sServiceUrl . '/organdata/' . $sOrganId . '.json';
		$sDataFound = file_get_contents($sUrl);
	
		$aOrganData = json_decode($sDataFound, true);
		return $aOrganData;
	}
	
	private function getDocumentData($sDocumentId)
	{
		$sUrl = $this->sServiceUrl . '/documentdata/' . $sDocumentId . '.json';
		$sDataFound = file_get_contents($sUrl);
	
		$aDocumentData = json_decode($sDataFound, true);
		return $aDocumentData;

	}
	
	private function parseOrganData($aOrganDataFound)
	{
		$aParsedData = array();
		
		$aParsedData['organ_datebuilt'] = $aOrganDataFound['organbase']['organ_date'];
		$sPlace = $aOrganDataFound['organparsed']['building']['city'];
		$sBuilding = $aOrganDataFound['organparsed']['building']['building_name'];
		$aParsedData['organ_place'] = $sPlace;
		$aParsedData['organ_building'] = $sBuilding;
		$aParsedData['coverage_spatial'] = $sBuilding . ', ' . $sPlace;
		if ($aOrganDataFound['organbase']['RDMZ_number'] != -1) {
			$aParsedData['organ_rcenumber'] = $aOrganDataFound['organbase']['RDMZ_number'];
		} 
		elseif ($aOrganDataFound['organbase']['RDMZ_case_number'] != -1) {
			$aParsedData['organ_rcenumber'] = $aOrganDataFound['organbase']['RDMZ_case_number'];
		}
		$aOrganBuildersFound = $aOrganDataFound['organparsed']['organbuilder'];
		foreach ($aOrganBuildersFound as $sOneBuilder) {
			$aParsedData['organ_builder'][] = $sOneBuilder;
		}
		
		return $aParsedData;
	}
	
	
	private function parseDocumentData($aDocumentDataFound)
	{
		$aParsedData = array();
		
		$aParsedData['organ_archive'] = $aDocumentDataFound['parsedbase']['holding_archive'];
		$aParsedData['organ_contenttype'] = 
				$aDocumentDataFound['parsedbase']['document_form'] . 
				$aDocumentDataFound['parsedbase']['document_type'];
		$aParsedData['type_content'] = 
				$this->getTypeContent($aDocumentDataFound['parsedbase']['document_form'], $aDocumentDataFound['parsedbase']['document_type']);
		
		$aParsedData['relation_ispartofnumberofpages'] = $aDocumentDataFound['base']['number_of_pages'];
		if ($aDocumentDataFound['base']['note'] != '') {
			$aParsedData['description_note'] = $aDocumentDataFound['base']['note'];
		}
		if ($aDocumentDataFound['base']['abstract'] != '') {
			$aParsedData['description_abstract'] = $aDocumentDataFound['base']['abstract'];
		}
		
		//dates
		if (isset($aDocumentDataFound['base']['exact_date']) && $aDocumentDataFound['base']['exact_date'] != '') {
			$aParsedData['date_issued'] = $aDocumentDataFound['base']['exact_date'];
		}
		elseif ($aDocumentDataFound['base']['period_from'] != 0) {
			$aParsedData['date_issued'] = $aDocumentDataFound['base']['period_from'];
		}
		if ($aDocumentDataFound['base']['period_from'] != 0) {
			$aParsedData['coverage_temporalstart'] = $aDocumentDataFound['base']['period_from'];
		}
		if (isset($aDocumentDataFound['base']['period_to']) && $aDocumentDataFound['base']['period_to'] != 0) {
			$aParsedData['coverage_temporalend'] = $aDocumentDataFound['base']['period_to'];
		}
		
		//authors and contributors
		if ($aDocumentDataFound['base']['author_name'] != '') {
			$aParsedData['contributor_author'][] = $aDocumentDataFound['base']['author_name'];
		}
		if (isset($aDocumentDataFound['parsedbase']['contributor_author'])) {
			$aParsedData['contributor_author'][] = $aDocumentDataFound['parsedbase']['contributor_author'];
		}
		if ($aDocumentDataFound['base']['sender_name'] != '') {
			$aParsedData['contributor_person'][] = $aDocumentDataFound['base']['sender_name'];
		}
		if ($aDocumentDataFound['base']['recipient_name'] != '') {
			$aParsedData['contributor_person'][] = $aDocumentDataFound['base']['recipient_name'];
		}
		if (!empty($aDocumentDataFound['parsedbase']['contributor_person'])) {
			foreach ($aDocumentDataFound['parsedbase']['contributor_person'] as $sPerson) {
				$aParsedData['contributor_person'][] = $sPerson;
			}
		}
		if (!empty($aDocumentDataFound['parsedbase']['contributor_corporation'])) {
			foreach ($aDocumentDataFound['parsedbase']['contributor_corporation'] as $sCorporation) {
				$aParsedData['contributor_corporation'][] = $sCorporation;
			}
		}
	
		return $aParsedData;
	}
	
	private function getTypeContent($sDocForm, $sDocType) {
		$sTypeContent = 'Other';
		if ($sDocForm == 'Handgeschreven') {
			$sTypeContent = 'Manuscript';
		}
		else {
			switch ($sDocType) {
				case 'tijdschrift':
					$sTypeContent = 'Article';
					break;
				case 'krant':
					$sTypeContent = 'Article';
					break;
				case 'boek':
					$sTypeContent = 'Book';
					break;
				case 'foto':
					$sTypeContent = 'Photograph';
					break;
				case 'keuringsrapport';
					$sTypeContent = 'Report';
					break;
				case 'rapport':
					$sTypeContent = 'Report';
					break;
				default:
					break;
			}
		}
		return $sTypeContent;
	}
	
	
}



