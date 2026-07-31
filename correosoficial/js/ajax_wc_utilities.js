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
 * Version 3.0
 */
jQuery(document).ready(function () {
    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                              GESTIÓN MASIVA DE PEDIDOS                               //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    /**
     * Generacion de envio y recogida.
     * Generación de etiqueta desde panel "Gestion Masiva Pedidos" e Impresion.
     * DESHABILITADO - Ahora se usa procesamiento secuencial en utilities.js
     */
    /*
    jQuery('#generateOrdersButton').on('click', function () {
        jQuery('#processingOrdersButtonMsg').removeClass('hidden-block');
        jQuery('#generateOrdersButtonMsg').addClass('hidden-block');

        let msgErrors_package_size = '';
        let msgErrors_packages = '';

        if (jQuery('#inputCheckSavePickup').is(':checked')) {
            var selectedGrabarRecogida = 'S';
        } else {
            var selectedGrabarRecogida = 'N';
        }

        if (jQuery('#inputCheckPrintLabel').is(':checked')) {
            var selectedImprimirEtiqueta = 'S';
        } else {
            var selectedImprimirEtiqueta = 'N';
        }

        let selectedTamanioPaquete = jQuery('#input_tamanio_paquete').val();

        let selectedData = tableRegOrders.rows({ selected: true }).data().toArray();

        selectedData.forEach(function (valor, indice, array) {
            array[indice].mod_product = jQuery('#select_option_' + array[indice].id_order).val();
            array[indice].bultos = jQuery('#input_text_' + array[indice].id_order).val();
            array[indice].AT_code = jQuery('#AT_code' + array[indice].id_order).val();
            array[indice].sender_default = jQuery('#sender_option_' + array[indice].id_order).val();
            array[indice].sender_iso_code = jQuery('#sender_option_' + array[indice].id_order + ' option:selected').data('iso');
            array[indice].senders = null; // Limpiamos array de senders

            if (selectedGrabarRecogida == 'S') {
                if (selectedTamanioPaquete == 0 && array[indice].carrier_type == 'Correos') {
                    msgErrors_package_size = msgErrors_package_size + array[indice].id_order + ' Seleccione un tamaño de paquete para la recogida <br />';
                }
            }

            // Si se ha seleccionado un carrier del select -> comprobamos máximo de bultos
            if (array[indice].mod_product != null) {
                htmlObject = jQuery('#select_option_' + array[indice].id_order);
                let selected_carrier = htmlObject.find('option:selected');
                let max_packages_carrier_selected = selected_carrier.data('max-packages');

                // Cambiamos el producto por el seleccionado
                let mod_company = selected_carrier.data('company');
                array[indice].company = mod_company;

                array[indice].id_product = jQuery('#select_option_' + array[indice].id_order).val();
                if (Number.parseInt(array[indice].bultos) > Number.parseInt(max_packages_carrier_selected)) {
                    msgErrors_packages = msgErrors_packages + array[indice].id_order + ' ' + parcelMaxForthisProduct + ' ' + max_packages_carrier_selected + '<br />';
                }
            } else {
                if (array[indice].id_product != null) {
                    if (Number.parseInt(array[indice].bultos) > Number.parseInt(array[indice].max_packages)) {
                        msgErrors_packages = msgErrors_packages + array[indice].id_order + ' ' + parcelMaxForthisProduct + ' ' + array[indice].max_packages + '<br />';
                    }
                } else {
                    array[indice].id_product = array[indice].id_product_custom;
                    if (Number.parseInt(array[indice].bulto) > Number.parseInt(array[indice].max_packages_custom)) {
                        msgErrors_packages = msgErrors_packages + array[indice].id_order + ' ' + parcelMaxForthisProduct + ' ' + array[indice].max_packages_custom + '<br />';
                    }
                }
            }
        });

        if (msgErrors_package_size != '') {
            showModalInfoWindow(msgErrors_package_size);
        } else if (msgErrors_packages != '') {
            showModalInfoWindow(msgErrors_packages);
        } else {
            let PickupDateRegister = jQuery('#PickupDateRegister').val();
            let PickupFromRegister = jQuery('#PickupFromRegister').val();
            let PickupToRegister = jQuery('#PickupToRegister').val();

            if (selectedData.length > 0) {

                jQuery('#generateOrdersButton').prop('disabled', true);
                jQuery.ajax({
                    type: 'post',
                    url: varsAjax.ajaxUrl,
                    data: {
                        _nonce: varsAjax.nonce,
                        action: 'correosOficialDispacher',
                        dispatcher: {
                            controller: 'AdminCorreosOficialUtilitiesProcess',
                            action: 'registerOrders',
                            selectedData: selectedData,
                            selectedGrabarRecogida: selectedGrabarRecogida,
                            selectedImprimirEtiqueta: selectedImprimirEtiqueta,
                            selectedTamanioPaquete: selectedTamanioPaquete,
                            PickupDateRegister: PickupDateRegister,
                            PickupFromRegister: PickupFromRegister,
                            PickupToRegister: PickupToRegister,
                        },
                    },
                    success: function (parsed_data) {

                        jQuery('#generateOrdersButton').prop('disabled', false);
                        jQuery('#reg_orders_errors_container').hide();
                        jQuery('#input_tipo_etiqueta_container_gestion').hide();
                        jQuery('#print_label_reg_container').hide();

                        let errorOrders = [];
                        let validOrders = []

                        // Separamos pedidos procesados y con error
                        parsed_data.forEach(order => {

                            // Si existe mensajeRetornoPick, lo convertimos en mensajeRetorno
                            if (order?.mensajeRetornoPick) {
                                order.mensajeRetorno = order.mensajeRetornoPick;
                            }

                            // Clasificación de errores
                            if (
                                (order?.codigoRetorno >= 1) ||
                                (order?.codigoRetorno == -1) ||
                                (order?.codigoRetornoPick >= 1)
                            ) {
                                errorOrders.push(order);
                            } else {
                                validOrders.push(order);
                            }
                        });

                        // Tratamos pedidos con error
                        if (errorOrders.length > 0) {
                            table_errors_reg_orders.clear().draw();
                            table_errors_reg_orders.rows.add(errorOrders);
                            table_errors_reg_orders.columns.adjust().draw();
                            jQuery('#reg_orders_errors_container').show();
                            jQuery('#processingOrdersButtonMsg').addClass('hidden-block');
                            jQuery('#generateOrdersButtonMsg').removeClass('hidden-block');
                        } 

                        // Tratamos pedidos pre-registrados
                        if (validOrders.length > 0) {
                            jQuery('#input_tipo_etiqueta_container_gestion').show();
                            jQuery('#print_label_reg_container').show();

                            //ImprimirEtiquetasButton
                            jQuery('#printLabelsGenerated').on('click', function () {
                                // Supongamos que parsed_data es un array de objetos
                                let selectedDataReimpresion = [];

                                // Usar forEach para recorrer parsed_data
                                parsed_data.forEach(order => {
                                    if (order['order_data']) {
                                        selectedDataReimpresion.push(order['order_data']);
                                    }
                                });

                                // Ahora selectedDataReimpresion contendrá todos los order_data
                                let selectedTipoEtiquetaReimpresion = jQuery('#input_tipo_etiqueta_gestion').val();
                                let selectedFormatEtiquetaReimpresion = jQuery('#input_format_etiqueta_gestion').val();
                                let selectedPosicionEtiquetaReimpresion = jQuery('#input_pos_etiqueta_gestion').val();

                                // Compatibilidad de etiquetas
                                if (!checkCEXLabelFormat(selectedDataReimpresion, selectedFormatEtiquetaReimpresion)) {
                                    return;
                                } else {
                                    jQuery('#ProcessingprintLabelsGeneratedButton').removeClass('hidden-block');
                                    jQuery('.label-message').addClass('hidden-block');
                                }

                                jQuery.ajax({
                                    type: 'post',
                                    url: varsAjax.ajaxUrl,
                                    data: {
                                        action: 'correosOficialDispacher',
                                        _nonce: varsAjax.nonce,
                                        dispatcher: {
                                            controller: 'AdminCorreosOficialUtilitiesProcess',
                                            action: 'printLabelsGenerated',
                                            selectedDataReimpresion: selectedDataReimpresion,
                                            selectedTipoEtiquetaReimpresion: selectedTipoEtiquetaReimpresion,
                                            selectedFormatEtiquetaReimpresion: selectedFormatEtiquetaReimpresion,
                                            selectedPosicionEtiquetaReimpresion: selectedPosicionEtiquetaReimpresion,
                                        },
                                    },
                                    success: function (parsed_data) {
                                        let errorLabels = [];
                                        let validLabels = [];

                                        // Separamos etiquetas válidad y con error
                                        parsed_data.forEach(label => {
                                            if (
                                                (label?.codigoRetorno >= "1") ||
                                                (label?.codigoRetornoPick >= "1")
                                            ) {
                                                errorLabels.push(label);
                                            }else{
                                                validLabels.push(label);
                                            }
                                        });

                                        // Tratamos pedidos con error
                                        if (errorLabels.length > 0) {
                                            table_errors_reg_orders.rows.add(errorLabels);
                                            table_errors_reg_orders.columns.adjust().draw();
                                            jQuery('#ProcessingprintLabelsGeneratedButton').removeClass('hidden-block');
                                        } 
                                        
                                        if (validLabels.length > 0) {
                                            validLabels[0]['filePath'].forEach(path => {
                                                printGeneratedLabels(path, co_path_to_module);
                                            });
                                            jQuery('#ProcessingprintLabelsGeneratedButton').addClass('hidden-block');
                                            jQuery('.label-message').removeClass('hidden-block');
                                        }
                                    },
                                });
                            });
                            
                            jQuery('#processingOrdersButtonMsg').addClass('hidden-block');
                            jQuery('#generateOrdersButtonMsg').removeClass('hidden-block');

                            // Para refrescar la tabla hay que volver a llamar a ajax
                            // con la misma co_fecha seleccionada en los inputs de búsqueda
                            let data_search = {
                                FromDateOrdersReg: jQuery('#inputFromDateOrdersReg').val(),
                                ToDateOrdersReg: jQuery('#inputToDateOrdersReg').val(),
                            };

                            if (new Date(data_search.ToDateOrdersReg).getTime() < new Date(data_search.FromDateOrdersReg).getTime()) {
                                showModalInfoWindow(dateFromIsMinor);
                            } else {
                                jQuery('#GestionDataTable').DataTable().ajax.reload();
                                let el = jQuery('#table-select-all').get(0);
                                if (el && el.checked && 'indeterminate' in el) {
                                    el.indeterminate = true;
                                }
                            }
                        } else {
                            let data_search = {
                                FromDateOrdersReg: jQuery('#inputFromDateOrdersReg').val(),
                                ToDateOrdersReg: jQuery('#inputToDateOrdersReg').val(),
                            };

                            if (new Date(data_search.ToDateOrdersReg).getTime() < new Date(data_search.FromDateOrdersReg).getTime()) {
                                showModalInfoWindow(dateFromIsMinor);
                            } else {
                                jQuery('#GestionDataTable').DataTable().ajax.reload();
                                let el = jQuery('#table-select-all').get(0);
                                if (el && el.checked && 'indeterminate' in el) {
                                    el.indeterminate = true;
                                }
                            }
                        }
                    },
                });
                jQuery('#generateOrdersButton').prop('disabled', false);
            } else {
                jQuery('#processingOrdersButtonMsg').addClass('hidden-block');
                jQuery('#generateOrdersButtonMsg').removeClass('hidden-block');
                showModalInfoWindow(mustSelectOneRecord);
            }
        }
    });
    */

    /**
     * Handler independiente para imprimir etiquetas generadas
     * Funciona con los pedidos mostrados en la tabla de resultados después del pre-registro
     */
    jQuery(document).on('click', '#printLabelsGenerated', function () {
        // Obtener datos de los pedidos exitosos desde la tabla de resultados
        let selectedDataReimpresion = [];
        
        // Obtener todas las filas de la tabla de resultados que tengan número de envío
        const tableData = table_errors_reg_orders.data().toArray();
        
        tableData.forEach(order => {
            // Solo incluir pedidos exitosos (codigoRetorno == 0)
            if ((order.codigoRetorno == 0 || order.codigoRetorno === '0') && order) {
                let shippingNumber = '';
                
                if (order.order_data && order.order_data.carrier_type == 'CEX') {
                    if (order.order_data.shipping_number && order.order_data.shipping_number !== '') {
                        shippingNumber = order.order_data.shipping_number;
                    }
                } else {
                    // Obtener el número de envío desde diferentes ubicaciones posibles
                    if (order.bultos && order.bultos.length > 0 && order.bultos[0].shipping_number) {
                        shippingNumber = order.bultos[0].shipping_number;
                    } else if (order.exp_number) {
                        shippingNumber = order.exp_number;
                    } else if (order.numeroEnvio) {
                        shippingNumber = order.numeroEnvio;
                    }
                }
                
                if (shippingNumber) {
                    // Necesito obtener los datos completos del pedido original desde la tabla principal
                    const originalOrder = tableRegOrders.data().toArray().find(orig => 
                        (orig.id_order == order.orderId || orig.id_order == order.id_order)
                    );
                    
                    // Prioridad: originalOrder > order.order_data > valores por defecto
                    const orderData = order.order_data || {};
                    
                    if (originalOrder) {
                        selectedDataReimpresion.push({
                            id_order: originalOrder.id_order,
                            reference: originalOrder.reference || originalOrder.id_order,
                            saved_sender: originalOrder.saved_sender || '',
                            sender_default: originalOrder.sender_default || '1',
                            company: originalOrder.carrier_type || 'Correos',
                            carrier_type: originalOrder.carrier_type || 'Correos',
                            id_product: originalOrder.id_product,
                            shipping_number: shippingNumber,
                            first_shipping_number: shippingNumber,
                            exp_number: shippingNumber
                        });
                    } else {
                        // Usar order_data del resultado del pre-registro (tiene los datos reales del envío)
                        selectedDataReimpresion.push({
                            id_order: order.orderId || order.id_order || orderData.id_order,
                            reference: (order.reference || orderData.reference || order.orderId || order.id_order || '').toString(),
                            saved_sender: orderData.id_sender || '',
                            sender_default: '1', 
                            company: orderData.carrier_type || 'Correos',
                            carrier_type: orderData.carrier_type || 'Correos',
                            id_product: orderData.id_product || '18',
                            shipping_number: shippingNumber,
                            first_shipping_number: shippingNumber,
                            exp_number: shippingNumber
                        });
                    }
                }
            }
        });

        console.log('Datos para impresión:', selectedDataReimpresion); // Debug

        if (selectedDataReimpresion.length === 0) {
            showModalInfoWindow('No hay pedidos pre-registrados exitosos para imprimir etiquetas');
            return;
        }

        let selectedTipoEtiquetaReimpresion = jQuery('#input_tipo_etiqueta_gestion').val();
        let selectedFormatEtiquetaReimpresion = jQuery('#input_format_etiqueta_gestion').val();
        let selectedPosicionEtiquetaReimpresion = jQuery('#input_pos_etiqueta_gestion').val();

        // Compatibilidad de etiquetas
        if (typeof checkCEXLabelFormat === 'function' && !checkCEXLabelFormat(selectedDataReimpresion, selectedFormatEtiquetaReimpresion)) {
            return;
        }

        jQuery('#ProcessingprintLabelsGeneratedButton').removeClass('hidden-block');
        jQuery('.label-message').addClass('hidden-block');

        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: {
                action: 'correosOficialDispacher',
                _nonce: varsAjax.nonce,
                dispatcher: {
                    controller: 'AdminCorreosOficialUtilitiesProcess',
                    action: 'printLabelsGenerated',
                    selectedDataReimpresion: selectedDataReimpresion,
                    selectedTipoEtiquetaReimpresion: selectedTipoEtiquetaReimpresion,
                    selectedFormatEtiquetaReimpresion: selectedFormatEtiquetaReimpresion,
                    selectedPosicionEtiquetaReimpresion: selectedPosicionEtiquetaReimpresion,
                },
            },
            success: function (parsed_data) {
                let errorLabels = [];
                let validLabels = [];

                // Separamos etiquetas válidas y con error
                parsed_data.forEach(label => {
                    if (
                        (label?.codigoRetorno >= "1") ||
                        (label?.codigoRetornoPick >= "1")
                    ) {
                        errorLabels.push(label);
                    } else {
                        validLabels.push(label);
                    }
                });

                // Tratamos etiquetas con error
                if (errorLabels.length > 0) {
                    table_errors_reg_orders.rows.add(errorLabels);
                    table_errors_reg_orders.columns.adjust().draw();
                }

                if (validLabels.length > 0) {
                    validLabels[0]['filePath'].forEach(path => {
                        if (typeof printGeneratedLabels === 'function') {
                            printGeneratedLabels(path, co_path_to_module);
                        }
                    });
                }
                
                jQuery('#ProcessingprintLabelsGeneratedButton').addClass('hidden-block');
                jQuery('.label-message').removeClass('hidden-block');
            },
            error: function() {
                jQuery('#ProcessingprintLabelsGeneratedButton').addClass('hidden-block');
                jQuery('.label-message').removeClass('hidden-block');
                showModalInfoWindow('Error al generar las etiquetas');
            }
        });
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                               REIMPRESION DE ETIQUETAS                               //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    /**
     * Reimpresion de etiquetas desde el panel "Reimpresion de etiquetas"
     */
    jQuery('#ReimprimirEtiquetasButton').on('click', function (e) {
        let selectedDataReimpresion = tableEtiquetas.rows({ selected: true }).data().toArray();

        let selectedTipoEtiquetaReimpresion = jQuery('#input_tipo_etiqueta_reimpresion').val();
        let selectedFormatEtiquetaReimpresion = jQuery('#input_format_etiqueta_reimpresion').val();
        let selectedPosicionEtiquetaReimpresion = jQuery('#input_pos_etiqueta_reimpresion').val();

        // Compatibilidad de etiquetas
        if (!checkCEXLabelFormat(selectedDataReimpresion, selectedFormatEtiquetaReimpresion)) {
            return;
        }

        if (selectedDataReimpresion.length > 0) {
            jQuery('#ProcessingReimprimirEtiquetasButton').removeClass('hidden-block');
            jQuery('.label-message').addClass('hidden-block');

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'AdminCorreosOficialUtilitiesProcess',
                        action: 'printLabelsGenerated',
                        selectedDataReimpresion: selectedDataReimpresion,
                        selectedTipoEtiquetaReimpresion: selectedTipoEtiquetaReimpresion,
                        selectedFormatEtiquetaReimpresion: selectedFormatEtiquetaReimpresion,
                        selectedPosicionEtiquetaReimpresion: selectedPosicionEtiquetaReimpresion,
                    },
                },
                success: function (parsed_data) {
                    let errorLabels = [];
                    let validLabels = []

                    // Separamos etiquetas válidad y con error
                    parsed_data.forEach(label => {
                        if (
                            (label?.codigoRetorno >= "1") ||
                            (label?.codigoRetornoPick >= "1")
                        ) {
                            errorLabels.push(label);
                        }else{
                            validLabels.push(label);
                        }
                    });
 
                    // Tratamos pedidos con error
                    if (errorLabels.length > 0) {
                        table_errors_print_labels.clear().draw();
                        table_errors_print_labels.rows.add(errorLabels);
                        table_errors_print_labels.columns.adjust().draw();
                        jQuery('#print_label_errors_container').removeClass('hidden-block');
                        jQuery('#ProcessingReimprimirEtiquetasButton').addClass('hidden-block');
                        jQuery('.label-message').removeClass('hidden-block');
                    }
                    
                    if (validLabels.length > 0) {
                        validLabels[0]['filePath'].forEach(path => {
                            printGeneratedLabels(path, co_path_to_module);
                        });
                        jQuery('#ProcessingReimprimirEtiquetasButton').addClass('hidden-block');
                        jQuery('.label-message').removeClass('hidden-block');
                    }
                },
            });
        } else {
            showModalInfoWindow(mustSelectOneRecord);
        }
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                              GENERACION RESUMEN PEDIDOS                              //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    jQuery('#ImprimirResumenButton').on('click', function () {
        //tableResumen.button('.buttons-print').trigger();

        var selectedData = tableResumen.rows({ selected: true }).data().toArray();

        if (selectedData.length > 0) {
            jQuery('#ProcessingImprimirResumenButton').removeClass('hidden-block');
            jQuery('.label-message').addClass('hidden-block');

            jQuery.ajax({
                type: 'post',
                url: url_prefix_back + '?controller=AdminCorreosOficialUtilitiesProcess&action=generatePDFManifest',
                data: {
                    selectedData: selectedData,
                },
                success: function (data) {
                    parsed_data = JSON.parse(data);
                    printGeneratedLabels(parsed_data, co_path_to_module);
                    jQuery('#ResumenDataTable').DataTable().ajax.reload();
                    jQuery('#ProcessingImprimirResumenButton').addClass('hidden-block');
                    jQuery('.label-message').removeClass('hidden-block');
                },
            });
        } else {
            showModalInfoWindow(mustSelectOneRecord);
        }
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                                      RECOGIDAS                                       //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    // Seteamos co_fecha min y máxima para la recogida
    document.getElementById('PickupDate').value = co_ano + '-' + co_mes + '-' + co_dia;
    jQuery('#PickupDate').attr('min', co_ano + '-' + co_mes + '-' + co_dia);

    jQuery('#datatable_errors_pickups_container').hide();

    // Ordena Recogidas con los elementos seleccionados del datatable
    jQuery('#generatePickupsButton').on('click', function () {
        jQuery('#processingPickupsButtonMsg').removeClass('hidden-block');
        jQuery('#generatePickupsButtonMsg').addClass('hidden-block');

        jQuery('#success_pickup_msg').addClass('hidden-block');

        let msgErrors_pickup_package_size = '';

        if (jQuery('#inputPrintLabelPickups').is(':checked')) {
            var PrintLabelPickups = 'S';
        } else {
            var PrintLabelPickups = 'N';
        }

        let TamLabelPickups = jQuery('#inputTamLabelPickups').val();

        let selectedDataPickups = tablePickups.rows({ selected: true }).data().toArray();

        //Actualizo valor de los inputs tamaño paquete e imprimir etiqueta en selectedDataPickups
        selectedDataPickups.forEach(function (valor, indice, array) {
            array[indice].package_size = jQuery('#select_option_tam_recogidas_' + array[indice].id_order).val();
            array[indice].print_label = jQuery('#select_option_imp_recogidas_' + array[indice].id_order).val();

            if (array[indice].company == 'Correos') {
                if (TamLabelPickups == 0) {
                    if (array[indice].package_size == 0) {
                        msgErrors_pickup_package_size = msgErrors_pickup_package_size + order_string_translate + ' ' + array[indice].id_order + ': ' + size_pickup_string_translate + ' <br />';
                    } else {
                        TamLabelPickups = array[indice].package_size;
                    }
                }
            }

            if (array[indice].print_label == 'S') {
                PrintLabelPickups = 'S'
            }
        });

        if (msgErrors_pickup_package_size != '') {
            jQuery('#processingPickupsButtonMsg').addClass('hidden-block');
            jQuery('#generatePickupsButtonMsg').removeClass('hidden-block');
            showModalInfoWindow(msgErrors_pickup_package_size);
        } else {
            let PickupDate = jQuery('#PickupDate').val();
            let PickupFrom = jQuery('#PickupFrom').val();
            let PickupTo = jQuery('#PickupTo').val();

            if (selectedDataPickups.length > 0) {
                jQuery.ajax({
                    type: 'post',
                    url: varsAjax.ajaxUrl,
                    data: {
                        action: 'correosOficialDispacher',
                        _nonce: varsAjax.nonce,
                        dispatcher: {
                            controller: 'AdminCorreosOficialUtilitiesProcess',
                            action: 'generatePickups',
                            selectedDataPickups: selectedDataPickups,
                            PrintLabelPickups: PrintLabelPickups,
                            TamLabelPickups: TamLabelPickups,
                            PickupDate: PickupDate,
                            PickupFrom: PickupFrom,
                            PickupTo: PickupTo,
                            page: 'pickup'
                        },
                    },
                    success: function (parsed_data) {

                        let errorOrders = [];
                        let validOrders = []

                        // Separamos pedidos procesados y con error
                        parsed_data.forEach(order => {
                            if (
                                (order?.codigoRetorno >= "1") ||
                                (order?.codigoRetornoPick >= "1")
                            ) {
                                errorOrders.push(order);
                            }else{
                                validOrders.push(order);
                            }
                        });

                        if (errorOrders.length > 0) {
                            table_errors_recogidas.clear().draw();
                            table_errors_recogidas.rows.add(errorOrders);
                            table_errors_recogidas.columns.adjust().draw();
                            jQuery('#datatable_errors_pickups_container').show();
                            jQuery('#processingPickupsButtonMsg').addClass('hidden-block');
                            jQuery('#generatePickupsButtonMsg').removeClass('hidden-block');
                        } else {
                            jQuery('#processingPickupsButtonMsg').addClass('hidden-block');
                            jQuery('#generatePickupsButtonMsg').removeClass('hidden-block');

                            //successDialog('Recogidas seleccionadas: ', 'Se han generado correctamente');

                            jQuery('#success_pickup_msg').removeClass('hidden-block');

                            let data_search = {
                                FromDatePickups: jQuery('#inputFromDatePickups').val(),
                                ToDatePickups: jQuery('#inputToDatePickups').val(),
                            };

                            if (new Date(data_search.ToDatePickups).getTime() < new Date(data_search.FromDatePickups).getTime()) {
                                showModalInfoWindow(dateFromIsMinor);
                            } else {
                                jQuery.ajax({
                                    type: 'post',
                                    url: varsAjax.ajaxUrl,
                                    data: {
                                        action: 'correosOficialDispacher',
                                        _nonce: varsAjax.nonce,
                                        dispatcher: {
                                            controller: 'AdminCorreosOficialUtilitiesProcess',
                                            action: 'searchPickups',
                                            FromDatePickups: data_search['FromDatePickups'],
                                            ToDatePickups: data_search['ToDatePickups'],
                                        },
                                    },
                                    success: function (data) {
                                        jQuery('#card4').show();
                                        jQuery('#PickupDataTable').DataTable().ajax.reload();
                                        let el = jQuery('#table-select-all-pickups').get(0);
                                        if (el && el.checked && 'indeterminate' in el) {
                                            el.indeterminate = true;
                                        }
                                    },
                                    error: function (e) {
                                        alert('ERROR 17010: Error al imprimir etiquetas de las recogidas');
                                    },
                                });
                            }
                        }
                    },
                });
            } else {
                jQuery('#processingPickupsButtonMsg').addClass('hidden-block');
                jQuery('#generatePickupsButtonMsg').removeClass('hidden-block');
                showModalInfoWindow(mustSelectOneRecord);
            }
        }
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                          GENERACION DOCUMENTACION ADUANERA                           //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    jQuery('#ImprimirCN23Button').on('click', function (event) {
        handleButtonClickUtilities('CN23');
    });

    jQuery('#ImprimirDUAButton').on('click', function (event) {
        handleButtonClickUtilities('DUA');
    });

    jQuery('#ImprimirDDPButton').on('click', function (event) {
        handleButtonClickUtilities('DDP');
    });

    // Imprimimos los registros seleccionados del datatable de Generación documentación aduanera
});

//--------------------------------------------------------------------------------------//
//                                                                                      //
//                                        COMUN                                         //
//                                                                                      //
//--------------------------------------------------------------------------------------//

/**
 * function para imprimir etiquetas
 * @param {string} data nombre del archivo PDF
 * @param {string} co_path_to_module ruta http del archivo PDF
 */

function printGeneratedLabels(data, co_path_to_module) {
    let secureUrl = co_path_to_module;

    // Comprobar si la tienda WordPress utiliza SSL
    if (isHttps()) {
        secureUrl = secureUrl.replace('http://', 'https://');
    }

    jQuery.ajax({
        type: 'post',
        url: varsAjax.ajaxUrl, // Ruta al archivo PHP
        'Content-Type': 'application/pdf',
        'Content-Disposition': 'attachment; filename="label.pdf"',
        data: {
            action: 'correosOficialDispacher',
            _nonce: varsAjax.nonce,
            dispatcher: {
                controller: 'AdminCorreosOficialDownloadLabelsController',
                filename: data + '&path=pdftmp',
            },
        },
        success: function (filename) {
            let fileHref = secureUrl + '/pdftmp/' + filename;

            let anchor = document.createElement('a');
            anchor.setAttribute('download', filename);
            anchor.setAttribute('href', fileHref);
            anchor.click();

            setTimeout(function () {
                jQuery.ajax({
                    type: 'post',
                    url: varsAjax.ajaxUrl,
                    data: {
                        action: 'correosOficialDispacher',
                        _nonce: varsAjax.nonce,
                        dispatcher: {
                            controller: 'AdminCorreosOficialUtilitiesProcess',
                            action: 'deleteFiles',
                        },
                    },
                });
            }, 6500);
        },
        error: function (xhr, textStatus, errorThrown) {
            console.error('Error al iniciar la descarga: ', textStatus, errorThrown);
        },
    });
}

function isHttps() {
    return document.location.protocol == 'https:';
}

function errorHandler(data) {

    error = [];

    if (data.codigoRetornoPick == 1) {
        data.error = data.mensajeRetornoPick;
    } else {
        data.error = data.mensajeRetorno;
    }

    data.id_order = data.orderId;
    data.reference = '';

    error.push(data);

    return error;
}

function transformErrorData(data) {
    const hasAnObject = data.some((item) => typeof item === 'object' && !Array.isArray(item));

    if (!hasAnObject) {
        return data;
    }

    let result = [];

    data.forEach((item) => {
        let transformedItem = {
            id_order: item.id_order,
            reference: item.reference,
            error: '',
        };

        if (typeof item.error === 'object' && !Array.isArray(item.error)) {
            transformedItem.error = Object.values(item.error)[0];
        } else {
            transformedItem.error = item.error;
        }

        result.push(transformedItem);
    });

    return result;
}

function handleButtonClickUtilities(type) {
    let button = jQuery(`#Imprimir${type}Button`);

    let selectedDataDocAduanera = tableDocAduanera.rows({ selected: true }).data().toArray();

    if (selectedDataDocAduanera.length > 0) {
        button.find('.spin').removeClass('hidden-block');
        button.find('.label-message').addClass('hidden-block');

        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: {
                action: 'correosOficialDispacher',
                _nonce: varsAjax.nonce,
                dispatcher: {
                    controller: 'AdminCorreosOficialUtilitiesProcess',
                    action: 'getCustomsDoc',
                    selectedDataDocAduanera: selectedDataDocAduanera,
                    optionButton: `Imprimir${type}Button`,
                },
            },
            success: function (parsed_data) {
                let errorLabels = [];
                let validLabels = [];

                // Separamos etiquetas válidad y con error
                parsed_data.forEach(label => {
                    if (
                        (label?.codigoRetorno >= "1") ||
                        (label?.codigoRetornoPick >= "1")
                    ) {
                        errorLabels.push(label);
                    }else{
                        validLabels.push(label);
                    }
                });

                // Tratamos pedidos con error
                if (errorLabels.length > 0) {
                    table_errors_aduanera.rows.add(error);
                    table_errors_aduanera.columns.adjust().draw();
                    jQuery('#datatable_results_aduanera_container').show();
                    jQuery('#datatable_results_aduanera_container').removeClass('hidden-block');
                }

                if (validLabels.length > 0) {
                    validLabels[0]['filePath'].forEach(path => {
                        printGeneratedLabels(path, co_path_to_module);
                    });
                }


                button.find('.spin').addClass('hidden-block');
                button.find('.label-message').removeClass('hidden-block');
            },
        });
    } else {
        showModalInfoWindow(mustSelectOneRecord);
    }
}
