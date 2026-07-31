<?php

namespace CorreosOficial\Models;

use Exception;
use WC_Data_Store_WP;


class CorreosOficialSgaOrdersStatusStore extends WC_Data_Store_WP {
    private $table;
	private $wpdb;

    public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_sga_orders_status';
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

    public function create( &$order ) {
		$data = $order->get_data();
		unset($data['id']);
		unset($data['meta_data']);
		$this->wpdb->insert(
			$this->table,
			$data,
			array_fill(0, count($data), '%s')
		);
		$order->set_order_id($data['order_id']);
	}

	public function update( &$order ) {
		$order_id = $order->get_order_id();
		if ( ! $order_id ) {
			return;
		}

		$data = $order->get_data();
		unset( $data['meta_data'] );

		$exists = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE order_id = %d",
				$order_id
			)
		);

		if ( $exists > 0 ) {
			$this->wpdb->update(
				$this->table,
				$data,
				array( 'order_id' => $order_id ),
				array_fill( 0, count( $data ), '%s' ),
				array( '%d' )
			);
		} else {
			$this->wpdb->insert(
				$this->table,
				$data,
				array_fill( 0, count( $data ), '%s' )
			);
		}
	}

    public function get_order_status( &$order ) {
        $order_id = $order->get_order_id();
		if (! $order_id) {
			return;
		}

		$order_status = $this->wpdb->get_row($this->wpdb->prepare('SELECT status FROM ' . esc_sql($this->table) . ' WHERE order_id = %d', $order_id), ARRAY_A); //phpcs:ignore

        if ($order_status) {
            $status = $order_status['status'];
            // Devolver limpio, sin el prefijo "wc-"
            return str_starts_with( $status, 'wc-' )
                ? substr( $status, 3 )
                : $status;
        }
    }
}