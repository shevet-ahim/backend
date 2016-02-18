<?php 
class User {
	public static $info;
	
	public static function signup($info) {
		global $CFG;
	
		if (!is_array($info))
			return false;
		
		$status = false;
		$errors = array();
		$error_fields = array();
		
		$info['email'] = preg_replace('/[^0-9a-zA-Z@\.\!#\$%\&\*+_\~\?\-]/','',$info['email']);
		$info['pass'] = preg_replace($CFG->pass_regex,'',$info['pass']);
		$info['first_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u", "",$info['first_name']);
		$info['last_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u", "",$info['last_name']);
		
		$exist_id = self::userExists($info['email']);
		if ($exist_id > 0) {
			$errors[] = 'Ese usuario ya existe.';
			$error_fields[] = 'email';
		}
		if (empty($info['email']) || !Email::verifyAddress($info['email'])) {
			$errors[] = 'Email no válido.';
			$error_fields[] = 'email';
		}
		if (!empty($info['pass']) && $info['pass'] != $info['pass1']) {
			$errors[] = 'La contraseña no es idéntica a su verificación.';
			$error_fields[] = 'pass';
			$error_fields[] = 'pass1';
		}
		if (empty($info['pass']) || mb_strlen($info['pass'],'utf-8') < $CFG->pass_min_chars) {
			$errors[] = 'Su contraseña debe tener más de '.$CFG->pass_min_chars.' caracteres.';
			$error_fields[] = 'pass';
		}
		if (empty($info['first_name'])) {
			$errors[] = 'Por favor ingrese su nombre.';
			$error_fields[] = 'first_name';
		}
		if (empty($info['last_name'])) {
			$errors[] = 'Por favor ingrese su apellido.';
			$error_fields[] = 'last_name';
		}
		
		if (count($errors) > 0)
			return array('errors'=>$errors,'error_fields'=>$error_fields);
			
		$pass1 = $info['pass'];
		$info['pass'] = Encryption::hash($info['pass']);
		$info['reg_date'] = date('Y-m-d');
		unset($info['pass1']);
		
		if ($CFG->new_user_require_approval == 'Y') {
			$info['site_users_status'] = $CFG->user_status_pending;
			$status = 'pending';
		}
		else {
			$info['site_users_status'] = $CFG->user_status_approved;
			$status = 'approved';
		}
				
		$record_id = db_insert('site_users',$info);
		$info['pass'] = $pass1;
		$email = SiteEmail::getRecord('register');
		Email::send($CFG->contact_email,$info['email'],$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$info);

		if ($CFG->email_notify_new_users) {
			$email = SiteEmail::getRecord('register-notify');
			$info['pass'] = false;
			Email::send($CFG->contact_email,$CFG->contact_email,$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$info);
		}
		return $status;	
	}
	
	public static function login($info) {
		global $CFG;
		
		if (!is_array($info))
			return false;
		
		$status = false;
		$errors = array();
		$error_fields = array();
		$invalid_login = false;
		
		$email1 = preg_replace("/[^0-9a-zA-Z@\.\!#\$%\&\*+_\~\?\-]/","",$info['email']);
		$pass1 = preg_replace($CFG->pass_regex,"",$info['pass']);
		$ip1 = self::getUserIp();
		
		if (!$email1) {
			$errors[] = 'Email en blanco!';
			$error_fields[] = 'user';
		}
			
		if (!$pass1) {
			$errors[] = 'Contraseña en blanco!';
			$error_fields[] = 'pass';
		}
		
		$result = db_query_array("SELECT site_users.*, site_users_status.key AS status, site_users_access.start AS `start`, site_users_access.last AS `last`, site_users_access.attempts AS attempts FROM site_users LEFT JOIN site_users_status ON (site_users.site_users_status = site_users_status.id) LEFT JOIN site_users_access ON (site_users_access.site_user = site_users.id) WHERE site_users.user = '$email1'");
		if (!$result) {
			if (mb_strlen($email1) > 2) {
				if ($ip_int) {
					$timeframe = 15;
					$sql = 'SELECT COUNT(1) AS login_attempts FROM ip_access_log WHERE login = "Y" AND `timestamp` > DATE_SUB("'.date('Y-m-d H:i:s').'", INTERVAL '.$timeframe.' MINUTE) AND ip = '.$ip_int;
					$result = db_query_array($sql);
						
					if ($result)
						$attempts = $result[0]['login_attempts'] + 1;
				}
		
				$result = db_query_array("SELECT attempts FROM site_users_catch WHERE site_user = '$email1'");
				if ($result) {
					$attempts = ($result[0]['attempts'] + 1 > $attempts) ? $result[0]['attempts'] + 1 : $attempts;
					$timeout = pow(2,$attempts);
					$timeout_next = pow(2,$attempts + 1);
					db_update('site_users_catch',$email1,array('attempts'=>($result[0]['attempts'] + 1)),'site_user');
				}
				else
					db_insert('site_users_catch',array('attempts'=>'1','site_user'=>$email1));
			}
		
			$invalid_login = 1;
		}
		elseif ($result) {
			if (empty($result[0]['start']) || ($result[0]['start'] - time() >= 3600)) {
				$attempts = 1;
				if ($result[0]['start'])
					db_update('site_users_access',$result[0]['id'],array('attempts'=>'1','start'=>time(),'last'=>time()),'site_user');
				else
					db_insert('site_users_access',array('attempts'=>'1','start'=>time(),'last'=>time(),'site_user'=>$result[0]['id']));
			}
			else {
				$attempts = $result[0]['attempts'] + 1;
				$timeout = pow(2,$attempts);
				$timeout_next = pow(2,$attempts + 1);
		
				if ($attempts == 3) {
					$CFG->language = ($result[0]['last_lang']) ? $result[0]['last_lang'] : 'en';
					$email = SiteEmail::getRecord('bruteforce-notify');
					Email::send($CFG->support_email,$result[0]['email'],$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$result[0]);
				}
		
				db_update('site_users_access',$result[0]['id'],array('attempts'=>$attempts,'last'=>time()),'site_user');
		
				if ((time() - $result[0]['last']) <= $timeout)
					$invalid_login = 1;
		
			}
		
			if (!$invalid_login)
				$invalid_login = (!Encryption::verify_hash($pass1,$result[0]['pass']));
		}
		
		
		if ($invalid_login) {
			db_insert('ip_access_log',array('ip'=>$ip_int,'timestamp'=>date('Y-m-d H:i:s'),'login'=>'Y'));
			if (count($errors) > 0)
				return array('errors'=>$errors,'error_fields'=>$error_fields,'attempts'=>0,'timeout'=>0);
			else
				return array('errors'=>array('Login inválido.'),'error_fields'=>array('user','pass'),'attempts'=>$attempts,'timeout'=>$timeout_next);
			
			exit;
		}
		
		$nonce = time() * 1000;
		$iv = bin2hex(mcrypt_create_iv('32',MCRYPT_DEV_RANDOM));
		
		$session_id = db_insert('sessions',array('session_key'=>$iv,'user_id'=>$result[0]['id'],'nonce'=>$nonce,'session_time'=>date('Y-m-d H:i:s'),'session_start'=>date('Y-m-d H:i:s'),'ip'=>$ip1));
		$return = array();
		$return['session_id'] = $session_id;
		$return['session_key'] = $iv;
		$return['status'] = $result[0]['status'];
		$return['has_children'] = $result[0]['has_children'];
		$return['push_notifications'] = $result[0]['push_notifications'];
		$return['age'] = $result[0]['age'];
		$return['sex'] = $result[0]['sex'];
		
		db_delete('site_users_access',$result[0]['id'],'site_user');
		return $return;
	}
	
	public static function saveSettings($info) {
		global $CFG;

		if (!$CFG->session_active || !is_array($info))
			return false;
		
		$status = false;
		$errors = array();
		$error_fields = array();
		$invalid_login = false;
		error_log(print_r($info,1),3,ini_get('error_log'));
		$info['email'] = preg_replace("/[^0-9a-zA-Z@\.\!#\$%\&\*+_\~\?\-]/","",$info['email']);
		$info['first_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u", "",$info['first_name']);
		$info['last_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u", "",$info['last_name']);
		$info['age'] = preg_replace("/[^0-9]/", "",$info['age']);
		$info['sex'] = preg_replace("/[^0-9]/", "",$info['sex']);
		$info['has_children'] = !empty($info['has_children']);
		$info['push_notifications'] = !empty($info['push_notifications']);
		
		if (empty($info['email']) || !Email::verifyAddress($info['email'])) {
			$errors[] = 'Email no válido.';
			$error_fields[] = 'email';
		}
		if (empty($info['first_name'])) {
			$errors[] = 'Por favor ingrese su nombre.';
			$error_fields[] = 'first_name';
		}
		if (empty($info['last_name'])) {
			$errors[] = 'Por favor ingrese su apellido.';
			$error_fields[] = 'last_name';
		}
		
		if (count($errors) > 0)
			return array('errors'=>$errors,'error_fields'=>$error_fields);
		
		
		db_update('site_users',User::$info['id'],$info);
		$email = SiteEmail::getRecord('update-settings');
		Email::send($CFG->contact_email,$info['email'],$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$info);

		return 'ok';
	}
	
	public static function savePassword($info) {
		global $CFG;
	
		if (!$CFG->session_active || !is_array($info))
			return false;
	
		$status = false;
		$errors = array();
		$error_fields = array();
		$invalid_login = false;
		
		$info['pass'] = preg_replace($CFG->pass_regex,'',$info['pass']);
		$info['pass1'] = preg_replace($CFG->pass_regex,'',$info['pass1']);
		$info['current_pass'] = preg_replace($CFG->pass_regex,'',$info['current_pass']);
		
		$invalid_pass = (!Encryption::verify_hash($info['current_pass'],User::$info['pass']));
		if ($invalid_pass) {
			$errors[] = 'Su contraseña actual no es la correcta.';
			$error_fields[] = 'current_pass';
		}
		if (!empty($info['pass']) && $info['pass'] != $info['pass1']) {
			$errors[] = 'La contraseña no es idéntica a su verificación.';
			$error_fields[] = 'pass';
			$error_fields[] = 'pass1';
		}
		if (empty($info['pass']) || mb_strlen($info['pass'],'utf-8') < $CFG->pass_min_chars) {
			$errors[] = 'Su contraseña debe tener más de '.$CFG->pass_min_chars.' caracteres.';
			$error_fields[] = 'pass';
		}
	
		if (count($errors) > 0)
			return array('errors'=>$errors,'error_fields'=>$error_fields);
	
	
		db_update('site_users',User::$info['id'],array('pass'=>Encryption::hash($info['pass'])));
		$email = SiteEmail::getRecord('update-password');
		Email::send($CFG->contact_email,$info['email'],$email['title'],$CFG->email_smtp_send_from,false,$email['content'],$info);
	
		return 'ok';
	}
	
	public static function logOut($session_id=false) {
		if (!($session_id > 0))
			return false;
	
		$session_id = preg_replace("/[^0-9]/", "",$session_id);
	
		//self::deleteCache();
		return db_delete('sessions',$session_id,'session_id');
	}
	
	public static function userExists($email) {
		$email = preg_replace("/[^0-9a-zA-Z@\.\!#\$%\&\*+_\~\?\-]/", "",$email);
	
		if (!$email)
			return false;
	
		$sql = "SELECT id FROM site_users WHERE email = '$email'";
		$result = db_query_array($sql);
	
		if ($result)
			return $result[0]['id'];
		else
			return false;
	}
	
	static public function getUserIp() {
		$ip_addresses = array();
		$ip_elements = array(
			'HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED_FOR',
			'HTTP_X_FORWARDED', 'HTTP_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_CLUSTER_CLIENT_IP',
			'HTTP_X_CLIENT_IP', 'HTTP_CLIENT_IP',
			'REMOTE_ADDR'
		);
	
		foreach ( $ip_elements as $element ) {
			if(isset($_SERVER[$element])) {
				if (!is_string($_SERVER[$element]) )
					continue;
	
				$address_list = explode(',',$_SERVER[$element]);
				$address_list = array_map('trim',$address_list);
	
				foreach ($address_list as $x)
					$ip_addresses[] = $x;
			}
		}
	
		if (count($ip_addresses) == 0)
			return false;
		else
			return $ip_addresses[0];
	}
	
	public static function setInfo($info) {
		User::$info = $info;
	}
}
?>