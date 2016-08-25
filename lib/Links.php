<?php 
class Links {
	public static function get() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = 'SELECT
				links.id,
				links.name,
				links.url,
				links.description
				FROM links
				ORDER BY links.name ASC';
		
		return db_query_array($sql);
	}
}
?>