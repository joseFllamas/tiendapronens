<?php
namespace CorreosOficial\Classes;

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

/**
 * Clase referente a las zonas de Woocommerce.
 */
class CorreosOficialZonesWC {

	/**
	 * Consigue id de zona del pedido
	 */
	public static function getShippingZone( $location_code1, $postcode ) {
		global $wpdb;

		$location_code_ISO = substr($location_code1, 0, 2);

		$where = "WHERE location_code = '$location_code1' GROUP BY zone_id";
		$record = $wpdb->get_results("SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_locations $where", ARRAY_A);

		if (!$record) {
			$where = "WHERE location_code = '$location_code_ISO' GROUP BY zone_id";
			$record = $wpdb->get_results("SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_locations $where", ARRAY_A);
		}
		if (!$record) {
			$where = "WHERE location_code = '$postcode' GROUP BY zone_id";
			$record = $wpdb->get_results("SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_locations $where", ARRAY_A);
		}

		return $record[0]['zone_id'];
	}

	public function getZones( $table ) {
		global $wpdb;
		return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}$table", ARRAY_A);
	}

	/**
	 * Solo para WC
	 */
	public function getCarriersByZone( $id_zone, $table ) {
		global $wpdb;
		$sql = 'SELECT instance_id, method_id, is_enabled
                FROM ' . $wpdb->prefix . 'woocommerce_shipping_zone_methods wszm LEFT OUTER
                JOIN ' . $wpdb->prefix . "correos_oficial_carriers_products cocp ON cocp.id_carrier = wszm.instance_id
                WHERE zone_id='$id_zone'
                UNION
                SELECT instance_id, method_id, is_enabled FROM " . $wpdb->prefix . 'woocommerce_shipping_zone_methods wszm
                LEFT OUTER JOIN ' . $wpdb->prefix . "correos_oficial_carriers_products cocp ON cocp.id_carrier = wszm.instance_id
                WHERE id_carrier IS NULL AND zone_id='$id_zone'";

		return $wpdb->get_results($sql, ARRAY_A);
	}
}
