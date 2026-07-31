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

namespace CorreosOficial\Controllers\Admin;

use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialProduct;

if (!defined('WC_VERSION')) {
	die;
}



class AdminCorreosOficialZonesCarriersProcessController {

	public $module;

	public function __construct() {
		global $wpdb;

		if (isset($_POST['_nonce'])) {
			$nonce = sanitize_text_field($_POST['_nonce']);
			if (!wp_verify_nonce(wp_unslash($nonce), 'correosoficial_nonce')) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_send_json_error( 'bad_nonce' );
				wp_die();
			}
		}

		$dispatcherData = isset($_POST['dispatcher']) ?
			array_map('sanitize_text_field', (array) $_POST['dispatcher']) : array();

		if ($dispatcherData == null) {
			return;
		}

		$formDataArray = array();
		$formDataArray = $dispatcherData;

		foreach ($formDataArray as $input => $value) {

			if ($input == 'controller') {
				continue;
			}

			// Esperamos claves en formato prefix_zone_carrier (al menos 3 partes)
			$data_explode_from_input = explode('_', $input);
			if (count($data_explode_from_input) < 3) {
				// Entrada no válida, la ignoramos
				continue;
			}

			$id_product = CorreosOficialNormalization::normalizeData($value, 'value');
			$id_zone = isset($data_explode_from_input[1]) ? CorreosOficialNormalization::normalizeData($data_explode_from_input[1], 'value') : null;
			$id_carrier = isset($data_explode_from_input[2]) ? CorreosOficialNormalization::normalizeData($data_explode_from_input[2], 'value') : null;

			// Si faltan datos imprescindibles, seguimos con la siguiente entrada
			if ($id_zone === null || $id_carrier === null) {
				continue;
			}

            if (!empty($id_product)) {
                $table = "{$wpdb->prefix}correos_oficial_carriers_products";
                $existing = $wpdb->get_results(
                    $wpdb->prepare("SELECT id_product FROM $table WHERE id_carrier = %d", $id_carrier)
                );
                if (empty($existing)) {
                    $wpdb->insert($table,
                        array('id_carrier' => $id_carrier, 'id_product' => $id_product, 'id_zone' => $id_zone),
                        array('%d', '%d', '%d')
                    );
                } else {
                    $wpdb->update($table,
                        array('id_product' => $id_product),
                        array('id_zone' => $id_zone, 'id_carrier' => $id_carrier),
                        '%d',
                        array('%d', '%d')
                    );
                }
            } else {
                $wpdb->delete(
                    "{$wpdb->prefix}correos_oficial_carriers_products",
                    array('id_carrier' => $id_carrier, 'id_zone' => $id_zone)
                );
            }
        }

		// Asignación automática del transportista en relación con Channable

		$carrierName_ant    = CorreosOficialConfig::getConfigValue('AutomaticProductAssignmentText');
		$productId_ant      = CorreosOficialConfig::getConfigValue('AutomaticProductAssignmentProduct');
		
		$carrierName    = sanitize_text_field(isset($_POST['dispatcher']['AutomaticProductAssignmentText']) ? $_POST['dispatcher']['AutomaticProductAssignmentText'] : '');
		$productId      = sanitize_text_field(isset($_POST['dispatcher']['AutomaticProductAssignmentProduct']) ? $_POST['dispatcher']['AutomaticProductAssignmentProduct'] : '');

		CorreosOficialConfig::save( 'AutomaticProductAssignmentText', $carrierName );
		CorreosOficialConfig::save( 'AutomaticProductAssignmentProduct', $productId );
		
		$product        = CorreosOficialProduct::get_product($productId);
		$productName    = '';
		if (is_array($product) && isset($product[0]) && is_object($product[0]) && property_exists($product[0], 'name')) {
			$productName = $product[0]->name;
		}

        //guardamos en el log
		if ( ( $carrierName_ant <> $carrierName ) || ( $productId_ant <> $productId ) ) {
             $filename = WP_PLUGIN_DIR . '/correosoficial/log/log_automatic_product_assignment.txt';
			$logLine = gmdate('Y-m-d H:i:s') . " Se ha modificado la asignación del transportista de origen '{$carrierName}' al producto '{$productName}'\r\n";
			@file_put_contents($filename, $logLine, FILE_APPEND);
         }
	}
}
