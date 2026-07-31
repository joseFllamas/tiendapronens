<?php

namespace CorreosOficial\Models;

use WC_Data;

defined('ABSPATH') || exit;

class CorreosOficialSgaOrdersStatus extends WC_Data {

    protected $id;
    protected $data = array (
        'order_id'          => null,
        'status'            => '',
        'timestamp'         => '',
        'shipping_number'   => '',
		'company_transport' => ''
    );

    protected $object_type = 'correos_oficial_sga_orders_status';

    public function __construct( $order_id = 0) {
        parent::__construct(0);
        $this->data_store = new CorreosOficialSgaOrdersStatusStore();

		if ($order_id > 0) {
			$this->set_order_id($order_id);
			$this->read();
		}
    }

    // Getter y Setter dinámicos
	public function __call( $method, $arguments ) {
		if (strpos($method, 'get_') === 0) {
			$prop = substr($method, 4);
			return isset($this->data[$prop]) ? $this->data[$prop] : null;
		}

		if (strpos($method, 'set_') === 0) {
			$prop = substr($method, 4);
			if (array_key_exists($prop, $this->data)) {
				$this->data[$prop] = $arguments[0];
			}
		}
	}
	
	public function read() {
		$this->data_store->read($this);
	}
	
	public function create() {
		$this->data_store->create($this);
	}

    public function save() {
		$this->data_store->update($this);
	}

    // Devuelve estado sin prefijo wc
    public function get_order_status() {
        return $this->data_store->get_order_status($this);
    }

	public static function existsByOrderId( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'correos_oficial_sga_orders_status';

		$existing_id = $wpdb->get_var(
			$wpdb->prepare("SELECT id FROM {$table} WHERE order_id = %d", $order_id)
		);

		return ( $existing_id !== null );
	}

	public static function deleteByOrderId( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'correos_oficial_sga_orders_status';

		$existing_id = $wpdb->get_var(
			$wpdb->prepare("SELECT id FROM {$table} WHERE order_id = %d", $order_id)
		);

		if ( $existing_id ) {
			$deleted = $wpdb->delete(
				$table,
				[ 'order_id' => $order_id ],
				[ '%d' ]
			);

			// Aseguramos un valor booleano
			return ( $deleted && $deleted > 0 );
		}

		return false;
	}

}