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
	
	public static function getForApp($lat=false,$long=false) {
		global $CFG;
		
		$return = array('user'=>array(),'sexos'=>array(),'content_cats'=>array(),'event_cats'=>array(),'hatzalah_phone'=>$CFG->hatzalah_phone,'dsi_phone'=>$CFG->dsi_phone,'one_signal_app_id'=>$CFG->one_signal_app_id);
		
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
			
			if ($lat) {
				$lat = floatval($lat);
				$long = floatval($long);
				
				db_update('site_users',User::$info['id'],array('lat'=>$lat,'long'=>$long));
			}
		}
		
		$sql = 'SELECT * FROM sexos';
		$result = db_query_array($sql);
		
		if ($result) {
			foreach ($result as $row) {
				$return['sexos'][$row['id']] = $row['name'];
			}
		}
		
		$return['content_cats'] = Content::getTopics();
		$return['event_cats'] = Events::getCats();
		
		return $return;
	}
}

?>