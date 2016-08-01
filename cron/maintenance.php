#!/usr/bin/php
<?php 
include '../lib/common.php';

$sql = 'SELECT su.id, su.email, su.first_name FROM site_users su LEFT JOIN site_users_status st ON (su.site_users_status = st.id) WHERE st.key = "approved" AND su.notified != "Y" ';
$result = db_query_array($sql);

if ($result) {
	foreach ($result as $row) {
		$info = array();
		$info['first_name'] = $row['first_name'];
		$email = SiteEmail::getRecord('usuario-aprobado');
		print_r($row);
		print_r($email);
		Email::send($CFG->contact_email,$row['email'],$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$info);
		db_update('site_users',$row['id'],array('notified'=>'Y'));
	}
}
?>