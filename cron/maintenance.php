#!/usr/bin/php
<?php 

$fp = explode('/',__FILE__);
array_pop($fp);
chdir(implode('/',$fp));

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

// send content notifications
$sql = 'SELECT content.title as title FROM content WHERE is_popup = "Y" AND sent != "Y"';
$result = db_query_array($sql);

if ($result) {
	foreach ($result as $item) {
		$content = array(
			"en" => $item['title']
		);
		
		$fields = array(
			'app_id' => $CFG->one_signal_app_id,
			'included_segments' => array('All'),
			'data' => array(),
			'contents' => $content
		);
		
		$fields = json_encode($fields);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8','Authorization: Basic '.$CFG->one_signal_api_key));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		
		$response = curl_exec($ch);
		
		curl_close($ch);
	}
	
	db_query('UPDATE content SET sent = "Y"');
}

// send event notifications
if (date('i') == 0) {
	$sql = 'SELECT events.title as title FROM events WHERE events.sent != "Y"';
	$result = db_query_array($sql);
	
	if ($result) {
		if (count($result) == 1) {
			$item = $result[0];
			$content = array(
				"en" => $item['title']
			);
		}
		else {
			$content = array(
				"en" => 'Hay '.count($result).' nuevos eventos.'
			);
		}
		
		$fields = array(
			'app_id' => $CFG->one_signal_app_id,
			'included_segments' => array('All'),
			'data' => array(),
			'contents' => $content
		);
		
		$fields = json_encode($fields);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8','Authorization: Basic '.$CFG->one_signal_api_key));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		
		$response = curl_exec($ch);
		
		curl_close($ch);
		
		db_query('UPDATE events SET sent = "Y"');
	}
}

?>