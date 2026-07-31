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
 * Clase para adaptar el objeto params de Prestashop en Woocommerce
 */
class CorreosOficialParamsAdapter {


	/**
	 * Provee el tipo de transportista y el id_carrier en un array $params
	 * 
	 * @param  string $carrier_type Valores válidos [office, citypaq, international, homedelivery]
	 * @param  int    $id_carrier   id carrier del carrito
	 * @return array $params
	 */
	public static function getParams( $carrier_type, $id_carrier ) {
		$params['carrier_type'] = $carrier_type;
		$params['id_carrier'] = $id_carrier;

		return $params;
	}
}
