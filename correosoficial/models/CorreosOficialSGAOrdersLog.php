<?php

namespace CorreosOficial\Models;

use WC_Data;

defined('ABSPATH') || exit;

class CorreosOficialSGAOrdersLog extends WC_Data {
    protected $id;
    protected $data = array (
        'order_id'  => null,
        'order_ref' => '',
        'timestamp' => '',
        'action'    => '',
        'message'   => '',
        'info'      => '',
        'url'       => ''
    );

    protected $object_type = 'correos_oficial_sga_orders_log';

    public function __construct( $order_id = 0) {
        parent::__construct(0);
        $this->data_store = new CorreosOficialSGAOrdersLogStore();

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

    public function save() {
		$this->data_store->update($this);
	}
	
	public function create() {
		$this->data_store->create($this);
	}

    public function read() {
        $this->data_store->read($this);
    }

    public function getLogsByIdOrder() {
        return $this->data_store->getLogsByIdOrder($this);
    }

}