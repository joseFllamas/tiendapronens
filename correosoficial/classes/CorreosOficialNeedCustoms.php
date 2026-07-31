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
 * Clase Necesita Aduana.
 *
 * Sustituye a la clase global \NeedCustoms de vendor/ecommerce_common_lib.
 * Controla si el envío necesita aduana, ya sea interiores o exteriores.
 */
class CorreosOficialNeedCustoms {

	public static function isCustomsRequired( $cp_source, $cp_dest, $country_source, $country_dest, $is_return = false ) {
		$sc_tenerife     = 38;
		$lp_gran_canaria = 35;
		$ceuta           = 51;
		$melilla         = 52;

		$cp_source2 = is_string( $cp_source ) ? substr( $cp_source, 0, 2 ) : '';
		$cp_dest2   = substr( (string) $cp_dest, 0, 2 );

		// Reglas especiales para Paq Retorno Internacional.
		if ( $is_return && $country_source != $country_dest ) {
			$excluded_areas = array( 35, 38, 51, 52 ); // Canarias, Ceuta, Melilla.

			if ( $country_source === 'AD' || $country_dest === 'AD' ) {
				return true;
			}

			if ( $country_source === 'ES' && in_array( $cp_source2, $excluded_areas ) ) {
				return true;
			}

			if ( $country_dest === 'ES' && in_array( $cp_dest2, $excluded_areas ) ) {
				return true;
			}

			if ( $country_source === 'ES' || $country_dest === 'ES' ) {
				return false;
			}
		}

		if ( $country_source != $country_dest ) {
			return true;
		}

		if ( $cp_source2 == $cp_dest2
			|| ( $cp_source2 == $sc_tenerife && $cp_dest2 == $lp_gran_canaria )
			|| ( $cp_source2 == $lp_gran_canaria && $cp_dest2 == $sc_tenerife )
		) {
			return false;
		}

		$excluded = array( $sc_tenerife, $lp_gran_canaria, $ceuta, $melilla );

		if ( in_array( $cp_source2, $excluded ) || in_array( $cp_dest2, $excluded ) ) {
			if ( $cp_source != $cp_dest ) {
				return true;
			}
		}
		return false;
	}

	public static function isInternational( $country_source, $country_dest ) {
		if ( $country_source == $country_dest ) {
			return false;
		}

		if ( $country_source === 'ES' ) {
			return ! ( $country_dest === 'AD' || $country_dest === 'PT' );
		}

		if ( $country_source === 'AD' ) {
			return ! ( $country_dest === 'ES' || $country_dest === 'PT' );
		}

		if ( $country_source === 'PT' ) {
			return ! ( $country_dest === 'AD' || $country_dest === 'ES' );
		}

		return true;
	}
}
