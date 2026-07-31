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

if (!defined('WC_VERSION')) {
	die;
}




class AdminCorreosOficialActiveCustomersController {
	public function __construct() {
		global $wpdb;

		$company = sanitize_text_field(isset($_REQUEST['company']) ? $_REQUEST['company'] : '');
		$active  = sanitize_text_field(isset($_REQUEST['active'])  ? $_REQUEST['active']  : '');
		$action  = sanitize_text_field(isset($_REQUEST['action'])  ? $_REQUEST['action']  : '');

		if ($action == 'updateActiveCustomers') {
			$active_int = (int) $active;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}correos_oficial_codes_actives SET active = %d WHERE company = %s",
					$active_int,
					$company
				)
			);
			die( 'Actualizado cliente activo' );
		} elseif ($action == 'getActivesCustomers') {
			return 'both';
		}
	}
}
