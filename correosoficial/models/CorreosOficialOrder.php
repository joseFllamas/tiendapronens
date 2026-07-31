<?php
namespace CorreosOficial\Models;

use WC_Data;
use WC_Order;

defined('ABSPATH') || exit;

class CorreosOficialOrder extends WC_Data {

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
		'added_values_cash_on_delivery'       => 0,
		'added_values_insurance'              => 0,
		'added_values_partial_delivery'       => 0,
		'added_values_delivery_saturday'      => 0,
		'added_values_cash_on_delivery_iban'  => null,
		'added_values_cash_on_delivery_value' => null,
		'added_values_insurance_value'        => null,
		'manifest_date'                       => null,
	);

	protected $object_type = 'correos_oficial_order';

	public function __construct( $id_order = 0 ) {
		parent::__construct(0);
		$this->data_store = new CorreosOficialOrderDataStore();

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
		return ( new CorreosOficialSavedOrderDataStore() )->get_all($this->get_id_order());
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

	public static function exists ($id_order) {
		return CorreosOficialOrderDataStore::exists($id_order);
	}

	/**
	 * Returns the latest shipping number for a given order, or null if none.
	 *
	 * @param int $order_id
	 * @return string|null
	 */
	public static function get_shipping_number_by_order_id( int $order_id ): ?string {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_saved_orders';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT shipping_number FROM {$table} WHERE id_order = %d ORDER BY id DESC",
				$order_id
			)
		);
		return $row ? $row->shipping_number : null;
	}

	/**
	 * Elimina un pedido y todos sus registros relacionados
	 * 
	 * @param int $id_order ID del pedido a eliminar
	 * @return bool True si se eliminó correctamente
	 */
	public static function deleteOrder($id_order) {
		global $wpdb;
		
		// Eliminar de correos_oficial_orders
		$order = new self($id_order);
		if ($order->get_id_order()) {
			$order->data_store->delete($order, true);
		}
		
		// Eliminar de correos_oficial_saved_orders (bultos)
		$table_saved_orders = $wpdb->prefix . 'correos_oficial_saved_orders';
		$wpdb->delete($table_saved_orders, array('id_order' => $id_order), array('%d'));
		
		// NO eliminamos de correos_oficial_requests para poder restaurar la dirección del punto de entrega al cancelar
		
		return true;
	}
}
