<?php
class Content{
	public static function get($cats=false,$topic=false,$age=false,$sex=false,$per_page=false,$start_date=false,$end_date=false){
		global $CFG;
		/*
		if (!$CFG->session_active)
			return false;
		*/
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$start_date = preg_replace("/[^0-9]/", "",$start_date);
		$end_date = preg_replace("/[^0-9]/", "",$end_date);
		$age = preg_replace("/[^0-9]/", "",$age);
		$sex = preg_replace("/[^0-9]/", "",$sex);
		$topic = preg_replace("/[^0-9]/", "",$topic);
		
		if (strlen((string)$start_date) > 10)
			$start_date = floor($start_date/1000);
		if (strlen((string)$end_date) > 10)
			$end_date = floor($end_date/1000);
		if (!$end_date)
			$end_date = time();
		if (!$start_date)
			$start_date = strtotime('- 30 days',$end_date);
		/*
		if (!$start_date)
			$start_date = strtotime('- '.$CFG->events_days_ahead.' days',$end_date);
		*/
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
				CONCAT_WS(" ",authors.first_name,authors.last_name) AS author_name,
				CONCAT("authors","_",authors_files.f_id,"_",authors_files.id,"_small.",authors_files.ext) AS author_img,
				authors.email AS author_email,
				content.is_popup,
				"content" AS `type`,
				CONCAT("content","_",content_files.f_id,"_",content_files.id,"_large.",content_files.ext) AS img
				FROM content
				LEFT JOIN content_cats ON (content_cats.id = content.content_cat)
				LEFT JOIN content_files ON (content.id = content_files.f_id)
				LEFT JOIN content_torah_types ON (content_torah_types.f_id = content.id)
				LEFT JOIN torah_types ON (content_torah_types.c_id = torah_types.id)
				LEFT JOIN content_age_groups ON (content_age_groups.f_id = content.id)
				LEFT JOIN age_groups ON (content_age_groups.c_id = age_groups.id)
				LEFT JOIN sexos ON (sexos.id = content.sexo)
				LEFT JOIN authors ON (authors.id = content.author)
				LEFT JOIN authors_files ON (authors.id = authors_files.f_id)
				LEFT JOIN content_site_users_relations ON (content.id = content_site_users_relations.f_id)
				WHERE 1 ';
		
		if (is_array($topic) && count($topic) > 0) {
			$t_arr = array();
			foreach ($topic as $t) {
				$topic = preg_replace("/[^a-zA-Z0-9]/", "",$t);
				$t_arr[] = '"'.$t.'"';
			}
				
			$sql .= ' AND content_cats.key IN ('.implode(',',$c_arr).') ';
		}
		else if ($topic)
			$sql .= ' AND content_cats.key = "'.$topic.'"';
		
		if ($cats)
			$sql .= ' AND torah_types.id = "'.$cats.'" ';
		if ($start_date > 0)
			$sql .= ' AND (DATE(content.date) >= "'.date('Y-m-d',$start_date).'" AND DATE(content.date) <= "'.date('Y-m-d',$end_date).'") ';
		if ($age > 0)
			$sql .= ' AND ((age_groups.min >= '.$age.' AND age_groups.max <= '.$age.') OR age_groups.min IS NULL) ';
		if ($sex > 0)
			$sql .= ' AND (content.sexo = '.$sex.' OR content.sexo = 0) ';
		
		if (!empty(User::$info['id']))
			$sql .= ' AND (content_site_users_relations.value IS NULL OR content_site_users_relations.value = '.User::$info['id'].')';
		else
			$sql .= ' AND (content_site_users_relations.value IS NULL)';
		
		$sql .= ' AND content.is_active = "Y" GROUP BY content.id ORDER BY content.date ASC';
		
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
	
	public static function getTopics(){
		global $CFG;
		
		$sql = 'SELECT * FROM torah_types ORDER BY name ASC';
		$result = db_query_array($sql);
		$return = array();
		
		if ($result) {
			foreach ($result as $row) {
				if ($row['p_id'] == 0)
					$return[$row['id']] = $row;
				else
					$return[$row['p_id']]['children'][] = $row;
			}
		}
		
		return $return;
	}
}
?>