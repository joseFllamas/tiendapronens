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

namespace CorreosOficial\Classes;

/**
 * Configuración de entorno (PRO/PRE) para los servicios de Correos.
 *
 * Sustituye al uso de la clase global \Config de vendor/ecommerce_common_lib/config.inc.php
 * desde el código del plugin.
 */
class CorreosOficialEnvironment {

	private static $environment = 'PRO';

	public static function getEnvironment() {
		return self::$environment;
	}

	public static function getCorreosURL() {
		if ( self::$environment === 'PRO' ) {
			return 'https://preregistroenvios.correos.es/preregistroenvios';
		}
		if ( self::$environment === 'PRE' ) {
			return 'https://preregistroenviospre.correos.es/preregistroenvios';
		}
		return null;
	}

	public static function getAnaliticaHost() {
		if ( self::$environment === 'PRO' ) {
			return 'api1.correos.es';
		}
		if ( self::$environment === 'PRE' ) {
			return 'api1.correospre.es';
		}
		return null;
	}
}
