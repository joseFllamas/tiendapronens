<?php
namespace CorreosOficial\Models;

use WC_Data;

defined('ABSPATH') || exit;

class CorreosOficialSavedReturn extends WC_Data {

	protected $id;
	protected $data = array(
		'id_order'                            => '',
		'shipping_number'                     => '',
		'exp_number'                          => '',
	);

	protected $object_type = 'correos_oficial_saved_return';

	public function __construct( $id = 0 ) {
		parent::__construct($id);
		$this->data_store = new CorreosOficialSavedReturnDataStore();

		if ($id > 0) {
			$this->id = $id;
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
	
	public function save() {
		$this->data_store->update($this);
	}
	
	public function create() {
		$this->data_store->create($this);
	}
}
