<?php
namespace CorreosOficial\Classes;

use WC_Countries;

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
 * Clases para países de WC.
 */
class CorreosOficialCountriesWC {


	/**
	 * Devuelve los países de WC
	 */
	public static function getCountries() {
		$countries_obj = new WC_Countries();
		$countries_wc = $countries_obj->__get('countries');

		foreach ($countries_wc as $key => $value) {
			$countries[] = array( 'iso_code' => $key, 'id_country' => $key, 'name' => $value );
		}
		return $countries;
	}
}
