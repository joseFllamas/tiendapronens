<?php
namespace CorreosOficial\Classes\Apis;

use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Classes\CorreosOficialCrypto;
use Exception;
/* LEGACY de CorreosRest de la librería - se usa para el histórico de correos
* anteriormente, esta llamada se hacía por REST y no por SOAP como el resto
*/
// TODOCRV: Mirar si existe otra alternativa para no hacer un require once, o hacerlo funcions


class CorreosOficialRest {

	private static $environment = 'PRO';

	/**
	 * In-memory token cache keyed by md5(clientId).
	 * Avoids calling session_start() which conflicts with WP's request lifecycle.
	 */
	private static $tokenCache = [];

	private $urlApi;
	private $urlGetToken;
	private $urlTrackingApi;

	// Constructor
	public function __construct() {
		// URLS PRE por defecto
		$this->urlGetToken    = 'https://apioauthcid.correospre.es/Api/Authorize/Token';
		$this->urlApi         = 'https://api1.correospre.es/logistics/tradeinout/api/v1';
		$this->urlTrackingApi = 'https://api1.correospre.es/support/track/api/v1';

		// URLS PRO
		if (self::$environment == 'PRO') {
			
			$this->urlApi         = 'https://api1.correos.es/logistics/tradeinout/api/v1';
			$this->urlGetToken    = 'https://apioauthcid.correos.es/Api/Authorize/token';
			$this->urlTrackingApi = 'https://api1.correos.es/support/track/api/v1';
		}
	}

	/* *********************************************************************************************************
	 * OBTENER TOKEN
	 ********************************************************************************************************* */

	/**
	 * Obtains a token from Correos API.
	 *
	 * @param string $clientId The client ID.
	 * @param string $clientSecret The client secret.
	 * @return string|false The token if successful, false otherwise.
	 */
	public function getCorreosToken( $clientId, $clientSecret ) {

		$return    = false;
		$cacheKey  = md5((string) $clientId);

		// Check in-memory cache first (avoids session_start() conflicts with WP's request lifecycle)
		if (isset(self::$tokenCache[$cacheKey]) && !$this->isJwtExpired(self::$tokenCache[$cacheKey])) {
			return self::$tokenCache[$cacheKey];
		}

		// Read from session only if already active — never call session_start()
		$token = '';
		if (session_status() === PHP_SESSION_ACTIVE) {
			$token = isset($_SESSION['tokenP3']) ? sanitize_text_field($_SESSION['tokenP3']) : '';
		}

		// Checks Token
		if ( $token && !$this->isJwtExpired($token)) {
			$return = $token;
		} else {
			try {

				$ch = curl_init();
				curl_setopt_array($ch, array(
					CURLOPT_URL => $this->urlGetToken,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 15,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_POSTFIELDS => http_build_query(array(
						'grant_type'    => 'client_credentials',
						'client_id'     => $clientId,
						'client_secret' => $clientSecret,
						'scope'         => 'AP3 LBS RCG',
					), '', '&', PHP_QUERY_RFC3986),
					CURLOPT_HTTPHEADER => array(
					  'Content-Type: application/x-www-form-urlencoded',
					),
				));
				
				$response = curl_exec($ch);
				$error = curl_error($ch);
				curl_close($ch);
	
				$jsonResponse = json_decode($response, true);

				// KO
				if ($error) {
					$return = false;
				}

				// OK — API may return 'access_token' (OAuth2) or legacy 'idToken'
				$fetchedToken = $jsonResponse['access_token'] ?? $jsonResponse['idToken'] ?? null;
				if ($fetchedToken) {
					self::$tokenCache[$cacheKey] = $fetchedToken;
					// Write to session only if already active — never call session_start() here
					if (session_status() === PHP_SESSION_ACTIVE) {
						$_SESSION['tokenP3'] = $fetchedToken;
					}
					$return = $fetchedToken;
				}
		
			} catch (Exception $e) {
				$return = false;
			}
		}
		return $return;
	}

	/* *********************************************************************************************************
	 * REST CALL
	 ********************************************************************************************************* */

	/**
	 * Makes a REST call to the specified URL with the given data and client.
	 *
	 * @param string $url The URL to make the request to.
	 * @param array $data The data to send in the request.
	 * @param string $client The client making the request.
	 * @return void
	 */
	public function requestRestCall( $url, $data, $client, $method = 'POST' ) {
		$headers = array(
			'client_id: ' . base64_decode('NzMwMjlhODM4ZTQzNDBhOGE3YmQ3NTM0ZGU4NzgxZWQ='),
			'client_secret: ' . base64_decode('NkFBQTQ4NEJlNjZCNGFDZmJCMjZDMDdhRjVFNjEwOTU='),
			'Content-Type: text/plain',
			'Accept: application/json',
		);

		$decryptedSecret = CorreosOficialCrypto::decrypt($client['CorreosSecretID']);
		if ($decryptedSecret === false) {
			return json_encode(['error' => CorreosOficialCrypto::getDecryptErrorMessage()]);
		}

		$token = $this->getCorreosToken($client['CorreosClientID'], $decryptedSecret);

		if ($token) {
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		$json_data = !empty($data) ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL => $this->urlApi . $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
			CURLOPT_POSTFIELDS => $json_data,
			CURLOPT_HTTPHEADER => $headers,
		));
		
		$response = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			return false;
		}

		return $response;
	}

	public function requestRestCallOrderStatus( $url, $username, $password ) {

		$password = str_replace('\\\\', '\\', $password);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept-language: en\r\n',
		));
		
		$result = curl_exec($ch);
		$error = curl_error($ch);
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
		$info = curl_getinfo($ch);
		$codigo_error = curl_errno($ch);
		curl_close($ch);
		return $result;
	}

	/* *********************************************************************************************************
	 * REGISTRAR ENVÍO
	 ********************************************************************************************************* */

	/**
	 * Registers a shipment with the given payload.
	 *
	 * @param array $payload The payload containing shipment details.
	 * @return array The response containing the result of the registration.
	 */
	public function registrarEnvio( $payload, $origin = 'order' ) {
		// Sacamos el índice order_form a una variable para simplificar código
		$orderForm = $payload['order_form'];

		// Flags
		$multibulto = $payload['bultos'] == 1 ? false : true;

		/**
		 * REGLA DE NEGOCIO CP REMITENTE
		 * Si el remitente es de Portugal, solo se deveuelven los 4 primeros dígitos
		 */
		if ($orderForm['sender_country'] == 'PT') {
			$orderForm['sender_cp'] = substr($orderForm['sender_cp'], 0, 4);
		}

		// REGLAS PARA DESTINATARIOS (UNIFICAR AL RESTO DE ENDPOINTS) -----------------------------------------------------
		/**
		 * REGLA DE NEGOCIO CP/ZIP DESTINATARIO
		 * ES y AD usan CP, resto usan ZIP
		 */
		$customerPostal = $this->getPostalCodeFields($orderForm['customer_cp'], $orderForm['customer_country']);
		$customer_postcode = $customerPostal['postcode'];
		$customer_zip = $customerPostal['zip'];

		$senderPostal = $this->getPostalCodeFields($orderForm['sender_cp'], $orderForm['sender_country']);
		$sender_postcode = $senderPostal['postcode'];
		$sender_zip = $senderPostal['zip'];

		/**
		 * REGLA DE NEGOCIO PHOME DESTINATARIO
		 * Más info dentro de CorreosOficialHelpers
		 */
		$phone_mobile_sms = CorreosOficialHelpers::getMobilePhone(
			$orderForm['customer_phone'],
			$orderForm['customer_country'],
			$orderForm['input_select_carrier']
		);

		// DATA - BULTOS
		$packages = array();
		foreach ($payload['info_bulto'] as $index => $bulto) {
		   
			// Datos de Aduanas
			$customsData = array();
			if ($payload['require_customs_doc'] && isset($payload['customs_desc_array'])) {
				foreach ($payload['customs_desc_array'][$index] as $customsDesc) {
					$customDataDesc = array(
						'quantity'       => $customsDesc['unidades'],
						'netValue'       => $customsDesc['valor_neto'],
						'netWeight'      => isset($customsDesc['weight']) ? $customsDesc['weight'] : '0', // En gramos - No aplicar peso por defecto en envíos con aduanas
						'description'    => isset($customsDesc['descripcion_aduanera']) ? $customsDesc['descripcion_aduanera'] : ''
					);

					// Tariff number: soportamos tanto la clave legacy 'numero_tarifario' como 'tariffNumber' (API)
					if (!empty($customsDesc['numero_tarifario'])) {
						$customDataDesc['tariffNumber'] = $customsDesc['numero_tarifario'];
					} elseif (!empty($customsDesc['tariffNumber'])) {
						$customDataDesc['tariffNumber'] = $customsDesc['tariffNumber'];
					}

					// Country of origin: aceptamos 'origin_country' (legacy) o 'countryOrigin' (API)
					if (!empty($customsDesc['origin_country'])) {
						$customDataDesc['countryOrigin'] = self::getCountryCodeByISO($customsDesc['origin_country']);
					} elseif (!empty($customsDesc['countryOrigin'])) {
						$customDataDesc['countryOrigin'] = self::getCountryCodeByISO($customsDesc['countryOrigin']);
					}

					$customsData[] = $customDataDesc;
				}
			}
			
		// Reglas de Pesos
		// Prioridad 1: peso declarado en el formulario de aduanas (netWeight, ya en gramos)
		$customsWeightGrams = 0;
		foreach ($customsData as $cd) {
			$customsWeightGrams += (int)($cd['netWeight'] ?? 0);
		}

		if ($customsWeightGrams > 0) {
			$weightGrams = $customsWeightGrams;
		} else {
			// Prioridad 2: peso del formulario de envío (total_weight en kg → gramos)
			$bultoWeight = 0;
			if ($payload['bultos'] == 1 && $orderForm['all_packages_equal'] == 0) {
				$bultoWeight = $orderForm["total_weight"];
			} elseif ($payload['bultos'] > 1 && $orderForm['all_packages_equal'] == 0) {
				$bultoWeight = $bulto['weight'];
			} elseif ($orderForm['all_packages_equal'] == 1) {
				$bultoWeight = ($orderForm["total_weight"] / $payload['bultos']);
			}
			$weightGrams = (int)($bultoWeight * 1000);

			// Prioridad 3: peso real de los productos del pedido (útil desde Utilidades sin formulario de aduanas)
			if ($weightGrams === 0 && !empty($payload['order_id'])) {
				$wc_order_weight = wc_get_order($payload['order_id']);
				if ($wc_order_weight) {
					$product_weight_grams = 0;
					$wc_weight_unit = get_option('woocommerce_weight_unit', 'kg');
					foreach ($wc_order_weight->get_items() as $item) {
						$product = $item->get_product();
						if ($product && $product->get_weight()) {
							$weight_in_wc_unit = (float)$product->get_weight() * $item->get_quantity();
							$product_weight_grams += (int)wc_get_weight($weight_in_wc_unit, 'g', $wc_weight_unit);
						}
					}
					if ($product_weight_grams > 0) {
						$weightGrams = $product_weight_grams;
					}
				}
			}
		}

		$package = array(
				'packageId'                 => strval($index),
				'packageWeightGrams'        => strval($weightGrams),
					'clientReference'           => $bulto['reference'],
					'clientReference2'          => '', // UIDN Code. Required for XL products.
					'clientReference3'          => 'MODULO_WC_' . get_option('woocommerce_version') . '/' . CORREOS_OFICIAL_VERSION, // PREGUNTAR!!!! UIDN code
					'normalizedWeightIndicator' => 'N', // PREGUNTAR!!!! Indicates if the weight has to be normalized. It has to be informed if the weight is below 20grams . Y (Yes), or N (No). For POAXAC and /delivery/cn endpoint only
					'packageContents' => array(
						'shipmentType'             => '2', // 1 (Documents), 2 (Goods), 3 (Gift), 4 (Commercial samples), 5 (Returned Merchandise), 6 (Other), 7 (Dangerous Goods)
						'customReferenceConsignor' => $orderForm['custom_ref_exp'],
						'importerTaxReference'     => $orderForm['AT_code'], // PREGUNTAR!!!! No estoy seguro
						'importerVatNumber'        => '',
						'importerCode'             => '',
						'instructionsDoNotDeliver' => 'D', // Required for international shipments, Return instructions in case of not being able to deliver for international packages. D (Return to Sender), A (Treat as Abandoned), Default (Return to Sender)
						'customsData'              => $customsData,
						),
			);

			// Observaciones del bulto excepto si el check de observaciones de pedido está activado.
			$labelObservations = (new CorreosOficialConfig('LabelObservations'))->get_value();

			if ($labelObservations == 'on' && !empty($orderForm['observations'])) {
				$package['observations'] = $orderForm['observations'];
			} else {
				$package['observations'] = $bulto['observations'];
			}

			// Dimensiones en mm si están disponibles
			if (!empty($bulto['height'])) {
				$package['packageHeight'] = strval((int) $bulto['height'] * 10);
			}

			if (!empty($bulto['width'])) {
				$package['packageWidth'] = strval((int) $bulto['width'] * 10);
			}

			if (!empty($bulto['large'])) {
				$package['packageLength'] = strval((int) $bulto['large'] * 10);
			}

			$packages[] = $package;
		}

		if ( !empty($payload['added_values']) && isset($payload['added_values']['partial_delivery']) ) {
			$partial_delivery = $payload['added_values']['partial_delivery'] == 'true' ? 'Y' : 'N';
		} else {
			$partial_delivery = 'N';
		}
		
		// Preparar datos del addressee según si hay empresa o no
		$addressee_data = array(
			'address'           => $orderForm['customer_address'],
			'locality'          => $orderForm['customer_city'],
			'cp'                => $customer_postcode,
			'zip'               => $customer_zip,
			'country'           => self::getCountryCodeByISO($orderForm['customer_country']),
			'contactPhone'      => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['customer_phone']),
			'email'             => $orderForm['customer_email'],
			'smsNumber'         => $phone_mobile_sms,
			'language'          => $this->getLanguage($orderForm['customer_country'])
		);

		// Si hay empresa, usar company + contactPerson concatenado
		if (!empty($orderForm['customer_company'])) {
			$addressee_data['company'] = $orderForm['customer_company'];
			$addressee_data['contactPerson'] = trim($orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname']);
		} else {
			// Si no hay empresa, usar name + lastName1
			$addressee_data['name'] = $orderForm['customer_firstname'];
			if (!empty($orderForm['customer_lastname'])) {
				$addressee_data['lastName1'] = $orderForm['customer_lastname'];
			}
		}

		// Preparar datos del remitente según si hay persona contacto o no
		$sender_data = array(
				'address'           => $orderForm['sender_address'],
				'locality'          => $orderForm['sender_city'],
				'cp'                => $sender_postcode,
				'zip'               => $sender_zip,
				'country'           => self::getCountryCodeByISO($orderForm['sender_country']),
				'contactPhone'      => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['sender_phone']),
				'smsNumber'         => '',
				'email'             => $orderForm['sender_email'],
				'language'          => $this->getLanguage($orderForm['sender_country']),
		);

		$labelAlternativeText = CorreosOficialConfig::getLabelAlternativeText();

		if (!$labelAlternativeText && !empty($orderForm['sender_contact'])) {
			$sender_data['company']       = $orderForm['sender_name'];
			$sender_data['contactPerson'] = $orderForm['sender_contact'];
		} else {
			$sender_data['name'] = $orderForm['sender_name'];
		}

		/**
		 * REGLA DE NEGOCIO CP REMITENTE
		 * Si tenemos texto alternativo configurado, lo sustituimos por el nombre del remitente
		 */
		if ($labelAlternativeText) {
			$sender_data['name'] = $labelAlternativeText;
		}

		// DATA - SHIPMENT
		$shipment = array(
			'packagesNumber'     => $payload['bultos'],
			'product'            => $payload['product']['code'],
			'deliveryMethod'     => $payload['product']['mode'],
			'admissionMethod'    => 1, // PONE QUE NO ES REQUIERED PREGUNTAR!!!! 1- UAM / Office / Online, 2- Citypaq, 3- Delivery unit / UAM / Inverse logistic.
			'contractNumber'     => $payload['client']['CorreosContract'],
			'clientNumber'       => $payload['client']['CorreosCustomer'],
			'labellerCode'       => $payload['client']['CorreosKey'],
			'modificationType'   => CorreosOficialConfig::getConfigValue('CorreosModify') ?? '1',
			'shipmentNotes'      => '',
			'partialDelivery'    => $partial_delivery,
			'packages'           => $packages,
			'addressee'          => $addressee_data,
			'sender'             => $sender_data,	
			'additionalValues' => array(),
		);

		// PEDIDOS CON OFICINA/CITYPAQ
		if ($payload['delivery_mode'] == 'citypaq') {
			$shipment['addressee']['homepaqCode'] = $orderForm['cod_homepaq'];
		} elseif ($payload['delivery_mode'] == 'office') {
			$shipment['addressee']['chosenOffice'] = $orderForm['cod_office'];
		}

		// Seguro
		if (!empty($payload['added_values']) && isset($payload['added_values']['insurance']) && $payload['added_values']['insurance'] == "true") {
			$shipment['additionalValues'][] = [
				"additionalValueId" => $this->getAVCode($payload['delivery_mode'], $payload['product']['code']),
				"fields" => [
					[ "fieldId" => "1", "value" => (string) ($payload['added_values']['insurance_value'] * 100) ],
				]
			];
		}

		// Contra reembolso
		if (!empty($payload['added_values']) && $payload['added_values']['cash_on_delivery'] == "true") {

			if (
				empty($payload['added_values']['cash_on_delivery_iban']) || 
				substr($payload['added_values']['cash_on_delivery_iban'], 0, 4) == '****'
				) {
				$bank_acc_number = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
				$decryptedIban = CorreosOficialCrypto::decrypt($bank_acc_number);
				$payload['added_values']['cash_on_delivery_iban'] = $decryptedIban !== false ? $decryptedIban : '';
			}
			
			$shipment['additionalValues'][] = [
				"additionalValueId" => "REICPA",
				"fields" => [
					[ "fieldId" => "1", "value" => (string) ($payload['added_values']['cash_on_delivery_value'] * 100) ],
					[ "fieldId" => "3", "value" => $payload['added_values']['cash_on_delivery_iban'] ],
					[ "fieldId" => "4", "value" => "Y" ]
				]
			];
		}
	
		// NIF/CIF CUSTOMER
		if (!empty($orderForm['customer_dni'])) {
			$shipment['addressee']['doiType']   = strval(CorreosOficialHelpers::getDocumentType($orderForm['customer_dni'])); // 0 (European ID), 1 (DNI/NIF), 3 (NIE), 10 (CIF)
			$shipment['addressee']['doiNumber'] = $orderForm['customer_dni'];
		}

		// NIF/CIF SENDER
		if (!empty($orderForm['sender_nif_cif'])) {
			$shipment['sender']['doiType']   = strval(CorreosOficialHelpers::getDocumentType($orderForm['sender_nif_cif'])); // 0 (European ID), 1 (DNI/NIF), 3 (NIE), 10 (CIF)
			$shipment['sender']['doiNumber'] = $orderForm['sender_nif_cif'];
		}

		// Se añade pedido a la lista de envíos
		$shipments[] = $shipment;

		// DATA
		$data = array(
			'errorCodeLanguage' => $this->getLanguage($orderForm['sender_country']),
			'shipments' => $shipments,
		);
		$deliveryCall = $this->requestRestCall('/preregister/delivery', $data, $payload['client']);
		$deliveryResponse = json_decode($deliveryCall, true);

		// KO - Sin respuesta de la API (fallo de red, timeout, respuesta no-JSON)
		if (is_null($deliveryResponse)) {
			return array(
				array(
					'codigoRetorno'  => 1,
					'mensajeRetorno' => __('Could not connect to Correos API. Please check your credentials and try again.', 'correosoficial'),
					'orderId'        => $payload['order_id'],
					'reference'      => $payload['info_bulto'][1]['reference'],
				)
			);
		}

		// KO JWT No valido 
		if (isset($deliveryResponse['error'])) {
			return array(
				array(
					'codigoRetorno'  => 500,
					'mensajeRetorno' => $deliveryResponse['error'],
					'orderId'        => $payload['order_id'],
					'reference'      => $payload['info_bulto'][1]['reference']
				)
			);
		}

		// KO - PRE-Registro
		if ($deliveryResponse['shipments'][0]['validationErrorCount']) {
			foreach ($deliveryResponse['shipments'][0]['error'] as $error) {
				$result[] = array(
					'codigoRetorno'  => $error['errorCode'],
					'mensajeRetorno' => $error['description'],
					'orderId'        => $payload['order_id'],
					'reference'      => $payload['info_bulto'][1]['reference'],
				);
			}
			return $result;
		}

		if (isset($deliveryResponse['code']) && $deliveryResponse['code'] == 400) {
			return array(
				array(
				'codigoRetorno'  => $deliveryResponse['code'],
				'mensajeRetorno' => $deliveryResponse['message'] . ': ' . (isset($deliveryResponse['moreInformation']['description']) ? $deliveryResponse['moreInformation']['description'] : ''),
				'orderId'        => $payload['order_id'],
				)
			);
		}

		// OK - PRE-Registro

		// Bultos
		$bultos = array();
		foreach ($deliveryResponse['shipments'][0]['packages'] as $key => $bulto) {
			$bultos[] = array(
				'numBulto'        => $key + 1,
				'shipping_number' => $bulto['packageCode'],
			);
			$payload['shipping_numbers'][] = $bulto['packageCode']; // Para recogidas
		}

		$exp_number = '';
		
		// Si el pedido es monobulto (solo tiene packageCode), sino utilizamos el shipmentCode
		if (!empty($deliveryResponse['shipments'][0]['packages']) && count($deliveryResponse['shipments'][0]['packages']) && empty($deliveryResponse['shipments'][0]['shipmentCode'])) {
			$exp_number = $deliveryResponse['shipments'][0]['packages'][0]['packageCode'];
		} else {
			$exp_number = $deliveryResponse['shipments'][0]['shipmentCode'];
		}

		// RECOGIDAS ------------------------------------------------------------------------------------------ //
		if ($payload['needPickup'] === 'S') {
			$responsePickup = $this->registrarRecogida($payload);
		}

		// RETURN ESTANDARIZADO
		return array(
			'codigoRetorno'  => $deliveryResponse['shipments'][0]['validationErrorCount'],
			'mensajeRetorno' => '',
			'exp_number'     => $exp_number,
			'bultos'         => $bultos,
			'pickup'         => isset($responsePickup) ? $responsePickup : array(),
		);
	}

	/* *********************************************************************************************************
	 * CANCELAR ENVIO
	 ********************************************************************************************************* */
	/**
	* Cancels a shipment with the given payload.
	*
	* @param array $payload The payload containing shipment details.
	* @return array The response containing the result of the cancellation.
	*/
	public function cancelarEnvio( $payload ) {
		// DATA
		$data = array(
			'errorCodeLanguage' => $this->getLanguage($payload['lang']),
			'packageCode' => $payload['bulto']->get_shipping_number(),
		);

		// Validación del cuerpo de la petición
		$deliveryAnnulmentCall = $this->requestRestCall('/preregister/delivery/annulment', $data, $payload['client']);
		$deliveryAnnulmentResponse = json_decode($deliveryAnnulmentCall, true);

		// KO JWT No valido 
		if (isset($deliveryAnnulmentResponse['error'])) {
			return array(
				array(
					'codigoRetorno'  => 500,
					'mensajeRetorno' => $deliveryAnnulmentResponse['error'],
					'orderId'        => $payload['order_id']
				)
			);
		}
		
		// Problema de conexión, credenciales, o cualquiera no controlado
		if (is_null($deliveryAnnulmentResponse) || isset($deliveryAnnulmentResponse['code']) && $deliveryAnnulmentResponse['code'] == 500) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('Error cancelling shipment, please try again later', 'correosoficial'),
				'orderId'        => $payload['order_id'],
			);
		}

		// KO - Cancelar Evio
		if (!isset($deliveryAnnulmentResponse['errors']) || count($deliveryAnnulmentResponse['errors'])) {
			$errorsDescription = '';
			foreach ($deliveryAnnulmentResponse['errors'] as $error) {
				$errorsDescription .= $error['description'] . ' ';
			}
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => isset($errorsDescription) ? $errorsDescription : $deliveryAnnulmentResponse['message'],
			);
		}

		// OK - Cancelar Envio
		// RETURN ESTANDARIZADO
		return array(
			'codigoRetorno'  => 0,
			'mensajeRetorno' => '',
		);
	}

	/**
	* Cancels a shipment with the given payload by Expedition Number.
	*
	* NOTA: En las primeras pruebas parece que solo vale para Correos XL
	*
	* @param array $payload The payload containing shipment details.
	* @return array The response containing the result of the cancellation.
	*/
	public function cancelarEnviobyExpNum( $payload ) {
		// DATA
		$data = array(
			'errorCodeLanguage' => $this->getLanguage($payload['lang']),
			'shipment' => $payload['expedition_number'],
		);

		// Validación del cuerpo de la petición
		$deliveryAnnulmentExpeditionCall = $this->requestRestCall('/preregister/delivery/annulment/expedition', $data, $payload['client']);
		$deliveryAnnulmentExpeditionResponse = json_decode($deliveryAnnulmentExpeditionCall, true);

		// Problema de conexión, credenciales, o cualquiera no controlado
		if (is_null($deliveryAnnulmentExpeditionResponse)) {
			return array(
				array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('Error generating shipment, please try again later', 'correosoficial'),
				'orderId'        => $payload['order_id'],
				)
			);
		}

		// KO - Cancelar Evio
		if (count($deliveryAnnulmentExpeditionResponse['errors'])) {
			foreach ($deliveryAnnulmentExpeditionResponse['errors'] as $error) {
				$result[] = array(
					'codigoRetorno' => $error['errorCode'],
					'mensajeRetorno' => $error['description'],
				);
			}
			return $result;
		}

		// RETURN ESTANDARIZADO
		return array(
			array(
				'codigoRetorno'  => 0,
				'mensajeRetorno' => '',
			),
		);
	}

	/* *********************************************************************************************************
	 * REGISTRAR RECOGIDA
	 ********************************************************************************************************* */
	public function registrarRecogida( $payload ) {

		// Cálculo peso recogida
		$collection_weight = array(
			'10' => 0.5,
			'20' => 2,
			'30' => 5,
			'40' => 30,
			'50' => 100,
			'60' => 100,
		);

		$numpeso = array_key_exists($payload['packetSize'], $collection_weight) ?
		CorreosOficialHelpers::getFloatValue($collection_weight[$payload['packetSize']]) * 1000 : 1000;

		$shipments = array();
		if (count($payload['shipping_numbers'])) {
			foreach ($payload['shipping_numbers'] as $shipping_number) {
				$shipments[] = [
					'codShipmentCorreos' => $shipping_number,
					'shipmentType' => 'Paqueteria'
				];
			}
		}

		$timeSlot = $this->getTimeSlot($payload['pickupFromRegister']);

		$data[] = array(
			'address'                 => $payload['order_form']['sender_address'],
			'addressObserv'           => '',
			'codAnnex'                => '091',
			'clientObservations'      => $payload['pickupFromRegister'] . ' ' . $payload['pickupToRegister'],
			'codContract'             => $payload['client']['CorreosContract'],
			'codSpecificContract'     => $payload['client']['CorreosCustomer'],
			'estimatedShipments'      => (int) $payload['bultos'],
			'estimatedVolume'         => (int) $payload['packetSize'],
			'time'                    => $payload['pickupFromRegister'],
			'locality'                => $payload['order_form']['sender_city'],
			'originSystem'            => 'WOO',
			'postalCode'              => $payload['order_form']['sender_cp'],
			'printLabel'              => $payload['needPrintLablPickup'] == 'S' ? true : false,
			'requestDate'             => $payload['pickupDateRegister'],
			'shipments'               => $shipments,
			'province'                => $payload['pickup_address_data']['province'],
			'timeSlot'                => strval($timeSlot['slot']), // 1 -> 9:00 to 12:00, 2 -> 12:00 to 15:00, 3 -> 15:00 to 18:00, 4 -> 18:00 to 21:00.
			'contactName'             => $payload['pickup_address_data']['contactName'],
			'lastNameContact'         => $payload['pickup_address_data']['lastNameContact'],
			'email'                   => !empty($payload['order_form']['sender_email']) ? $payload['order_form']['sender_email'] : '',
			'contactPhone'            => !empty($payload['order_form']['sender_phone']) ? CorreosOficialHelpers::cleanTelephoneNumber($payload['order_form']['sender_phone']) : '',
			'coordinate'              => false,
			'type'                    => 'E', // sporadic (E) or fixed (F)
		);
		//return;
		if ($payload['product']['product_type'] != 'office' || $payload['product'] != 'citypaq') {
			$data[0]['modalityType'] = 'S';
		}

		// Solicitamos Recogida
		$requestsCall = $this->requestRestCall('/requests', $data, $payload['client']);
		$requestsCallResponse = json_decode($requestsCall, true);

		// Error en la petición 500 (Detectado en pre, no tendría que pasar si funciona la validación REVISAR!)
		if (isset($requestsCallResponse['code']) && ($requestsCallResponse['code'] == 500 || $requestsCallResponse['code'] == 400)) {

			if (isset($requestsCallResponse['moreInformation']['description'])) {
				return array(
					'codigoRetorno'  => 1,
					'mensajeRetorno' => $requestsCallResponse['code'] . ' ' . $requestsCallResponse['moreInformation']['description'],
				);
			}

			return array(
				'mensajeRetorno' => $requestsCallResponse['code'] . ' ' . $requestsCallResponse['message'],
			);
		}

		return array(
			'codigoRetorno'  => 0,
			'mensajeRetorno' => 'RECOGIDA ASIGNADA',
			'codRecogida'    => $requestsCallResponse[0]['codRequest'], // Aqui vendrá código recogida
			'dateRegister'   => $requestsCallResponse[0]['requestDate'], // Comprobar si en el cuerpo viene fecha,
			'fromRegister'   => $timeSlot['from'],
			'toRegister'     => $timeSlot['to'],
		);
	}

	public function cancelarRecogida( $payload ) {

		$id = !empty($payload['pickup_number']) ? $payload['pickup_number'] : $payload['pickup_number_return'];

		$data = array(
			'id' => $id, 
		);

		$requestsCall = $this->requestRestCall('/requests/cancel/' . urlencode($id), $data, $payload['client'], 'PATCH');
		$requestsCallResponse = json_decode($requestsCall, true);

		// Problema de conexión, credenciales, o cualquiera no controlado
		if (is_null($requestsCallResponse)) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('Error generating shipment, please try again later', 'correosoficial'),
				'orderId'        => $payload['order_id'],
			);
		}

		if ($requestsCallResponse) {
			if (isset($requestCallResponse['error'])) {
				return array(
					'codigoRetorno'  => 1,
					'mensajeRetorno' => $requestsCallResponse['error'],
				);
			} else {
				// RETURN ESTANDARIZADO
				return array(
					'codigoRetorno'  => 0,
					'mensajeRetorno' => $requestsCallResponse['state'],
					'codRecogida'    => $requestsCallResponse['codRequest'],
				);
			}
		}

		return array(
			'codigoRetorno'  => 1,
			'mensajeRetorno' => 'No se ha podido cancelar la recogida, inténtalo más tarde',
		);
	}

	/* *********************************************************************************************************
	* SEGUIMIENTO RECOGIDA
	********************************************************************************************************* */

	public function seguimientoRecogida( $client_data, $id ) {

		$seguimientoRecogidaCall = $this->requestRestCall('/requests/' . urlencode($id), '', $client_data, 'GET');
		$seguimientoRecogidaResponse = json_decode($seguimientoRecogidaCall, true);

		if ($seguimientoRecogidaResponse == null) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => 'No es posible localizar la recogida',
			);
		} else {
			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'  => 0,
				'mensajeRetorno' => $seguimientoRecogidaResponse,
				'codRecogida'    => $seguimientoRecogidaResponse['codRequest'],
			);
		}
	}

	/* *********************************************************************************************************
	* GENERAR DEVOLUCION
	********************************************************************************************************* */
	public function generateReturn( $payload ) {
		// Sacamos el índice order_form a una variable para simplificar código
		$orderForm = $payload['order_form'];

		// Flags
		$multibulto = $payload['bultos'] == 1 ? false : true;

		// REGLAS PARA DESTINATARIOS (UNIFICAR AL RESTO DE ENDPOINTS) -----------------------------------------------------
		/**
		 * REGLA DE NEGOCIO CP/ZIP DESTINATARIO
		 * ES y AD usan CP, resto usan ZIP
		 */

		// OJO REGLAS INVERSAS PARA REMITENTE Y DESTINATARIO (Es decir el remitente es el destinatario de la devolución y el destinatario es el remitente de la devolución)
		$customerPostal = $this->getPostalCodeFields($orderForm['customer_cp'], $orderForm['customer_country']);
		$customer_postcode = $customerPostal['postcode'];
		$customer_zip = $customerPostal['zip'];

		$senderPostal = $this->getPostalCodeFields($orderForm['sender_cp'], $orderForm['sender_country']);
		$sender_postcode = $senderPostal['postcode'];
		$sender_zip = $senderPostal['zip'];

		/**
		 * REGLA DE NEGOCIO PHOME DESTINATARIO
		 * Más info dentro de CorreosOficialHelpers
		 */
		$phone_mobile_sms = CorreosOficialHelpers::getMobilePhone(
			$orderForm['sender_phone'],
			$orderForm['sender_country'],
			$orderForm['input_select_carrier_return']
		);

		// DATA - BULTOS
		$packages = [];

		foreach ($payload['info_bulto'] as $index => $bulto) {

			if ($payload['require_customs_doc'] && isset($payload['customs_desc_array'])) {
				// Datos de Aduanas
				$customsData = [];
				if (!empty($payload['customs_desc_array'][$index])) {
					foreach ($payload['customs_desc_array'][$index] as $customsDesc) {
						$customDataDesc = array(
							'quantity'       => $customsDesc['unidades'],
							'netValue'       => $customsDesc['valor_neto'],
							'netWeight'      => $customsDesc['weight'],
							'description'    => isset($customsDesc['descripcion_aduanera']) ? $customsDesc['descripcion_aduanera'] : ''
						);

						// Tariff number: soportamos tanto la clave legacy 'numero_tarifario' como 'tariffNumber' (API)
						if (!empty($customsDesc['numero_tarifario'])) {
							$customDataDesc['tariffNumber'] = $customsDesc['numero_tarifario'];
						} elseif (!empty($customsDesc['tariffNumber'])) {
							$customDataDesc['tariffNumber'] = $customsDesc['tariffNumber'];
						}

						// Country of origin: aceptamos 'origin_country' (legacy) o 'countryOrigin' (API)
						if (!empty($customsDesc['origin_country'])) {
							$customDataDesc['countryOrigin'] = self::getCountryCodeByISO($customsDesc['origin_country']);
						} elseif (!empty($customsDesc['countryOrigin'])) {
							$customDataDesc['countryOrigin'] = self::getCountryCodeByISO($customsDesc['countryOrigin']);
						}

						$customsData[] = $customDataDesc;
					}
				}
			}

			// Reglas de Pesos
			if($payload['bultos'] == 1 && $orderForm['all_packages_equal'] == 0){
				$bultoWeight = $orderForm["total_weight"];
			}elseif ($payload['bultos'] > 1 && $orderForm['all_packages_equal'] == 0){
				$bultoWeight =  $bulto['weight'];
			}elseif ($orderForm['all_packages_equal'] == 1){
				$bultoWeight =  ( $orderForm["total_weight"] / $payload['bultos'] );
			}

			$package = [
				'packageId'                 => strval($index),
				'packageWeightGrams'        => strval((int) ($bultoWeight * 1000)),
				'clientReference'           => $bulto['reference'],
				'clientReference2'          => '',
				//'clientReference3'          => 'MODULO_WC_' . get_option('woocommerce_version') . '/' . CORREOS_OFICIAL_VERSION,
				'observations'              => isset($bulto['observations']) ? $bulto['observations'] : '',
				'normalizedWeightIndicator' => 'N',
				'packageContents' => [
					'shipmentType'             => '2',
					'customReferenceConsignor' => $orderForm['custom_ref_exp'],
					'importerTaxReference'     => isset($orderForm['AT_code']) ? $orderForm['AT_code'] : ( $orderForm['code_at'] ?? '' ),
					'importerVatNumber'        => '',
					'importerCode'             => '',
					'instructionsDoNotDeliver' => 'D',
				],
			];

			if ( !empty($customsData) ) {
				$package['packageContents']['customsData'] = $customsData;
			}

			// Dimensiones en mm si están disponibles
			if (!empty($bulto['height'])) {
				$package['packageHeight'] = strval((int) $bulto['height'] * 10);
			}

			if (!empty($bulto['width'])) {
				$package['packageWidth'] = strval((int) $bulto['width'] * 10);
			}

			if (!empty($bulto['large'])) {
				$package['packageLength'] = strval((int) $bulto['large'] * 10);
			}

			$packages[] = $package;
		}

		$addressee_data = array(
			'address'           => $orderForm['sender_address'],
			'locality'          => $orderForm['sender_city'],
			'cp'                => $sender_postcode,
			'zip'               => $sender_zip,
			'country'           => self::getCountryCodeByISO($orderForm['sender_country']),
			'contactPhone'      => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['sender_phone']),
			'email'             => $orderForm['sender_email'],
			'smsNumber'         => '',
			'language'          => $this->getLanguage($orderForm['sender_country'])
		);

		$labelAlternativeText = CorreosOficialConfig::getLabelAlternativeText();

		if (!$labelAlternativeText && !empty($orderForm['sender_contact'])) {
			$addressee_data['company']       = $orderForm['sender_name'];
			$addressee_data['contactPerson'] = $orderForm['sender_contact'];
		} else {
			$addressee_data['name'] = $orderForm['sender_name'];
		}

		/**
		 * REGLA DE NEGOCIO CP REMITENTE
		 * Si tenemos texto alternativo configurado, lo sustituimos por el nombre del remitente
		 */
		if ($labelAlternativeText) {
			$addressee_data['name'] = $labelAlternativeText;
		}

		// Preparar datos del remitente según si hay persona contacto o no
		$sender_data = array(
				'address'           => $orderForm['customer_address'],
				'locality'          => $orderForm['customer_city'],
				'cp'                => $customer_postcode,
				'zip'               => $customer_zip,
				'country'           => self::getCountryCodeByISO($orderForm['customer_country']),
				'contactPhone'      => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['customer_phone']),
				'smsNumber'         => $phone_mobile_sms,
				'email'             => $orderForm['customer_email'],
				'language'          => $this->getLanguage($orderForm['customer_country']),
		);

		// Si hay empresa, usar company + contactPerson concatenado
		if (!empty($orderForm['customer_company'])) {
			$sender_data['company'] = $orderForm['customer_company'];
			$sender_data['contactPerson'] = trim($orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname']);
		} else {
			// Si no hay empresa, usar name + lastName1
			$sender_data['name'] = $orderForm['customer_firstname'];
			if (!empty($orderForm['customer_lastname'])) {
				$sender_data['lastName1'] = $orderForm['customer_lastname'];
			}
		}

		// DATOS PRODUCTO DEVOLUCIÓN
		switch ($payload['product_id']) {
			case 'S0159': // Paq Retorno Internacional
				$return_product_code = 'PAAZW';
				$return_delivery_mode = 'DOURUA';
				break;
			default: // Paq Retorno
				$return_product_code = 'PAAZE';
				$return_delivery_mode = 'DOURUA';
				break;
		}

		// DATA - SHIPMENT
		$shipment = array(
			'packagesNumber'     => $payload['bultos'],
			'product'            => $return_product_code,
			'deliveryMethod'     => $return_delivery_mode,
			'admissionMethod'    => 1, // PONE QUE NO ES REQUIERED PREGUNTAR!!!! 1- UAM / Office / Online, 2- Citypaq, 3- Delivery unit / UAM / Inverse logistic.
			'contractNumber'     => $payload['client']['CorreosContract'],
			'clientNumber'       => $payload['client']['CorreosCustomer'],
			'labellerCode'       => $payload['client']['CorreosKey'],
			'modificationType'   => CorreosOficialConfig::getConfigValue('CorreosModify') ?? '1',
			'shipmentNotes'      => '',
			'packages'           => $packages,
			'addressee'          => $addressee_data,
			'sender'             => $sender_data,	
			'additionalValues' => array(),
		);

		// NIF/CIF CUSTOMER
		if (!empty($orderForm['sender_nif_cif'])) {
			$shipment['addressee']['doiType']   = strval(CorreosOficialHelpers::getDocumentType($orderForm['customer_dni'])); // 0 (European ID), 1 (DNI/NIF), 3 (NIE), 10 (CIF)
			$shipment['addressee']['doiNumber'] = $orderForm['customer_dni'];
		}

		// NIF/CIF SENDER
		if (!empty($orderForm['customer_dni'])) {
			$shipment['sender']['doiType']   = strval(CorreosOficialHelpers::getDocumentType($orderForm['sender_nif_cif'])); // 0 (European ID), 1 (DNI/NIF), 3 (NIE), 10 (CIF)
			$shipment['sender']['doiNumber'] = $orderForm['sender_nif_cif'];
		}

		// Se añade pedido a la lista de envíos
		$shipments[] = $shipment;

		// DATA
		$data = array(
			'errorCodeLanguage' => $this->getLanguage($orderForm['sender_country']),
			'shipments' => $shipments,
		);

		$deliveryCall = $this->requestRestCall('/preregister/delivery', $data, $payload['client']);
		$deliveryResponse = json_decode($deliveryCall, true);

		// KO - PRE-Registro
		if (!empty($deliveryResponse) && $deliveryResponse['shipments'][0]['validationErrorCount']) {
			foreach ($deliveryResponse['shipments'][0]['error'] as $error) {
				$result[] = array(
				'codigoRetorno'  => $error['errorCode'],
				'mensajeRetorno' => $error['description'],
				'order'          => $payload['order_id'],
				);
			}
			return $result;
		} else {
			if (is_null($deliveryResponse)) {
				return array(
					array(
					'codigoRetorno'  => 1,
					'mensajeRetorno' => __('Error generating shipment, please try again later', 'correosoficial'),
					'orderId'        => $payload['order_id'],
					)
				);
			}
		}

		if (isset($deliveryResponse['code']) && $deliveryResponse['code'] == 400) {
			return array(
				array(
				'codigoRetorno'  => $deliveryResponse['code'],
				'mensajeRetorno' => $deliveryResponse['message'] . ': ' . (isset($deliveryResponse['moreInformation']['description']) ? $deliveryResponse['moreInformation']['description'] : ''),
				'orderId'        => $payload['order_id'],
				)
			);
		}

		// OK - PRE-Registro
		// Bultos
		$bultos = array();
		foreach ($deliveryResponse['shipments'][0]['packages'] as $key => $bulto) {
			$bultos[] = array(
			'numBulto'        => $key + 1,
			'shipping_number' => $bulto['packageCode'],
			);
			$payload['shipping_numbers'][] = $bulto['packageCode']; // Para recogidas
		}

		// RECOGIDAS ------------------------------------------------------------------------------------------ //
		if ($payload['needPickup'] === 'S') {
			$responsePickup = $this->registrarRecogida($payload);
		}

		// RETURN ESTANDARIZADO
		return array(
		'codigoRetorno'  => $deliveryResponse['shipments'][0]['validationErrorCount'],
		'mensajeRetorno' => '',
		'exp_number'     => $deliveryResponse['shipments'][0]['shipmentCode'],
		'bultos'         => $bultos,
		'pickup'         => isset($responsePickup) ? $responsePickup : array(),
		);
	}

	/* *********************************************************************************************************
	 * IMPRESION ETIQUETA
	 ********************************************************************************************************* */
	public function imprimirEtiqueta( $payload ) {

		$data = array(
			'application' => 'PRS',
			'documentationType' => 1, // (0) AMBOS, LABEL(1), CN23(2)
			'print' => array(
				'labelOrderType' => 2, // ordenacion de datos direccion cliente
				'labelFormat' => 2, // XML (1), PDF (2) or ZPL (3)
				'labelPrintMode' => 2, // (1) A4A, (2) TERMICA
				'labelPrintInitialPosition' => 1,
				'shipments' => $payload['shipments'],
			),
		);

		$labelCall = $this->requestRestCall('/labels/print/labels', $data, $payload['client']);
		$labelCallResponse = json_decode($labelCall, true);

		// Problema de conexión, credenciales, o cualquiera no controlado
		if (is_null($labelCallResponse)) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('Error obtaining the label, please try again later', 'correosoficial'),
				'orderId'        => $payload['order_id'],
			);
		}

		if (!isset($labelCallResponse['code']) && $labelCallResponse['pdf'] != '') {
			return array(
				'codigoRetorno'   => 0,
				'mensajeRetorno'  => '',
				'labels'          => array( base64_decode($labelCallResponse['pdf']) ),
				'orderId'         => $payload['order_id'],
				'reference'       => isset($payload['reference']) ? $payload['reference'] : '',
			);
		} else {
			$mensajeRetorno = isset($labelCallResponse['message']) ? $labelCallResponse['message'] : $labelCallResponse['error'];
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $mensajeRetorno ? $mensajeRetorno : __('The label could not be obtained', 'correosoficial'),
				'orderId'        => $payload['order_id'],
				'reference'      => isset($payload['reference']) ? $payload['reference'] : '',
			);
		}
	}

	/* *********************************************************************************************************
	 * DOCUMENTACION ADUANAS
	 ********************************************************************************************************* */
	public function getDocAduanera( $payload ) {
		
		$data = $this->optionsGetDocAduanera($payload);
		if ($payload['print_option'] == 'IMPRIMIRCN23BUTTON') {
			$labelCall = $this->requestRestCall('/labels/print/labels', $data, $payload['client']);
		} else {
			$labelCall = $this->requestRestCall('/labels/print/documents', $data, $payload['client']);
		}
		
		$labelCallResponse = json_decode($labelCall, true);

		// Problema de conexión, credenciales, o cualquiera no controlado
		if (is_null($labelCallResponse)) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('Error generating shipment, please try again later', 'correosoficial'),
				'orderId'        => $payload['order_id'],
			);
		}

		if (!isset($labelCallResponse['code']) && $labelCallResponse['pdf'] != '') {
			return array(
				'codigoRetorno'   => 0,
				'mensajeRetorno'  => '',
				'labels'          => array( base64_decode($labelCallResponse['pdf']) ),
				'orderId'        => $payload['order_id'],
				'reference'      => isset($payload['reference']) ? $payload['reference'] : '',
			);
		} else {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => isset($labelCallResponse['message']) ? $labelCallResponse['message'] : $labelCallResponse['error'],
				'orderId'        => $payload['order_id'],
				'reference'      => isset($payload['reference']) ? $payload['reference'] : '',
			);
		}
	}

	/* *********************************************************************************************************
	 * GET PICKUP LOCATIONS
	 ********************************************************************************************************* */
	public function getPickupLocations( $payload ) {
		$use_pce = false;

		switch ($payload['selector_type']) {
			case 'citypaq':
				$pickupLocationCall = $this->requestRestCall('/terminals/homepaqs?postalCode=' . urlencode($payload['postcode']) . '&visible=S&terminalStates=ACTIVO', [], $payload['client'], 'GET');
				$locations = json_decode($pickupLocationCall ?? '', true);
				break;
			case 'office':
				$sendDelivery = 'E'; // S -> Oficinas
							        //  E -> Oficinas + PCE

				if ( isset($payload['sendDelivery']) ) {
					$candidate_send_delivery = strtoupper((string) $payload['sendDelivery']);
					if ( in_array($candidate_send_delivery, array('S', 'E'), true) ) {
						$sendDelivery = $candidate_send_delivery;
					}
				}

				$use_pce = ($sendDelivery === 'E');
									
				$pickupLocationCall = $this->requestRestCall('/units/operative-unit?postalCode=' . urlencode($payload['postcode']) . '&statusCode=2&sendDelivery=' . $sendDelivery, [], $payload['client'], 'GET');
				$locations = json_decode($pickupLocationCall ?? '', true);

				if(isset($locations['content']) && count($locations['content'])){
					$locations = $locations['content'];
				}

				if ( is_array($locations) && isset($locations[0]) && is_array($locations[0]) ) {
					foreach ( $locations as &$location ) {
						$location['use_PCE'] = $use_pce;
					}
					unset($location);
				}

				break;
		}

		if (!empty($locations) && count($locations) < 1){
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('No Office or CityPaq found', 'correosoficial'),
				'use_PCE'        => $use_pce,
			);
		} else {
			return array(
				'codigoRetorno'  => 0,
				'mensajeRetorno' => '',
				'locations'      => $locations,
				'use_PCE'        => $use_pce,
			);
		}
	}

	public static function optionsGetDocAduanera( $payload ) {
		if ($payload['print_option'] == 'IMPRIMIRCN23BUTTON') {
			return array(
				'application'       => 'PRS',
				'documentationType' => $payload['documentation_type'], // (0) AMBOS, LABEL(1), CN23(2)
				'print'             => array(
					'labelOrderType'            => 2, // ordenacion de datos direccion cliente
					'labelFormat'               => 2, // XML (1), PDF (2) or ZPL (3)
					'labelPrintMode'            => 2, // (1) A4A, (2) TERMICA
					'labelPrintInitialPosition' => 1,
					'shipments'                 => $payload['shipments'],
				),
			);
		} else {
			return array(
				'application'       => 'PRS',
				'documentationType' => $payload['documentation_type'], // (5) DUA, DDP(6)
				'documentData'      => array(
					'contractNumber'  => $payload['client']['CorreosContract'],
					'clientNumber'    => $payload['client']['CorreosCustomer'],
					'addresseeName'   => $payload['adressed_name'],
					'destinationName' => $payload['customer_country'],
					'destinationCode' => $payload['customer_iso'],
					'shipmentsNumber' => (string) $payload['shipment_numbers'], // tiene que ser de tipo string.
					'signaturePlace'  => $payload['sender_city'],
					'signatoryDNI'    => $payload['sender_nif_cif'],
					'signatoryName'   => $payload['sender_name'],
				),
			);
		}
	}

	/* **********************************************************************************************************
	 *                                            HISTÓRICO DE PEDIDO
	 ********************************************************************************************************* */

	public function getOrderStatusP3( $payload ) {
		
		if (!isset($payload['shipping_number']) || empty(trim($payload['shipping_number']))) {
			return false;
		}

		$orderStatusRequest = $this->requestRestCall('/track/search-continuous-trace/' . urlencode($payload['shipping_number']), [], $payload['client'], 'GET');
		$orderStatusResponse = json_decode($orderStatusRequest ?? '', true);

		// Problema de conexión, credenciales, o cualquiera no controlado
		if(is_null($orderStatusResponse) || (isset($orderStatusResponse['code']) && $orderStatusResponse['code'] == 500)){
			// Si el error es 500, no se puede obtener el estado del pedido
			return false;
		}

		// Verificar que la respuesta tiene el formato esperado
		if (!isset($orderStatusResponse[0]) || !is_array($orderStatusResponse[0])) {
			return false;
		}

		if (isset($orderStatusResponse) && isset($orderStatusResponse[0]["error"]["codError"]) && $orderStatusResponse[0]["error"]["codError"] == "0") {
			return $orderStatusResponse;
		} else {
			return array(
				'codigoRetorno'  => isset($orderStatusResponse[0]["error"]["codError"]) ? $orderStatusResponse[0]["error"]["codError"] : 'ERROR',
				'mensajeRetorno' => isset($orderStatusResponse[0]["error"]["desError"]) ? $orderStatusResponse[0]["error"]["desError"] : 'Invalid response format'
			);
		}
	}

	/* LEGACY de CorreosRest de la librería - se usa para el histórico de correos
	* anteriormente, esta llamada se hacía por REST y no por SOAP como el resto
	*/
	public function getOrderStatus( $payload, $all = false ) {

		try {
			$decryptedPassword = CorreosOficialCrypto::decrypt($payload['client']['CorreosPassword']);
			if ($decryptedPassword === false) {
				return false;
			}
			$correos_user_password['password'] = $decryptedPassword;

			if (!isset($payload['shipping_number']) || empty($payload['shipping_number'])) {
				return false;
			}

			if ($all) {
				$url = $this->location($payload['shipping_number']) . '&indUltEvento=N';
			} else {
				$url = $this->location($payload['shipping_number']) . '&indUltEvento=S';
			}

			$username = $payload['client']['CorreosUser'];
			$password = $correos_user_password['password'];

			$orderStatusResponse = $this->requestRestCallOrderStatus($url, $username, $password);
			$orderStatusResponse = json_decode($orderStatusResponse);

			if (
				!is_object($orderStatusResponse) ||
				!isset($orderStatusResponse->error) ||
				!isset($orderStatusResponse->error->codError)
			) {
				return array(
					'codigoRetorno' => 1,
					'mensajeRetorno' => __('Is not possible to get the current shipment status', 'correosoficial'),
				);
			}

			return $orderStatusResponse;
		} catch (Exception $e) {
			return null;
		}
	}

	/* *********************************************************************************************************
	 * UTILS
	 ********************************************************************************************************* */
	
	 /**
	 *  Devuelve la URL para el servicio de seguimietno de correos
	  *
	 * @link https://localizador.correos.es/canonico/eventos_envio_servicio_auth/PY43B40720207850128042X?codIdioma=ES&indUltEvento=N';
	 * @return string Url para el servicio de seguimiento de correos
	 */
	private function location( $shipping_number ) {
		return sprintf(
			'%s/%s?codIdioma=ES',
			CORREOS_BASE_LOCATION,
			$shipping_number
		);
	}

	 /**
	 * Gets the time slot for a given time.
	 */
	public function getTimeSlot( $time ) {
		// Definir las franjas horarias
		$slots = array(
			1 => array( 'from' => '09:00', 'to' => '12:00' ),
			2 => array( 'from' => '12:00', 'to' => '15:00' ),
			3 => array( 'from' => '15:00', 'to' => '18:00' ),
			4 => array( 'from' => '18:00', 'to' => '21:00' ),
		);
	
		foreach ($slots as $slot => $range) {
			if ($time >= $range['from'] && $time < $range['to']) {
				return array( 'slot' => $slot, 'from' => $range['from'], 'to' => $range['to'] );
			}
		}
	
		// Si es antes de la primera franja, asignar la primera
		if ($time < $slots[1]['from']) {
			return array( 'slot' => 1, 'from' => $slots[1]['from'], 'to' => $slots[1]['to'] );
		}
	
		// Si es después de la última franja, asignar la última
		return array( 'slot' => 4, 'from' => $slots[4]['from'], 'to' => $slots[4]['to'] );
	}

	/**
	 * Gets the language code for a given ISO code.
	 */
	public function getLanguage( $iso ) {
		return strtolower($iso) === 'es' ? 'spa' : 'eng';
	}

	/**
	 * Checks if a JWT token is expired.
	 *
	 * @param string $jwt The JWT token to check.
	 * @return bool True if the token is expired, false otherwise.
	 */
	public function isJwtExpired( $jwt ) {
		// Dividir el token en sus partes (header, payload, signature)
		$tokenParts = explode('.', $jwt);
		
		if (count($tokenParts) !== 3) {
			return true; // Si el token no tiene el formato correcto, lo consideramos inválido
		}
	
		// Decodificar la parte del payload (Base64 URL decode)
		$tokenPayload = json_decode(base64_decode(str_replace(array( '-', '_' ), array( '+', '/' ), $tokenParts[1])), true);
	
		if (!$tokenPayload || !isset($tokenPayload['exp'])) {
			return true; // Si no tiene 'exp', asumimos que está caducado
		}
	
		// Comparar el tiempo actual con la expiración del token
		return $tokenPayload['exp'] < time();
	}

	public function getAVCode($delivery_mode, $product_code) {
		
		switch($delivery_mode) {
			case 'international':
					if ($product_code == 'POAXAC') {
						return 'VDINPO';
					}
					return 'SEINPA';
				break;
			default:
				return 'VADEPA';
		}
	}

	public static function getStateCodeByName( $name, $index = 'code' ) {
		$provinces = array(
			array( 'code' => '00', 'region_code' => 'PDA', 'name' => 'ANDORRA' ),
			array( 'code' => '01', 'region_code' => 'PVS', 'name' => 'ARABA/ALAVA' ),
			array( 'code' => '02', 'region_code' => 'CLA', 'name' => 'ALBACETE' ),
			array( 'code' => '03', 'region_code' => 'CVA', 'name' => 'ALACANT/ALICANTE' ),
			array( 'code' => '04', 'region_code' => 'AND', 'name' => 'ALMERIA' ),
			array( 'code' => '05', 'region_code' => 'CLE', 'name' => 'AVILA' ),
			array( 'code' => '06', 'region_code' => 'EXT', 'name' => 'BADAJOZ' ),
			array( 'code' => '07', 'region_code' => 'BAL', 'name' => 'ILLES BALEARS' ),
			array( 'code' => '08', 'region_code' => 'CAT', 'name' => 'BARCELONA' ),
			array( 'code' => '09', 'region_code' => 'CLE', 'name' => 'BURGOS' ),
			array( 'code' => '10', 'region_code' => 'EXT', 'name' => 'CACERES' ),
			array( 'code' => '11', 'region_code' => 'AND', 'name' => 'CADIZ' ),
			array( 'code' => '12', 'region_code' => 'CVA', 'name' => 'CASTELLÓ/CASTELLÓN' ),
			array( 'code' => '13', 'region_code' => 'CLA', 'name' => 'CIUDAD REAL' ),
			array( 'code' => '14', 'region_code' => 'AND', 'name' => 'CORDOBA' ),
			array( 'code' => '15', 'region_code' => 'GAL', 'name' => 'A CORUÑA' ),
			array( 'code' => '16', 'region_code' => 'CLA', 'name' => 'CUENCA' ),
			array( 'code' => '17', 'region_code' => 'CAT', 'name' => 'GIRONA' ),
			array( 'code' => '18', 'region_code' => 'AND', 'name' => 'GRANADA' ),
			array( 'code' => '19', 'region_code' => 'CLA', 'name' => 'GUADALAJARA' ),
			array( 'code' => '20', 'region_code' => 'PVS', 'name' => 'GIPUZKOA' ),
			array( 'code' => '21', 'region_code' => 'AND', 'name' => 'HUELVA' ),
			array( 'code' => '22', 'region_code' => 'ARA', 'name' => 'HUESCA' ),
			array( 'code' => '23', 'region_code' => 'AND', 'name' => 'JAEN' ),
			array( 'code' => '24', 'region_code' => 'CLE', 'name' => 'LEON' ),
			array( 'code' => '25', 'region_code' => 'CAT', 'name' => 'LLEIDA' ),
			array( 'code' => '26', 'region_code' => 'RIO', 'name' => 'LA RIOJA' ),
			array( 'code' => '27', 'region_code' => 'GAL', 'name' => 'LUGO' ),
			array( 'code' => '28', 'region_code' => 'MAD', 'name' => 'MADRID' ),
			array( 'code' => '29', 'region_code' => 'AND', 'name' => 'MALAGA' ),
			array( 'code' => '30', 'region_code' => 'MUR', 'name' => 'MURCIA' ),
			array( 'code' => '31', 'region_code' => 'NAV', 'name' => 'NAVARRA' ),
			array( 'code' => '32', 'region_code' => 'GAL', 'name' => 'OURENSE' ),
			array( 'code' => '33', 'region_code' => 'AST', 'name' => 'ASTURIAS' ),
			array( 'code' => '34', 'region_code' => 'CLE', 'name' => 'PALENCIA' ),
			array( 'code' => '35', 'region_code' => 'CAA', 'name' => 'LAS PALMAS' ),
			array( 'code' => '36', 'region_code' => 'GAL', 'name' => 'PONTEVEDRA' ),
			array( 'code' => '37', 'region_code' => 'CLE', 'name' => 'SALAMANCA' ),
			array( 'code' => '38', 'region_code' => 'CAA', 'name' => 'SANTA CRUZ DE TENERIFE' ),
			array( 'code' => '39', 'region_code' => 'CAB', 'name' => 'CANTABRIA' ),
			array( 'code' => '40', 'region_code' => 'CLE', 'name' => 'SEGOVIA' ),
			array( 'code' => '41', 'region_code' => 'AND', 'name' => 'SEVILLA' ),
			array( 'code' => '42', 'region_code' => 'CLE', 'name' => 'SORIA' ),
			array( 'code' => '43', 'region_code' => 'CAT', 'name' => 'TARRAGONA' ),
			array( 'code' => '44', 'region_code' => 'ARA', 'name' => 'TERUEL' ),
			array( 'code' => '45', 'region_code' => 'CLA', 'name' => 'TOLEDO' ),
			array( 'code' => '46', 'region_code' => 'CVA', 'name' => 'VALÈNCIA/VALENCIA' ),
			array( 'code' => '47', 'region_code' => 'CLE', 'name' => 'VALLADOLID' ),
			array( 'code' => '48', 'region_code' => 'PVS', 'name' => 'BIZKAIA' ),
			array( 'code' => '49', 'region_code' => 'CLE', 'name' => 'ZAMORA' ),
			array( 'code' => '50', 'region_code' => 'ARA', 'name' => 'ZARAGOZA' ),
			array( 'code' => '51', 'region_code' => 'CEU', 'name' => 'CEUTA' ),
			array( 'code' => '52', 'region_code' => 'MEL', 'name' => 'MELILLA' ),
		);

		foreach ($provinces as $province) {
			if (strcasecmp($province['name'], $name) === 0) {
				return $province[$index];
			}
		}
		return null;
	}

	public static function getCountryCodeByISO( $iso ) {
		$countries = array(
			array( 'Country' => 'Afganistán', 'Code' => 'AFG', 'ISO' => 'AF' ),
			array( 'Country' => 'Åland', 'Code' => 'ALA', 'ISO' => 'AX' ),
			array( 'Country' => 'Albania', 'Code' => 'ALB', 'ISO' => 'AL' ),
			array( 'Country' => 'Alemania', 'Code' => 'DEU', 'ISO' => 'DE' ),
			array( 'Country' => 'Andorra', 'Code' => 'AND', 'ISO' => 'AD' ),
			array( 'Country' => 'Angola', 'Code' => 'AGO', 'ISO' => 'AO' ),
			array( 'Country' => 'Anguila', 'Code' => 'AIA', 'ISO' => 'AI' ),
			array( 'Country' => 'Antártida', 'Code' => 'ATA', 'ISO' => 'AQ' ),
			array( 'Country' => 'Antigua y Barbuda', 'Code' => 'ATG', 'ISO' => 'AG' ),
			array( 'Country' => 'Antillas Neerlandesas', 'Code' => 'ANT', 'ISO' => 'AN' ),
			array( 'Country' => 'Arabia Saudita', 'Code' => 'SAU', 'ISO' => 'SA' ),
			array( 'Country' => 'Argelia', 'Code' => 'DZA', 'ISO' => 'DZ' ),
			array( 'Country' => 'Argentina', 'Code' => 'ARG', 'ISO' => 'AR' ),
			array( 'Country' => 'Armenia', 'Code' => 'ARM', 'ISO' => 'AM' ),
			array( 'Country' => 'Aruba', 'Code' => 'ABW', 'ISO' => 'AW' ),
			array( 'Country' => 'Ascensión', 'Code' => 'ASC', 'ISO' => 'AC' ),
			array( 'Country' => 'Australia', 'Code' => 'AUS', 'ISO' => 'AU' ),
			array( 'Country' => 'Austria', 'Code' => 'AUT', 'ISO' => 'AT' ),
			array( 'Country' => 'Azerbaiyán', 'Code' => 'AZE', 'ISO' => 'AZ' ),
			array( 'Country' => 'Bahamas', 'Code' => 'BHS', 'ISO' => 'BS' ),
			array( 'Country' => 'Banco Central Europeo', 'Code' => 'XBX', 'ISO' => 'XB' ),
			array( 'Country' => 'Bangladés', 'Code' => 'BGD', 'ISO' => 'BD' ),
			array( 'Country' => 'Barbados', 'Code' => 'BRB', 'ISO' => 'BB' ),
			array( 'Country' => 'Baréin', 'Code' => 'BHR', 'ISO' => 'BH' ),
			array( 'Country' => 'Bélgica', 'Code' => 'BEL', 'ISO' => 'BE' ),
			array( 'Country' => 'Belice', 'Code' => 'BLZ', 'ISO' => 'BZ' ),
			array( 'Country' => 'Benín', 'Code' => 'BEN', 'ISO' => 'BJ' ),
			array( 'Country' => 'Bermudas', 'Code' => 'BMU', 'ISO' => 'BM' ),
			array( 'Country' => 'Bielorrusia', 'Code' => 'BLR', 'ISO' => 'BY' ),
			array( 'Country' => 'Bolivia', 'Code' => 'BOL', 'ISO' => 'BO' ),
			array( 'Country' => 'Bonaire, San Eustaquio y Saba', 'Code' => 'BES', 'ISO' => 'BQ' ),
			array( 'Country' => 'Bosnia y Herzegovina', 'Code' => 'BIH', 'ISO' => 'BA' ),
			array( 'Country' => 'Botsuana', 'Code' => 'BWA', 'ISO' => 'BW' ),
			array( 'Country' => 'Brasil', 'Code' => 'BRA', 'ISO' => 'BR' ),
			array( 'Country' => 'Brunéi', 'Code' => 'BRN', 'ISO' => 'BN' ),
			array( 'Country' => 'Bulgaria', 'Code' => 'BGR', 'ISO' => 'BG' ),
			array( 'Country' => 'Burkina Faso', 'Code' => 'BFA', 'ISO' => 'BF' ),
			array( 'Country' => 'Burundi', 'Code' => 'BDI', 'ISO' => 'BI' ),
			array( 'Country' => 'Bután', 'Code' => 'BTN', 'ISO' => 'BT' ),
			array( 'Country' => 'Cabo Verde', 'Code' => 'CPV', 'ISO' => 'CV' ),
			array( 'Country' => 'Camboya', 'Code' => 'KHM', 'ISO' => 'KH' ),
			array( 'Country' => 'Camerún', 'Code' => 'CMR', 'ISO' => 'CM' ),
			array( 'Country' => 'Canadá', 'Code' => 'CAN', 'ISO' => 'CA' ),
			array( 'Country' => 'Catar', 'Code' => 'QAT', 'ISO' => 'QA' ),
			array( 'Country' => 'Chad', 'Code' => 'TCD', 'ISO' => 'TD' ),
			array( 'Country' => 'Chile', 'Code' => 'CHL', 'ISO' => 'CL' ),
			array( 'Country' => 'China', 'Code' => 'CHN', 'ISO' => 'CN' ),
			array( 'Country' => 'Chipre', 'Code' => 'CYP', 'ISO' => 'CY' ),
			array( 'Country' => 'Colombia', 'Code' => 'COL', 'ISO' => 'CO' ),
			array( 'Country' => 'Comoras', 'Code' => 'COM', 'ISO' => 'KM' ),
			array( 'Country' => 'Corea del Norte', 'Code' => 'PRK', 'ISO' => 'KP' ),
			array( 'Country' => 'Corea del Sur', 'Code' => 'KOR', 'ISO' => 'KR' ),
			array( 'Country' => 'Costa de Marfil', 'Code' => 'CIV', 'ISO' => 'CI' ),
			array( 'Country' => 'Costa Rica', 'Code' => 'CRI', 'ISO' => 'CR' ),
			array( 'Country' => 'Croacia', 'Code' => 'HRV', 'ISO' => 'HR' ),
			array( 'Country' => 'Cuba', 'Code' => 'CUB', 'ISO' => 'CU' ),
			array( 'Country' => 'Curazao', 'Code' => 'CUW', 'ISO' => 'CW' ),
			array( 'Country' => 'Dinamarca', 'Code' => 'DNK', 'ISO' => 'DK' ),
			array( 'Country' => 'Dominica', 'Code' => 'DMA', 'ISO' => 'DM' ),
			array( 'Country' => 'Ecuador', 'Code' => 'ECU', 'ISO' => 'EC' ),
			array( 'Country' => 'Egipto', 'Code' => 'EGY', 'ISO' => 'EG' ),
			array( 'Country' => 'El Salvador', 'Code' => 'SLV', 'ISO' => 'SV' ),
			array( 'Country' => 'Emiratos Árabes Unidos', 'Code' => 'ARE', 'ISO' => 'AE' ),
			array( 'Country' => 'Eritrea', 'Code' => 'ERI', 'ISO' => 'ER' ),
			array( 'Country' => 'Eslovaquia', 'Code' => 'SVK', 'ISO' => 'SK' ),
			array( 'Country' => 'Eslovenia', 'Code' => 'SVN', 'ISO' => 'SI' ),
			array( 'Country' => 'España', 'Code' => 'ESP', 'ISO' => 'ES' ),
			array( 'Country' => 'Estados Unidos', 'Code' => 'USA', 'ISO' => 'US' ),
			array( 'Country' => 'Estonia', 'Code' => 'EST', 'ISO' => 'EE' ),
			array( 'Country' => 'Etiopía', 'Code' => 'ETH', 'ISO' => 'ET' ),
			array( 'Country' => 'Filipinas', 'Code' => 'PHL', 'ISO' => 'PH' ),
			array( 'Country' => 'Finlandia', 'Code' => 'FIN', 'ISO' => 'FI' ),
			array( 'Country' => 'Fiyi', 'Code' => 'FJI', 'ISO' => 'FJ' ),
			array( 'Country' => 'Francia', 'Code' => 'FRA', 'ISO' => 'FR' ),
			array( 'Country' => 'Gabón', 'Code' => 'GAB', 'ISO' => 'GA' ),
			array( 'Country' => 'Gambia', 'Code' => 'GMB', 'ISO' => 'GM' ),
			array( 'Country' => 'Georgia', 'Code' => 'GEO', 'ISO' => 'GE' ),
			array( 'Country' => 'Ghana', 'Code' => 'GHA', 'ISO' => 'GH' ),
			array( 'Country' => 'Gibraltar', 'Code' => 'GIB', 'ISO' => 'GI' ),
			array( 'Country' => 'Granada', 'Code' => 'GRD', 'ISO' => 'GD' ),
			array( 'Country' => 'Grecia', 'Code' => 'GRC', 'ISO' => 'GR' ),
			array( 'Country' => 'Groenlandia', 'Code' => 'GRL', 'ISO' => 'GL' ),
			array( 'Country' => 'Guadalupe', 'Code' => 'GLP', 'ISO' => 'GP' ),
			array( 'Country' => 'Guam', 'Code' => 'GUM', 'ISO' => 'GU' ),
			array( 'Country' => 'Guatemala', 'Code' => 'GTM', 'ISO' => 'GT' ),
			array( 'Country' => 'Guayana Francesa', 'Code' => 'GUF', 'ISO' => 'GF' ),
			array( 'Country' => 'Guernsey', 'Code' => 'GGY', 'ISO' => 'GG' ),
			array( 'Country' => 'Guinea', 'Code' => 'GIN', 'ISO' => 'GN' ),
			array( 'Country' => 'Guinea-Bisáu', 'Code' => 'GNB', 'ISO' => 'GW' ),
			array( 'Country' => 'Guinea Ecuatorial', 'Code' => 'GNQ', 'ISO' => 'GQ' ),
			array( 'Country' => 'Guyana', 'Code' => 'GUY', 'ISO' => 'GY' ),
			array( 'Country' => 'Haití', 'Code' => 'HTI', 'ISO' => 'HT' ),
			array( 'Country' => 'Honduras', 'Code' => 'HND', 'ISO' => 'HN' ),
			array( 'Country' => 'Hong Kong', 'Code' => 'HKG', 'ISO' => 'HK' ),
			array( 'Country' => 'Hungría', 'Code' => 'HUN', 'ISO' => 'HU' ),
			array( 'Country' => 'India', 'Code' => 'IND', 'ISO' => 'IN' ),
			array( 'Country' => 'Indonesia', 'Code' => 'IDN', 'ISO' => 'ID' ),
			array( 'Country' => 'Inmarsat', 'Code' => 'XTX', 'ISO' => 'XT' ),
			array( 'Country' => 'Instituciones de la Unión Europea', 'Code' => 'XUX', 'ISO' => 'XU' ),
			array( 'Country' => 'Irak', 'Code' => 'IRQ', 'ISO' => 'IQ' ),
			array( 'Country' => 'Irán', 'Code' => 'IRN', 'ISO' => 'IR' ),
			array( 'Country' => 'Irlanda', 'Code' => 'IRL', 'ISO' => 'IE' ),
			array( 'Country' => 'Isla Bouvet', 'Code' => 'BVT', 'ISO' => 'BV' ),
			array( 'Country' => 'Isla de Man', 'Code' => 'IMN', 'ISO' => 'IM' ),
			array( 'Country' => 'Isla de Navidad', 'Code' => 'CXR', 'ISO' => 'CX' ),
			array( 'Country' => 'Islandia', 'Code' => 'ISL', 'ISO' => 'IS' ),
			array( 'Country' => 'Islas Caimán', 'Code' => 'CYM', 'ISO' => 'KY' ),
			array( 'Country' => 'Islas Cocos', 'Code' => 'CCK', 'ISO' => 'CC' ),
			array( 'Country' => 'Islas Cook', 'Code' => 'COK', 'ISO' => 'CK' ),
			array( 'Country' => 'Islas Feroe', 'Code' => 'FRO', 'ISO' => 'FO' ),
			array( 'Country' => 'Islas Georgias del Sur y Sandwich del Sur', 'Code' => 'SGS', 'ISO' => 'GS' ),
			array( 'Country' => 'Islas Heard y McDonald', 'Code' => 'HMD', 'ISO' => 'HM' ),
			array( 'Country' => 'Islas Malvinas', 'Code' => 'FLK', 'ISO' => 'FK' ),
			array( 'Country' => 'Islas Marianas del Norte', 'Code' => 'MNP', 'ISO' => 'MP' ),
			array( 'Country' => 'Islas Marshall', 'Code' => 'MHL', 'ISO' => 'MH' ),
			array( 'Country' => 'Islas Pitcairn', 'Code' => 'PCN', 'ISO' => 'PN' ),
			array( 'Country' => 'Islas Salomón', 'Code' => 'SLB', 'ISO' => 'SB' ),
			array( 'Country' => 'Islas Turcas y Caicos', 'Code' => 'TCA', 'ISO' => 'TC' ),
			array( 'Country' => 'Islas ultramarinas de Estados Unidos', 'Code' => 'UMI', 'ISO' => 'UM' ),
			array( 'Country' => 'Islas Vírgenes Británicas', 'Code' => 'VGB', 'ISO' => 'VG' ),
			array( 'Country' => 'Islas Vírgenes de los Estados Unidos', 'Code' => 'VIR', 'ISO' => 'VI' ),
			array( 'Country' => 'Israel', 'Code' => 'ISR', 'ISO' => 'IL' ),
			array( 'Country' => 'Italia', 'Code' => 'ITA', 'ISO' => 'IT' ),
			array( 'Country' => 'Jamaica', 'Code' => 'JAM', 'ISO' => 'JM' ),
			array( 'Country' => 'Japón', 'Code' => 'JPN', 'ISO' => 'JP' ),
			array( 'Country' => 'Jersey', 'Code' => 'JEY', 'ISO' => 'JE' ),
			array( 'Country' => 'Jordania', 'Code' => 'JOR', 'ISO' => 'JO' ),
			array( 'Country' => 'Kazajistán', 'Code' => 'KAZ', 'ISO' => 'KZ' ),
			array( 'Country' => 'Kenia', 'Code' => 'KEN', 'ISO' => 'KE' ),
			array( 'Country' => 'Kirguistán', 'Code' => 'KGZ', 'ISO' => 'KG' ),
			array( 'Country' => 'Kiribati', 'Code' => 'KIR', 'ISO' => 'KI' ),
			array( 'Country' => 'Kosovo', 'Code' => 'XKX', 'ISO' => 'XK' ),
			array( 'Country' => 'Kuwait', 'Code' => 'KWT', 'ISO' => 'KW' ),
			array( 'Country' => 'Laos', 'Code' => 'LAO', 'ISO' => 'LA' ),
			array( 'Country' => 'Lesoto', 'Code' => 'LSO', 'ISO' => 'LS' ),
			array( 'Country' => 'Letonia', 'Code' => 'LVA', 'ISO' => 'LV' ),
			array( 'Country' => 'Líbano', 'Code' => 'LBN', 'ISO' => 'LB' ),
			array( 'Country' => 'Liberia', 'Code' => 'LBR', 'ISO' => 'LR' ),
			array( 'Country' => 'Libya', 'Code' => 'LBY', 'ISO' => 'LY' ),
			array( 'Country' => 'Liechtenstein', 'Code' => 'LIE', 'ISO' => 'LI' ),
			array( 'Country' => 'Lithuania', 'Code' => 'LTU', 'ISO' => 'LT' ),
			array( 'Country' => 'Luxembourg', 'Code' => 'LUX', 'ISO' => 'LU' ),
			array( 'Country' => 'Macao', 'Code' => 'MAC', 'ISO' => 'MO' ),
			array( 'Country' => 'Macedonia', 'Code' => 'MKD', 'ISO' => 'MK' ),
			array( 'Country' => 'Madagascar', 'Code' => 'MDG', 'ISO' => 'MG' ),
			array( 'Country' => 'Malaysia', 'Code' => 'MYS', 'ISO' => 'MY' ),
			array( 'Country' => 'Malawi', 'Code' => 'MWI', 'ISO' => 'MW' ),
			array( 'Country' => 'Maldives', 'Code' => 'MDV', 'ISO' => 'MV' ),
			array( 'Country' => 'Mali', 'Code' => 'MLI', 'ISO' => 'ML' ),
			array( 'Country' => 'Malta', 'Code' => 'MLT', 'ISO' => 'MT' ),
			array( 'Country' => 'Morocco', 'Code' => 'MAR', 'ISO' => 'MA' ),
			array( 'Country' => 'Martinique', 'Code' => 'MTQ', 'ISO' => 'MQ' ),
			array( 'Country' => 'Mauritius', 'Code' => 'MUS', 'ISO' => 'MU' ),
			array( 'Country' => 'Mauritania', 'Code' => 'MRT', 'ISO' => 'MR' ),
			array( 'Country' => 'Mayotte', 'Code' => 'MYT', 'ISO' => 'YT' ),
			array( 'Country' => 'Mexico', 'Code' => 'MEX', 'ISO' => 'MX' ),
			array( 'Country' => 'Micronesia', 'Code' => 'FSM', 'ISO' => 'FM' ),
			array( 'Country' => 'Moldova', 'Code' => 'MDA', 'ISO' => 'MD' ),
			array( 'Country' => 'Monaco', 'Code' => 'MCO', 'ISO' => 'MC' ),
			array( 'Country' => 'Mongolia', 'Code' => 'MNG', 'ISO' => 'MN' ),
			array( 'Country' => 'Montenegro', 'Code' => 'MNE', 'ISO' => 'ME' ),
			array( 'Country' => 'Montserrat', 'Code' => 'MSR', 'ISO' => 'MS' ),
			array( 'Country' => 'Mozambique', 'Code' => 'MOZ', 'ISO' => 'MZ' ),
			array( 'Country' => 'Myanmar', 'Code' => 'MMR', 'ISO' => 'MM' ),
			array( 'Country' => 'Namibia', 'Code' => 'NAM', 'ISO' => 'NA' ),
			array( 'Country' => 'Nauru', 'Code' => 'NRU', 'ISO' => 'NR' ),
			array( 'Country' => 'Nepal', 'Code' => 'NPL', 'ISO' => 'NP' ),
			array( 'Country' => 'Nicaragua', 'Code' => 'NIC', 'ISO' => 'NI' ),
			array( 'Country' => 'Niger', 'Code' => 'NER', 'ISO' => 'NE' ),
			array( 'Country' => 'Nigeria', 'Code' => 'NGA', 'ISO' => 'NG' ),
			array( 'Country' => 'Niue', 'Code' => 'NIU', 'ISO' => 'NU' ),
			array( 'Country' => 'Norfolk', 'Code' => 'NFK', 'ISO' => 'NF' ),
			array( 'Country' => 'Norway', 'Code' => 'NOR', 'ISO' => 'NO' ),
			array( 'Country' => 'New Caledonia', 'Code' => 'NCL', 'ISO' => 'NC' ),
			array( 'Country' => 'New Zealand', 'Code' => 'NZL', 'ISO' => 'NZ' ),
			array( 'Country' => 'Oman', 'Code' => 'OMN', 'ISO' => 'OM' ),
			array( 'Country' => 'International organizations other than EU institutions and ECB', 'Code' => 'XNX', 'ISO' => 'XN' ),
			array( 'Country' => 'Other countries or territories not listed', 'Code' => 'QUQ', 'ISO' => 'QU' ),
			array( 'Country' => 'Netherlands', 'Code' => 'NLD', 'ISO' => 'NL' ),
			array( 'Country' => 'Pakistan', 'Code' => 'PAK', 'ISO' => 'PK' ),
			array( 'Country' => 'Palau', 'Code' => 'PLW', 'ISO' => 'PW' ),
			array( 'Country' => 'Palestine', 'Code' => 'PSE', 'ISO' => 'PS' ),
			array( 'Country' => 'Panama', 'Code' => 'PAN', 'ISO' => 'PA' ),
			array( 'Country' => 'Papua New Guinea', 'Code' => 'PNG', 'ISO' => 'PG' ),
			array( 'Country' => 'Paraguay', 'Code' => 'PRY', 'ISO' => 'PY' ),
			array( 'Country' => 'Peru', 'Code' => 'PER', 'ISO' => 'PE' ),
			array( 'Country' => 'French Polynesia', 'Code' => 'PYF', 'ISO' => 'PF' ),
			array( 'Country' => 'Poland', 'Code' => 'POL', 'ISO' => 'PL' ),
			array( 'Country' => 'Portugal', 'Code' => 'PRT', 'ISO' => 'PT' ),
			array( 'Country' => 'Puerto Rico', 'Code' => 'PRI', 'ISO' => 'PR' ),
			array( 'Country' => 'United Kingdom', 'Code' => 'GBR', 'ISO' => 'GB' ),
			array( 'Country' => 'Sahrawi Arab Democratic Republic', 'Code' => 'ESH', 'ISO' => 'EH' ),
			array( 'Country' => 'Central African Republic', 'Code' => 'CAF', 'ISO' => 'CF' ),
			array( 'Country' => 'Czech Republic', 'Code' => 'CZE', 'ISO' => 'CZ' ),
			array( 'Country' => 'Republic of the Congo', 'Code' => 'COG', 'ISO' => 'CG' ),
			array( 'Country' => 'Democratic Republic of the Congo', 'Code' => 'COD', 'ISO' => 'CD' ),
			array( 'Country' => 'Dominican Republic', 'Code' => 'DOM', 'ISO' => 'DO' ),
			array( 'Country' => 'Reunion', 'Code' => 'REU', 'ISO' => 'RE' ),
			array( 'Country' => 'Rwanda', 'Code' => 'RWA', 'ISO' => 'RW' ),
			array( 'Country' => 'Romania', 'Code' => 'ROU', 'ISO' => 'RO' ),
			array( 'Country' => 'Russia', 'Code' => 'RUS', 'ISO' => 'RU' ),
			array( 'Country' => 'Samoa', 'Code' => 'WSM', 'ISO' => 'WS' ),
			array( 'Country' => 'American Samoa', 'Code' => 'ASM', 'ISO' => 'AS' ),
			array( 'Country' => 'Saint Barthelemy', 'Code' => 'BLM', 'ISO' => 'BL' ),
			array( 'Country' => 'Saint Kitts and Nevis', 'Code' => 'KNA', 'ISO' => 'KN' ),
			array( 'Country' => 'San Marino', 'Code' => 'SMR', 'ISO' => 'SM' ),
			array( 'Country' => 'Saint Martin', 'Code' => 'MAF', 'ISO' => 'MF' ),
			array( 'Country' => 'Saint Pierre and Miquelon', 'Code' => 'SPM', 'ISO' => 'PM' ),
			array( 'Country' => 'Saint Helena, Ascension and Tristan da Cunha', 'Code' => 'SHN', 'ISO' => 'SH' ),
			array( 'Country' => 'Saint Lucia', 'Code' => 'LCA', 'ISO' => 'LC' ),
			array( 'Country' => 'São Tomé and Príncipe', 'Code' => 'STP', 'ISO' => 'ST' ),
			array( 'Country' => 'Saint Vincent and the Grenadines', 'Code' => 'VCT', 'ISO' => 'VC' ),
			array( 'Country' => 'Senegal', 'Code' => 'SEN', 'ISO' => 'SN' ),
			array( 'Country' => 'Serbia', 'Code' => 'SRB', 'ISO' => 'RS' ),
			array( 'Country' => 'Seychelles', 'Code' => 'SYC', 'ISO' => 'SC' ),
			array( 'Country' => 'Sierra Leone', 'Code' => 'SLE', 'ISO' => 'SL' ),
			array( 'Country' => 'Singapore', 'Code' => 'SGP', 'ISO' => 'SG' ),
			array( 'Country' => 'Sint Maarten', 'Code' => 'SXM', 'ISO' => 'SX' ),
			array( 'Country' => 'Syria', 'Code' => 'SYR', 'ISO' => 'SY' ),
			array( 'Country' => 'Somalia', 'Code' => 'SOM', 'ISO' => 'SO' ),
			array( 'Country' => 'Sri Lanka', 'Code' => 'LKA', 'ISO' => 'LK' ),
			array( 'Country' => 'Swaziland', 'Code' => 'SWZ', 'ISO' => 'SZ' ),
			array( 'Country' => 'South Africa', 'Code' => 'ZAF', 'ISO' => 'ZA' ),
			array( 'Country' => 'Sudan', 'Code' => 'SDN', 'ISO' => 'SD' ),
			array( 'Country' => 'South Sudan', 'Code' => 'SSD', 'ISO' => 'SS' ),
			array( 'Country' => 'Sweden', 'Code' => 'SWE', 'ISO' => 'SE' ),
			array( 'Country' => 'Switzerland', 'Code' => 'CHE', 'ISO' => 'CH' ),
			array( 'Country' => 'Suriname', 'Code' => 'SUR', 'ISO' => 'SR' ),
			array( 'Country' => 'Svalbard and Jan Mayen', 'Code' => 'SJM', 'ISO' => 'SJ' ),
			array( 'Country' => 'Thailand', 'Code' => 'THA', 'ISO' => 'TH' ),
			array( 'Country' => 'Taiwan (Republic of China)', 'Code' => 'TWN', 'ISO' => 'TW' ),
			array( 'Country' => 'Tanzania', 'Code' => 'TZA', 'ISO' => 'TZ' ),
			array( 'Country' => 'Tajikistan', 'Code' => 'TJK', 'ISO' => 'TJ' ),
			array( 'Country' => 'British Indian Ocean Territory', 'Code' => 'IOT', 'ISO' => 'IO' ),
			array( 'Country' => 'French Southern and Antarctic Lands', 'Code' => 'ATF', 'ISO' => 'TF' ),
			array( 'Country' => 'East Timor', 'Code' => 'TLS', 'ISO' => 'TL' ),
			array( 'Country' => 'Togo', 'Code' => 'TGO', 'ISO' => 'TG' ),
			array( 'Country' => 'Tokelau', 'Code' => 'TKL', 'ISO' => 'TK' ),
			array( 'Country' => 'Tonga', 'Code' => 'TON', 'ISO' => 'TO' ),
			array( 'Country' => 'Trinidad and Tobago', 'Code' => 'TTO', 'ISO' => 'TT' ),
			array( 'Country' => 'Tristan da Cunha', 'Code' => 'TAA', 'ISO' => 'TA' ),
			array( 'Country' => 'Tunisia', 'Code' => 'TUN', 'ISO' => 'TN' ),
			array( 'Country' => 'Turkmenistan', 'Code' => 'TKM', 'ISO' => 'TM' ),
			array( 'Country' => 'Turkey', 'Code' => 'TUR', 'ISO' => 'TR' ),
			array( 'Country' => 'Tuvalu', 'Code' => 'TUV', 'ISO' => 'TV' ),
			array( 'Country' => 'Ukraine', 'Code' => 'UKR', 'ISO' => 'UA' ),
			array( 'Country' => 'Uganda', 'Code' => 'UGA', 'ISO' => 'UG' ),
			array( 'Country' => 'Uruguay', 'Code' => 'URY', 'ISO' => 'UY' ),
			array( 'Country' => 'Uzbekistan', 'Code' => 'UZB', 'ISO' => 'UZ' ),
			array( 'Country' => 'Vanuatu', 'Code' => 'VUT', 'ISO' => 'VU' ),
			array( 'Country' => 'Vatican City', 'Code' => 'VAT', 'ISO' => 'VA' ),
			array( 'Country' => 'Venezuela', 'Code' => 'VEN', 'ISO' => 'VE' ),
			array( 'Country' => 'Vietnam', 'Code' => 'VNM', 'ISO' => 'VN' ),
			array( 'Country' => 'Wallis and Futuna', 'Code' => 'WLF', 'ISO' => 'WF' ),
			array( 'Country' => 'Yemen', 'Code' => 'YEM', 'ISO' => 'YE' ),
			array( 'Country' => 'Djibouti', 'Code' => 'DJI', 'ISO' => 'DJ' ),
			array( 'Country' => 'Zambia', 'Code' => 'ZMB', 'ISO' => 'ZM' ),
			array( 'Country' => 'Zimbabwe', 'Code' => 'ZWE', 'ISO' => 'ZW' ),

		);
		
		foreach ($countries as $country) {
			if ($country['ISO'] === $iso) {
				return $country['Code'];
			}
		}
		return null;
	}

	/**
	 * Determina si usar postcode o zip según el país
	 * ES y AD usan postcode, el resto usa zip
	 * 
	 * @param string $postalCode Código postal
	 * @param string $countryCode Código ISO del país
	 * @return array Array con 'postcode' y 'zip'
	 */
	private function getPostalCodeFields($postalCode, $countryCode) {
		$usePostcode = in_array($countryCode, ['ES', 'AD']);
		return [
			'postcode' => $usePostcode ? $postalCode : '',
			'zip' => $usePostcode ? '' : $postalCode
		];
	}
}
