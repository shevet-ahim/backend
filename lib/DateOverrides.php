<?php
class DateOverrides {
	public static function get() {
		$sql = 'SELECT * FROM date_overrides WHERE `date` >= "'.date('Y-m-d',strtotime('-1 month')).'" AND `date` <= "'.date('Y-m-d',strtotime('+1 month')).'" ORDER BY `date` DESC';
		$result = db_query_array($sql);
		
		if (!$result)
			return false;
		
		$return = array();
		foreach ($result as $row) {
			
			$return[$row['date']] = array(
				'neitz_hachama'=>strtotime($row['date'].' '.substr($row['netz'],strrpos($row['netz'],' ') + 1)) * 1000,
				'sof_zman_shma'=>strtotime($row['date'].' '.substr($row['shema'],strrpos($row['shema'],' ') + 1)) * 1000,
				'sof_zman_tfilla'=>strtotime($row['date'].' '.substr($row['tefilah'],strrpos($row['tefilah'],' ') + 1)) * 1000,
				'mincha_gedola'=>strtotime($row['date'].' '.substr($row['minha_gedola'],strrpos($row['minha_gedola'],' ') + 1)) * 1000,
				'mincha_ketana'=>strtotime($row['date'].' '.substr($row['minha_ketana'],strrpos($row['minha_ketana'],' ') + 1)) * 1000,
				'shkiah'=>strtotime($row['date'].' '.substr($row['shekia'],strrpos($row['shekia'],' ') + 1)) * 1000,
				'tzeit'=>strtotime($row['date'].' '.substr($row['tzet'],strrpos($row['tzet'],' ') + 1)) * 1000,
				'candles'=>(($row['candles'] > 0) ? strtotime($row['date'].' '.substr($row['candles'],strrpos($row['candles'],' ') + 1)) * 1000 : 0));
		}
		
		return $return;
	}
}
?>
