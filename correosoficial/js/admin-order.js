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

/* INICIALIZACIÓN */
if (!sga_module) {
    let init_selected_carrier = jQuery('#input_select_carrier').find('option:selected');
    let company = init_selected_carrier.data('company');
    let container = jQuery('#masive_pickup_container');
    let inputLabel = jQuery('#orderAdminPrintLabelPickup');
    let inputPackageSize = jQuery('#orderAdminPackageSize');
    let selectedCompany = '';

    jQuery(document).ready(function () {

        const mainContentLoaded = document.getElementById('correos_oficial_main_container');
        
        if (mainContentLoaded) {
            // COMPROBACION VISUAL DE RECOGIDAS SEGUN CORREOS O CEX
            let checkbox = jQuery('#inputCheckSavePickup');

            jQuery('#input_select_carrier').on('change', function () {
                let selectedOption = jQuery(this).find('option:selected');

                selectedCompany = selectedOption.data('company');
                checkCorreosOrCEX(selectedCompany);
            });

            checkbox.on('change', function () {
                let isChecked = checkbox.prop('checked');
                showContent(isChecked);
            });

            if (company == 'Correos') {
                setCorreosRangeDate('pickup_date');
                setCorreosRangeDate('return_pickup_date');
            } else {
                setCEXRangeDate('pickup_date');
                setCEXRangeDate('return_pickup_date');
            }

            if (jQuery('#order_done_hidden').val()) {
                disableForm('#container_customer');
                disableForm('#container_shipping');
                disableForm('#added_values');
            }
            
            // Gestiona CodeAT de CEX cuando cambia customer_country
            jQuery('#customer_country').on('change', function () {
                manageCodeAT();
            });

            // Ocultamos selector de bultos dependiendo de la compañía
            var selected_carrier_return = jQuery('#input_select_carrier_return').find('option:selected');
            return_company = selected_carrier_return.data('company');

            jQuery('#input_select_carrier_return').on('change', function () {
                var selected_carrier_return = jQuery('#input_select_carrier_return').find('option:selected');
                var company = selected_carrier_return.data('company');

                if (company == 'Correos') {
                    jQuery('#save-return-pickup-container').addClass('hidden-block');
                    jQuery('#generate_return_pickup').removeClass('hidden-block');
                    jQuery('#correos-options-pickup-return-container').removeClass('hidden-block');
                    jQuery('.correos-num-parcels-return-container').addClass('hidden-block');
                } else if (company == 'CEX'){
                    jQuery('#save-return-pickup-container').removeClass('hidden-block');
                    // Ocultamos el blqoue de opciones de Correos y el botón de generar recogida para CEX  ya que se hace automáticamente
                    jQuery('#generate_return_pickup').addClass('hidden-block');
                    jQuery('#correos-options-pickup-return-container').addClass('hidden-block');
                    jQuery('.correos-num-parcels-return-container').removeClass('hidden-block');
                    jQuery('#pickupReturnButton').addClass('hidden-block');
                }

                manageReturnCustomDocPackage(company);
            });

            // Para cerrar alerts sin eliminación en el DOM
            jQuery('#success_register').on('close.bs.alert', function () {
                jQuery('#success_register').addClass('hidden-block');
                return false;
            });

            jQuery('#error_register').on('close.bs.alert', function () {
                jQuery('#error_register').addClass('hidden-block');
                return false;
            });

            jQuery('#success_register_return').on('close.bs.alert', function () {
                jQuery('#success_register_return').addClass('hidden-block');
                return false;
            });

            jQuery('#error_register_return').on('close.bs.alert', function () {
                jQuery('#error_register_return').addClass('hidden-block');
                return false;
            });

            jQuery('#no_offices_zip_message').on('close.bs.alert', function () {
                jQuery('#no_offices_zip_message').addClass('hidden-block');
                return false;
            });

            jQuery('#no_citypaqs_zip_message').on('close.bs.alert', function () {
                jQuery('#no_citypaqs_zip_message').addClass('hidden-block');
                return false;
            });

            /* FUNCIONALIDAD BULTOS */

            // EventListener para formulario de bulto
            jQuery("input[type='radio']").on('change', function () {
                index_id = jQuery(this)[0].name.indexOf('_');
                id_radio = jQuery(this)[0].name.substring(index_id + 1, jQuery(this)[0].name.length);
                if (this.value == '0') {
                    jQuery('#packageCustomDesc_' + id_radio).prop('disabled', false);
                    jQuery('#packageTariffCode_' + id_radio).prop('disabled', true);
                    jQuery('#packageTariffDesc_' + id_radio).prop('disabled', true);
                    jQuery('#packageTariffDesc_' + id_radio).prop('required', false);
                } else {
                    jQuery('#packageCustomDesc_' + id_radio).prop('disabled', true);
                    jQuery('#packageTariffCode_' + id_radio).prop('disabled', false);
                    jQuery('#packageTariffDesc_' + id_radio).prop('disabled', false);
                    jQuery('#packageTariffDesc_' + id_radio).prop('required', true);
                }

                if (this.value == '0') {
                    jQuery('#packageCustomDescReturn_' + id_radio).prop('disabled', false);
                    jQuery('#packageTariffCodeReturn_' + id_radio).prop('disabled', true);
                    jQuery('#packageTariffDescReturn_' + id_radio).prop('disabled', true);
                    jQuery('#packageTariffDescReturn_' + id_radio).prop('required', false);
                } else {
                    jQuery('#packageCustomDescReturn_' + id_radio).prop('disabled', true);
                    jQuery('#packageTariffCodeReturn_' + id_radio).prop('disabled', false);
                    jQuery('#packageTariffDescReturn_' + id_radio).prop('disabled', false);
                    jQuery('#packageTariffDescReturn_' + id_radio).prop('required', true);
                }
            });

            // Clonación de bultos devolución
            jQuery('#correos-num-parcels-return').change(function () {
                jQuery('.container-bulto-return-cloned').remove();
                let selected = jQuery('#input_select_carrier_return').find('option:selected');
                let company = selected.data('company');

                var cloneId = 1;
                if (company == 'CEX' || (company == 'Correos' && jQuery(this).val() == 1) ) {
                    for (var i = 1; i < jQuery(this).val(); i++) {
                        cloneId++;
                        id_bulto = 'containerBultoReturn_' + cloneId;
                        var clone = jQuery('#containerBultoReturn_1').clone().attr({ id: id_bulto }).addClass('container-bulto-return-cloned');
                        clone.find('.card-header').html('Devolución del paquete ' + cloneId);

                        clone.find("input[name='DescriptionRadioReturn_1']").prop('name', 'DescriptionRadioReturn_' + cloneId);

                        clone.find("select[name='packageCustomDescReturn_1']").attr('id', 'packageCustomDescReturn_' + cloneId);
                        clone.find("select[name='packageCustomDescReturn_1']").prop('name', 'packageCustomDescReturn_' + cloneId);
                        clone.find("input[name='packageTariffCodeReturn_1']").attr('id', 'packageTariffCodeReturn_' + cloneId);
                        clone.find("input[name='packageTariffCodeReturn_1']").prop('name', 'packageTariffCodeReturn_' + cloneId);
                        clone.find("input[name='packageTariffDescReturn_1']").attr('id', 'packageTariffDescReturn_' + cloneId);
                        clone.find("input[name='packageTariffDescReturn_1']").prop('name', 'packageTariffDescReturn_' + cloneId);

                        clone.find("input[name='packageWeightReturn_1']").prop('value', '');
                        clone.find("input[name='packageWeightReturn_1']").prop('name', 'packageWeightReturn_' + cloneId);

                        clone.find("input[name='packageAmountReturn_1']").prop('value', '');
                        clone.find("input[name='packageAmountReturn_1']").prop('name', 'packageAmountReturn_' + cloneId);

                        clone.find("input[name='packageLargeReturn_1']").attr('id', 'packageLargeReturn_' + cloneId);
                        clone.find("input[name='packageWidthReturn_1']").attr('id', 'packageWidthReturn_' + cloneId);
                        clone.find("input[name='packageHeightReturn_1']").attr('id', 'packageHeightReturn_' + cloneId);

                        clone.find("input[name='packageLargeReturn_1']").prop('name', 'packageLargeReturn_' + cloneId);
                        clone.find("input[name='packageWidthReturn_1']").prop('name', 'packageWidthReturn_' + cloneId);
                        clone.find("input[name='packageHeightReturn_1']").prop('name', 'packageHeightReturn_' + cloneId);

                        clone.find("textarea[name='deliveryRemarksReturn_1']").prop('name', 'deliveryRemarksReturn_' + cloneId);

                        clone.appendTo('.container-bultos-return');
                    }

                    // EventListener para radiobuttons al clonar formulario bulto
                    jQuery("input[type='radio']").on('change', function () {
                        index_id = jQuery(this)[0].name.indexOf('_');
                        id_radio = jQuery(this)[0].name.substring(index_id + 1, jQuery(this)[0].name.length);
                        if (this.value == '0') {
                            jQuery('#packageCustomDescReturn_' + id_radio).prop('disabled', false);
                            jQuery('#packageTariffCodeReturn_' + id_radio).prop('disabled', true);
                            jQuery('#packageTariffDescReturn_' + id_radio).prop('disabled', true);
                            jQuery('#packageTariffDescReturn_' + id_radio).prop('required', false);
                        } else {
                            jQuery('#packageCustomDescReturn_' + id_radio).prop('disabled', true);
                            jQuery('#packageTariffCodeReturn_' + id_radio).prop('disabled', false);
                            jQuery('#packageTariffDescReturn_' + id_radio).prop('disabled', false);
                            jQuery('#packageTariffDescReturn_' + id_radio).prop('required', true);
                        }
                    });
                }
            });

            // Cambio de peso total
            jQuery('#total_weight').change(function () {
                // Actualiza peso bulto 1
                jQuery('#packageWeight_1').val(jQuery('#total_weight').val());
            });

            // Restringir dimensiones a números enteros
            jQuery(document).on('input', "input[name^='packageLarge_'], input[name^='packageWidth_'], input[name^='packageHeight_']", function () {
                // Elimina cualquier carácter que no sea dígito
                this.value = this.value.replace(/\D/g, '');
            });

            // Clonación de bultos
            jQuery('#correos-num-parcels').change(function () {
                var selected = jQuery('#input_select_carrier').find('option:selected');
                var carrier_type = selected.data('carrier_type');
                var max_packages = selected.data('max_packages');
                let company = selected.data('company');

                jQuery('.container-bulto-cloned').remove();

                if (jQuery(this).val() == 1 && company != 'CEX') {
                    jQuery('.all-packages-equal-container').addClass('hidden-block');
                    jQuery('#partial_delivery_container').addClass('hidden-block');
                } else {
                    jQuery('.all-packages-equal-container').removeClass('hidden-block');
                    jQuery('#partial_delivery_container').removeClass('hidden-block');
                }

                if (jQuery(this).val() > 1) {
                    jQuery('#total_weight').prop('disabled', true);
                    jQuery("input[id^='packageWeight_']").prop('disabled', false);
                }else{
                    jQuery('#total_weight').prop('disabled', false);
                    jQuery("input[id^='packageWeight_']").prop('disabled', true);
                }

                var cloneId = 1;

                for (var i = 1; i < jQuery(this).val(); i++) {
                    cloneId++;
                    id_bulto = 'containerBulto_' + cloneId;

                    // Clonación de formulario sin eventos. Se asigna id único a cada campo
                    var clone = jQuery('#containerBulto_1').clone(true, true).attr({ id: id_bulto }).addClass('container-bulto-cloned');
                    clone.find('.card-header').html('Bulto ' + cloneId);

                    clone.find('#DescriptionRadioDesc_1').attr('id', 'DescriptionRadioDesc_' + cloneId);
                    clone.find('#DescriptionRadioTariff_1').attr('id', 'DescriptionRadioTariff_' + cloneId);

                    clone.find("input[name='DescriptionRadio_1']").prop('name', 'DescriptionRadio_' + cloneId);

                    clone.find("select[name='packageCustomDesc_1']").attr('id', 'packageCustomDesc_' + cloneId);
                    clone.find("select[name='packageCustomDesc_1']").prop('name', 'packageCustomDesc_' + cloneId);
                    clone.find("input[name='packageTariffCode_1']").attr('id', 'packageTariffCode_' + cloneId);
                    clone.find("input[name='packageTariffCode_1']").prop('name', 'packageTariffCode_' + cloneId);
                    clone.find("input[name='packageTariffDesc_1']").attr('id', 'packageTariffDesc_' + cloneId);
                    clone.find("input[name='packageTariffDesc_1']").prop('name', 'packageTariffDesc_' + cloneId);

                    clone.find("input[name='packageRef_1']").prop('value', '');
                    clone.find("input[name='packageRef_1']").prop('name', 'packageRef_' + cloneId);

                    clone.find("input[name='packageLarge_1']").prop('name', 'packageLarge_' + cloneId);
                    clone.find("input[name='packageWidth_1']").prop('name', 'packageWidth_' + cloneId);
                    clone.find("input[name='packageHeight_1']").prop('name', 'packageHeight_' + cloneId);

                    clone.find("textarea[name='deliveryRemarks_1']").prop('name', 'deliveryRemarks_' + cloneId);

                    /**
                     * Tabs de documentación aduanera
                     */
                    clone.find('#tabs_customs_doc_1').attr('id', 'tabs_customs_doc_' + cloneId);

                    clone.find('#customs_desc_1').attr('data-number', cloneId);
                    clone.find('#customs_code_1').attr('data-number', cloneId);

                    clone.find('#add_description_1').attr('data-number', cloneId);
                    clone.find('#del_description_1').attr('data-number', cloneId);

                    clone.find('#add_description_1').prop('disabled', false);
                    clone.find('#add_description_1').attr('id', 'add_description_' + cloneId);
                    clone.find('#del_description_1').attr('id', 'del_description_' + cloneId);

                    clone.find('#added_customs_description_1').html('');
                    clone.find('#added_customs_description_1').attr('id', 'added_customs_description_' + cloneId);

                    clone.find('#customs_desc_tab_1').attr('id', 'customs_desc_tab_' + cloneId);
                    clone.find('#customs_code_tab_1').attr('id', 'customs_code_tab_' + cloneId);

                    clone.find('#customs_desc_1').attr('id', 'customs_desc_' + cloneId);
                    clone.find('#customs_code_1').attr('id', 'customs_code_' + cloneId);

                    clone.find("input[name='packageWeight_1']").prop('value', '');
                    clone.find("input[name='packageWeight_1']").attr('id', 'packageWeight_' + cloneId);
                    clone.find("input[name='packageWeight_1']").prop('name', 'packageWeight_' + cloneId);

                    clone.find("input[name='packageWeightDesc_1']").prop('value', '');
                    clone.find("input[name='packageWeightDesc_1']").attr('id', 'packageWeightDesc_' + cloneId);
                    clone.find("input[name='packageWeightDesc_1']").prop('name', 'packageWeightDesc_' + cloneId);

                    clone.find("input[name='packageAmount_1']").prop('value', '');
                    clone.find("input[name='packageAmount_1']").attr('id', 'packageAmount_' + cloneId);
                    clone.find("input[name='packageAmount_1']").prop('name', 'packageAmount_' + cloneId);

                    clone.find("input[name='packageUnits_1']").prop('value', '');
                    clone.find("input[name='packageUnits_1']").prop('name', 'packageUnits_' + cloneId);
                    clone.find('#packageUnits_1').attr('id', 'packageUnits_' + cloneId);

                    clone.appendTo('.container-bultos');

                    co_DescriptionCounter[cloneId] = 1;
                }

                // EventListener para radiobuttons al clonar formulario bulto
                jQuery("input[type='radio']").on('change', function () {
                    index_id = jQuery(this)[0].name.indexOf('_');
                    id_radio = jQuery(this)[0].name.substring(index_id + 1, jQuery(this)[0].name.length);
                    if (this.value == '0') {
                        jQuery('#packageCustomDesc_' + id_radio).prop('disabled', false);
                        jQuery('#packageTariffCode_' + id_radio).prop('disabled', true);
                        jQuery('#packageTariffDesc_' + id_radio).prop('disabled', true);
                        jQuery('#packageTariffDesc_' + id_radio).prop('required', false);
                    } else {
                        jQuery('#packageCustomDesc_' + id_radio).prop('disabled', true);
                        jQuery('#packageTariffCode_' + id_radio).prop('disabled', false);
                        jQuery('#packageTariffDesc_' + id_radio).prop('disabled', false);
                        jQuery('#packageTariffDesc_' + id_radio).prop('required', true);
                    }
                });

                if (carrier_type == 'international' && jQuery(this).val() > max_packages) {
                    jQuery('.alert-max-packages').removeClass('hidden-block');

                    jQuery('#all_packages_equal').prop('disabled', true);
                    jQuery('#all_packages_equal').prop('checked', true);

                    jQuery('.container-bulto').each(function () {
                        if (jQuery(this)[0].id != 'containerBulto_1') {
                            jQuery('input', jQuery(this)).each(function () {
                                jQuery(this).prop('disabled', true);
                            });
                            jQuery('textarea', jQuery(this)).each(function () {
                                jQuery(this).prop('disabled', true);
                            });
                            jQuery('select', jQuery(this)).each(function () {
                                jQuery(this).prop('disabled', true);
                            });
                            jQuery('.card', this).addClass('package-off');
                        }
                    });
                } else {
                    jQuery('.alert-max-packages').addClass('hidden-block');
                    jQuery('#all_packages_equal').prop('checked', false);
                }
            });

        // Todos los bultos iguales
        jQuery('#all_packages_equal').on('click', function () {
            if (jQuery(this).is(':checked')) {
                // Peso Total
                jQuery('#total_weight').prop('disabled', false);

                var pesoBulto1 = jQuery('#packageWeight_1').val();
                jQuery('#packageWeight_1').prop('disabled', true);

                jQuery('.container-bulto').each(function () {
                    // Verificar que no sea el primer contenedor ni un contenedor de devoluciones
                    if (jQuery(this)[0].id !== 'containerBulto_1' && 
                        !jQuery(this)[0].id.includes('returns_container')) {
                        // Deshabilitar los campos
                        jQuery('input', jQuery(this)).prop('disabled', true);
                        jQuery('textarea', jQuery(this)).prop('disabled', true);
                        jQuery('select', jQuery(this)).prop('disabled', true);
                        
                        // Copiar el peso del bulto 1
                        jQuery('#' + jQuery(this)[0].id.replace('containerBulto', 'packageWeight')).val(pesoBulto1);
                        
                        jQuery('.card', this).addClass('package-off');
                    }
                });
            } else {
                // Peso Total
                if (jQuery('#correos-num-parcels').val() > 1) {
                    jQuery('#total_weight').prop('disabled', true);
                }
                jQuery('#packageWeight_1').prop('disabled', false);
                
                jQuery('.container-bulto').each(function () {
                    if (jQuery(this)[0].id !== 'containerBulto_1' && 
                        !jQuery(this)[0].id.includes('returns_container')) {

                        jQuery('input', jQuery(this)).prop('disabled', false);
                        jQuery('textarea', jQuery(this)).prop('disabled', false);
                        jQuery('select', jQuery(this)).prop('disabled', false);
                        
                        jQuery('.card', this).removeClass('package-off');
                    }
                });
            }
        });

            /* FUNCIONALIDAD VALORES AÑADIDOS */

            // Gestiona cuenta bancaria
            jQuery('#bank_acc_number').on('click', function () {
                if (jQuery('#bank_acc_number').val().substring(0, 4) === '****') {
                    ibanNumber = jQuery('#bank_acc_number').val();
                }
                jQuery('#bank_acc_number').val('');
            });

            jQuery('#bank_acc_number').on('blur', function () {
                if (jQuery('#bank_acc_number').val() == '') {
                    jQuery('#bank_acc_number').val(ibanNumber);
                }
            });

            // Contrareembolso
            jQuery('#contrareembolsoCheckbox').on('click', function () {
                var selected_carrier = jQuery('#input_select_carrier').find('option:selected');
                var company = selected_carrier.data('company');
                if (jQuery(this).is(':checked')) {
                    if (company == 'Correos') {
                        jQuery('#cash_on_delivery_value_container').removeClass('hidden-block');
                        jQuery('#bank_acc_number_container').removeClass('hidden-block');
                    } else {
                        jQuery('#cash_on_delivery_value_container').removeClass('hidden-block');
                        jQuery('#bank_acc_number_container').addClass('hidden-block');
                    }
                } else {
                    jQuery('#cash_on_delivery_value_container').addClass('hidden-block');
                    jQuery('#bank_acc_number_container').addClass('hidden-block');
                }
            });

            // Seguro
            jQuery('#seguroCheckbox').on('click', function () {
                if (jQuery(this).is(':checked')) {
                    jQuery('.seguro-info').removeClass('hidden-block');
                } else {
                    jQuery('.seguro-info').addClass('hidden-block');
                }
            });

            jQuery('#generate_return_pickup').on('click', function (event) {
                generateReturnPickup();
            });

            // Comprobamos el tipo seleccionado
            labelsSelectActions(jQuery('#input_tipo_etiqueta_reimpresion').val());

            // Escuchamos cambios de tipo
            jQuery('#input_tipo_etiqueta_reimpresion').on('change', function () {
                labelsSelectActions(this.value);
            });

            /* FUNCIONALIDAD SENDERS */
            jQuery('#senderSelect').on('change', function (e) {
                var sender_id = jQuery(this).val();

                var data = {
                    ajax: true,
                    token: static_token,
                    action: 'getSenderById',
                    sender_id: sender_id,
                };
                var rand = 'rand=' + new Date().getTime();
                var ajaxtrue = '&ajax=true';

                jQuery.ajax({
                    url: AdminOrderURL + rand + ajaxtrue,
                    type: 'POST',
                    data: data,
                    cache: false,
                    processData: true,
                    success: function (data) {
                        parsed_data = JSON.parse(data);
                        jQuery('#sender_name').val(parsed_data['sender_name']);
                        jQuery('#sender_contact').val(parsed_data['sender_contact']);
                        jQuery('#sender_address').val(parsed_data['sender_address']);
                        jQuery('#sender_city').val(parsed_data['sender_city']);
                        jQuery('#sender_cp').val(parsed_data['sender_cp']);
                        jQuery('#sender_phone').val(parsed_data['sender_phone']);
                        jQuery('#sender_email').val(parsed_data['sender_email']);
                        jQuery('#sender_nif_cif').val(parsed_data['sender_nif_cif']);
                        jQuery('#sender_from_time').val(parsed_data['sender_from_time']);
                        jQuery('#sender_to_time').val(parsed_data['sender_to_time']);
                        jQuery('#sender_country').val(parsed_data['sender_iso_code_pais']);
                        jQuery('#correos_code').val(parsed_data['correos_code']);
                        jQuery('#cex_code').val(parsed_data['cex_code']);

                        manageCodeAT();

                        // Comprobamos compativiliad con producto seleccionado
                        var carrierSelected = jQuery('#input_select_carrier').find('option:selected');

                        if (carrierSelected.data('company') == 'Correos' && parsed_data['correos_code'] != 0) {
                            jQuery('#client_code').val(parsed_data['correos_code']);
                        } else if (carrierSelected.data('company') == 'CEX' && parsed_data['cex_code'] != 0) {
                            jQuery('#client_code').val(parsed_data['cex_code']);
                        } else {
                            jQuery('#client_code').val('');
                            senderErrorModal();
                        }
                    },
                });
            });

            /* FUNCIONALIDAD CAMBIAR CARRIER */
            jQuery('#input_select_carrier').on('change', function (e) {
                let selected = jQuery(this).find('option:selected');
                let company = selected.data('company');
                let carrier_value = selected.val();
                let carrier_type = selected.data('carrier_type');
                let max_packages = selected.data('max_packages');
                let available_carriers_default_dimensions;

                // funcionalidad dimensiones por defecto para los siguiente transportistas.
                available_carriers_default_dimensions = ['S0179', 'S0176', 'S0178', '18'];

                let containerBultoInfo = document.querySelectorAll('.container-bulto-info');
                for (let info of containerBultoInfo) {
                    let element = info;
                    if (available_carriers_default_dimensions.includes(carrier_value)) {

                        // Comprobamos si el campo está vacío
                        if(element.querySelector('input[name^="packageLarge"]').value === '' &&
                        element.querySelector('input[name^="packageWidth"]').value === '' &&
                        element.querySelector('input[name^="packageHeight"]').value === '') {
                            // Asignamos los valores por defecto
                            element.querySelector('input[name^="packageLarge"]').value = large_by_default;
                            element.querySelector('input[name^="packageWidth"]').value = width_by_default;
                            element.querySelector('input[name^="packageHeight"]').value = height_by_default;
                        }

                    } 
                    
                    // else {
                    //     element.querySelector('input[name^="packageLarge"]').value = '';
                    //     element.querySelector('input[name^="packageWidth"]').value = '';
                    //     element.querySelector('input[name^="packageHeight"]').value = '';
                    // }
                }

                // client_code según remitente
                let client_code = '';
                if (company == 'Correos' && jQuery('#correos_code').val() != 0) {
                    client_code = jQuery('#correos_code').val();
                } else if (company == 'CEX' && jQuery('#cex_code').val() != 0) {
                    client_code = jQuery('#cex_code').val();
                } else {
                    // El remitente no tiene contrato asociado
                    senderErrorModal();
                }

                jQuery('#client_code').val(client_code);
                var require_customs_doc = jQuery('#require_customs_doc_hidden').val();

                if (jQuery('#correos-num-parcels').val() >= max_packages) {
                    jQuery('.alert-max-packages').removeClass('hidden-block');
                    jQuery('#all_packages_equal').prop('disabled', true);
                    jQuery('#all_packages_equal').prop('checked', false);
                    jQuery('#correos-num-parcels').prop('disabled', true);
                    
                    if (jQuery('#correos-num-parcels').val() > max_packages) {
                        jQuery('.container-bulto').each(function () {
                            if (jQuery(this)[0].id != 'containerBulto_1') {
                                jQuery('input', jQuery(this)).each(function () {
                                    jQuery(this).prop('disabled', true);
                                });
                                jQuery('textarea', jQuery(this)).each(function () {
                                    jQuery(this).prop('disabled', true);
                                });
                                jQuery('select', jQuery(this)).each(function () {
                                    jQuery(this).prop('disabled', true);
                                });
                                jQuery('.card', this).addClass('package-off');
                            }
                        });
                    }
                } else {
                    jQuery('.alert-max-packages').addClass('hidden-block');
                    jQuery('#correos-num-parcels').prop('disabled', false);
                    jQuery('#all_packages_equal').prop('disabled', false);
                    jQuery('#all_packages_equal').prop('checked', false);

                    jQuery('.container-bulto').each(function () {
                        if (jQuery(this)[0].id != 'containerBulto_1') {
                            jQuery('input', jQuery(this)).each(function () {
                                jQuery(this).prop('disabled', false);
                            });
                            jQuery('textarea', jQuery(this)).each(function () {
                                jQuery(this).prop('disabled', false);
                            });
                            jQuery('select', jQuery(this)).each(function () {
                                jQuery(this).prop('disabled', false);
                            });
                            jQuery('.card', this).removeClass('package-off');
                        }
                    });
                }

                switch (company) {
                    case 'Correos':
                        switch (carrier_type) {
                            case 'office':
                                jQuery('.office-container').removeClass('hidden-block');
                                jQuery('.citypaq-container').addClass('hidden-block');
                                jQuery('.pudocex-container').addClass('hidden-block');
                                break;
                            case 'citypaq':
                                jQuery('.office-container').addClass('hidden-block');
                                jQuery('.citypaq-container').removeClass('hidden-block');
                                jQuery('.pudocex-container').addClass('hidden-block');
                                break;
                            case 'homedelivery':
                            case 'international':
                                jQuery('.office-container').addClass('hidden-block');
                                jQuery('.citypaq-container').addClass('hidden-block');
                                jQuery('.pudocex-container').addClass('hidden-block');
                                break;
                        }

                        setCorreosRangeDate('pickup_date');

                        if (require_customs_doc) {
                            jQuery('.customs-correos-container').removeClass('hidden-block');
                            jQuery('#customs-labels-container').removeClass('hidden-block');
                        }

                        if (jQuery('#correos-num-parcels').val() > 1 && company != 'CEX') {
                            jQuery('#partial_delivery_container').removeClass('hidden-block');
                        } else {
                            jQuery('#partial_delivery_container').addClass('hidden-block');
                        }

                        if (jQuery('#contrareembolsoCheckbox').is(':checked')) {
                            jQuery('#cash_on_delivery_value_container').removeClass('hidden-block');
                            jQuery('#bank_acc_number_container').removeClass('hidden-block');
                        } else {
                            jQuery('#cash_on_delivery_value_container').addClass('hidden-block');
                            jQuery('#bank_acc_number_container').addClass('hidden-block');
                        }

                        break;
                    case 'CEX':

                        switch (carrier_type) {

                            case 'office':
                                jQuery('.office-container').removeClass('hidden-block');
                                jQuery('.citypaq-container').addClass('hidden-block');
                                jQuery('.pudocex-container').addClass('hidden-block');
                                break;
                            case 'pudocex':
                                jQuery('.office-container').addClass('hidden-block');
                                jQuery('.citypaq-container').addClass('hidden-block');
                                jQuery('.pudocex-container').removeClass('hidden-block');
                                break;
                            default:
                                jQuery('.office-container').addClass('hidden-block');
                                jQuery('.citypaq-container').addClass('hidden-block');
                                jQuery('.pudocex-container').addClass('hidden-block');
                                break;
                        }

                        // if (carrier_type == 'office') {
                        //     jQuery('.office-container').removeClass('hidden-block');
                        //     jQuery('.citypaq-container').addClass('hidden-block');
                        // } else {
                        //     jQuery('.office-container').addClass('hidden-block');
                        //     jQuery('.citypaq-container').addClass('hidden-block');
                        // }

                        jQuery('.alert-more-5-labels').addClass('hidden-block');
                        jQuery('#inputCheckPrintLabel').prop('disabled', false);

                        setCEXRangeDate('pickup_date');

                        jQuery('.customs-correos-container').addClass('hidden-block');
                        jQuery('#customs-labels-container').addClass('hidden-block');

                        jQuery('#partial_delivery_container').addClass('hidden-block');

                        if (jQuery('#contrareembolsoCheckbox').is(':checked')) {
                            jQuery('#cash_on_delivery_value_container').removeClass('hidden-block');
                            jQuery('#bank_acc_number_container').addClass('hidden-block');
                        } else {
                            jQuery('#cash_on_delivery_value_container').addClass('hidden-block');
                            jQuery('#bank_acc_number_container').addClass('hidden-block');
                        }
                        break;
                }

                manageCodeAT();
                manageDeliverySaturday(company);
            });

            jQuery.validator.addMethod('validate_nif_cif_nie', function (value) {
                if (jQuery('#customer_dni').val() == '') {
                    return true;
                } else {
                    result = validate_nif_cif_nie(value);
                    return result.valid;
                }
            });

            jQuery.validator.addMethod(
                'validate_acc_iban',
                function (value) {
                    if (value.substring(0, 4) === '****') {
                        return true;
                    } else {
                        return validate_acc_iban(value);
                    }
                },
                wrongACCAndIBAN
            ); /* Retornamos el literal traducible del settings-user-configuration.tpl */

            jQuery('#returnPickupButtonMsg').on('click', function () {
                if (!jQuery('#return_package_type').val()) {
                    jQuery('#return_package_type').addClass('error');
                }
            });

            /**
             * Tabs de documentación aduanera
             */
            let co_cloneNumber = 1;

            let customsTabsElement = jQuery('.tabs_customs_doc');

            let addingDesc = true;
            let addingTarriffCode = false;

            // Encuentra dentro de la lista hija ul, el li con el class active
            if (customsTabsElement.length) {
                let activeTabElement = customsTabsElement.find('ul li a.active');
                // si el elemento tiene el class customs_desc_tab
                if (activeTabElement.hasClass('customs_desc')) {
                    addingDesc = true;
                    addingTarriffCode = false;
                } else if (activeTabElement.hasClass('customs_code')) {
                    addingDesc = false;
                    addingTarriffCode = true;
                }
            }
            
            let type = '_shipping';
            let activeTab = '';

            jQuery('#customs_correos_container_shipping').on('mouseover', function (event) {
                type = '_shipping';
                co_DescriptionCounter[co_cloneNumber] = co_DescriptionCounter_shipping;
            });
            jQuery('#customs_correos_container_return').on('mouseover', function (event) {
                type = '_return';
                co_DescriptionCounter[co_cloneNumber] = co_DescriptionCounter_return;
            });

            activeTab = getActiveTab(co_cloneNumber, type);

            if (activeTab == 'desc_tab') {
                showCustomsDesc(co_cloneNumber, '_shipping');
                showCustomsDesc(co_cloneNumber, '_return');
            } else if (activeTab == 'code_tab') {
                showCustomsCode(co_cloneNumber, '_shipping');
                showCustomsCode(co_cloneNumber, '_return');
            }

            jQuery('#customs_correos_container_shipping .nav-link, #customs_correos_container_return .nav-link').on('click', function (event) {
                event.preventDefault();
                co_cloneNumber = jQuery(this).attr('data-number');
                jQuery(this).addClass('active');

                if (jQuery(this).attr('data-type') == 'customs_desc') {
                    addingDesc = true;
                    addingTarriffCode = false;
                    showCustomsDesc(co_cloneNumber, type);
                    setCustomsDescActive(co_cloneNumber, type);
                    jQuery('#customs_correos_container' + type + ' #DescriptionRadioDesc_' + co_cloneNumber).val(1);
                    jQuery('#customs_correos_container' + type + ' #DescriptionRadioTariff_' + co_cloneNumber).val(0);
                } else if (jQuery(this).attr('data-type') == 'customs_code') {
                    addingDesc = false;
                    addingTarriffCode = true;
                    showCustomsCode(co_cloneNumber, type);
                    setCustomsCodeActive(co_cloneNumber, type);
                    jQuery('#customs_correos_container' + type + ' #DescriptionRadioTariff_' + co_cloneNumber).val(1);
                    jQuery('#customs_correos_container' + type + ' #DescriptionRadioDesc_' + co_cloneNumber).val(0);
                }
            });

            let co_AddedDescription;
            let co_DescriptionCounter = {};
            let co_DescriptionCounter_shipping = 1;
            let co_DescriptionCounter_return = 1;
            co_DescriptionCounter[co_cloneNumber] = 1;

            jQuery('#customs_correos_container_shipping .add_description, #customs_correos_container_return .add_description').on('click', function (event) {
                event.preventDefault();
                co_cloneNumber = jQuery(this).attr('data-number');
                co_AddedDescription = jQuery('#customs_correos_container' + type + ' #added_customs_description_' + co_cloneNumber);

                let customsCode = jQuery('#customs_correos_container' + type + ' #packageCustomDesc_' + co_cloneNumber).val();
                let customsDesc = jQuery('#customs_correos_container' + type + ' #packageCustomDesc_' + co_cloneNumber).find('option:selected').text();

                let TariffCodeElement = jQuery('#customs_correos_container' + type + ' #packageTariffCode_' + co_cloneNumber);
                let TariffDescElement = jQuery('#customs_correos_container' + type + ' #packageTariffDesc_' + co_cloneNumber);

                let AmountElement = jQuery('#customs_correos_container' + type + ' #packageAmount_' + co_cloneNumber);
                let WeightElement = jQuery('#customs_correos_container' + type + ' #packageWeightDesc_' + co_cloneNumber);
                let UnitsElement = jQuery('#customs_correos_container' + type + ' #packageUnits_' + co_cloneNumber);
                let CountryOrigyn = jQuery('#customs_correos_container' + type + ' #CountryOrigyn_' + co_cloneNumber);

                // Comprobamos que tab está activo
                let customsDescActiveTab = getActiveTab(co_cloneNumber, type);

                let Amount = AmountElement.val();
                let Weight = WeightElement.val();
                let Units = UnitsElement.val();
                let Country = CountryOrigyn.val();

                let TariffCode = TariffCodeElement.val();  
                let TariffDesc = TariffDescElement.val();
                
                // Inicializamos array para los errores
                let errors = [];

                if (Amount == '')
                    errors.push(AmountElement.addClass('error'));
                
                if (Weight == '')
                    errors.push(WeightElement.addClass('error'));
                
                if (Units == '')
                    errors.push(UnitsElement.addClass('error'));

                if (customsDescActiveTab == "code_tab" && TariffCode == '')
                    errors.push(TariffCodeElement.addClass('error'));
                
                if (customsDescActiveTab == "code_tab" && TariffDesc == '')
                    errors.push(TariffDescElement.addClass('error'));

                // Si hay errores, no dejamos continuar
                if (errors.length) {
                    console.log(errors);
                    return;
                } else {
                    AmountElement.removeClass('error');
                    WeightElement.removeClass('error');
                    UnitsElement.removeClass('error');

                    TariffCodeElement.removeClass('error');
                    TariffDescElement.removeClass('error');
                }

                if (co_DescriptionCounter[co_cloneNumber] <= 5) {
                
                    // Verificar si estamos agregando una descripción o un código arancelario
                    if (addingDesc) {

                        let customsString = customsCode + ' • ' + customsDesc + ' • ' + Amount + ' €' + ' • ' + Weight + ' Kg' + ' • ' + Units + ' Unid.';

                        if(Country) {
                            customsString += ' • ' + Country;
                        }

                        co_AddedDescription.append(
                            "<div class=\"chip-row\" style=\"display:flex;align-items:center;gap:8px;margin-bottom:6px;\">" +
                                "<input class='chip col-sm-12' readonly id='customs_desc" +
                                type +
                                '_' +
                                co_cloneNumber +
                                '_' +
                                (co_DescriptionCounter[co_cloneNumber]) +
                                "' name='customs_desc_" +
                                co_cloneNumber +
                                '_' +
                                (co_DescriptionCounter[co_cloneNumber]) +
                                "' value='" + customsString + "' />" +
                                "<div class=\"chip-actions\">" +
                                    "<button type=\"button\" class=\"chip-edit-btn\" title=\"Editar\" style=\"background:none;border:none;padding:0;cursor:pointer;\">" +
                                        "<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#2d3e50\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M12 20h9\"/><path d=\"M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z\"/></svg>" +
                                    "</button>" +
                                    "<button type=\"button\" class=\"chip-delete-btn\" title=\"Eliminar\" style=\"background:none;border:none;padding:0;cursor:pointer;\">" +
                                        "<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#2d3e50\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"/><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"/></svg>" +
                                    "</button>" +
                                "</div>" +
                            "</div>"
                        );
                    } else if (addingTarriffCode) {

                        let customsString = TariffCode + ' • ' + TariffDesc + ' • ' + Amount + ' €' + ' • ' + Weight + ' Kg' + ' • ' + Units + ' Unid.';

                        if(Country) {
                            customsString += ' • ' + Country;
                        }

                        co_AddedDescription.append(
                            "<div class=\"chip-row\" style=\"display:flex;align-items:center;gap:8px;margin-bottom:6px;\">" +
                                "<input class='chip col-sm-12' readonly id='customs_desc" +
                                type +
                                '_' +
                                co_cloneNumber +
                                '_' +
                                (co_DescriptionCounter[co_cloneNumber]) +
                                "' name='customs_desc_" +
                                co_cloneNumber +
                                '_' +
                                (co_DescriptionCounter[co_cloneNumber]) +
                                "' value='" + customsString + "' />" +
                                "<div class=\"chip-actions\">" +
                                    "<button type=\"button\" class=\"chip-edit-btn\" title=\"Editar\" style=\"background:none;border:none;padding:0;cursor:pointer;\">" +
                                        "<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#2d3e50\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M12 20h9\"/><path d=\"M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z\"/></svg>" +
                                    "</button>" +
                                    "<button type=\"button\" class=\"chip-delete-btn\" title=\"Eliminar\" style=\"background:none;border:none;padding:0;cursor:pointer;\">" +
                                        "<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#2d3e50\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"/><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"/></svg>" +
                                    "</button>" +
                                "</div>" +
                            "</div>"
                        );
                    }
                
                    // Verificar si el número de descripciones es menor o igual a 5
                    if (co_DescriptionCounter[co_cloneNumber] <= 5) {
                        // Habilitar los botones
                        jQuery('#customs_correos_container' + type + ' #add_description_' + co_cloneNumber).prop('disabled', false);
                        jQuery('#customs_correos_container' + type + ' #del_description_' + co_cloneNumber).prop('disabled', false);
                    } else {
                        // Deshabilitar los botones si el contador es mayor a 5
                        jQuery('#customs_correos_container' + type + ' #add_description_' + co_cloneNumber).prop('disabled', true);
                        jQuery('#customs_correos_container' + type + ' #del_description_' + co_cloneNumber).prop('disabled', true);
                    }

                    // Incrementar el contador de descripciones después de agregar la descripción
                    co_DescriptionCounter[co_cloneNumber]++;

                
                    // Actualizar los contadores según el tipo
                    if (type == '_shipping') {
                        co_DescriptionCounter_shipping++;
                    } else if (type == '_return') {
                        co_DescriptionCounter_return++;
                    }
                }
                
                // Limpiar los valores de los campos relacionados
                AmountElement.val('');
                WeightElement.val('');
                UnitsElement.val('');
            });
            jQuery('#customs_correos_container_shipping .del_description, #customs_correos_container_return .del_description').on('click', function (event) {
                event.preventDefault();
                
                // Primero actualizamos co_cloneNumber
                co_cloneNumber = jQuery(this).attr('data-number'); 
                
                if (co_DescriptionCounter[co_cloneNumber] < 1) {
                    return;
                }
            
                // Decrementamos el contador
                co_DescriptionCounter[co_cloneNumber]--;
            
                // Decrementar el contador correspondiente
                if (type == '_shipping') {
                    co_DescriptionCounter_shipping--;
                } else if (type == '_return') {
                    co_DescriptionCounter_return--;
                }
            
                // Aquí se está generando correctamente la ID para eliminar la descripción
                jQuery('#customs_correos_container' + type + ' #customs_desc' + type + '_' + co_cloneNumber + '_' + co_DescriptionCounter[co_cloneNumber]).remove();
                jQuery('#customs_correos_container' + type + ' #customs_tariff' + type + '_' + co_cloneNumber + '_' + co_DescriptionCounter[co_cloneNumber]).remove();
            
                // Lógica para habilitar/deshabilitar los botones
                if (co_DescriptionCounter[co_cloneNumber] == 0) {
                    jQuery('#customs_correos_container' + type + ' #add_description_' + co_cloneNumber).prop('disabled', false);
                    jQuery('#customs_correos_container' + type + ' #del_description_' + co_cloneNumber).prop('disabled', true);
                } else if (co_DescriptionCounter[co_cloneNumber] < 5) {
                    jQuery('#customs_correos_container' + type + ' #add_description_' + co_cloneNumber).prop('disabled', false);
                    jQuery('#customs_correos_container' + type + ' #del_description_' + co_cloneNumber).prop('disabled', false);
                }
            });
            
            

            /////////////////////////////////////////////////////////////////////////////////////////
            ///////////////////////////////        LIMPIA EL +34                  ///////////////////
            /////////////////////////////////////////////////////////////////////////////////////////
            const phoneField = jQuery('#customer_phone').val();

            let newPhoneField = phoneField.replace(/0034|0034\s|\+34|\+34\s/g, '').trim();

            jQuery('#customer_phone').val(newPhoneField);

            // Copiar contenido citypack u oficina
            jQuery('#copyCityPaqContent').on('click', function () {
                getCityPaqContent();
            });

            jQuery('#copyOfficeContent').on('click', function () {
                getOfficeContent();
            });

            /////////////////////////////////////////////////////////////////////////////////////////
            ///////////////////////////////        FUNCIONES                  ///////////////////////
            /////////////////////////////////////////////////////////////////////////////////////////

            function getCityPaqContent () {
                let co_address = jQuery('#citypaq_address').val();
                let co_city = jQuery('#citypaq_city').val();
                let co_cp = jQuery('#citypaq_cp').val();

                let combinedText = co_titleAddress + co_address + '\n' + co_titleCity + co_city + '\n' + co_titleCp + co_cp;
                let tempTextArea = document.createElement("textarea");

                tempTextArea.value = combinedText;
                document.body.appendChild(tempTextArea);

                // Seleccionar y copiar el contenido del textarea
                tempTextArea.select();
                document.execCommand("copy");
                tempTextArea.remove();

                showNotification('#copyCityPaqContent');
            }

            function getOfficeContent () {
                let co_address = jQuery('#office_address').val();
                let co_city = jQuery('#office_city').val();
                let co_cp = jQuery('#office_cp').val();

                let combinedText = co_titleAddress + co_address + '\n' + co_titleCity + co_city + '\n' + co_titleCp + co_cp;
                let tempTextArea = document.createElement("textarea");

                tempTextArea.value = combinedText;
                document.body.appendChild(tempTextArea);

                // Seleccionar y copiar el contenido del textarea
                tempTextArea.select();
                document.execCommand("copy");
                tempTextArea.remove();

                showNotification('#copyOfficeContent');
            }

            function showNotification(buttonSelector) {
                // Crear el cuadro de notificación
                let notification = jQuery('#contentCopied');
                let button = jQuery(buttonSelector);
                button.offset();

                notification.removeClass('hidden-block').css({
                    'position': 'absolute',
                    'bottom': '80%',
                    'left': '43%', 
                    'transform': 'translateX(-50%)',
                    'color': '#664d03',
                    'background-color': '#fff3cd',
                    'border-color': '#d2a63c',
                    'padding': '10px',
                    'border-radius': '5px',
                    'font-size': '14px',
                    'z-index': '1000',  /* Asegúrate de que el z-index sea alto */
                    'white-space': 'nowrap',
                    'display': 'block',
                    'box-shadow': '0px 4px 8px rgba(0, 0, 0, 0.3)'
                });
            
                setTimeout(function () {
                    notification.fadeOut(500, function () {
                        notification.addClass('hidden-block');
                        notification.removeAttr('style');
                    });
                }, 1500);
            }

            function labelsSelectActions(label_type) {
                switch (label_type) {
                    case '0': // Adhesiva
                        jQuery('#input_pos_etiqueta_container_reimpresion').show();
                        jQuery('#input_format_etiqueta_container_reimpresion').show();

                        let format_selected = jQuery('#input_format_etiqueta_reimpresion').val();

                        switch (format_selected) {
                            case '1': // 3/A4
                                loadLabelSelectPositions('#input_pos_etiqueta_reimpresion', 3);
                                break;
                            default: // Estandar y 4/A4
                                loadLabelSelectPositions('#input_pos_etiqueta_reimpresion', 4);
                                break;
                        }

                        jQuery('#input_format_etiqueta_reimpresion').on('change', function () {
                            switch (this.value) {
                                case '1': // 3/A4
                                    loadLabelSelectPositions('#input_pos_etiqueta_reimpresion', 3);
                                    break;
                                default: // Estandar y 4/A4
                                    loadLabelSelectPositions('#input_pos_etiqueta_reimpresion', 4);
                                    break;
                            }
                        });
                        break;
                    case '1': // Medio Folio
                        loadLabelSelectPositions('#input_pos_etiqueta_reimpresion', 2);

                        jQuery('#input_pos_etiqueta_container_reimpresion').show();

                        break;

                    case '2': // Térmica
                        jQuery('#input_pos_etiqueta_container_reimpresion').hide();

                        // Reset input formato
                        jQuery('#input_format_etiqueta_container_reimpresion').hide();
                        jQuery('#input_format_etiqueta_reimpresion').val(0);

                        break;

                    default:
                        break;
                }
            }

            // Funcion que nos permite rellenar dinámicamente el select de posiciones de etiquetas
            function loadLabelSelectPositions(element, positions) {
                let select_input = jQuery(element);

                select_input.empty();
                for (let i = 1; i <= positions; i++) {
                    select_input.append('<option value="' + i + '">' + i + '</option>');
                }
            }

            // Botón seleccionar remitente desde modal
            jQuery('#errorSender-change').on('click', (e) => {
                e.preventDefault();
                jQuery('.errorSender-screen').hide();
                jQuery('#senderSelect').focus();
            });

            function senderErrorModal() {
                // Sender Name
                let error_sender_name = jQuery('.errorSender-text .error_sender_name');
                let optionSenderText = jQuery('#senderSelect option:selected').text();
                error_sender_name.text(optionSenderText);

                // Company Name
                let error_company_name = jQuery('.errorSender-text .error_company_name');
                let optionDataCompany = jQuery('#input_select_carrier option:selected').attr('data-company');
                error_company_name.text(optionDataCompany);

                // Mostramos modal
                jQuery('.errorSender-screen').show();
                const position = jQuery('.errorSender-screen').offset().top - 200;
                jQuery('html, body').animate(
                    {
                        scrollTop: position,
                    },
                    1000
                );
            }
        }
    });

    /**
     * eliminar tracking_number al cancelar un envío en Prestashop
     */
    function removeTrackingNumberInfo() {
        if (platform == 'ps') {
            jQuery('#orderShippingTabContent .table td:last').prev().html('');
        }
    }

    // COMPROBACION VISUAL DE RECOGIDAS SEGUN CORREOS O CEX

    function setCheckTrue() {
        jQuery('#inputCheckSavePickup').prop('checked', true)
        showContent(true);
    }

    function setCheckFalse() {
        jQuery('#inputCheckSavePickup').prop('checked', false)
        showContent(false);
    }

    function showContent(isChecked) {
        selectedCompany = selectedCompany == '' ? company : selectedCompany;

        selectedCompany == 'Correos' ? inputLabel.removeClass('hidden-block') && inputPackageSize.removeClass('hidden-block') 
        : inputLabel.addClass('hidden-block') && inputPackageSize.addClass('hidden-block');

        if (isChecked) {
            container.removeClass('hidden-block');
        } else {
            container.addClass('hidden-block');
        }
    }

    function checkCorreosOrCEX(selectedCompany) {

        selectedCompany = selectedCompany == '' ? company : selectedCompany;

        let fromTime = jQuery('#sender_from_time').val();
        let toTime =   jQuery('#sender_to_time').val();

        if (selectedCompany == 'Correos') {
            setCheckFalse();
        } else if (selectedCompany == 'CEX' && (fromTime != toTime)) {
            setCheckTrue();
        } else {
            setCheckFalse();
        }
        
    }
}