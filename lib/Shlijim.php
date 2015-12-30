<?php 
class Shlijim {
	public static function get() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = 'SELECT
				shlijim.id,
				CONCAT_WS(" ",shlijim.first_name,shlijim.last_name) AS name,
				shlijim.passport,
				shlijim.motivo,
				shlijim.num_estudiantes,
				shlijim.comments AS content,
				shlijim.warn,
				iso_countries.name AS country,
				IFNULL(shlijim_status.key,"unknown") AS status
				FROM shlijim
				LEFT JOIN iso_countries ON (shlijim.country = iso_countries.id)
				LEFT JOIN shlijim_visit_reg ON (shlijim_visit_reg.shlijim_id = shlijim.id AND shlijim_visit_reg.date_start <= CURDATE() AND shlijim_visit_reg.date_end >= CURDATE())
				LEFT JOIN shlijim_status ON (shlijim_visit_reg.status = shlijim_status.id)
				GROUP BY shlijim.id ORDER BY shlijim.last_name ASC';
		
		return db_query_array($sql);
	}
}
?>