<?php

namespace CorreosOficial\Models;

use Exception;
use WC_Data_Store_WP;

class CorreosOficialSGAOrdersLogStore extends WC_Data_Store_WP {
    private $table;
	private $wpdb;

    public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_sga_orders_log';
	}

    public function read( &$order ) {
		$order_id = $order->get_order_id();
		if (! $order_id) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE order_id = %d', $order_id), ARRAY_A); //phpcs:ignore

		if ($data) {
			$order->set_props($data);
		}
	}

	public function update( &$order ) {
		$id_order = $order->get_order_id();
		if (! $id_order) {
			return;
		}

		$data = $order->get_data();
		unset($data['meta_data']);
		
		$this->wpdb->update(
			$this->table,
			$data,
			array( 'order_id' => $id_order ),
			array_fill(0, count($data), '%s'),
			array( '%d' )
		);
	}

    public function create( &$log ) {
        $data = $log->get_data();
        unset($data['meta_data']);

        $this->wpdb->insert(
            $this->table,
            $data,
            array_fill(0, count($data), '%s')
        );

        $log->set_id($this->wpdb->insert_id);
    }

	/**
	 * Obtiene los logs de un pedido por su ID
	 *
	 * @param int $order_id ID del pedido en WooCommerce.
	 * @return array Regresa un arreglo con los registros del log o vacío si no hay resultados.
	 */
	public function getLogsByIdOrder( $log ) {

		$id_order = $log->get_order_id();
		if (! $id_order) {
			return;
		}

		$data = $log->get_data();
		unset($data['meta_data']);

		// Consulta segura con preparación de parámetros
		$query = $this->wpdb->prepare(
			"SELECT * FROM ". esc_sql($this->table) . " WHERE order_id = %d ORDER BY timestamp ASC",
			(int) $id_order
		);

		$logs = $this->wpdb->get_results($query, ARRAY_A);

		if (empty($logs)) {
			return [];
		}

		// Formatear fecha y hora para mostrar en zona horaria de WP
		foreach ($logs as &$log) {
			if (!empty($log['timestamp'])) {
				$log['timestamp'] = get_date_from_gmt($log['timestamp'], get_option('date_format') . ' ' . get_option('time_format'));
			}
		}

		return $logs;
	}
}