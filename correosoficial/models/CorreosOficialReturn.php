<?php
namespace CorreosOficial\Models;

use WC_Data;
use WC_Order;

defined('ABSPATH') || exit;

class CorreosOficialReturn extends WC_Data {

	protected $data = array(
		'id_order'                            => '',
		'id_sender'                           => '',
		'reference'                           => '',
		'shipping_number'                     => '',
		'carrier_type'                        => '',
		'date_add'                            => '',
		'office'                              => null,
		'id_product'                          => '',
		'id_carrier'                          => '',
		'bultos'                              => '',
		'AT_code'                             => '',
		'last_status'                         => '',
		'status'                              => '',
		'updated_at'                          => null,
		'deleted_at'                          => null,
		'pickup'                              => null,
		'pickup_number'                       => null,
		'pickup_date'                         => null,
		'pickup_from_hour'                    => null,
		'pickup_to_hour'                      => null,
		'package_size'                        => null,
		'print_label'                         => 'N',
		'pickup_status'                       => null,
		'require_customs_doc'                 => 0,
	);

	protected $object_type = 'correos_oficial_order_return';

	public function __construct( $id_order = 0 ) {
		parent::__construct(0);
		$this->data_store = new CorreosOficialReturnDataStore();

		if ($id_order > 0) {
			$this->set_id_order($id_order);
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

	// Bultos
	public function get_bultos() {
		return ( new CorreosOficialSavedReturnDataStore() )->get_all($this->get_id_order());
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
