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

use DateTime;
use WC_Order;

/**
 * Clase de uso general (versión WordPress-only).
 *
 * Sustituye a la clase global \CorreosOficialUtils de vendor/ecommerce_common_lib/CorreosOficialUtils.php
 * eliminando todas las ramas de Prestashop. Solo expone los métodos realmente usados por el plugin.
 */
class CorreosOficialUtils {

	/**
	 * Función Genérica para traducir cadena.
	 */
	public static function translateStringsToDB( $string_from_db, $id_language, $string ) {
		if ( $id_language == 0 ) {
			return false;
		}

		$dest_array = json_decode( $string_from_db, true );
		if ( ! is_array( $dest_array ) ) {
			$dest_array = array();
		}

		$dest_array[ $id_language ] = self::replaceBadCharaters( $string );

		return json_encode( $dest_array, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Función Genérica para traducir cadena.
	 */
	public static function translateStringsFromDB( $string_from_db, $id_language ) {
		$dest_array = array();

		if ( isset( $string_from_db ) ) {
			$dest_array = json_decode( self::restoreBadCharacters( $string_from_db ), true, 512, JSON_UNESCAPED_UNICODE );
		}

		if ( ! $dest_array ) {
			return false;
		}

		if ( $id_language !== null && array_key_exists( $id_language, $dest_array ) ) {
			return $dest_array[ $id_language ];
		}
		return false;
	}

	public static function replaceBadCharaters( $string ) {
		return str_replace( "'", '__APOS__', $string );
	}

	public static function restoreBadCharacters( $string ) {
		return str_replace( '__APOS__', "'", $string );
	}

	/**
	 * Devuelve los idiomas activos de WordPress (id_lang + iso_code).
	 */
	public static function getActiveLanguages( $context = null ) {
		$available_languages = function_exists( 'get_available_languages' ) ? get_available_languages() : array();
		$array_lang          = array();

		foreach ( $available_languages as $key => $value ) {
			$id_part1            = ord( substr( $value, 0, 1 ) );
			$id_part2            = ord( substr( $value, 1, 2 ) );
			$array_lang[ $key ] = array(
				'id_lang'  => $id_part1 . $id_part2,
				'iso_code' => substr( $value, 0, 2 ),
			);
		}

		return $array_lang;
	}

	/**
	 * Rellena el selector de idiomas con los idiomas activos de WP.
	 */
	public static function fillLanguagesSelector( $active_languages, $context, $selected_language_id = '' ) {
		$smarty          = $context;
		$array_languages = array();

		foreach ( $active_languages as $language ) {
			$array_languages[ $language['id_lang'] ] = $language['iso_code'];
		}

		if ( ! empty( $array_languages ) ) {
			$smarty->assign( 'array_languages', $array_languages );
			$smarty->assign( 'selected_language_id', $selected_language_id !== '' ? $selected_language_id : '' );
		} else {
			$smarty->assign( 'selected_language', '' );
			$smarty->assign( 'selected_language_id', '' );
		}
	}

	/**
	 * Obtiene el prefijo de tablas de WordPress.
	 */
	public static function getPrefix() {
		global $wpdb;
		return $wpdb->prefix;
	}

	/**
	 * Activa trazas. Ver includes/config.php.
	 */
	public static function varDump( $text, $var, $die = null ) {
		global $co_debugCorreosOficial;
		if ( $co_debugCorreosOficial ) {
			var_dump( $text, $var );
		}

		if ( $die !== null ) {
			die( 'Error 00000: Ejecución parada: ' . $die );
		}
	}

	/**
	 * Reemplazo de caracteres unicode.
	 */
	public static function replaceUnicodeCharacters( $str ) {
		$src  = array( 'u00c1', 'u00e1', 'u00c9', 'u00e9', 'u00cd', 'u00ed', 'u00d3', 'u00f3', 'u00da', 'u00fa', 'u00d1', 'u00f1', 'u00bf' );
		$dest = array( 'Á', 'á', 'É', 'é', 'Í', 'í', 'Ó', 'ó', 'Ú', 'ú', 'Ñ', 'ñ', '¿' );
		return str_replace( $src, $dest, $str );
	}

	/**
	 * Cambia el estado de un pedido (WooCommerce).
	 */
	public static function changeOrderStatus( $idOrder, $order_status, $id_employee = 1 ) {
		$order = new WC_Order( $idOrder );

		if ( $order->set_status( $order_status ) ) {
			$order->save();
		}
	}

	/**
	 * Borra archivos temporales de la carpeta pdftmp.
	 */
	public static function deleteFiles() {
		$base_dir = self::getPluginBaseDir();
		$patterns = array( 'labels*.*', 'CEX_*.*', 'E_*.*', 'CN23*.*', 'DCAF*.*', 'DDP*.*', 'manifest_*.*' );

		foreach ( $patterns as $pattern ) {
			foreach ( glob( $base_dir . '/pdftmp/' . $pattern ) as $filename ) {
				unlink( $filename );
			}
		}
		return array( 'Resultado' => 'Etiquetas del directorio pdftmp eliminados correctamente' );
	}

	/**
	 * Saneamiento de datos usando funciones de WordPress.
	 */
	public static function sanitize( $data ) {
		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				$data[ $k ] = self::sanitize( $v );
			} else {
				$data[ $k ] = sanitize_text_field( $v );
			}
		}
		return $data;
	}

	/**
	 * Limpia los teléfonos que empiecen por 34, 351 y combinaciones.
	 */
	public static function cleanTelephoneNumber( $number ) {
		$number = str_replace( ' ', '', $number );

		if ( substr( $number, 0, 5 ) === '0034 ' ) {
			$result = substr( $number, 5 );
		} elseif ( substr( $number, 0, 5 ) === '0034-' ) {
			$result = substr( $number, 5 );
		} elseif ( substr( $number, 0, 4 ) === '0034' ) {
			$result = substr( $number, 4 );
		} elseif ( substr( $number, 0, 4 ) === '034 ' ) {
			$result = substr( $number, 4 );
		} elseif ( substr( $number, 0, 4 ) === '034-' ) {
			$result = substr( $number, 4 );
		} elseif ( substr( $number, 0, 4 ) === '+34 ' ) {
			$result = substr( $number, 4 );
		} elseif ( substr( $number, 0, 4 ) === '+34-' ) {
			$result = substr( $number, 4 );
		} elseif ( substr( $number, 0, 3 ) === '+34' ) {
			$result = substr( $number, 3 );
		} elseif ( substr( $number, 0, 3 ) === '34 ' ) {
			$result = substr( $number, 3 );
		} elseif ( substr( $number, 0, 3 ) === '34-' ) {
			$result = substr( $number, 3 );
		} elseif ( substr( $number, 0, 2 ) === '34' ) {
			$result = substr( $number, 2 );
		} elseif ( substr( $number, 0, 4 ) === '+351' ) {
			$result = substr( $number, 4 );
		} elseif ( substr( $number, 0, 5 ) === '+351-' ) {
			$result = substr( $number, 5 );
		} elseif ( substr( $number, 0, 4 ) === '+351 ' ) {
			$result = substr( $number, 4 );
		} else {
			$result = $number;
		}

		return str_replace( '-', '', $result );
	}

	/**
	 * Comprueba si el dni esta guardado como string.
	 */
	public static function nifIsAnString( $customerDni ) {
		return is_string( $customerDni ) ? $customerDni : '';
	}

	/**
	 * Escribe en el fichero correosoficial/sql/install_error.log.
	 */
	public static function writeInstallErrorLog( $line ) {
		$now       = DateTime::createFromFormat( 'U.u', microtime( true ) );
		$date_time = $now ? $now->format( 'd-m-Y H:i:s:u' ) : date( 'd-m-Y H:i:s' );

		$logMessage = $date_time . ': ' . $line . "\r\n";
		file_put_contents( self::getPluginBaseDir() . '/sql/install_error.log', $logMessage . "\r\n", FILE_APPEND );
	}

	/**
	 * Comprueba que la extensión SOAP esté cargada.
	 */
	public static function checkSoapInstalled( $error ) {
		if ( ! extension_loaded( 'soap' ) ) {
			add_action(
				'admin_notices',
				function () use ( $error ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
				}
			);
			return false;
		}
		return true;
	}

	/**
	 * Comprueba si el plugin SISLOG (correosecomsga) está activo.
	 */
	public static function sislogModuleIsActive() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'correosecomsga/correosecomsga.php' );
	}

	/**
	 * Devuelve la ruta base del plugin (equivalente a dirname(MODULE_CORREOS_OFICIAL_PATH)).
	 */
	private static function getPluginBaseDir() {
		if ( defined( 'MODULE_CORREOS_OFICIAL_PATH' ) ) {
			return dirname( realpath( MODULE_CORREOS_OFICIAL_PATH ) );
		}
		return dirname( __DIR__ );
	}
}
