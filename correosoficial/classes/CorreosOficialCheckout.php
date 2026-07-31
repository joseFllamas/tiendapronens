<?php
use CorreosOficial\Classes\CorreosOficialUtils;


class CorreosOficialCheckout {


	public static function insertReferenceCode( $id_order, $id_cart, $reference_code, $data ) {

		global $wpdb;

		$table = $wpdb->prefix . 'correos_oficial_requests';
		$order_exists = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE id_order = %d', $table, $id_order));
		$date = gmdate('Y-m-d h:i:s');

		$data = CorreosOficialUtils::replaceUnicodeCharacters($data);
		$data = str_replace('\\', '', $data);

		if (!$order_exists) {
			$wpdb->query($wpdb->prepare('INSERT INTO %i (id_cart, reference_code, data, date, id_order) VALUES (%s, %s, %s, %s, %d)',
				$table, $id_cart, $reference_code, $data, $date, $id_order));
		} else {
			$wpdb->query($wpdb->prepare('UPDATE %i SET id_cart = %s, reference_code = %s, data = %s, date = %s WHERE id_order = %d',
				$table, $id_cart, $reference_code, $data, $date, $id_order));
		}
	}
}
