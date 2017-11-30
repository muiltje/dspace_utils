<?php

require_once '../init.php';

$sScrolService = SCROL_SERVICE . '/periodtheses';

$oItem = new Item();
$oItemMetadata = new ItemMetadata();

$nStudentNumberFieldId = 195;
$nSubjectCourseFieldId = 167;
$nTypeContentFieldId = 146;
$nIdentifierOtherFieldId = 71;
$nTitleFieldId = 143;


/**
 * If we want to automate this, we will have to get the desired values
 * from somewhere, e.g. a file (like with download.php).
 * But for now, we will hard code them
 * 
 * In Dspace-admin we can fetch a list of studies per faculty:
 * http://uws12.library.uu.nl/scrolosiris/scrolservice/studies/GW/BA
 * this won't work if the study is not a current one
 * 
 * or you could use an Autonomy query 
 * $querystr = 'action=GetQueryTagValues&FieldName=DC.SUBJECT.COURSEUU&Text={"' . $faculty . '"}:DSPACE_COLLECTION&DatabaseMatch=scrol&sort=Alphabetical';
 * $u="$autonomy_uri/$query";
 * $autonomy_uri='http://192.168.9.190:50200
 */

$sStartLine = ' ======== Reporting on student theses on ' . date('Ymd') . ' ========';
wlog($sStartLine, ' ');

//start by reading the input file:
$sReportRequestFile = '/tmp/thesisreportrequest.txt';
$aFaculties = array();
$aShortTypes = array();
$aAcademicYears = array();
$aCourses = array();

$fh = fopen($sReportRequestFile, "r");
while (($buffer = fgets($fh)) !== false) {
	//$aFileNameParts = explode('-', $buffer);
	//aargh, some coursenames also have - in them, so this doesn't work
	
	//first check if a file of that name already exists; 
	//no point on recreating it
	$sFileToCheck = REPORT_BASE . $buffer;
	if (!file_exists($sFileToCheck)) {
		$sFacPos = strpos($buffer, '-');
		$sFaculty = substr($buffer, 0, $sFacPos);
		$sAfterFac = substr($buffer, $sFacPos+1);
		$sYearPos = strpos($sAfterFac, '-');
		$sAcademicYear = substr($sAfterFac, 0, $sYearPos);
		$sAfterYear = substr($sAfterFac, $sYearPos+1);
		$sTypePos = strpos($sAfterYear, '-');
		$sShortType = substr($sAfterYear, 0, $sTypePos);
		$sTheRest = substr($sAfterYear, $sTypePos+1);
		$sExtPos = strpos($sTheRest, 'csv');
		$sUnparsedCourse = substr($sTheRest, 0, $sExtPos-1);
		$sCourse = preg_replace('/_/', ' ', $sUnparsedCourse);
		
		$aFaculties[] = $sFaculty;
		$aShortTypes[] = $sShortType;
		$aAcademicYears[] = $sAcademicYear;
		$aCourses[] = $sCourse;
	}
}
fclose($fh);


$sFaculty = $aFaculties[0]; //we know there is only one
$sShortType = $aShortTypes[0]; //we know there is only one
$aAcYears = array_unique($aAcademicYears);
$aCourseNames = array_unique($aCourses);
		
$sTypeContent = 'Master thesis';
if ($sShortType == 'BA') {
	$sTypeContent = 'Bachelor thesis';
}


$aAllRelevantTheses = array();



foreach ($aAcYears as $sOneAcademicYear) {
	$sStartDate = substr($sOneAcademicYear, 0, 4) . '-09-01';
	$sEndDate = substr($sOneAcademicYear, 4, 4) . '-08-31';

	$sThesesUrl = $sScrolService . '/' . $sStartDate . '/' . $sEndDate . '/' . $sFaculty;
	//echo $sThesesUrl . "\n";
	$sThesesResponse = file_get_contents($sThesesUrl);
	$aScrolIds = json_decode($sThesesResponse, true);

	/**
	* Now go through all those ids and find their metadata
	* This is the time consuming part and the reason we can't do this
	* "live" on an admin page
	*/
	foreach ($aScrolIds as $aOneScrol) {
		$sScrolId = trim($aOneScrol['scrol_id']);
		$sDateExam = $aOneScrol['date_exam'];
	
		$aItem = $oItemMetadata->findItemByMetadata($nIdentifierOtherFieldId, $sScrolId);
		if (!isset($aItem['itemids'])) {
			$sLogLine = 'Item with ScrolId ' . $sScrolId . ' is not in DSpace';
			wlog($sLogLine, 'INF');
		}
		else {
			$nItemId = $aItem['itemids'][0];
		
			//find type
			//is it the one we're loooking for
			$aTypeContentData = $oItemMetadata->getMetadataValue($nItemId, $nTypeContentFieldId);
			$sTypeContentFound = $aTypeContentData['values'][0];
			if ($sTypeContentFound == $sTypeContent) {
				//OK, we have the desired type of thesis, let's continue
				//get the course
				$aCourseData = $oItemMetadata->getMetadataValue($nItemId, $nSubjectCourseFieldId);
				$sCourseFound = $aCourseData['values'][0];
				//is course in the Courses array
				if (in_array($sCourseFound, $aCourseNames)) {
					//echo 'got one' . "\n";
					//get title
					$aTitleData = $oItemMetadata->getMetadataValue($nItemId, $nTitleFieldId);
					$sTitle = $aTitleData['values'][0];
		
					//get student number
					$aStudentData = $oItemMetadata->getMetadataValue($nItemId, $nStudentNumberFieldId);
					$sStudentNumber = $aStudentData['values'][0];
				
					//$sLine = $sScrolId . ' - ' . $sTitle . ' - ' . $sStudentNumber . ' - ' . $sDateExam . "\n";
					//echo $sLine;
				
					$aAllRelevantTheses[$sCourseFound][] = array(
						'title' => $sTitle, 'studentnumber' => $sStudentNumber, 'examdate' => $sDateExam,
					);
				}
				else {
					//echo 'The course is ' . $sCourseFound . "\n";
				}
			}
			else {
				//echo 'not the right type, it is a ' . $sTypeContentFound  . "\n";
			}
		}
	}
}

//print_r($aAllRelevantTheses);

$nFileCount = 0;
foreach ($aAllRelevantTheses as $sCourseName => $aTheses) {
	$sFile = REPORT_BASE . $sFaculty . '-'  . $sOneAcademicYear . '-' 
			. $sShortType . '-' . preg_replace('/ /', '_', $sCourseName) . '.csv';
	
	echo 'writing ' . $sFile . "\n";
	
	$nFileCount++;
	$fh = fopen($sFile, "w");
	
	$sFirstLine = '"Title";"Student number";"Exam date"' . "\n";
	fwrite($fh, $sFirstLine);
	
	foreach ($aTheses as $aOneThesis) {
		$sRawTitle = $aOneThesis['title'];
		$sTitle = preg_replace('/\n/', ' ', $sRawTitle);
		$sStudentNumber = $aOneThesis['studentnumber'];
		$sRawExamDate = $aOneThesis['examdate'];
		$sUnquotedExamDate = preg_replace('/"/', '', $sRawExamDate);
		$sExamDate = preg_replace('/\n/', '', $sUnquotedExamDate);
		
		$sLine = '"' . $sTitle . '";"' . $sStudentNumber . '";"' . $sExamDate . '"' . "\n";
		fwrite($fh, $sLine);
	}
	fclose($fh);
}

//echo "done \n";
$sEndLine = '===== Reporting done; wrote ' . $nFileCount . ' files ======';
wlog($sEndLine, 'INF');

