<?php

class RestConnector {

	private $sToken = '';
	private $sBaseUrl = '';
	private $sUserCredentials = '';
	
	/**
	 * 
	 * 
	 */
	function __construct() {
		$inifile = '/home/dspace/utils/phplib/config/dspace.ini';
        $aIniArray = parse_ini_file($inifile, true);
		$sHost = '';
        $sHostname = strtolower(php_uname('n'));
        if (preg_match('/grieg/', $sHostname)) {
            $sHost = 'grieg';
        }
		elseif (preg_match('/bizet/', $sHostname)) {
			$sHost = 'bizet';
		}
        else {
            $sHost = 'elgar';
        }
    
		$this->sBaseUrl = $aIniArray[$sHost]['restbaseurl'];
		//$this->sBaseUrl = $aIniArray[$sHost]['restreadonlybaseurl'];
		$this->sUserCredentials = file_get_contents($aIniArray[$sHost]['restconnect']);
				
		$aStatus = $this->checkLoginStatus();
		if ($aStatus['authenticated'] === 'true') {
			$this->sToken = $aStatus['rest-dspace-token'];
		}
		else {
			$this->sToken = $this->doLogin();
		}
	}

	private function checkLoginStatus()
	{
		$sService = 'status';
		$sData = '';
		
		$sCheckResult = $this->doLoginRequests($sService, 'GET', $sData);
		$aResult = json_decode($sCheckResult, true);
		return $aResult;
	}
	
	
	public function findLoginStatus() {
		return $this->sToken;
	
	}
	
	private function doLogin()
	{
		$sService = 'login';
				
		$aResult = $this->doLoginRequests($sService, 'POST', $this->sUserCredentials);
		
		return $aResult;
	}
	
	/**
	 * The login and status endpoints do not expect a token
	 * 
	 * @param type $sService
	 * @param type $sMethod
	 * @param type $sData
	 */
	private function doLoginRequests($sService, $sMethod, $sData) {
		//$oData = http_build_query($aData);
		$sHeader = 'Accept: application/json' . "\r\n";
		$sHeader .= 'Content-Type: application/json' . "\r\n";
		$sUrl = $this->sBaseUrl . '/' . $sService;
		
		$aParams = array(
			'http' => array(
				'method' => $sMethod,
				'content' => $sData,
				'header' => $sHeader,
			),
		);
		
		$oContext = stream_context_create($aParams);
		
		try {
			$fu = fopen($sUrl, 'rb', false, $oContext);
			$aResult = stream_get_contents($fu);
		} 
		catch (Exception $exc) {
			//echo $exc->getTraceAsString();
			$aResult['error'] = $exc->getMessage(); 
		}
		
		return $aResult;
	}
	
	/**
	 * Once you're logged in, always include the token in your header
	 * 
	 * @param string $sService
	 * @param string $sMethod
	 * @param string $sData
	 */
	public function doRequest($sService, $sMethod, $sData) 
	{
		$sHeader = 'Accept: application/json' . "\r\n";
		$sHeader .= 'Content-Type: application/json' . "\r\n";
		$sHeader .= 'rest-dspace-token: ' . $this->sToken . "\r\n";
		
		$sUrl = $this->sBaseUrl . '/' . $sService;
		
		$aParams = array(
			'http' => array(
				'method' => $sMethod,
				'content' => $sData,
				'header' => $sHeader,
			),
		);
		
		$oContext = stream_context_create($aParams);
		$aResult = array();
		try {
			$fu = fopen($sUrl, 'rb', false, $oContext);
			if ($fu) {
				$aResult['response'] = stream_get_contents($fu);
			}
			else {
				$aResult['error'] = 'could not open ' . $sUrl;
			}
		} 
		catch (Exception $exc) {
			$aResult['error'] = $exc->getMessage(); 
		}
		
		return $aResult;
	}
	
}

