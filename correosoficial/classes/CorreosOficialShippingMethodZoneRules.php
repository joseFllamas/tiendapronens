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
 * Reglas de métodos de envíos y zonas según PRODUCTO.
 *
 * Sustituye a \ShippingMethodZoneRules de vendor/ecommerce_common_lib.
 */
class CorreosOficialShippingMethodZoneRules extends CorreosOficialCarrier {

	private $national_types;
	private $exclude_CEX_90;
	private $exclude_S0360;
	private $exclude_S0361;
	private $exclude_PT;
	private $other_regions;
	private $exclude_ES;
	private $exclude_ES_PT_AD;

	public function __construct() {
		parent::__construct();

		$this->national_types = array( '18', '24', '44', '61', '62', '63', '92', '93', '26', '46', '79', '54', '27', '76', 'S0235', 'S0236', 'S0176', 'S0132', 'S0133', 'S0178', 'S0179', null );

		$this->exclude_CEX_90 = array(
			'LU', 'AT', 'BE', 'BG', 'CH', 'CZ', 'DE', 'DK', 'EE', 'FI', 'FR', 'GB',
			'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LV', 'NL', 'NO', 'PL', 'RO', 'RS', 'SE', 'SI', 'SK', 'TR',
		);

		$this->exclude_S0360 = array(
			'LU', 'SA', 'DO', 'AE', 'AT', 'AU', 'AW', 'BB', 'BE', 'BR', 'CA', 'CH', 'CN', 'CY',
			'CZ', 'DE', 'DK', 'EE', 'EG', 'FI', 'FR', 'GB', 'GE', 'GI', 'GR', 'HK', 'HR', 'HU', 'ID', 'IE', 'IL',
			'IS', 'IT', 'JE', 'JP', 'KR', 'LB', 'LT', 'LV', 'MT', 'MX', 'MY', 'NL', 'NO', 'NZ', 'PL', 'PT', 'RO',
			'RS', 'RU', 'SE', 'SG', 'SI', 'SK', 'SZ', 'TH', 'TR', 'ZA', 'US', 'KZ', 'ZW',
		);

		$this->exclude_S0361 = $this->exclude_S0360;

		$this->exclude_PT = array( '90', '91', 'S0133', 'S0176', 'S0178', 'S0236', '46', '76' );

		$this->exclude_ES       = array( '73', '90', '91', 'S0410', 'S0411', 'S0360', 'S0361', 'S0004', 'S0031' );
		$this->exclude_ES_PT_AD = array( '26', '27', '46', '54', '61', '62', '63', '73', '76', '79', '92', '93', 'S0132', 'S0133', 'S0176', 'S0178', 'S0179', 'S0235', 'S0236' );

		$this->other_regions = array( '90', 'S0410', 'S0411', 'S0004', 'S0031' );
	}

	public function isInternational( $iso, $product_type ) {
		$iso = strtoupper( $iso );
		return ( $iso !== 'ES' && $iso !== 'AD' ) && $product_type === 'international';
	}

	public function excludeCEX90( $iso, $product_code ) {
		$iso = strtoupper( $iso );
		return in_array( $iso, $this->exclude_CEX_90 ) && $product_code === '90';
	}

	public function excludeS360( $iso, $product_code ) {
		$iso = strtoupper( $iso );
		return ! in_array( $iso, $this->exclude_S0360 ) && $product_code === 'S0360';
	}

	public function excludeS361( $iso, $product_code ) {
		$iso = strtoupper( $iso );
		return ! in_array( $iso, $this->exclude_S0361 ) && $product_code === 'S0361';
	}

	public function excludeNationalProducts( $iso, $product_type ) {
		$iso = strtoupper( $iso );
		return $iso === 'PT' && in_array( $product_type, $this->exclude_PT );
	}

	public function isNational( $iso, $product_type ) {
		$iso = strtoupper( $iso );
		return ( $iso === 'ES' || $iso === 'PT' || $iso === 'AD' ) && in_array( $product_type, $this->national_types );
	}

	public function filterProducts( $zone_array ) {
		$products = array(
			'18', '24', '26', '27', '44', '46', '54', '61', '62', '63', '73', '76', '79', '90', '91', '92', '93',
			'S0132', 'S0133', 'S0176', 'S0178', 'S0179', 'S0235', 'S0236', 'S0360', 'S0361', 'S0410', 'S0411', 'S0004', 'S0031',
		);

		if ( $zone_array['id'] == 0 ) {
			return $this->other_regions;
		}

		foreach ( $zone_array['zone_locations'] as $region ) {
			$iso = self::getRegionType( $region );

			if ( $iso === null ) {
				continue;
			}
			if ( $iso === 'ES' ) {
				$products = array_diff( $products, $this->exclude_ES );
			}
			if ( $iso === 'PT' ) {
				$products = array_diff( $products, $this->exclude_PT );
			}
			if ( ! in_array( $iso, array( 'ES', 'PT', 'AD' ) ) ) {
				$products = array_diff( $products, $this->exclude_ES_PT_AD );
			}
			if ( ! in_array( $iso, $this->exclude_CEX_90 ) ) {
				$products = array_diff( $products, array( '90' ) );
			}
			if ( ! in_array( $iso, $this->exclude_S0360 ) ) {
				$products = array_diff( $products, array( 'S0360' ) );
			}
			if ( ! in_array( $iso, $this->exclude_S0361 ) ) {
				$products = array_diff( $products, array( 'S0361' ) );
			}
		}

		if ( isset( $products ) && $zone_array['zone_locations'] ) {
			return $products;
		}
	}

	/**
	 * Obtiene el código ISO país según la tabla wp_woocommerce_shipping_zone_locations.
	 */
	public static function getRegionType( $data ) {
		if ( $data->type === 'state' ) {
			return substr( $data->code, 0, 2 );
		} elseif ( $data->type === 'postcode' ) {
			if ( strstr( $data->code, '...' ) ) {
				$tokens   = explode( '...', $data->code );
				$postcode = $tokens[0];
			} else {
				$postcode = $data->code;
			}
			return self::getRegionTypeFromPostcode( $postcode );
		} elseif ( $data->type === 'country' || $data->type === 'continent' ) {
			return $data->code;
		}
	}

	private static function getRegionTypeFromPostcode( $postcode ) {
		if ( empty( $postcode ) ) {
			return null;
		}

		if ( stripos( $postcode, 'AD' ) === 0 ) {
			return 'AD';
		}

		if ( preg_match( '/^\d{5}$/', $postcode ) ) {
			$cp_num = intval( $postcode );
			if ( $cp_num >= 1000 && $cp_num <= 52999 ) {
				return 'ES';
			}
		}

		return null;
	}

	public function filterCarriers( $all_carriers, $countries ) {
		$carriers = array();

		foreach ( $all_carriers as $carrier ) {
			foreach ( $countries as $country ) {
				$add_carrier = true;
				$exclude     = false;

				if ( self::excludeCEX90( $country['iso_code'], $carrier['codigoProducto'] ) ) {
					$exclude = true;
				}
				if ( self::excludeS360( $country['iso_code'], $carrier['codigoProducto'] ) ) {
					$exclude = true;
				}
				if ( self::excludeS361( $country['iso_code'], $carrier['codigoProducto'] ) ) {
					$exclude = true;
				}
				if ( self::excludeNationalProducts( $country['iso_code'], $carrier['codigoProducto'] ) ) {
					$exclude = true;
				}

				if ( self::isInternational( $country['iso_code'], $carrier->product_type ) && ! $exclude ) {
					break;
				}

				if ( self::isNational( $country['iso_code'], $carrier['codigoProducto'] ) && ! $exclude ) {
					break;
				} else {
					$add_carrier = false;
				}
			}

			if ( $add_carrier ) {
				$carriers[] = $carrier;
			}
		}

		return $carriers;
	}

	public function getIsoS0360() {
		return $this->exclude_S0360;
	}

	public function getIsoS0361() {
		return $this->exclude_S0361;
	}
}
