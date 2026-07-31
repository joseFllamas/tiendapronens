<?php
namespace CorreosOficial\Classes;

require_once __DIR__ . '/../vendor/datatables/autoload.php';
use CorreosOficial\Models\CorreosOficialSender;
use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Classes\CorreosOficialSenders;
use Ozdemir\Datatables\Datatables;
use Ozdemir\Datatables\DB\WPAdapter;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use LogicException;

define('CO_LEFT_JOIN', 'LEFT JOIN ');
define('CO_JOIN', 'JOIN ');

class CorreosOficialUtilitiesDatatable {

	protected $dt;
	protected $config;
	protected $prefix;

	public function __construct() {
		$this->dt = $this->getConnectionToDatabase();
		global $wpdb;
		$this->prefix = $wpdb->prefix;
	}

	public function getConnectionToDatabase() {
		return new Datatables(new WPAdapter(array()));
	}

	public function loadColumnContent( $getColumn ) {

		switch ($getColumn) {
			case 'products':
				$this->dt->edit('products', function ( $data ) {
					return $this->getProductsNames($data);
				});
				break;
			case 'carrier_type':
				$this->dt->add('carrier_type', function ( $data ) {
					return $this->getCarrierType($data);
				});
				break;
			case 'pickup_number':
				$this->dt->add('pickup_number', function ( $data ) {
					return $this->getPickupOrReturnPickupNumber($data);
				});
				break;
			case 'order_state':
				$this->dt->edit('order_state', function ( $data ) {
					return $this->getOrderStateLabel($data['order_state']);
				});
				break;
			case 'customer_name':
				$this->dt->edit($getColumn, function ( $data ) {
					return $this->getCustomerName($data['id_order']);
				});
				break;
			default:
				throw new LogicException('ERROR 17030: No se ha encontrado el filtro ha utilizar');
		}
	}

	public function getProductsNames( $data ) {
		$order = wc_get_order($data['id_order']);
		
		// Verificar que el pedido existe
		if (!$order || !is_a($order, 'WC_Order')) {
			return '';
		}
		
		$items = $order->get_items();
		
		$productNames = array_map(function ( $item ) {
			return html_entity_decode($item->get_name(), ENT_QUOTES, 'UTF-8');
		}, $items);
		$productName = implode(' ', $productNames);

		return ( strlen($productName) > 30 ) ? mb_substr($productName, 0, 27) . '...' : $productName;
	}
	
	public function getCarrierType( $data ) {
		if ($data['shipping_number']) {
			global $wpdb;
			$company = $wpdb->get_results($this->getSavedOrderCarrier($data['shipping_number']), ARRAY_A);
			return $company[0]['company'];
		}

		return $data['company'];
	}

	public function getPickupOrReturnPickupNumber( $data ) {
		if (isset ($data['pickup_number']) && $data['pickup_number'] != '') {
			return $data['pickup_number'];
		} elseif (isset ($data['pickup_return']) && $data['pickup_return'] != '') {
			return $data['pickup_return'];
		}

		return '';
	}

	public function getCustomerName( $idOrder ) {
		$order = wc_get_order($idOrder);
		if ($order && is_a($order, 'WC_Order')) {
			$firstName = $order->get_shipping_first_name();
			$secondName = $order->get_shipping_last_name();
			return $firstName . ' ' . $secondName;
		}
		return '';
	}

	/**
	 * Resuelve la etiqueta legible de un estado de pedido.
	 *
	 * Soporta:
	 *  - Estados nativos de WooCommerce (wc-processing, wc-completed, ...).
	 *  - Estados custom registrados por este u otros módulos (wc-preparesga,
	 *    wc-okcorreosecomsga, A010000V, etc.), ya que wc_get_order_statuses()
	 *    aplica el filtro 'wc_order_statuses' al que se enganchan esos plugins.
	 *  - Fallback: devuelve el valor tal cual (sin traducir al dominio
	 *    'woocommerce') para no generar etiquetas engañosas.
	 */
	protected function getOrderStateLabel( $rawStatus ) {
		$raw = is_string($rawStatus) ? trim($rawStatus) : '';
		if ($raw === '') {
			return '';
		}

		$slug = (strpos($raw, 'wc-') === 0) ? $raw : 'wc-' . $raw;

		if (function_exists('wc_get_order_statuses')) {
			$statuses = wc_get_order_statuses();
			if (isset($statuses[$slug])) {
				return $statuses[$slug];
			}
			if (isset($statuses[$raw])) {
				return $statuses[$raw];
			}
		}

		return $raw;
	}

	// Filtro por creación de pedido
	public function loadDateFilter( $from, $to ) {

		if ($from == '1970-01-01' && $to == '1970-01-01') {
			$from = gmdate('Y-m-d');
			$to = gmdate('Y-m-d');
		}

		$this->dt->filter('date_add', function () use ( $from, $to ) {

			$value = $this->searchValue() ? $this->searchValue() : '';

			if (!$value) {
				return $this->between($from . ' 00:00:00', $to . ' 23:59:59');
			}
		});
	}

	// Filtro por etiquetado
	public function loadByDateFilter( $field, $from, $to ) {

		if ($from == '1970-01-01' && $to == '1970-01-01') {
			$from = gmdate('Y-m-d');
			$to = gmdate('Y-m-d');
		}

		$this->dt->filter($field, function () use ( $from, $to ) {
			$value = $this->searchValue() ? $this->searchValue() : '';

			if (!$value) {
				return $this->between($from . ' 00:00:00', $to . ' 23:59:59');
			}
		});
	}

	public function getSenders() {
		$sendersData = CorreosOficialSender::get_senders_with_codes();

		if ( empty( $sendersData ) ) {
			return array();
		}
	
		$transformSender = function ( $sender ) {
			$correosCode = $sender['correos_code'];
			$cexCode = $sender['cex_code'];
	
			if ($correosCode != 0 && $cexCode == 0) {
				$senderCompany = 'Correos';
			} elseif ($cexCode != 0 && $correosCode == 0) {
				$senderCompany = 'CEX';
			} elseif ($correosCode != 0 && $cexCode != 0) {
				$senderCompany = 'all';
			} else {
				$senderCompany = '';
			}
	
			return array(
				'sender_id' => $sender['id'],
				'sender_data' => array(
					'name' => $sender['sender_name'],
					'company' => $senderCompany,
					'sender_iso_code' => $sender['sender_iso_code_pais'],
					'default' => $sender['sender_default'],
				),
			);
		};
	
		return array_map($transformSender, $sendersData);
	}

	public function loadGestionDatatableSelectors() {
		$senders = $this->getSenders();
	
		$this->dt->add('senders', function ( $data ) use ( $senders ) {
			$disable = $data['first_shipping_number'] ? 'disabled' : '';
			$orderId = $data['id_order'];
			$savedSenderId = $data['saved_sender'];
	
			$options = array_map(function ( $sender ) use ( $savedSenderId ) {
				$selected = ($savedSenderId == $sender['sender_id']) ? 'selected': (($sender['sender_data']['default'] == 1
					&& empty($savedSenderId)) ? 'selected' : '');

				$isoCode = htmlspecialchars($sender['sender_data']['sender_iso_code'], ENT_QUOTES, 'UTF-8');
				$company = htmlspecialchars($sender['sender_data']['company'], ENT_QUOTES, 'UTF-8');
				$name = htmlspecialchars($sender['sender_data']['name'], ENT_QUOTES, 'UTF-8');
	
				return "<option data-iso='{$isoCode}' data-scope='{$company}' value='{$sender['sender_id']}' {$selected}>{$name}</option>";
			}, $senders);
	
			// Unir las opciones y construir el select
			$optionsHtml = implode("\n", $options);
			return "<select id='sender_option_{$orderId}' name='sender_option_{$orderId}' class='custom-select select_sender' required {$disable}>{$optionsHtml}</select>";
		});
	}

	public function parseIdsFilter( $value ) {
		$value = trim($value);
		if ($value === '') {
			return null;
		}

		$ids = array();

		if (strpos($value, ';') !== false) {
			$parts = explode(';', $value);
		} elseif (strpos($value, ',') !== false) {
			$parts = explode(',', $value);
		} else {
			$id = intval($value);
			if ($id > 0) {
				return 'id_order = ' . $id;
			}
			return null;
		}

		foreach ($parts as $part) {
			$id = intval(trim($part));
			if ($id > 0) {
				$ids[] = $id;
			}
		}

		if (count($ids) === 0) {
			return null;
		}

		if (count($ids) === 1) {
			return 'id_order = ' . $ids[0];
		}

		return 'id_order IN (' . implode(',', $ids) . ')';
	}

	public function loadGestionDatablePage( $from, $to ) {
		$defaultPackage = CorreosOficialConfig::getConfigValue('DefaultPackages');

		$fromDate = gmdate($from);
		$toDate = gmdate($to);

		if (wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
			$query_hpos = "SELECT DISTINCT
			NULL AS c0,
			wco.id AS id_order,
			wco.id AS reference,
			(SELECT GROUP_CONCAT(DISTINCT woi.order_item_name SEPARATOR ', ') FROM {$this->prefix}woocommerce_order_items woi WHERE woi.order_id = wco.id AND woi.order_item_type = 'line_item') AS products,
			coo.shipping_number AS first_shipping_number,
			COALESCE(cop_mod.company, cop_reg.company) AS carrier_type,
			COALESCE(NULLIF(wco.status, ''), NULLIF(coo.status, ''), coo.last_status) AS order_state,
			wcom.meta_value AS customer_name,
			wco.date_created_gmt AS date_add,
		CASE 
			WHEN coo.shipping_number IS NOT NULL AND cop_mod.product_type IS NULL THEN NULL
			WHEN coo.shipping_number IS NOT NULL AND cop_mod.product_type NOT IN ('office', 'citypaq', 'pudocex') THEN NULL
			ELSE {$this->prefix}correos_oficial_requests.reference_code
		END AS office,
			COALESCE(cop_mod.name, cop_reg.name) AS name,
			coo.id_sender AS saved_sender,
			COALESCE(cop_mod.id, cop_reg.id) AS id_product,
			coo.bultos AS bultos,
			coo.AT_code AS AT_code,
		CASE
			WHEN coo.shipping_number != '' THEN (SELECT sender_iso_code_pais FROM {$this->prefix}correos_oficial_senders WHERE id = coo.id_sender)
			ELSE (SELECT sender_iso_code_pais FROM {$this->prefix}correos_oficial_senders WHERE sender_default = 1)
		END AS sender_iso_code,
			wcoa.country AS delivery_iso_code,
			coo.shipping_number,
			cop_reg.name AS register_product,
			cop_mod.name AS mod_product,
			cop_reg.company AS register_company,
			cop_mod.company AS mod_company
		FROM
			{$this->prefix}wc_orders AS wco
			LEFT JOIN {$this->prefix}woocommerce_order_items AS woi_ship ON (woi_ship.order_id = wco.id AND woi_ship.order_item_type = 'shipping')
			LEFT JOIN {$this->prefix}woocommerce_order_itemmeta AS woim_instance ON (woim_instance.order_item_id = woi_ship.order_item_id AND woim_instance.meta_key = 'instance_id')
			LEFT JOIN {$this->prefix}woocommerce_order_itemmeta AS woim_method ON (woim_method.order_item_id = woi_ship.order_item_id AND woim_method.meta_key = 'method_id')
			LEFT JOIN {$this->prefix}correos_oficial_carriers_products AS cocp ON cocp.id_carrier = COALESCE(
				NULLIF(woim_instance.meta_value, ''),
				SUBSTRING_INDEX(woim_method.meta_value, ':', -1)
			)
			LEFT JOIN {$this->prefix}correos_oficial_products AS cop_reg ON cop_reg.id = cocp.id_product
			LEFT JOIN {$this->prefix}correos_oficial_orders AS coo ON coo.id_order = wco.id
			LEFT JOIN {$this->prefix}correos_oficial_products AS cop_mod ON cop_mod.id = coo.id_product
			LEFT JOIN {$this->prefix}wc_order_operational_data ON {$this->prefix}wc_order_operational_data.order_id = wco.id
			LEFT JOIN {$this->prefix}correos_oficial_saved_orders AS cos ON cos.id_order = wco.id
			LEFT JOIN {$this->prefix}wc_orders_meta AS wcom ON wcom.order_id = wco.id AND wcom.meta_key = '_shipping_address_index'
			LEFT JOIN {$this->prefix}correos_oficial_requests ON {$this->prefix}correos_oficial_requests.id_order = wco.id
			LEFT JOIN {$this->prefix}correos_oficial_senders AS wcos ON wcos.id = coo.id_sender
			LEFT JOIN {$this->prefix}wc_order_addresses AS wcoa ON wcoa.order_id = wco.id AND wcoa.address_type = 'shipping'
			WHERE wco.date_created_gmt BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
			AND wco.type = 'shop_order'
			AND wco.status != 'wc-checkout-draft'
			AND NOT EXISTS (
				SELECT 1 FROM {$this->prefix}correos_oficial_sga_orders_status s WHERE s.order_id = wco.id
			)
			GROUP BY wco.id";
			
			$this->dt->query($query_hpos);
        } else {
            $query_posts = "SELECT DISTINCT
            NULL AS c0,
            wp.ID AS id_order,
            wp.ID AS reference,
            (SELECT GROUP_CONCAT(DISTINCT woi.order_item_name SEPARATOR ', ') FROM {$this->prefix}woocommerce_order_items woi WHERE woi.order_id = wp.ID AND woi.order_item_type = 'line_item') AS products,
            coo.shipping_number AS first_shipping_number,
            COALESCE(cop_mod.company, cop_reg.company) AS carrier_type,
            COALESCE(NULLIF(wp.post_status, ''), NULLIF(coo.status, ''), coo.last_status) AS order_state,
            wcom.meta_value AS customer_name,
            wp.post_date AS date_add,
        CASE
            WHEN coo.shipping_number IS NOT NULL AND cop_mod.product_type IS NULL THEN NULL
            WHEN coo.shipping_number IS NOT NULL AND cop_mod.product_type NOT IN ('office', 'citypaq', 'pudocex') THEN NULL
            ELSE {$this->prefix}correos_oficial_requests.reference_code
        END AS office,
            COALESCE(cop_mod.name, cop_reg.name) AS name,
            coo.id_sender AS saved_sender,
            COALESCE(cop_mod.id, cop_reg.id) AS id_product,
            coo.bultos AS bultos,
            coo.AT_code AS AT_code,
        CASE
            WHEN coo.shipping_number != '' THEN (SELECT sender_iso_code_pais FROM {$this->prefix}correos_oficial_senders WHERE id = coo.id_sender)
            ELSE (SELECT sender_iso_code_pais FROM {$this->prefix}correos_oficial_senders WHERE sender_default = 1)
        END AS sender_iso_code,
            wcoa.meta_value AS delivery_iso_code,
            coo.shipping_number,
            cop_reg.name AS register_product,
            cop_mod.name AS mod_product,
            cop_reg.company AS register_company,
            cop_mod.company AS mod_company
        FROM
            {$this->prefix}posts AS wp
            LEFT JOIN {$this->prefix}woocommerce_order_items AS woi_ship ON (woi_ship.order_id = wp.ID AND woi_ship.order_item_type = 'shipping')
            LEFT JOIN {$this->prefix}woocommerce_order_itemmeta AS woim_instance ON (woim_instance.order_item_id = woi_ship.order_item_id AND woim_instance.meta_key = 'instance_id')
            LEFT JOIN {$this->prefix}woocommerce_order_itemmeta AS woim_method ON (woim_method.order_item_id = woi_ship.order_item_id AND woim_method.meta_key = 'method_id')
            LEFT JOIN {$this->prefix}correos_oficial_carriers_products AS cocp ON cocp.id_carrier = COALESCE(
                NULLIF(woim_instance.meta_value, ''),
                SUBSTRING_INDEX(woim_method.meta_value, ':', -1)
            )
            LEFT JOIN {$this->prefix}correos_oficial_products AS cop_reg ON cop_reg.id = cocp.id_product
            LEFT JOIN {$this->prefix}correos_oficial_orders AS coo ON coo.id_order = wp.ID
            LEFT JOIN {$this->prefix}correos_oficial_products AS cop_mod ON cop_mod.id = coo.id_product
            LEFT JOIN {$this->prefix}correos_oficial_saved_orders AS cos ON cos.id_order = wp.ID
            LEFT JOIN {$this->prefix}postmeta AS wcom ON wcom.post_id = wp.ID AND wcom.meta_key = '_shipping_address_index'
            LEFT JOIN {$this->prefix}correos_oficial_requests ON {$this->prefix}correos_oficial_requests.id_order = wp.ID
            LEFT JOIN {$this->prefix}correos_oficial_senders AS wcos ON wcos.id = coo.id_sender
            LEFT JOIN {$this->prefix}postmeta AS wcoa ON wcoa.post_id = wp.ID AND wcoa.meta_key = '_shipping_country'
            WHERE wp.post_date_gmt BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
            AND wp.post_type = 'shop_order'
            AND wp.post_status != 'wc-checkout-draft'
            AND NOT EXISTS (
                SELECT 1 FROM {$this->prefix}correos_oficial_sga_orders_status s WHERE s.order_id = wp.ID
            )
            GROUP BY wp.ID";
            
            $this->dt->query($query_posts);
        }

		// Cargar contenido de columnas
		$this->loadColumnContent('products');
		$this->loadColumnContent('order_state');
		$this->loadColumnContent('customer_name');

		// Filtrar por estado de pedido
		$this->dt->filter('order_state', function () {
			$searchValue = $this->searchValue();
			if ($searchValue) {
				$searchValueLower = strtolower($searchValue);
				$bestMatch = null;
				$bestMatchCount = 0;

				// Buscar concordancias con las traducciones de los estados
				foreach (wc_get_order_statuses() as $key => $value) {
					$valueLower = strtolower($value);
					$position = stripos($valueLower, $searchValueLower);
					if ($position !== false) {
						$matchCount = substr_count($valueLower, $searchValueLower);
						if ($matchCount > $bestMatchCount) {
							$bestMatchCount = $matchCount;
							$bestMatch = $key;
						}
					}
				}

				$return = "order_state LIKE '%" . esc_sql($searchValue) . "%'";
				if ($bestMatch) {
					$return .= " OR order_state LIKE '%" . esc_sql($bestMatch) . "%'";
				}

				return $return;
			}
		});

		$self = $this;
		$this->dt->filter('id_order', function () use ( $self ) {
			$searchValue = $this->searchValue();
			if ($searchValue !== '') {
				return $self->parseIdsFilter($searchValue);
			}
		});

		$this->loadGestionDatatableSelectors();

		$this->dt->edit('bultos', function ( $data ) use ( $defaultPackage ) {
			if (!$data['bultos']) {
				return $defaultPackage;
			}
			return $data['bultos'];
		});

		exit(wp_kses_post($this->dt->generate()));
	}

	public function loadPickupContent() {
		$this->loadColumnContent('pickup_number');

		$this->dt->filter('company', function () {
			return $this->whereIn(array( 'Correos' ));
		});

		$this->dt->filter('pickup', function () {
			$searchValue = $this->searchValue();

			if ($searchValue != '') {
				return "pickup_number LIKE '%" . $searchValue . "%' OR pickup_return LIKE '%" . $searchValue . "%'";
			} else {
				// Esta línea asegura que se devuelvan todos los pedidos si el filtro está vacío
				return '1=1';
			}
		});
	}
	
	public function loadEtiquetasPage( $from, $to, $pickupPage, $printLabelPage ) {
		$customerAddress = $this->getMetaKeyValue('_shipping_address_index');
		$getProducts = '';

		$fromDate = gmdate($from);
		$toDate = gmdate($to);

		if ($printLabelPage) {
			$getProducts =$this->getProducts();
		}

		if (wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
			if ($pickupPage) {
				$this->pickupPageQueryHPOS($customerAddress, $fromDate, $toDate);
			} else {
				$this->dt->query('SELECT DISTINCT
				NULL AS c0,
				wco.id AS id_order,
				wco.id AS reference,
				' . $getProducts . '
				cop.company AS company,
				cos.exp_number AS first_shipping_number,
				' . $customerAddress . ' AS customer_address,
				' . $customerAddress . ' AS customer_name,
				coo.date_add as date_add,
				' . $this->getShippingMethodName() . '
				coo.bultos AS bultos,
				coo.id_sender AS saved_sender
				FROM ' . $this->prefix . 'wc_orders wco
				LEFT JOIN ' . $this->prefix . 'correos_oficial_orders coo ON wco.id = coo.id_order
				JOIN ' . $this->prefix . 'correos_oficial_saved_orders cos ON coo.shipping_number = cos.exp_number
				LEFT JOIN ' . $this->prefix . 'correos_oficial_products cop ON coo.id_product = cop.id
				LEFT JOIN ' . $this->prefix . 'wc_order_operational_data wcod ON wcod.order_id = wco.id
				LEFT JOIN ' . $this->prefix . "correos_oficial_codes coc ON coc.id = wco.customer_id
				WHERE coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
				GROUP BY wco.id");
			}
		} else {
			$customerFirstName = $this->getMetaKeyValue('_shipping_first_name');
			if ($pickupPage) {
				$this->pickupPagequery($customerAddress, $customerFirstName, $fromDate, $toDate);
			} else {
				$this->dt->query('SELECT DISTINCT
				NULL AS c0,
				wp.ID AS id_order,
				wp.ID AS reference,
				' . $getProducts . '
				cop.company AS company,
				cos.exp_number AS first_shipping_number,
				' . $customerAddress . ' AS customer_address,
				' . $customerFirstName . ' AS customer_name,
				coo.date_add AS date_add,
				' . $this->getShippingMethodName() . '
				coo.bultos AS bultos,
				coo.id_sender AS saved_sender
				FROM ' . $this->prefix . 'posts wp
				' . CO_LEFT_JOIN . $this->prefix . "postmeta wpm ON (wp.ID = wpm.post_id AND wpm.meta_key = '_order_key')
				" . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders coo ON (wp.ID = coo.id_order)
				' . CO_JOIN . $this->prefix . 'correos_oficial_saved_orders cos ON (coo.shipping_number = cos.exp_number)
				' . CO_LEFT_JOIN . $this->prefix . "correos_oficial_products cop ON (coo.id_product = cop.id) 
				WHERE coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
				GROUP BY wp.ID");
			}
		}
		
		$this->loadColumnContent('customer_name');

		if ($printLabelPage) {
			$this->loadColumnContent('products');
		}
		if ($pickupPage) {
			$this->loadPickupContent();
		}

		exit(wp_kses_post($this->dt->generate()));
	}

	public function loadResumenPage( $from, $to, $searchByLabelingDate, $searchBySender ) {
		$customerFirstName  = $this->getMetaKeyValue('_shipping_first_name');
		$customerLastName   = $this->getMetaKeyValue('_shipping_last_name');
		$customerAddress    = $this->getMetaKeyValue('_shipping_address_index');
		$customerPostalCode = $this->getMetaKeyValue('_shipping_postcode');

		$fromDate = gmdate($from);
		$toDate = gmdate($to);

		if ($searchByLabelingDate) {
			// Filtrar por fecha de etiquetado
			$dateFilter = "coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
		} else {
			// Filtrar por fecha de creación del pedido
			if (wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
				$dateFilter = "wco.date_created_gmt BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
			} else {
				$dateFilter = "wp.post_date_gmt BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'";
			}
		}
		
		if (wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
			$this->dt->query('SELECT DISTINCT
				NULL AS c0,
				wco.id AS id_order,
				wco.id AS reference,
				cos.exp_number AS first_shipping_number,
				(SELECT cos_inner.shipping_number
				FROM ' . $this->prefix . "correos_oficial_saved_orders cos_inner
				WHERE coo.shipping_number = cos_inner.exp_number
				LIMIT 1) AS cod_bultos,
				cop.company AS company,
				coc.customer_code AS customer_code,
				(SELECT CONCAT_WS(' ', woa_inner.first_name, woa_inner.last_name)
				FROM " . $this->prefix . "wc_order_addresses woa_inner
				WHERE woa_inner.order_id = wco.id AND woa_inner.address_type = 'shipping'
				LIMIT 1) AS customer_name,
				" . $customerAddress . " AS customer_address,
				woa.postcode AS postal_code,
				wco.date_created_gmt AS date_add,
				coo.date_add AS labeling_date,
				IF(coo.manifest_date IS NOT NULL, 'S', 'N') AS manifested,
				coo.manifest_date AS manifest_date,
				coo.id_sender AS sender
			FROM " . $this->prefix . 'wc_orders wco
			JOIN ' . $this->prefix . 'correos_oficial_orders coo ON wco.id = coo.id_order
			JOIN ' . $this->prefix . 'correos_oficial_saved_orders cos ON coo.shipping_number = cos.exp_number
			LEFT JOIN ' . $this->prefix . 'correos_oficial_products cop ON coo.id_product = cop.id
			LEFT JOIN ' . $this->prefix . 'wc_order_operational_data wcod ON wcod.order_id = wco.id
			LEFT JOIN ' . $this->prefix . "wc_order_addresses woa ON woa.order_id = wco.id AND woa.address_type = 'shipping'
			LEFT JOIN (
				SELECT coo.id_order, coo.id_sender, IF(coo.carrier_type = 'correos', cos.correos_code, cos.cex_code) AS id_code
				FROM " . $this->prefix . 'correos_oficial_orders coo
				LEFT JOIN ' . $this->prefix . 'correos_oficial_senders cos ON coo.id_sender = cos.id
			) AS subquery ON wco.id = subquery.id_order
			LEFT JOIN ' . $this->prefix . "correos_oficial_codes coc ON coc.id = subquery.id_code
			WHERE $dateFilter
			GROUP BY wco.id");
		} else {
			$this->dt->query('SELECT DISTINCT
				NULL AS c0,
				wp.id AS id_order,
				wp.id AS reference,
				cos.exp_number AS first_shipping_number,
				(SELECT cos2.shipping_number 
				FROM ' . $this->prefix . 'correos_oficial_saved_orders cos2 
				WHERE coo.shipping_number = cos2.exp_number 
				LIMIT 1) AS cod_bultos,
				cop.company AS company,
				coc.customer_code AS customer_code,
				CONCAT(' . $customerFirstName . ", ' ', " . $customerLastName . ') AS customer_name,
				' . $customerAddress . ' AS customer_address,
				' . $customerPostalCode . " AS postal_code,
				wp.post_date_gmt AS date_add,
				coo.date_add AS labeling_date,
				IF(coo.manifest_date IS NOT NULL, 'S', 'N') AS manifested,
				coo.manifest_date AS manifest_date,
				coo.id_sender AS sender,
				coo.last_status AS last_status
			FROM " . $this->prefix . 'posts wp
			JOIN ' . $this->prefix . 'correos_oficial_orders coo ON wp.id = coo.id_order
			JOIN ' . $this->prefix . 'correos_oficial_saved_orders cos ON coo.shipping_number = cos.exp_number
			LEFT JOIN ' . $this->prefix . "correos_oficial_products cop ON coo.id_product = cop.id
			LEFT JOIN (
				SELECT coo.id_order, coo.id_sender,
				IF(coo.carrier_type = 'correos', cos.correos_code, cos.cex_code) AS id_code
				FROM " . $this->prefix . 'correos_oficial_orders coo
				LEFT JOIN ' . $this->prefix . 'correos_oficial_senders cos ON coo.id_sender = cos.id
			) AS subquery ON wp.id = subquery.id_order
			LEFT JOIN ' . $this->prefix . "correos_oficial_codes coc ON coc.id = subquery.id_code
			WHERE $dateFilter
			GROUP BY wp.id");
		}
			
		if ($searchByLabelingDate) {
			$this->loadByDateFilter('labeling_date', $from, $to);
		}

		$this->dt->filter('sender', function () use ( $searchBySender ) {
			if ($searchBySender == '0') {
				return "sender LIKE '%%'";
			}
			return "sender = '" . $searchBySender . "'";
		});

		exit(wp_kses_post($this->dt->generate()));
	}

	public function loadDocAduaneraPage( $from, $to ) {

		$customerAddress = $this->getMetaKeyValue('_shipping_address_index');
		$customerCountry = $this->getMetaKeyValue('_shipping_country');

		$fromDate = gmdate($from);
		$toDate = gmdate($to);

		if (wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
			$this->dt->query('SELECT DISTINCT
                NULL as c0,
                wco.id as id_order,
                wco.id As reference, ' .
				'cos.exp_number as first_shipping_number,
				cop.company as company, ' .
				$customerAddress . ' as customer_name, ' .
				$customerAddress . ' as customer_address,
				wcoa.country as customer_country,
				coo.date_add as date_add, ' .
				$this->getShippingMethodName() .
				'coo.require_customs_doc as custom_doc,
				coo.id_sender as saved_sender
                FROM ' . $this->prefix . 'wc_orders wco ' .
				CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders coo ON (wco.id = coo.id_order)' .
				CO_LEFT_JOIN . $this->prefix . 'correos_oficial_products cop ON (coo.id_product = cop.id)' .
				CO_JOIN . $this->prefix . 'correos_oficial_saved_orders cos ON (coo.shipping_number = cos.exp_number) ' .
				CO_LEFT_JOIN . $this->prefix . 'wc_order_operational_data wcod On wcod.order_id = wco.id ' .
				CO_LEFT_JOIN . $this->prefix . "wc_order_addresses wcoa ON wcoa.order_id = wco.id
				WHERE coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
				GROUP BY wco.id");
		} else {
			$this->dt->query('SELECT DISTINCT
                NULL as c0,
                wp.ID as id_order,
				wp.ID AS reference, ' .
				'cos.exp_number as first_shipping_number,
				cop.company as company, ' .
				$customerAddress . ' as customer_name, ' .
				$customerAddress . ' as customer_address, ' .
				$customerCountry . ' as customer_country,
				coo.date_add as date_add, ' .
				$this->getShippingMethodName() .
				'coo.require_customs_doc as custom_doc,
				coo.id_sender as saved_sender
                FROM ' . $this->prefix . 'posts wp ' .
				CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders coo ON (wp.ID = coo.id_order)' .
				CO_LEFT_JOIN . $this->prefix . 'correos_oficial_products cop ON (coo.id_product = cop.id)' .
				CO_JOIN . $this->prefix . "correos_oficial_saved_orders cos ON (coo.shipping_number = cos.exp_number)
				WHERE coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
				GROUP BY wp.ID");
		}

		$this->loadColumnContent('customer_name');

		$this->dt->filter('custom_doc', function () {
			return $this->greaterThan(1);
		});

		exit(wp_kses_post($this->dt->generate()));
	}

	private function pickupPagequery( $customerAddress, $customerFirstName, $fromDate, $toDate ) {
		$this->dt->query('SELECT
		q.c0,
        q.id_order,
        q.reference,
		q.first_shipping_number,
		q.company,
		q.customer_name,
        q.customer_address,
		q.date_add,
        q.bultos,
        q.pickup_number,
        q.pickup_return,
        q.pickup,
        q.pickup_date,
        q.pickup_from_hour,
        q.pickup_to_hour,
		q.products,
		q.saved_sender,
            CASE WHEN cosr.pickup_number IS NULL THEN coo.package_size ELSE cosr.package_size END AS package_size,
            CASE WHEN cosr.pickup_number IS NULL THEN coo.print_label ELSE cosr.print_label END AS print_label
            FROM
                (SELECT
                    Null AS c0,
                    wp.ID AS id_order,
					wp.ID AS reference, ' .
					$this->getProducts() .
					$customerAddress . ' as customer_address,' .
					$customerFirstName . " as customer_name,
                    coo.date_add AS date_add,
                    coo.bultos AS bultos,
                    coo.pickup_number AS pickup_number,
                    coo.pickup AS pickup,
                    cos.exp_number AS first_shipping_number,
                    '' AS pickup_return,
                    cop.company AS company,
                    cosr.pickup_date AS pickup_date,
                    cosr.pickup_from_hour AS pickup_from_hour,
                    cosr.pickup_to_hour AS pickup_to_hour,
					coo.id_sender AS saved_sender
            FROM
                " . $this->prefix . 'posts AS wp
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders AS coo ON coo.id_order = wp.ID
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_saved_orders AS cos ON cos.exp_number = coo.shipping_number
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_products AS cop ON cop.id = coo.id_product
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_pickups_returns AS cosr ON cosr.id_order = coo.id_order
            UNION ALL
                SELECT
                    Null AS c0,
                    wp.ID AS id_order,
					wp.ID AS reference, ' .
					$this->getProducts() .
					$customerAddress . ' as customer_address,' .
					$customerFirstName . " as customer_name,
                    coo.date_add AS date_add,
                    coo.bultos AS bultos,
                    '' AS pickup_number,
                    coo.pickup AS pickup,
                    cos.exp_number AS first_shipping_number,
                    cosr.pickup_number AS pickup_return,
                    cop.company AS company,
                    cosr.pickup_date AS pickup_date,
                    cosr.pickup_from_hour AS pickup_from_hour,
                    cosr.pickup_to_hour AS pickup_to_hour,
					coo.id_sender AS saved_sender
            FROM
                " . $this->prefix . 'posts AS wp
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders AS coo ON coo.id_order = wp.ID
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_saved_orders AS cos ON cos.exp_number = coo.shipping_number
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_products AS cop ON cop.id = coo.id_product
                ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_pickups_returns AS cosr ON cosr.id_order = coo.id_order
            WHERE
                cosr.pickup_number IS NOT NULL) AS q
            LEFT JOIN ' . $this->prefix . 'correos_oficial_orders AS coo ON coo.id_order = q.id_order
            LEFT JOIN ' . $this->prefix . "correos_oficial_pickups_returns AS cosr ON cosr.id_order = q.id_order
			WHERE coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
			GROUP BY
			q.c0,
			q.id_order,
			q.reference,
			q.first_shipping_number,
			q.company,
			q.customer_name,
			q.customer_address,
			q.date_add,
			q.bultos,
			package_size,
			print_label,
			q.pickup_number,
			q.pickup_return,
			q.pickup,
			q.pickup_date,
			q.pickup_from_hour,
			q.pickup_to_hour,
			q.products,
			q.saved_sender
        ");
	}

	private function pickupPageQueryHPOS( $customerAddress, $fromDate, $toDate ) {

		$this->dt->query('SELECT
		q.c0,
        q.id_order,
        q.reference,
		q.first_shipping_number,
		q.company,
		q.customer_name,
        q.customer_address,
		q.date_add,
        q.bultos,
        q.pickup_number,
        q.pickup_return,
        q.pickup,
        q.pickup_date,
        q.pickup_from_hour,
        q.pickup_to_hour,
		q.products,
		q.saved_sender,
        CASE WHEN cosr.pickup_number IS NULL THEN coo.package_size ELSE cosr.package_size END AS package_size,
        CASE WHEN cosr.pickup_number IS NULL THEN coo.print_label ELSE cosr.print_label END AS print_label
        FROM
            (SELECT
                Null AS c0,
                wco.ID AS id_order,
                wco.ID As reference,' .
				$this->getProducts() .
				$customerAddress . ' as customer_address,' .
				$customerAddress . " as customer_name,
                coo.date_add AS date_add,
                coo.bultos AS bultos,
                coo.pickup_number AS pickup_number,
                coo.pickup AS pickup,
                cos.exp_number AS first_shipping_number,
                '' AS pickup_return,
                cop.company AS company,
                cosr.pickup_date AS pickup_date,
                cosr.pickup_from_hour AS pickup_from_hour,
                cosr.pickup_to_hour AS pickup_to_hour,
				coo.id_sender AS saved_sender
        FROM
            " . $this->prefix . 'wc_orders AS wco
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders AS coo ON coo.id_order = wco.ID
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_saved_orders AS cos ON cos.exp_number = coo.shipping_number
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_products AS cop ON cop.id = coo.id_product
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_pickups_returns AS cosr ON cosr.id_order = coo.id_order
            ' . CO_LEFT_JOIN . $this->prefix . 'wc_order_operational_data wcod On wcod.order_id = wco.ID
        UNION ALL
            SELECT
                Null AS c0,
                wco.ID AS id_order,
                wco.ID As reference,' .
				$this->getProducts() .
				$customerAddress . ' as customer_address,' .
				$customerAddress . " as customer_name,
                coo.date_add AS date_add,
                coo.bultos AS bultos,
                '' AS pickup_number,
                coo.pickup AS pickup,
                cos.exp_number AS first_shipping_number,
                cosr.pickup_number AS pickup_return,
                cop.company AS company,
                cosr.pickup_date AS pickup_date,
                cosr.pickup_from_hour AS pickup_from_hour,
                cosr.pickup_to_hour AS pickup_to_hour,
				coo.id_sender AS saved_sender
        FROM
            " . $this->prefix . 'wc_orders AS wco
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_orders AS coo ON coo.id_order = wco.ID
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_saved_orders AS cos ON cos.exp_number = coo.shipping_number
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_products AS cop ON cop.id = coo.id_product
            ' . CO_LEFT_JOIN . $this->prefix . 'correos_oficial_pickups_returns AS cosr ON cosr.id_order = coo.id_order
            ' . CO_LEFT_JOIN . $this->prefix . 'wc_order_operational_data wcod On wcod.order_id = wco.ID
        WHERE
            cosr.pickup_number IS NOT NULL) AS q
        LEFT JOIN ' . $this->prefix . 'correos_oficial_orders AS coo ON coo.id_order = q.id_order
        LEFT JOIN ' . $this->prefix . "correos_oficial_pickups_returns AS cosr ON cosr.id_order = q.id_order
		WHERE coo.date_add BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'
        GROUP BY
		q.c0,
        q.id_order,
        q.reference,
		q.first_shipping_number,
		q.company,
		q.customer_name,
        q.customer_address,
		q.date_add,
        q.bultos,
		package_size,
		print_label,
        q.pickup_number,
        q.pickup_return,
        q.pickup,
        q.pickup_date,
        q.pickup_from_hour,
        q.pickup_to_hour,
		q.products,
		q.saved_sender
    ");
	}

	private function getSavedOrderCarrier( $shippingNumber ) {
		return "SELECT cop.name as 'product_name', cop.company as company FROM " . $this->prefix . 'correos_oficial_orders coo
        LEFT JOIN ' . $this->prefix . "correos_oficial_products cop ON (cop.id = coo.id_product) WHERE coo.shipping_number='" . $shippingNumber . "'";
	}

	private function getMetaKeyValue( $meta_key ) {
		return wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
			? '(SELECT wcom.meta_value  FROM ' . $this->prefix . "wc_orders_meta wcom WHERE wcom.order_id=wco.id AND meta_key='" . $meta_key . "'  LIMIT 1)"
			: '(SELECT wpm.meta_value  FROM ' . $this->prefix . "postmeta wpm WHERE wpm.post_id=wp.ID AND meta_key='" . $meta_key . "' LIMIT 1)" ;
	}

	private function getProducts() {
		return wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
			? "(SELECT GROUP_CONCAT(DISTINCT woi.order_item_name SEPARATOR ', ') FROM " . $this->prefix . "woocommerce_order_items woi WHERE woi.order_id = wco.id AND woi.order_item_type = 'line_item' LIMIT 1) AS products,"
			: "(SELECT GROUP_CONCAT(DISTINCT woi.order_item_name SEPARATOR ', ') FROM " . $this->prefix . "woocommerce_order_items woi WHERE woi.order_id = wp.ID AND woi.order_item_type = 'line_item' LIMIT 1) AS products,";
	}

	private function getShippingMethodName() {
		return wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
			? 'IFNULL(cop.name, (SELECT wcoi.order_item_name FROM ' . $this->prefix . "woocommerce_order_items wcoi WHERE wcoi.order_id = wco.id AND wcoi.order_item_type LIKE 'shipping' LIMIT 1)) AS name," :
			'IFNULL(cop.name, (SELECT wcoi.order_item_name FROM ' . $this->prefix . "woocommerce_order_items wcoi WHERE wcoi.order_id = wp.ID AND wcoi.order_item_type LIKE 'shipping' LIMIT 1)) AS name,";
	}
}
