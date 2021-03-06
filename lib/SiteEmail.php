<?php
class SiteEmail{
	public static function getRecord($key){
		global $CFG;
		
		$key = preg_replace("/[^0-9a-zA-Z!@#$%&*?\.\-_]/", "",$key);
		
		if (empty($key))
			return false;	
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('email_'.$key);
			if ($cached) {
				return $cached;
			}
		}
			
		$sql="SELECT * FROM emails WHERE emails.key='$key' ";	
		$result = db_query_array($sql);
		
		$result[0]['title'] = str_replace('[app_name]',$CFG->app_name,$result[0]['title']);
		$result[0]['content'] = str_replace('[app_name]',$CFG->app_name,$result[0]['content']);
		$result[0]['title'] = str_replace('[baseurl]',$CFG->baseurl,$result[0]['title']);
		$result[0]['content'] = str_replace('[baseurl]',$CFG->baseurl,$result[0]['content']);
		
		if ($CFG->memcached)
			$CFG->m->set('email_'.$key,$result[0],300);
		
		return $result[0];
	}
	
	public static function getCountry($country_id) {
		return DB::getRecord('iso_countries',$country_id,0,1);
	}
	
	public static function contactForm($contact_info) {
		global $CFG;
		
		$email = SiteEmail::getRecord('contact');
		$pais = SiteEmail::getCountry($contact_info['country']);
		$contact_info['country'] = $pais['name'];

		if (User::$info['id'] > 0)
			$contact_info['user_id'] = User::$info['id'];
		
		return Email::send($contact_info['email'],$CFG->contact_email,$email['title'],$CFG->form_email_from,false,$email['content'],$contact_info);
	}
}
?>