<?php
namespace CorreosOficial\Classes;

use CorreosOficial\Models\CorreosOficialConfig;
use WC_Shipping;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use CorreosOficial\Models\CorreosOficialProduct;
use LogicException;

/**
 * Clase: CorreosOficialCommonShippingMethods
 * Uso: Implementación de varios métodos comunes para la carga de los shipping methods
 */
class CorreosOficialCommonShippingMethods {


	protected static $all = null;

	/**
     * Carga todos los productos una única vez por petición
     */
    protected static function loadAll() {
        if ( null !== self::$all ) {
            return;
        }
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}correos_oficial_products",
            ARRAY_A
        );

        // Indexamos por id para mapear con metodos de envio
        self::$all = array();
        foreach ( (array) $rows as $row ) {
            self::$all[ (int) $row['id'] ] = $row;
        }
    }

	/**
	 * Función para cargar el método de envío segun el id del producto
	 *
	 * @param string $paqId proporcionado del shipping method en uso
	 * @return array devuelve toda la info de la tabla correos_oficial_products
	 */
	public static function loadShippingMethod( $paqId ) {
		self::loadAll();
		$id = (int) $paqId;

		if ( isset( self::$all[ $id ] ) ) {
			return array( self::$all[ $id ] );
		}
		return array();
	}

	/**
	 * Función para comprobar si el producto está activado en la configuracion del módulo
	 *
	 * @param string $instanceId id de la instancia de la zona de envio
	 * @return array devuelve el id, ademas del valor de 1 o 0 si está activo.
	 */
	public static function isProductEnable( $instanceId ) {
		global $wpdb;

		$productEnable = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cop.id, cop.active FROM {$wpdb->prefix}correos_oficial_products AS cop
            LEFT JOIN {$wpdb->prefix}correos_oficial_carriers_products AS cocp ON (cocp.id_product = cop.id)
            WHERE cocp.id_carrier = %d",
				$instanceId
			)
		);

		return $productEnable;
	}

	/**
	 * Funcion que comprueba si los valores son float
	 *
	 * @param string $from regla de coste (precio/peso)
	 * @param string $to regla de coste (precio/peso)
	 * @param string $cost precio del método de envio según reglas de coste
	 * @return false si no es un float.
	 */
	public static function validateData( $from, $to, $cost ) {
		if (!is_float($from) || !is_float($to) || !is_float($cost)) {
			return false;
		}
	}

	/**
	 * Convierte valores decimales localizados (coma o punto) a float.
	 *
	 * @param mixed $value valor decimal en formato string o numérico.
	 * @return float
	 */
	public static function parseDecimalToFloat( $value ) {
		if (function_exists('wc_format_decimal')) {
			return (float) wc_format_decimal($value, false, false);
		}

		if (is_string($value)) {
			$value = str_replace(',', '.', trim($value));
		}

		return (float) $value;
	}

	/**
	 * Funcion para eliminar las reglas y metodos de envios previamente eliminados
	 * en woocommerce_shipping_zone_methods.
	 */
	public static function removeRulesFromDeletedShippingMethods() {

		global $wpdb;

		// Do not delete orphan shipping rules automatically.
		// During upgrades or zone/method recreation, instance_id may be temporarily
		// desynchronized and this cleanup can remove valid pricing configuration.
		$orphan_rules = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}correos_oficial_shipping_method_rules
                WHERE instance_id NOT IN (SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods)"
		);

		if ( $orphan_rules > 0 ) {
			error_log(
				'CorreosOficial: Se detectaron ' . $orphan_rules . ' reglas huérfanas de transportista. No se eliminan automáticamente para evitar pérdida de configuración.'
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}correos_oficial_carriers_products
            WHERE id_carrier NOT IN (SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods)"
			)
		);
	}

	/**
	 * Función para cargar solo los métodos de envío en la zona.
	 *
	 * @param string $zone_id id de la zona
	 * @param  $enabled_only devuelve true o false
	 * @return array con los datos de los métodos de envio.
	 */
	public static function getMethods( $zone_id, $enabled_only ) {
		global $wpdb;
		$result = '';

		if ($enabled_only) {
			$result = $wpdb->get_results($wpdb->prepare("SELECT method_id, method_order, instance_id, is_enabled FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id = %d AND is_enabled = 1", $zone_id));
		} else {
			$result = $wpdb->get_results($wpdb->prepare("SELECT method_id, method_order, instance_id, is_enabled FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id = %d", $zone_id));
		}
		return $result;
	}

	/**
	 * Función para obtener la condición de la regla de coste
	 *
	 * @param string $condition devuelve vacio si la condicion es por coste
	 * o devuelve 'weightkg' en caso de ser por peso.
	 * @return string
	 */
	public static function getCondition( $condition ) {
		if ($condition == '') {
			return 'weightkg';
		}
		return $condition;
	}

	/**
	 * Función para saber que opción está seleccionada
	 *
	 * @param string $option es el nombre de la clase a comprobar
	 * @param string $selected es el nombre de la clase seleccionada
	 * @return 'selected' si coinciden el nombre de las 2 variables
	 */
	public static function isSelected( $option, $selected ) {

		if ($option == $selected) {
			return 'selected';
		}
	}

	/**
	 * Funcion para obtener los valores de las reglas del metodo de envio.
	 *
	 * @param string instance_id del metodo de la zona a mirar
	 * @return array de reglas de costes
	 */
	public static function getValues( $instance_id ) {
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}correos_oficial_shipping_method_rules WHERE instance_id= %d ORDER BY class,`condition`, `from`", $instance_id));
	}

	/**
	 * Funcion que devuelve el coste del envío según el rango de coste
	 *
	 * @param string $class nombre del método de envio
	 * @param string $total_cost coste de la regla de coste
	 * @param string $instance_id instancia del método
	 */
	public static function getCostFromRule( $class, $total_cost, $instance_id ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cost FROM {$wpdb->prefix}correos_oficial_shipping_method_rules
                WHERE class = %s
                AND `condition` = 'cost'
                AND %f >= `from`
                AND (%f <= `to` OR `to` = 0)
                AND instance_id = %d
                ORDER BY id DESC",
				$class,
				$total_cost,
				$total_cost,
				$instance_id
			)
		);
		return $result;
	}

	/**
	 * Funcion que devuelve el coste según el rango de peso
	 *
	 * @param string $class nombre del metédo de envío
	 * @param float $weight peso estipulado en la regla de coste
	 * @param string $instance_id id del método de envío seleccionado
	 * @return string coste de la regla
	 */
	public static function getCostByWeight( $class, $weight, $instance_id ) {
		global $wpdb;

		// El peso ya llega convertido a kg desde getTotalWeight(), no reconvertir
		$weight = round((float) $weight, 2);

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cost FROM {$wpdb->prefix}correos_oficial_shipping_method_rules
                WHERE class = %s
                AND `condition` = 'weightkg'
                AND %f >= `from`
                AND (%f <= `to` OR `to` = 0)
                AND instance_id = %d",
				$class,
				$weight,
				$weight,
				$instance_id
			)
		);

		if ($result) {
			return $result[0]->cost;
		}

		return false;
	}

	/**
	 * Funcion para obtener la condición de la regla según el método de envío
	 *
	 * @param string $class nombre de la clase de envio
	 * @param string $instance_id id de la instancia del método de envío.
	 * @return array con las condiciones de las reglas de coste
	 */
	public static function getRuleCondition( $class, $instance_id ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `condition` FROM {$wpdb->prefix}correos_oficial_shipping_method_rules
						WHERE class = %s AND instance_id = %d
						GROUP BY `condition`
						ORDER BY `condition` ASC",
				$class,
				$instance_id
			)
		);

		if ($result) {
			return $result[0]->condition;
		}
	}

	/**
	 * Funcion para obtener el peso total de los paquetes
	 *
	 * @param array $packages datos de cada paquete
	 * @return float $total_weight peso total de los paquetes
	 */
	public static function getTotalWeight( $packages ) {
		$total_weight = 0;

		foreach ($packages['contents'] as $cart_item_key => $item_id) {
			$product = $item_id['data'];

			// Saltar items PADRE de composites/bundles
			// (no filtrar por tipo de producto: un hijo puede ser wooco/composite)
			if (!empty($item_id['wooco_ids']) || !empty($item_id['composite_children']) || !empty($item_id['bundled_items'])) {
				continue;
			}

			// Leer peso desde _weight en post_meta (evita filtros de plugins composite)
			$raw = get_post_meta($product->get_id(), '_weight', true);
			if (($raw === '' || $raw === false) && $product->is_type('variation')) {
				$raw = get_post_meta($product->get_parent_id(), '_weight', true);
			}
			$weight = ($raw !== '' && $raw !== false) ? (float) $raw : 0;

			$total_weight += $weight * $item_id['quantity'];
		}

		switch (get_option('woocommerce_weight_unit')) {
			case 'g': 
				$total_weight = $total_weight * 0.001;
				break;
			case 'lbs':
				$total_weight = $total_weight * 0.45359237;
				break;
			case 'oz':
				$total_weight = $total_weight * 0.0283495;
				break;
		}

		$total_weight = round($total_weight, 2);

		return $total_weight;
	}

	/**
	 * Funcion para obtener el coste total de los paquetes
	 *
	 * @param array $packages datos de cada paquete
	 * @return float $line_total coste total de los paquetes
	 */
	public static function getTotalCost( $packages ) {
		$line_total = 0;

		foreach ($packages['contents'] as $content) {
			$line_total += $content['line_total'] + $content['line_tax'];
		}

		return $line_total;
	}

	/**
	 * Funcion para cargar el formulario por defecto de cada metodo de envio
	 *
	 * @param array $paqDara datos del producto de correos por cada metodo de envio.
	 * @return array $form
	 */
	public static function loadDefaultFormFields( $paqData ) {
		$form = array(
			'title' => array(
				'type' => 'text',
				'title' => __('Title', 'correosoficial'),
				'description' => __('Title to be displayed on site', 'correosoficial'),
				'default' => __($paqData['name'], 'correosoficial'),
			),
			'tax_status' => array(
				'title' => __('Tax status', 'woocommerce'),
				'type' => 'select',
				'class' => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __('Taxable', 'woocommerce'),
					'none' => _x('None', 'Tax status', 'woocommerce'),
				),
			),
			'cost' => array(
				'type' => 'text',
				'title' => __('Fixed Fee', 'correosoficial'),
				'description' => __('Minimum fee to be paid in any case', 'correosoficial'),
				'default' => '',
			),
			'max_weight' => array(
				'type' => 'number',
				'title' => __('Max weight', 'correosoficial'),
				'description' => __('Enter max weight', 'correosoficial'),
				'default' => '',
			),
		);

		return $form;
	}

	/**
	 * Funcion para guardar las reglas de costes en la bd
	 *
	 * @param string $table nombre de la tabla
	 * @param string $instance_id id de la instancia del método de envio
	 * @param array $data contenido de cada regla de coste
	 */
	public static function saveCostRules( $table, $instance_id, $data ) {
		global $wpdb;

		$wpdb->insert(
			$table,
			array(
				'instance_id' => $instance_id,
				'id_rule' => sanitize_key($data['rule_num']),
				'class' => sanitize_key($data['classes']),
				'condition' => sanitize_key($data['conditions']),
				'from' => $data['from'],
				'to' => $data['to'],
				'cost' => str_replace(',', '.', $data['cost']),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%f', '%f' )
		);
	}

	/**
	 * Funcion para añadir los botones de eliminar o añadir
	 * regla de coste
	 *
	 * @return string
	 */
	public static function createButtons() {
		return '
            <tr>
                <td id="buttonsSet" colspan="5">
                    <input type="button" id="addRule" class="co_shipping_buttons" value="' . __('Add rule', 'correosoficial') . '" />
                    <input type="button" id="deleteRule" class="co_shipping_buttons"  value="' . __('Delete rule', 'correosoficial') . '" />
                    </td>
                <td colspan="3"></td>
            <tr>';
	}

	/**
	 * Funcion para aplicar las reglas al método de envio
	 *
	 * @param array $packages datos de cada paquete del envio
	 * @return string según el tipo de producto tendra un regla de coste diferente
	 */
	public static function getRuleToApply( $packages ) {
		$found_shipping_classes = self::findShippingClasses($packages);
		$shipping_classes = array();

		foreach ($found_shipping_classes as $shipping_class => $shipping_class_id) {
			$shipping_classes[] = $shipping_class_id;
		}

		if (count($found_shipping_classes) > 1) {
			return 'allproducts';
		} elseif (!$shipping_classes[0]) {
			return 'productswithoutclass';
		} else {
			return $shipping_classes[0];
		}
	}

	/**
	 * Funcion para encontrar las clases de envio
	 *
	 * @param array $package datos de los paquetes
	 * @return array con las clases de envio encontrados
	 */
	public static function findShippingClasses( $package ) {
		$found_shipping_classes = array();

		foreach ($package['contents'] as $item_id => $values) {
			if ($values['data']->needs_shipping()) {
				$found_class = $values['data']->get_shipping_class();
				$found_class_id = $values['data']->get_shipping_class_id();

				if ($found_class_id) {
					$found_shipping_classes[$found_class] = $found_class_id;
				} else {
					$found_shipping_classes[$found_class] = '';
				}
			}
		}

		return $found_shipping_classes;
	}

	/**
	 * Funcion para añadir los productos de correos a la zona
	 *
	 * @param string $zone_id identificador de la zona
	 */
	public static function createProducts( $zone_id, $paqId ) {
		global $temp_path;

		// Zona existente o Ubicaciones no cubiertas por tus otras zonas
		if ($zone_id || $zone_id == 0) {
			$carrier = new CorreosOficialCarrier();

			$product = CorreosOficialProduct::get_product($paqId);

			$carrier->addCarrierFromZone($product, $zone_id);

		} elseif (!$zone_id) {

			wp_send_json_error(
				array(
					'success' => false,
					'data' => 'missing_fields',
				)
			);
		}
	}

	/**
	 * Funcion para saber si tenemos que cargar los transportistas
	 *
	 * @return boolean true o false
	 */
	public static function mustLoadCarriers() {
		global $pagename;

		$saving_changes = sanitize_text_field(isset($_GET['woocommerce_shipping_zone_methods_save_changes'])
			? $_GET['woocommerce_shipping_zone_methods_save_changes'] : '');

		$return = true;

		if ($pagename != 'carrito' && $saving_changes) {
			// Si no estamos en métodos de envíos y checkout salimos
			if (
				( !isset($_GET['page']) && !isset($_GET['wc-ajax']) ) ||
				( isset($_GET['page']) && $_GET['page'] != 'wc-settings' ) ||
				( isset($_GET['wc-ajax']) && $_GET['wc-ajax'] != 'update_order_review' &&
				isset($_GET['wc-ajax']) && $_GET['wc-ajax'] != 'order_update_review' )
			) {
				$return = false;
			}

		}
		return $return;
	}

	/**
	 * Funcion para crear las celdas del formulario de reglas de coste
	 *
	 * @param array $paqData array con datos necesarios del metodo de envio
	 * @return string html escrito para ser pasado al front
	 */
	public static function createCell( $paqData ) {
		$cells = '';
		$rules = 0;

		$cost = '';
		$class = '';
		$condition = '';
		$from = '';
		$to = '';

		$values = self::getValues($paqData['instance_id']);

		$rules = empty($values) ? 1 : count($values);

		for ($i = 0; $i < $rules; $i++) {

			if (isset($values[$i])) {
				$cost = $values[$i]->cost;
				$class = $values[$i]->class;
				$condition = $values[$i]->condition;
				$from = $values[$i]->from;
				$to = $values[$i]->to;
			}

			if (wc_get_price_decimal_separator() == ',') {
				$cost = str_replace('.', ',', $cost);
			}

			$rule_num = $i + 1;
			$cells .= '<tr id="ruleSet' . $rule_num . '" class="ruleOptions">
                <td>
                <input type="checkbox" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][value]" value="' . $paqData['instance_id'] . '" />
                <input type="hidden" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][rule_num]" value="' . $rule_num . '" />
                <select class="co_selectType" data-type="class" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][classes]">
                        <option value="productswithoutclass" ' . self::isSelected('productswithoutclass', $class) . '>' . __('Products without class', 'correosoficial') . '</option>
                        <option value="allproducts" ' . self::isSelected('allproducts', $class) . '>' . __('All Products', 'correosoficial') . '</option>
                        ' . self::getShippingClasses($class) . '
                        </select>
                </td>
                <td><select data-type="condition" class="condition" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][conditions]" value="' . get_option('woocommerce_' . $paqData['request_shipping_quote'] . '_conditions_1') . '">
                        <option value="weightkg" ' . self::isSelected('weightkg', $condition) . '>' . __('Weight', 'correosoficial') . ' (Kg) </option>
                        <option value="cost" ' . self::isSelected('cost', $condition) . '>' . __('Cost', 'correosoficial') . ' (€) </option>
                </td>
                <td><input data-type="from" data-condition="' . self::getCondition($condition) . '" class="range co_shipping_inputs" type="text" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][from]" value="' . $from . '" placeholder="0.00" /></td>
                <td><input data-type="to" data-condition="' . self::getCondition($condition) . '" class="range co_shipping_inputs" type="text" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][to]" value="' . $to . '" placeholder="0.00" /></td>
                <td><input class="co_shipping_inputs" type="text" name="woocommerce_' . $paqData['id_rule'] . '[' . $rule_num . '][cost]" value="' . $cost . '" placeholder="0.00" /></td>';
		}

		if (version_compare(WC_VERSION, '8.3', '>')) {
			$cells .= '</table>';
		}

		return $cells;
	}

	/**
	 * Funcion para obtener las clases de envio
	 *
	 * @param string $classId identificador de la clase de envio
	 * @return string de opciones para el selector igual al numero de clases de envio
	 */
	public static function getShippingClasses( $classId ) {
		$option = '';
		$name = '';
		$shippings = new WC_Shipping();
		$shipping_classes = array();

		$shipping_classes = $shippings->get_shipping_classes();

		if (!count($shipping_classes)) {
			return $option;
		}

		foreach ($shipping_classes as $class) {
			$name = sanitize_key(strtolower($class->name));
			$option .= '<option ' . self::isSelected($classId, $class->term_id) . ' value=' . $class->term_id . " title='" . $class->description . "'>" . $class->name . '</option>';
		}
		return $option;
	}

	/**
	 * Funcion para obtener las reglas de coste
	 *
	 * @param string $class nombre de la clase de envio
	 * @param string $condition condicion segun peso/coste
	 * @param string $total_cost coste total de la regla
	 * @param string $weight peso de la regla
	 * @return string devuelve el coste del envio para el pedido
	 */
	public static function getRuleCost( $class, $condition, $subtotal, $weight, $instance_id ) {
		// Mantener precisión original del subtotal para evitar redondeos prematuros.
		$subtotal = (float) $subtotal;

		// Si no hay condición (no hay reglas configuradas), devolver 0 para que
		// se aplique la cuota fija o se muestre como envío gratuito
		if (empty($condition)) {
			return 0;
		}

		if ($condition == 'cost') {
			$result = self::getCostFromRule($class, $subtotal, $instance_id);
		} else {
			return self::getCostByWeight($class, $weight, $instance_id);
		}

		if (!empty($result)) {
			return $result->cost;
		}

		// Fallback a regla por peso; puede devolver false si no existe regla
		return self::getCostByWeight($class, $weight, $instance_id);
	}

	/**
	 * Funcion para guardar los carriers de la zona
	 *
	 * @param string $paqId identificador del producto de correos
	 * @throws LogicException 21010 y 12050
	 */
	public static function saveCarriers( $paqId, $paqData, $zoneId ) {
		if (isset($_POST['wc_shipping_zones_nonce'])) {
			$nonce = sanitize_text_field($_POST['wc_shipping_zones_nonce']);
			if (!wp_verify_nonce(wp_unslash($nonce), 'wc_shipping_zones_nonce')) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_send_json_error('bad_nonce');
				wp_die();
			}
		}

		if (!isset($_POST['zone_id'])) {
			throw new LogicException('Error 21010 : Error técnico: No se encuentra el $zone_id');
		}

		global $temp_path;

		$zone_id = wc_clean(wp_unslash($_POST['zone_id']));
		$zone = new WC_Shipping_Zone($zone_id);

		self::removeRulesFromDeletedShippingMethods();
		self::createProducts($zone_id, $paqId);
	}

	/**
	 * Comprueba si un producto está habilitado o cumple con los filtros
	 * para mostrarse en una zona determinada.
	 *
	 * @param int $id_zone El ID de la zona de envío.
	 * @param int $paqId El ID del paquete/producto.
	 * @param int|null $instance_id El ID de la instancia (opcional).
	 * @return bool Devuelve true si el producto está habilitado para mostrarse en la zona especificada.
	 */
	public static function showInZoneFilter( $id_zone, $paqId, $instance_id ) {

		global $wpdb;
		// Comprobamos si el producto está activo está activo cuando se llama sin instance_id
		$productActive = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cop.id, cop.active FROM {$wpdb->prefix}correos_oficial_products AS cop
            WHERE cop.id = %d",
				$paqId
			)
		);

		if ($productActive[0]->active == 0 && !$instance_id) {
			return false;
		}

		// Al crear zona, mostramos el producto
		if ($id_zone == 'new') {
			return true;
		}

		$zone = WC_Shipping_Zones::get_zone($id_zone);
		$product = self::loadShippingMethod($paqId);

		$zoneArray = array();
		$zoneArray['id'] = $zone->get_id();
		$zoneArray['zone_locations'] = $zone->get_zone_locations();

		$carrierClass = new CorreosOficialCarrier();
		$filtered_products = $carrierClass->getFilteredProducts($zoneArray);

		if ($filtered_products != null && in_array($product[0]['codigoProducto'], $filtered_products)) {
			return true;
		} else {
			return false;
		}
	}

	public static function getProductDimensions( $packages )	{
		$articles = [];

		if ( ! isset( $packages['contents'] ) || ! is_array( $packages['contents'] ) ) {
			return $articles;
		}

		foreach ( $packages['contents'] as $item ) {
			if ( ! isset( $item['data'] ) || ! is_object( $item['data'] ) ) {
				continue;
			}

			/** @var WC_Product $product */
			$product = $item['data'];
			$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;

			$articles[] = [
				'length'   => floatval( $product->get_length() ),
				'width'    => floatval( $product->get_width() ),
				'height'   => floatval( $product->get_height() ),
				'quantity' => $quantity,
			];
		}

		return $articles;
	}


	public static function showCarrier($weight, $articles) {

		// Medida perimetral
		$perimetral = 0;
		// Unidades totales del carrito
		$units = 0;

		foreach ( $articles as $d ) {
			if ( isset($d['quantity']) ) {
				$units += (int) $d['quantity'];
			}
		}

		$ActivateDimensionsByDefault = ( new CorreosOficialConfig('ActivateDimensionsByDefault') )->get_value();

		// Dimensiones por defecto
		if ( $ActivateDimensionsByDefault == 'on' ) {
			$large  = (int) ( new CorreosOficialConfig('DimensionsByDefaultLarge') )->get_value();
			$width  = (int) ( new CorreosOficialConfig('DimensionsByDefaultWidth') )->get_value();
			$height = (int) ( new CorreosOficialConfig('DimensionsByDefaultHeight') )->get_value();
			$perimetral = $large + $width + $height;
		}
		
		// Si en el carrito hay un solo una unidad de un artículo y este tiene 
        // dimensiones, se toman las del artículo.
		if ((int) count($articles) && $units == 1 &&
			$articles[0]['length'] > 0 &&
			$articles[0]['width'] > 0 &&
			$articles[0]['height'] > 0
		) {
			$perimetral = (int) $articles[0]['length'] + (int) $articles[0]['width'] + (int) $articles[0]['height'];
		}

		// Conversión de peso según unidad
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

		// Validaciones finales
		if (
			$weight > 15 || // Peso máximo
			($units > 1 && !$perimetral) || // Varias unidades sin dimensiones válidas
			$perimetral < 26 || // Perímetro mínimo
			$perimetral > 150 // Perímetro máximo
		) {
			return false;
		}

		return true;
	}

	/**
	 * Funcion para devolver un string con codigo jquery.
	 *
	 * @param string $id identificador del producto.
	 * @param string $instanceId identeficador del método de envio.
	 * @return string codigo jquery para javascript.
	 */
	public static function loadCommonScript( $id, $instanceId, $idMethod ) {
		if (version_compare(WC_VERSION, '8.2.0', '>')) {
			$wooVersion = 'new';
		} else {
			$wooVersion = 'old';
		}

		return 'jQuery( function( $ ) {

            let temp_methods = [];
            let assigned_instance_id = ' . $instanceId . ";

            // Detectamos si ya existe una instancia del método de envío en la zona
            let table = $('table.wc-shipping-zone-methods tbody');
            let tr = table.find('tr[data-id=\"" . $instanceId . "\"]');
            if(tr.length > 0){
                $(document).on('wc_backbone_modal_loaded', function() {
                    displayMethod(this,'" . $wooVersion . "', 'hide');
                });
            }

            // Detectamos llamadas ajax
            jQuery(document).ajaxComplete(function(event, xhr, settings) {
                if (settings.url.includes('admin-ajax.php')) {


                    // Detectamos que se ha añadido un método de envío a una zona
                    if (settings.url && settings.url.includes('action=woocommerce_shipping_zone_methods_save_settings')
                    || settings.url.includes('action=woocommerce_shipping_zone_add_method')) {

                        let settingsData = settings.data;

                        // Obtenemos el instance_id asignado
                        temp_methods = xhr.responseJSON.data.methods;
                        Object.keys(temp_methods).forEach(instance_id => {
                            if (temp_methods[instance_id].id === '" . $id . "') {
                                assigned_instance_id = instance_id;

                                // Detectamos apertura de modal añadir método de envío
                                $(document).on('wc_backbone_modal_loaded', function() {
                                    displayMethod(this,'" . $wooVersion . "', 'hide');
                                });
                            }
                        });
                    }


                    if (settings.url && settings.url.includes('action=woocommerce_shipping_zone_remove_method')) {

                        let instance_id = xhr.responseJSON.data.instance_id;

                        $(document).on('wc_backbone_modal_loaded', function() {
                            if(instance_id == assigned_instance_id) {
                                displayMethod(this,'" . $wooVersion . "', 'show');
                            }
                        });
                    }

                    if(settings.url && settings.url.includes('action=woocommerce_shipping_zone_methods_save_changes')) {
                        window.location.reload();
                    }

                }
            });

            function displayMethod (instance, version, action) {

                switch(version) {
                    case 'old':
                            if(action == 'hide') {
                                let selectedOption = $(instance).find('select[name=\"add_method_id\"] option').filter(function() {
                                    return $(this).val() =='" . $idMethod . "';
                                });

                                if (selectedOption.length) {
                                    selectedOption.hide();
                                }
                            } else {
                                let selectedOption = $(instance).find('select[name=\"add_method_id\"] option').filter(function() {
                                    return $(this).val() =='" . $idMethod . "';
                                });

                                if (selectedOption.length) {
                                    selectedOption.show();
                                }
                            }
                        break;
                    case 'new':
                        if(action == 'hide') {
                            $(instance).find('#" . $id . "').closest('div').hide();
                        } else {
                            $(instance).find('#" . $id . "').closest('div').show();
                        }
                        break;
                }
            }

        });";
	}
}

// Global alias for backward compatibility with non-namespaced shipping method files
if ( ! class_exists( 'CorreosOficialCommonShippingMethods', false ) ) {
class_alias( \CorreosOficial\Classes\CorreosOficialCommonShippingMethods::class, 'CorreosOficialCommonShippingMethods' );
}
