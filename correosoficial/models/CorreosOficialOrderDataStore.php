<?php
namespace CorreosOficial\Models;

use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialOrderDataStore extends WC_Data_Store_WP {

	private $table;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_orders';
	}

	public function create( &$order ) {
		$data = $order->get_data();
		unset($data['id']);
		unset($data['meta_data']);
		$this->wpdb->insert(
			$this->table,
			$data,
			array_fill(0, count($data), '%s')
		);
		$order->set_id_order($data['id_order']);
	}

	public function read( &$order ) {
		$id_order = $order->get_id_order();
		if (! $id_order) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE id_order = %d', $id_order), ARRAY_A); //phpcs:ignore

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
		unset($data['id']);
		unset($data['meta_data']);
		$this->wpdb->update(
			$this->table,
			$data,
			array( 'id_order' => $id_order ),
			array_fill(0, count($data), '%s'),
			array( '%d' )
		);
	}

	public function delete( &$order, $force_delete = false ) {
		$id_order = $order->get_id_order();
		if (! $id_order) {
			return;
		}

		return $this->wpdb->delete($this->table, array( 'id_order' => $id_order ), array( '%d' ));

		// if ($force_delete) {
		//     $this->wpdb->delete($this->table, ['id_order' => $id_order], ['%d']);
		// } else {
		//     $order->set_deleted_at(current_time('mysql'));
		//     $this->update($order);
		// }
	}

	public function get_all() {
		// Consulta SQL para obtener todos los pedidos
		$results = $this->wpdb->get_results('SELECT * FROM ' . esc_sql($this->table), ARRAY_A);

		if ($results) {
			$orders = array();
			foreach ($results as $row) {
				$order = new CorreosOficialOrder(); // Asumiendo que tienes una clase Order para representar un pedido
				$order->set_props($row); // Usando el set_props para establecer todas las propiedades
				$orders[] = $order;
			}
			return $orders;
		}
	
		return array();
	}

	public static function exists( $id_order ) {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_orders';
		$result = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . esc_sql($table) . ' WHERE id_order = %d', $id_order));
		return $result > 0;
	}
}
