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
jQuery(document).ready(function () {
    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                                VALIDACION REMITENTES                                 //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    /* Reglas */
    jQuery('#CorreosSendersForm').validate({
    rules: {
        sender_name: {
            required: true,
            minlength: 3,
            maxlength: 40,
        },
        sender_contact: {
            required: false,
            minlength: 3,
            maxlength: 40,
        },
        sender_address: {
            required: true,
            minlength: 3,
            maxlength: 100,
        },
        sender_city: {
            required: true,
            minlength: 3,
            maxlength: 40,
        },
        sender_cp: {
            required: true,
            minlength: 3,
            maxlength: 8,
        },
        sender_iso_code_pais: {
            required: true,
        },
        sender_phone: {
            required: false,
        },
        sender_email: {
            required: false,
            minlength: 3,
            maxlength: 50,
        },
        sender_nif_cif: {
            required: true,
            maxlength: 30,
            validate_nif_cif_nie: false,
        },
        correos_code: {
            selectOneRequired: true,
        },
        cex_code: {
            selectOneRequired: true,
        },
    },
    messages: {
        sender_name: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 40 ' + characters,
        },
        sender_contact: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 40 ' + characters,
        },
        sender_address: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 100 ' + characters,
        },
        sender_city: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 40 ' + characters,
        },
        sender_cp: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 8 ' + characters,
        },
        sender_iso_code_pais: {
            required: requiredCustomMessage,
        },
        sender_phone: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 15 ' + characters,
        },
        sender_email: {
            required: requiredCustomMessage,
            minlength: minLengthMessage + ' 3 ' + characters,
            maxlength: maxLengthMessage + ' 50 ' + characters,
            email: invalidEmail,
        },
        sender_nif_cif: {
            required: requiredCustomMessage,
        },
    } /* Fin validaciones */,

    submitHandler: function () {
        let table = jQuery('#SendersDataTable').DataTable();
        let rows = table.rows();

        let is_first_sender = '0';

        if (rows['0'].length == 0) {
            is_first_sender = '1';
        }

        let sender_name = jQuery('#sender_name').val();

        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: {
                action: 'correosOficialDispacher',
                _nonce: varsAjax.nonce,
                dispatcher: {
                    controller: 'AdminCorreosOficialSendersProcess',
                    action: 'CorreosSendersInsertForm',
                    sender_name: sender_name,
                    sender_address: jQuery('#sender_address').val(),
                    sender_cp: jQuery('#sender_cp').val(),
                    sender_nif_cif: jQuery('#sender_nif_cif').val(),
                    sender_city: jQuery('#sender_city').val(),
                    sender_contact: jQuery('#sender_contact').val(),
                    sender_phone: jQuery('#sender_phone').val(),
                    sender_from_time: jQuery('#sender_from_time').val(),
                    sender_to_time: jQuery('#sender_to_time').val(),
                    sender_iso_code_pais: jQuery('#sender_iso_code_pais').val(),
                    sender_email: jQuery('#sender_email').val(),
                    correos_code: jQuery('#correos_code').val(),
                    cex_code: jQuery('#cex_code').val(),
                    sender_default: is_first_sender,
                },
            },
            success: function (response) {
                jQuery('#SendersDataTable').DataTable().ajax.reload();
                document.getElementById('CorreosSendersForm').reset();
                reloadSenderContractsSelects();

                showModalInfoWindow(senderDefaultSaved);
            },
        });
    },
});

jQuery('#SendersDataTable').on('click', '.remove', function () {
    let table = jQuery('#SendersDataTable').DataTable();
    let row = table.row(jQuery(this).parents('tr')[0]);

    let data_senders_request = {
        sender_id: table.row(row).data().id,
    };

    jQuery('#myModal').find('#myModalActionButtonSenders').html('Eliminar');
    jQuery('#myModal').find('.myModalActionButton').hide();
    jQuery('#myModalActionButtonSenders').show();
    jQuery('#myModal').find('#myModalDescription').html('<p>¿Está seguro de borrar el remitente?</p>');
    jQuery('#myModal').data('id', table.row(row).data().id).modal('show');

    //Aceptar
    jQuery('body').on('click', '#myModalActionButtonSenders', function () {
        let id = jQuery('#myModal').data('id');
        table.row('#'.id).remove().draw();
        jQuery('#myModal').modal('hide');

        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: {
                action: 'correosOficialDispacher',
                _nonce: varsAjax.nonce,
                dispatcher: {
                    controller: 'AdminCorreosOficialSendersProcess',
                    action: 'CorreosSendersDeleteForm',
                    sender_id: data_senders_request.sender_id,
                },
            },
            success: function (response) {
                jQuery('#SendersDataTable').DataTable().ajax.reload();
                document.getElementById('CorreosSendersForm').reset();
            },
        });
    });

    //Cancelar
    jQuery('body').on('click', '#myModalCancelButton', function () {
        jQuery('#myModal').modal('hide');
    });
});

// ---- EDITAR REMITENTE -----------------------------------------------------------------

jQuery('#SendersEditButton').click(function (event) {
    if (jQuery('#CorreosSendersForm').valid()) {
        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: {
                action: 'correosOficialDispacher',
                _nonce: varsAjax.nonce,
                dispatcher: {
                    controller: 'AdminCorreosOficialSendersProcess',
                    action: 'CorreosSendersUpdateForm',
                    sender_id: jQuery('#sender_id').val(),
                    sender_name: jQuery('#sender_name').val(),
                    sender_address: jQuery('#sender_address').val(),
                    sender_cp: jQuery('#sender_cp').val(),
                    sender_nif_cif: jQuery('#sender_nif_cif').val(),
                    sender_city: jQuery('#sender_city').val(),
                    sender_contact: jQuery('#sender_contact').val(),
                    sender_phone: jQuery('#sender_phone').val(),
                    sender_from_time: jQuery('#sender_from_time').val(),
                    sender_to_time: jQuery('#sender_to_time').val(),
                    sender_iso_code_pais: jQuery('#sender_iso_code_pais').val(),
                    sender_email: jQuery('#sender_email').val(),
                    correos_code: jQuery('#correos_code').val(),
                    cex_code: jQuery('#cex_code').val(),
                },
                success: function (response) {
                    document.getElementById('CorreosSendersForm').reset();
                    document.getElementById('SendersEditButton').disabled = true;
                    document.getElementById('SendersSaveButton').disabled = false;
                    reloadSenderContractsSelects();

                    showModalInfoWindow(senderDefaultSaved);

                    jQuery('#SendersDataTable').DataTable().ajax.reload();
                },
            },
        });
    }
});

// ---- GUARDAR REMITENTE POR DEFECTO ----------------------------------------------------

jQuery('#SenderDefaultSaveButton').click(function (event) {
    let sender_default_id = jQuery('#sender_default_select').val();
    jQuery.ajax({
        type: 'post',
        url: varsAjax.ajaxUrl,
        data: {
            action: 'correosOficialDispacher',
            _nonce: varsAjax.nonce,
            dispatcher: {
                controller: 'AdminCorreosOficialSendersProcess',
                action: 'CorreosSenderSaveDefaultForm',
                sender_default_id: sender_default_id,
            },
        },
        success: function (data) {
            jQuery('#SendersDataTable').DataTable().ajax.reload();
        },
    });
    jQuery('#SendersDataTable').on('draw.dt', function () {
        showModalInfoWindow(senderDefaultSaved);
    });
});

    // ---- BORRADO DE REMITENTE -------------------------------------------------------------

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                           VALIDAR CONFIGURACION DE USUARIO                           //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    /* Añadimos una nueva regla que compruebe que las dimensiones son 10x15x1 como mínimo,
    es decir, que sean mayores que 0, uno mayor que 10 y otro mayor de 15 */
    jQuery.validator.addMethod('dimensionsValidation', function (value, element) {
        var values = [parseInt(jQuery('#DimensionsByDefaultHeight').val()), parseInt(jQuery('#DimensionsByDefaultWidth').val()), parseInt(jQuery('#DimensionsByDefaultLarge').val())];
        var mayorQue0 = values.every((num) => num > 0);
        var mayorQue10 = false;
        var mayorQue15 = false;

        for (var i = values.length - 1; i > -1; i--) {
            if (values[i] >= 15 && mayorQue15 === false) {
                mayorQue15 = true;
                values.splice(i, 1);
            }
            if (values[i] >= 10 && mayorQue10 === false) {
                mayorQue10 = true;
                values.splice(i, 1);
            }
        }

        return mayorQue0 && mayorQue10 && mayorQue15;
    });

    jQuery('#UserConfigurationDataForm').validate({
        rules: {
            DefaultPackages: {
                required: true,
                min: 1,
                max: 10,
            },
            BankAccNumberAndIBAN: {
                required: false,
                validate_acc_iban: false,
            },
            GoogleMapsApi: {
                required: false,
                maxlength: 150,
            },
            LabelAlternativeText: {
                required: true,
                minlength: 3,
                maxlength: 40,
            },
            WeightByDefault: {
                required: true,
                min: 0.1,
                max: 30,
            },
            DimensionsByDefaultHeight: {
                required: true,
                dimensionsValidation: true,
            },
            DimensionsByDefaultWidth: {
                required: true,
                dimensionsValidation: true,
            },
            DimensionsByDefaultLarge: {
                required: true,
                dimensionsValidation: true,
            },
            ShowLabelData: {
                required: true,
                min: 1,
                max: 30,
            },
            NifFieldPersonalizedValue: {
                required: function (element) {
                    return jQuery('#NifFieldPersonalized').is(':checked');
                },
            },
        },
        /* Mensaje custom por campo  */
        messages: {
            DefaultPackages: {
                min: minValue1,
                max: maxValue10,
            },
            BankAccNumberAndIBAN: {
                required: requiredCustomMessage,
                validate_acc_iban: wrongACCAndIBAN,
            },
            GoogleMapsApi: {
                maxlength: maxLengthMessage + ' 150 ' + characters,
            },
            LabelAlternativeText: {
                required: requiredCustomMessage,
                minlength: minLengthMessage + ' 3 ' + characters,
                maxlength: maxLengthMessage + ' 40 ' + characters,
            },
            WeightByDefault: {
                required: requiredCustomMessage,
                min: valuesWeightDefault,
                max: valuesWeightDefault,
            },
            DimensionsByDefaultHeight: {
                dimensionsValidation: valuesDimensionDefault,
            },
            DimensionsByDefaultWidth: {
                dimensionsValidation: valuesDimensionDefault,
            },
            DimensionsByDefaultLarge: {
                dimensionsValidation: valuesDimensionDefault,
            },
            NifFieldPersonalizedValue: {
                required: requiredCustomMessage,
            },
        },
        groups: {
            valuesDimensionDefault: 'DimensionsByDefaultHeight DimensionsByDefaultWidth DimensionsByDefaultLarge',
        },

        submitHandler: function () {
            let formElement = document.getElementById('UserConfigurationDataForm');
            let sourceForm = new FormData(formElement);
            let destinyForm = new FormData();

            // Agregar los campos adicionales requeridos al objeto FormData
            destinyForm.append('action', 'correosOficialDispacher');
            destinyForm.append('_nonce', varsAjax.nonce);
            destinyForm.append('dispatcher[controller]', 'AdminCorreosOficialUserConfigurationProcess');
            destinyForm.append('dispatcher[action]', 'UserConfigurationDataForm');

            sourceForm.forEach((value, key) => {
                destinyForm.append('dispatcher[' + key + ']', value);
            });

            let imgLogoName = '';

            if (typeof document.getElementById('UploadLogoLabels').files[0] !== 'undefined') {
                imgLogoName = document.getElementById('UploadLogoLabels').files[0].name;
            } else {
                imgLogoName = 'default.jpg';
            }

            jQuery('#ProcessingUserConfigButton').removeClass('hidden-block');
            jQuery('#MsgUserConfigButton').addClass('hidden-block');

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: destinyForm,
                contentType: false,
                cache: false,
                processData: false,
                success: function (data) {
                    jQuery('#response').html(data);
                    let obj = JSON.parse(data);

                    if (obj.error == 'Error') {
                        showModalErrorWindow(obj.desc);
                    } else {
                        if (obj.savedLogo != '') {
                            jQuery('#UploadLogoLabelsImg').attr('src', obj.savedLogo);
                            jQuery('#UploadLogoLabelsText').html(imgLogoName);
                        }
                        showModalInfoWindow(userConfigurationSaved);
                    }

                    jQuery('#ProcessingUserConfigButton').addClass('hidden-block');
                    jQuery('#MsgUserConfigButton').removeClass('hidden-block');
                },
                error: function (e) {
                    alert('ERROR 12000: Error al enviar el formulario Configuración de usuario.');
                },
            });
        },
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                     SELECTOR DE PRODUCTOS PARA PROCESO DE COMPRA                     //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    jQuery('#CorreosProductsForm').submit(function (e) {
        let destinyForm = new FormData();

        // Agregar los campos adicionales requeridos al objeto FormData
        destinyForm.append('action', 'correosOficialDispacher');
        destinyForm.append('_nonce', varsAjax.nonce);
        destinyForm.append('dispatcher[controller]', 'AdminCorreosOficialProductsProcess');
        destinyForm.append('dispatcher[action]', 'CorreosProductsForm');

        // Obtener todos los checkboxes del formulario por su clase
        let checkboxes = document.getElementsByClassName('form-check-input');

        // Recorrer el objeto products y agregar los productos seleccionados al formulario destinyForm
        for (let i = 0; i < checkboxes.length; i++) {
            let checkbox = checkboxes[i];
            if (checkbox.checked) {
                let name = checkbox.name;
                let value = checkbox.value;
                let matches = name.match(/^products\[(\d+)\]$/); // Buscar coincidencias con el formato 'products[numProduct]'
                if (matches) {
                    let productNumber = matches[1];
                    destinyForm.append(`dispatcher[products][${productNumber}]`, value);
                }
            }
        }

        jQuery('#ProcessingProductsButton').removeClass('hidden-block');
        jQuery('#MsgSaveProductsButton').addClass('hidden-block');

        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: destinyForm,
            contentType: false,
            cache: false,
            processData: false,
            success: function (data) {
                showModalInfoWindow(productsSaved);
                let obj = JSON.parse(data);
                switch (obj.info) {
                    case 'INFO 14503':
                    case 'INFO 14504':
                    case 'INFO 14505':
                    case 'INFO 14506':
                        showModalInfoWindow(obj.desc);
                        break;
                }

                jQuery.ajax({
                    type: 'post',
                    url: varsAjax.ajaxUrl,
                    data: {
                        action: 'correosOficialDispacher',
                        _nonce: varsAjax.nonce,
                        dispatcher: {
                            controller: 'AdminCorreosOficialProductsProcess',
                            action: 'getActiveProducts',
                        },
                    },
                    success: function (response) {
                        jQuery('#ProcessingProductsButton').addClass('hidden-block');
                        jQuery('#MsgSaveProductsButton').removeClass('hidden-block');
                        jQuery('#CorreosZonesCarriersForm .scp_products').each(function () {
                            let select_name = jQuery(this).attr('name');
                            let select_value = jQuery(this).val();
                            if (select_value == null) {
                                jQuery(this).empty();
                                jQuery('#' + select_name).append('<option value="0">Select a product</option>');
                                jQuery.each(JSON.parse(response), function (key, value) {
                                    if (value.product_type == 'office' || value.product_type == 'citypaq') {
                                        jQuery('#' + select_name).append('<option value=' + value.id + ' disabled>' + value.name + '</option>');
                                    } else {
                                        jQuery('#' + select_name).append('<option value=' + value.id + '>' + value.name + '</option>');
                                    }
                                });
                            } else {
                                jQuery(this).empty();
                                jQuery('#' + select_name).append('<option selected="" disabled="" value="0">Select a product</option>');
                                jQuery.each(JSON.parse(response), function (key, value) {
                                    if (select_value == value.id) {
                                        jQuery('#' + select_name).append('<option selected="selected" value=' + value.id + '>' + value.name + '</option>');
                                    } else {
                                        if (value.product_type == 'office' || value.product_type == 'citypaq') {
                                            jQuery('#' + select_name).append('<option value=' + value.id + ' disabled>' + value.name + '</option>');
                                        } else {
                                            jQuery('#' + select_name).append('<option value=' + value.id + '>' + value.name + '</option>');
                                        }
                                    }
                                });
                            }
                        });
                    },
                });
            },
            error: function (e) {
                showModalErrorWindow('ERROR 14502: ' + product_technical_error);
            },
        });
        e.preventDefault(); // Prevenir el envío del formulario (solicitud tradicional)
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                                ZONAS Y TRANSPORTISTAS                                //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    jQuery('#CorreosZonesCarriersForm').submit(function (e) {
        let sourceForm = new FormData(this);
        let destinyForm = new FormData();

        destinyForm.append('action', 'correosOficialDispacher');
        destinyForm.append('_nonce', varsAjax.nonce);
        destinyForm.append('dispatcher[controller]', 'AdminCorreosOficialZonesCarriersProcess');

        sourceForm.forEach((value, key) => {
            destinyForm.append('dispatcher[' + key + ']', value);
        });

        jQuery.ajax({
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: destinyForm,
            contentType: false,
            cache: false,
            processData: false,
            success: function (data) {
                showModalInfoWindow(zonesCarriersSaved);
            },
        });
        e.preventDefault();
    });

    //--------------------------------------------------------------------------------------//
    //                                                                                      //
    //                        GUARDAR TRAMITACION ADUANERA DE ENVIOS                        //
    //                                                                                      //
    //--------------------------------------------------------------------------------------//

    jQuery('#CustomProcessingForm').validate({
        rules: {
            ShippCustomsReference: {
                required: false,
                minlength: 3,
                maxlength: 50,
            },
            MessageToWarnBuyer: {
                required: false,
            },
            TranslatableInput: {
                required: true,
                minlength: 3,
                maxlength: 100,
            },
            TariffDescription: {
                required: false,
                minlength: 3,
                maxlength: 100,
            },
            Tariff: {
                required: false,
                TariffLength: true,
            },
        },
        messages: {
            ShippCustomsReference: {
                required: requiredCustomMessage,
                minlength: minLengthMessage + ' 3 ' + characters,
                maxlength: maxLengthMessage + ' 50 ' + characters,
            },
            TranslatableInput: {
                required: requiredCustomMessage,
                minlength: minLengthMessage + ' 3 ' + characters,
                maxlength: maxLengthMessage + ' 100 ' + characters,
            },
            TariffDescription: {
                minlength: minLengthMessage + ' 3 ' + characters,
                maxlength: maxLengthMessage + ' 100 ' + characters,
            },
            Tariff: {
                TariffLength: tariffLength,
            },
        }, // Fin Validaciones

        submitHandler: function () {
            /** Procesamos el formulario de Aduanas*/
            let formElement = document.getElementById('CustomProcessingForm');
            let sourceForm = new FormData(formElement);
            let destinyForm = new FormData();

            // Agregar los campos adicionales requeridos al objeto FormData
            destinyForm.append('action', 'correosOficialDispacher');
            destinyForm.append('_nonce', varsAjax.nonce);
            destinyForm.append('dispatcher[controller]', 'AdminCorreosOficialCustomsProcessingProcess');

            sourceForm.forEach((value, key) => {
                destinyForm.append('dispatcher[' + key + ']', value);
            });

            // Usar el valor del hidden que refleja el tab seleccionado (0, 1 o 2)
            let selectedTab = document.getElementById('CustomsDesriptionAndTariffSelected');
            let tabValue = selectedTab ? selectedTab.value : '0';
            destinyForm.append('dispatcher[CustomsDesriptionAndTariff]', tabValue);

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: destinyForm,
                contentType: false,
                cache: false,
                processData: false,
                success: function (data) {
                    showModalInfoWindow(customsProcessingSaved);
                },
                error: function (e) {
                    alert('ERROR 13000: Error al enviar el formulario Tramitación Aduanera de Envíos');
                },
            });
        },
    });

    jQuery('#showAllCarriersCheck').change(function () {
        if (jQuery('#showAllCarriersCheck').is(':checked')) {
            jQuery('.hidden-product-option').show();
        } else {
            jQuery('.hidden-product-option').hide();
        }
    });

    jQuery('#ActivateNifFieldCheckout').on('click', function () {
        if (!this.checked) {
            const nifFieldPersonalized = jQuery('#NifFieldPersonalized');
            const personalizedNifValue = jQuery('#NifFieldPersonalizedValue');

            // Verificar si el checkbox está marcado y el valor del campo no está vacío
            if (nifFieldPersonalized.prop('checked') && personalizedNifValue.val().trim() == '') {
                alert('No guardes la configuración sin dejar el campo NIF personalizado vacío');
                jQuery('#UserConfigurationSaveButton').prop('disabled', true);
            }
        } else {
            jQuery('#UserConfigurationSaveButton').prop('disabled', false);
        }
    });
});