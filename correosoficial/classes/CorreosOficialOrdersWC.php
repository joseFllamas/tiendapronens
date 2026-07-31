<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Classes\CorreosOficialUtils;

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


class CorreosOficialOrdersWC extends CorreosOficialOrders {

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Para versiones del plugin superiores o igual a 1.3.1.0
	 *
	 * @version >= 1.3.1.0
	 */
	public static function getRequestRecord( $id_order ) {
		$query = 'SELECT post_id FROM ' . CorreosOficialUtils::getPrefix() . 'postmeta WHERE post_id=' . $id_order . ' LIMIT 1';
		$record = self::launchQuery($query, true);

		if ($record) {
			$query = 'SELECT * FROM ' . CorreosOficialUtils::getPrefix() . "correos_oficial_requests cor
            WHERE cor.id_order = '" . $record[0]['post_id'] . "' ORDER BY cor.id DESC";
			$record = self::launchQuery($query, true);

			if ($record) {
				return $record[0];
			} else {
				return self::getRequestRecordLegacy($id_order);
			}
		}
	}

	/**
	 * Para versiones del plugin inferiores a 1.3.1.0
	 *
	 * @version < 1.3.1.0
	 */
	public static function getRequestRecordLegacy( $id_order ) {
		$query = 'SELECT meta_value FROM ' . CorreosOficialUtils::getPrefix() . 'postmeta WHERE post_id=' . $id_order . " AND meta_key = '_cart_hash'";
		$record = self::launchQuery($query, true);

		if ($record) {

			$query = 'SELECT * FROM ' . CorreosOficialUtils::getPrefix() . "correos_oficial_requests cor
             WHERE cor.id_cart = '" . $record[0]['meta_value'] . "' ORDER BY cor.id DESC";

			$record = self::launchQuery($query, true);

			if ($record) {
				return $record[0];
			}
		}
	}

	/**
	 * Para versiones del plugin que usen HPOS
	 *
	 * @version >= 1.5.0
	 */
	public static function getRequestRecordHPOS( $id_order ) {
		$query = 'SELECT * FROM ' . CorreosOficialUtils::getPrefix() . "correos_oficial_requests cor
		WHERE cor.id_order = '" . $id_order . "' ORDER BY cor.id DESC";
		$record = self::launchQuery($query, true);

		if ($record) {
			return $record[0];
		} else {
			return null;
		}
	}
}
