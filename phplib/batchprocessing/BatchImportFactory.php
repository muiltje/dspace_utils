<?php

abstract class BatchImportFactory {
	
	function createImportSource($sSourceName, $sMetadataService) 
	{
		$oSourceClass = null;
		
		switch($sSourceName) {
			case 'organ':
				require_once 'OrganBatchImport.php';
				return new OrganBatchImport($sMetadataService);
			default:
				require_once 'OrganBatchImport.php';
				return new OrganBatchImport($sMetadataService);
		
		}
		
		return $oSourceClass;
	}
	
}
