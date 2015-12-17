<?php
class Content{
	public static function get($cats=false,$topic=false,$age=false,$sex=false,$per_page=false,$start_date=false,$end_date=false,$last_id=false,$first_id=false){
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$start_date = preg_replace("/[^0-9]/", "",$start_date);
		$end_date = preg_replace("/[^0-9]/", "",$end_date);
		$last_id = preg_replace("/[^0-9]/", "",$last_id);
		$first_id = preg_replace("/[^0-9]/", "",$first_id);
		$age = preg_replace("/[^0-9]/", "",$age);
		$sex = preg_replace("/[^0-9]/", "",$sex);
		$topic = preg_replace("/[^0-9]/", "",$topic);
		
		if (strlen((string)$start_date) > 10)
			$start_date = floor($start_date/1000);
		if (strlen((string)$end_date) > 10)
			$end_date = floor($end_date/1000);
		if (!$start_date)
			$start_date = time();
		if (!$end_date)
			$end_date = strtotime('+ '.$CFG->events_days_ahead.' days',$start_date);
		
		$sql = 'SELECT 
				content.id, 
				content.title,
				content.abstract,  
				content.content, 
				content.date, 
				content_cats.key, 
				content_cats.name AS category, 
				GROUP_CONCAT(DISTINCT CONCAT_WS("|",torah_types.id,torah_types.name) SEPARATOR ",") AS topics,
				sexos.name AS sexo,
				content.site_users,
				CONCAT_WS(" ",authors.first_name,authors.last_name) AS author_name,
				authors.email AS author_email,
				content.is_popup
				FROM content
				LEFT JOIN content_cats ON (content_cats.id = content.content_cat)
				LEFT JOIN content_torah_types ON (content_torah_types.f_id = content.id)
				LEFT JOIN torah_types ON (content_torah_types.c_id = torah_types.id)
				LEFT JOIN content_age_groups ON (content_age_groups.f_id = content.id)
				LEFT JOIN age_groups ON (content_age_groups.c_id = age_groups.id)
				LEFT JOIN sexos ON (sexos.id = content.sexo)
				LEFT JOIN authors ON (authors.id = content.author)
				WHERE 1 ';
		
		if (is_array($topic) && count($topic) > 0) {
			$t_arr = array();
			foreach ($topic as $t) {
				$topic = preg_replace("/[^a-zA-Z0-9]/", "",$t);
				$t_arr[] = '"'.$t.'"';
			}
				
			$sql .= ' AND content_cats.key IN ('.implode(',',$c_arr).') ';
		}
		else if ($cats)
			$sql .= ' AND content_cats.key = "'.$cat.'"';
		
		if ($topic > 0)
			$sql .= ' AND torah_types = '.$topic.' ';
		if ($start_date > 0)
			$sql .= ' AND (DATE(content.date) >= "'.date('Y-m-d',$start_date).'" AND DATE(content.date) <= "'.date('Y-m-d',$end_date).'") ';
		if ($age > 0)
			$sql .= ' AND ((age_groups.min >= '.$age.' AND age_groups.max <= '.$age.') OR age_groups.min IS NULL) ';
		if ($sex > 0)
			$sql .= ' AND (content.sexo = '.$sex.' OR content.sexo = 0) ';
		if ($last_id > 0)
			$sql .= ' AND content.id > '.$last_id.' ';
		if ($first_id > 0)
			$sql .= ' AND content.id < '.$first_id.' ';
		
		$sql .= ' AND ((content.site_users LIKE "%{%" AND content.site_users LIKE "%'.User::$info['first_name'].' '.User::$info['last_name'].'%") OR content.site_users NOT LIKE "%{%")';
		$sql .= ' AND is_active = "Y" GROUP BY content.id ORDER BY content.date ASC';
		
		if ($per_page > 0)
			$sql .= 'LIMIT 0,'.$per_page;
		
		return db_query_array($sql);
	}
	
	public static function getRecord($url){
		global $CFG;
		
		$url = preg_replace("/[^0-9a-zA-Z!@#$%&*?\.\-_]/", "",$url);
		
		if(empty($url))
			return false;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('content_'.$url.'_'.$CFG->language);
			if ($cached) {
				return $cached;
			}
		}
			
		$sql = "SELECT * FROM content WHERE url = '$url' ";
		$result = db_query_array($sql);
		
		if ($result) {
			$result[0]['title'] = (empty($result[0]['title_'.$CFG->language])) ? $result[0]['title']: $result[0]['title_'.$CFG->language];
			$result[0]['content'] = (empty($result[0]['content_'.$CFG->language])) ? $result[0]['content']: $result[0]['content_'.$CFG->language];
			$result[0]['title'] = str_replace('[exchange_name]',$CFG->exchange_name,str_replace('[baseurl]',$CFG->frontend_baseurl,$result[0]['title']));
			$result[0]['content'] = str_replace('[exchange_name]',$CFG->exchange_name,str_replace('[baseurl]',$CFG->frontend_baseurl,$result[0]['content']));
			
			if ($CFG->memcached)
				$CFG->m->set('content_'.$url.'_'.$CFG->language,$result[0],300);
			
			return $result[0];
		}
		return false;				
	}
	
}
?>