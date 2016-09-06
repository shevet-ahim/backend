#!/usr/bin/php
<?php 

$fp = explode('/',__FILE__);
array_pop($fp);
chdir(implode('/',$fp));

include '../lib/common.php';
mb_internal_encoding('UTF-8');

$kashrut_url = 'http://kasherpanama.com/';

// get products from kashrut site
$ch = curl_init($kashrut_url.'/lista-kosher/');
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,array());
curl_setopt($ch,CURLOPT_FRESH_CONNECT,TRUE);

$result = curl_exec($ch);
$html = str_get_html($result);
curl_close($ch);

$products = array();
$categories = array();

$trs = $html->find('#table_1 tr');
if ($trs) {
	foreach ($trs as $elems) {
		$tr = str_get_html($elems->innertext);
		$ths = $tr->find('th');
		if (count($ths) > 0)
			continue;
		
		$tds = $tr->find('td');
		$cat = mb_convert_case(mb_strtolower($tds[0]->plaintext), MB_CASE_TITLE, "UTF-8");
		$name = mb_convert_case(mb_strtolower($tds[2]->plaintext), MB_CASE_TITLE, "UTF-8").' - '.mb_convert_case(mb_strtolower($tds[1]->plaintext), MB_CASE_TITLE, "UTF-8");
		
		$categories[$cat] = $cat;
		$products[$name] = array('name'=>$name,'comment'=>mb_convert_case(mb_strtolower($tds[1]->plaintext), MB_CASE_TITLE, "UTF-8"),'cat'=>$cat);
	}
}

if ($categories) {
	$cat_rows = array();
	$cat_strings = array();
	
	foreach ($categories as $cat) {
		$cat_rows[] = '("'.$cat.'")';
		$cat_strings[] = '"'.$cat.'"';
	}
	
	$sql = 'INSERT INTO product_cats (name) VALUES '.implode(',',$cat_rows).' ON DUPLICATE KEY UPDATE name = VALUES(name)';
	db_query($sql);
	
	$sql = 'DELETE FROM product_cats WHERE name NOT IN ('.implode(',',$cat_strings).')';
	db_query($sql);
	
	$sql = 'SELECT id,name FROM product_cats';
	$result = db_query_array($sql);
	
	if ($result) {
		foreach ($result as $row) {
			$categories[$row['name']] = $row['id'];
		}
	}
}

if ($products) {
	$prod_rows = array();
	$rest_ids = array();
	$prod_ids = array();
	
	foreach ($products as $product) {
		$prod_rows[] = '("'.$product['name'].'","'.$product['comment'].'","'.date('Y-m-d H:i:s').'")';
		$prod_strings[] = '"'.$product['name'].'"';
	}
	
	$sql = 'INSERT INTO products (name,comment,date_updated) VALUES '.implode(',',$prod_rows).' ON DUPLICATE KEY UPDATE name = VALUES(name), comment = VALUES(comment), date_updated = VALUES(date_updated)';
	db_query($sql);
	
	
	$sql = 'DELETE FROM products WHERE name NOT IN ('.implode(',',$prod_strings).')';
	db_query($sql);
	
	$sql = 'SELECT id,name FROM products';
	$result = db_query_array($sql);
	
	if ($result) {
		foreach ($result as $row) {
			$cat_id = $categories[$products[$row['name']]['cat']];
			if (!$cat_id)
				continue;
			
			$prod_ids[] = '('.$row['id'].','.$cat_id.')';
		}
	}
	
	if ($prod_ids) {
		$sql = 'DELETE FROM products_product_cats';
		db_query($sql);
		
		$sql = 'INSERT INTO products_product_cats (f_id,c_id) VALUES '.implode(',',$prod_ids).' ';
		db_query($sql);
	}
}

// get restaurants from kashrut site
$ch = curl_init($kashrut_url.'/wp-admin/admin-ajax.php');
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,array('action'=>'get_portfolio_works','category'=>'all','html_template'=>'port_type_9','now_open_works'=>'0','tags'=>'all','thumbnail'=>'thumbnail_type_1','works_per_load'=>500));
curl_setopt($ch,CURLOPT_FRESH_CONNECT,TRUE);

$result = curl_exec($ch);
$html = str_get_html($result);
curl_close($ch);

$restaurants = array();
$categories = array();

$links = $html->find('.port_item_details');
if ($links) {
	foreach ($links as $elems) {
		$elem = str_get_html($elems->innertext);
		$link = $elem->find('a');
		$url = $link[0]->href;
		
		if (!$url)
			continue;
		
		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,array());
		curl_setopt($ch,CURLOPT_FRESH_CONNECT,TRUE);
		
		$result = curl_exec($ch);
		$html = str_get_html($result);
		curl_close($ch);
		
		$name = mb_convert_case(mb_strtolower($html->find('h2.port_details_title')[0]->plaintext), MB_CASE_TITLE, "UTF-8");
		$restaurants[$name] = array('name'=>$name);
		
		$details = $html->find('.item_details_entry div');
		if ($details) {
			foreach ($details as $div) {
				$tag = $div->find('strong');
				$label = $tag[0]->innertext;
				$tag[0]->innertext = '';
				
				if (stristr($label,'Direc')) {
					$restaurants[$name]['address'] = $div->plaintext;
				}
				else if (stristr($label,'Tel')) {
					$restaurants[$name]['tel'] = $div->plaintext;
				}
				else if (stristr($label,'Horar')) {
					$restaurants[$name]['content'] = 'Horario: '.$div->plaintext;
				}
			}
		}
		
		$details = $html->find('.port_metas .port_meta');
		if ($details) {
			foreach ($details as $div) {
				$label = str_get_html($div->innertext)->find('.port_meta_first')[0]->plaintext;
				$cat = str_get_html($div->innertext)->find('.port_meta_last')[0]->plaintext;
				
				if (stristr($label,'Tag')) {
					$categories[$cat] = $cat;
					$restaurants[$name]['cat'] = $cat;
				}
			}
		}
	}
}


if ($categories) {
	$cat_rows = array();
	$cat_strings = array();

	foreach ($categories as $cat) {
		$cat_rows[] = '("'.$cat.'")';
		$cat_strings[] = '"'.$cat.'"';
	}

	$sql = 'INSERT INTO restaurant_cats (name) VALUES '.implode(',',$cat_rows).' ON DUPLICATE KEY UPDATE name = VALUES(name)';
	db_query($sql);

	$sql = 'DELETE FROM restaurant_cats WHERE name NOT IN ('.implode(',',$cat_strings).')';
	db_query($sql);

	$sql = 'SELECT id,name FROM restaurant_cats';
	$result = db_query_array($sql);

	if ($result) {
		foreach ($result as $row) {
			$categories[$row['name']] = $row['id'];
		}
	}
}

if ($restaurants) {
	$rest_rows = array();
	$rest_strings = array();
	$rest_ids = array();

	foreach ($restaurants as $restaurant) {
		$rest_rows[] = '("'.$restaurant['name'].'","'.$restaurant['address'].'","'.$restaurant['tel'].'","'.$restaurant['content'].'","Y",(SELECT id FROM directory_cats WHERE `key` = "restaurants"))';
		$rest_strings[] = '"'.$restaurant['name'].'"';
	}

	$sql = 'INSERT INTO directory (name,address,tel,content,is_active,directory_cat) VALUES '.implode(',',$rest_rows).' ON DUPLICATE KEY UPDATE name = VALUES(name), address = VALUES(address), tel = VALUES(tel), content = VALUES(content), directory_cat = VALUES(directory_cat)';
	db_query($sql);


	$sql = 'DELETE FROM directory WHERE name NOT IN ('.implode(',',$rest_strings).') AND directory_cat = (SELECT id FROM directory_cats WHERE `key` = "restaurants")';
	db_query($sql);

	$sql = 'SELECT id,name FROM directory WHERE directory_cat = (SELECT id FROM directory_cats WHERE `key` = "restaurants")';
	$result = db_query_array($sql);

	if ($result) {
		foreach ($result as $row) {
			$cat_id = $categories[$restaurants[$row['name']]['cat']];
			if (!$cat_id)
				continue;
				
			$rest_ids[] = '('.$row['id'].','.$cat_id.')';
		}
	}

	if ($rest_ids) {
		$sql = 'DELETE FROM directory_restaurant_cats';
		db_query($sql);

		$sql = 'INSERT INTO directory_restaurant_cats (f_id,c_id) VALUES '.implode(',',$rest_ids).' ';
		db_query($sql);
	}
}

?>