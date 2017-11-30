<?php

require_once 'alert_init.php';

/*
 * this doesn't work yet, because the user has no permission to connect
 */
$mysqli;
try {
    $mysqli = mysqli_connect(IGDISSHOSTPROD, $sUser, $sPwd, 'igitur');
    print_r($mysqli);
}
catch (Exception $e) {
    echo 'no connection';
    //exit();
}
echo "\n";
?>
