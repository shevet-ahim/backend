<?php 

include '../cfg/cfg.php';
include '../lib/dblib.php';
include '../lib/stdlib.php';
include '../lib/simple_html_dom.php';
include '../lib/autoload.php';

/* Connect to DB */
db_connect($CFG->dbhost,$CFG->dbname,$CFG->dbuser,$CFG->dbpass);

// memcached check
$CFG->memcached = (class_exists('Memcached'));
$CFG->m = false;
if ($CFG->memcached) {
	$CFG->m = (class_exists('MemcachedFallback')) ? new MemcachedFallback() : new Memcached();
	$CFG->m->addServer('localhost', 11211);
}

/* Load settings and timezone */
Settings::assign();
date_default_timezone_set($CFG->default_timezone);
$dtz = new DateTimeZone($CFG->default_timezone);
$dtz1 = new DateTime('now', $dtz);
$CFG->timezone_offset = $dtz->getOffset($dtz1);

/* Current URL */
$CFG->self = basename($_SERVER['SCRIPT_FILENAME']);

/* Constants */
$CFG->user_status_approved = 1;
$CFG->user_status_rejected = 2;
$CFG->user_status_pending = 3;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
?>