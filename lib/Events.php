<?php 
class Events{
	public static function get($in_feed=false,$cats=false,$id=false,$recurrence=false,$age=false,$sex=false,$per_page=false,$start_date=false,$end_date=false,$day_he=false,$month_he=false) {
		global $CFG;
		/*
		if (!$CFG->session_active)
			return false;
		*/
		$id = preg_replace("/[^0-9]/", "",$id);
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$start_date = preg_replace("/[^0-9]/", "",$start_date);
		$end_date = preg_replace("/[^0-9]/", "",$end_date);
		$month_he = preg_replace("/[^a-zA-Z0-9]/", "",$month_he);
		$day_he = preg_replace("/[^0-9]/", "",$day_he);
		$day_he = ($day_he > 0) ? $day_he : '0';
		$recurrence = preg_replace("/[^a-zA-Z]/", "",$recurrence);
		$age = preg_replace("/[^0-9]/", "",$age);
		$sex = preg_replace("/[^0-9]/", "",$sex);
		$days_of_week = array();
		
		if (strlen((string)$start_date) > 10)
			$start_date = floor($start_date/1000);
		if (strlen((string)$end_date) > 10)
			$end_date = floor($end_date/1000);
		/*
		if (!$start_date)
			$start_date = time();
		*/
		if (!$start_date)
			$start_date = strtotime('- 30 days',time());
		if (!$end_date)
			$end_date = strtotime('+ '.$CFG->events_days_ahead.' days',time());
		if ($start_date)
			$start_heb = self::getJewishDate($start_date);
		if ($end_date)
			$end_heb = self::getJewishDate($end_date);
		
		if ($start_date && $end_date) {
			$dw1 = date('w',$start_date);
			$dw2 = date('w',$end_date);
			$day_diff = floor(($end_date - $start_date)/(60*60*24));
			
			if ($day_diff >= 7)
				$days_of_week = range(0,6);
			else if ($dw1 < $dw2)
				$days_of_week = range($dw1,$dw2);
			else if ($dw1 > $dw2)
				$days_of_week = array_merge(range($dw1,6),range(0,$dw2));
			else if ($dw1 == $dw2)
				$days_of_week = array($dw1);
		}
		
		$sql = 'SELECT 
				events.id, 
				events.title, 
				events.place, 
				events.content, 
				events.date, 
				events.time, 
				events.date_end, 
				events.day_he, 
				he_months.key AS month_he, 
				events_recurrence.key AS recurrence, 
				event_cats.key, 
				event_cats.name AS category,
				event_cats1.key AS p_key,
				event_cats1.name AS p_category,
				IF(events_recurrence.key = "recurrent",event_cats.show_in_feed_rec,event_cats.show_in_feed) AS show_in_feed, 
				sexos.name AS sexo,
				CONCAT_WS(" ",authors.first_name,authors.last_name) AS author_name,
				CONCAT("authors","_",authors_files.f_id,"_",authors_files.id,"_small.",authors_files.ext) AS author_img,
				GROUP_CONCAT(DISTINCT days.key SEPARATOR ",") AS weekdays,
				"event" AS `type`,
				CONCAT("events","_",events_files.f_id,"_",events_files.id,"_large.",events_files.ext) AS img
				FROM events
				LEFT JOIN event_cats ON (event_cats.id = events.event_cat)
				LEFT JOIN event_cats event_cats1 ON (event_cats.p_id = event_cats1.id)
				LEFT JOIN he_months ON (he_months.id = events.month_he)
				LEFT JOIN events_recurrence ON (events_recurrence.id = events.recurrence)
				LEFT JOIN events_days ON (events_days.f_id = events.id)
				LEFT JOIN days ON (events_days.c_id = days.id)
				LEFT JOIN events_age_groups ON (events_age_groups.f_id = events.id)
				LEFT JOIN age_groups ON (events_age_groups.c_id = age_groups.id)
				LEFT JOIN sexos ON (sexos.id = events.sexo)
				LEFT JOIN authors ON (authors.id = events.author)
				LEFT JOIN authors_files ON (authors.id = authors_files.f_id)
				LEFT JOIN events_files ON (events.id = events_files.f_id)
				WHERE 1 ';
		
		if (is_array($cats) && count($cats) > 0) {
			$c_arr = array();
			foreach ($cats as $cat) {
				$cat = preg_replace("/[^a-zA-Z0-9\-\_]/", "",$cat);
				$c_arr[] = '"'.$cat.'"';
			}
			
			$sql .= ' AND (event_cats.key IN ('.implode(',',$c_arr).') OR event_cats1.key IN ('.implode(',',$c_arr).')) ';
		}
		else if ($cats) {
			$cats = preg_replace("/[^a-zA-Z0-9\-\_]/", "",$cats);
			$sql .= ' AND (event_cats.key = "'.$cats.'" OR event_cats1.key = "'.$cats.'")';
		}
		
		if ($start_date > 0 && $end_date > 0)
			$sql .= ' AND ((events_recurrence.key = "specific_greg" AND DATE(events.date) >= "'.date('Y-m-d',$start_date).'" AND DATE(events.date) <= "'.date('Y-m-d',$end_date).'") OR (events_recurrence.key = "specific_heb" AND ((month_he = '.$start_heb[1].' AND day_he >= '.$start_heb[0].') OR (month_he = '.$end_heb[1].' AND day_he <= '.$end_heb[0].'))) OR (events_recurrence.key = "recurrent" '.((count($days_of_week) > 0) ? 'AND days.key IN ('.implode(',',$days_of_week).')' : '').')) ';
		if ($day_he > 0 && $month_he > 0)
			$sql .= ' AND (he_months.key = "'.$month_he.'" AND day_he = '.$day_he.') ';
		if ($age > 0)
			$sql .= ' AND ((age_groups.min <= '.$age.' AND age_groups.max >= '.$age.') OR age_groups.min IS NULL) ';
		if ($sex > 0)
			$sql .= ' AND (events.sexo = '.$sex.' OR events.sexo = 0) ';
		if ($in_feed)
			$sql .= ' AND event_cats.show_in_feed = "Y" ';
		
		if ($recurrence)
			$sql .= ' AND events_recurrence.key = "'.$recurrence.'" ';
		else if ($in_feed)
			$sql .= ' AND events_recurrence.key != "recurrent" ';
		
		$sql .= ' AND (DATE(date_end) >= "'.date('Y-m-d').'" OR DATE(date_end) < "1980-01-01") AND is_active = "Y" GROUP BY events.id ORDER BY events.date ASC';
		
		if ($per_page > 0)
			$sql .= 'LIMIT 0,'.$per_page;
		//error_log(print_r(array($sql),1),3,ini_get('error_log'));
		$result = db_query_array($sql);
		if ($id > 0 && $result)
			return $result[0];
		else
			return $result;
	}
	
	public static function getJewishDate($timestamp) {
		$d = jdtojewish(gregoriantojd(date('n',$timestamp),date('j',$timestamp),date('Y',$timestamp)),false,CAL_JEWISH_ADD_GERESHAYIM + CAL_JEWISH_ADD_ALAFIM + CAL_JEWISH_ADD_ALAFIM_GERESH);
		list($month_name,$day,$year) = explode('/', $d);
		
		$leap = ($year % 19 == 0 || $year % 19 == 3 || $year % 19 == 6 || $year % 19 == 8 || $year % 19 == 11 || $year % 19 == 14 || $year % 19 == 17);
		$months = array("Tishri"=>1,"Heshvan"=>2,"Kislev"=>3,"Tevet"=>4,"Shevat"=>5,"Adar"=>6,"Adar I"=>6,"Adar II"=>7,"Nisan"=>8,"Iyar"=>9,"Sivan"=>10,"Tammuz"=>11,"Av"=>12,"Elul"=>13);
		
		if (is_numeric($month_name)) {
			$month = $month_name;
			if (!$leap && $month == 7)
				$month == 6;
		}
		else
			$month = $months[$month_name];
		
		return array($day,$month,$year,$leap);
	}
	
	public static function getCats(){
		global $CFG;
	
		$sql = 'SELECT * FROM event_cats ORDER BY name ASC';
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
