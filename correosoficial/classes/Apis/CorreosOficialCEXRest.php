<?php
namespace CorreosOficial\Classes\Apis;

use CorreosOficial\Classes\CorreosOficialHelpers;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Classes\CorreosOficialCrypto;
use DateTime;
use stdClass;

// // CEX
// define('CEX_BASE_LOCATION', 'https://www.cexpr.es/wspsc/apiRestSeguimientoEnviosk8s/json/seguimientoEnvio');
// define('CEX_BASE_LOCATION_LISTA', 'https://www.cexpr.es/wspsc/apiRestListaEnvios/json/listaEnvios');
// define('CEX_BASE_LABELS', 'https://www.cexpr.es/wspsc/apiRestEtiquetaTransporte/json/etiquetaTransporte');
// define('CEX_GRABAR_ENVIO', 'https://www.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio');
// define('CEX_GRABAR_RECOGIDA', 'https://www.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/grabarRecogida');
// define('CEX_ANULAR_RECOGIDA', 'https://www.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida');
// define('CEX_CONSULTAR_RECOGIDA', 'https://www.cexpr.es/wspsc/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida');

// //Pre
// define('CEX_BASE_LOCATION_LISTA_PRE', 'https://www.test.cexpr.es/wspsc/apiRestListaEnvios/json/listaEnvios');
// define('CEX_GRABAR_ENVIO_PRE', 'https://www.test.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio');
// define('CEX_ANULAR_RECOGIDA_PRE', 'https://www.test.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida');
// define('CEX_GRABAR_RECOGIDA_PRE', 'https://www.test.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/grabarRecogida');
// define('CEX_CONSULTAR_RECOGIDA_PRE', 'https://www.test.cexpr.es/wsps/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida');


class CorreosOficialCEXRest {


	private static $environment = 'PRO';

	/* *********************************************************************************************************
	 * REST CALL
	 ********************************************************************************************************* */
	public function requestRestCall( $url, $data, $client ) {
		// Si no tenemos user password return con error
		if (!isset($client['CEXUser'])) {
			return array(
				'output' => json_encode(array(
					'mensajeRetorno' => '',
					'codigoRetorno' => '401',
				)),
				'status' => 0,
			);
		}

		// Tiene que venir ya el data válido
		// $postdata = json_encode($data);
		// if (is_null($postdata)) {
		//     throw new \Exception('decoding params');
		// }
		// $rest = $postdata;

		// iniciamos y componemos la peticion curl
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

		$decryptedPassword = CorreosOficialCrypto::decrypt($client['CEXPassword']);
		if ($decryptedPassword === false) {
			return array(
				'output' => json_encode(array(
					'mensajeRetorno' => CorreosOficialCrypto::getDecryptErrorMessage(),
					'codigoRetorno' => '500',
				)),
				'status' => 0,
			);
		}

		curl_setopt($ch, CURLOPT_USERPWD, $client['CEXUser'] . ':' . $decryptedPassword);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen(json_encode($data)),
		));

		$output = curl_exec($ch);
		$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // get status code
		$error = curl_error($ch);
		$info = curl_getinfo($ch);
		$codigo_error = curl_errno($ch);
		curl_close($ch);

		// si no es 200...
		if ($status_code != 200) {
			return array(
				'output' => json_encode(array(
					'status_code' => 0,
					'mensajeRetorno' => CO_TIMEOUT_MSG,
				)),
				'status' => $status_code,
			);
		}

		return array(
			'output' => $output,
			'status' => $status_code,
		);
	}

	 public function altaClienteCEXCall($payload)
    {
		// Control de url según entorno
		$urlGrabarEnvio = 'https://www.test.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio';
		if (self::$environment == 'PRO') {
			$urlGrabarEnvio = 'https://www.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio';
		}

        $data = array(
            "solicitante" => "",
            "canalEntrada" => "",
            "numEnvio" => "",
            "ref" => "",
            "refCliente" => "",
            "fecha" => "",
            "codRte" => "",
            "nomRte" => "",
            "nifRte" => "",
            "dirRte" => "",
            "pobRte" => "",
            "codPosNacRte" => "",
            "paisISORte" => "",
            "codPosIntRte" => "",
            "contacRte" => "",
            "telefRte" => "",
            "emailRte" => "",
            "codDest" => "",
            "nomDest" => "",
            "nifDest" => "",
            "dirDest" => "",
            "pobDest" => "",
            "codPosNacDest" => "",
            "paisISODest" => "",
            "codPosIntDest" => "",
            "contacDest" => "",
            "telefDest" => "",
            "emailDest" => "",
            "contacOtrs" => "",
            "telefOtrs" => "",
            "emailOtrs" => "",
            "observac" => "",
            "numBultos" => "",
            "kilos" => "",
            "volumen" => "",
            "alto" => "",
            "largo" => "",
            "ancho" => "",
            "producto" => "",
            "portes" => "",
            "reembolso" => "",
            "entrSabado" => "",
            "seguro" => "",
            "numEnvioVuelta" => "",
            "listaBultos" => [],
            "codDirecDestino" => "",
            "password" => "",
            "listaInformacionAdicional" => []
        );

        $interior = array();
        $interior['alto'] = "";
        $interior['ancho'] = "";
        $interior['codBultoCli'] = "1";
        $interior['codUnico'] = "";
        $interior['descripcion'] = "";
        $interior['kilos'] = "";
        $interior['largo'] = "";
        $interior['observaciones'] = "";
        $interior['orden'] = "";
        $interior['referencia'] = "";
        $interior['volumen'] = "";
        $data["listaBultos"][] = $interior;

        $lista = new stdClass();
        $lista->tipoEtiqueta = "";
        $lista->etiquetaPDF = "N";
        $lista->posicionEtiqueta = '';
        $lista->hideSender = "1";
        $lista->logoCliente = "";
        $lista->codificacionUnicaB64 = "1";
        $lista->textoRemiAlternativo = "";
        $lista->idioma = "ES";
        $lista->creaRecogida = "0";
        $lista->fechaRecogida = "";
        $lista->horaDesdeRecogida = "";
        $lista->horaHastaRecogida = "";
        $lista->referenciaRecogida = "";
        $data["listaInformacionAdicional"][] = $lista;

        $retorno = $this->requestRestCall($urlGrabarEnvio, $data, $payload['client']);

		if($retorno['status'] == "200"){
			return true;
		}
		return false;

    }

	/* *********************************************************************************************************
	 * PREREGISTRO PEDIDO
	 ********************************************************************************************************* */
	public function registrarEnvio( $payload ) {
		// Control de url según entorno
		$urlGrabarEnvio = 'https://www.test.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio';
		if (self::$environment == 'PRO') {
			$urlGrabarEnvio = 'https://www.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio';
		}

		// Sacamos el índice order_form a una variable para simplificar código
		$orderForm = $payload['order_form'];

		if ($orderForm['sender_country'] == 'ES') {
			$codPosNacRte = $orderForm['sender_cp'];
			$codPosIntRte = '';
		} elseif ($orderForm['sender_country'] == 'PT') {
			$codPosNacRte = '';
			$codPosIntRte = substr($orderForm['sender_cp'], 0, 4);
		} else {
			$codPosNacRte = '';
			$codPosIntRte = $orderForm['sender_cp'];
		}

		// Entrega en Oficina
		$id_office = '';
		if ($payload['delivery_mode'] == 'office') {
			$id_office = $orderForm['cod_office'];
		}

		/*
		 * Código postal nacional/internacional CUSTOMERS.
		 * Para Andorra se trata como nacional
		 * Para Portugal se cogen los 4 primeros dígitos
		 */
		if ($orderForm['customer_country'] == 'ES') {
			$codPosNacDest = $orderForm['customer_cp'];
			$codPosIntDest = '';
		} elseif ($orderForm['customer_country'] == 'PT') {
			$codPosNacDest = '';
			$codPosIntDest = substr($orderForm['customer_cp'], 0, 4);
		} else {
			$codPosNacDest = '';
			$codPosIntDest = $orderForm['customer_cp'];
		}

		// Contrareembolso
		$check_reembolso = isset($orderForm['contrareembolsoCheckbox']) ? $orderForm['contrareembolsoCheckbox'] : 0;
		$reembolso = ( $check_reembolso == 1 ) ? ( isset($orderForm['cash_on_delivery_value']) ? $orderForm['cash_on_delivery_value'] : '' ) : '';

		// Seguro
		$check_seguro = isset($orderForm['seguroCheckbox']) ? $orderForm['seguroCheckbox'] : 0;
		$seguro = ( $check_seguro == 1 ) ? ( isset($orderForm['insurance_value']) ? $orderForm['insurance_value'] : '' ) : '';
		
		// Entrega en sábado
        $check_sabado = isset($orderForm['delivery_saturday']) ? $orderForm['delivery_saturday'] : 0;
        $entrSabado = ( $check_sabado == 1 ) ? 'S' : 'N';

		$labelObservations = ( new CorreosOficialConfig( 'LabelObservations' ) )->get_value();
		$orderObservations = ( $labelObservations == 'on' && ! empty( $orderForm['observations'] ) )
			? substr( $orderForm['observations'], 0, 80 )
			: '';

		// Establecemos texto alternativo del remitente configurado en Ajustes
		$labelAlternativeText = CorreosOficialConfig::getLabelAlternativeText();
		if ($labelAlternativeText) {
			$orderForm['sender_name'] = $labelAlternativeText;
		}

		// Nombre destinatario y empresa
		if (isset($orderForm['customer_company']) && $orderForm['customer_company']) {
			$nomDestAndCompany = $orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname'] . ' ' . $orderForm['customer_company'];
		} else {
			$nomDestAndCompany = $orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname'];
		}

		// Contacto destinatario
		if ($orderForm['customer_contact'] != '') {
			$contactDest = $orderForm['customer_contact'];
		} else {
			$contactDest = $orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname'];
		}

		// ARRAY DATOS CUERPO
		$data = array(
			'solicitante' => 'P' . $payload['client']['CEXCustomer'],
			'canalEntrada' => '',
			'numEnvio' => '',
			'ref' => $orderForm['order_reference'],
			'refCliente' => 'MODULO_WC_' . get_option('woocommerce_version') . '/' . CORREOS_OFICIAL_VERSION,
			'fecha' => gmdate('dmY'),
			
			'codRte' => $payload['client']['CEXCustomer'],
			'nomRte' => $orderForm['sender_name'],
			'nifRte' => $orderForm['sender_nif_cif'],
			'dirRte' => $orderForm['sender_address'],
			'pobRte' => $orderForm['sender_city'],
			'codPosNacRte' => $codPosNacRte,
			'paisISORte' => $orderForm['sender_country'],
			'codPosIntRte' => $codPosIntRte,
			'contacRte' => $orderForm['sender_contact'],
			'telefRte' => $orderForm['sender_phone'],
			'emailRte' => $orderForm['sender_email'],

			'codDest' => '',
			'nomDest' => $nomDestAndCompany,
			'nifDest' => $orderForm['customer_dni'],
			'dirDest' => $orderForm['customer_address'],
			'pobDest' => $orderForm['customer_city'],
			'codPosNacDest' => $codPosNacDest,
			'paisISODest' => $orderForm['customer_country'],
			'codPosIntDest' => $codPosIntDest,
			'contacDest' => $contactDest,
			'telefDest' => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['customer_phone']),
			'emailDest' => $orderForm['customer_email'],

			'contacOtrs' => '',
			'telefOtrs' => '',
			'emailOtrs' => '',
			'observac' => $orderObservations,
			'numBultos' => $orderForm['correos-num-parcels'],
			'kilos' => '',
			'volumen' => '',
			'alto' => '',
			'largo' => '',
			'ancho' => '',
			'producto' => $payload['product']['codigoProducto'],
			'portes' => 'P',
			'reembolso' => $reembolso,
			'entrSabado' => $entrSabado,
			'seguro' => $seguro,
			'numEnvioVuelta' => '',
			'listaBultos' => array(),
			'codDirecDestino' => $id_office,
			'password' => '',
			'listaInformacionAdicional' => array(),
		);

		// Entrega en PUDO
		if ($payload['delivery_mode'] == 'pudocex') {
			$data['idPtoExterno'] = $orderForm['cod_pudocex'];
		}

		$suma_peso = 0;

		// Cuerpo Bultos
		for ($i = 1; $i <= $payload['bultos']; $i++) {

			if ($orderForm['all_packages_equal'] == 1) {
				$index = 1;
				$weight = 0;
			} else {
				$index = $i;
				$weight = $payload['info_bulto'][$index]['weight'];
			}

			$suma_peso = $suma_peso + $weight;

			$data['listaBultos'][] = array(
				'alto'          => CorreosOficialHelpers::parseMeters($payload['info_bulto'][$index]['height']),
				'ancho'         => CorreosOficialHelpers::parseMeters($payload['info_bulto'][$index]['width']),
				'codBultoCli'   => $i,
				'codUnico'      => '',
				'descripcion'   => '',
				'kilos'         => $weight,
				'largo'         => CorreosOficialHelpers::parseMeters($payload['info_bulto'][$index]['large']),
				'observaciones' => substr(
					! empty( $payload['info_bulto'][ $index ]['observations'] )
						? $payload['info_bulto'][ $index ]['observations']
						: $orderObservations,
					0,
					80
				),
				'orden'         => $i,
				'referencia'    => '',
				'volumen'       => '',
			);
		}


		// Kilos totales
	    if($payload['bultos'] == 1 || $orderForm['all_packages_equal'] == 1){
			$data['kilos'] = $orderForm["total_weight"];
		}elseif($payload['bultos'] > 1 && $orderForm['all_packages_equal'] == 0){
			$data['kilos'] = $suma_peso;
		}
		
		$lista = new stdClass();
		$lista->tipoEtiqueta = '5';
		$lista->etiquetaPDF = 'N';
		$lista->posicionEtiqueta = '';
		$lista->hideSender = '0';
		$lista->logoCliente = '';

		// Logo personalizado
		if (( new CorreosOficialConfig('ChangeLogoOnLabel') )->get_value() == 'on') {
			$imagedata = ( new CorreosOficialConfig('UploadLogoLabels') )->get_value();
			$base64 = base64_encode($imagedata);
			$lista->logoCliente = $base64;
		}

		$lista->codificacionUnicaB64 = '1';
		$lista->textoRemiAlternativo = '';
		$lista->idioma = 'ES';

		$lista->creaRecogida = $payload['needPickup'];
		$lista->fechaRecogida = gmdate('dmY', strtotime($payload['pickupDateRegister']));
		$lista->horaDesdeRecogida = gmdate('H:i', strtotime($payload['pickupFromRegister']));
		$lista->horaHastaRecogida = gmdate('H:i', strtotime($payload['pickupToRegister']));
		$lista->referenciaRecogida = '';
		if ($payload['needPickup'] === 'S') {
			$lista->referenciaRecogida = $orderForm['order_number'] . ' ' . $orderForm['order_reference'] . gmdate('dmY');
		}
		// Codigo AT opcional para los envíos PORTUGAL-PORTUGAL
		if ($orderForm['sender_country'] == 'PT' && $orderForm['customer_country'] == 'PT') {
			$lista->codigoAT = $orderForm['AT_code'];
		}

		// Añadimos a data la información adicional
		$data['listaInformacionAdicional'][] = $lista;
		
		$restResponse = $this->requestRestCall($urlGrabarEnvio, $data, $payload['client']);
		$response = json_decode($restResponse['output'], true);

		// Errores devueltos por el Api Rest (puede devolver codigoRetorno negativo OJO)
		if (!is_array($response) || !isset($response['codigoRetorno'])) {
			$result[] = array(
				'codigoRetorno'  => 18005,
				'mensajeRetorno' => 'Invalid or empty response from CEX API',
				'orderId'        => $payload['order_id']
			);
			return $result;
		}

		if ($response['codigoRetorno'] > 0 || $response['codigoRetorno'] < 0) {
			$result[] = array(
				'codigoRetorno'  => $response['codigoRetorno'],
				'mensajeRetorno' => mb_convert_encoding($response['mensajeRetorno'], 'UTF-8', 'ISO-8859-1'),
				'orderId'        => $payload['order_id']
			);
			return $result;
		}

		$pickupData = array();
		if ($payload['needPickup'] === 'S') {
			$fechaRecogida = isset($response['fechaRecogida']) ? $response['fechaRecogida'] : null;
			$dateFormatted = '';
			if ($fechaRecogida !== null) {
				$dateObj = DateTime::createFromFormat('dmY', $fechaRecogida);
				$dateFormatted = $dateObj ? $dateObj->format('Y-m-d') : '';
			}
			$pickupData = array(
				'codigoRetorno' => 0,
				'mensajeRetorno' => '',
				'codRecogida' => isset($response['numRecogida']) ? $response['numRecogida'] : '',
				'dateRegister' => $dateFormatted,
				'fromRegister' => isset($response['horaRecogidaDesde']) ? $response['horaRecogidaDesde'] : '',
				'toRegister' => isset($response['horaRecogidaHasta']) ? $response['horaRecogidaHasta'] : '',
			);
		}

		// RETURN ESTANDARIZADO
		return array(
			'codigoRetorno' => $response['codigoRetorno'],
			'mensajeRetorno' => $response['mensajeRetorno'] ? mb_convert_encoding($response['mensajeRetorno'], 'UTF-8', 'ISO-8859-1') : '',
			'exp_number' => $response['datosResultado'],
			'bultos'     => array_map(fn( $bulto ) => array(
				'numBulto'        => (int) $bulto['orden'],
				'shipping_number' => $bulto['codUnico'],
			), $response['listaBultos']),
			'pickup'     => $pickupData,
		);
	}

	/* *********************************************************************************************************
	 * CANCELAR ENVÌO
	 ********************************************************************************************************* */
	// Parece que CEX no tiene endpoint para cancelar pre-registros, si no tiene recogida, simpremente borramos
	// de la DB, si tiene recogida, si la tenemos que cancelar

	public function cancelarEnvio( $payload ) {

		if ($payload['pickup_number']) {
			// Control de url según entorno
			$urlCancelarRecogida = 'https://www.test.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida';
			if (self::$environment == 'PRO') {
				$urlCancelarRecogida = 'https://www.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida';
			}

			$data = array(
				'solicitante' => $payload['client']['CEXCustomer'],
				'password' => '',
				'keyRecogida' => !empty($payload['pickup_number']) ? $payload['pickup_number'] : $payload['pickup_number_return'],
				'strTextoAnulacion' => 'anulacion',
				'strUsuario' => '',
				'strReferencia' => '',
				'strCodCliente' => '',
				'strFRecogida' => '',
			);

			$restResponse = $this->requestRestCall($urlCancelarRecogida, $data, $payload['client']);
			$response = json_decode($restResponse['output'], true);

			// Errores devueltos por el Api Rest (20 es ya anulada, no devolvemos error)
			if ($response['codError'] && $response['codError'] != 20) {
				return array(
					array(
						'codigoRetorno' => $response['codError'],
						'mensajeRetorno' => mb_convert_encoding($response['mensError'], 'UTF-8', 'ISO-8859-1'),
					),
				);
			}		
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
	* REGISTRAR DEVOLUCION
	********************************************************************************************************* */
	public function generateReturn( $payload ) {
		$urlGrabarEnvio = 'https://www.test.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio';
		if (self::$environment == 'PRO') {
			$urlGrabarEnvio = 'https://www.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio';
		}

		// Sacamos el índice order_form a una variable para simplificar código
		$orderForm = $payload['order_form'];

		if ($orderForm['sender_country'] == 'ES') {
			$codPosNacRte = $orderForm['sender_cp'];
			$codPosIntRte = '';
		} elseif ($orderForm['sender_country'] == 'PT') {
			$codPosNacRte = '';
			$codPosIntRte = substr($orderForm['sender_cp'], 0, 4);
		} else {
			$codPosNacRte = '';
			$codPosIntRte = $orderForm['sender_cp'];
		}

		/*
		 * Código postal nacional/internacional CUSTOMERS.
		 * Para Andorra se trata como nacional
		 * Para Portugal se cogen los 4 primeros dígitos
		 */
		if ($orderForm['customer_country'] == 'ES') {
			$codPosNacDest = $orderForm['customer_cp'];
			$codPosIntDest = '';
		} elseif ($orderForm['customer_country'] == 'PT') {
			$codPosNacDest = '';
			$codPosIntDest = substr($orderForm['customer_cp'], 0, 4);
		} else {
			$codPosNacDest = '';
			$codPosIntDest = $orderForm['customer_cp'];
		}

		// Nombre destinatario y empresa
		if (isset($orderForm['customer_company']) && $orderForm['customer_company']) {
			$nomDestAndCompany = $orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname'] . ' ' . $orderForm['customer_company'];
		} else {
			$nomDestAndCompany = $orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname'];
		}

		// Contacto destinatario
		if ($orderForm['customer_contact'] != '') {
			$contactDest = $orderForm['customer_contact'];
		} else {
			$contactDest = $orderForm['customer_firstname'] . ' ' . $orderForm['customer_lastname'];
		}


		// ARRAY DATOS CUERPO
		$data = array(
			'solicitante' => 'P' . $payload['client']['CEXCustomer'],
			'canalEntrada' => '',
			'numEnvio' => '',
			'ref' => $orderForm['order_number'] . ' ' . $orderForm['order_reference'],
			'refCliente' => 'MODULO_WC_' . get_option('woocommerce_version') . '/' . CORREOS_OFICIAL_VERSION,
			'fecha' => gmdate('dmY'),
			
			'codRte' => $payload['client']['CEXCustomer'],
			'nomRte' => $nomDestAndCompany,
			'nifRte' => $orderForm['customer_dni'],
			'dirRte' => $orderForm['customer_address'],
			'pobRte' => $orderForm['customer_city'],
			'codPosNacRte' => $codPosNacDest,
			'paisISORte' => $orderForm['customer_country'],
			'codPosIntRte' => $codPosIntDest,
			'contacRte' => $contactDest,
			'telefRte' => CorreosOficialHelpers::cleanTelephoneNumber($orderForm['customer_phone']),
			'emailRte' => $orderForm['customer_email'],

			'codDest' => '',
			'nomDest' => $orderForm['sender_name'],
			'nifDest' => $orderForm['sender_nif_cif'],
			'dirDest' => $orderForm['sender_address'],
			'pobDest' => $orderForm['sender_city'],
			'codPosNacDest' => $codPosNacRte,
			'paisISODest' => $orderForm['sender_country'],
			'codPosIntDest' => $codPosIntRte,
			'contacDest' => $orderForm['sender_contact'],
			'telefDest' => $orderForm['sender_phone'],
			'emailDest' => $orderForm['sender_email'],

			'contacOtrs' => '',
			'telefOtrs' => '',
			'emailOtrs' => '',
			'observac' => '',
			'numBultos' => $orderForm['correos-num-parcels-return'],
			'kilos' => '',
			'volumen' => '',
			'alto' => '',
			'largo' => '',
			'ancho' => '',
			'producto' => '63',
			'portes' => 'P',
			'reembolso' => '',
			'entrSabado' => $orderForm['delivery_saturday'] == 0 ? 'N' : 'S',
			'seguro' => '',
			'numEnvioVuelta' => '',
			'listaBultos' => array(),
			'codDirecDestino' => '',
			'password' => '',
			'listaInformacionAdicional' => array(),
		);

		$all_packages_equal = $orderForm['all_packages_equal'];
		$total_weight = 0;

		for ($i = 1; $i <= $payload['bultos']; $i++) {
			$index = $all_packages_equal == 1 ? 1 : $i;

			$data['listaBultos'][] = array(
				'alto'          => CorreosOficialHelpers::parseMeters($payload['info_bulto'][$index]['height']),
				'ancho'         => CorreosOficialHelpers::parseMeters($payload['info_bulto'][$index]['width']),
				'codBultoCli'   => $i,
				'codUnico'      => '',
				'descripcion'   => '',
				'kilos'         => $payload['info_bulto'][$index]['weight'],
				'largo'         => CorreosOficialHelpers::parseMeters($payload['info_bulto'][$index]['large']),
				'observaciones' => '',
				'orden'         => $i,
				'referencia'    => '',
				'volumen'       => '',
			);

			$total_weight += floatval($payload['info_bulto'][$index]['weight']);
		}

		// Añadimos los kilos calculados
		$data['kilos'] = number_format($total_weight, 2);

		$lista = new stdClass();
		$lista->tipoEtiqueta = '5';
		$lista->etiquetaPDF = 'N';
		$lista->posicionEtiqueta = '';
		$lista->hideSender = '0';
		$lista->logoCliente = '';

		// Logo personalizado
		if (( new CorreosOficialConfig('ChangeLogoOnLabel') )->get_value() == 'on') {
			$imagedata = ( new CorreosOficialConfig('UploadLogoLabels') )->get_value();
			$base64 = base64_encode($imagedata);
			$lista->logoCliente = $base64;
		}

		$lista->codificacionUnicaB64 = '1';
		$lista->textoRemiAlternativo = '';
		$lista->idioma = 'ES';

		$lista->creaRecogida = $payload['needPickup'];
		$lista->fechaRecogida = gmdate('dmY', strtotime($orderForm['PickupDateRegister']));
		$lista->horaDesdeRecogida = gmdate('H:i', strtotime($orderForm['PickupFromRegister']));
		$lista->horaHastaRecogida = gmdate('H:i', strtotime($orderForm['PickupToRegister']));
		$lista->referenciaRecogida = '';
		if ($payload['needPickup'] === 'S') {
			$lista->referenciaRecogida = $orderForm['order_reference'] . ' ' . gmdate('dmY');
		}
		// Codigo AT opcional para los envíos PORTUGAL-PORTUGAL
		if ($orderForm['sender_country'] == 'PT' && $orderForm['customer_country'] == 'PT') {
			$lista->codigoAT = $orderForm['AT_code'];
		}

		// Añadimos a data la información adicional
		$data['listaInformacionAdicional'][] = $lista;

		$restResponse = $this->requestRestCall($urlGrabarEnvio, $data, $payload['client']);
		$response = json_decode($restResponse['output'], true);

		// Errores devueltos por el Api Rest (codigoRetorno es mayor que 0)
		if (!is_array($response) || !isset($response['codigoRetorno'])) {
			$result[] = array(
				'codigoRetorno' => 18005,
				'mensajeRetorno' => 'Invalid or empty response from CEX API',
			);
			return $result;
		}
		if ($response['codigoRetorno']) {
			$result[] = array(
				'codigoRetorno' => $response['codigoRetorno'],
				'mensajeRetorno' => mb_convert_encoding($response['mensajeRetorno'], 'UTF-8', 'ISO-8859-1'),
			);
			return $result;
		}

		$pickupData = array();
		if ($payload['needPickup'] === 'S') {
			$fechaRecogida2 = isset($response['fechaRecogida']) ? $response['fechaRecogida'] : null;
			$dateFormatted2 = '';
			if ($fechaRecogida2 !== null) {
				$dateObj2 = DateTime::createFromFormat('dmY', $fechaRecogida2);
				$dateFormatted2 = $dateObj2 ? $dateObj2->format('Y-m-d') : '';
			}
			$pickupData = array(
				'codigoRetorno' => 0,
				'mensajeRetorno' => '',
				'codRecogida' => isset($response['numRecogida']) ? $response['numRecogida'] : '',
				'dateRegister' => $dateFormatted2,
				'fromRegister' => isset($response['horaRecogidaDesde']) ? $response['horaRecogidaDesde'] : '',
				'toRegister' => isset($response['horaRecogidaHasta']) ? $response['horaRecogidaHasta'] : '',
			);
		}

		// RETURN ESTANDARIZADO
		return array(
			'codigoRetorno' => $response['codigoRetorno'],
			'mensajeRetorno' => $response['mensajeRetorno'] ? mb_convert_encoding($response['mensajeRetorno'], 'UTF-8', 'ISO-8859-1') : '',
			'exp_number' => $response['datosResultado'],
			'bultos'     => array_map(fn( $bulto ) => array(
				'numBulto'        => (int) $bulto['orden'],
				'shipping_number' => $bulto['codUnico'],
			), $response['listaBultos']),
			'pickup'     => $pickupData,
		);
	}

	/* *********************************************************************************************************
	* SEGUIMIENTO DE ENVIO
	********************************************************************************************************* */
	public function getOrderStatus( $payload ) {
		$urlgetOrderStatus = 'https://www.test.cexpr.es/wspsc/apiRestListaEnvios/json/listaEnvios';
		if (self::$environment == 'PRO') {
			$urlgetOrderStatus = 'https://www.cexpr.es/wspsc/apiRestSeguimientoEnviosk8s/json/seguimientoEnvio';
		}

		$data = array(
			'codigoCliente' => $payload['client']['CEXCustomer'],
			'dato'          => $payload['shipping_number'],
			'idioma'        => 'ES',    
		);

		$orderStatusResponse = $this->requestRestCall($urlgetOrderStatus, $data, $payload['client']);

		// Manejo de error en la respuesta
		if (isset($orderStatusResponse['status']) && $orderStatusResponse['status'] != 200) {
			return array(
				array(
					'codigoRetorno'  => $orderStatusResponse['status'],
					'mensajeRetorno' => $orderStatusResponse['output'],
				),
			);
		}

		
		return array(
			'codigoRetorno' => $orderStatusResponse['status'],
			'mensajeRetorno' => json_decode($orderStatusResponse['output']),
		);
	}

	/* *********************************************************************************************************
	 * IMPRIMIR ETIQUETA
	 ********************************************************************************************************* */

	public function imprimirEtiqueta( $payload ) {
		// Control de url según entorno
		$urlImprimirEtiqueta = 'https://www.test.cexpr.es/wspsc/apiRestEtiquetaTransporte/json/etiquetaTransporte';
		if (self::$environment == 'PRO') {
			$urlImprimirEtiqueta = 'https://www.cexpr.es/wspsc/apiRestEtiquetaTransporte/json/etiquetaTransporte';
		}

		$position = !$payload['label_position'] ? 0 : (int) ( $payload['label_position'] - 1 );
		$tipo = CEX_LABEL_THERMAL_ADHESIVE;

		// Mapeo de tipos de etiquetas para CEX
		if ($payload['label_type'] == LABEL_TYPE_THERMAL || $payload['label_type'] == LABEL_TYPE_ADHESIVE ) {
			$tipo = CEX_LABEL_THERMAL_ADHESIVE;
		}
		
		if ($payload['label_type'] == LABEL_TYPE_ADHESIVE && $payload['label_format'] == LABEL_FORMAT_3A4) {
			$tipo = CEX_LABEL_3A4;
			$position = 0;
		}

		$data = array(
			'keyCli'           => $payload['client']['customer_code'],
			'nenvio'           => $payload['exp_number'],
			'posicionEtiqueta' => $position,
			'tipo'             => $tipo,
			'logoCliente'      => isset($payload['label_custom_logo']) ? $payload['label_custom_logo'] : '',
		);
	
		$restResponse = $this->requestRestCall($urlImprimirEtiqueta, $data, $payload['client']);
		$response = json_decode($restResponse['output'], true);
	
		// Manejo de error en la respuesta
		if (isset($response['codigoRetorno'])) {
			return array(
				array(
					'codigoRetorno'  => isset($response['codigoRetorno']) ? $response['codigoRetorno'] : '18005',
					'mensajeRetorno' => mb_convert_encoding($response['mensajeRetorno'], 'UTF-8', 'ISO-8859-1'),
					'orderId'        => $payload['order_id'],
					'reference'      => $payload['reference'],
				),
			);
		}

		if (isset($response['codErr']) && $response['codErr'] == '-10') {
			return array(
				array(
					'codigoRetorno'  => isset($response['codErr']) ? $response['codErr'] : '18005',
					'mensajeRetorno' => $response['desErr'],
					'orderId'        => $payload['order_id'],
					'reference'      => $payload['reference'],
				),
			);
		}

		
		// Procesar etiquetas si existen
		$labels = isset($response['listaEtiquetas']) && is_array($response['listaEtiquetas']) ? $response['listaEtiquetas'] : array();
		if (empty($labels)) {
			return array(
				array(
					'codigoRetorno'  => isset($response['codErr']) ? $response['codErr'] : '18005',
					'mensajeRetorno' => isset($response['desErr']) && $response['desErr'] !== '' ? $response['desErr'] : 'No se recibieron etiquetas de CEX.',
					'orderId'        => $payload['order_id'],
					'reference'      => $payload['reference'],
				),
			);
		}

		$result = array();
		$result[] = array( // Agregamos cada etiqueta como un nuevo array en $result
			'codigoRetorno'   => $response['codErr'],
			'mensajeRetorno'  => $response['desErr'],
			'labels'          => array_map('base64_decode', $labels),
		);
		return $result;
	}

	/* *********************************************************************************************************
	 * ESTADO DE RECOGIDA
	********************************************************************************************************* */

	public function consultarRecogida( $payload ) {
		$urlConsultarRecogida = 'https://www.test.cexpr.es/wsps/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida';
		if (self::$environment == 'PRO') {
			$urlConsultarRecogida = 'https://www.cexpr.es/wspsc/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida';
		}

		$data = array (
			'recogida'      => $payload['pickup_number'],
			'codigoCliente' => $payload['client']['customer_code'],
			'fecRecogida'   => $payload['fecRecogida'],
            "idioma"        => $payload['idioma']
		);

		$restResponse = $this->requestRestCall($urlConsultarRecogida, $data, $payload['client']);
		$response = json_decode($restResponse['output'], true);

		if ($response['codigoRetorno'] != 0) {
			return array (
				'codigoRetorno' => $response['codigoRetorno'],
				'mesajeRetorno' => $response['mensajeRetorno']
			);
		}

		return array (
			'codigoRetorno' => 0,
			'mensajeRetorno' => $response
		);
	}

	/* *********************************************************************************************************
	 * GET PICKUP LOCATIONS
	 ********************************************************************************************************* */

	public function getPickupLocations( $payload ) {

		// Usamos siempre PRO para localizador de oficinas
		$urlListadoOficinas = 'https://www.cexpr.es/wspsc/apiRestOficina/v1/oficinas/listadoOficinasCoordenadas';

		// Control de url según entorno
		$urlListadoConsultaPUDOS = 'https://www.test.cexpr.es/wspsc/apiRestInterfacePuntosEntrega/json/consultPudo';
		if (self::$environment == 'PRO') {
			$urlListadoConsultaPUDOS = 'https://www.cexpr.es/wspsc/apiRestInterfacePuntosEntrega/json/consultPudo';
		}

		$locations = array();

		switch ($payload['selector_type']) {
			case 'office':

				$data = array(
					'cod_postal' => $payload['postcode'],
					'poblacion'  => "",
				);

				$restResponse = $this->requestRestCall($urlListadoOficinas, $data, $payload['client']);
				$response = json_decode($restResponse['output'], true);

				if(isset($response['oficinas']) && count($response['oficinas'])){
					$locations = $response['oficinas'];
				}

				break;

			case 'pudocex':

				$data = array(
					'Expedicion'       => "",
					'cpOrigen'         => "",
					'latitudOrigen'    => "",
					'longitudOrigen'   => "",
					'isoPaisOrigen'    => "",
					'cpDest'           => $payload['postcode'],
					'latitudDest'      => "",
    				'longitudDest'     => "",
					'isoPaisDest'      => $payload['country'],
					'idCliente'        => $payload['client']['CEXCustomer'],
					'idProducto'       => "18",
					'importeReembolso' => "0",
					'valorMercancia'   => strval($payload['cart_total']),
					'valorAsegurado'   => (int) $payload['insurance_value'],
					'nroBultos'        => "1",
					'pod'              => "",
					'entregaSabado'    => "0",
					'portes'           => "P",
					'largo'            => CorreosOficialHelpers::parseMeters($payload['info_bulto'][1]['large']),
					'ancho'            => CorreosOficialHelpers::parseMeters($payload['info_bulto'][1]['width']),
					'alto'             => CorreosOficialHelpers::parseMeters($payload['info_bulto'][1]['height']),
					'peso'             => strval($payload['total_weight']),
					'volumen' 		   => "",
					'servicioEntrega'  => "",
					'servicioRecogida' => ""
				);

				$restResponse = $this->requestRestCall($urlListadoConsultaPUDOS, $data, $payload['client']);
				$response = json_decode($restResponse['output'], true);

				// Para errores como por ejemplo Timeouts
				if (isset($response['status_code']) && $response['status_code'] == "0"){
					return array(
						'codigoRetorno'  => 1,
						'mensajeRetorno' => $response['mensajeRetorno'],
					);
				}

				if(isset($response['ptoConv']) && count($response['ptoConv'])){
					$locations = $response['ptoConv'];
				}

				break;
		}

		if (!count($locations)){
			return array(
				'codigoRetorno'  => 1,
				'mensajeRetorno' => __('No Collection point found', 'correosoficial'),
			);
		} else {
			return array(
				'codigoRetorno'  => 0,
				'mensajeRetorno' => '',
				'locations'      => $locations
			);
		}

	}
}


