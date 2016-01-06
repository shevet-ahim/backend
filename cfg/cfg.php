<?php
error_reporting(E_ALL);
ini_set('display_errors',0);
ini_set('log_errors',1);
ini_set('error_log','/var/www/shevet_ahim/errors.log');

class object {}
$CFG = new object ( );
$CFG->dbhost = "localhost";
$CFG->dbname = "shevet_ahim";
$CFG->dbuser = "root";
$CFG->dbpass = "12345";
?>