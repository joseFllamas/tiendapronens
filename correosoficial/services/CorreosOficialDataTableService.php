<?php
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

namespace CorreosOficial\Services;

use CorreosOficial\Models\CorreosOficialConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service class for WooCommerce DataTable queries.
 *
 * Replaces the three query methods of CorreosOficialUtilitiesDaoWC so that
 * controllers no longer depend on the vendor DAO hierarchy.
 *
 * All methods use $wpdb->prefix directly so that no dependency on
 * CorreosOficialUtils (vendor, non-namespaced) is required.
 */
class CorreosOficialDataTableService {

	// -----------------------------------------------------------------
	// Private SQL fragment helpers (mirror of CorreosOficialUtilitiesDaoWC)
	// -----------------------------------------------------------------

	/**
	 * Returns a sub-SELECT SQL fragment that retrieves a postmeta value.
	 *
	 * @param string $meta_key The meta_key to look up.
	 * @param string $prefix   WordPress table prefix (e.g. 'wp_').
	 * @return string SQL sub-query fragment.
	 */
	private static function get_meta_key_value( string $meta_key, string $prefix ): string {
		return "(SELECT meta_value FROM {$prefix}postmeta WHERE post_id=wp.ID AND meta_key='" . $meta_key . "')";
	}

	/**
	 * Returns a sub-SELECT SQL fragment for the WooCommerce order item meta (instance_id).
	 *
	 * @param string $prefix WordPress table prefix (e.g. 'wp_').
	 * @return string SQL sub-query fragment.
	 */
	private static function get_order_item_meta( string $prefix ): string {
		return "SELECT meta_value FROM {$prefix}woocommerce_order_itemmeta woim
            JOIN {$prefix}woocommerce_order_items woi ON woim.order_item_id = woi.order_item_id
            WHERE order_id=wp.ID and meta_key = 'instance_id' LIMIT 1)";
	}

	/**
	 * Decorates an order row array with wc_get_order()-derived fields
	 * (order_number, post_id).
	 *
	 * @param array $order Associative-array row from a DataTable query.
	 * @return array The same row with 'order_number' and 'post_id' keys added.
	 */
	private static function set_order_number( array $order ): array {
		$order2 = wc_get_order( $order['id_order'] );
		$order['order_number'] = $order2->get_order_number();
		$order['post_id']      = $order['id_order'];
		return $order;
	}

	// -----------------------------------------------------------------
	// Public API — DataTable query methods
	// -----------------------------------------------------------------

	/**
	 * Returns all WooCommerce orders for the mass-management DataTable.
	 *
	 * @param string|null $date_from Lower bound of order date (Y-m-d).
	 * @param string|null $date_to   Upper bound of order date (Y-m-d).
	 * @return array List of order rows as associative arrays, or empty array.
	 */
	public static function get_orders_for_mass_management( ?string $date_from, ?string $date_to ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		$order_key         = self::get_meta_key_value( '_order_key', $p );
		$shipping_firstname = self::get_meta_key_value( '_shipping_first_name', $p );
		$shipping_lastname  = self::get_meta_key_value( '_shipping_last_name', $p );
		$billing_country    = self::get_meta_key_value( '_billing_country', $p );
		$cart_hash          = self::get_meta_key_value( '_cart_hash', $p );

		$default_packages_value = (new CorreosOficialConfig( 'DefaultPackages' ))->get_value();

		$orders = [];

		if ( $date_from !== null && $date_to !== null ) {
			$sql = "SELECT
            wp.ID as id_order,
            " . $order_key . " as reference,
            cocp.id_carrier as id_carrier,
            '10' as 'current_sate',
            wp.post_date as date_add,
            coo.shipping_number as shipping_number,
            coo.AT_code as AT_code,
            cop.id as id_product,
            cos.exp_number as first_shipping_number,
            cor.reference_code as office,

            IF (coo.last_status IS NULL, (SELECT status FROM {$p}wc_order_stats WHERE order_id = wp.ID  LIMIT 1),coo.last_status) as order_state,
            (SELECT order_item_name FROM {$p}woocommerce_order_items WHERE order_id = wp.ID AND order_item_type = 'shipping'  LIMIT 1) as carrier_type,

            IF (cop.name IS NULL, (SELECT name FROM {$p}correos_oficial_products WHERE id = cocp.id_product LIMIT 1), cop.name) as name,
            cop.company as company,
            cop.max_packages as max_packages,
            cop.codigoProducto as codigoProducto,
            cop.product_type as product_type,
            cocp.id_product as id_product_custom,

            IF (cop.max_packages IS NULL, (SELECT max_packages FROM {$p}correos_oficial_products WHERE id = cocp.id_product LIMIT 1), null) as max_packages_custom,
            concat($shipping_firstname, ' ', $shipping_lastname) as cliente,
            " . $billing_country . " as delivery_iso_code,

            IF(coo.shipping_number !='',
            (SELECT sender_iso_code_pais FROM {$p}correos_oficial_senders WHERE id=coo.id_sender),
            (SELECT sender_iso_code_pais FROM {$p}correos_oficial_senders WHERE sender_default=1)
            ) as sender_iso_code,

            IFNULL(coo.bultos,$default_packages_value) as bultos

            FROM {$p}posts wp

            LEFT JOIN {$p}wc_order_stats os ON (os.order_id = wp.ID)
            LEFT JOIN {$p}correos_oficial_orders coo ON (wp.ID = coo.id_order)
            LEFT JOIN {$p}correos_oficial_carriers_products cocp ON (cocp.id_carrier = (" . self::get_order_item_meta( $p ) . ")
            LEFT JOIN {$p}correos_oficial_saved_orders cos ON (coo.shipping_number = cos.exp_number)
            LEFT JOIN {$p}correos_oficial_requests cor ON (
                (CASE WHEN cor.id_order IS NULL
                THEN cor.id_cart = " . $cart_hash . "
                ELSE cor.id_order = wp.ID
                END)
                )
            LEFT JOIN {$p}correos_oficial_products cop ON (cop.id = cocp.id_product)

            WHERE date(wp.post_date)  BETWEEN '$date_from' AND '$date_to' AND wp.post_type = 'shop_order' AND wp.post_status != 'trash'
            GROUP BY wp.ID, cocp.id_carrier";

			$wpdb->query( "SET sql_mode=''" );
			$result = $wpdb->get_results( $sql, ARRAY_A );

			if ( $result ) {
				foreach ( $result as $order ) {
					$order            = self::set_order_number( $order );
					$order['reference'] = $order['order_number'] . ' ' . str_replace( 'wc_order_', '', $order['reference'] );
					$order['order_state'] = __( ucFirst( str_replace( 'wc-', '', $order['order_state'] ) ), 'woocommerce' );
					$orders[] = $order;
				}
			}
		}

		return $orders;
	}

	/**
	 * Returns pre-registered WooCommerce shipments for the Labels / Summary /
	 * Pickups DataTable.
	 *
	 * @param string|null $date_from Lower bound of order date (Y-m-d).
	 * @param string|null $date_to   Upper bound of order date (Y-m-d).
	 * @return array List of shipment rows as associative arrays, or empty array.
	 */
	public static function get_shippings( ?string $date_from, ?string $date_to ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		$order_key          = self::get_meta_key_value( '_order_key', $p );
		$customer_first_name = self::get_meta_key_value( '_shipping_first_name', $p );
		$customer_last_name  = self::get_meta_key_value( '_shipping_last_name', $p );
		$customer_address    = self::get_meta_key_value( '_shipping_address_1', $p );

		$default_packages_value = (new CorreosOficialConfig( 'DefaultPackages' ))->get_value();

		$shippings = [];

		if ( $date_from !== null && $date_to !== null ) {
			$sql = "SELECT wp.ID as id_order,
            " . $order_key . " as reference,
            coo.shipping_number as shipping_number,
            cop.company as company,
            concat($customer_first_name, ' ', $customer_last_name) as customer_name,
            " . $customer_address . " as customer_address,
            wp.post_date as date_add,
            cop.id as id_product,
            cop.codigoProducto as codigoProducto,
            IFNULL(coo.bultos,$default_packages_value) as bultos,
            coo.pickup as pickup,
            coo.print_label as print_label,
            coo.package_size as package_size,
            cos.exp_number as first_shipping_number,
            coo.pickup_number as pickup_number,
            coo.last_status as last_status,
            coo.status as status

            FROM {$p}posts wp
            LEFT JOIN {$p}correos_oficial_orders coo ON (wp.ID = coo.id_order)
            LEFT JOIN {$p}correos_oficial_products cop ON (coo.id_product = cop.id)
            LEFT JOIN {$p}correos_oficial_saved_orders cos ON (coo.shipping_number = cos.exp_number)
            LEFT JOIN {$p}wc_order_stats os ON (os.order_id = wp.ID)
            WHERE date(wp.post_date) BETWEEN '$date_from' AND '$date_to' AND coo.shipping_number != '' AND wp.post_type = 'shop_order'
            AND os.status != 'wc-trash'
            GROUP BY wp.ID";

			$result = $wpdb->get_results( $sql, ARRAY_A );

			if ( $result ) {
				foreach ( $result as $shipping ) {
					$shipping              = self::set_order_number( $shipping );
					$shipping['reference'] = $shipping['order_number'] . ' ' . str_replace( 'wc_order_', '', $shipping['reference'] );
					$shippings[] = $shipping;
				}
			}
		}

		return $shippings;
	}

	/**
	 * Returns pre-registered WooCommerce shipments that require a customs
	 * document, for the Customs Doc DataTable.
	 *
	 * @param string|null $date_from Lower bound of order date (Y-m-d).
	 * @param string|null $date_to   Upper bound of order date (Y-m-d).
	 * @return array List of shipment rows as associative arrays, or empty array.
	 */
	public static function get_shippings_customs_doc( ?string $date_from, ?string $date_to ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		$order_key          = self::get_meta_key_value( '_order_key', $p );
		$shipping_country   = self::get_meta_key_value( '_shipping_country', $p );
		$customer_first_name = self::get_meta_key_value( '_shipping_first_name', $p );
		$customer_last_name  = self::get_meta_key_value( '_shipping_last_name', $p );
		$customer_address    = self::get_meta_key_value( '_shipping_address_1', $p );

		$default_packages_value = (new CorreosOficialConfig( 'DefaultPackages' ))->get_value();

		$shippings = [];

		if ( $date_from !== null && $date_to !== null ) {
			$sql = "SELECT wp.ID as id_order,
            " . $order_key . " as reference,
            coo.shipping_number as shipping_number,
            cop.company as company,
            concat($customer_first_name, ' ', $customer_last_name) as customer_name,
            " . $customer_address . " as customer_address,
            " . $shipping_country . " as customer_country,
            wp.post_date as date_add,
            cos.exp_number as first_shipping_number,
            IFNULL(coo.bultos,$default_packages_value) as bultos,
            coo.require_customs_doc as require_customs_doc

            FROM {$p}posts wp
                LEFT JOIN {$p}correos_oficial_orders coo ON (wp.ID = coo.id_order)
                LEFT JOIN {$p}correos_oficial_products cop ON (coo.id_product = cop.id)
                LEFT JOIN {$p}correos_oficial_saved_orders cos ON (coo.shipping_number = cos.exp_number)
                LEFT JOIN {$p}wc_order_stats os ON (os.order_id = wp.ID)
                WHERE date(wp.post_date) BETWEEN '$date_from' AND '$date_to' AND ( coo.require_customs_doc = 1) AND coo.shipping_number != '' AND cop.company='Correos'
                AND wp.post_type = 'shop_order' AND os.status != 'wc-trash'
                GROUP BY wp.ID";

			$result = $wpdb->get_results( $sql, ARRAY_A );

			if ( $result ) {
				foreach ( $result as $shipping ) {
					$shipping              = self::set_order_number( $shipping );
					$shipping['reference'] = $shipping['order_number'] . ' ' . str_replace( 'wc_order_', '', $shipping['reference'] );
					$shippings[] = $shipping;
				}
			}
		}

		return $shippings;
	}
}
