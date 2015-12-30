<?php 
class Products {
	public static function get() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = 'SELECT
				products.id,
				products.name,
				products.supervision,
				products.warn,
				products.date_updated,
				GROUP_CONCAT(DISTINCT CONCAT_WS("|",product_cats.id,product_cats.name) SEPARATOR ",") AS categories
				FROM products
				LEFT JOIN products_product_cats ON (products_product_cats.f_id = products.id)
				LEFT JOIN product_cats ON (products_product_cats.c_id = product_cats.id)
				GROUP BY products.id ORDER BY products.name ASC';
		
		return db_query_array($sql);
	}
}
?>