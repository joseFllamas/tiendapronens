<?php
use CorreosOficial\Classes\CorreosOficialUtils;

require_once __DIR__ . '/../../classes/CorreosOficialCarrier.php';

global $table;
global $temp_path;

$table = CorreosOficialUtils::getPrefix() . 'correos_oficial_shipping_method_rules';
$temp_path = __DIR__ . '/../';

add_action('woocommerce_shipping_init', 'request_shipping_quote_method_19');

function request_shipping_quote_method_19() {
	if (!class_exists('WC_Paq_Estandar_Oficina_Elegida')) {

		class WC_Paq_Estandar_Oficina_Elegida extends WC_Shipping_Method {
		

			private $max_weight;
			private $cost;
			private $id_rule;
			private $info;
			private $charge_rules;
			private $paqData = array();
			private static $paqId = '19';
			private static $scriptLoaded = false;

			public static function getPaqId() {
				return self::$paqId;
			}

			public function __construct( $instance_id = 0 ) {

				$zone_id = '';

				if (!CorreosOficialCommonShippingMethods::mustLoadCarriers()) {
					return;
				}

				if (isset($_REQUEST['zone_id'])) {
					$zone_id = wc_clean(wp_unslash($_REQUEST['zone_id']));

					// Comprobamos si aplica a la zona
					if (!CorreosOficialCommonShippingMethods::showInZoneFilter($zone_id, self::getPaqId(), $instance_id)) {
						return;
					}
				}

				$wszds = new WC_Shipping_Zone_Data_Store();
				$zone_method_id = $wszds->get_zone_id_by_instance_id($instance_id);

				if (isset($_REQUEST['zone_id']) && isset($zone_method_id)) {
					if ($zone_method_id != $zone_id && 0 != $zone_method_id) {
						return;
					}
				}

				global $temp_path;
				$this->id_rule = 'request_shipping_quote_' . $instance_id;

				$shippingMethod = CorreosOficialCommonShippingMethods::loadShippingMethod(self::getPaqId());

				// Cargar variables locales
				$this->paqData['name'] = $shippingMethod[0]['name'];
				$this->paqData['request_shipping_quote'] = 'request_shipping_quote_' . $shippingMethod[0]['id'];
				$this->paqData['instance_id'] = absint($instance_id);
				$this->paqData['id_rule'] = $this->id_rule;

				$this->id = 'request_shipping_quote_' . $shippingMethod[0]['id'];
				$this->instance_id = absint($instance_id);
				$this->method_title = $shippingMethod[0]['name'];
				$this->method_description = __('Allows you to configure a fixed fee, max. weight and cost rules', 'correosoficial');
				$this->supports = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

				$this->init();

				add_action('woocommerce_update_options_shipping_methods' . $this->id, array( $this, 'process_admin_options' ));

				if (isset($_REQUEST['action']) && ( $_REQUEST['action'] == 'woocommerce_shipping_zone_add_method' )) {
					CorreosOficialCommonShippingMethods::saveCarriers(self::getPaqId(), $this->paqData, $zone_id);
				}
			}

			// Load the settings API
			private function init() {
				$this->init_form_fields();
				$this->init_settings();

				global $wpdb;

				if (isset($_REQUEST['zone_id'])) {
					$zone_id = wc_clean(wp_unslash($_REQUEST['zone_id']));

					$product = CorreosOficialCommonShippingMethods::isProductEnable($this->instance_id);

					// Se desactiva en wc_shipping_zone_methods si está desactivado en Ajustes->Productos
					if (count($product) && $product[0]->active == 0) {
						$wpdb->update(
							CorreosOficialUtils::getPrefix() . 'woocommerce_shipping_zone_methods',
							array( 'is_enabled' => '0' ),
							array( 'zone_id' => $zone_id, 'instance_id' => $this->instance_id )
						);
					}
				}

				if (isset($zone_id)) {
					$this->enqueue_admin_js($this->id, $this->instance_id);
				}

				$this->enabled = $this->get_option('enabled', 'yes');
				$this->title = $this->get_option('title', 'correosoficial');
				$this->info = $this->get_option('info', 'correosoficial');
				$this->cost = CorreosOficialCommonShippingMethods::parseDecimalToFloat($this->get_option('cost'));
				$this->max_weight = (float) $this->get_option('max_weight');
				$this->charge_rules = $this->get_option('charge_rules');
				$this->tax_status = $this->get_option('tax_status');
			}

			public function init_form_fields() {
				$this->instance_form_fields = CorreosOficialCommonShippingMethods::loadDefaultFormFields($this->paqData);

				$this->instance_form_fields['charge_rules'] = array(
					'title' => __('Rules table', 'correosoficial'),
					'type' => 'charge_rules',
					'default' => '',
				);
			}

			public function process_admin_options() {
				parent::process_admin_options();

				global $wpdb;
				global $table;

				if (isset($_REQUEST['instance_id'])) {
					$instance_id = intval($_REQUEST['instance_id']);
				}

				// Solo aplicamos a métodos del plugin
				if ($this->instance_id != $instance_id) {
					return false;
				}

				$instance_id = sanitize_key($instance_id);

				/**
				 *
				 * Guardamos reglas en base de datos
				 */
				$data_array = array();

				if (isset($_REQUEST['data'])) {

					$data_array = CorreosOficialUtils::sanitize($_REQUEST['data']); // phpcs:ignore
				}

				$rules_to_save = array();

				foreach ($data_array as $data) {

					if (is_array($data) && !isset($data['instance_id'])) {
						CorreosOficialCommonShippingMethods::validateData((float) $data['from'], (float) $data['to'], (float) $data['cost']);
						$rules_to_save[] = $data;
					}
				}

				// Si no llegan reglas válidas, preservamos la configuración existente.
				if (empty($rules_to_save)) {
					return true;
				}

				$wpdb->delete($table, array( 'instance_id' => $instance_id ));

				foreach ($rules_to_save as $data) {
					CorreosOficialCommonShippingMethods::saveCostRules($table, $instance_id, $data);
				}

				return true;
			}

			public function get_shipping_methods( $zone, $enabled_only = false, $context = 'admin' ) {
				if (null === $zone->get_id()) {
					return array();
				}

				$raw_methods = CorreosOficialCommonShippingMethods::getMethods($zone->get_id(), $enabled_only);
				$wc_shipping = WC_Shipping::instance();
				$allowed_classes = $wc_shipping->get_shipping_method_class_names();
				$methods = array();

				foreach ($raw_methods as $raw_method) {

					if (!array_key_exists($raw_method->method_id, $allowed_classes)) {
						continue;
					}

					$class_name = $allowed_classes[$raw_method->method_id];
					$instance_id = $raw_method->instance_id;

					// If the class is not an object, it should be a string. It's better
					// to double check, to be sure (a class must be a string, anything)
					// else would be useless.
					if (is_string($class_name) && class_exists($class_name)) {
						$methods[$instance_id] = new $class_name($instance_id);
					}

					// Let's make sure that we have an instance before setting its attributes.
					if (is_object($methods[$instance_id])) {
						$methods[$instance_id]->method_order = absint($raw_method->method_order);
						$methods[$instance_id]->enabled = $raw_method->is_enabled ? 'yes' : 'no';
						$methods[$instance_id]->has_settings = $methods[$instance_id]->has_settings();
						$methods[$instance_id]->settings_html = $methods[$instance_id]->supports('instance-settings-modal') ? $methods[$instance_id]->get_admin_options_html() : false;
						$methods[$instance_id]->method_description = wp_kses_post(wpautop($methods[$instance_id]->method_description));
					}

					if ('json' === $context) {
						// We don't want the entire object in this context, just the public props.
						$methods[$instance_id] = (object) get_object_vars($methods[$instance_id]);
						unset($methods[$instance_id]->instance_form_fields, $methods[$instance_id]->form_fields, $methods[$instance_id]->data);
					}
				}

				uasort($methods, 'wc_shipping_zone_method_order_uasort_comparison');
				/**
				 * Aplica las tarifas de envío
				 *
				 * Aplica las tarifas de envío por cada transportista según configuración.
				 *
				 * @since 1.0.0.0
				 *
				 * @param array $methods Transportistas.
				 * @param array $raw_methods Transportistas.
				 * @param array $allowed_classes Tipos de transportistas admitidos para el filtro.
				 * @param object $this Esta misma clase
				 */
				return apply_filters('woocommerce_shipping_zone_shipping_methods', $methods, $raw_methods, $allowed_classes, $this);
			}

			public function generate_charge_rules_html( $key, $data ) {
				$tab = '';

				if (version_compare(WC_VERSION, '8.3', '>')) {
					$tab = '<table id="co_shipping_methods_table">';
				}

				return sprintf(
					$tab .
					'
					<tr class="leaveatleastonerecord">
						<td colspan="5" >' . __('You must leave at least one rule', 'correosoficial') . '</td>
					</tr>
					<tr>
						<td colspan="5">' . __('Rules by cost have priority over rules by weight', 'correosoficial') . '<td>
					</tr>
					<tr>
                        <th>%1$s</th>
                        <th>%2$s</th>
                        <th>%3$s</th>
                        <th class="co_to">%4$s</th>
                        <th>%5$s</th>
                    </tr>
                    <tr class="mustselectrecord">
                        <td>' . __('You must select a record from the table', 'correosoficial') . '</td>
                    </tr>
                    %6$s
                    %7$s
                     ',
					__('Shipping Classes', 'correosoficial'),
					__('Conditions', 'correosoficial'),
					__('From (min) >=', 'correosoficial'),
					__('To (max) <', 'correosoficial'),
					__('Cost', 'correosoficial'),
					CorreosOficialCommonShippingMethods::createCell($this->paqData),
					CorreosOficialCommonShippingMethods::createButtons()

				);
			}

			public function calculate_shipping( $packages = array() ) {

				$rate = array(
					'id' => $this->get_rate_id(),
					'label' => $this->title,
					'cost' => 0,
					'package' => $packages,
				);

				// Calculate the costs.
				$class = CorreosOficialCommonShippingMethods::getRuleToApply($packages);
				$weight = CorreosOficialCommonShippingMethods::getTotalWeight($packages);
				$total_cost = CorreosOficialCommonShippingMethods::getTotalCost($packages);
				$condition = CorreosOficialCommonShippingMethods::getRuleCondition($class, $this->instance_id);

				$rule_cost = CorreosOficialCommonShippingMethods::getRuleCost($class, $condition, $total_cost, $weight, $this->instance_id);

				// Si no se encontró regla de coste aplicable, no ofrecer este método de envío
				if ($rule_cost === false) {
					return;
				}

				$rate['cost'] = $rule_cost;
				$fixed_cost = (float) $this->cost;
				$rule_cost_float = (float) $rule_cost;
				$epsilon = 0.00001;

				if (abs($rule_cost_float) < $epsilon && abs($fixed_cost) < $epsilon) {
					$rate['label'] = $this->title . ': ' . __('Free', 'correosoficial');
				}

				$rate['cost'] = $rule_cost_float + $fixed_cost;

				$this->add_rate($rate);

				/**
				 * Calcula el la tarifa adicional de envío.
				 *
				 * Calcula la tarifa adicional de envío por cada transportista según configuración.
				 *
				 * @since 1.0.0.0
				 *
				 * @param array $rate Tarifas de transportistas.
				 */
				do_action('woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate);
			}

			public function is_available( $packages ) {
				if (( CorreosOficialCommonShippingMethods::getTotalWeight($packages) > $this->max_weight ) && $this->max_weight != 0) {
					return false;
				}
				return true;
			}

			/**
			 * Enqueue JS to handle free shipping options.
			 *
			 * Static so that's enqueued only once.
			 */
			public static function enqueue_admin_js( $id, $instanceId ) {
				if (!self::$scriptLoaded) {
					self::$scriptLoaded = true;

					// 1) Registrar un handle para asociar el JS
					$handle = 'correos-oficial-admin-inline';

					wp_register_script( $handle, false, array('jquery'), null, true );
					wp_enqueue_script( $handle );

					// 2) Añadir el código inline
					$inline = CorreosOficialCommonShippingMethods::loadCommonScript($id, $instanceId, $id);

					wp_add_inline_script( $handle, $inline );
				}
			}
		}
	}
}

add_filter('woocommerce_shipping_methods', 'add_request_shipping_quote_19');

function add_request_shipping_quote_19( $methods ) {

	$shippingMethod = CorreosOficialCommonShippingMethods::loadShippingMethod(WC_Paq_Estandar_Oficina_Elegida::getPaqId());

	if ($shippingMethod) {
		$methods['request_shipping_quote_' . $shippingMethod[0]['id']] = 'WC_Paq_Estandar_Oficina_Elegida';
	}

	return $methods;
}
