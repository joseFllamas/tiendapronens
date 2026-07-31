<?php

namespace CorreosOficial\Models;

use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialProductDataStore extends WC_Data_Store_WP {

	protected $table_name;
	protected $carrier_products_table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'correos_oficial_products';
		$this->carrier_products_table_name = $wpdb->prefix . 'correos_oficial_carriers_products';
	}

	public function read( &$product ) {
		global $wpdb;
		$data = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . esc_sql($this->table_name) . ' WHERE id = %d', $product->get_id()), ARRAY_A);
		if ($data) {
			$product->set_props($data);
			$product->set_object_read(true);
		}
	}

	public function get_product_by_carrier( $carrier_id ) {
		global $wpdb;
		$id_product = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id_product FROM ' . esc_sql($this->carrier_products_table_name) . ' WHERE id_carrier = %d LIMIT 1',
				$carrier_id
			)
		);

		if ( $id_product ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . esc_sql($this->table_name) . ' WHERE id = %d',
					$id_product
				),
				ARRAY_A
			);
		}

		return null;
	}

	public function get_all_products() {
		global $wpdb;

		$products = $wpdb->get_results( 'SELECT * FROM ' . esc_sql( $this->table_name ), ARRAY_A );

		if ( $products ) {
			return $products;
		}
	}
}
