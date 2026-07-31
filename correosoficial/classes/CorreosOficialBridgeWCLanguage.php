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
 * Bridge entre WooCommerce y los idiomas del plugin.
 *
 * Sustituye a \BridgeWCLanguage de vendor/ecommerce_common_lib.
 */
class CorreosOficialBridgeWCLanguage {

	/**
	 * Compara iso_code con array de idiomas instalados en WooCommerce y devuelve el id_language configurado.
	 */
	public static function getIdLanguageByIsoCode( $iso_code ) {
		$iso_code   = substr( (string) $iso_code, 0, 2 );
		$array_lang = CorreosOficialUtils::getActiveLanguages();
		foreach ( $array_lang as $lang ) {
			if ( $lang['iso_code'] === $iso_code ) {
				return $lang['id_lang'];
			}
		}
		return null;
	}

	/**
	 * Devuelve un array con los id e iso_code de WordPress.
	 *
	 * @deprecated Usa CorreosOficialUtils::getActiveLanguages() en su lugar.
	 */
	public static function getLanguagesFromWC() {
		return CorreosOficialUtils::getActiveLanguages();
	}
}
