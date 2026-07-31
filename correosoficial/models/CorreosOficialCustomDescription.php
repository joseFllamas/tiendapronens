<?php
namespace CorreosOficial\Models;

use WC_Data;

defined('ABSPATH') || exit;

class CorreosOficialCustomDescription extends WC_Data {
    
    protected $data = array(
        'id'          => '',
        'code'        => '',
        'descripcion' => ''
    );

    public function __construct( $id = null) {
        parent::__construct(0);
		$this->data_store = new CorreosOficialCustomDescriptionDataStore();

		if ($id > 0) {
			$this->set_id($id);
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

    public function get_all_customs_desc () {
        return $this->data_store->get_all_customs_desc();
    }
}