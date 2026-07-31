<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Classes\CorreosOficialUtils;
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


define('SELECT', 'SELECT * FROM ');
define('DB_PREFIX', CorreosOficialUtils::getPrefix());

class CorreosOficialOrders {


	public static function getCustomerOrder( $id_order ) {
		$order = new WC_Order($id_order);

		// Soporte para versiones inferiores a Woocommerce 5.6.0
		if (version_compare(WC_VERSION, '5.6.0', '<')) {
			$phone = $order->get_billing_phone();
		} else {
			$phone = $order->get_shipping_phone() == '' ? $order->get_billing_phone() : $order->get_shipping_phone();
		}

		
		$phone = CorreosOficialUtils::cleanTelephoneNumber($phone);
		
		// sólo establecer phone_mobile con el phone si:
		// - phone tiene 9 dígitos
		// - phone empieza por 6, 7, 9
		if ( strlen($phone)==9 
				&&  ( substr($phone, 0, 1)=='6' || substr($phone, 0, 1)=='7' || substr($phone, 0, 1)=='9' ) ) {
			$phone_mobile = $phone;
		} else {
			$phone_mobile = '';
		}

		return array(
			'id_order' => $id_order,
			'id_cart' => $order->get_cart_hash(),
			'customer_firstname' => $order->get_shipping_first_name(),
			'customer_lastname' => $order->get_shipping_last_name(),
			'customer_dni' => CorreosOficialOrder::getRealDnI($id_order),
			'delivery_address' => $order->get_shipping_address_1(),
			'delivery_address2' => $order->get_shipping_address_2(),
			'delivery_city' => $order->get_shipping_city(),
			'delivery_postcode' => $order->get_shipping_postcode(),
			'delivery_phone' => $phone,
			'phone' => $phone,
			'phone_mobile' => $phone_mobile,
			'customer_email' => $order->get_billing_email(),
			'delivery_country_iso' => $order->get_shipping_country(),
		);
	}

	public static function getRequestRecord( $id_order ) {
		$query = SELECT . DB_PREFIX . 'correos_oficial_requests cor
        LEFT JOIN ' . DB_PREFIX . "orders po ON (cor.id_cart = po.id_cart)
        WHERE po.id_order=$id_order";
		return self::launchQuery($query, true);
	}

	public static function getCorreosOrder( $id_order ) {
		if (!empty($id_order)) {
			global $wpdb;
			$query = SELECT . DB_PREFIX . 'correos_oficial_orders coo
            LEFT JOIN ' . DB_PREFIX . "correos_oficial_products cop ON (cop.id = coo.id_product)
            WHERE id_order = $id_order";
			$record = $wpdb->get_results($query, ARRAY_A);

			if ($record) {
				return $record[0];
			}
		}
	}

	public static function getCorreosPackages( $id_order ) {
		global $wpdb;
		$query = SELECT . DB_PREFIX . 'correos_oficial_orders coo
        LEFT JOIN ' . DB_PREFIX . "correos_oficial_saved_orders coso ON (coso.exp_number = coo.shipping_number)
        WHERE coo.id_order=$id_order";
		return $wpdb->get_results($query, ARRAY_A);
	}

	public static function getCorreosReturn( $id_order ) {
		$query = SELECT . DB_PREFIX . "correos_oficial_returns WHERE id_order = $id_order";
		$result = self::launchQuery($query, true);

		if ($result) {
			return $result[0];
		}
	}

	public static function getCorreosPickupReturn( $id_order ) {
		$query = SELECT . DB_PREFIX . "correos_oficial_returns WHERE id_order = $id_order";
		$result = self::launchQuery($query, true);

		if ($result) {
			return $result[0];
		}
	}

	public static function getCorreosPackagesReturn( $id_order ) {
		$query = SELECT . DB_PREFIX . 'correos_oficial_returns cor
        LEFT JOIN ' . DB_PREFIX . "correos_oficial_saved_returns cosr ON (cosr.exp_number = cor.shipping_number)
        WHERE cor.id_order=$id_order";
		return self::launchQuery($query, true);
	}

	public static function launchQuery( $query, $as_array = false ) {
		global $wpdb;
		$output = $as_array ? ARRAY_A : OBJECT;
		return $wpdb->get_results($query, $output);
	}

	public static function updateOrderManifestDate( $id_order ) {
		$sql = 'UPDATE ' . DB_PREFIX . "correos_oficial_orders SET manifest_date=NOW()
        WHERE id_order=$id_order";
		$result = self::launchQuery($sql, true);
		if ($result) {
			return $result[0];
		}
	}
}
