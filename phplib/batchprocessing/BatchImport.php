<?php
/**
 * Interface for classes that get metadata for an item from a source.
 * Implementations should:
 *  - fetch the raw metadata from the source (or some external system that shares an
 * identifier with the source, like an Alephsysnumber)
 *  - parse the metadata
 *  - return an array with the correct Dublin Core elements and their values
 */

interface BatchImport {
	
	function getIdentifiers($sDirectoryName);
	
	function getMetadata($aIdentifiers);
	
	function getFileData($sImportPath);

}

