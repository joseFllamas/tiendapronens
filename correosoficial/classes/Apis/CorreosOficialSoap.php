<?php
namespace CorreosOficial\Classes\Apis;

use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Classes\CorreosOficialCrypto;
use DateTime;
use SoapClient;
use SoapFault;
use SoapVar;

class CorreosOficialSoap {


	private static $environment = 'PRO';

	/* *********************************************************************************************************
	 * CLIENTES SOAPS
	 ********************************************************************************************************* */

	/**
	 * Establece la conexión con un recurso SOAP de PreRegistro.
	 *
	 * @return SoapClient Devuelve un recurso objeto cliente soap
	 */
	public function soapClientPreRegistro( $correos_code ) {
		$location = 'https://preregistroenviospre.correos.es/preregistroenvios';
		if (self::$environment == 'PRO') {
			$location = 'https://preregistroenvios.correos.es/preregistroenvios';
		}

		$decryptedPassword = CorreosOficialCrypto::decrypt($correos_code['CorreosPassword']);
		if ($decryptedPassword === false) {
			return null;
		}

		return new SoapClient(
			realpath(__DIR__ . '/wsdl/preregistro.wsdl'),
			array(
				'login' => $correos_code['CorreosUser'],
				'password' => stripslashes($decryptedPassword),
				'exceptions' => true,
				'trace' => true,
				'connection_timeout' => 10,
				'location' => $location,
				'cache_wsdl' => WSDL_CACHE_NONE,
			)
		);
	}

	/**
	 * Establece la conexión con un recurso SOAP de Recogida.
	 *
	 * @return SoapClient Devuelve un recurso objeto cliente soap
	 */
	public function soapClientRecogida( $correos_code ) {
		$location = 'https://serviciorecogidaspre.correos.es:20189/serviciorecogidas';
		if (self::$environment == 'PRO') {
			$location = 'https://serviciorecogidas.correos.es/serviciorecogidas';
		}

		$decryptedPassword = CorreosOficialCrypto::decrypt($correos_code['CorreosPassword']);
		if ($decryptedPassword === false) {
			return null;
		}

		return new SoapClient(
			$location . '?wsdl',
			array(
				'login' => $correos_code['CorreosUser'],
				'password' => stripslashes($decryptedPassword),
				'exceptions' => true,
				'trace' => true,
				'connection_timeout' => 10,
				'location' => $location,
				'cache_wsdl' => WSDL_CACHE_NONE,
				'soap_version' => SOAP_1_1,
				'namespaces' => array(
					'ns1' => 'http://www.correos.es/ServicioPuertaAPuerta',
					'ns2' => 'http://www.correos.es/ServicioPuertaAPuertaBackOffice',
				),
			)
		);
	}

	/**
	 * Establece la conexión con un recurso SOAP de Localizador oficinas.
	 *
	 * @return SoapClient Devuelve un recurso objeto cliente soap
	 */
	public function soapClientLocalizadorOficinas( $correos_code ) { 
		$location = 'http://localizadoroficinaspre.correos.es/localizadoroficinas';
		if (self::$environment == 'PRO') {
			$location = 'http://localizadoroficinas.correos.es/localizadoroficinas';
		}

		$decryptedPassword = CorreosOficialCrypto::decrypt($correos_code['CorreosPassword']);
		if ($decryptedPassword === false) {
			return null;
		}

		return new SoapClient(
			$location . '?wsdl',
			array(
				'login' => $correos_code['CorreosUser'],
				'password' => stripslashes($decryptedPassword),
				'exceptions' => true,
				'trace' => true,
				'connection_timeout' => 10,
				'location' => $location,
				'cache_wsdl' => WSDL_CACHE_NONE,
			)
		);
	}

	/**
     * Comprueba las credenciales de Correos con una llamada al servicio de Prereregistro
     * operacion DocumentacionAduaneraOp
     * @return boolean resultado de la llamada.
     */
    public function altaClienteCorreosOpCall( $payload, $origin = "order" )
    {
		// Campos obligatorios pero no utilizados en la llamada
		$payload['shipping_number'] = '';
		$payload['print_option'] = '';
		$payload['customer_iso'] = '';
		$payload['sender_name'] = '';
		$payload['total_buks'] = '';

		$soapClient = $this->soapClientPreRegistro($payload['client']);
		$getDocAduaneraOptions = self::optionsGetDocAduanera($payload);

		try {
			$soapClient->DocumentacionAduaneraOp($getDocAduaneraOptions);
			$headers = $soapClient->__getLastResponseHeaders();
			if(substr($headers, 9, 3) == "200"){
				return true;
			}
		} catch (SoapFault $e) {
			return false;
		}
		return false;
    }

	/* *********************************************************************************************************
	 * PREREGISTRO PEDIDO
	 ********************************************************************************************************* */
	public function registrarEnvio( $payload, $origin = 'order' ) {
		// Flags
		$multibulto = $payload['bultos'] == 1 ? false : true;

		try {
			// Instancia cliente SOAP
			$soapClient = $this->soapClientPreRegistro($payload['client']);

			// Creamos Options según datos del envío
			$registrarEnvioOptions = self::optionsRegistrarEnvio($payload, $origin);

			// Petición Soap PreRegistro / PreRegistroMultibulto
			$operation = $multibulto ? 'PreRegistroMultibulto' : 'PreRegistro';
			$registrarEnvioResponse = $soapClient->$operation($registrarEnvioOptions);

			// Errores devueltos por el WS (Resultado es 1)
			if ($registrarEnvioResponse->Resultado) {
				$bultosError = $multibulto ? $registrarEnvioResponse->BultosError->BultoError : array( $registrarEnvioResponse->BultoError );
				$orderId = $payload['order_id'];
				$reference = $payload['order_form']['order_reference'];
				return array_map(function ( $bultoError ) use ( $orderId, $reference ) {
					return array(
						'codigoRetorno'  => 1,
						'mensajeRetorno' => $bultoError->DescError,
						'numBulto'       => $bultoError->NumBulto,
						'orderId'        => $orderId,
						'reference'      => $reference,
					);
				}, (array) $bultosError);
			}

			// Respuesta Bultos
			$listaBultos = $multibulto ? $registrarEnvioResponse->Bultos->Bulto : array( $registrarEnvioResponse->Bulto );

			// RECOGIDAS ------------------------------------------------------------------------------------------ //
			if ($payload['needPickup'] === 'S') {
				// Añadimos códigos de envío de los bultos al payload (para IndImprimirEtiquetas si tiene valor S)
				foreach ($listaBultos as $bulto) {
					$payload['shipping_numbers'][] = $bulto->CodEnvio;
				}
				$responsePickup = $this->registrarRecogida($payload);
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'  => $registrarEnvioResponse->Resultado,
				'mensajeRetorno' => '',
				'exp_number'     => $registrarEnvioResponse->CodExpedicion,
				'bultos'         => array_map(fn( $bulto ) => array(
					'numBulto'        => (int) $bulto->NumBulto,
					'shipping_number' => $bulto->CodEnvio,
				), $listaBultos),
				'pickup'         => isset($responsePickup) ? $responsePickup : array(),
			);

		} catch (SoapFault $e) {
			$errores[] = array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
			return $errores;
		}
	}

	/* *********************************************************************************************************
	 * CANCELAR ENVIO
	 ********************************************************************************************************* */
	public function cancelarEnvio( $payload ) {
		try {
			// Instancia cliente SOAP
			$soapClient = $this->soapClientPreRegistro($payload['client']);

			// Creamos Options según datos del envío
			$cancelarEnvioOptions = array(
				'IdiomaErrores'  => $payload['lang'],
				'codCertificado' => $payload['bulto']->get_shipping_number(),
			);

			// Creamos Options según datos del envío
			$cancelarEnvioResponse = $soapClient->AnularOp($cancelarEnvioOptions);

			// Errores devueltos por el WS (Resultado es 1)
			if ($cancelarEnvioResponse->Resultado) {
				throw new SoapFault('Server', isset($cancelarEnvioResponse->ErroresValidacion->ErrorVal->DescError) ? 
				$cancelarEnvioResponse->ErroresValidacion->ErrorVal->DescError : __('Unknown error cancelling shipping', 'correosoficial'));
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'  => $cancelarEnvioResponse->Resultado,
				'mensajeRetorno' => '',
			);

		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}

	/* *********************************************************************************************************
	 * REGISTRAR RECOGIDA
	 ********************************************************************************************************* */
	public function registrarRecogida( $payload ) {

		$nsSer = 'http://www.correos.es/ServicioPuertaAPuertaBackOffice';

		try {
			// Instancia cliente SOAP
			$soapClient = $this->soapClientRecogida($payload['client']);

			
			// Creamos Options según datos del recogida
			$soapOptions = new SoapVar(self::optionsRegistrarRecogida($payload), SOAP_ENC_OBJECT, 'SolicitudRegistroRecogida', $nsSer);
			
			// Lanzamos Consulta
			$soapResponse = $soapClient->__soapCall('SolicitudRegistroRecogida', array( $soapOptions ));

			// Errores devueltos por el WS
			if (!empty($soapResponse->RespuestaSolicitudRegistroRecogida->CodigoError)) {
				throw new SoapFault('Server', isset($soapResponse->RespuestaSolicitudRegistroRecogida->DescripcionError) ? 
				$soapResponse->RespuestaSolicitudRegistroRecogida->DescripcionError : __('Unknown error generating package/s pickup', 'correosoficial'));
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'  => 0,
				'mensajeRetorno' => '',
				'codRecogida'    => $soapResponse->RespuestaSolicitudRegistroRecogida->CodSolicitud,
			);

		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}

	/* *********************************************************************************************************
	* CANCELAR RECOGIDA
	********************************************************************************************************* */
	
	public function cancelarRecogida( $payload ) {
		try {
			$soapClient = $this->soapClientRecogida($payload['client']);

			// Creamos Options según datos para cancelar la recogida
			$cancelarRecogidaOptions = array(
				'FechaOperacion'     => gmdate('d-m-Y H:i:s'),
				'NumContrato'        => $payload['client']['CorreosContract'],
				'NumDetallable'      => $payload['client']['CorreosCustomer'],
				'CodUsuario'         => $payload['client']['CorreosOv2Code'],
				'CodSolicitud'       => !empty($payload['pickup_number']) ? $payload['pickup_number'] : $payload['pickup_number_return'],
				'ReferenciaRecogida' => $payload['order_reference'],
			);

			// Lanzamos Consulta
			$cancelarRecogidaResponse = $soapClient->__soapCall('AnulacionRecogidaPaP', array( $cancelarRecogidaOptions ));

			// Errores devueltos por el WS
			if (!empty($cancelarRecogidaResponse->AnulacionRecogidaPaPResult->CodigoResultado != 0)) {
				throw new SoapFault('Server', isset($cancelarRecogidaResponse->AnulacionRecogidaPaPResult->DetalleResultado) ? 
				$cancelarRecogidaResponse->AnulacionRecogidaPaPResult->DetalleResultado : __('Unknown error generating package/s pickup', 'correosoficial'));
			} 

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'  => 0,
				'mensajeRetorno' => $cancelarRecogidaResponse->AnulacionRecogidaPaPResult->DetalleResultado,
				'codRecogida'    => $cancelarRecogidaResponse->AnulacionRecogidaPaPResult->CodigoSRE,
			);


		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}


	/* *********************************************************************************************************
	* REGISTRAR DEVOLUCION
	********************************************************************************************************* */
	public function generateReturn( $payload ) {
		try {
			// Flags
			$multibulto = $payload['bultos'] == 1 ? false : true;
			$soapClient = $this->soapClientPreRegistro($payload['client']);

			
			$registrarDevolucionOptions = self::optionsRegistrarDevolucion($payload);

			$operation = $multibulto ? 'PreRegistroMultibulto' : 'PreRegistro';
			$registrarDevolucionResponse = $soapClient->$operation($registrarDevolucionOptions);


			if ($registrarDevolucionResponse->Resultado) {
				$bultosError = $multibulto ? $registrarDevolucionResponse->BultosError->BultoError : array( $registrarDevolucionResponse->BultoError );
				return array_map(function ( $bultoError ) {
					return array(
						'codigoRetorno'  => 1,
						'mensajeRetorno' => $bultoError->DescError,
						'numBulto'       => $bultoError->NumBulto,
					);
				}, (array) $bultosError);
			}

			// Respuesta Bultos
			$listaBultos = $multibulto ? $registrarDevolucionResponse->Bultos->Bulto : array( $registrarDevolucionResponse->Bulto );

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'  => $registrarDevolucionResponse->Resultado,
				'mensajeRetorno' => '',
				'exp_number'     => $registrarDevolucionResponse->CodExpedicion,
				'bultos'         => array_map(fn( $bulto ) => array(
					'numBulto'        => (int) $bulto->NumBulto,
					'shipping_number' => $bulto->CodEnvio,
				), $listaBultos),
				'pickup'         => isset($responsePickup) ? $responsePickup : array(),
			);

		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}

	/* *********************************************************************************************************
	* IMPRESION ETIQUETA
	********************************************************************************************************* */
	public function imprimirEtiqueta( $payload ) {
		try {
			// Instancia cliente SOAP
			$soapClient = $this->soapClientPreRegistro($payload['client']);

			// Creamos Options según datos del envío
			$imprimirEtiquetaOptions = array(
				'FechaOperacion' => gmdate('d-m-Y H:i:s'),
				'CodEnvio'       => $payload['bulto']->get_shipping_number(),
				'CodEtiquetador' => $payload['client']['CorreosKey'],
				'Care'           => '000000',
				'ModDevEtiqueta' => 2, // XML (1), PDF (2) or ZPL (3)
			);

			// Creamos Options según datos del envío SolicitudEtiquetaOp
			$imprimirEtiquetaResponse = $soapClient->SolicitudEtiquetaOp($imprimirEtiquetaOptions);

			// Errores devueltos por el WS (Resultado es 1)
			if ($imprimirEtiquetaResponse->Resultado) {
				throw new SoapFault('Server', 'No ha sido posible obtener la etiqueta.');
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'   => $imprimirEtiquetaResponse->Resultado,
				'mensajeRetorno'  => '',
				'label'           => $imprimirEtiquetaResponse->Bulto->Etiqueta->Etiqueta_pdf->Fichero,
			);

		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
				'orderId'        => $payload['order_id'],
				'reference'      => $payload['reference'],
			);
		}
	}

	/* *********************************************************************************************************
	 * DOCUMENTACION ADUANAS
	 ********************************************************************************************************* */

	public function getDocAduanera( $payload ) {
		try {
			// Instancia cliente SOAP
			$soapClient = $this->soapClientPreRegistro($payload['client']);

			//Creamos options según datos del envío
			$getDocAduaneraOptions = $this->optionsGetDocAduanera($payload);

			if ($payload['print_option'] == 'IMPRIMIRCN23BUTTON' || $payload['print_option'] == 'IMPRIMIRCN23BUTTON-RETURN') {
				$getDocAduaneraResponse = $soapClient->DocumentacionAduaneraCN23CP71Op($getDocAduaneraOptions);
			} else {
				$getDocAduaneraResponse = $soapClient->DocumentacionAduaneraOp($getDocAduaneraOptions);
			}

			// Errores devueltos por el WS (Resultado es 1)
			if ($getDocAduaneraResponse->Resultado) {
				throw new SoapFault('Server', $getDocAduaneraResponse->MotivoError);
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'   => $getDocAduaneraResponse->Resultado,
				'mensajeRetorno'  => '',
				'label'           => $getDocAduaneraResponse->Fichero,
			);

		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}

	/* *********************************************************************************************************
	 * GET PICKUP LOCATIONS
	 ********************************************************************************************************* */

	public function getPickupLocations( $payload ) {
		try {

			$locations = array();

			// Instancia cliente SOAP
			$soapClient = $this->soapClientLocalizadorOficinas($payload['client']);

			$getPickupLocationsOptions = array(
				'codigoPostal' => $payload['postcode'],
			);

			if ($payload['selector_type'] == 'citypaq') {
				$getPickupLocationsResponse = $soapClient->homePaqConsultaPorCP1($getPickupLocationsOptions);
			} else {
				$getPickupLocationsResponse = $soapClient->procesaLocalizador($getPickupLocationsOptions);
			}

			if (isset($getPickupLocationsResponse->Resultado) && $getPickupLocationsResponse->Resultado != 0) {
				throw new SoapFault('Server', $getPickupLocationsResponse->MotivoError);
			}

			if(isset($getPickupLocationsResponse->arrayOficina)){
				$locations = $getPickupLocationsResponse->arrayOficina;
			}

			if(isset($getPickupLocationsResponse->listaHomePaq->homePaq)){
				$locations = $getPickupLocationsResponse->listaHomePaq->homePaq;
			}

			if(count($locations)){
				foreach ($locations as &$item) {
					$item = json_decode(json_encode($item), true);
				}
				unset($item); // buena práctica al usar referencias
			}else{
				throw new SoapFault('Server', 'No locations found for the given postal code.');
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'   => 0,
				'mensajeRetorno'  => '',
				'locations' => $locations,
			);
		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}

	public function ConsultaSRE( $payload ) {
		try {
			// Instancia cliente SOAP
			$soapClient = $this->soapClientRecogida($payload['client']);

			$nsSer1 = 'http://www.correos.es/ServicioPuertaAPuerta';

			$Identificacion = array (
					'NumContrato'   => $payload['client']['CorreosContract'],
					'NumDetallable' => $payload['client']['CorreosCustomer'],
					'CodUsuario'    => $payload['client']['CorreosOv2Code'],
					'TipoOperacion' => 'CONSULTA',
					'ModoOperacion' => $payload['ModoOperacion']
			);

			$CriterioConsulta = array (
				'CodigoSRE'     => $payload['CodigoSRE'],
				'ReferenciaRecogida' => ''
			);

			$getPickupStatus = array();

			$getPickupStatus['Identificacion'] = new SoapVar($Identificacion, SOAP_ENC_OBJECT, null, null, 'Identificacion', $nsSer1);
			$getPickupStatus['CriterioConsulta'] = new SoapVar($CriterioConsulta, SOAP_ENC_OBJECT, null, null, 'CriterioConsulta', $nsSer1);

			$getPickupStatusResponse = $soapClient->ConsultaSRE($getPickupStatus);
			
			if ($getPickupStatusResponse->CodigoResultado != '0') {
				throw new SoapFault('Server', $getPickupStatusResponse->DetalleResultado);
			}

			// RETURN ESTANDARIZADO
			return array(
				'codigoRetorno'   => 0,
				'mensajeRetorno'  => '',
				'data' => $getPickupStatusResponse->RespuestaSolicitudConsultaRecogidaEsporadica
					->ListaRespuestaCodigoRecogidaEsporadica->RespuestaCodigoRecogidaEsporadica
			);
		} catch (SoapFault $e) {
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => $e->getMessage(),
			);
		}
	}

	public static function optionsGetDocAduanera( $payload ) {

		$getDocAduaneraOptions = array(
			'codCertificado' => $payload['shipping_number'],
		);

		if ($payload['print_option'] != 'IMPRIMIRCN23BUTTON' || ($payload['print_option'] != 'IMPRIMIRCN23BUTTON' && $payload['type'] == 'return')) {

			if ($payload['print_option'] == 'IMPRIMIRDUABUTTON') {
				$option = 'DCAF';
			} else {
				$option = 'DDP';
			}

			if ($payload['customer_iso'] == 'ES') {
				$postal_code = substr($payload['postal_code'], 0, 2);
			}

			$getDocAduaneraOptions = array(
				'TipoESAD' => $option,
				'NumContrato' => $payload['client']['CorreosCustomer'],
				'NumCliente' => $payload['client']['CorreosContract'],
				'CodEtiquetador' => $payload['client']['CorreosKey'],
				'Provincia' => !empty($postal_code) ? $postal_code : '', // codigos postales (ej st cruz de tenerife -> 38)
				'PaisDestino' => $payload['customer_iso'],
				'NombreDestinatario' => $payload['adressed_name'],
				'NumeroEnvios' => $payload['total_buks'],
			);
		}

		return $getDocAduaneraOptions;
	}

	/**
	 * Genera el dataset de options que se le mandará a la operation
	 * de RegistrarRecogida del Soap
	 *
	 * @return array Devuelve un array con las options
	 */
	public static function optionsRegistrarRecogida( $payload ) {
		// Sacamos el índice order_form a una variable para simplificar código
		$orderForm = $payload['order_form'];

		$nsSer1 = 'http://www.correos.es/ServicioPuertaAPuerta';

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

		$recogida = array(
			'ReferenciaRecogida'      => isset($orderForm['order_reference']) ? $orderForm['order_reference'] : $payload['order_id'],
			'FecRecogida'             => gmdate('d/m/Y', strtotime($payload['pickupDateRegister'])),
			'HoraRecogida'            => gmdate('H:i', strtotime($payload['pickupFromRegister'])),
			'CodAnexo'                => '091',
			'NomNombreViaRec'         => $orderForm['sender_address'],
			'NomLocalidadRec'         => $orderForm['sender_city'],
			'CodigoPostalRecogida'    => $orderForm['sender_cp'],
			'DesPersonaContactoRec'   => $orderForm['sender_name'],
			'DesTelefContactoRec'     => $orderForm['sender_phone'],
			'DesEmailContactoRec'     => $orderForm['sender_email'],
			'DesObservacionRec'       => '',
			'NumEnvios'               => $payload['bultos'],
			'NumPeso'                 => $numpeso,
			'TipoPesoVol'             => $payload['packetSize'],
			'IndImprimirEtiquetas'    => $payload['needPrintLablPickup'],
			'IndDevolverCodSolicitud' => 'S',
		);

		// Si tenemos que imprimir las etiquetas, pasámos los Códigos de envío
		if ($payload['needPrintLablPickup'] == 'S') {
			$listacodenvios = array();
			foreach ($payload['shipping_numbers'] as $CodEnvio) {
				$listacodenvios[] = new SoapVar($CodEnvio, XSD_STRING, null, null, 'CodigoEnvio');
			}
			$recogida['ListaCodEnvios'] = new SoapVar($listacodenvios, SOAP_ENC_OBJECT, null, null, 'ListaCodEnvios', $nsSer1);
		}

		return array(
			'ReferenciaRelacionPaP' => 1,
			'TipoOperacion'         => 'ALTA',
			'FechaOperacion'        => ( new DateTime() )->format('d-m-Y H:i:s'),
			'NumContrato'           => $payload['client']['CorreosContract'],
			'NumDetallable'         => $payload['client']['CorreosCustomer'],
			'CodSistema'            => '',
			'CodUsuario'            => $payload['client']['CorreosOv2Code'],
			'Recogida'              => new SoapVar($recogida, SOAP_ENC_OBJECT, null, null, 'Recogida', $nsSer1),
		);
	}
	
	/**
	 * Genera el dataset de options que se le mandará a la operation
	 * de RegistrarEnvio del Soap
	 *
	 * @return array Devuelve un array con las options
	 */
	public static function optionsRegistrarEnvio( $payload, $origin ) {
		// Sacamos el índice order_form a una variable para simplificar código
		$orderForm = $payload['order_form'];

		// Texto alternativo en sender_name
		$labelAlternativeText = CorreosOficialConfig::getLabelAlternativeText();
		if ($labelAlternativeText) {
			$orderForm['sender_name'] = $labelAlternativeText;
		}

		// Identificación Remitente
		if (!$labelAlternativeText && !empty($orderForm['sender_contact'])) {
			$identRemitente = array(
				'Empresa'         => $orderForm['sender_name'],
				'PersonaContacto' => $orderForm['sender_contact'],
			);
		} else {
			$identRemitente = array(
				'Nombre'    => $orderForm['sender_name'],
				'Apellido1' => '',
				'Apellido2' => '',
			);
		}
		$identRemitente['Nif'] = $orderForm['sender_nif_cif'];

		// Identificación Destinatario
		// Si hay empresa, se usa p. contacto
		if ( !empty($orderForm['customer_company']) ) {

			$identDestinatario = array(
				'Empresa'   => $orderForm['customer_company'],
			);

			$identDestinatario['PersonaContacto'] = trim($orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname']);
		} else {
			// Si no hay empresa, se usa nombre y apellidos
			$identDestinatario = array(
				'Nombre'    => $orderForm['customer_firstname'],
				'Apellido1' => $orderForm['customer_lastname'],
				'Apellido2' => '',
			);
		}

		$identDestinatario['Nif'] = $orderForm['customer_dni'];

		/**
		 * REGLA DE NEGOCIO CP REMITENTE
		 * Si el remitente es de Portugal, solo se deveuelven los 4 primeros dígitos
		 */
		if ($orderForm['sender_country'] == 'PT') {
			$orderForm['sender_cp'] = substr($orderForm['sender_cp'], 0, 4);
		}

		/**
		 * REGLA DE NEGOCIO CP/ZIP DESTINATARIO
		 * ES y AD usan CP, resto usan ZIP
		 */
		if ($orderForm['customer_country'] != 'ES' && $orderForm['customer_country'] != 'AD') {
			$delivery_postcode = '';
			$delivery_zip = $orderForm['customer_cp'];
		} else {
			$delivery_postcode = $orderForm['customer_cp'];
			$delivery_zip = '';
		}

		/**
		 * REGLA DE NEGOCIO PHOME DESTINATARIO
		 * Más info dentro de CorreosOficialHelpers
		 */
		$phone_mobile_sms = CorreosOficialHelpers::getMobilePhone(
			$orderForm['customer_phone'],
			$orderForm['customer_country'],
			$orderForm['input_select_carrier']
		);

		$soapOptions = array(
			'FechaOperacion' => gmdate('d-m-Y H:i:s'),
			'CodEtiquetador' => $payload['client']['CorreosKey'],
			'ModDevEtiqueta' => 2,
			'TotalBultos'    => $payload['bultos'],
			'CanalOrigen'    => $payload['source_channel'],
			'Care'           => '000000',
			'Remitente'      => array(
				'Identificacion' => $identRemitente,
				'DatosDireccion' => array(
					'Direccion'  => $orderForm['sender_address'],
					'Localidad'  => $orderForm['sender_city'],
				),
				'CP'               => $orderForm['sender_cp'],
				'Telefonocontacto' => $orderForm['sender_phone'],
				'Email'            => $orderForm['sender_email'],
				'DatosSMS'   => array(
					'NumeroSMS'    => $orderForm['sender_phone'],
					'Idioma'       => $orderForm['sender_phone'] ? 1 : '',
				),
			),
			'Destinatario'      => array(
				'Identificacion' => $identDestinatario,
				'DatosDireccion' => array(
					'Direccion' => $orderForm['customer_address'],
					'Localidad' => $orderForm['customer_city'],
					'Provincia' => '',
				),
				'DatosDireccion2'  => '',
				'CP'               => $delivery_postcode,
				'ZIP'              => $delivery_zip,
				'Pais'             => $orderForm['customer_country'],
				'Telefonocontacto' => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['customer_phone']),
				'Email'            => $orderForm['customer_email'],
				'DatosSMS'   => array(
					'NumeroSMS'    => $phone_mobile_sms,
					'Idioma'       => 1,
				),
			),
		);

		// ENVIO/S

		// Generación de envíos según número de bultos
		$envios = array();
		foreach ($payload['info_bulto'] as $bultoNum => $bultoInfo) {

			// ENVIO
			$envios[$bultoNum] = array(
				'ReferenciaCliente' => $bultoInfo['reference'],
				'ReferenciaCliente3'=> 'MODULO_WC_' . get_option('woocommerce_version') . '/' . CORREOS_OFICIAL_VERSION,
				'InstruccionesDevolucion' => 'D',
				'TipoModificacion' => CorreosOficialConfig::getConfigValue('CorreosModify') ?: '1',
			);

			$labelObservations = (new CorreosOficialConfig('LabelObservations'))->get_value();

			// Observaciones cliente
			if ($labelObservations == 'on') {
				$envios[$bultoNum]['Observaciones1'] = substr($orderForm['observations'], 0, 40);
				$envios[$bultoNum]['Observaciones2'] = substr($orderForm['observations'], 40, 80);
			}

			// Si es multibulto añadimos NumBulto
			if ($payload['bultos'] > 1) {
				$envios[$bultoNum]['NumBulto'] = $bultoNum;
			}

			// PESOS ENVIO
			// Reglas de Pesos
			if($payload['bultos'] == 1 && $orderForm['all_packages_equal'] == 0){
				$bultoWeight = $orderForm["total_weight"];
			}elseif ($payload['bultos'] > 1 && $orderForm['all_packages_equal'] == 0){
				$bultoWeight =  $bultoInfo['weight'];
			}elseif ($orderForm['all_packages_equal'] == 1){
				$bultoWeight =  ( $orderForm["total_weight"] / $payload['bultos'] );
			}

			$envios[$bultoNum]['Pesos']['Peso'][] = array(
				'TipoPeso' => 'R',
				'Valor'    => CorreosOficialHelpers::getFloatValue($bultoWeight) * 1000,
			);

			if (CorreosOficialHelpers::hasSize(
				$bultoInfo['large'],
				$bultoInfo['width'],
				$bultoInfo['height']
			)) {
				$envios[$bultoNum]['Pesos']['Peso'][] = array(
					'TipoPeso' => 'V',
					'Valor'    => CorreosOficialHelpers::calculateVWeight(
						$bultoInfo['large'],
						$bultoInfo['width'],
						$bultoInfo['height']
					),
				);
				
				// Agregar dimensiones
				$envios[$bultoNum]['Alto']  = $bultoInfo['large'];
				$envios[$bultoNum]['Largo'] = $bultoInfo['width'];
				$envios[$bultoNum]['Ancho'] = $bultoInfo['height'];
			}

			// ADUANAS ENVIO
			if ($orderForm['require_customs_doc'] == 1) {
				$envios[$bultoNum]['Aduana'] = array(
					'TipoEnvio'            => 2,
					'EnvioComercial'       => 'S',
					'FacturaSuperiora500'  => 'N',
					'DUAConCorreos'        => 'N',
					'RefAduaneraExpedidor' => $orderForm['custom_ref_exp'],
				);

				foreach ($payload['customs_desc_array'][$bultoNum] as $desc) {

					$ValornetoCents = (int) round(((float) $desc['valor_neto']) * 100);

					$weight = ( isset($desc['weight']) && floatval($desc['weight']) > 0 ) ? $desc['weight'] 
					: '0'; // En gramos - No aplicar peso por defecto en envíos con aduanas

					$datosAduana = array(
						'Cantidad' => $desc['unidades'],
						'Descripcion' => $desc['descripcion_aduanera'],
						'Pesoneto' => $weight,
						'Valorneto' => (string) $ValornetoCents,
					);

					if(!empty($desc['numero_tarifario'])) {
						$datosAduana['NTarifario'] = $desc['numero_tarifario'];
					}

					if(!empty($desc['origin_country'])) {
						$datosAduana['PaisOrigen'] = $desc['origin_country'];
					}

					// Añadimos DatosAduana
					$envios[$bultoNum]['Aduana']['DescAduanera']['DATOSADUANA'][] = $datosAduana;
				}
			}
		}

		// Dentro de envio en monobulto, fuera en multibulto
		$extra = array(
			'TipoFranqueo'      => 'FP',
			'ValoresAnadidos'   => array(
				'TextoAdicional'      => $payload['info_bulto'][1]['observations'],
				'EntregaconRecogida'  => 'N',
				'IndImprimirEtiqueta' => 'N',
			),
		);

		// MODALIDAD ENTREGA
		switch ($payload['delivery_mode']) {
			case 'homedelivery':
			case 'international':
				$extra['ModalidadEntrega'] = 'ST';
				break;
			case 'office':
				$extra['ModalidadEntrega'] = 'LS';
				$extra['OficinaElegida'] = $orderForm['cod_office'];
				break;
			case 'citypaq':
				$extra['ModalidadEntrega'] = 'CP';
				$extra['CodigoHomepaq'] = $orderForm['cod_homepaq'];
				break;
			default:
				$extra['ModalidadEntrega'] = 'ST';
				break;
		}

		// VALORES AÑADIDOS

		// Seguro
		if (!empty($payload['added_values']) && $payload['added_values']['insurance'] == "true") {
			$insuranceValue = CorreosOficialHelpers::getFloatValue($orderForm['insurance_value']) * 100;
			$extra['ValoresAnadidos']['ImporteSeguro'] = $insuranceValue;
		}

		// Contrareembolso
		if ($payload['added_values']['cash_on_delivery'] == "true") {
			$extra['ValoresAnadidos']['Reembolso']['TipoReembolso'] = 'RC';

			$cashValue = CorreosOficialHelpers::getFloatValue($payload['added_values']['cash_on_delivery_value']) * 100;
			$extra['ValoresAnadidos']['Reembolso']['Importe'] = $cashValue;

			if (
				empty($payload['added_values']['cash_on_delivery_iban']) || 
				substr($payload['added_values']['cash_on_delivery_iban'], 0, 4) == '****'
			) {
				$bank_acc_number = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
				$decryptedIban = CorreosOficialCrypto::decrypt($bank_acc_number);
				$payload['added_values']['cash_on_delivery_iban'] = $decryptedIban !== false ? $decryptedIban : '';
			}
			
			$extra['ValoresAnadidos']['Reembolso']['NumeroCuenta'] = $payload['added_values']['cash_on_delivery_iban'];
		}

		// MERGEAMOS SEGUN MONOBULTO O MULTIBULTO
		if ($payload['bultos'] == 1) {
			$soapOptions['Envio'] = array_merge($envios[1], $extra);
			$soapOptions['Envio']['CodProducto'] = isset($orderForm['input_select_carrier']) ? $orderForm['input_select_carrier']: $payload['product']['codigoProducto'];
		} else {
			$soapOptions['Envios']['Envio'] = array_values($envios);
			$soapOptions['CodProducto'] = isset($orderForm['input_select_carrier']) ? $orderForm['input_select_carrier']: $payload['product']['codigoProducto'];
			$soapOptions['EntregaParcial'] = $orderForm['partial_delivery'] == 0 ? 'N' : 'S';
			$soapOptions['ReferenciaExpedicion'] = $orderForm['order_reference'];
			$soapOptions = array_merge($soapOptions, $extra);
		}
		
		// DEVOLVEMOS
		return $soapOptions;
	}

	public static function optionsRegistrarDevolucion( $payload ) {
		$order_form = $payload['order_form'];

		// Texto alternativo en sender_name
		$labelAlternativeText = CorreosOficialConfig::getLabelAlternativeText();
		if ($labelAlternativeText) {
			$order_form['sender_name'] = $labelAlternativeText;
		}

		// Identificación Remitente
		if (!empty($order_form['customer_company'])) {
			$identRemitente = array(
				'Empresa' => $order_form['customer_company'],
			);
			$identRemitente['PersonaContacto'] = trim($order_form['customer_firstname'] . ' ' . $order_form['customer_lastname']);
		} else {
			$identRemitente = array(
				'Nombre'    => $order_form['customer_firstname'],
				'Apellido1' => $order_form['customer_lastname'],
				'Apellido2' => '',
			);
		}

		$identRemitente['Nif'] = $order_form['customer_dni'];

		// Identificación Destinatario
		if (!$labelAlternativeText && !empty($order_form['sender_contact'])) {
			$identDestinatario = array(
				'Empresa'         => $order_form['sender_name'],
				'PersonaContacto' => $order_form['sender_contact'],
			);

		} else {
			$identDestinatario = array(
				'Nombre'    => $order_form['sender_name'],
				'Apellido1' => '',
				'Apellido2' => '',
			);
		}

		$identDestinatario['Nif'] = $order_form['sender_nif_cif'];


		/**
		 * REGLA DE NEGOCIO CP REMITENTE
		 * Si el remitente es de Portugal, solo se deveuelven los 4 primeros dígitos
		 */
		if ($order_form['sender_country'] == 'PT') {
			$order_form['sender_cp'] = substr($order_form['sender_cp'], 0, 4);
		}
		
		/**
		 * REGLA DE NEGOCIO CP/ZIP DESTINATARIO
		 * ES y AD usan CP, resto usan ZIP
		 */
		if ($order_form['sender_country'] != 'ES' && $order_form['sender_country'] != 'AD') {
			$delivery_postcode = '';
			$delivery_zip = $order_form['sender_cp'];
		} else {
			$delivery_postcode = $order_form['sender_cp'];
			$delivery_zip = '';
		}

		/**
		 * REGLA DE NEGOCIO PHOME DESTINATARIO
		 * Más info dentro de CorreosOficialHelpers
		 */
		$phone_mobile_sms = CorreosOficialHelpers::getMobilePhone(
			$order_form['sender_phone'],
			$order_form['sender_country'],
			'S0148'
		);

		$soapOptions = array(
			'FechaOperacion' => gmdate('d-m-Y H:i:s'),
			'CodEtiquetador' => $payload['client']['CorreosKey'],
			'ModDevEtiqueta' => 2,
			'TotalBultos'    => $payload['bultos'],
			'CanalOrigen'    => 'WOO',
			'Care'           => '000000',
			'Remitente'      => array(
				'Identificacion' => $identRemitente,
				'DatosDireccion' => array(
					'Direccion'  => $order_form['customer_address'],
					'Localidad'  => $order_form['customer_city'],
				),
				'CP'               => $order_form['customer_cp'],
				'Telefonocontacto' => $order_form['customer_phone'],
				'Email'            => $order_form['customer_email'],
				'DatosSMS'         => array(
					'NumeroSMS'    => $order_form['customer_phone'],
					'Idioma'       => $order_form['customer_phone'] ? 1 : '',
				),
			),
			'Destinatario' => array(
				'Identificacion' => $identDestinatario,
				'DatosDireccion' => array(
					'Direccion' => $order_form['sender_address'],
					'Localidad' => $order_form['sender_city'],
					'Provincia' => '',
				),
				'DatosDireccion2'  => '',
				'CP'               => $order_form['sender_cp'],
				'ZIP'              => $delivery_zip,
				'Pais'             => $order_form['sender_country'],
				'Telefonocontacto' => CorreosOficialHelpers::cleanTelephoneNumber($order_form['sender_phone']),
				'Email'            => $order_form['sender_email'],
				'DatosSMS'         => array(
					'NumeroSMS' => $phone_mobile_sms,
					'Idioma'    => 1,
				),
			),
		);

		

		// ENVIO

		// PESOS ENVIO
		$bultoWeight = $order_form['packageWeightReturn_1'] ? $order_form['packageWeightReturn_1'] : ( new CorreosOficialConfig('WeightByDefault') )->get_value();

		$envio = array(
			'CodProducto'        => 'S0148',
			'ReferenciaCliente'  => $payload['order_id'] . ' ' . $payload['order_reference'],
			'ReferenciaCliente3' => 'MODULO_WC_' . get_option('woocommerce_version') . '/' . CORREOS_OFICIAL_VERSION,
			'TipoFranqueo'       => 'FP',
			'ModalidadEntrega'   => 'ST',
			'Pesos'              => array(
				'Peso' => array(
					'TipoPeso' => 'R',
					'Valor'    => CorreosOficialHelpers::getFloatValue($bultoWeight) * 1000,
				),
			),
			'ValoresAnadidos'    => array(
				'TextoAdicional'      => '',
				'EntregaconRecogida'  => 'N',
				'IndImprimirEtiqueta' => 'N',
			),
			'Observaciones1'          => '',
			'Observaciones2'          => '',
			'InstruccionesDevolucion' => 'D',
		);

		if (CorreosOficialHelpers::hasSize(
			$order_form['packageLargeReturn_1'],
			$order_form['packageHeightReturn_1'],
			$order_form['packageWidthReturn_1']
		)) {
			$envio[0]['Pesos']['Peso'][] = array(
				'TipoPeso' => 'V',
				'Valor'    => CorreosOficialHelpers::calculateVWeight(
					$order_form['packageLargeReturn_1'],
					$order_form['packageHeightReturn_1'],
					$order_form['packageWidthReturn_1']
				),
			);
			
			// Agregar dimensiones
			$envio[0]['Alto']  = $order_form['packageLargeReturn_1'];
			$envio[0]['Largo'] = $order_form['packageHeightReturn_1'];
			$envio[0]['Ancho'] = $order_form['packageWidthReturn_1'];
		}

		// ADUANAS ENVIO
		if ($order_form['require_customs_doc'] == 1) {
			$envio['Aduana'] = array(
				'TipoEnvio'            => 2,
				'EnvioComercial'       => 'S',
				'FacturaSuperiora500'  => 'N',
				'DUAConCorreos'        => 'N',
				'RefAduaneraExpedidor' => $order_form['custom_ref_exp'],
			);

			foreach ($payload['customs_desc_array'][1] as $desc) {
				$envio['Aduana']['DescAduanera']['DATOSADUANA'][] = array(
					'Cantidad' => $desc['unidades'],
					'Descripcion' => $desc['descripcion_aduanera'],
					'NTarifario' => $desc['numero_tarifario'],
					'Pesoneto' => $desc['weight'],
					'Valorneto' => $desc['valor_neto'],
				);
			}
		}

		$soapOptions['Envio'] = $envio;

		return $soapOptions;
	}
}
