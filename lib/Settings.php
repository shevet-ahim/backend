<?php
class Settings {
	public static function assign() {
		global $CFG;
		
		$all = self::get();
		if (is_array($all) && is_object($CFG)) {
			foreach ($all as $name => $value) {
				if (!strstr($name,'_api'))
					$name = str_replace('api_','',$name);
				
				$CFG->$name = $value;
			}
		}
	}
	
	public static function get() {
		global $CFG;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('settings');
			if ($cached) {
				return $cached;
			}
		}
		
		$sql = 'SELECT * FROM app_configuration WHERE id = 1';
		$result = db_query_array($sql);
		
		if ($CFG->memcached)
			$CFG->m->set('settings',$result[0],300);
		
		return $result[0];
	}
	
	public static function getForApp() {
		global $CFG;
		
		$return = array('user'=>array(),'sexos'=>array());
		
		if (!empty(User::$info)) {
			$return['user'] = User::$info;
			$status = User::getUserStatus();
			$return['user']['status'] = $status[User::$info['site_users_status']]['key'];
			
			unset($return['user']['nonce']);
			unset($return['user']['session_key']);
			unset($return['user']['ip']);
			unset($return['user']['awaiting']);
			unset($return['user']['id']);
			unset($return['user']['reg_date']);
			unset($return['user']['last_login']);
			unset($return['user']['user']);
			unset($return['user']['pass']);
			unset($return['user']['site_users_status']);
		}
		
		$sql = 'SELECT * FROM sexos';
		$result = db_query_array($sql);
		
		if ($result) {
			foreach ($result as $row) {
				$return['sexos'][$row['id']] = $row['name'];
			}
		}
		
		return $return;
	}
}

?>