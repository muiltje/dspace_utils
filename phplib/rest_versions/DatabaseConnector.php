<?php
/**
 * Central place to get the DSpace connection
 *
 * @author muilw101
 */
class DatabaseConnector {
    
    private $aIniArray = array();
    private $sHost;
    
    /**
     * Construct and get the name of the current host
     */
    public function __construct() {
        $inifile = '/home/dspace/utils/phplib/config/dspace.ini';

        $this->aIniArray = parse_ini_file($inifile, true);

        $sHostname = strtolower(php_uname('n'));
        if (preg_match('/grieg/', $sHostname)) {
            $this->sHost = 'grieg';
        }
		elseif (preg_match('/bizet/', $sHostname)) {
			$this->sHost = 'bizet';
		}
		else {
            $this->sHost = 'elgar';
        }

    }
    
    /**
     * Connect to the right database
     * @return type
     */
    public function getConnection()
    {
        require $this->aIniArray[$this->sHost]['dbconnect'];
        $dbconn = @pg_connect($connect); //connect is set in the required file
        
        return $dbconn;
    }
    
}

