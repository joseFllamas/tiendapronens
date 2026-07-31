<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Models\CorreosOficialConfig;
use WC_Order;

/**
 * This program is free software: you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see https://www.gnu.org/licenses/.
 */

class CorreosOficialOrder {

	private $id_order;
	private $order;

	public function __construct( $id_order ) {
		$this->id_order = $id_order;

		try {
			$this->order = new WC_Order($this->id_order);
		} catch (Exception $e) {
			return;
		}
	}

	public function getFirstMessage() {
		return $this->order->get_customer_note();
	}

	public function getTotalWeight() {
		$items = $this->order->get_items();

		$order_weight = 0;
		$totalWeight = 0;

		// Obtener la unidad de medida configurada en WooCommerce
		$unit = get_option('woocommerce_weight_unit');

		/* Calculamos peso */
		foreach ( $items as $item ) {
			if ( $item['product_id'] > 0 ) {
				$product = $item->get_product();

				// Saltar items PADRE de composites/bundles
				// (no filtrar por tipo de producto: un hijo puede ser wooco/composite)
				if ( $item->get_meta('_wooco_ids') || $item->get_meta('_composite_children') || $item->get_meta('_bundled_items') ) {
					continue;
				}

				if ( ! $product->is_virtual() ) {
					// Leer peso desde _weight en post_meta (evita filtros de plugins composite)
					$raw = get_post_meta( $product->get_id(), '_weight', true );
					if ( ( $raw === '' || $raw === false ) && $product->is_type('variation') ) {
						$raw = get_post_meta( $product->get_parent_id(), '_weight', true );
					}
					$product_weight = ( $raw !== '' && $raw !== false ) ? (float) $raw : 0;

					if ( $unit === 'g' ) {
						$product_weight = $product_weight / 1000;
					}
					$order_weight += $product_weight * $item['qty'];
				}
			}
		}

		$totalWeight += $order_weight;

		return $totalWeight;
	}

	public function getSubTotal() {
		return $this->order->get_subtotal();
	}

	public function getUnits() {
		return $this->order->get_item_count();
	}

	public function getTotalPaid() {
		return $this->order->get_total();
	}

	public function isCashOnDeliveryMethodType() {
		if ($this->order->get_payment_method() == 'cod') {
			return true;
		}
	}

	public function getCurrentState() {
		return $this->order->get_status();
	}

	public function orderExist() {
		global $wpdb;

		return $wpdb->get_row($wpdb->prepare(
			"SELECT COUNT(id) as c FROM {$wpdb->prefix}posts WHERE id = %d",
			(int) $this->id_order)
		);
	}

	public function getIdCarrier( $id_order, $id_product ) {
		global $wpdb;

		$order = new WC_Order($id_order);
		$shipping_methods = array();

		$shipping_methods = $order->get_shipping_methods();

		
		// Ha sido seleccionado un transportista en el checkout
		if (count($shipping_methods)) {
			foreach ($shipping_methods as $shipping_method) {
				$shipping_method_data = $shipping_method->get_data();
			}

			if (isset($shipping_method_data['instance_id'])) {
				$id_zone = CorreosOficialCarrier::getCarrierZone($shipping_method_data['instance_id']);

				$order->id_carrier = $shipping_method_data['instance_id'];

				$result = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}correos_oficial_carriers_products WHERE id_product=%d and id_zone=%d",
						$id_product,
						$id_zone
					)
				);

				if (isset($result[0])) {
					return $result[0]->id_carrier;
				} else {
					return 0;
				}
			}
		} else { // Transportista aún no seleccionado (ejemplo un pedido hecho desde Woocommerce->Pedidos)
			return false;
		}
	}

	public static function getRealDnI( $order_id ) {
		$NifFieldRadio = CorreosOficialConfig::getConfigValue('NifFieldRadio');

		if ('PERSONALIZED' == $NifFieldRadio && $NifFieldRadio) {
			$NifFieldValue = CorreosOficialConfig::getConfigValue('NifFieldPersonalizedValue');
		} else {
			$NifFieldValue = 'NIF';
		}

		return get_post_meta($order_id, $NifFieldValue, true);
	}

	// Se borran pedidos de las tablas del plugin
	public static function deleteOrder( $id_order ) {
		global $wpdb;
		$wpdb->delete("{$wpdb->prefix}correos_oficial_orders", array( 'id_order' => $id_order ) );
		$wpdb->delete("{$wpdb->prefix}correos_oficial_saved_orders", array( 'id_order' => $id_order ) );
		$wpdb->delete("{$wpdb->prefix}correos_oficial_saved_returns", array( 'id_order' => $id_order ) );
	}
}
