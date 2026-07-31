<?php
namespace CorreosOficial\Models;

use WC_Data;
use WC_Order;
use CorreosOficial\Classes\Apis\CorreosOficialCEXRest;
use CorreosOficial\Classes\Apis\CorreosOficialRest;
use CorreosOficial\Classes\Apis\CorreosOficialSoap;
use CorreosOficial\Classes\CorreosOficialCrypto;

defined('ABSPATH') || exit;

class CorreosOficialCode extends WC_Data {

	protected $data = array(
		'customer_code'   => '',
		'company'         => null,
		'CorreosContract' => 'n/a',
		'CorreosCustomer' => 'n/a',
		'CorreosKey'      => 'n/a',
		'CorreosUser'     => 'n/a',
		'CorreosPassword' => 'n/a',
		'CorreosOv2Code'  => 'n/a',
		'CEXCustomer'     => 'n/a',
		'CEXUser'         => 'n/a',
		'CEXPassword'     => 'n/a',
		'CorreosClientID' => 'n/a',
		'CorreosSecretID' => 'n/a',
	);

	protected $object_type = 'correos_oficial_code';

	public function __construct( $id = 0 ) {
		parent::__construct($id);
		$this->data_store = new CorreosOficialCodeDataStore();

		if ($id > 0) {
			$this->set_id($id);
			$this->read();
		}
	}

	// Getter y Setter dinámicos
	public function __call( $method, $arguments ) {
		if (strpos($method, 'get_') === 0) {
			$prop = substr($method, 4);
			return isset($this->data[$prop]) ? $this->data[$prop] : null;
		}

		if (strpos($method, 'set_') === 0) {
			$prop = substr($method, 4);
			if (array_key_exists($prop, $this->data)) {
				$this->data[$prop] = $arguments[0];
			}
		}
	}

    public static function getAllCodes() {
		global $wpdb;

		$results = array();

		// Verifica que la extensión SOAP esté activa
		if (!extension_loaded('soap')) {
			wp_send_json($results);
		}

		// Asume que el prefijo de la tabla ya está incluido si usas una tabla personalizada
		$table_name = $wpdb->prefix . 'correos_oficial_codes';
		$query = "SELECT * FROM {$table_name}";

		$results = $wpdb->get_results($query, ARRAY_A);
		$codes = [];

		foreach ($results as $row) {
			$code = new self($row['id']);
			if ($code) {
				$code_data = (array) $code->data;
				$code_data['id'] = $code->id;
				$codes[] = $code_data;
			}
		}

		return $codes;
	}

    public static function validateUser($user_data) {
		// Verifica que la extensión SOAP esté activa
        if (!extension_loaded('soap')) {
            die(json_encode([]));
        }

        if ($user_data['company'] === 'Correos') {
            return self::validateCorreosUser($user_data);
        } 
        
        if ($user_data['company'] === 'CEX') {
            return (new CorreosOficialCEXRest())->altaClienteCEXCall(['client' => $user_data]);
        }

        return false;
    }

    private static function validateCorreosUser($user_data)
    {
        // Validación con ClientID/SecretID
        if ($user_data['CorreosClientID'] !== 'n/a' && $user_data['CorreosSecretID'] !== 'n/a') {
            return self::validateCorreosWithRestApi($user_data);
        }
        
        // Validación con User/Password (SOAP)
        if (!empty($user_data["CorreosUser"]) && !empty($user_data["CorreosPassword"])) {
            return (new CorreosOficialSoap())->altaClienteCorreosOpCall(['client' => $user_data]);
        }
        
        return false;
    }

    private static function validateCorreosWithRestApi($user_data)
    {
        // Validar nacional
        $nationalResult = self::validateDeliveryService($user_data, 'national', 1003);
        if ($nationalResult === null) {
            return false; // Error JWT
        }

        // Validar internacional
        $internationalResult = self::validateDeliveryService($user_data, 'international', 1005);
        if ($internationalResult === null) {
            return false; // Error JWT
        }

        return $nationalResult || $internationalResult;
    }

    // Valida el servicio de envíos (nacional o internacional)
    private static function validateDeliveryService($user_data, $type, $allowedErrorCode)
    {
        self::clearSessionToken();

        $json = ($type === 'national') ? self::getNationalJson($user_data) : self::getInternationalJson($user_data);

        $deliveryCall = (new CorreosOficialRest())->requestRestCall(
            '/preregister/delivery', 
            json_decode($json, true), 
            $user_data
        );

        $deliveryResponse = json_decode($deliveryCall ?? '', true);

        self::clearSessionToken();

        // Validar TOKEN - CLIENTID / CLIENTSECRET
        if (isset($deliveryResponse['error']) && strpos($deliveryResponse['error'], 'JWT') !== false) {
            return null; // Error de autenticación
        }

        // Validar respuesta vacía o error 400
        if ($deliveryResponse === null || (isset($deliveryResponse['code']) && $deliveryResponse['code'] === '400')) {
            return false;
        }

        // Si hay errores de validación, verificar que todos sean del código permitido
        if (isset($deliveryResponse['shipments'][0]['validationErrorCount']) 
            && $deliveryResponse['shipments'][0]['validationErrorCount'] >= 1) {
            
            foreach ($deliveryResponse['shipments'][0]['error'] as $error) {
                if ($error['errorCode'] != $allowedErrorCode) {
                    return false;
                }
            }
        }

        // Validacion correcta
        return true;
    }

    private static function clearSessionToken()
    {
        if (isset($_SESSION['tokenP3'])) {
            unset($_SESSION['tokenP3']);
        }
    }

	public function read() {
		$this->data_store->read($this);
	}
	
	public function save() {
		$this->data_store->update($this);
	}
	
	public function create() {
		$this->data_store->create($this);
	}

    public function getCodeByCustomer($customer_code) {
        $this->data_store->get_code_by_customer($this, $customer_code);

        return $this;
    }

    public static function getNationalJson($code_data) {
        return '{
            "application": "NEO",
            "shipments": [
                {
                "packagesNumber": "1",
                "product": "PAFXB",
                "admissionMethod": 1,
                "deliveryMethod": "DOUAOF",
                "frankingType": "FP",
                "totalWeight": "3000",
                "contractNumber": "' . $code_data["CorreosContract"] . '",
                "clientNumber": "' . $code_data["CorreosCustomer"] . '",
                "labellerCode": "' . $code_data["CorreosKey"] . '",
                "contractIndicator": "Y",
                "packages": [
                    {
                    "packageId": "TEST CREDENTIALS",
                    "packageWeightGrams": "1000",
                    "packageHeight": "1",
                    "packageWidth": "145",
                    "packageLength": "100",
                    "clientReference": "TEST CREDENTIALS",
                    "clientReference2": "",
                    "clientReference3": "",
                    "observations": "Observciones paq 1",
                    "packageContents": {
                        "shipmentType": "2",
                        "invoiceNumber": "",
                        "licenseNumber": "",
                        "certificateNumber": "",
                        "customReferenceConsignor": "",
                        "importerTaxReference": "",
                        "importerCode": "",
                        "phoneNumber": "666777888",
                        "importerEmail": "test@mail.com",
                        "instructionsDoNotDeliver": "D",
                        "customsData": [
                        {
                        "quantity": "2",
                        "description": "Contenido de prueba",
                        "netWeight": "150",
                        "netValue": "150",
                        "tariffNumber": "490110",
                        "countryOrigin": "ESP"
                        }
                        ]
                    }
                    }
                ],
                "addressee": {
                    "name": "NOMBRE DESTINATARIO",
                    "lastName1": "APELLIDO 1 D",
                    "lastName2": "APELLIDO 2 D",
                    "doiType": "1",
                    "doiNumber": "00000000T",
                    "company": "",
                    "contactPerson": "CONTACT PERSON D",
                    "addressType": "CJ",
                    "address": "ADDRESS DESTINO",
                    "number": "25",
                    "portal": "PR",
                    "block": "BR",
                    "staircase": "ww",
                    "floor": "ss",
                    "door": "Izda",
                    "addressComplement": "DIR 2 DESTINO",
                    "locality": "Madrid",
                    "province": "28",
                    "cp": "28998",
                    "country": "ESP",
                    "contactPhone": "666777888",
                    "email": "test@pruebas.com",
                    "smsNumber": "666555444",
                    "language": "spa"
                },
                "sender": {
                    "name": "Nombre Remitente",
                    "lastName1": "Apellido 1 Remitente",
                    "lastName2": "Apellido 2 Remitente",
                    "doiType": "1",
                    "doiNumber": "12345678Z",
                    "company": "COMPANY RMTE",
                    "contactPerson": "CONTACT RMTE",
                    "addressType": "CJ",
                    "address": "ADDRESS RMTE",
                    "number": "25",
                    "portal": "PR",
                    "block": "BR",
                    "staircase": "C",
                    "floor": "4",
                    "door": "Izda",
                    "addressComplement": "ADDRESS 2 RMTE",
                    "locality": "LOC RMTE",
                    "province": "28",
                    "cp": "28037",

                    "country": "ESP",
                    "contactPhone": "666888999",
                    "email": "test@pruebas.com",
                    "smsNumber": "",
                    "language": "spa"
                },
            "additionalValues": [
                ]
                }
            ]
            }
        ';
    }

    public static function getInternationalJson($code_data) {
        return '{
            "shipments": [
                {
                "sourceChannel":"SHP",
                "packagesNumber": "1",
                "product": "PAAXI",
                "admissionMethod": 1,
                "deliveryMethod": "DOUAOF",
                "frankingType": "FP",
                "totalWeight": "1000",
                "contractNumber": "' . $code_data["CorreosContract"] . '",
                "clientNumber": "' . $code_data["CorreosCustomer"] . '",
                "labellerCode": "' . $code_data["CorreosKey"] . '",
                "contractIndicator": "Y",
                "packages": [
                    {
                    "packageId": "TEST CREDENTIALS",
                    "packageHeight": "1",
                    "packageWidth": "145",
                    "packageLength": "100",
                    "packageWeightGrams": "1000",
                    "clientReference": "TEST CREDENTIALS",
                    "clientReference2": "",
                    "clientReference3": "",
                    "observations": "Observciones paq 1",
                    "packageContents": {
                        "shipmentType": "2",
                        "invoiceNumber": "",
                        "licenseNumber": "",
                        "certificateNumber": "",
                        "customReferenceConsignor": "",
                        "importerTaxReference": "",
                        "importerCode": "",
                        "phoneNumber": "666777888",
                        "importerEmail": "test@mail.com",
                        "instructionsDoNotDeliver": "D",
                        "customsData": [
                        {
                        "quantity": "2",
                        "description": "Contenido de prueba",
                        "netWeight": "150",
                        "netValue": "150",
                        "tariffNumber": "490110",
                        "countryOrigin": "ESP"
                        }
                        ]
                    }
                    }
                ],
                "addressee": {
                    "name": "NOMBRE DESTINTARIO",
                    "lastName1": "APELLIDO 1 D",
                    "lastName2": "APELLIDO 2 D",
                    "doiType": "1",
                    "doiNumber": "00000000T",
                    "company": "",
                    "contactPerson": "CONTACT PERSON D",
                    "addressType": "CJ",
                    "address": "ADDRESS DESTINO",
                    "number": "25",
                    "portal": "PR",
                    "block": "BR",
                    "staircase": "ww",
                    "floor": "ss",
                    "door": "Izda",
                    "addressComplement": "direcc2 destino",
                    "locality": "LOC DESTINO",
                    "zip": "28001",
                    "country": "",
                    "contactPhone": "917775544",
                    "email": "aaa@bbb.com",
                    "language": "spa"
                },
                "sender": {
                    "name": "Nombre Remitente",
                    "lastName1": "Apellido 1 Remitente",
                    "lastName2": "Apellido 2 Remitente",
                    "doiType": "1",
                    "doiNumber": "12345678Z",
                    "company": "Empresa",
                    "contactPerson": "CONTACT SENDER",
                    "addressType": "CJ",
                    "address": "ADDRESS SENDER",
                    "number": "25",
                    "portal": "PR",
                    "block": "BR",
                    "staircase": "C",
                    "floor": "4",
                    "door": "Izda",
                    "addressComplement": "",
                    "locality": "LOC SENDER",
                    "province": "28",
                    "cp": "28037",
                    "country": "ESP",
                    "contactPhone": "666777888",
                    "email": "test@prtest.com",
                    "language": "spa"
                },
            "additionalValues": [
                ]
                }
            ]
            }
        ';
    }

	// ─── Write / query helpers ───────────────────────────────────────────────

	/**
	 * Deletes a customer code record by DB id.
	 *
	 * Replicates the behaviour of CorreosOficialCustomerDataDao::deleteCustomerCode():
	 *  1. Deletes the row from correos_oficial_codes.
	 *  2. Sets active = 0 in correos_oficial_codes_actives for the same company.
	 *  3. Deletes the row from correos_oficial_customers by customer_code.
	 *
	 * @param int|string $id  DB record id in correos_oficial_codes.
	 */
	public static function delete_code( $id ): void {
		global $wpdb;
		$id            = (int) $id;
		$codes_table   = $wpdb->prefix . 'correos_oficial_codes';
		$actives_table = $wpdb->prefix . 'correos_oficial_codes_actives';

		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$codes_table} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $record ) {
			return;
		}

		$company = $record['company'];

		$wpdb->delete( $codes_table, array( 'id' => $id ), array( '%d' ) );

		$wpdb->update(
			$actives_table,
			array( 'active' => 0 ),
			array( 'company' => $company ),
			null,
			array( '%s' )
		);
	}

	/**
	 * Returns rows from correos_oficial_codes filtered by company.
	 *
	 * @param  string      $company  e.g. 'Correos', 'CORREOS', 'CEX'.
	 * @param  string|null $fields   Raw SQL field list (e.g. '`id`,`CorreosCustomer`'); null = all columns.
	 * @return array|null            Array of associative arrays, or null when empty.
	 */
	public static function get_by_company( string $company, string $fields = null ): ?array {
		global $wpdb;
		$table     = $wpdb->prefix . 'correos_oficial_codes';
		$col_list  = $fields ?? '*';
		$prepared  = $wpdb->prepare( "SELECT {$col_list} FROM {$table} WHERE company = %s", $company );
		return $wpdb->get_results( $prepared, ARRAY_A ) ?: null;
	}
}
