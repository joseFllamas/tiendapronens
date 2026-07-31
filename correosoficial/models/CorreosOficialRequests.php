<?php
namespace CorreosOficial\Models;

use WC_Data;
use WC_Order;

defined('ABSPATH') || exit;

class CorreosOficialRequests extends WC_Data {

	protected $data = array(
		'id' => '',
		'id_cart' => '',
		'reference_code' => null,
		'data' => '',
		'date' => '',
		'id_order' => '',
	);

	protected $object_type = 'correos_oficial_request';

	public function __construct( $id_order = 0 ) {
		parent::__construct(0);
		$this->data_store = new CorreosOficialRequestsDataStore();

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

	public function read() {
		$this->data_store->read($this);
	}
	
	public function save() {
		$this->data_store->update($this);
	}
	
	public function create() {
		$this->data_store->create($this);
	}

	public function getCartHashFromWooTable($order_id) {
		return $this->data_store->getCartHashFromWooTable($order_id);
	}

	public function getRequestData($id_order, $product_type) {
		return $this->data_store->getRequestData($id_order, $product_type);
	}

	public function normalizeLocations($data) {
		return $this->data_store->normalizeLocations($data);
	}

	public function getRequestByIdOrder($id_order) {
		return $this->data_store->getRequestByIdOrder($id_order);
	}

	public function deleteByOrderID() {
		$this->data_store->deleteByOrderID($this);
	}

        /**
         * Inserts a reference code linked to an order/cart only if no row with id_order exists.
         *
         * @param string      $id_cart         WooCommerce cart hash.
         * @param string|null $reference_code  Selected reference code (nullable).
         * @param string      $data            JSON-encoded pickup location data.
         * @param int         $id_order        Order id.
         */
        public static function insert_reference_code_with_order_id( string $id_cart, ?string $reference_code, string $data, int $id_order ): void {
                global $wpdb;
                $table = $wpdb->prefix . 'correos_oficial_requests';
                $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id_order) FROM {$table} WHERE id_order = %d", $id_order ) );
                if ( empty( $count ) ) {
                        $req = new self();
                        $req->set_id_cart( $id_cart );
                        $req->set_reference_code( $reference_code );
                        $req->set_data( $data );
                        $req->set_id_order( $id_order );
                        $req->create();
                }
        }

}