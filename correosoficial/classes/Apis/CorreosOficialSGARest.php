<?php
namespace CorreosOficial\Classes\Apis;

use CorreosOficial;
use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Classes\CorreosOficialCrypto;
use Exception;

class CorreosOficialSGARest {
    // ApiMule
    private static $clientIdApiMule = '88e1a19c67b744d7bc778c37006148ad';
    private static $secretIdApiMule = '2D414ad47C0F4C93a9323660833690cD';

    private static $environment = 'PRO';

    private $urlApi;
    private $urlGetToken;
    private $urlCheckItem;

    // Constructor
    public function __construct() {
        // URLS PRE por defecto
        $this->urlGetToken  = 'https://apioauthcid.correospre.es/Api/Authorize/Token';
        $this->urlApi       = 'https://api1.correospre.es/logistics/tradeinout/api/v1';

         // URLS PRO
        if (self::$environment == 'PRO') {
            $this->urlApi      = 'https://api1.correos.es/logistics/tradeinout/api/v1';
            $this->urlGetToken = 'https://apioauthcid.correos.es/Api/Authorize/token';
        }
    }

    public function getApiBaseUrl() {
        return $this->urlApi;
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
    public function getCorreosToken($clientId, $clientSecret, $forceRefresh = false) {

        if (!session_id()) {
			session_start();
		}
        
		$return = false;

        $token = isset($_SESSION['tokenP3']) ? sanitize_text_field($_SESSION['tokenP3']) : '';

        // Checks Token (skip cache if forceRefresh requested)
		if ( !$forceRefresh && $token && !$this->isJwtExpired($token)) {
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
				// OK
				if (isset($jsonResponse['idToken'])) {
					$_SESSION['tokenP3'] = $jsonResponse['idToken'];
					$return = $jsonResponse['idToken'];
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
    public function requestRestCall($url, $data, $client, $method = 'POST', $forceTokenRefresh = false) {
        $decryptedSecret = CorreosOficialCrypto::decrypt($client['CorreosSecretID']);
        if ($decryptedSecret === false) {
            return self::setError(500, 'KO', CorreosOficialCrypto::getDecryptErrorMessage());
        }

         $headers = [
            'client_id: ' . self::$clientIdApiMule,
            'client_secret: ' . self::$secretIdApiMule,
            'Content-Type: text/plain',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getCorreosToken(
                $client['CorreosClientID'],
                $decryptedSecret,
                $forceTokenRefresh
            ),
        ];

        $json_data = [];

        if (! empty($data)) {
            $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->urlApi . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            CURLOPT_POSTFIELDS     => $json_data,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $output = curl_exec($ch);
        $error = curl_error($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
        $info = curl_getinfo($ch);
        $error_code = curl_errno($ch);
        curl_close($ch);

        if ($status_code == 201 || $status_code == 200) {
            $ret = [
                'output' => $output,
                'status' => $status_code
            ];
        } else {
            CorreosOficialHelpers::writeToLog("sga", "Error 23001 - ERROR DE CONECTIVIDAD INFO CURL: " . print_r($info, true));
            CorreosOficialHelpers::writeToLog("sga", "Error 23001 - ERROR DE CONECTIVIDAD CODIGO ERROR CURL: " . $error_code);
            CorreosOficialHelpers::writeToLog("sga", "Error 23001 - ERROR CONECTIVIDAD ERROR COMPLETO CURL: " . print_r($error, true));

            $json = json_decode($output);

            $description = 'No error description. Can\t connect to service';

            if ($json != null) {
                if (isset($json->error)) {
                    $status_code = 407;
                    $description = $json->error;
                    return self::setError($status_code, $json->status, $description);
                } else if (isset($json->code) && ( $json->code != 201 || $json->status == "KO")) {
                    $status_code = $json->code;
                    $description = $json->message;

                    $status = isset($json->status) ? $json->status : $status_code;

                    return self::setError($status_code, $status, $description);
                }

                if (isset($json->moreInformation) && $json->moreInformation != null) {
                    $description = $json->moreInformation->description;
                }

                $status_code == 0 ? '0' : $status_code;

                $ret = self::setError($status_code, $json->status, $description);
            }
        }

        return $ret;
    }

    /**
     * Envia un pedido de venta al Almacén de Correos
     * 
     * @param array $payload Contiene los datos necesarios para la llamada a la API
     * @return array Resultado de la llamada a la API
    */
    public function sendOutgoingOrder ($payload) {
        $baseUrl = '/warehouse/owners/' . urlencode($payload['ownerid']) . '/clients/' . urlencode($payload['clientid']) . '/outgoing-orders';

        return  $this->requestRestCall($baseUrl, $payload['data'], $payload['client'], 'POST');
    }

    /**
     * Solicita el stock de un producto
     * @param array $payload Contiene los datos necesarios para la llamada a la API
     * @return array Contiene la respuesta de la llamada CURL
     */
    public function getProductStockBySKU($payload, $cron_action = false, $forceTokenRefresh = false) {
        $base_url = '/warehouse/owners/' . urlencode($payload['ownerid']) . '/stock-checkitem';
        
        // Comrobar si es llamada desde cron o no para ajustar parámetros
        if (!$cron_action) {
            $data_array = [
                'warehouse' => $payload['warehouse'],
                'article'   => $payload['product_sku'],
            ];
        } else {

            $data_array = [
                'warehouse' => $payload['warehouse'],
            ];
        }

        $product_stock = $this->requestRestCall(
            $base_url . '?' . http_build_query($data_array),
            [],
            $payload['client'],
            'GET',
            $forceTokenRefresh
        );

        if ($product_stock['status'] == 200) {
            $msg = "Conectividad con servicio Sislog OK: " . $product_stock['status'];
            CorreosOficialHelpers::writeToLog("sga", $msg);

            return [
				'codigoRetorno'  => 0,
				'mensajeRetorno' => $msg,
				'output' 	     => $product_stock['output'],
				'status'         => $product_stock['status']
			];
        } else {
            // PREGUNTAR A HECTOR.
            $error = "Error 11011: Error de conectividad con servicio Sislog Status Code: " . $product_stock['status'];
            CorreosOficialHelpers::writeToLog("sga", $error);
            CorreosOficialHelpers::writeToCronErrorLog($error);
        }
        
        return [
            'codigoRetorno'  => 1,
            'mensajeRetorno' => $error,
        ];
    }

    /**
     * Consulta el estado de los pedidos enviados al Almacén de Correos
     * 
     * @param array $payload Contiene los datos necesarios para la llamada a la API
     * @return array Resultado de la llamada a la API
     */
    public function findOutgoingOrdersSituation($payload) {
        $baseUrl = '/warehouse/owners/' . urlencode($payload['ownerid']) . '/outgoingorders-situation';

        $data_array = [
            'warehouse'         => $payload['warehouse'],
            'fromOrderDate'     => $payload['fromOrderDate'],
            'toOrderDate'       => $payload['toOrderDate'],
        ];

        $outgoing_orders = $this->requestRestCall($baseUrl . '?' . http_build_query($data_array), [], $payload['client'], 'GET');

        if ($outgoing_orders['status'] == 200) {
            $msg = "Conectividad con servicio Sislog OK: " . $outgoing_orders['status'];
            CorreosOficialHelpers::writeToLog("sga", $msg);

            return [
                'codigoRetorno'  => 0,
                'mensajeRetorno' => $msg,
                'output' 	     => $outgoing_orders['output'],
                'status'         => $outgoing_orders['status']
            ];
        } else {
            $error = "Error 11011: Error de conectividad con servicio Sislog Status: " . $outgoing_orders['status'];
            CorreosOficialHelpers::writeToLog("sga", $error);
            CorreosOficialHelpers::writeToCronErrorLog($error);
        }

        return [
            'codigoRetorno'  => 1,
            'mensajeRetorno' => $error,
        ];
    }
    
    public function cancelOutgoingOrder($payload) {
        $baseUrl = '/warehouse/owners/' . urlencode($payload['ownerid']) . '/clients/' . urlencode($payload['clientid']) . '/outgoing-orders/'. urlencode($payload['id_order']) 
            . '?orderType=PS&warehouse=' . urlencode($payload['warehouse']);

        $outgoinOrderResponse = $this->requestRestCall($baseUrl, [], $payload['client'], 'DELETE');

        if (
            (isset($outgoinOrderResponse['code']) && $outgoinOrderResponse['code'] == 200) ||
            (isset($outgoinOrderResponse['status']) && ($outgoinOrderResponse['status'] == 200 || $outgoinOrderResponse['status'] == 201))
        ) {

            $status = isset($outgoinOrderResponse['code']) ? $outgoinOrderResponse['code'] : $outgoinOrderResponse['status'];

            return [
                'codigoRetorno'  => 0,
                'output' 	     => $outgoinOrderResponse['output'],
                'status'         => $status
            ];
        }else {
            $error = "Petición para cancelar envío de pedido: " . $payload['id_order'] . " - " . gmdate('Y-m-d H:i:s');
            $error .= "Error 11011: Error de conectividad con servicio Sislog Status Code: " . $outgoinOrderResponse['status'];

            CorreosOficialHelpers::writeToLog("sga", $error);
            CorreosOficialHelpers::writeToCronErrorLog($error);
        }
        
        return [
            'codigoRetorno'  => 1,
            'mensajeRetorno' => $error,
        ];
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

    public static function setError($statusCode, $status, $description) {
        $result = array(
            'status_code' => $statusCode == 0 ? '0' : $statusCode,
            'status'      => $status,
            'returnMessage' => $description
        );

        $outJson = json_encode($result);

        return [
            'output' => $outJson,
            'status' => $statusCode
        ];
    }
}