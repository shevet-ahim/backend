<?php 
// is name Dir because Directory is a system class
class Dir {
	public static function get($cats=false) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		//GROUP_CONCAT(DISTINCT CONCAT_WS("|",directory_schedule.start,directory_schedule.end) SEPARATOR ",") AS times
		//LEFT JOIN directory_schedule ON (directory_schedule.directory_id = directory_schedule.id)
		
		$sql = 'SELECT 
				directory.id,
				directory.name,
				directory.tel,
				directory.email,
				directory.website,
				directory.address,
				directory.warn,
				directory.content,
				directory_cats.key, 
				directory_cats.name AS category,
				"directory" AS type, 
				GROUP_CONCAT(DISTINCT CONCAT_WS("|",restaurant_cats.id,restaurant_cats.name) SEPARATOR ",") AS restaurant_categories
				FROM directory
				LEFT JOIN directory_cats ON (directory.directory_cat = directory_cats.id)
				LEFT JOIN directory_restaurant_cats ON (directory_restaurant_cats.f_id = directory.id)
				LEFT JOIN restaurant_cats ON (directory_restaurant_cats.c_id = restaurant_cats.id)
				WHERE 1 ';
		
		if (is_array($cats) && count($cats) > 0) {
			$c_arr = array();
			foreach ($cats as $cat) {
				$cat = preg_replace("/[^a-zA-Z0-9]/", "",$cat);
				$c_arr[] = '"'.$cat.'"';
			}
				
			$sql .= ' AND directory_cats.key IN ('.implode(',',$c_arr).' ';
		}
		else if ($cats)
			$sql .= ' AND directory_cats.key = "'.$cats.'"';
		
		$sql .= ' AND directory.is_active = "Y" GROUP BY directory.id ORDER BY directory.name ASC';
		return db_query_array($sql);
	}
}
?>