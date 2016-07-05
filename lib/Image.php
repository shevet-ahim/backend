<?php 
class Image {
	public static function show($filename) {
		global $CFG;
		
		header('Content-Type: image/jpeg');
		readfile($CFG->backstage_baseurl.'uploads/'.$filename);
	}
}
?>
