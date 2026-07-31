<?php

namespace CorreosOficial\Classes;

use CorreosOficial;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Models\CorreosOficialProduct;
use CorreosOficial\Models\CorreosOficialRequests;
use CorreosOficial\Models\CorreosOficialSGAOrdersLog;
use CorreosOficial\Models\CorreosOficialSgaOrdersStatus;
use CorreosOficial\Classes\Apis\CorreosOficialSGARest;
use CorreosOficial\Classes\CorreosOficialCrypto;
use CorreosOficial\Models\CorreosOficialCode;
use DateTime;
use WC_Product;

/**
 * Clase genérica para lo relacionado con Senders
 */
class CorreosOficialSGA
{
    public static function sendOutgoingOrder($payloads) {

        $APIRouter = new CorreosOficialApiRouter();
        $sga_rest = new CorreosOficialSGARest();

        foreach($payloads as $payload) {

            $wc_order = wc_get_order($payload['id_order']);
            CorreosOficialHelpers::writeToLog('sga', 'Enviando pedido' . $payload['id_order'] . ' a Sislog');

            // Añadimos Ownmer, Client y Warehouse
            $payload['ownerid']     = ( new CorreosOficialConfig('SGAOwner') )->get_value();
            $payload['clientid']    = ( new CorreosOficialConfig('SGACustomer') )->get_value();
            $payload['warehouse']   = ( new CorreosOficialConfig('SGAStore') )->get_value();

            // Datos dirección envio
            $country        = $wc_order->get_billing_country();
		    $state          = $wc_order->get_billing_state();
            $wc_order_state = WC()->countries->get_states($country)[$state];

            $payload['addressDelivery'] = [
                'firstname'   => $wc_order->get_shipping_first_name(),
                'lastname'    => $wc_order->get_shipping_last_name(),
                'company'     => $wc_order->get_shipping_company(),
                'address_1'   => $wc_order->get_shipping_address_1(),
                'address_2'   => $wc_order->get_shipping_address_2(),
                'city'        => $wc_order->get_shipping_city(),
                'state'       => $state,
                'postcode'    => $wc_order->get_shipping_postcode(),
                'country'     => $country,
                'phone'       => $wc_order->get_billing_phone(),
                'email'       => $wc_order->get_billing_email(),
                'dni'         => $wc_order->get_meta('NIF'),
                'iso_country' => $wc_order->get_shipping_country(),
                'state'       => $wc_order_state
            ];

            
            // referencia de la orden/pedido
            $order_number = (int) $wc_order->get_order_number();
            $payload['order_reference'] = empty($order_number) ? $payload['id_order'] : $order_number;

            $client_phone = $payload['addressDelivery']['phone'];

            if ($client_phone) {
                // Filtros a aplicar  RN30 - ID00015194
                $payload['addressDelivery']['phone'] = str_replace('+34', '', $client_phone);
                $payload['addressDelivery']['phone'] = str_replace('+351', '', $client_phone);
                $payload['addressDelivery']['phone'] = str_replace(' ', '', $client_phone);
            }

            // Notas del pedido
            $payload['transportComments'] = '';
            if (!empty($wc_order->get_customer_note())) {
                $payload['transportComments'] = str_replace(array( "\r\n", "\n" ), ' ', $wc_order->get_customer_note());
			    $payload['transportComments'] = substr($payload['transportComments'], 0, 256);
            }

            // Fecha de alta pedido en la tienda
            $datetime = current_time('Y-m-d\TH:i:sP');
            $payload['registryDate'] = $datetime;

            // TRANSPORTISTA
            // Obtenemos el producto de correos por el carrier del pedido
            $shipping_methods = $wc_order->get_shipping_methods();
            
            // Inicializamos variables
            $carrier_id = null;
            $payload['details'] = [];
            $payload['details_error'] = [];
            $payload['reference'] = '';
            $payload['companyTransport'] = 0;
            $payload['serviceProduct'] = '';
            $payload['deliveryType'] = '';

            foreach ( $shipping_methods as $method ) {
                $carrier_id  = $method->get_instance_id();
            }

            $co_product = (new CorreosOficialProduct())->get_by_carrier($carrier_id);
            $n = 1;
            
            // Si el pedido se ha registrado con un producto de correos
            if ($co_product) {
                // Si es un producto de tipo oficina o citypaq, obtenemos referencia
                $payload['reference'] = '';
                if ($co_product->get_product_type() && ($co_product->get_product_type() == 'citypaq' || $co_product->get_product_type() == 'office')) {
                    $request = new CorreosOficialRequests($payload['id_order']);
                    $payload['reference'] = $request->get_reference_code();
                }

                // Código Producto
                $payload['serviceProduct'] = $co_product->get_codigoProducto();
                // Delivery Type
                $payload['deliveryType'] = $co_product->get_mode();

                // Company
                switch ($co_product->get_company()) {
                    case 'Correos': 
                        $payload['companyTransport'] = 11;
                        break;
                    case 'CEX':
                        $payload['companyTransport'] = 1;
                        break;
                    default:
                        $payload['companyTransport'] = 0;
                        break;
                }
            }

            // PRODUCTOS DEL PEDIDO
            $details = [];
            $payload['details_error'] = [];
            $n = 1;

            $items = $wc_order->get_items();

            foreach ( $items as $item ) {
                $product = $item->get_product();
                
                // Validar existencia del producto
                if ( ! $product instanceof WC_Product ) {
                    return [
                        'id_order' => $payload['id_order'],
                        'error'    => __( 'Uno o varios de los productos del pedido no se encuentra disponible o no existe.', 'correosecomsga' ),
                    ];
                }

                $item_sku = $product->get_sku();

                // Si es una variación, el SKU debe venir de la variación
                if ( $product->is_type( 'variation' ) ) {
                    $item_sku = $product->get_sku();
                } elseif ( $item->get_variation_id() ) {
                    $variation = wc_get_product( $item->get_variation_id() );
                    if ( $variation ) {
                        $item_sku = $variation->get_sku() ?: $item_sku;
                    }
                }

                $product_details = [
                    'lineCode'         => $n,
                    'itemId'           => strtoupper( $item_sku ),
                    'quantityOrdered'  => (int) $item->get_quantity(),
                    'variant1'         => '0',
                    'variant2'         => '0',
                    'variableLogistic' => 0,
                    'formatType'       => 'U',
                    'manufactureBatch' => '',
                    'comments'         => '',
                    'serialNumbers'    => [],
                ];
                
                // Determinar si el producto se procesa o se descarta
                if ( ! $product->is_virtual() && ! $product->is_downloadable() ) {
                    $payload['details'][] = $product_details;
                } else {
                    $payload['details_error'][] = $product_details;

                    $message = sprintf(
                        'Artículo: %s no es de tipo físico, no se procesará.',
                        strtoupper( $product->get_sku() )
                    );
                    self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'VERART', $message);
                }

                $n++;
            }

            // Si no tenemos artículos físicos a procesar, devolvemos error
            $details_error = $payload['details_error'] ?? [];

            if (($n - 1) === count($details_error)) {
                $message = __(' Status: 406. The order does not contain physical items to be sent to the warehouse.', 'correosoficial');
                self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $message);
                return ['type' => 'error', 'message' => $message];
            }

            // sobreescribimos el array details
            // agrupando sus elementos para los productos con mismo "itemId"
            $payload['details'] = CorreosOficialHelpers::groupArrayDetailsByItemId($payload['details']);

            // RN70: truncamos algunos campos
            $address_delivery_truncated = substr($payload['addressDelivery']['address_1'] . ' ' . $payload['addressDelivery']['address_2'], 0, 255);
            $city_delivery_truncated    = substr($payload['addressDelivery']['city'], 0, 40);
            $state_delivery_truncated   = ($payload['addressDelivery']['state'] != null) ? substr($payload['addressDelivery']['state'], 0, 40) : substr($payload['addressDelivery']['city'], 0, 40);
            $client_name_truncated      = substr($payload['addressDelivery']['firstname'] . ' ' . $payload['addressDelivery']['lastname'], 0, 75);
            $cp_original                = $payload['addressDelivery']['postcode'];
            $cp_delivery_truncated      = $cp_original;
            
            // Si el código postal tiene más de 7 caracteres, se trunca aplicando las siguientes reglas:
            if (strlen($cp_delivery_truncated) > 7) {
                if (strpos($cp_delivery_truncated, ' ') !== false) { // Si el código postal contiene espacios, se eliminan todos los espacios y se truncan a los primeros 7 caracteres.
                    $cp_delivery_truncated = str_replace(' ', '', $cp_delivery_truncated);

                    if (strlen($cp_delivery_truncated) > 7) { // Si aun eliminando los espacios tiene más de 7 caracteres, se trunca a los primeros 7 caracteres.
                        $cp_delivery_truncated = substr($cp_delivery_truncated, 0, 7);
                    }
                } elseif (strpos($cp_delivery_truncated, '-') !== false) { // Si el código postal contiene guiones, se toma la parte antes del primer guion.
                    $cp_delivery_truncated = explode('-', $cp_delivery_truncated, 2)[0];
                } else {
                    $cp_delivery_truncated = substr($cp_delivery_truncated, 0, 7);
                }
            }
            
            // Id del pedido syslog
            $payload['outGoingOrderId'] = (string) $payload['id_order'];

            $payload['data'] = array(
                'header' => array(
                    'division' => '0',
                    'warehouse' => $payload['warehouse'],
                    'transmissionDate' => $payload['registryDate'],
                    'serviceDate' => $payload['registryDate'],
                    'orderType' => 'PS',
                    'groupingType' => '',
                    'indUrgency' => 0,
                    'indNotApproved' => 'N',
                    'indAdvancedPrinting' => 'N',
                    'nameCompanyTrans' => '',
                    'indExpedition' => 'S',
                    'outgoingOrderId' => $payload['outGoingOrderId'],
                    'weightOrder' => 0,
                    'volumeOrder' => 0,
                    'addressDelivery' => $address_delivery_truncated,
                    'cityDelivery' => $city_delivery_truncated,
                    'stateDelivery' => $state_delivery_truncated,
                    'zipCodeDelivery' => $cp_delivery_truncated,
                    'countryDelivery' => $payload['addressDelivery']['iso_country'],
                    'comments' => '',
                    'itemFab' => '',
                    'variant1Fab' => '',
                    'variant2Fab' => '',
                    'variableLogisticFab' => 0,
                    'orderSalesFinished' => '',
                    'clientName' => $client_name_truncated,
                    'clientPhone' => $payload['addressDelivery']['phone'],
                    'clientEmail' => $payload['addressDelivery']['email'],
                    'clientIdentityNumber' => $payload['addressDelivery']['dni'] != null ? $payload['addressDelivery']['dni'] : '',
                    'territoryCode' => '',
                    'clientOrder' => $payload['outGoingOrderId'],
                    'MASCode' => '',
                    'indicatorPortes' => 'P',
                    'transportComments' => $payload['transportComments'],
                    'clientBulkCode' => '',
                    'clientBulkReference' => '',
                    'senderEmail' => '',
                    'senderPhone' => '',
                    'deliveryNoteSigned' => '',
                    'goodsType' => '',
                    'reference' => '',
                    'invoiceReference' => '',
                ),
                'detail' => $payload['details']
            );

            // Si tenemos $reference añadimos el campo a indice header
            if ($payload['reference']) {
                $payload['data']['header']['reference'] = $payload['reference'];
            }

            // Si tenemos $companyTransport añadimos el campo a indice header
            if ($payload['companyTransport']) {
                $payload['data']['header']['nameCompanyTrans'] = strval($payload['companyTransport']);
                $payload['data']['header']['companyTransport'] = (int) $payload['companyTransport'];
            }

            // Si tenemos $serviceProduct añadimos el campo a indice header
            if ($payload['serviceProduct']) {
                $payload['data']['header']['serviceProduct'] = $payload['serviceProduct'];
            }

            // Delivery Type
            if ($payload['deliveryType']) {
                $payload['data']['header']['deliveryType'] = $payload['deliveryType'];
            }

            // Si es un reembolsos añadimos los campos
            $IBAN = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
            $cash_on_delivery_method = ( new CorreosOficialConfig('CashOnDeliveryMethod') )->get_value();

            // Importante: solo tratamos el pedido como Contra Reembolso si el total es > 0.
            // no podemos enviar amountRefund=0 a la API de Logística porque devuelve 400 Bad Request
            $order_total = floatval($wc_order->get_total());

            // Aceptamos tanto el método configurado en el módulo como el COD nativo de WooCommerce.
            $payment_method = $wc_order->get_payment_method();
            $is_cash_on_delivery = ($payment_method == $cash_on_delivery_method || $payment_method === 'cod');

            if ($is_cash_on_delivery && $order_total > 0) {
                $payload['data']['header']['amountRefund'] = $order_total;
                $IBAN                           = ( new CorreosOficialConfig('BankAccNumberAndIBAN') )->get_value();
                if ($IBAN) {
                    $decryptedIban = CorreosOficialCrypto::decrypt($IBAN);
				    $payload['data']['header']['accountNumber'] = $decryptedIban !== false ? $decryptedIban : '';
                } else {
                    $payload['data']['header']['accountNumber'] = '';
			    }
            }

             // **** RN60 **** se comprueba stock disponible de los artículos del pedido antes de la llamada addOutgoingOrder
            self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'INIPED', 'Iniciamos pedido.');

            // Si el check de 'Comprobar stock en el pedido de venta' está activo...
            $update_order_stock = ( new CorreosOficialConfig('SGAOrderUpdateStock') )->get_value();

            // Por cada artículo se ha de verificar si se permite realizar el pedido sin stock suficiente.
			// IMPORTANTE: no podemos hacer esto iterando el array $orderProductsStock, porque ese array contiene
			//   solo los productos con stock, y nosotros necesitamos que si el producto no tiene stock y
			//   si tiene permitido el backorder, considerar ese producto en la orden del pedido.
			//   Por eso habrá que usar la matriz de $details y comprobar el stock de cada producto, por separado.
            if ($update_order_stock == 'on') {
                foreach ($payload['details'] as $detail) {
                    // obtenemos el objeto de producto a partir del SKU
                    if ($detail['itemId']) {
                        $product_sku = $detail['itemId'];
                        $product_id  = wc_get_product_id_by_sku($product_sku); 
                        $product     = wc_get_product($product_id);
				    }

                    // La llamada a la consulta de stock se registra en tabla de log con la anotación REQSTO
                    // y en información el contenido de la request
                    $message = 'Consultamos el Stock del producto con SKU: ' . $product_sku;
                    $stock_query = [
                    	'warehouse' => $payload['warehouse'],
                    	'article' => $product_sku,
                    ];
                    $stock_url = $sga_rest->getApiBaseUrl() . '/warehouse/owners/' . urlencode($payload['ownerid']) . '/stock-checkitem?' . http_build_query($stock_query);
                    self::writeToOrdersLog(
                    	$payload['id_order'],
                    	$payload['order_reference'],
                    	'REQSTO',
                    	$message . '  ' . $stock_url,
                    	json_encode([
                    		'payload' => $stock_query,
                    	], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    	
                    );

                    // consultar el stock de cada uno de los artículos para verificar si hay existencias en almacén
                    $payload['product_sku'] = $product_sku;
                    $product_stock = $APIRouter->getProductStockBySKU($payload);

                    if ($product_stock['status'] != 200) {
                        $decoded_output = json_decode($product_stock['output']);
                        $message = isset($decoded_output->returnMessage) ? $decoded_output->returnMessage : 'Error al consultar stock del producto';
                        $info    = $product_stock['output'];
                        
                        self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $message, $info);

                        // Cambiar estado del pedido a error (con fallback a SGA_STATUS_KO)
                        self::applySgaErrorStatus( $wc_order, $message );

                        // Devolver error sin interrumpir
                        $error_message = 'Error al consultar stock para el producto ' . $product_sku . ': ' . $message;
                        return ['type' => 'error', 'message' => $error_message];
                    }

                    // Por cada artículo se ha de verificar si se permite realizar el pedido sin stock suficiente
                    // NOTA: El lugar donde se guarda en BD si se permiten backorders se saca con esta consulta
                    //      SELECT * FROM wp_postmeta 
                    //      WHERE post_id = (SELECT ID FROM wp_posts WHERE post_type = 'product' AND post_title = 'Nombre del Producto') 
                    //      AND meta_key ='_backorders';
                    //      Posibles valores: no, notify, yes
                    $allow_out_of_stock         = $product->backorders_allowed();
                    $decoded                    = json_decode($product_stock['output']);
                    $product_stock              = (int) ($decoded->data->stockCheckItem[0]->quantityAvailable ?? 0); 
                    $requested_product_quantity = (int) $detail['quantityOrdered'];
                    $stock_exists = true;

                    if ($allow_out_of_stock) {
                        $message = 'Artículo: ' . $product_sku . ' / Stock: ' . $product_stock . '  El artículo se encuentra configurado para poder realizar pedido sin stock.';
					    self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'VERART', $message);
                    } else {
                        $stock_exists = ($product_stock >= $requested_product_quantity);
                        
                        if ($stock_exists) {
                            $message = 'Artículo: ' . $product_sku . ' / Stock: ' . $product_stock . '  Se permite la venta del artículo, stock suficiente.';
                        } else {
                            $message = 'Artículo: ' . $product_sku . ' / Stock: ' . $product_stock . '  No se permite la venta del artículo, stock insuficiente ya que se solicitan ' . $requested_product_quantity . ' unidades.';
                        }
                    }

                    self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'VERART', $message);

                    if (!$stock_exists) {
                        // Registrar error sin cancelar
                        $error_message = 'Pedido: ' . $payload['id_order'] . ' no se ha podido enviar a almacén. ' . $message;
                        self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $error_message);

                        self::applySgaErrorStatus( $wc_order, $error_message );

                        return ['type' => 'error', 'message' => $error_message];
                    }
                }
            } // fin Si el check de 'Comprobar stock en el pedido de venta' está activo...

            // Si llegamos hasta aquí es que se permite hacer la llamada API Warehouse
            //   bien porque el checkbox de 'Comprobar stock en el pedido de venta' esté desactivado
            //   bien porque se comprueba el stock, y hay stock

            // anotación en la tabla de log: wp_correos_ecom_sga_orders_log
            $message = 'Llamamos a la API';
            $orders_url = $sga_rest->getApiBaseUrl() . '/warehouse/owners/' . urlencode($payload['ownerid']) . '/clients/' . urlencode($payload['clientid']) . '/outgoing-orders';
            self::writeToOrdersLog(
                $payload['id_order'],
                $payload['order_reference'],
                'REQPED',
                $message,
                json_encode([
                    'url' => $orders_url,
                    'payload' => $payload['data'],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $orders_url
            );

            // llamada API Warehouse
            $outGoingOrderResult = $APIRouter->sendOutgoingOrders($payload);
            
            if (!is_null($outGoingOrderResult) && !isset($outGoingOrderResult['error'])) {
                $resultAPIDecoded = json_decode($outGoingOrderResult['output']);

                // anotación en la tabla de log: wp_correos_ecom_sga_orders_log
                $message = "Respuesta de la llamada a la API";
                $info   = json_encode($outGoingOrderResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'RESPED', $message, $info);
            } else if(isset($outGoingOrderResult['codigoRetorno']) && $outGoingOrderResult['codigoRetorno'] != 0) {
                // anotación en la tabla de log: wp_correos_ecom_sga_orders_log
                $message = "Error en la llamada a la API. Código de retorno";

                self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $message , $outGoingOrderResult['mensajeRetorno']);
                return ['type' => 'error', 'message' => $outGoingOrderResult['mensajeRetorno']];
            }

            // Obtener estados de la configuración
            $order_status_OK_obj = new CorreosOficialConfig('SGA_STATUS_OK');
            $order_status_OK = $order_status_OK_obj->get_value();
            
            $order_status_KO_obj = new CorreosOficialConfig('SGA_STATUS_KO');
            $order_status_KO = $order_status_KO_obj->get_value();
            
            // Si los valores están vacíos, usar los de la constante como fallback
            if (empty($order_status_OK) || empty($order_status_KO)) {
                $sga_states = CORREOS_OFICIAL_SGA_ORDER_STATES;
                if (empty($order_status_OK)) {
                    $order_status_OK = $sga_states[1]['value'];
                }
                if (empty($order_status_KO)) {
                    $order_status_KO = $sga_states[2]['value'];
                }
            }

            // PEDIDO CON RESPUESTA OK o KO
            // La API devuelve 201 para pedidos nuevos y 200 cuando el pedido ya existe
            if (isset($outGoingOrderResult['status']) && in_array((int) $outGoingOrderResult['status'], [200, 201])) {
                CorreosOficialHelpers::writeToLog('sga', 'Conectividad con servicio Sislog OK: ' . $outGoingOrderResult['status']);
                
                $order_status = $order_status_OK;

                // Operaciones de KO
                if ($resultAPIDecoded->status == 'KO') {

                    $is_warehouse_error = isset($resultAPIDecoded->message) && 
                        (strpos($resultAPIDecoded->message, 'no existe el almac') !== false || 
                         strpos($resultAPIDecoded->message, 'Almacen no informado') !== false);
                    
                    // Si el pedido ya existe, entonces no es error
                    if($resultAPIDecoded->message != 'KO:El pedido ya existe.') {
                        $order_status = $order_status_KO;
                        
                        if ($is_warehouse_error) {
                            $error_message = 'Error en almacén SGA: ' . $resultAPIDecoded->message . ' - ID Pedido: ' . $payload['id_order'];
                            CorreosOficialHelpers::writeToLog('sga', $error_message);
                            self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $error_message, json_encode($resultAPIDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            
                            $wc_order->update_status($order_status_KO);
                            $wc_order->add_order_note('Error SGA: ' . $resultAPIDecoded->message);
                            
                            return ['type' => 'error', 'message' => 'Error: ' . $resultAPIDecoded->message];
                        }
                    } else {
                        // Si ya existe Guardamos en la tabla wp_correos_ecom_sga_orders_status
                        $sga_order_status = new CorreosOficialSgaOrdersStatus($payload['id_order']);
                        $sga_order_status->set_status($order_status);
                        $sga_order_status->set_timestamp(date('Y-m-d H:i:s'));
                        $sga_order_status->save();
                    }
                } else if($resultAPIDecoded->status == 'OK') {
                    // Operaciones de OK
                    // Guardamos en la tabla wp_correos_ecom_sga_orders_status
                    $sga_order_status = new CorreosOficialSgaOrdersStatus($payload['id_order']);
                    $sga_order_status->set_status($order_status);
                    $sga_order_status->set_timestamp(date('Y-m-d H:i:s'));
                    $sga_order_status->set_company_transport($payload['companyTransport']);
                    $sga_order_status->create();
                }

                // Le asignamos al pedido el estado por defecto de SGA, o el que se haya configurado en el módulo
                $wc_order->update_status($order_status);
                
                // Devolver éxito
                $success_message = isset($resultAPIDecoded->message) ? $resultAPIDecoded->message : 'Pedido enviado al almacén correctamente';
                return ['type' => 'success', 'message' => $success_message, 'data' => $outGoingOrderResult];
            } else if (isset($outGoingOrderResult['status']) && $outGoingOrderResult['status'] == 400) {
                $order_status = $order_status_KO;
                $wc_order->update_status($order_status);
                $message = __(' Status: 400. The information sent to the API WAREHOUSE is not correct', 'correosoficial');
                self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $message);
                return ['type' => 'error', 'message' => $message];
            }
            
            else {
                $error_message = ' - ID Pedido: ' . $payload['id_order'] . ' - ' . '- Error 11010: Error de conectividad con servicio Sislog Status Code: ' . ($outGoingOrderResult['status'] ?? 'unknown');
                CorreosOficialHelpers::writeToLog('sga', $error_message);
                CorreosOficialHelpers::writeToCronErrorLog($error_message);
                self::writeToOrdersLog($payload['id_order'], $payload['order_reference'], 'ERROR', $error_message);

                return ['type' => 'error', 'message' => $error_message, 'data' => $outGoingOrderResult];
            }
            // **** fin RN60 **** se comprueba stock disponible de los artículos del pedido antes de la llamada addOutgoingOrder
        } 
    }

    public static function cancelOutgoingOrder($payload) {
        $APIRouter = new CorreosOficialApiRouter();
        $sga_rest = new CorreosOficialSGARest();

        $wc_order = wc_get_order($payload['id_order']);
        $order_number = (int) $wc_order->get_order_number();

        $payload['reference'] = empty($order_number) ? $payload['id_order'] : $order_number;
        $payload['ownerid']   = ( new CorreosOficialConfig('SGAOwner') )->get_value();
        $payload['clientid']  = ( new CorreosOficialConfig('SGACustomer') )->get_value();
        $payload['warehouse'] = ( new CorreosOficialConfig('SGAStore') )->get_value();

        $cancel_query = [
            'orderType' => 'PS',
            'warehouse' => $payload['warehouse'],
        ];
        $cancel_url = $sga_rest->getApiBaseUrl() . '/warehouse/owners/' . urlencode($payload['ownerid']) . '/clients/' . urlencode($payload['clientid']) . '/outgoing-orders/' . urlencode($payload['id_order']) . '?' . http_build_query($cancel_query);

        self::writeToOrdersLog(
            $payload['id_order'],
            $payload['reference'],
            'DELETE',
            'Url cancelación de envio SGA: ' . $cancel_url,
            json_encode([
                'payload' => $cancel_query,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $resultAPI = $APIRouter->cancelOutgoingOrder($payload);
        $output = json_decode($resultAPI['output']);
        $type = 'success';

        // Verificar mensajes que deben tratarse como cancelación exitosa
        $is_cancelable_error = isset($output->message) && (
            $output->message === 'KO:Almacen no informado en fichero' ||
            $output->message === 'KO:El pedido no existe.' ||
            strpos($output->message, 'no existe el almac') !== false
        );

        if (is_null($resultAPI) || $resultAPI['codigoRetorno'] == 1 || (isset($output->status) && $output->status == 'KO' && !$is_cancelable_error)) {
            $msg = 'Petición para cancelar envío de pedido: ' . $payload['id_order'] . ' - ' . gmdate('Y-m-d H:i:s') . "\n";
            $msg .= ' Error al conectar con el servicio Sislog.';
            self::writeToOrdersLog($payload['id_order'], $payload['reference'], 'DELETE', $msg . '\n' . $cancel_url, $resultAPI['output']);
            $type = 'error';
        } else if ($resultAPI['codigoRetorno'] == 0 || $is_cancelable_error) {
            $msg  = 'Petición para cancelar envío de pedido: ' . $payload['id_order'] . ' - ' . gmdate('Y-m-d H:i:s') . "\n";
            $msg .= 'Conectividad con servicio Sislog OK: ' . $resultAPI['code'] . "\n";

            // Verificamos si el pedido existe en la tabla SGA
            $sga_order_exists = CorreosOficialSgaOrdersStatus::existsByOrderId($payload['id_order']);
            
            // Eliminamos el pedido de la tabla de pedidos enviados a SGA (si existe)
            if ($sga_order_exists) {
                if (CorreosOficialSgaOrdersStatus::deleteByOrderId($payload['id_order'])) {
                    $msg .= 'Pedido cancelado correctamente en SGA';
                    if ($is_cancelable_error) {
                        $msg .= ' (' . $output->message . ' - cancelación automática)';
                    }
                    CorreosOficialHelpers::writeToLog('sga', 'Pedido eliminado de la tabla de pedidos enviados a SGA, ID Pedido: ' . $payload['id_order']);
                    $type = 'success';
                } else {
                    $msg .= ' Error al eliminar el pedido de la tabla de pedidos enviados a SGA';
                    CorreosOficialHelpers::writeToLog('sga', 'Error al eliminar el pedido de la tabla de pedidos enviados a SGA, ID Pedido: ' . $payload['id_order']);
                    $type = 'error';
                }
            } else {
                // El pedido no está en la tabla SGA (nunca se envió), consideramos exitosa la cancelación
                $msg .= 'Pedido cancelado correctamente (el pedido no había sido enviado a SGA)';
                if ($is_cancelable_error) {
                    $msg .= ' (' . $output->message . ')';
                }
                
                CorreosOficialHelpers::writeToLog('sga', 'Pedido no estaba en la tabla SGA, cancelación exitosa, ID Pedido: ' . $payload['id_order']);
                $type = 'success';
            }

            self::writeToOrdersLog($payload['id_order'], $payload['reference'], 'DELETE', $msg, $resultAPI['output']);
        }
        
        return  array (
            'message' => $msg,
            'type'    => $type
        );
    }

    public static function updateAllOrdersStatuses($payload) {
        $APIRouter = new CorreosOficialApiRouter();
       
        // Configuración de la fecha actual y restar días de forma segura
        $timezone         = get_option('timezone_string') ? get_option('timezone_string') : 'UTC';
        $today            = new DateTime('now', new \DateTimeZone($timezone));
        $fromO_order_date = $today->modify('-120 days')->format('Y-m-d\TH:i:s\Z');

        $to_order_date = (new DateTime('now', new \DateTimeZone($timezone)))->format('Y-m-d\TH:i:s\Z');

        $payload['fromOrderDate'] = $fromO_order_date;
        $payload['toOrderDate']   = $to_order_date;

        // llamada a la api warehouse
        $resultAPI = $APIRouter->findOutgoingOrdersSituation($payload);

        $resultAPI_decoded = json_decode($resultAPI['output']);
        $outgoing_orders = ( $resultAPI_decoded->responseStatus->totalResults ) ? $resultAPI_decoded->data->outgoingOrders : null;


        if ($resultAPI['status'] == 200) {
            if ($outgoing_orders) {
                foreach ($outgoing_orders as $order) {
                    //guardamos en el Log del Cron de Seguimiento de Estado de Pedido
                    CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('Llamada de estado del pedido: [' . $order->externalOrder . ']');
                    CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('Retorno de estado de Pedido:[' . $order->orderSituation . ']');

                    // pedido de woocommerce
                    $wc_order = wc_get_order($order->externalOrder);

                    if ($wc_order) {
                        // Si el pedido cambia de estado y el estado al que cambia es EX
                        if ($order->orderSituation === 'EX') {
                            $current_state = $wc_order->get_status();
                            $order_status_EX = ( new CorreosOficialConfig('SGAOrderStatusTrackingEX') )->get_value();
                            $order_status_EX = str_starts_with($order_status_EX, 'wc-') ? substr($order_status_EX, 3) : $order_status_EX;

                            // Si el estado del pedido en woocommerce es diferente al estado EX
                            if ($current_state != $order_status_EX) {

                                // si el pedido está en EX (completado) se recupera el código de envío
                                // establecer el código del envío en el pedido actualizándolo con el valor firstBulkDispatch del primer bulto
                                if (is_null($order->firstBulkDispatch) || empty($order->firstBulkDispatch)) {
                                    CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('ERROR: No existe firstBulkDispatch en el pedido [' . $order->externalOrder . ']');
                                } else {
                                    // código del envío en el pedido --> Número de seguimiento, del Shipment Tracking
                                    $sga_order_status = new CorreosOficialSgaOrdersStatus($order->externalOrder);
                                    $tracking_number = $order->firstBulkDispatch;
                                    $order_reference = $wc_order->get_order_number();

                                    // Actualizamos el pedido con el número de seguimiento si no está guardado aún
                                    $sga_order_shipping_number = $sga_order_status->get_shipping_number();

                                    if ( ($sga_order_shipping_number == null || $sga_order_shipping_number == '') ) {
                                        $message = 'Seguimiento de Estado de Pedido - Nuevo estado del Pedido: ' . $order_status_EX;
                                        self::writeToOrdersLog($order->externalOrder, $order_reference, 'APIOST', $message);

                                        $sga_order_status->set_shipping_number($tracking_number);
                                        $sga_order_status->set_status('wc-' . $order_status_EX);
                                        $sga_order_status->save();

                                        // Actualizamos estado del pedido en woocommerce
                                        $wc_order = wc_get_order($order->externalOrder);
                                        $wc_order->update_status($order_status_EX, 'Estado actualizado por SGA');

                                        CorreosOficialHelpers::writeToOrderStatusTrackingCronLog("Pedido: [{$order->externalOrder}] Estado: [{$current_state}] | Estado SGA: [{$order->orderSituation}] | Estado final: [{$order_status_EX}]");
                                    } else {
                                        CorreosOficialHelpers::writeToOrderStatusTrackingCronLog("Pedido: [{$order->externalOrder}] Estado [{$current_state}] no pudo actualizarse. Queda pendiente de cambio por seguimiento en el módulo de transporte.");
                                    }
                                }
                            }
                        } else { // todo lo que no sea EX lo tratamos como PE
                            // si el pedido tiene más de 20 días,  actualización en el historial del pedido indicando "Expirado plazo de consulta de preparación"
                            $order_date   = new DateTime($order->orderDate);
                            $current_date = new DateTime();
                            $difference   = $order_date->diff($current_date)->days;

                            if ($difference > 20) {
                                //añadir el mensaje en el historial del pedido
                                $wc_order->add_order_note('Expirado plazo de consulta de preparación.');
                            } else {
                                // mapeo de PE
                                $order_status_PE = ( new CorreosOficialConfig('SGAOrderStatusTrackingPE') )->get_value();
                                $wc_order->update_status($order_status_PE);
                            }
                        }
                    } else { //si existe el pedido...
                        CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('No existe el pedido [' . $order->externalOrder . ']');
                    }
                }
            } else {
                $message = 'SISLOG no ha encontrado pedidos en la llamada a FindOutgoingOrdersSituation';
                CorreosOficialHelpers::writeToLog('sga,', $message);
                CorreosOficialHelpers::writeToOrderStatusTrackingCronLog($message);
            }
        } else {
            $error = 'Error 11012: Error de conectividad con servicio Sislog Status Code: ' . $resultAPI['status'];
            CorreosOficialHelpers::writeToLog('sga,', $error);
            CorreosOficialHelpers::writeToOrderStatusTrackingCronLog($error);
            CorreosOficialHelpers::writeToCronErrorLog($error);
        }
        
        CorreosOficialHelpers::writeToLog('sga,', wp_json_encode($resultAPI, JSON_PRETTY_PRINT));
        CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('Fin actualización estados de pedidos.');

        CorreosOficialHelpers::writeToOrderStatusTrackingCronLog('Fin de la ejecución de Seguimiento de Estado de Pedido.');
        CorreosOficialHelpers::writeToLog('sga', 'Fin Seguimiento de Estado de Pedido.');
    }

    /**
     * Valida la conexión con la plataforma SGA (Sislog).
     *
     * Logística NO depende de un remitente
     * Para autenticar, usa la primera cuenta Correos disponible en la tabla codes.
     * Si no hay ninguna cuenta Correos, no se puede validar y se devuelve null.
     *
     * @return true   conexión OK
     * @return false  credenciales SGA incorrectas
     * @return null   no hay cuenta Correos configurada (no se puede validar)
     */
    public static function validateConnection($payload) {
        CorreosOficialHelpers::writeToLog('sga', 'Validando conexión con la plataforma logística.');

        // Obtener cualquier cuenta Correos de la BD (no se necesita remitente).
        $correos_codes = CorreosOficialCode::get_by_company('Correos');

        if (empty($correos_codes)) {
            CorreosOficialHelpers::writeToLog(
                'sga',
                'validateConnection: No hay cuenta Correos configurada. Validación SGA omitida (independiente de Transporte).'
            );
            // null indica "sin cuenta" — el guardado de ajustes puede continuar.
            return null;
        }

        // Usar la primera cuenta disponible para obtener el token OAuth.
        $first_code           = $correos_codes[0];
        $payload['client']    = [
            'CorreosClientID' => $first_code['CorreosClientID'],
            'CorreosSecretID' => $first_code['CorreosSecretID'],
        ];

        $sga_rest  = new CorreosOficialSGARest();
        $resultAPI = $sga_rest->getProductStockBySKU($payload, false, true);

        if (isset($resultAPI['status']) && $resultAPI['status'] == 200) {
            CorreosOficialHelpers::writeToLog('sga', 'Conectividad con servicio Sislog OK: ' . $resultAPI['status']);
            return true;
        }

        $status = isset($resultAPI['status']) ? $resultAPI['status'] : 'sin respuesta';
        $error  = 'Error 11011: Error de conectividad con servicio Sislog Status Code: ' . $status;
        CorreosOficialHelpers::writeToLog('sga', $error);
        CorreosOficialHelpers::writeToCronErrorLog($error);
        return false;
    }

    public static function updateAllProductsStock($payload) {
        $APIRouter = new CorreosOficialApiRouter();


        CorreosOficialHelpers::writeToLog('sga', 'Actualizando stock con la plataforma logística');

        // Obtener todos los productos publicados de la tienda
        $updateStockResult = $APIRouter->getProductStockBySKU($payload, true);
        
        if (is_null($updateStockResult) || isset($updateStockResult['error'])) {
            $message = 'Error al conectar con el servicio Sislog. No hay respuesta del servicio [Cron Update Stock]';
            CorreosOficialHelpers::writeToLog('sga', $message);
            CorreosOficialHelpers::writeToCronErrorLog($message);

        } elseif ($updateStockResult['status'] == 200) {
            CorreosOficialHelpers::writeToLog('sga', 'Conectividad con servicio Sislog OK: ' . $updateStockResult['status']);

            $update_stock_result_decoded = json_decode($updateStockResult['output']);

            $warehouse_stock = $update_stock_result_decoded->data->stockCheckItem ?? [];

            if (!empty($warehouse_stock)) {
                foreach ($warehouse_stock as $item) {
                    if (!is_null($item->externalArticle)) {
                        $id = wc_get_product_id_by_sku($item->externalArticle);
                        $store_product = wc_get_product($id);

                        if (!empty($store_product)) {
                            self::updateStoreProductStock($store_product, $item->quantityAvailable);
                            CorreosOficialHelpers::writeToUpdateStockCronLog('Actualizado stock del producto con SKU[' . $item->externalArticle . '] - Su stock ha cambiado a [' . $item->quantityAvailable . '] unidades.');
                            CorreosOficialHelpers::writeToUpdateStockCronLog('Fin Actualización de Stock');
                        }
                    }
                }
            }
        } elseif ($updateStockResult['status'] != 200) {
            $message = 'Error 11013: Error de conectividad con servicio Sislog Status Code: ' . $updateStockResult['status'] . ' [Cron Update Stock]';
            CorreosOficialHelpers::writeToLog('sga', $message);
            CorreosOficialHelpers::writeToCronErrorLog($message);
        }
    }

    public static function getOrderLogs($id_order) {
        // instanciamos order_log, y utilizamos getLogsByIdOrder() para obtener array de todos los logs del pedido
        $order_logs = new CorreosOficialSGAOrdersLog($id_order);
        wp_send_json($order_logs->getLogsByIdOrder(), 200);
    }

    public static function updateStoreProductStock( $storeProduct, $quantity ) {
		$storeProduct->set_manage_stock(true);
		$intQuantity = intval($quantity);

		if ($intQuantity < 0) {
			$storeProduct->set_stock_status('outofstock');
		} else {
			$storeProduct->set_stock_status('instock');
		}

		wc_update_product_stock($storeProduct, $quantity, 'set', 'false');

		$storeProduct->save();
	}

    public static function showSGAMessage($message, $type = 'success') {
		// Solo permitimos 'success' o 'error'
		$type = in_array($type, ['success', 'error']) ? $type : 'success';

		// Solo hacer redirect si estamos en el admin y NO durante AJAX o checkout
		if ( is_admin() && ! wp_doing_ajax() && ! is_checkout() ) {
			$query_arg = ($type === 'success') ? 'correosecomsga_message' : 'correosecomsga_error';

			$redirect_url = add_query_arg(
				$query_arg,
				urlencode($message),
				admin_url('edit.php?post_type=shop_order')
			);

			wp_safe_redirect($redirect_url);
			exit;
		} else {
			// Durante checkout o AJAX, solo registrar en log, no interrumpir
			CorreosOficialHelpers::writeToLog( 'sga', "Mensaje SGA ({$type}): {$message}" );
		}
	}

        /**
     * Método para escribir en la tabla `correos_oficial_sga_orders_log`
     * que es donde se guarda el historial de las órdenes de pedido
     *
     * @param int $idOrder El ID de la orden.
     * @param string $order_reference La referencia de la orden.
     * @param string $action La acción realizada
     * @param string|null $message (Opcional) Mensaje adicional relacionado con la acción.
     * @param string|null $info (Opcional) Información adicional relacionada con la acción. Viene en formato JSON.
     * @param string|null $url (Opcional) URL de la llamada a la API
     */
    /**
     * Aplica al pedido WC el estado configurado para errores SGA.
     *
     * Si la configuración 'SGAOrderStatusTrackingError' está vacía (caso típico
     * cuando el cliente todavía no ha guardado los ajustes SGA), hacemos fallback
     * al estado por defecto SGA_STATUS_KO ('wc-kocorreosecomsga') para que el
     * pedido no se quede en 'Procesando' y aparezca como
     * "Sin enviar Almacén Correos" como sucedía antes de la migración.
     *
     * @param \WC_Order $wc_order
     * @param string    $note  Nota a añadir al pedido al cambiar de estado.
     */
    private static function applySgaErrorStatus( $wc_order, string $note = '' ): void
    {
        $sga_error_status = ( new CorreosOficialConfig('SGAOrderStatusTrackingError') )->get_value();

        if ( empty( $sga_error_status ) ) {
            $sgastates_map    = array_column( CORREOS_OFICIAL_SGA_ORDER_STATES, 'value', 'alias' );
            $sga_error_status = $sgastates_map['SGA_STATUS_KO'] ?? 'wc-kocorreosecomsga';
        }

        // WooCommerce espera el slug sin el prefijo 'wc-' en set_status().
        $sga_error_status_slug = str_starts_with( $sga_error_status, 'wc-' )
            ? substr( $sga_error_status, 3 )
            : $sga_error_status;

        $wc_order->set_status( $sga_error_status_slug, $note );
        $wc_order->save();
    }

    public static function writeToOrdersLog($order_id, $order_reference, $action, $message=null, $info=null, $url=null)
    {
        $info = ($info === null) ? json_encode(null) : $info;

        $sgaOrdersLog = new CorreosOficialSGAOrdersLog();
        $sgaOrdersLog->set_order_id($order_id);
        $sgaOrdersLog->set_order_ref($order_reference);
        $sgaOrdersLog->set_timestamp(date('Y-m-d H:i:s'));
        $sgaOrdersLog->set_action($action);
        $sgaOrdersLog->set_message($message);
        $sgaOrdersLog->set_info($info);
        $sgaOrdersLog->set_url($url);
        $sgaOrdersLog->create();
    }
}