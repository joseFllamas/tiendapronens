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

/**
 * Plugin Name: Correos Ecommerce
 * Plugin URI: https://es.wordpress.org/plugins/correos-ecommerce/
 * Description: Correos and Correos Express Spain plugin for shipment management. It integrates national and international parcel services, making the management of your orders a quick and easy task.
 * Version: 2.8.0
 * Author: Grupo Correos
 * Author URI: http://correos.es
 * Text Domain: correosoficial
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4.33
 * WC requires at least: 6.0
 * WC tested up to: 10.6.0-dev
 */

require_once 'vendor/autoload.php';
require_once __DIR__ . '/classes/CorreosOficialConstants.php';

use CorreosOficial\Classes\CorreosOficialUtils;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Classes\CorreosOficialSGA;
use CorreosOficial\Classes\CorreosOficialSmarty;
use CorreosOficial\Classes\CorreosOficialPrefilter;
use CorreosOficial\Classes\Analitica;
use CorreosOficial\Classes\CorreosOficialMarketplace;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Models\CorreosOficialRequests;
use CorreosOficial\Models\CorreosOficialOrder;
use CorreosOficial\Models\CorreosOficialSgaOrdersStatus;
use CorreosOficial\Classes\CorreosOficialCarrierExtraContent;
use CorreosOficial\Controllers\Admin\AdminCorreosOficialCronProcessController;
use CorreosOficial\Controllers\Admin\AdminCorreosOficialHomeController;
use CorreosOficial\Controllers\Admin\AdminCorreosOficialSettingsController;
use CorreosOficial\Controllers\Admin\AdminCorreosOficialUtilitiesController;
use CorreosOficial\Controllers\Admin\AdminCorreosOficialNotificationsController;

if (!defined('WPINC')) {
	die;
}

define('MODULE_CORREOS_OFICIAL_PATH', __FILE__);
define('MODULE_CORREOS_OFICIAL_PATH_FRONT', plugin_dir_url('views/*.*'));


require_once 'correosTrackings.php';

require_once 'classes/CorreosOficialAddShippingMethod.php';
require_once 'classes/CorreosOficialOrders.php';
require_once 'classes/CorreosOficialOrder.php';

require_once 'controllers/admin/AdminCorreosOficialCronProcessController.php';
require_once 'controllers/admin/AdminCorreosOficialDatatableController.php';
require_once 'controllers/admin/AdminCorreosOficialSGAProcessController.php';

global $smarty;
global $co_module_url;
global $co_page;

// Estados para los pedidos
define("CORREOS_OFICIAL_SGA_ORDER_STATES", array(
    array(
        'alias' => 'SGA_STATUS_PREPARE',
        'name' => 'Solicitar preparación Correos',
        'value' => 'wc-preparesga',
    ),
    array(
        'alias' => 'SGA_STATUS_OK',
        'name' => 'Enviado OK Almacén Correos',
        'value' => 'wc-okcorreosecomsga',
    ),
    array(
        'alias' => 'SGA_STATUS_KO',
        'name' => 'Sin enviar Almacén Correos',
        'value' => 'wc-kocorreosecomsga',
    ),
    array(
        'alias' => 'SGA_STATUS_CANCELLED',
        'name' => 'Solicitar cancelación Correos',
        'value' => 'wc-cancelledcorreosecomsga',
    ),
));

class CorreosOficial {


	private $version;
	private $smarty;
	public $SGAIsActive = false;
	public $marketplaceIsActive = false;
	private $codPceNoticeAdded = false;

	public function __construct() {
		
		global $wpdb;

		//error_log(print_r($wpdb,true));

		if (!defined('CORREOS_OFICIAL_VERSION')) {
			define('CORREOS_OFICIAL_VERSION', $this->getModuleVersion());
		}

		$this->correosoficialIncludes();
		$this->correosoficialInitHooks();
		$this->version = $this->getModuleVersion();

		static $config = [];

		// Evitar acceso a DB durante activación del plugin o si las tablas no existen
		if ((!defined('WP_INSTALLING') || !WP_INSTALLING) && self::tableExists($wpdb->prefix . 'correos_oficial_configuration')) {
			if (!isset($config['ActivateNifFieldCheckout'])) {
				$config['ActivateNifFieldCheckout'] = CorreosOficialConfig::getConfigValue('ActivateNifFieldCheckout');
			}

			if (!isset($config['NifFieldRadio'])) {
				$config['NifFieldRadio'] = CorreosOficialConfig::getConfigValue('NifFieldRadio');
			}
		}

		if ( ( isset($config['ActivateNifFieldCheckout']) && $config['ActivateNifFieldCheckout'] == 'on' && isset($config['NifFieldRadio']) && $config['NifFieldRadio'] == 'OPTIONAL' ) ||
			(isset($config['NifFieldRadio']) && $config['NifFieldRadio'] == 'OBLIGATORY') ) {
			add_action(
				'woocommerce_after_checkout_billing_form',
				'\\CorreosOficial\\Classes\\CorreosOficialNifNumberForCheckout::addNifFieldToCheckout'
			);
		}

		add_action('init', array( $this, 'registerShippedOrderStatus' ));
		add_filter('wc_order_statuses', array( $this, 'customOrderStatus' ));

		if (is_admin()) {
			add_action('init', array( $this, 'init' ));
			add_action('init', array( $this, 'checkVersionAndRunUpdates' ));
			add_filter('upgrader_pre_install', array( $this, 'upgraderPreInstall' ), 10, 2);
			add_filter('upgrader_post_install', array( $this, 'upgraderPostInstall' ), 10, 2);

			// Eliminación de pedido
			add_action('before_delete_post', array( $this, 'deleteOrder' ), 10, 2);
		}

		// Procesos a ejecutar para capturas de pedidos que entrar por API Rest usado por el módulo Channable
		add_action( 'woocommerce_new_order_item', array( $this, 'channableTasks' ), 10, 3 );

		// Cron schedule interval
		add_action('correosoficial_tracking_cron_event', array( AdminCorreosOficialCronProcessController::class, 'cronExecute' ));
		add_filter('cron_schedules', array( AdminCorreosOficialCronProcessController::class, 'updateCronInterval' ));

		// Fallback explícito para checkout shortcode (incluye flujos AJAX de temas/plugins).
		add_action('woocommerce_checkout_process', array( $this, 'validateCheckout' ), 1);
		add_action('woocommerce_after_checkout_validation', array( $this, 'validateCheckoutAfterValidation' ), 9999, 2);

		add_action('woocommerce_init', function() {
			add_action('woocommerce_checkout_order_created', array( $this, 'saveOrderFromCheckout' ));

			if (is_checkout()) {
				// Checkout process
				add_action('woocommerce_admin_order_data_after_billing_address', '\\CorreosOficial\\Classes\\CorreosOficialNifNumberForCheckout::showPersonalisedFieldAdminOrder');
				add_action('woocommerce_checkout_update_order_meta', '\\CorreosOficial\\Classes\\CorreosOficialNifNumberForCheckout::updateOrderInfoWithNewField');
				add_action('woocommerce_order_details_after_customer_details', array($this, 'hookOrderDetailDisplayed'));


				// Hooks para campos personalizados del producto (código HS y país de origen)
				add_action('woocommerce_product_options_shipping', array( $this, 'addCustomsProductFields' ));
				add_action('woocommerce_process_product_meta', array( $this, 'saveCustomsProductFields' ));

				// Hook para manejar cambios en el método de envío desde el admin
				add_action('woocommerce_process_shop_order_meta', array( $this, 'handleShippingMethodChangeInAdmin' ), 45, 2);
			}
		});

		add_action('wp_ajax_correosOficialDispacher', array( $this, 'correosOficialDispacherProxy' ));
		add_action('wp_ajax_nopriv_correosOficialDispacher', array( $this, 'correosOficialDispacherProxy' ));

		add_action('rest_api_init', array( $this, 'registerCheckFeatureEndpoint' ));


		add_action( 'plugins_loaded', array( $this, 'check_woocommerce_version' ) );

		$page = sanitize_text_field(isset($_GET['page']) ? $_GET['page'] : '');

		// Deferimos la comprobación que usa traducciones hasta admin_init
		add_action( 'admin_init', function() use ( $page ) {
			if ( in_array( $page, array( 'settings', 'utilities', 'notifications', 'correosoficial' ), true ) ) {
				$error = __('ERROR 12050: To use webservice credentials, you must have the SOAP feature installed. Please contact your hosting for more information.', 'correosoficial');
				CorreosOficialUtils::checkSoapInstalled( $error );
			}
		} );
		
		// Filtro para ocultar pago contra reembolso con carrier específico
		add_filter('woocommerce_available_payment_gateways', array( $this, 'hide_cod_payment_for_specific_carrier' ));
		
		// Filtros adicionales para el checkout con bloques
		add_action('plugins_loaded', function() {
			if ( class_exists('Automattic\WooCommerce\Blocks\Package') ) {
				add_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'apply_cod_filter_for_blocks'), 10, 2);
				add_filter('woocommerce_blocks_loaded', array($this, 'register_checkout_block_filters'));
				add_action('rest_api_init', array($this, 'register_rest_api_filters'));
			}
		});

		if(!defined('CORREOS_OFFICIAL_DEBUG')) {
            // Si no está definida, la definimos como false por defecto
            define('CORREOS_OFFICIAL_DEBUG', false);
        }

		// ============================================
		// MÓDULO DE LOGISTICA
		$status = CorreosOficialConfig::get_config_status('ActivateSGA');
		$this->SGAIsActive = !empty($status->value) && $status->value === 'on';

		// MÓDULO DE MARKETPLACE
		$this->marketplaceIsActive = CorreosOficialMarketplace::isMarketplaceEnabled();

		// Registrar estado de pedido "Sent to Marketplace" cuando el módulo está activo
		if ( $this->marketplaceIsActive ) {
			add_action( 'init', [ CorreosOficialMarketplace::class, 'registerOrderStatus' ] );
			add_filter( 'wc_order_statuses', [ CorreosOficialMarketplace::class, 'addOrderStatus' ] );
		}

		// INICIO: Implementación de estados SGA (solo si está activo)
		// ============================================
		if ( $this->SGAIsActive ) {

			if (is_admin()) {
				// 3. Registrar acciones masivas (bulk actions) en la tabla de pedidos
				add_filter('bulk_actions-edit-shop_order', array( $this, 'registerSgaBulkActions' ), 10);
				add_filter('bulk_actions-woocommerce_page_wc-orders', array( $this, 'registerSgaBulkActions' ), 10);

				// 4. Asegurar que aparezcan en reportes y vistas "Todos"
				add_filter('woocommerce_reports_order_statuses', array( $this, 'addSgaToReportStatuses' ));
				
				// 5. Permitir editar pedidos con estos estados (opcional)
				add_filter('wc_order_is_editable', array( $this, 'makeSgaOrdersEditable' ), 10, 2);

				add_action('admin_notices', function() {
					// Mensaje de éxito
					if ( ! empty($_GET['correosecomsga_message']) ) {
						echo '<div class="notice notice-success is-dismissible">';
						echo '<p>' . esc_html(urldecode($_GET['correosecomsga_message'])) . '</p>';
						echo '</div>';
					}

					// Mensaje de error
					if ( ! empty($_GET['correosecomsga_error']) ) {
						echo '<div class="notice notice-error is-dismissible">';
						echo '<p>' . esc_html(urldecode($_GET['correosecomsga_error'])) . '</p>';
						echo '</div>';
					}

					// Mensajes acumulados de acciones en lote (bulk)
					$bulk_messages = get_transient( 'correosoficial_sga_bulk_messages' );
					if ( is_array( $bulk_messages ) && ! empty( $bulk_messages ) ) {
						foreach ( $bulk_messages as $msg ) {
							$notice_class = ( $msg['type'] === 'success' ) ? 'notice-success' : 'notice-error';
							echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible">';
							echo '<p>' . esc_html( $msg['message'] ) . '</p>';
							echo '</div>';
						}
						delete_transient( 'correosoficial_sga_bulk_messages' );
					}
				});
			}

			// Hooks para tareas CRON logistica
			add_action('correosoficial_sga_stock_cron_event', array( $this, 'executeSGAStockCron'));
			add_action('correosoficial_sga_status_cron_event', array( $this, 'executeSGAUpdateOrderStatusCron'));

			add_action('init', array( $this, 'registerSgaOrderStatuses' ));
			add_filter('wc_order_statuses', array( $this, 'addSgaToWcOrderStatuses' ));
			
			add_action('woocommerce_order_status_changed', array( $this, 'orderStatusChanged' ) , 10, 3);
		}
	}

	public function check_woocommerce_version() {
		// Cargar traducciones
		load_plugin_textdomain( 'correosoficial', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( class_exists( 'WooCommerce' ) ) {
			$current_version = WC_VERSION;
	
			if ( version_compare( $current_version, '8.5.3', '>' ) ) {
				// Declara la compatibilidad con los bloques de WooCommerce.
				add_action( 'before_woocommerce_init', function () {
					if ( class_exists( FeaturesUtil::class ) ) {
						FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true ); // Checkout Blocks
						FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true ); // HPOS
					}
				} );
	
				/**
				 * Include the dependencies needed to instantiate the block.
				 */
				add_action(
					'woocommerce_blocks_loaded',
					function () {
						require_once __DIR__ . '/correosoficial-wc-extend-store-endpoint.php';
						require_once __DIR__ . '/correosoficial-wc-extend-woo-core.php';
						require_once __DIR__ . '/correosoficial-wc-blocks-integration.php';
	
						// Initialize our store endpoint extension when WC Blocks is loaded.
						CorreosOficial_Wc_Extend_Store_Endpoint::init();
	
						// Add hooks relevant to extending the Woo core experience.
						$extend_core = new CorreosOficial_Wc_Extend_Woo_Core();
						$extend_core->init();
						
						add_action(
							'woocommerce_blocks_checkout_block_registration',
							function( $integration_registry ) {

								if ( $integration_registry->is_registered( 'correosoficial' ) ) {
									return;
								}

								$integration_registry->register(
									new CorreosOficial_Wc_Blocks_Integration()
								);
							}
						);
					}
				);
			} else {
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-error">';
					echo '<p><strong>' . esc_html(__( 'Error:', 'correosoficial' )) . '</strong> ' . esc_html(__( 'The current installed version of WooCommerce is not compatible with this Correos Oficial version.', 'correosoficial' )) . '</p>';
					echo '</div>';
				} );
			}
		}
	}
	
	public function init() {
		$this->updater();
	}

	public function updater() {
		if (!get_option('CORREOS_OFICIAL_LAST_UPDATE') || get_option('CORREOS_OFICIAL_LAST_UPDATE') != $this->version) {

			//$this->deleteDuplicatedOrders('correos_oficial_saved_orders');
			//$this->deleteDuplicatedOrders('correos_oficial_saved_returns');

			// Añadimos los estados para los pedidos
			$sga_order_states   = CORREOS_OFICIAL_SGA_ORDER_STATES;
			CorreosOficialConfig::set_value_by_name($sga_order_states[1]['alias'], $sga_order_states[1]['value']);
			CorreosOficialConfig::set_value_by_name($sga_order_states[2]['alias'], $sga_order_states[2]['value']);

			//$this->deleteLabelFromTables();
			//$this->updateOldShippingMethods();

			self::createCronTasks();
		}
	}

	public function correosoficialCronSchedules( $schedules ) {
		$schedules['correosoficial_cron'] = array(
			'interval' => 3600 * CorreosOficialConfig::getConfigValue('CronInterval'),
			'display'  => __('Cada ' . CorreosOficialConfig::getConfigValue('CronInterval') . ' Horas'),
		);
		return $schedules;
	}

	public function updateOldShippingMethods() {
		global $wpdb;
		$shippingZoneTable = $wpdb->prefix . 'woocommerce_shipping_zone_methods';

		try {

			$carriersList = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}correos_oficial_carriers_products
			WHERE id_carrier IN (SELECT instance_id FROM %i)", $shippingZoneTable ), ARRAY_A);

			foreach ($carriersList as $carrier) {
				// Actualizar ids de los shippings methods
				$newMethodId = "request_shipping_quote_{$carrier['id_product']}";

				$wpdb->query($wpdb->prepare('UPDATE %i
				SET method_id = %s WHERE instance_id = %d', $shippingZoneTable, $newMethodId, $carrier['id_carrier']));
				// Actualizar wp_option shipping methods quote
				$oldOptionName = 'woocommerce_request_shipping_quote_' . $carrier['id_carrier'] . '_settings';
				if (get_option($oldOptionName) !== false) {
					$newOptionName = 'woocommerce_request_shipping_quote_' . $carrier['id_product'] . '_' . $carrier['id_carrier'] . '_settings';
					$wpdb->query(
						$wpdb->prepare("UPDATE $wpdb->options SET option_name = %s WHERE option_name = %s", $newOptionName, $oldOptionName)
					);
				}

			}

		} catch (Exception $e) {
			error_log('ERROR: ' . $e);
		}
	}

	public function deleteDuplicatedOrders( $input_table ) {
		global $wpdb;

		$table = "{$wpdb->prefix}$input_table";

		try {
			// Recupera los pedidos duplicados
			$records = $wpdb->get_results($wpdb->prepare('SELECT id_order FROM %i GROUP BY id_order HAVING COUNT(id_order)>1;', $table));

			if (!count($records)) {
				return;
			}

			$bad_ones = array();

			/**
			 * Devuelve los registros duplicados de cada envío
			 */
			foreach ($records as $record) {
				$records2 = $wpdb->get_results($wpdb->prepare('SELECT * FROM  %i WHERE id_order = {$record->id_order} ORDER BY id ASC', $table), ARRAY_A);

				$i = 0;
				foreach ($records2 as $record) {

					if ($i > 0 && $records2[0]['exp_number'] != $record['exp_number']) {
						array_push($bad_ones, $record['id']);
					}
					$i++;
				}
			}

			$final = join(',', $bad_ones);

			if (!empty($final)) {
				// Eliminamos los duplicados que no sean los reales
				$wpdb->get_results($wpdb->prepare('DELETE FROM %i WHERE id IN ($final)', $table));
			}
		} catch (Exception $e) {
			// Captura cualquier excepción que se haya generado durante la ejecución
			error_log('Error :' . $e->getMessage());
		}
	}

	public function deleteLabelFromTables() {
		global $wpdb;

		$table_orders = $wpdb->prefix . 'correos_oficial_saved_orders';
		$table_returns = $wpdb->prefix . 'correos_oficial_saved_returns';

		try {
			// Comprobar si la columna 'label' existe en la tabla 'correos_oficial_saved_orders / returns '
			$column_exists_orders = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM %i LIKE 'label'", $table_orders));
			$column_exists_returns = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM %i LIKE 'label'", $table_returns));

			if ($column_exists_orders || $column_exists_returns) {
				// Si la columna 'label' existe en alguna de las tablas, intenta eliminarla
				if ($column_exists_orders) {
					$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP COLUMN label', $table_orders));
				}
				if ($column_exists_returns) {
					$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP COLUMN label', $table_returns));
				}
			}
		} catch (Exception $e) {
			// Captura cualquier excepción que se haya generado durante la ejecución
			error_log('Error :' . $e->getMessage());
		}
	}

	// Acciones antes de actualizar
	public function upgraderPreInstall() {
	}

	// Acciones tras la actualización
	public function upgraderPostInstall() {
		// Ejecutar installTables después de una actualización
		self::installTables();
	}

	/**
	 * Verifica la versión del plugin y ejecuta updates si hay cambios
	 */
	public function checkVersionAndRunUpdates() {
		$stored_version = get_option('CORREOS_OFICIAL_LAST_UPDATE');
		$current_version = CORREOS_OFICIAL_VERSION;

		// Si la versión almacenada es diferente a la actual, ejecutar updates
		if ($stored_version !== $current_version) {
			self::installTables();
		}
	}

	/**
	 * Registra los estados de pedido personalizados de SGA en WordPress
	 * Similar a registerPostOrderStatus() del módulo de terceros
	 */
	public function registerSgaOrderStatuses() {
		foreach ( CORREOS_OFICIAL_SGA_ORDER_STATES as $sga_state ) {
			register_post_status(
				$sga_state['value'], // 'wc-preparesga'
				array(
					'label'                     => $sga_state['name'],
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop(
						$sga_state['name'] . ' <span class="count">(%s)</span>',
						$sga_state['name'] . ' <span class="count">(%s)</span>',
						'correosoficial'
					),
				)
			);
		}
	}

	/**
	 * Añade los estados SGA al listado de estados de WooCommerce
	 * Similar a addStatusToFilter() del módulo de terceros
	 */
	public function addSgaToWcOrderStatuses( $order_statuses ) {
		foreach ( CORREOS_OFICIAL_SGA_ORDER_STATES as $sga_state ) {
			$order_statuses[ $sga_state['value'] ] = $sga_state['name'];
		}

		return $order_statuses;
	}

	/**
	 * Registra las acciones masivas (bulk actions) para los estados SGA
	 * Similar a registerOrderCustomStatusBulkActions() del módulo de terceros
	 */
	public function registerSgaBulkActions( $bulk_actions ) {
		foreach ( CORREOS_OFICIAL_SGA_ORDER_STATES as $sga_state ) {
			// Eliminar el prefijo 'wc-' para las acciones masivas
			$sga_state['value'] = str_replace( 'wc-', '', $sga_state['value'] );
			$bulk_actions[ 'mark_' . $sga_state['value'] ] = $sga_state['name'];
		}

		return $bulk_actions;
	}

	/**
	 * Añade los estados SGA a los reportes para que aparezcan en "Todos"
	 * Similar a wcbvCustomStatusIsPaid() pero para reportes
	 */
	public function addSgaToReportStatuses( $statuses ) {
		if ( ! is_array( $statuses ) ) {
			$statuses = array();
		}

		foreach ( CORREOS_OFICIAL_SGA_ORDER_STATES as $sga_state ) {
			// Para reportes se usa el slug sin el prefijo 'wc-'
			$status_without_prefix = str_replace( 'wc-', '', $sga_state['value'] );
			if ( ! in_array( $status_without_prefix, $statuses, true ) ) {
				$statuses[] = $status_without_prefix;
			}
		}

		return $statuses;
	}

	/**
	 * Permite editar pedidos con estados SGA
	 * Similar a bp_add_order_statuses_to_editable() del módulo de terceros
	 */
	public function makeSgaOrdersEditable( $editable, $order ) {
		// Obtener todos los slugs SGA sin el prefijo 'wc-'
		$sga_statuses = array();
		foreach ( CORREOS_OFICIAL_SGA_ORDER_STATES as $sga_state ) {
			$sga_statuses[] = str_replace( 'wc-', '', $sga_state['value'] );
		}

		// Si el pedido tiene un estado SGA, permitir editarlo
		if ( $order->has_status( $sga_statuses ) ) {
			return true;
		}

		return $editable;
	}

	// Procesamos cambio estado pedido manual
	public function orderStatusChanged( $id_order, $current_status, $new_status ) {
		
		// Prevenir procesamiento recursivo
		static $processing = array();
		
		if ( isset( $processing[ $id_order ] ) ) {
			return;
		}
		
		$processing[ $id_order ] = true;
		
		$wc_order          = wc_get_order( $id_order );
		$process_status    = ( new CorreosOficialConfig( 'SGAProcessStatus' ) )->get_value();
		$process_status    = str_starts_with( $process_status, 'wc-' ) ? substr( $process_status, 3 ) : $process_status;
		$sga_order_status  = array();
		$new_status		   = str_starts_with( $new_status, 'wc-' ) ? substr( $new_status, 3 ) : $new_status;

		// Comprobamos correctamente si el pedido ya existe en la tabla de Correos
		$co_exists = CorreosOficialOrder::exists( $id_order );

		// Si NO existe en la tabla de Correos => tratar como pedido sga
		if ( ! $co_exists ) {
			if ( $wc_order && ( $current_status != $new_status && $new_status === 'cancelledcorreosecomsga' ) ) {
				CorreosOficialHelpers::writeToLog( 'sga', "Iniciando cancelación SGA - Pedido: {$id_order}, Estado anterior: {$current_status}, Estado nuevo: {$new_status}" );
				
				$sga_order_status = CorreosOficialSGA::cancelOutgoingOrder( [
					'id_order' => $id_order,
					'company'  => 'Correos',
				] );

				if ( ! empty( $sga_order_status ) && $sga_order_status['type'] === 'error' ) {
					CorreosOficialHelpers::writeToLog( 'sga', "Error en cancelación SGA - Pedido: {$id_order}, revirtiendo estado a: {$current_status}" );
					if ( $wc_order ) {
						$wc_order->set_status( $current_status );
						$wc_order->save();
						$wc_order->add_order_note( 'Error SGA: ' . $sga_order_status['message'] );
					}
					$this->showSGAMessage( $sga_order_status['message'], 'error' );
				} elseif ( ! empty( $sga_order_status ) && $sga_order_status['type'] === 'success' ) {
				
					// Cancelación exitosa: cambiar al estado "cancelled" de WooCommerce
					CorreosOficialHelpers::writeToLog( 'sga', "Cancelación SGA exitosa - Pedido: {$id_order}, cambiando estado a 'cancelled'" );
					if ( $wc_order ) {
						$wc_order->set_status( 'cancelled' );
						$wc_order->save();
						$wc_order->add_order_note( 'Cancelación SGA exitosa: ' . $sga_order_status['message'] );
					}

					$final_status = $wc_order->get_status();
					CorreosOficialHelpers::writeToLog( 'sga', "Estado final confirmado del pedido {$id_order}: {$final_status}" );
					$this->showSGAMessage( $sga_order_status['message'], 'success' );

					unset( $processing[ $id_order ] );
					return;
				}
			} elseif ( $new_status !== 'cancelledcorreosecomsga' ) {
				if ( $new_status == "preparesga" || $new_status == $process_status ) {
					// Detectar si es procesamiento automático (desde checkout)
					$is_automatic_processing = ( $new_status == $process_status );
					
					CorreosOficialHelpers::writeToLog( 'sga', "Iniciando envío SGA - Pedido: {$id_order}, Estado: {$new_status}, Automático: " . ($is_automatic_processing ? 'Sí' : 'No') );
					
					// Capturar el resultado del envío al almacén
					$result = CorreosOficialSGA::sendOutgoingOrder( [ [
						'id_order' => $id_order,
						'company'  => 'Correos',
					] ] );
					
					// Manejar el resultado
					if ( ! empty( $result ) && isset( $result['type'] ) ) {
						if ( $result['type'] === 'error' ) {
							CorreosOficialHelpers::writeToLog( 'sga', "Error en envío SGA - Pedido: {$id_order}, Error: {$result['message']}" );
							if ( $wc_order ) {
								$wc_order->add_order_note( 'Error en envío al almacén SGA: ' . $result['message'] );
							}
							// Solo mostrar mensaje si NO es procesamiento automático y estamos en admin
							if ( ! $is_automatic_processing && is_admin() && ! wp_doing_ajax() ) {
								$this->showSGAMessage( $result['message'], 'error' );
							}
						} elseif ( $result['type'] === 'success' ) {
							CorreosOficialHelpers::writeToLog( 'sga', "Envío SGA exitoso - Pedido: {$id_order}" );
							if ( $wc_order ) {
								$wc_order->add_order_note( 'Pedido enviado al almacén SGA correctamente.' );
							}
							// Solo mostrar mensaje si NO es procesamiento automático y estamos en admin
							if ( ! $is_automatic_processing && is_admin() && ! wp_doing_ajax() ) {
								$this->showSGAMessage( $result['message'], 'success' );
							}
						}
					}
				} else {
					$wc_status = $wc_order ? $wc_order->get_status() : 'unknown';
					CorreosOficialHelpers::writeToLog( 'sga', 'Su nuevo estado no es procesable automáticamente Pedido: ' . $id_order . ' Estado: ' . $wc_status );
					unset( $processing[ $id_order ] );
					return;
				}
			}
		} elseif ( $wc_order ) {
			$wc_order->set_status( $new_status );
			$wc_order->save();
		}
	
		unset( $processing[ $id_order ] );
	}

	public function processSgaOrderFromCheckout ( $id_order ) {
		$wc_order       = wc_get_order($id_order);
		$process_status = ( new CorreosOficialConfig('SGAProcessStatus') )->get_value();

		$process_status = str_starts_with($process_status, 'wc-') ? substr($process_status, 3) : $process_status;

		if ( $wc_order && $wc_order->get_status() === $process_status ) {
			CorreosOficialSGA::sendOutgoingOrder([[
				'id_order' => $id_order,
				'company'  => 'Correos'
			]]);
		}
	}

	// sga cron tasks 
	public function executeSGAStockCron() {

		$sga_owner     = ( new CorreosOficialConfig('SGAOwner') )->get_value();
		$sga_customer  = ( new CorreosOficialConfig('SGACustomer') )->get_value();
		$sga_warehouse = ( new CorreosOficialConfig('SGAStore') )->get_value();

		CorreosOficialHelpers::writeToLog('sga', 'Comienza el Cron de actualización de stock.');

		/* Escritura en el log del "Cron de Actualización de Stock" */
		CorreosOficialHelpers::writeToUpdateStockCronLog(('---'));
		CorreosOficialHelpers::writeToUpdateStockCronLog('Correos Oficial - SGA: LOG del Cron de Actualización de stock.');
		CorreosOficialHelpers::writeToUpdateStockCronLog('Llamada de actualización de Stock. Propietario[' . $sga_owner . '] Cliente[' . $sga_customer . '] Almacén[' . $sga_warehouse . ']');

		CorreosOficialSGA::updateAllProductsStock([
			'ownerid'   => $sga_owner,
			'clientid'  => $sga_customer,
			'warehouse' => $sga_warehouse,
			'company'   => 'Correos'
		]);
	}

	public function executeSGAUpdateOrderStatusCron() {

		$sga_owner     = ( new CorreosOficialConfig('SGAOwner') )->get_value();
		$sga_customer  = ( new CorreosOficialConfig('SGACustomer') )->get_value();
		$sga_warehouse = ( new CorreosOficialConfig('SGAStore') )->get_value();

		CorreosOficialHelpers::writeToLog('sga', 'Comienza el Cron de actualización de estados de pedidos.');

		// Escritura en el log del "Cron de Actualización de Estados de Pedidos"
		CorreosOficialHelpers::writeToOrderStatusTrackingCronLog(('---'), false);
		CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('Correos Oficial - SGA: LOG del Cron de seguimiento de estado de pedido.');
		CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('Llamada de seguimiento de estado de pedido. Propietario[' . $sga_owner . '] Cliente[' . $sga_customer . '] Almacén[' . $sga_warehouse . ']');

		CorreosOficialSGA::updateAllOrdersStatuses([
			'ownerid'   => $sga_owner,
			'clientid'  => $sga_customer,
			'warehouse' => $sga_warehouse,
			'company'   => 'Correos'
		]);
	}

	public function showSGAMessage($message, $type = 'success') {
		// Solo permitimos 'success' o 'error'
		$type = in_array($type, ['success', 'error']) ? $type : 'success';

		// Detectar si estamos en una acción en lote (bulk action)
		// En ese caso, no hacer redirect+exit para permitir que se procesen todos los pedidos
		if ( $this->isBulkAction() ) {
			$notice_type = ($type === 'success') ? 'success' : 'error';
			$bulk_messages = get_transient( 'correosoficial_sga_bulk_messages' );
			if ( ! is_array( $bulk_messages ) ) {
				$bulk_messages = array();
			}
			$bulk_messages[] = array( 'message' => $message, 'type' => $notice_type );
			set_transient( 'correosoficial_sga_bulk_messages', $bulk_messages, 60 );
			return;
		}

		$query_arg = ($type === 'success') ? 'correosecomsga_message' : 'correosecomsga_error';

		$redirect_url = add_query_arg(
			$query_arg,
			urlencode($message),
			admin_url('edit.php?post_type=shop_order')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Detecta si la petición actual es una acción en lote (bulk action)
	 */
	private function isBulkAction() {
		$action  = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_text_field( $_REQUEST['action2'] ) : '';

		// WooCommerce bulk actions para cambio de estado empiezan con 'mark_'
		if ( strpos( $action, 'mark_' ) === 0 || strpos( $action2, 'mark_' ) === 0 ) {
			return true;
		}

		return false;
	}
	
	/**
	 * Callback para las llamadas ajax al dispacher, haciendo de proxy
	 * entre admin-ajax.php y dispatcher.php
	 */
	public function correosOficialDispacherProxy() {

		// Verificar la seguridad del nonce (opcional pero recomendado)
		check_ajax_referer('correosoficial_nonce', '_nonce');

		// Reindexamos REQUEST - NO sanitizar el array completo, solo los campos que se usan
		$_REQUEST = isset($_POST['dispatcher']) ? $_POST['dispatcher'] : array(); // phpcs:ignore

		// No cargar autoload en el dispacher
		$_GET['autoload'] = false;

		// Para el switch del dispacher
		$_GET['controller'] = isset($_REQUEST['controller']) ? sanitize_text_field($_REQUEST['controller']) : ''; // phpcs:ignore

		require_once 'dispatcher.php';
	}

	public function validateCheckout() {
		$nonce = sanitize_text_field(
			isset($_POST['woocommerce-process-checkout-nonce']) ? $_POST['woocommerce-process-checkout-nonce'] : '');

		// Verificar que el nonce sea válido
		if (!wp_verify_nonce($nonce, 'woocommerce-process_checkout')) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wc_add_notice(__('The order could not be completed, security check failed.', 'correosoficial'), 'error');
			return;
		}

		// Validar que los campos requeridos estén presentes cuando se usa un método de envío que requiere punto de recogida
		foreach ($_POST as $key => $value) {
			if (strpos($key, 'citypaq_reference') !== false || strpos($key, 'office_reference') !== false || strpos($key, 'pudocex_reference') !== false) {
				// Si existe un campo de referencia, entonces DEBE existir ReferenceType y SelectedReference
				if (!isset($_POST['ReferenceType']) || !isset($_POST['SelectedReference']) || empty($_POST['SelectedReference'])) {
					wc_add_notice(__('The order could not be completed, please select a pickup location.', 'correosoficial'), 'error');
					return;
				}
				break;
			}
		}

		if ($this->shouldBlockCodForPceInLegacy($_POST)) {
			$this->addCodPceCheckoutNoticeOnce();
			return;
		}
	}

	/**
	 * Validación extra para checkout shortcode tras el parseo de datos de Woo.
	 * Evita bypasses en algunos flujos AJAX de themes/plugins.
	 *
	 * @param array $posted_data Datos de checkout parseados por WooCommerce.
	 * @param \WP_Error $errors Objeto de errores de checkout.
	 * @return void
	 */
	public function validateCheckoutAfterValidation($posted_data, $errors) {
		if (!($errors instanceof \WP_Error)) {
			return;
		}

		if (!empty($errors->errors)) {
			return;
		}

		$request_data = is_array($posted_data) ? array_merge($_POST, $posted_data) : $_POST;
		if ($this->shouldBlockCodForPceInLegacy($request_data)) {
			if ($this->codPceNoticeAdded) {
				return;
			}
			$this->codPceNoticeAdded = true;
			$errors->add(
				'correos_cod_not_allowed_for_pce',
				__('Cash on delivery is not available for the selected delivery point. Please choose another payment method.', 'correosoficial')
			);
		}
	}

	/**
	 * Añade el aviso de COD/PCE una sola vez por request.
	 *
	 * @return void
	 */
	private function addCodPceCheckoutNoticeOnce() {
		if ($this->codPceNoticeAdded) {
			return;
		}

		$this->codPceNoticeAdded = true;
		wc_add_notice(__('Cash on delivery is not available for the selected delivery point. Please choose another payment method.', 'correosoficial'), 'error');
	}

	/**
	 * Determina si se debe bloquear COD para puntos PCE/PCI en checkout clásico.
	 *
	 * @param array $request_data Datos de request del checkout.
	 * @param string $trace_scope Sufijo de traza para log.
	 * @return bool
	 */
	private function shouldBlockCodForPceInLegacy($request_data) {
		$selected_payment_method = isset($request_data['payment_method']) ? sanitize_text_field($request_data['payment_method']) : '';
		$cod_candidates = class_exists(CorreosOficialConfig::class) ? CorreosOficialConfig::getConfiguredCodMethodAliases() : array( 'cod' );
		$is_cod_payment = in_array($selected_payment_method, $cod_candidates, true);

		if (!$is_cod_payment) {
			return false;
		}

		$chosen_shipping_methods = isset(WC()->session) ? WC()->session->get('chosen_shipping_methods') : array();
		$shipping_method_id = '';
		if (!empty($chosen_shipping_methods)) {
			$shipping_method_parts = explode(':', $chosen_shipping_methods[0]);
			$shipping_method_id = $shipping_method_parts[0];
		}

		$reference_type = isset($request_data['ReferenceType']) ? strtolower(sanitize_text_field($request_data['ReferenceType'])) : '';
		$selected_reference_data = isset($request_data['SelectedReferenceData']) ? json_decode(stripslashes($request_data['SelectedReferenceData']), true) : array();
		if (!is_array($selected_reference_data)) {
			$selected_reference_data = array();
		}

		$type_code = isset($selected_reference_data['typeCode'])
			? $selected_reference_data['typeCode']
			: ( isset($selected_reference_data['data']['typeCode']) ? $selected_reference_data['data']['typeCode'] : ( isset($selected_reference_data['type_code']) ? $selected_reference_data['type_code'] : '' ) );
		$use_pce = isset($selected_reference_data['use_PCE'])
			? $selected_reference_data['use_PCE']
			: ( isset($selected_reference_data['data']['use_PCE']) ? $selected_reference_data['data']['use_PCE'] : ( isset($selected_reference_data['use_pce']) ? $selected_reference_data['use_pce'] : false ) );
		$session_office_pce_active = isset(WC()->session)
			? WC()->session->get('correosoficial_office_pce_active', false)
			: false;

		$is_use_pce = in_array(strtolower((string) $use_pce), array('1', 'true', 'yes', 'on'), true) || $use_pce === true || $use_pce === 1 || $session_office_pce_active === true;
		$is_type_pce = in_array(strtoupper((string) $type_code), array('PCE', 'PCI'), true);
		$is_type_citypaq = in_array(strtoupper((string) $type_code), array('CITYPAQ', 'CITYPAQ_PREMIUM', 'HOMEPAQ'), true);

		$carrier_id = isset($request_data['CarrierID']) ? absint($request_data['CarrierID']) : 0;
		$is_carrier_office = false;
		$is_carrier_citypaq = false;
		if ($carrier_id > 0 && class_exists(CorreosOficialProduct::class)) {
			$product = (new CorreosOficialProduct())->get_by_carrier($carrier_id);
			if ($product && method_exists($product, 'get_product_type')) {
				$product_type = (string) $product->get_product_type();
				$is_carrier_office = $product_type === 'office';
				$is_carrier_citypaq = $product_type === 'citypaq';
			}
		}

		$is_office_reference = in_array($reference_type, array('oficina', 'office'), true);
		$is_citypaq_reference = in_array($reference_type, array('citypaq', 'citypaq_premium', 'homepaq'), true);
		$is_office_pce = ($is_office_reference && ($is_type_pce || $is_use_pce));
		$is_citypaq_pickup = $is_citypaq_reference || $is_type_citypaq;
		$is_shipping_office = (strpos($shipping_method_id, 'office') !== false) || $is_carrier_office;
		$is_shipping_citypaq = (strpos($shipping_method_id, 'citypaq') !== false) || $is_carrier_citypaq;

		$cod_enabled_plugin = true;
		if (class_exists(CorreosOficialConfig::class)) {
			$cod_enabled_plugin = CorreosOficialConfig::getConfigValue('ActivateCOD') !== 'off';
		}
		$selected_gateway_enabled = true;
		if (isset(WC()->payment_gateways) && WC()->payment_gateways()) {
			$available_gateways = WC()->payment_gateways()->payment_gateways;
			if (isset($available_gateways[$selected_payment_method])) {
				$selected_gateway_enabled = $available_gateways[$selected_payment_method]->enabled === 'yes';
			}
		}


		return (($is_office_pce || ($is_shipping_office && ($is_type_pce || $is_use_pce)) || $is_citypaq_pickup || $is_shipping_citypaq) && $cod_enabled_plugin && $selected_gateway_enabled);
	}

	public function saveOrderFromCheckout( $params ) {
		if ( isset($_POST['woocommerce-process-checkout-nonce']) &&
			wp_verify_nonce(sanitize_text_field($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout')
		) {
			if (!isset($_POST['ReferenceType']) || !isset($_POST['SelectedReference'])) {
				return false;
			}

			$ReferenceType = CorreosOficialUtils::sanitize($_POST['ReferenceType']); // phpcs:ignore

			// Si es pedido de Oficina o CityPaq
			if (isset($ReferenceType) && ( $ReferenceType == 'Oficina' || $ReferenceType == 'CityPaq' || $ReferenceType == 'pudocex')) {
				$selectedReference = filter_var($_POST['SelectedReference'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
				// NO sanitizar el JSON como string, solo stripslashes para quitar los escapes de PHP
				$selectedReferenceData = !empty($_POST['SelectedReferenceData']) ? stripslashes($_POST['SelectedReferenceData']) : ''; // phpcs:ignore

				$id_order = $params->get_id();
				$id_cart = $params->get_cart_hash();

				json_decode($selectedReferenceData);

			if (json_last_error() != JSON_ERROR_NONE) {
				return false;
			}
			
			CorreosOficialRequests::insert_reference_code_with_order_id($id_cart, $selectedReference, $selectedReferenceData, $id_order);
			
			// Actualizar la dirección de envío con la dirección del punto de recogida
			$this->updateOrderShippingAddressWithPickupLocation($params, $selectedReference, $selectedReferenceData);
		}
	}
}

	/**
	 * Actualiza la dirección de envío del pedido con los datos del punto de recogida (checkout legacy)
	 *
	 * @param \WC_Order $order El pedido a actualizar
	 * @param string $reference La referencia del punto de recogida
	 * @param string $location_data_json Datos del punto de recogida en formato JSON
	 * @return void
	 */
	private function updateOrderShippingAddressWithPickupLocation( $order, $reference, $location_data_json ) {
		// Eliminar las barras de escape que añade sanitize()
		$location_data_json = stripslashes($location_data_json);
		$location_data = json_decode($location_data_json, true);
		
		if ( ! $location_data ) {
			return;
		}

		// Solo guardar dirección original si no existe ya (evitar sobrescribir)
		if ( ! $order->get_meta( '_correosoficial_original_shipping_address_1', true ) ) {
			$order->update_meta_data( '_correosoficial_original_shipping_address_1', $order->get_shipping_address_1() );
			$order->update_meta_data( '_correosoficial_original_shipping_address_2', $order->get_shipping_address_2() );
			$order->update_meta_data( '_correosoficial_original_shipping_city', $order->get_shipping_city() );
			$order->update_meta_data( '_correosoficial_original_shipping_postcode', $order->get_shipping_postcode() );
			$order->update_meta_data( '_correosoficial_original_shipping_state', $order->get_shipping_state() );
			$order->update_meta_data( '_correosoficial_original_shipping_country', $order->get_shipping_country() );
			$order->update_meta_data( '_correosoficial_original_shipping_company', $order->get_shipping_company() );
			
			// Marcar que este pedido tiene un punto de recogida
			$order->update_meta_data( '_correosoficial_is_pickup_location', 'yes' );
		}
		
		// Usar la misma lógica que el método normalize() de CorreosOficialRequestsDataStore
		// El objeto location ya viene normalizado desde el servidor con campos estandarizados:
		// name, address, city, zipcode (independientemente del tipo: CityPaq, Office, PudoCEX)
		
		// Primero intentar usar los campos normalizados (si vienen del objeto location completo)
		$pickup_name = isset($location_data['name']) ? $location_data['name'] : '';
		$pickup_address = isset($location_data['address']) ? $location_data['address'] : '';
		$pickup_city = isset($location_data['city']) ? $location_data['city'] : '';
		$pickup_postcode = isset($location_data['zipcode']) ? $location_data['zipcode'] : '';
		
		// Si no hay datos normalizados, intentar buscar en los campos crudos (legacy)
		if (empty($pickup_name) || empty($pickup_address) || empty($pickup_city) || empty($pickup_postcode)) {
			if (!class_exists('\CorreosOficial\Classes\CorreosOficialHelpers')) {
				return;
			}
			
			$helpers = new \CorreosOficial\Classes\CorreosOficialHelpers();
			
			if (empty($pickup_name)) {
				// Nombre del punto: CityPaq usa 'alias', Office usa 'unitName'/'nombre', CEX Oficinas usa 'nombreOficina', CEX PudoCEX usa 'nombrePtoConv'
				$pickup_name = $helpers::getOneValue( $location_data, 'alias', 'unitName', 'nombre', 'nombreOficina', 'nombrePtoConv' );
			}
			
			if (empty($pickup_address)) {
				// Para Office y CEX: buscar en campo directo
				// CEX Oficinas usa 'direccionOficina', CEX PudoCEX usa 'direccionPtoConv'
				$pickup_address = $helpers::getOneValue( $location_data, 'address', 'direccion', 'direccionOficina', 'direccionPtoConv' );
			}
			
			if (empty($pickup_city)) {
				// Ciudad: CityPaq usa 'municipality', Office usa 'municipalityName', CEX Oficinas usa 'poblacionOficina', CEX PudoCEX usa 'ciudadPtoConv'
				$pickup_city = $helpers::getOneValue( $location_data, 'municipality', 'desc_localidad', 'municipalityName', 'descLocalidad', 'poblacionOficina', 'ciudadPtoConv' );
			}
			
			if (empty($pickup_postcode)) {
				// Código postal: CEX Oficinas usa 'codigoPostalOficina', CEX PudoCEX usa 'codigoPostalPtoConv'
				$pickup_postcode = $helpers::getOneValue( $location_data, 'postalCode', 'cod_postal', 'cp', 'codigoPostalOficina', 'codigoPostalPtoConv' );
			}
		}
		
		// Valores por defecto si aún están vacíos
		if ( empty( $pickup_name ) ) {
			$pickup_name = '';
		}
		if ( empty( $pickup_address ) ) {
			$pickup_address = '';
		}
		if ( empty( $pickup_city ) ) {
			$pickup_city = $order->get_shipping_city();
		}
		if ( empty( $pickup_postcode ) ) {
			$pickup_postcode = $order->get_shipping_postcode();
		}
		
		// IMPORTANTE: Mantener el nombre del cliente (first_name y last_name) original
		// Solo actualizamos la dirección física del punto de entrega
		
		// Actualizar dirección de envío con los datos del punto de recogida
		// El nombre del cliente (shipping_first_name y shipping_last_name) se mantiene sin cambios
		$order->set_shipping_company( $pickup_name ); // Nombre del punto en empresa
		$order->set_shipping_address_1( $pickup_address ); // Dirección del punto
		$order->set_shipping_city( $pickup_city );
		$order->set_shipping_postcode( $pickup_postcode );
		
		// Mantener el país y estado del envío original
		// $order->set_shipping_country() y $order->set_shipping_state() no se modifican
		// Los campos first_name y last_name tampoco se modifican
		
		$order->save();
	}

	/**
	 * Maneja cambios en el método de envío cuando se edita un pedido desde el admin
	 * Restaura la dirección original si se cambia a un método que no es punto de recogida
	 * O actualiza con la dirección del punto si se cambia a un método de punto de recogida
	 *
	 * @param int $order_id ID del pedido
	 * @param WP_Post|WC_Order $post El post o pedido
	 * @return void
	 */
	public function handleShippingMethodChangeInAdmin( $order_id, $post ) {
		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			return;
		}
		
		// Obtener el método de envío actual
		$shipping_methods = $order->get_shipping_methods();
		$current_instance_id = null;
		$current_product_type = null;
		
		foreach ( $shipping_methods as $method ) {
			$current_instance_id = $method->get_instance_id();
			break;
		}
		
		if ( ! $current_instance_id ) {
			return;
		}
		
		// Verificar si el método actual es un punto de recogida
		$co_product = (new CorreosOficialProduct())->get_by_carrier( $current_instance_id );
		
		if ( $co_product ) {
			$current_product_type = $co_product->get_product_type();
		}
		
		$is_pickup_location = $co_product && in_array( 
			$current_product_type, 
			array( 'office', 'citypaq', 'pudocex' ) 
		);
		
		// Verificar si tenemos dirección original guardada
		$has_original_address = $order->get_meta( '_correosoficial_original_shipping_address_1', true );
		
		// Caso 1: Cambio de punto de recogida a método normal -> Restaurar dirección original
		if ( ! $is_pickup_location && $has_original_address ) {
			$this->restoreOriginalShippingAddress( $order );
		} 
		
		// Caso 2: Cambio a punto de recogida -> Actualizar con dirección del punto
		// Esto solo se hace si hay una referencia seleccionada en la request
		if ( $is_pickup_location && isset( $_POST['SelectedReference'] ) ) {
			$selectedReference = filter_var( $_POST['SelectedReference'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$selectedReferenceData = isset( $_POST['SelectedReferenceData'] ) ? 
				CorreosOficialUtils::sanitize( $_POST['SelectedReferenceData'] ) : '';
			
			if ( $selectedReference && $selectedReferenceData ) {
				$this->updateOrderShippingAddressWithPickupLocation( $order, $selectedReference, $selectedReferenceData );
			}
		}
	}

	/**
	 * Restaura la dirección de envío original del cliente
	 *
	 * @param \WC_Order $order El pedido
	 * @return void
	 */
	private function restoreOriginalShippingAddress( $order ) {
		$original_address_1 = $order->get_meta( '_correosoficial_original_shipping_address_1', true );
		$original_address_2 = $order->get_meta( '_correosoficial_original_shipping_address_2', true );
		$original_city = $order->get_meta( '_correosoficial_original_shipping_city', true );
		$original_postcode = $order->get_meta( '_correosoficial_original_shipping_postcode', true );
		$original_state = $order->get_meta( '_correosoficial_original_shipping_state', true );
		$original_country = $order->get_meta( '_correosoficial_original_shipping_country', true );
		$original_company = $order->get_meta( '_correosoficial_original_shipping_company', true );
		
		if ( $original_address_1 ) {
			$order->set_shipping_address_1( $original_address_1 );
			$order->set_shipping_address_2( $original_address_2 );
			$order->set_shipping_city( $original_city );
			$order->set_shipping_postcode( $original_postcode );
			if ( $original_company !== false ) {
				$order->set_shipping_company( $original_company );
			}
			if ( $original_state ) {
				$order->set_shipping_state( $original_state );
			}
			if ( $original_country ) {
				$order->set_shipping_country( $original_country );
			}
			
			// Limpiar los metas de dirección original ya que hemos restaurado
			$order->delete_meta_data( '_correosoficial_original_shipping_address_1' );
			$order->delete_meta_data( '_correosoficial_original_shipping_address_2' );
			$order->delete_meta_data( '_correosoficial_original_shipping_city' );
			$order->delete_meta_data( '_correosoficial_original_shipping_postcode' );
			$order->delete_meta_data( '_correosoficial_original_shipping_state' );
			$order->delete_meta_data( '_correosoficial_original_shipping_country' );
			$order->delete_meta_data( '_correosoficial_original_shipping_company' );
			$order->delete_meta_data( '_correosoficial_is_pickup_location' );
			
			$order->save();
		}
	}

	public function registerShippedOrderStatus() {
		register_post_status(
			'wc-prepared-cocex',
			array(
				'label' => __('Shipment prepared for Correos - CEX', 'correosoficial'),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: Nº de pedido en estado Preparado */
				'label_count' => _n_noop('Prepared <span class="count">(%s)</span>', 'Prepared <span class="count">(%s)</span>'),
			)
		);
		register_post_status(
			'wc-cancelled-cocex',
			array(
				'label' => __('Shipment cancelled Correos - CEX', 'correosoficial'),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: Nº de pedido en estado Cancelado */
				'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>'),
			)
		);
		register_post_status(
			'wc-returned-cocex',
			array(
				'label' => __('Shipment returned Correos - CEX', 'correosoficial'),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: Nº de pedido en estado Devuelto */
				'label_count' => _n_noop('Returned <span class="count">(%s)</span>', 'Returned <span class="count">(%s)</span>'),
			)
		);
		register_post_status(
			'wc-delivered-cocex',
			array(
				'label' => __('Shipment delivered Correos - CEX', 'correosoficial'),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: Nº de pedido en estado Entregado */
				'label_count' => _n_noop('Delivered <span class="count">(%s)</span>', 'Delivered <span class="count">(%s)</span>'),
			)
		);
		register_post_status(
			'wc-inprogress-cocex',
			array(
				'label' => __('Shipment in progress Correos - CEX', 'correosoficial'),
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: Nº de pedido en estado En Curso */
				'label_count' => _n_noop('In progress <span class="count">(%s)</span>', 'In progress <span class="count">(%s)</span>'),
			)
		);
	}

	public function customOrderStatus( $order_statuses ) {
		$order_statuses['wc-prepared-cocex'] = __('Shipment prepared for Correos - CEX', 'correosoficial');
		$order_statuses['wc-inprogress-cocex'] = __('Shipment in progress Correos - CEX', 'correosoficial');
		$order_statuses['wc-delivered-cocex'] = __('Shipment delivered Correos - CEX', 'correosoficial');
		$order_statuses['wc-cancelled-cocex'] = __('Shipment cancelled  Correos - CEX', 'correosoficial');
		$order_statuses['wc-returned-cocex'] = __('Shipment returned Correos - CEX', 'correosoficial');
		return $order_statuses;
	}

	private function correosoficialIncludes() {
		if (!class_exists('Smarty\\Smarty')) {
			// Smarty 5 usa namespaces, comprobamos si la clase existe antes de cargar el autoload para evitar conflictos con otros plugins que usen Smarty 3 sin namespaces
			require_once __DIR__ . '/vendor/autoload.php';
		}

		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/config.php';

		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/header.php';

		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/controllers/admin/AdminCorreosOficialHomeController.php';
		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/controllers/admin/AdminCorreosOficialSettingsController.php';
		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/controllers/admin/AdminCorreosOficialUtilitiesController.php';
		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/controllers/admin/AdminCorreosOficialNotificationsController.php';

		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/classes/CorreosOficialCarrierExtraContent.php';
		include_once $this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/classes/CorreosOficialNifNumberForCheckout.php';
	}

	private function correosoficialInitHooks() {
        global $co_module_url_wc;
        global $co_module_url;

		if (is_admin()) {
			//Analitica
        	add_action('all_admin_notices', array( $this, 'correosAdminHeader' ));
			add_action('admin_enqueue_scripts', array( $this, 'adminMenuCSS' ));
        	add_action('admin_menu', array( $this, 'menuCorreosOficial' ));
			//Admin Order (Pedido)
        	add_action( 'add_meta_boxes', array( $this, 'displayAdminOrderBox' ) );
		}

        $this->smarty = CorreosOficialSmarty::loadSmartyInstance();
        $this->smarty->setTemplateDir(plugin_dir_path(__FILE__) . '/views/templates/admin');

        $co_module_url = plugin_dir_url(__FILE__);
        $this->smarty->assign('co_base_dir', $co_module_url);

        // Checkout
		add_action('wp_enqueue_scripts', array( $this, 'loadCheckoutStyles' ), 19);
		add_action('woocommerce_after_shipping_rate', array( $this, 'hookdisplayCarrierExtraContent' ), 20);
		add_action('wp_enqueue_scripts', array( $this, 'loadCheckoutScripts' ), 21);
	}

	public static function correosOficialActivation() {
		// chequeamos tablas y compatibilidad con PHP cada vez que se activa el plugin.		
		self::checkPHPversionCompatibility();
		self::installTables();

		if (version_compare(PHP_VERSION, '5.6', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('Mi Plugin requiere al menos PHP 5.6. Por favor actualiza PHP.');
		}
		if (CorreosOficialConfig::getConfigValue('GDPR') === '1') {
			( new Analitica() )->moduleRecord();
		}

		// Init Cron tasks
		self::createCronTasks();
	}

	public static function correosOficialDeactivation() {
		( new Analitica() )->disableCall();

		wp_clear_scheduled_hook('correosoficial_tracking_cron_event');
	}

	private static function createCronTasks() {
		// Init Cron tasks
		if (! wp_next_scheduled('correosoficial_tracking_cron_event')) {
			wp_schedule_event(time(), 'correosoficial_cron', 'correosoficial_tracking_cron_event');
		}
	}

	public function correosAdminHeader() {
		if (
			isset($_GET['page'])
			&& $_GET['page'] === 'notifications'
		) {
			return;
		}

		if (CorreosOficialConfig::get_config_status('GDPR') === '0') {
			return;
		}

		$lastNotificationsCall = (new CorreosOficialConfig('Analitica_date'))->get_value();
		$cachedCount = get_option('correos_notification_count', null);

		if (
			$cachedCount === null ||
			!$lastNotificationsCall ||
			( $lastNotificationsCall && strtotime(gmdate('Y-m-d H:i:s')) > strtotime($lastNotificationsCall . '+ 1 hours') )
		) {
			CorreosOficialConfig::set_value_by_name('Analitica_date', gmdate('Y-m-d H:i:s'));

			$notifications = ( new Analitica() )->getNotifications();

			$total_notifications = 0;
			if ($notifications['status'] === 200) {
				$total_notifications = count($notifications['output']);
			}

			update_option('correos_notification_count', $total_notifications);
		} else {
			$total_notifications = (int) $cachedCount;
		}

		if ($total_notifications === 0) {
			return;
		}

		$this->smarty->assign(array(
			'notifications' => $total_notifications,
			'img' => plugins_url( 'views/commons/img/logo.gif', __FILE__ ),
			'link' => admin_url() . 'admin.php?page=notifications',
			'msg1' => __('Yo have', 'correosoficial'),
			'msg2' => __(' notifications without read in the Correosoficial module', 'correosoficial'),
			'msgButton' => __('Go to notifications', 'correosoficial'),
		));

		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);
		return $this->smarty->display(__DIR__ . '/views/templates/admin/notificationalert.tpl');
	}

	public function loadCheckoutStyles() {
		// Solo cargar estilos en páginas de checkout (o cuando se edita ese contenido con Elementor),
		// para evitar que las reglas CSS interfieran con el tema en el resto de páginas (home, tienda, etc.).
		if ( ! $this->isCheckoutContext() ) {
			return;
		}

		wp_enqueue_style('co_global', plugins_url('views/commons/css/global.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');

		// Detectar Elementor (comprobación sencilla y segura en frontend)
		$elementor_active = class_exists('\Elementor\Plugin') || defined('ELEMENTOR_VERSION');

		if ( $elementor_active ) {
			wp_enqueue_style('co_checkout_elementor_compatiblity', plugins_url('views/commons/css/co_elementor_checkout.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		} else {
			wp_enqueue_style('co_checkout', plugins_url('views/commons/css/checkout.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
			wp_enqueue_style('co_override_checkout', plugins_url('override/css/checkout.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		}
	}

	/**
	 * Determina si el contexto actual es la página de checkout (o cart) donde tiene sentido
	 * cargar los estilos/scripts de transportistas del plugin.
	 */
	private function isCheckoutContext() {
		// Funciones WooCommerce (solo existen si WC está cargado)
		if ( function_exists('is_checkout') && is_checkout() ) {
			return true;
		}
		if ( function_exists('is_cart') && is_cart() ) {
			return true;
		}

		// Si el editor de Elementor está activo sobre esta página, dejamos que cargue
		// para que la previsualización del checkout se vea correctamente.
		if ( isset($_GET['elementor-preview']) ) {
			return true;
		}

		return false;
	}

	public function loadCheckoutScripts() {

		$google_api_key = CorreosOficialConfig::getConfigValue('GoogleMapsApi');
		if (!empty($google_api_key)) {
			wp_enqueue_script('google_js', 'https://maps.googleapis.com/maps/api/js?callback=Function.prototype&key=' . $google_api_key, array(), CORREOS_OFICIAL_VERSION, true);
		}

		wp_enqueue_script('co_woocommerce', plugins_url('/js/woocommerce.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		wp_enqueue_script('co_reference_code', plugins_url('js/library/reference-code.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		wp_enqueue_script('co_checkout_hide_map', plugins_url('/js/checkout_hide_map.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		self::definePluginURLS();

		// Encolando el primer script
		wp_enqueue_script(
			'co_ajax',
			plugins_url('correosoficial/views/js/commons/ajax.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		// Localizando variables para el primer script
		wp_localize_script(
			'co_ajax',
			'varsAjax',
			array()
		);

		$whereAmI = '';

		if (is_cart()) {
			$whereAmI = 'cart';
		} elseif (is_checkout()) {
			$whereAmI = 'checkout';
		}

		// Encolando el segundo script
		wp_enqueue_script(
			'co_ajax_wc',
			plugins_url('correosoficial/js/ajax_wc_checkout.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		// Localizando variables para el segundo script
		wp_localize_script(
			'co_ajax_wc',
			'varsAjax',
			array(
				'nonce' => wp_create_nonce('correosoficial_nonce'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'whereAmI' => $whereAmI,
				'codMethodId' => CorreosOficialConfig::getConfiguredCodMethod(),
				'codMethodAliases' => CorreosOficialConfig::getConfiguredCodMethodAliases(),
			)
		);
	}

	public function hookdisplayCarrierExtraContent( $session_cart_params ) {
		return new CorreosOficialCarrierExtraContent($session_cart_params, $this->smarty);
	}

	public function displayAdminOrderBox() {
		if (!CorreosOficialUtils::sislogModuleIsActive()) {
			$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

			// Meta box para mostrar el hook de pedidos
			add_meta_box(
				'correosoficial-order',
				'Correos Ecommerce',
				array( $this, 'correosoficialOrderMetaBox' ),
				$screen,
				'normal',
				'low'
			);
		}
	}

	public function correosoficialOrderMetaBox() {
		include_once WP_PLUGIN_DIR . '/correosoficial/langs/orderLang.php';
		include_once __DIR__ . '/classes/CorreosOficialAdminOrderHook.php';

		$plugin_dir = WP_PLUGIN_DIR . '/correosoficial/';
		$google_api_key = CorreosOficialConfig::getConfigValue('GoogleMapsApi');

		// Carga de estilos
		wp_enqueue_style('co_jquery_datatables', plugins_url('views/commons/css/datatables/jquery.dataTables.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_style('co_bootstrap_min', plugins_url('views/commons/css/bootstrap.min.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_style('co_global', plugins_url('views/commons/css/global.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_style('co_admin-order', plugins_url('views/commons/css/admin-order.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_style('co_override_admin-order', plugins_url('override/css/admin-order.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');

		// Carga de scripts
		self::loadgeneralScripts();
		wp_enqueue_script('co_jquery_validate', plugins_url('views/js/jquery.validate.min.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		wp_enqueue_script('co_custom-validators', plugins_url('views/js/commons/common-settings.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		wp_enqueue_script('co_admin_order_library', plugins_url('views/js/library/admin-order.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		wp_enqueue_script('co_admin_order', plugins_url('js/admin-order.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		wp_enqueue_script('co_jquery_datatables', plugins_url('views/js/datatables/jquery.dataTables.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
				
		$google_api_key = CorreosOficialConfig::getConfigValue('GoogleMapsApi');
		if (!empty($google_api_key)) {
			wp_enqueue_script('co_maps', 'https://maps.googleapis.com/maps/api/js?callback=Function.prototype&key=' . $google_api_key, array(), CORREOS_OFICIAL_VERSION, true);
		}
		//Cargar variable varAjax.
		wp_localize_script(
			'co_admin_order',
			'varsAjax',
			array(
				'nonce' => wp_create_nonce('correosoficial_nonce'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
			)
		);

		wp_enqueue_script(
			'co_ajax',
			plugins_url('correosoficial/views/js/commons/ajax.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		wp_enqueue_script(
			'co_ajax_wc',
			plugins_url('correosoficial/js/ajax_wc_admin_order.js'),
			array(),
			CORREOS_OFICIAL_VERSION,
			true
		);

		self::loadBootstrapScripts();
		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);

		return new CorreosOficialAdminOrderHook($this->smarty, $plugin_dir);
	}

	public function menuCorreosOficial() {
		// $home = __('Home', 'correosoficial');
		$settings = __('Settings', 'correosoficial');
		$notificationsLabel = __('Notifications', 'correosoficial');

		$notificationCount = (int) get_option('correos_notification_count', 0);
		$notificationsMenuTitle = $notificationCount > 0
			? sprintf(
				'%s <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>',
				esc_html($notificationsLabel),
				$notificationCount,
				$notificationCount
			)
			: esc_html($notificationsLabel);

		add_menu_page('Correos Oficial ' . CORREOS_OFICIAL_VERSION, 'Correos Ecommerce', 'manage_options', 'correosoficial', array( $this, 'getContentSettings' ), plugins_url('correosoficial/views/commons/img/logos/correos_logo_white.svg'));
		// add_submenu_page('correosoficial', $home, $home, 'manage_options', 'home', array($this, 'getContentHome'));

		add_submenu_page('correosoficial', $settings, $settings, 'manage_options', 'settings', array( $this, 'getContentSettings' ));

		$utilities = __('Utilities', 'correosoficial');
		if (!$this->marketplaceIsActive) {
			add_submenu_page('correosoficial', $utilities, $utilities, 'manage_options', 'utilities', array( $this, 'getContentUtilities' ));
		}

		add_submenu_page('correosoficial', $notificationsLabel, $notificationsMenuTitle, 'manage_options', 'notifications', array( $this, 'getContentNotifications' ));

	}


	public function adminMenuCSS($hook) {
		wp_enqueue_style('co_menu', plugins_url('/override/css/menu.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
	}

	public function getContentHome() {
		if (isset($_GET['page']) && $_GET['page'] == 'home') {

			// Carga de estilos
			self::loadGeneralStyles();
			wp_enqueue_style('co_home', plugins_url('views/commons/css/home.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');

			// Carga de scripts
			self::loadBootstrapScripts();
			self::loadgeneralScripts();
			return new AdminCorreosOficialHomeController($this->smarty);
		}
	}

	public function getContentSettings() {
		if (isset($_GET['page']) && ( $_GET['page'] == 'settings' || $_GET['page'] == 'correosoficial' )) {

			// Carga de estilos
			self::loadGeneralStyles();
			wp_enqueue_style('co_settings', plugins_url('views/commons/css/settings.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
			wp_enqueue_style('co_override_settings', plugins_url('/override/css/settings.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');

			// Carga de scriptsp
			self::loadBootstrapScripts();
			wp_enqueue_script('co_jquery_validate', plugins_url('views/js/jquery.validate.min.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
			wp_enqueue_script('co_custom-validators', plugins_url('views/js/commons/custom-validators.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
			wp_enqueue_script('co_back', plugins_url('js/back.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
						
			self::loadgeneralScripts();
			return new AdminCorreosOficialSettingsController($this->smarty);
		}
	}

	public function getContentUtilities() {
		if (isset($_GET['page']) && $_GET['page'] == 'utilities') {

			//Optimizador DataTable
			$this->dataTableRegisterAjax();

			// Carga de estilos
			self::loadGeneralStyles();
			wp_enqueue_style('co_utilities', plugins_url('views/commons/css/utilities.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
			wp_enqueue_style('co_override_utilities', plugins_url('/override/css/utilities.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');

			// Carga de scripts
			self::loadBootstrapScripts();
			wp_enqueue_script('co_back', plugins_url('js/back.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
						
			self::loadgeneralScripts();
			return new AdminCorreosOficialUtilitiesController($this->smarty);
		}
	}

	public function getContentNotifications() {
		if (isset($_GET['page']) && $_GET['page'] == 'notifications') {

			// Carga de estilos
			self::loadGeneralStyles();
			wp_enqueue_style('co_notifications', plugins_url('views/commons/css/notifications.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');

			// Carga de scripts
			self::loadBootstrapScripts();
			wp_enqueue_script('co_notifications', plugins_url('js/notifications.js', __FILE__), array( 'jquery' ), CORREOS_OFICIAL_VERSION, true);

			wp_localize_script(
				'co_notifications',
				'notificationsVar',
				array(
					'correos_inView_check' => __('Mark as ready and discart', 'correosoficial'),
					'gdpr_nonce' => wp_create_nonce( 'gdpr_nonce' ),
				)
			);

			self::loadgeneralScripts();
			return new AdminCorreosOficialNotificationsController($this->smarty);
		}
	}

	public static function loadGeneralStyles() {
		wp_enqueue_style('co_all', plugins_url('views/commons/css/all.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
		wp_enqueue_style('co_global', plugins_url('/views/commons/css/global.css', __FILE__), array(), CORREOS_OFICIAL_VERSION, 'all');
	}

	public static function loadgeneralScripts() {
		wp_enqueue_script('co_woocommerce', plugins_url('js/woocommerce.js', __FILE__), array(), CORREOS_OFICIAL_VERSION, true);
		self::definePluginURLS();
	}

	public static function loadBootstrapScripts() {
		wp_enqueue_script('co_popper_min', plugins_url('views/js/popper.min.js', __FILE__), array(), '2.9.2', false);
		wp_enqueue_script('co_bootstrap_min', plugins_url('views/js/bootstrap.min.js', __FILE__), array(), '5.0.2', false);
	}

	public function getModuleVersion() {
		$configFile = file_get_contents($this->getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/config.xml');
		$module = new SimpleXMLElement($configFile);
		return (string) $module->version;
	}

	// Funciones auxiliares
	public function getRealPath( $file ) {
		return dirname(realpath($file));
	}

	// Método install de las tablas de la Base de Datos
	public static function installTables() {

		// Control único de versión/upgrade.
		$last_update_option = 'CORREOS_OFICIAL_LAST_UPDATE';
		if ( get_option( $last_update_option ) === CORREOS_OFICIAL_VERSION ) {
			error_log( 'CorreosOficial: Upgrade ya ejecutado para la versión ' . CORREOS_OFICIAL_VERSION . ', saltando.' );
			return;
		}

		error_log( 'CorreosOficial: Ejecutando upgrade manager para versión ' . CORREOS_OFICIAL_VERSION );

		// Tareas Cron
		self::createCronTasks();

		// Delegar toda la lógica de instalación/actualización al Upgrade Manager
		require_once __DIR__ . '/upgrade/CorreosOficialUpgrade.php';
		$result = \CorreosOficial\Upgrade\CorreosOficialUpgrade::run_upgrade();

		if ( $result['success'] ) {
			CorreosOficialUtils::writeInstallErrorLog( 'Upgrade Manager completado correctamente — ' . $result['message'] );
			// Registrar la versión procesada solo si el upgrade fue exitoso.
			update_option( $last_update_option, CORREOS_OFICIAL_VERSION, false );
			// Limpieza de controles legacy de versiones anteriores.
			delete_option( 'correosoficial_install_lock_version' );
			delete_option( 'correosoficial_version' );
		} else {
			CorreosOficialUtils::writeInstallErrorLog( 'Upgrade Manager falló — ' . $result['message'] );
			if ( ! empty( $result['rollback_executed'] ) ) {
				CorreosOficialUtils::writeInstallErrorLog( 'Rollback automático ejecutado — ' . $result['rollback_result']['reverted'] . ' cambios revertidos.' );
			}
			// No actualizamos la marca de versión: en la próxima carga se volverá a intentar.
		}
	}

	public static function areTablesIntalled() {
		global $wpdb;
		$table = $wpdb->prefix . 'correos_oficial_install';
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
		if ( $wpdb->get_var( $query ) !== $table ) {
			return false;
		}
		$record = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE id='1'", OBJECT );
		return ! empty( $record ) ? $record[0]->installed : false;
	}

	/**
	 * Comprobar si una tabla existe en la base de datos
	 * @param string $tableName Nombre de la tabla a comprobar
	 * @return bool True si la tabla existe, False en caso contrario
	 */
	private static function tableExists($tableName) {
		global $wpdb;
		$query = $wpdb->prepare('SHOW TABLES LIKE %s', $tableName);
		return $wpdb->get_var($query) === $tableName;
	}

	/**
	 * Hook Detalles del usuario
	 */
	public function hookOrderDetailDisplayed( $order ) {
		global $co_module_url;
		global $co_page;
		$items = array();
		$co_page = 'my_account';

		include_once WP_PLUGIN_DIR . '/correosoficial/langs/orderDetailLang.php';

		$order_id = $order->get_id();

		$items = $order->get_items('shipping');

		/*
		 * Salimos si no es transportista de correos_oficial
		 */
		if (!count($items) || reset($items)->get_method_id() != 'request_shipping_quote') {
			return false;
		}

		$shipping_number = CorreosOficialOrder::get_shipping_number_by_order_id( $order_id );

		// Salimos si el envío todavía no se ha prerregistrado.
		if ( $shipping_number === null ) {
			return false;
		}

		$this->smarty->assign('co_base_dir', $co_module_url);
		$this->smarty->assign('shipping_number', $shipping_number);
		$this->smarty->registerFilter('pre', [CorreosOficialPrefilter::class, 'preFilterConstants']);

		return $this->smarty->display(self::getRealPath(MODULE_CORREOS_OFICIAL_PATH) . '/views/templates/hook/OrderDetail.tpl');
	}

	public static function checkPHPversionCompatibility() {
		if (version_compare(phpversion(), '7.2', '<')) {
			$out = 'Versión de plugin ' . CORREOS_OFICIAL_VERSION . '. Este plugin necesita PHP7.2+ o PHP8.0+';
			wp_die(esc_html($out));
		}

		if (version_compare(phpversion(), '9', '>=')) {
			$out = 'Versión de plugin ' . CORREOS_OFICIAL_VERSION . '. Este plugin de CorreosEcommerce no es compatible con la versión ' . phpversion() . ' de PHP';
			wp_die(esc_html($out));
		}
	}

	public static function definePluginURLS() {
		wp_localize_script(
			'co_woocommerce',
			'woocommerceVars',
			array(
				'pluginsUrl' => plugins_url(),
				'adminUrl' => get_admin_url(),
			)
		);
	}

	//Optimizador DataTable
	public function dataTableRegisterAjax() {
		wp_enqueue_script('dataTableAjax', plugins_url('/js/ajax_wc_utilities.js', __FILE__), array( 'jquery' ), CORREOS_OFICIAL_VERSION, true);

		// Pasar datos a JavaScript
		wp_localize_script('dataTableAjax', 'dataTableVars', array(
			'dataTableNonce' => wp_create_nonce('dataTableNonce'),
			'dataTableurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('correosoficial_nonce'),
			'ajaxUrl' => admin_url('admin-ajax.php'),
		));
	}

	/**
	 * Eliminamos un pedido si ha sido eliminado permanentemente de Woocommerce->Pedidos
	 */
	public function deleteOrder( $postid, $post = null ) {
		// Verificamos si el post que se esta eliminando es un pedido
		if (get_post_type($postid) == 'shop_order') {
			CorreosOficialOrder::deleteOrder($postid);
		}
	}

	/**
	 * Filtro para ocultar el pago contra reembolso cuando se selecciona un carrier específico
	 */
	public function hide_cod_payment_for_specific_carrier( $available_gateways ) {
		// Solo aplicar en el checkout
		if ( ! is_checkout() && ! wp_doing_ajax() && ! defined('REST_REQUEST') ) {
			return $available_gateways;
		}

		return $this->apply_cod_filter_logic( $available_gateways );
	}

	/**
	 * Lógica central consolidada para ocultar COD según el carrier seleccionado
	 */
	public function apply_cod_filter_logic( $available_gateways ) {
		$cod_candidates = class_exists( 'CorreosOficialConfig' ) ? CorreosOficialConfig::getConfiguredCodMethodAliases() : array( 'cod' );

		$remove_cod_gateways = static function ( $gateways ) use ( $cod_candidates ) {
			foreach ( array_unique( $cod_candidates ) as $cod_candidate ) {
				if ( isset( $gateways[ $cod_candidate ] ) ) {
					unset( $gateways[ $cod_candidate ] );
				}
			}

			return $gateways;
		};

		$posted_request_data = $_POST;
		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			parse_str( wp_unslash( $_POST['post_data'] ), $parsed_post_data );
			if ( is_array( $parsed_post_data ) ) {
				$posted_request_data = array_merge( $posted_request_data, $parsed_post_data );
			}
		}

		$posted_reference_type = isset( $posted_request_data['ReferenceType'] )
			? strtolower( sanitize_text_field( $posted_request_data['ReferenceType'] ) )
			: '';

		if ( in_array( $posted_reference_type, array( 'citypaq', 'citypaq_premium', 'homepaq' ), true ) ) {
			return $remove_cod_gateways( $available_gateways );
		}

		$posted_carrier_id = isset( $posted_request_data['CarrierID'] ) ? (string) absint( $posted_request_data['CarrierID'] ) : '';
		if ( $posted_carrier_id && class_exists( 'CorreosOficialProduct' ) ) {
			$posted_product = ( new CorreosOficialProduct() )->get_by_carrier( (int) $posted_carrier_id );
			if ( $posted_product && method_exists( $posted_product, 'get_product_type' ) && $posted_product->get_product_type() === 'citypaq' ) {
				return $remove_cod_gateways( $available_gateways );
			}
		}

		// Obtener los métodos de envío seleccionados
		if ( function_exists( 'WC' ) && WC()->session ) {
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		} else {
			$chosen_shipping_methods = array();
		}

		if ( ! empty( $chosen_shipping_methods ) ) {
			// Obtener el listado de productos/carriers de Correos
			$correos_products = class_exists( 'CorreosOficialCarrier' )
				? CorreosOficialCarrier::getCarriersProductsList()
				: array();

			foreach ( $chosen_shipping_methods as $chosen_shipping_method ) {
				// Extraer carrier_id del formato request_shipping_quote_X:carrier_id
				if ( ! preg_match( '/:(\d+)$/', $chosen_shipping_method, $matches ) ) {
					continue;
				}
				$selected_carrier_id = $matches[1];

				foreach ( $correos_products as $product ) {
					$carrier_id   = isset( $product['id_carrier'] ) ? (string) $product['id_carrier'] : '';
					$product_type = isset( $product['product_type'] ) ? strtolower( $product['product_type'] ) : '';

					if ( $carrier_id === $selected_carrier_id ) {
						// Ocultar COD para CityPaq (oficina PCE se controla por selectedPickupLocationOption/sendDelivery)
						if ( $product_type === 'citypaq' ) {
							$available_gateways = $remove_cod_gateways( $available_gateways );
						}
						break 2;
					}
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * Registra filtros específicos para el checkout con bloques
	 */
	public function register_checkout_block_filters() {
		// Filtros específicos para bloques que reutilizan la lógica central
		add_filter('woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_cod_filter_for_blocks' ), 10, 2);
		add_action('woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_cod_filter_for_blocks' ), 10, 2);
	}

	/**
	 * Filtro unificado para ocultar COD en checkout con bloques
	 * Reutiliza la lógica central y aplica filtros dinámicos para bloques
	 */
	public function apply_cod_filter_for_blocks( $order, $request = null ) {
		// Aplicar filtro dinámico con alta prioridad para bloques
		add_filter('woocommerce_available_payment_gateways', array( $this, 'apply_cod_filter_logic' ), 999);
		return $order;
	}

	/**
	 * Registra filtros para las peticiones REST API del checkout de bloques
	 */
	public function register_rest_api_filters() {
		// Solo aplicar en rutas específicas del checkout
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/wc/store' ) !== false ) {
			add_filter('woocommerce_available_payment_gateways', array( $this, 'apply_cod_filter_logic' ), 999);
		}
	}

	/**
	 * Tareas de asignación de transportista a los pedidos que entran por Channable
	 */
	public function channableTasks( $item_id, $item, $id_order ) {

		global $wpdb;

		// Comprobamos si el item es un objeto de tipo WC_Order_Item_Shipping
		if ( ! $item instanceof WC_Order_Item_Shipping ) {
			return;
		}

		// Expresión regular para comprobar si el nombre del item tiene la palabra "Amazon"
		$automaticProductAssignmentText = CorreosOficialConfig::getConfigValue('AutomaticProductAssignmentText');
		
		// Validar que el texto no esté vacío
		if (empty($automaticProductAssignmentText)) {
			return;
		}

		$productId = CorreosOficialConfig::getConfigValue('AutomaticProductAssignmentProduct');

		if (!$productId) {
			return;
		}

		$product = CorreosOficialProduct::get_product($productId);

		if (!empty($product)) {
			$productName = $product[0]->name;

			// Escapar caracteres especiales de regex y crear el patrón de búsqueda
			$escapedText = preg_quote($automaticProductAssignmentText, '~');
			$pattern = '~' . $escapedText . '~i';
			
			$itemName = $item->get_name();
			
			// Comprobamos si el nombre del item contiene la palabra guardada
			if (!empty($itemName) && preg_match($pattern, $itemName)) {
				// Si el nombre del item contiene la palabra guardada, añadimos una nota al pedido
				$order = new WC_Order($id_order);
				$order->add_order_note('El módulo Correos Ecommerce ha cambiado el transportista.');

				//$carrier_order = CorreosOficialCarrier::getCarrierByProductId($productId);

				$instanceId = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT instance_id FROM %i WHERE method_id=%s',
						CorreosOficialUtils::getPrefix() . 'woocommerce_shipping_zone_methods',
						'request_shipping_quote_' . $productId
					)
				)[0]->instance_id;

				// Añadimos método de envío
				$shipping_method = new WC_Order_Item_Shipping($item_id);
				$shipping_method->set_method_title($productName . ' (' . $item->get_name() . ')');
				$shipping_method->set_method_id('request_shipping_quote_' . $productId);
				$shipping_method->set_instance_id($instanceId);
				$shipping_method->save();


				//guardamos en el log
				$filename = WP_PLUGIN_DIR . '/correosoficial/log/log_automatic_product_assignment.txt';
				file_put_contents(
					$filename,
					gmdate('Y-m-d H:i:s') . " Pedido con Id {$id_order} Se ha asignado automáticamente el transportista de origen '{$automaticProductAssignmentText}' al producto '{$productName}'\r\n",
					FILE_APPEND
				);

			}
		}
	}

	/**
	 * Añadir campos personalizados en la pestaña de envío del producto
	 */
	public function addCustomsProductFields() {
		global $post;

		echo '<div class="options_group">';

		// Campo para código HS
		woocommerce_wp_text_input(
			array(
				'id'          => '_hs_code',
				'label'       => __('HS Code', 'correosoficial'),
				'placeholder' => '',
				'desc_tip'    => true,
				'description' => __('Código del Sistema Armonizado para aduanas', 'correosoficial'),
				'value'       => get_post_meta($post->ID, '_hs_code', true),
			)
		);

		// Campo para país de origen
		woocommerce_wp_text_input(
			array(
				'id'          => '_country_of_origin',
				'label'       => __('Country of Origin', 'correosoficial'),
				'placeholder' => '',
				'desc_tip'    => true,
				'description' => __('País de origen del producto (código ISO 2 letras, ej: ES, FR)', 'correosoficial'),
				'value'       => get_post_meta($post->ID, '_country_of_origin', true),
			)
		);

		echo '</div>';
	}

	/**
	 * Guardar los campos personalizados del producto
	 */
	public function saveCustomsProductFields($post_id) {
		// Guardar código HS
		if (isset($_POST['_hs_code'])) {
			update_post_meta($post_id, '_hs_code', sanitize_text_field($_POST['_hs_code']));
		}

		// Guardar país de origen
		if (isset($_POST['_country_of_origin'])) {
			update_post_meta($post_id, '_country_of_origin', sanitize_text_field($_POST['_country_of_origin']));
		}
	}

	/**
	 * Registra el endpoint público /correosoficial/v1/checkfeature
	 * para consultar si una funcionalidad del módulo está activa.
	 *
	 * Ejemplo: GET /wp-json/correosoficial/v1/checkfeature?feature=marketplace
	 */
	public function registerCheckFeatureEndpoint() {
		register_rest_route('correosoficial/v1', '/checkfeature', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handleCheckFeature' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'feature' => array(
					'required'          => true,
					'validate_callback' => function( $param ) {
						return is_string( $param ) && ! empty( trim( $param ) );
					},
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
	}

	/**
	 * Callback del endpoint checkfeature.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handleCheckFeature( $request ) {
		$feature  = $request->get_param( 'feature' );
		$features = array(
			'marketplace' => CorreosOficialMarketplace::CONFIG_KEY_ACTIVATE,
		);

		if ( ! isset( $features[ $feature ] ) ) {
			return new WP_REST_Response(
				array(
					'feature' => $feature,
					'active'  => false,
					'error'   => 'Unknown feature: ' . $feature,
				),
				200
			);
		}

		$status = CorreosOficialConfig::get_config_status( $features[ $feature ] );
		$active = ! empty( $status->value ) && $status->value === 'on';

		return new WP_REST_Response(
			array(
				'feature' => $feature,
				'active'  => $active,
			),
			200
		);
	}
}

// Registrar hooks de activación / desactivación
register_activation_hook(
    __FILE__,
    ['correosoficial', 'correosOficialActivation']
);

register_deactivation_hook(
    __FILE__,
    ['correosoficial', 'correosOficialDeactivation']
);

$co = new CorreosOficial();
