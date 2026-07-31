<?php
namespace CorreosOficial\Models;

use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialSavedOrderDataStore extends WC_Data_Store_WP {

	private $table;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_saved_orders';
	}

	public function create( &$order ) {
		$data = $order->get_data();
		unset($data['meta_data']);

		$this->wpdb->insert(
			$this->table,
			$data,
			array_fill(0, count($data), '%s')
		);
		$order->set_id_order($this->wpdb->insert_id);
	}

	public function read( &$order ) {
		$id_order = $order->get_id_order();
		if (! $id_order) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table). ' WHERE id_order = %d', $id_order), ARRAY_A); //phpcs:ignore


		if ($data) {
			$order->set_props($data);
		}
	}

	public function update( &$order ) {
		$id_order = $order->get_id_order();
		if (! $id_order) {
			return;
		}

		$data = $order->get_data();
		$this->wpdb->update(
			$this->table,
			$data,
			array( 'id_order' => $id_order ),
			array_fill(0, count($data), '%s'),
			array( '%d' )
		);
	}

	public function delete( &$order_or_id, $args = array() ) {
		$id_order = is_object( $order_or_id ) ? $order_or_id->get_id_order() : $order_or_id;
		return $this->wpdb->delete($this->table, array( 'id_order' => $id_order ), array( '%d' ));
	}

	public function get_all( $id_order ) {
		// Consulta SQL para obtener todos los pedidos

		$results = $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM '. esc_sql($this->table) . ' WHERE id_order = %d', $id_order), ARRAY_A); //phpcs:ignore

		if ($results) {
			$orders = array();
			foreach ($results as $row) {
				$order = new CorreosOficialSavedOrder();
				$order->set_props($row);
				$orders[] = $order;
			}
			return $orders;
		}
	
		return array();
	}
}
