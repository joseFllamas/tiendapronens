<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Models\CorreosOficialConfig;

/**
 * Clase para métodos de ayuda, ya sean de formateo, normalización o limpieza,
 * muchos de ellos vendran de la clase CorreosOficialUtils, que se deprecará al quitar la librería
 */

class CorreosOficialHelpers {


	/**
	 * Limpia los teléfonos que empiecen por 34, 351 y combinaciones
	 */
	public static function cleanTelephoneNumber( $number ) {

		$number = str_replace(' ', '', $number);

		if (substr($number, 0, 5) == '0034 ') {
$result = substr($number, 5);
		} elseif (substr($number, 0, 5) == '0034-') {
		$result = substr($number, 5);
		} elseif (substr($number, 0, 4) == '0034') {
		$result = substr($number, 4);
		} elseif (substr($number, 0, 4) == '034 ') {
		$result = substr($number, 4);
		} elseif (substr($number, 0, 4) == '034-') {
		$result = substr($number, 4);
		} elseif (substr($number, 0, 4) == '+34 ') {
		$result = substr($number, 4);
		} elseif (substr($number, 0, 4) == '+34-') {
		$result = substr($number, 4);
		} elseif (substr($number, 0, 3) == '+34') {
		$result = substr($number, 3);
		} elseif (substr($number, 0, 3) == '34 ') {
		$result = substr($number, 3);
		} elseif (substr($number, 0, 3) == '34-') {
		$result = substr($number, 3);
		} elseif (substr($number, 0, 2) == '34') {
		$result = substr($number, 2);
		} elseif (substr($number, 0, 4) == '+351') {
		$result = substr($number, 4);
		} elseif (substr($number, 0, 5) == '+351-') {
		$result = substr($number, 5);
		} elseif (substr($number, 0, 4) == '+351 ') {
		$result = substr($number, 4);} else {
			$result = $number;
			}

		$result = str_replace('-', '', $result);

		return $result;
	}

	/**
	 * Limpiamos todos los números del prefijo +34 y +351
	 * Si el producto no está dentro de los de la península no tiene 9 dígitos ni empieza por 6 , 7 y 9
	 * Si el producto está dentro de la península, el destino es ES o AD, no tiene 9 dígitos ni empieza por 6 o 7
	 * Si el producto está dentro de la península, pero es un envio a PT, no tiene 9 dígitos ni empieza por 9
	 * se devuelve vacío para que no se copie en NumeroSMS
	 */
	public static function getMobilePhone( $number, $iso, $product_code = null ) {

		$products = array( 'S0132', 'S0133', 'S0178', 'S0176', 'S0235', 'S0236', 'S0179' );

		$result = self::cleanTelephoneNumber($number);

		if ($product_code !== null &&
			( strlen($result) !== 9 ||
				! ( substr($result, 0, 1) == '6' ||
					substr($result, 0, 1) == '7' ||
					substr($result, 0, 1) == '9' ) ||
				! in_array($product_code, $products) )) {
			return '';
		}

		// Comprobación adicional según el destino
		if ($iso === 'ES' || $iso === 'AD') {
			if (strlen($result) === 9 &&
				( substr($result, 0, 1) == '6' ||
					substr($result, 0, 1) == '7' )) {
				return $result; // Devuelve el número para <NumeroSMS>
			}
		} elseif ($iso === 'PT') {
			if (strlen($result) === 9 &&
				substr($result, 0, 1) == '9') {
				return $result;
			}
		}

		return '';
	}

	/**
	 * Comprueba si el dni esta guardado como string
	 *
	 * @param mixed $customerDni
	 * @return string
	 */
	public static function nifIsAnString( $dni ) {
		if (is_string($dni)) {
			return $dni;
		} else {
			return '';
		}
	}

	/**
	 * Obtenemos valor float formateado
	 *
	 * @return float
	 */
	public static function getFloatValue( $value ) {
		return floatval(str_replace(',', '.', $value));
	}

	/**
	 * Verifica si un paquete tiene dimensiones válidas.
	 *
	 * Esta función determina si un paquete tiene tamaño,
	 * comprobando que las dimensiones (largo, alto y ancho) sean mayores que cero.
	 *
	 * @param float|int $long   Largo del paquete.
	 * @param float|int $height Alto del paquete.
	 * @param float|int $width  Ancho del paquete.
	 *
	 * @return bool Devuelve `true` si todas las dimensiones son mayores que cero, de lo contrario, `false`.
	 */
	public static function hasSize( $long, $height, $width ) {
		return $long > 0 && $height > 0 && $width > 0;
	}

	/**
	 * Calcula el peso volumétrico de un paquete.
	 *
	 * El peso volumétrico se obtiene multiplicando las dimensiones del paquete
	 * (largo, alto y ancho) y dividiendo el resultado por 6.
	 * El resultado se redondea al entero más cercano.
	 *
	 * @param float|int $long   Largo del paquete.
	 * @param float|int $height Alto del paquete.
	 * @param float|int $width  Ancho del paquete.
	 *
	 * @return int Peso volumétrico redondeado.
	 */
	public static function calculateVWeight( $long, $height, $width ) {
		return round($long * $height * $width / 6);
	}

	/**
	 * Función que transforma centímetros a metros
	 *
	 * @param string $value son los centímetros que queremos convertir a metros
	 *
	 * @return string $metros son los metros
	 */
	public static function parseMeters( $value ) {
		if (empty($value)) {
			$metros = 0;
		} else {
			$metros = intval($value) / 100;
		}
		return (string) $metros;
	}

	/**
	 * Función que devuelve el tipo de documento según el formato
	 * 0: European ID, 1: DNI/NIF, 3: NIE, 10: CIF
	 *
	 * @param string $doc es el documento que queremos comprobar
	 * @return int $type es el tipo de documento
	 */
	public static function getDocumentType( $doc ) {
		$doc = strtoupper($doc); // Convertir a mayúsculas por seguridad
	
		// DNI/NIF: 8 dígitos seguidos de una letra
		if (preg_match('/^\d{8}[A-Z]$/', $doc)) {
			return 1;
		}
	
		// NIE: Empieza con X, Y o Z, seguido de 7 números y una letra final
		if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $doc)) {
			return 3;
		}
	
		// CIF: Letra inicial, 7 números y una letra final
		if (preg_match('/^[A-Z]\d{7}[A-Z0-9]$/', $doc)) {
			return 10;
		}
	
		// Si no encaja en ninguno, se considera European ID
		return 0;
	}

	public static function getOneValue($item, ...$keys) {
		foreach ($keys as $key) {
			if (isset($item[$key])) {
				return $item[$key];
			}
		}
		return null;
	}

	public static function weightToKg($weight) {

		// Sanitizar: reemplazar coma decimal por punto (locale ES puede guardar "0,4")
		$weight = floatval(str_replace(',', '.', $weight));

		switch (get_option('woocommerce_weight_unit')) {
			case 'g': 
				$weight = $weight * 0.001;
				break;
			case 'lbs':
				$weight = $weight * 0.45359237;
				break;
			case 'oz':
				$weight = $weight * 0.0283495;
				break;
		}

		$weight = round($weight, 2);

		if ($weight == 0 && (new CorreosOficialConfig('ActivateWeightByDefault'))->get_value() == 'on') {
			$weight = floatval(str_replace(',', '.', (new CorreosOficialConfig('WeightByDefault'))->get_value()));
		}

		return $weight;
	}

	/**
	 * Lee el peso de un producto desde post_meta (_weight) en crudo, evitando
	 * filtros que plugins tipo Composite/Bundle aplican sobre WC_Product::get_weight().
	 *
	 * Para variaciones sin peso propio cae al peso del producto padre.
	 *
	 * @param \WC_Product|null $product
	 * @return float Peso en la unidad configurada en WooCommerce. 0 si no hay dato.
	 */
	public static function getProductMetaWeight($product) {
		if (! $product || ! is_object($product)) {
			return 0.0;
		}

		$raw = get_post_meta($product->get_id(), '_weight', true);
		if (($raw === '' || $raw === false) && method_exists($product, 'is_type') && $product->is_type('variation')) {
			$raw = get_post_meta($product->get_parent_id(), '_weight', true);
		}

		return ($raw !== '' && $raw !== false) ? floatval($raw) : 0.0;
	}

	/**
	 *
	 * Reconoce ambas convenciones de meta:
	 *   - Order item meta: "wooco_ids"  (set por WPC Composite en add_order_item_meta)
	 *   - Order item meta: "_wooco_ids" (set en algunas versiones/custom)
	 *   - Cart item key:   "wooco_ids"  (set por WPC Composite en el carrito)
	 *   - Producto con WC_Product::is_type('composite') = true.
	 *
	 * @param \WC_Order_Item_Product|array $item    Order item o cart item.
	 * @param \WC_Product|null             $product Producto resuelto (opcional, permite cache).
	 * @return bool
	 */
	public static function isCompositeParentItem($item, $product = null) {
		if (is_array($item)) {
			if (! empty($item['wooco_ids'])) {
				return true;
			}
		} elseif (is_object($item) && method_exists($item, 'get_meta')) {
			if ($item->get_meta('wooco_ids') || $item->get_meta('_wooco_ids')) {
				return true;
			}
		}

		return $product && method_exists($product, 'is_type') && $product->is_type('composite');
	}

	/**
	 * ¿Es el ítem PADRE de un Bundle (WC Product Bundles o similar que no declara peso propio)?
	 *
	 * @param \WC_Order_Item_Product|array $item
	 * @param \WC_Product|null             $product
	 * @return bool
	 */
	public static function isBundleParentItem($item, $product = null) {
		if (is_array($item)) {
			if (! empty($item['bundled_items']) || ! empty($item['composite_children'])) {
				return true;
			}
		} elseif (is_object($item) && method_exists($item, 'get_meta')) {
			if ($item->get_meta('_bundled_items') || $item->get_meta('_composite_children')) {
				return true;
			}
		}

		return $product && method_exists($product, 'is_type') && $product->is_type('bundle');
	}

	/**
	 * Escribe un log de depuración.
	 * @param string $scope   Contexto o ámbito del log.
	 * @param string $message Mensaje a escribir en el log.
	 * @return void
	 */
	public static function writeToLog($scope, $message)
	{
		if (CORREOS_OFFICIAL_DEBUG) {
			$plugin_dir = plugin_dir_path(__FILE__);
			$log_dir = $plugin_dir . '../log/';
			if (!file_exists($log_dir)) {
				mkdir($log_dir, 0755, true);
			}

			$file = $log_dir . 'debug_' . $scope . '_' . date('Ymd') . '.txt';
			$line = date("d/m/Y H:i:s") . " " . $message . PHP_EOL;

			if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
				error_log("No se pudo escribir en el archivo de log: $file");
			}
		}
	}

	/**
	 * Escribe un log de errores del cron SGA.
	 * @param string $message Mensaje a escribir en el log.
	 * @return void
	 */
	public static function writeToCronErrorLog($message)
	{
		$plugin_dir = plugin_dir_path(__FILE__);
		$log_dir = $plugin_dir . '../log/';
		if (!file_exists($log_dir)) {
			mkdir($log_dir, 0755, true);
		}

		$file = $log_dir . 'cron_error_log.txt';
		$line = date("d/m/Y H:i:s") . " " . $message . PHP_EOL;

		if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
			error_log("No se pudo escribir en el archivo de log: $file");
		}
	}

	/**
	 * Escribe un log del "Cron de Actualización de Stock"
	 *
	 * @param string $message Mensaje a escribir en el log.
	 * @param bool   $append  Si es true, añade al final del archivo; si es false, lo sobrescribe.
	 * @return void
	 */
	public static function writeToUpdateStockCronLog($message, $append = true)
	{
		// Obtener directorio base del plugin
		$plugin_dir = plugin_dir_path(__FILE__);
		$log_dir = $plugin_dir . '../log/';

		// Crear carpeta /log si no existe
		if (!file_exists($log_dir)) {
			if (!mkdir($log_dir, 0755, true) && !is_dir($log_dir)) {
				error_log('No se pudo crear el directorio de logs: ' . $log_dir);
				return;
			}
		}

		// Definir archivo de log
		$file = $log_dir . 'update_stock_cron_log.txt';
		$line = date("d/m/Y H:i:s") . " " . $message . PHP_EOL;

		// Modo de escritura: sobrescribir o añadir
		$flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;

		// Escribir log
		if (file_put_contents($file, $line, $flags) === false) {
			error_log('No se pudo escribir en el archivo de log: ' . $file);
		}
	}

	/**
	 * Escribe un log del "Cron de Seguimiento de Estado de Pedido"
	 *
	 * @param string $message Mensaje a escribir en el log.
	 * @param bool   $append  Si es true, añade al final del archivo; si es false, lo sobrescribe.
	 * @return void
	 */
	public static function writeToOrderStatusTrackingCronLog($message, $append = true)
	{
		// Obtener directorio base del plugin
		$plugin_dir = plugin_dir_path(__FILE__);
		$log_dir = $plugin_dir . '../log/';

		// Crear carpeta /log si no existe
		if (!file_exists($log_dir)) {
			if (!mkdir($log_dir, 0755, true) && !is_dir($log_dir)) {
				error_log('No se pudo crear el directorio de logs: ' . $log_dir);
				return;
			}
		}

		// Definir archivo de log
		$file = $log_dir . 'order_status_tracking_cron_log.txt';
		$line = date("d/m/Y H:i:s") . " " . $message . PHP_EOL;

		// Modo de escritura: sobrescribir o añadir
		$flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;

		// Escribir log
		if (file_put_contents($file, $line, $flags) === false) {
			error_log('No se pudo escribir en el archivo de log: ' . $file);
		}
	}



	/**
     * Método que fusiona/agrupa los elementos del array $details por el campo "itemId"
    */
    public static function groupArrayDetailsByItemId($array)
    {
        // Recorremos el array $details y agrupamos por "itemId", sumando "quantityOrdered"
        $agrupados = [];
        foreach ($array as $item) {
            $id          = $item['itemId'];
            $cantidad    = $item['quantityOrdered'];

            if (isset($agrupados[$id])) {
                // Si el itemId ya está en el array, sumamos la cantidad
                $agrupados[$id]['quantityOrdered'] += $cantidad;
            } else {
                // Si el itemId no está en el array, creamos el elemento en el array
                $agrupados[$id] = $item;
            }
        }

        // Después de agrupar, eliminamos la clave del array "agrupados" para que sea numérica
        $details_grouped = [];
        foreach ($agrupados as $item) {
            $details_grouped [] = $item;
        }

        // Devolvemos el array agrupado 
        return $details_grouped;

    }

}
