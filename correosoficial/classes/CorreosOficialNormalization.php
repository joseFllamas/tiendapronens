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

use SplFileInfo;

/**
 * Clase Normalizar Datos (versión WordPress-only).
 *
 * Sustituye a la clase global \Normalization de vendor/ecommerce_common_lib/Commons/Normalization.php.
 * Controla que los datos necesarios entren en la BBDD sin espacios y en mayúsculas.
 * Filtra y sanea los datos de entrada.
 */
class CorreosOficialNormalization {

	private static $regex       = "/^[a-zA-Z0-9\.\-_=ªº°’€`´ÁÉÍÓÚáéíóúàèìòùÀÈÌÒÙÑñÇçüÜÂâÃãÊêÔôÕõ():,*\/¿?“·$%&[\]{}\^\+\;\<\>\|\~\#!¡@ åÄßÖÆØŒÐÞŐŰŁĆĐ]+$/";
	private static $regexpasswd = "/^[a-zA-Z0-9\!\"\\@\$%'#\(\)\*\+,\-\.\/\:;\=\>\?@\[\]\^_`\{\|\}~]+$/";

	/**
	 * @param mixed  $input campo a introducir en la BD
	 * @param string $type  tipo de campo (alfanumérico, email, etc).
	 */
	public static function normalizeData( $input, $type = 'alphanumeric' ) {

		$input = trim( $input );
		$input = self::getData( $input, $type );

		if ( is_array( $input ) ) {

			$output = array();
			$n      = 0;

			foreach ( $input as $key => $value ) {

				if ( is_array( $value ) || is_array( $key ) ) {
					$output[ $n ] = array();
					foreach ( $value as $key => $value2 ) {

						if ( is_array( $value2 ) ) {
							$first_value = reset( $value2 );
							if ( is_array( $first_value ) ) {
								$first_value = reset( $first_value );
							}

							$value2 = is_scalar( $first_value ) ? trim( (string) $first_value ) : '';
						} else {
							$value2 = trim( (string) $value2 );
						}

						$output[ $n ][ $key ] = self::sanitize( $value2, $type );
					}
					$n++;
				} else {
					$type_data      = self::isEmail( $value, $type );
					$output[ $key ] = self::sanitize( $value, $type_data, $key );
				}
			}
		} else {
			$output = self::sanitize( $input, $type );
		}

		return $output;
	}

	public static function toUpperCase( $input ) {
		$exceptions = array( 'on', 'true', 'false', 'Correos', 'CEX' );

		if ( ! in_array( $input, $exceptions, true ) ) {
			$input = strtoupper( $input );
		}

		return $input;
	}

	/**
	 * Sanea los datos de entrada según el tipo.
	 */
	public static function sanitize( $input, $type, $key = null ) {
		if ( $type !== 'password' ) {
			$input = trim( $input, "'" );
			$input = str_replace( '\\', '', $input );
			$input = str_replace( "'", '', $input );
		}

		if ( is_integer( $input ) ) {
			$input = filter_var( $input, FILTER_VALIDATE_INT );
		} elseif ( $type === 'email' ) {
			$input = filter_var( $input, FILTER_VALIDATE_EMAIL );
		} elseif ( $type === 'user' || $type === 'password' ) {
			$input = trim( $input );
			$input = self::replaceDoubleQuote( $input );
			$input = self::replaceBar( $input );
			$input = filter_var( $input, FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => self::$regexpasswd ) ) );
			$input = self::restoreBar( $input );
		} elseif ( $type === 'cookie_cart' || $type === 'no_uppercase' ) {
			$input = filter_var( $input, FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => self::$regex ) ) );
		} elseif ( $type === 'nospaces' ) {
			$input = preg_replace( '/\s+/', '', $input );
		} else {
			$exclude_fields = array(
				'customer_cp',
				'customer_firstname',
				'customer_lastname',
				'customer_company',
				'customer_contact',
				'customer_address',
				'customer_city',
				'customer_phone',
				'customer_dni',
			);
			if ( ! in_array( $key, $exclude_fields, true ) ) {
				$input = filter_var( $input, FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => self::$regex ) ) );
			}

			$input = self::replaceQuote( $input );
			$input = self::toUpperCase( $input );
			$input = self::restoreQuote( $input );
		}
		return $input;
	}

	/**
	 * Adecuación del nombre del fichero.
	 */
	public static function filterFiles( $targetPath ) {
		$info        = new SplFileInfo( $targetPath );
		$full_name   = $info->getBaseName();
		$ext         = $info->getExtension();
		$allowed_ext = array( 'png', 'jpg', 'jpeg' );

		if ( ! in_array( $ext, $allowed_ext, true ) ) {
			return 'ERROR: 12010';
		}

		$name       = basename( $full_name, '.' . $ext );
		$targetPath = filter_var( $name, FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => self::$regex ) ) );
		$targetPath = $targetPath . '.' . $ext;

		return $targetPath;
	}

	/**
	 * Retorna el dato según la entrada (versión WordPress-only).
	 */
	private static function getData( $input, $type ) {
		if ( $type === 'value' ) {
			return $input;
		}

		if ( $type === 'cookie_cart' ) {
			return $input;
		}

		if ( $type === 'cookie' ) {
			return isset( $_COOKIE[ $input ] ) ? $_COOKIE[ $input ] : '';
		}

		return isset( $_REQUEST[ $input ] ) ? $_REQUEST[ $input ] : '';
	}

	private static function isEmail( $value, $type ) {
		return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? 'email' : $type;
	}

	public static function replaceQuote( $input ) {
		return str_replace( array( '’', '`', '´' ), '__QUOTE__', $input );
	}

	public static function restoreQuote( $input ) {
		return str_replace( '__QUOTE__', '’', $input );
	}

	public static function replaceBar( $input ) {
		return str_replace( '\\', '__BAR__', $input );
	}

	public static function restoreBar( $input ) {
		return str_replace( '__BAR__', '\\', $input );
	}

	public static function replaceDoubleQuote( $input ) {
		return str_replace( '\"', '"', $input );
	}
}
