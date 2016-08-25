#!/usr/bin/php
<?php 
include '../lib/common.php';

// send welcome email to approved users
$sql = 'SELECT su.id, su.email, su.first_name FROM site_users su LEFT JOIN site_users_status st ON (su.site_users_status = st.id) WHERE st.key = "approved" AND su.notified != "Y" ';
$result = db_query_array($sql);
if ($result) {
	foreach ($result as $row) {
		$info = array();
		$info['first_name'] = $row['first_name'];
		$email = SiteEmail::getRecord('usuario-aprobado');
		Email::send($CFG->contact_email,$row['email'],$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$info);
		db_update('site_users',$row['id'],array('notified'=>'Y'));
	}
}

// increase people's age each year (no, I'm not stupid, precise age is not needed for anything here)
$m1 = date('Y',time());
$m2 = date('Y',strtotime('-6 minute'));
if ($m1 != $m2) {
	$sql = 'UPDATE site_users SET age = age + 1 WHERE age > 0';
	db_query($sql);
}

?>