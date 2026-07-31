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
if (!sga_module) {
    jQuery(document).ready(function () {

    const mainContentLoaded = document.getElementById('correos_oficial_main_container');
    let markers = [];

    if (mainContentLoaded) {
        let pickupDone = false;
        //                            Buscador de Oficina - CityPaq                             //
        if (typeof google !== 'undefined') {
            /* MAPAS OFICINA Y CITYPAQ */
            var mapOfficeObj = new google.maps.Map(document.getElementById('mapOffice'), {
                center: { lat: 40.234013044698884, lng: -3.768710630003362 },
                zoom: 13,
            });

            var mapCityPaqObj = new google.maps.Map(document.getElementById('mapCityPaq'), {
                center: { lat: 40.234013044698884, lng: -3.768710630003362 },
                zoom: 13,
            });

            var mapPudoCEXObj = new google.maps.Map(document.getElementById('mapPudoCEX'), {
                center: { lat: 40.234013044698884, lng: -3.768710630003362 },
                zoom: 13,
            });
        }

        /* FUNCIONALIDAD OFICINA */
        jQuery('#changeOffice').on('click', function (e) {
            jQuery('.change-container-office').toggle();
            jQuery('#mapOffice').hide();
        });

        jQuery('#searchOfficeButton').on('click', function (event) {
            jQuery('#office-list').find('option').remove();

            let postcode = jQuery('#input_cp_office').val();
            // Datos de las oficinas del webservice de localizador de oficinas
            let offices = '';

            let sender_id = jQuery('#senderSelect').val();
            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let company = selected_carrier.data('company');

            let province   = postcode.substring(0, 2);
            let adresse_cp = jQuery('#customer_cp').val().substring(0, 2);

            if (province !== adresse_cp) {
                alert(wrong_province);
                return;
            }

            jQuery.ajax({
                type: 'POST',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialCheckoutModuleFrontController',
                        action: 'SearchOfficeByPostalCode',
                        token: static_token,
                        postcode: postcode,
                        sender_id: sender_id,
                        company: company,
                        order_detail_page: true,
                        order_id: jQuery('#id_order_hidden').val()
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    jQuery('.map-info-office').hide();
                    jQuery('#mapOffice').hide();
                    jQuery('#inputSelectOffices').hide();
                    jQuery('#office-list').hide();
                    document.getElementById('office_address').value = '';
                    document.getElementById('office_city').value = '';
                    document.getElementById('office_cp').value = '';
                    document.getElementById('cod_office').value = '';
                    jQuery('#no_offices_zip_message').removeClass('hidden-block');
                },
                success: function (parsed_data) {
                    let dir_office, loc_office, cp_office, cod_office;
                    
                    parsed_data.forEach(function (location, index, array) {

                        if (index == 0) {

                            dir_office = location.address;
                            loc_office = location.city;
                            cp_office  = location.zipcode;
                            cod_office = location.reference;

                            // Informamos los campos ocultos con la primera oficina cuando hacemos click con el botón Buscar (3)
                            jQuery('#reference_code').val(cod_office);
                            jQuery('#request_data').val(JSON.stringify(location.data));

                            document.getElementById('dir-office').innerHTML = dir_office;
                            document.getElementById('loc-office').innerHTML = loc_office;
                            document.getElementById('cp-office').innerHTML = cp_office;
                            document.getElementById('cod_office').value = cod_office;

                            document.getElementById('office_address').value = dir_office;
                            document.getElementById('office_city').value = loc_office;
                            document.getElementById('office_cp').value = cp_office;

                            const myLatLng = {
                                lat: parseFloat(location.lat),
                                lng: parseFloat(location.long),
                            };

                            if (typeof google !== 'undefined') {
                                // Verificar si hay marcadores existentes para este transportista y eliminamos
                                if (markers.length > 0) {
                                    markers.forEach(function (marker) {
                                        marker.setMap(null);
                                    });
                                }

                                let marker = new google.maps.Marker({
                                    position: myLatLng,
                                    title: location.name,
                                });
                                marker.setMap(mapOfficeObj);
                                markers.push(marker);

                                mapOfficeObj.setCenter(myLatLng);
                                mapOfficeObj.setZoom(14);
                            }
                        }

                        jQuery('#inputSelectOffices').append('<option value=' + index + '>' + location.name + '</option>');
                    });

                    // Acciones cuando cambia el selector de Oficinas
                    jQuery('#inputSelectOffices').on('change', function (e) {

                        let locationSelected = parsed_data[jQuery(this).val()];

                        dir_office = locationSelected.address;
                        loc_office = locationSelected.city;
                        cp_office  = locationSelected.zipcode;
                        cod_office = locationSelected.reference;

                        // Informamos los campos ocultos cambiar el selector de Oficinas (1)
                        jQuery('#reference_code').val(cod_office);
                        jQuery('#request_data').val(JSON.stringify(locationSelected.data));

                        document.getElementById('dir-office').innerHTML = dir_office;
                        document.getElementById('loc-office').innerHTML = loc_office;
                        document.getElementById('cp-office').innerHTML = cp_office;
                        document.getElementById('cod_office').value = cod_office;

                        document.getElementById('office_address').value = dir_office;
                        document.getElementById('office_city').value = loc_office;
                        document.getElementById('office_cp').value = cp_office;

                        const myLatLng = {
                            lat: parseFloat(locationSelected.lat),
                            lng: parseFloat(locationSelected.long),
                        };

                        if (typeof google !== 'undefined') {

                            // Verificar si hay marcadores existentes para este transportista y eliminamos
                            if (markers.length > 0) {
                                markers.forEach(function (marker) {
                                    marker.setMap(null);
                                });
                            }

                            let marker = new google.maps.Marker({
                                position: myLatLng,
                                title: locationSelected.name,
                            });


                            marker.setMap(mapOfficeObj);
                            markers.push(marker);

                            mapOfficeObj.setCenter(myLatLng);
                            mapOfficeObj.setZoom(14);
                        }
                    });

                    jQuery('#selectOfficeButton').on('click', function (e) {
                        
                        let locationSelected = parsed_data[jQuery('#inputSelectOffices').val()];
                        
                        jQuery('.change-container-office').hide();
                        document.getElementById('office_address').value = dir_office;
                        document.getElementById('office_city').value = loc_office;
                        document.getElementById('office_cp').value = cp_office;
                        document.getElementById('cod_office').value = cod_office;

                        // Informamos los campos ocultos cuando hacemos click con el botón Seleccionar Oficina (2)
                        jQuery('#reference_code').val(cod_office);
                        jQuery('#request_data').val(JSON.stringify(locationSelected.data));
                        
                        // Actualizar los campos de dirección de envío en el formulario de WooCommerce
                        updateShippingAddressFields(locationSelected);
                    });

                    jQuery('#inputSelectOffices').show();
                    jQuery('#office-list').show();
                    jQuery('.map-info-office').show();
                    jQuery('#mapOffice').show();
                    jQuery('#no_offices_zip_message').addClass('hidden-block');
                },
            });
            event.preventDefault();
        });

        /**
     * Cambio de País en bloque Destinatario
     */
    jQuery('#customer_country').on('change', function(e){
        let destination = jQuery(this).val();
        
        // $cp_source, $cp_dest, $country_source, $country_dest
        let rand = 'rand=' + new Date().getTime();
        let ajaxtrue = '&ajax=true';

        let data = {
            action: 'RequireCustom',
            ajax: true,
            token: static_token,
            action: 'RequireCustom',
            cp_source: jQuery('#order_form input[name="sender_cp"]').val(),
            cp_dest: jQuery('#order_form input[name="customer_cp"]').val(), 
            country_source: jQuery('#order_form input[name="sender_country"]').val(),
            country_dest: jQuery('#order_form select[name="customer_country"]').val(),
        };

        jQuery.ajax({
            url: AdminOrderURL + rand + ajaxtrue,
            type: 'POST',
            data: data,
            cache: false,
            processData: true,
            success: function (data) {
                let parsed_data = JSON.parse(data);
                if (parsed_data['require_custom']  == true) {
                     /** @todo Pendiente lógica Requiere aduanas */
                    jQuery('#customs_correos_container_shipping').removeClass('hidden-block');
                    // Marcamos el pedido como "Requiere aduanas"
                    jQuery('#order_form input[name="require_customs_doc"]').val(1);
                } else {
                    jQuery('#customs_correos_container_shipping').addClass('hidden-block');
                }
            }
        });
    });

        /* FUNCIONALIDAD CITYPAQ */
        jQuery('#changeCityPaq').on('click', function (e) {
            jQuery('.change-container-citypaq').toggle();
            jQuery('#mapCityPaq').hide();
        });

        jQuery('#searchCityPaqButton').on('click', function (event) {
            jQuery('#citypaq-list').find('option').remove();

            let postcode = jQuery('#input_cp_citypaq').val();
            // Datos de las oficinas del webservice de localizador de oficinas
            let citypaqs = '';

            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let company = selected_carrier.data('company');

            let province   = postcode.substring(0, 2);
            let adresse_cp = jQuery('#customer_cp').val().substring(0, 2);

            if (province !== adresse_cp) {
                alert(wrong_province);
                return;
            }

            jQuery.ajax({
                type: 'POST',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialCheckoutModuleFrontController',
                        action: 'SearchCityPaqByPostalCode',
                        token: static_token,
                        postcode: postcode,
                        company: company,
                        sender_id: jQuery('#senderSelect').val(),
                        order_id: jQuery('#id_order_hidden').val(),
                        order_detail_page: true,
                    },
                },
                cache: false,
                processData: true,
                success: function (parsed_data) {
                    let dir_citypaq, loc_citypaq, cp_citypaq, cod_homepaq;

                    parsed_data.forEach(function (location, index, array) {

                        jQuery('#inputSelectCityPaqs').append('<option value=' + index + '>' + location.name + '</option>');

                        if (index == 0) {

                            dir_citypaq = location.address;
                            loc_citypaq = location.city;
                            cp_citypaq  = location.zipcode;
                            cod_homepaq = location.reference;

                            // Informamos los campos ocultos con el primer CityPaq cuando hacemos click con el botón Buscar (3)
                            jQuery('#reference_code').val(cod_homepaq);
                            jQuery('#request_data').val(JSON.stringify(location.data));

                            document.getElementById('dir-citypaq').innerHTML = dir_citypaq;
                            document.getElementById('loc-citypaq').innerHTML = loc_citypaq;
                            document.getElementById('cp-citypaq').innerHTML  = cp_citypaq;
                            document.getElementById('cod_homepaq').value     = cod_homepaq;

                            document.getElementById('citypaq_address').value = dir_citypaq;
                            document.getElementById('citypaq_city').value    = loc_citypaq;
                            document.getElementById('citypaq_cp').value      = cp_citypaq;

                            const myLatLng = {
                                lat: parseFloat(location.lat),
                                lng: parseFloat(location.long),
                            };

                            if (typeof google !== 'undefined') {
                                let marker = new google.maps.Marker({
                                    position: myLatLng,
                                    title: location.name,
                                });
                                marker.setMap(mapCityPaqObj);
                                mapCityPaqObj.setCenter(myLatLng);
                                mapCityPaqObj.setZoom(14);
                            }
                        }
                    });

                    // Acciones cuando cambia el selector de CityPaq
                    jQuery('#inputSelectCityPaqs').on('change', function (e) {

                        let locationSelected = parsed_data[jQuery(this).val()];

                        dir_citypaq = locationSelected.address;
                        loc_citypaq = locationSelected.city;
                        cp_citypaq  = locationSelected.zipcode;
                        cod_homepaq = locationSelected.reference;

                        // Informamos los campos ocultos cambiar el selector de CityPaqs (1)
                        jQuery('#reference_code').val(cod_homepaq);
                        jQuery('#request_data').val(JSON.stringify(locationSelected.data));

                        document.getElementById('dir-citypaq').innerHTML = dir_citypaq;
                        document.getElementById('loc-citypaq').innerHTML = loc_citypaq;
                        document.getElementById('cp-citypaq').innerHTML  = cp_citypaq;
                        document.getElementById('cod_homepaq').value     = cod_homepaq;

                        document.getElementById('citypaq_address').value = dir_citypaq;
                        document.getElementById('citypaq_city').value    = loc_citypaq;
                        document.getElementById('citypaq_cp').value      = cp_citypaq;

                        const myLatLng = {
                            lat: parseFloat(locationSelected.lat),
                            lng: parseFloat(locationSelected.long),
                        };

                        if (typeof google !== 'undefined') {
                            let marker = new google.maps.Marker({
                                position: myLatLng,
                                title: locationSelected.name,
                            });
                            marker.setMap(mapCityPaqObj);
                            mapCityPaqObj.setCenter(myLatLng);
                            mapCityPaqObj.setZoom(14);
                        }
                    });

                    jQuery('#inputSelectCityPaqs').show();
                    jQuery('#citypaq-list').show();
                    jQuery('#no_citypaqs_zip_message').addClass('hidden-block');
                    jQuery('.map-info-citypaq').show();
                    jQuery('#mapCityPaq').show();

                    jQuery('#selectCityPaqButton').on('click', function (e) {
                        let locationSelected = parsed_data[jQuery('#inputSelectCityPaqs').val()];

                        jQuery('.change-container-citypaq').hide();

                        const address = locationSelected.address || locationSelected.location || (locationSelected.data && (locationSelected.data.addressName || locationSelected.data.location)) || '';
                        const city = locationSelected.city || locationSelected.locality || (locationSelected.data && locationSelected.data.locality) || '';
                        const zipcode = locationSelected.zipcode || locationSelected.postalCode || (locationSelected.data && locationSelected.data.postalCode) || '';
                        const code = locationSelected.reference || locationSelected.terminalId || (locationSelected.data && (locationSelected.data.terminalId || locationSelected.data.terminalId)) || '';

                        document.getElementById('citypaq_address').value = address;
                        document.getElementById('citypaq_city').value = city;
                        document.getElementById('citypaq_cp').value = zipcode;
                        document.getElementById('cod_homepaq').value = code;

                        // Informamos los campos ocultos cuando hacemos click con el botón Seleccionar CityPaq (2)
                        jQuery('#reference_code').val(code);
                        jQuery('#request_data').val(JSON.stringify(locationSelected.data || locationSelected));
                    });
                },
            });
            event.preventDefault();
        });

        /* FUNCIONALIDAD PUDO CEX */
        jQuery('#changePudoCEX').on('click', function (e) {
            jQuery('.change-container-pudocex').toggle();
            jQuery('#mapPudoCEX').hide();
        });

        jQuery('#searchPudocexButton').on('click', function (event) {
            jQuery('#pudocex-list').find('option').remove();

            let postcode = jQuery('#input_cp_pudocex').val();

            // Dimensiones bulto 1
            let length = jQuery('input[name="packageLarge_1"]').val();
            let width = jQuery('input[name="packageWidth_1"]').val();
            let height = jQuery('input[name="packageHeight_1"]').val();

            // Totales
            let cart_total = jQuery('input[name="pudocex_cart_total"]').val();
            let total_weight = jQuery('input[name="pudocex_total_weight"]').val();
            let country = jQuery('input[name="pudocex_country"]').val();

            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let company = selected_carrier.data('company');

            let province   = postcode.substring(0, 2);
            let adresse_cp = jQuery('#customer_cp').val().substring(0, 2);

            if (province !== adresse_cp) {
                alert(wrong_province);
                return;
            }

            jQuery.ajax({
                type: 'POST',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialCheckoutModuleFrontController',
                        action: 'SearchPudoCEXByPostalCode',
                        token: static_token,
                        postcode: postcode,
                        length: length,
                        width: width,
                        height: height,
                        cart_total: cart_total,
                        total_weight: total_weight,
                        country: country,
                        company: company,
                        sender_id: jQuery('#senderSelect').val(),
                        order_id: jQuery('#id_order_hidden').val(),
                        order_detail_page: true
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    jQuery('.map-info-pudocex').hide();
                    jQuery('#mapPudoCEX').hide();
                    jQuery('#inputSelectPudocexs').hide();
                    jQuery('#pudocex-list').hide();
                    document.getElementById('pudocex_address').value = '';
                    document.getElementById('pudocex_city').value = '';
                    document.getElementById('pudocex_cp').value = '';
                    document.getElementById('cod_pudocex').value = '';
                    jQuery('#no_pudocexs_zip_message').removeClass('hidden-block');   
                },
                success: function (parsed_data) {

                    let dir_citypaq, loc_citypaq, cp_citypaq, cod_homepaq;

                    parsed_data.forEach(function (location, index, array) {

                        jQuery('#inputSelectPudocexs').append('<option value=' + index + '>' + location.name + '</option>');

                        if (index == 0) {

                            dir_pudocex = location.address;
                            loc_pudocex = location.city;
                            cp_pudocex  = location.zipcode;
                            cod_pudocex = location.reference;

                            // Informamos los campos ocultos con el primer CityPaq cuando hacemos click con el botón Buscar (3)
                            jQuery('#reference_code').val(cod_pudocex);
                            jQuery('#request_data').val(JSON.stringify(location.data));

                            document.getElementById('dir-pudocex').innerHTML = dir_pudocex;
                            document.getElementById('loc-pudocex').innerHTML = loc_pudocex;
                            document.getElementById('cp-pudocex').innerHTML  = cp_pudocex;
                            document.getElementById('cod_pudocex').value     = cod_pudocex;

                            document.getElementById('pudocex_address').value = dir_pudocex;
                            document.getElementById('pudocex_city').value    = loc_pudocex;
                            document.getElementById('pudocex_cp').value      = cp_pudocex;

                            const myLatLng = {
                                lat: parseFloat(location.lat),
                                lng: parseFloat(location.long),
                            };

                            if (typeof google !== 'undefined') {
                                let marker = new google.maps.Marker({
                                    position: myLatLng,
                                    title: location.name,
                                });
                                marker.setMap(mapPudoCEXObj);
                                mapPudoCEXObj.setCenter(myLatLng);
                                mapPudoCEXObj.setZoom(14);
                            }
                        }
                    });

                    // Acciones cuando cambia el selector de CityPaq
                    jQuery('#inputSelectPudocexs').on('change', function (e) {

                        let locationSelected = parsed_data[jQuery(this).val()];

                        dir_pudocex = locationSelected.address;
                        loc_pudocex = locationSelected.city;
                        cp_pudocex  = locationSelected.zipcode;
                        cod_pudocex = locationSelected.reference;

                        // Informamos los campos ocultos cambiar el selector de pudocex (1)
                        jQuery('#reference_code').val(cod_pudocex);
                        jQuery('#request_data').val(JSON.stringify(locationSelected.data));

                        document.getElementById('dir-pudocex').innerHTML = dir_pudocex;
                        document.getElementById('loc-pudocex').innerHTML = loc_pudocex;
                        document.getElementById('cp-pudocex').innerHTML  = cp_pudocex;
                        document.getElementById('cod_pudocex').value     = cod_pudocex;

                        document.getElementById('pudocex_address').value = dir_pudocex;
                        document.getElementById('pudocex_city').value    = loc_pudocex;
                        document.getElementById('pudocex_cp').value      = cp_pudocex;

                        const myLatLng = {
                            lat: parseFloat(locationSelected.lat),
                            lng: parseFloat(locationSelected.long),
                        };

                        if (typeof google !== 'undefined') {
                            let marker = new google.maps.Marker({
                                position: myLatLng,
                                title: locationSelected.name,
                            });
                            marker.setMap(mapPudoCEXObj);
                            mapPudoCEXObj.setCenter(myLatLng);
                            mapPudoCEXObj.setZoom(14);
                        }
                    });

                    jQuery('#inputSelectPudocexs').show();
                    jQuery('#pudocex-list').show();
                    jQuery('#no_pudocex_zip_message').addClass('hidden-block');
                    jQuery('.map-info-pudocex').show();
                    jQuery('#mapPudoCEX').show();


                    jQuery('#selectPudocexButton').on('click', function (e) {
                        let locationSelected = parsed_data[jQuery('#inputSelectPudocexs').val()];

                        jQuery('.change-container-pudocex').hide();
                        document.getElementById('pudocex_address').value = dir_pudocex;
                        document.getElementById('pudocex_city').value = loc_pudocex;
                        document.getElementById('pudocex_cp').value = cp_pudocex;
                        document.getElementById('cod_pudocex').value = cod_pudocex;

                        // Fill customer form fields with pickup point data (visual only, no address creation yet)
                        jQuery('#customer_company').val(locationSelected.name || 'PUDO CEX');
                        jQuery('#customer_address').val(dir_pudocex);
                        jQuery('#customer_cp').val(cp_pudocex);
                        jQuery('#customer_city').val(loc_pudocex);

                        // Informamos los campos ocultos cuando hacemos click con el botón Seleccionar pudocex (2)
                        jQuery('#reference_code').val(cod_pudocex);
                        jQuery('#request_data').val(JSON.stringify(locationSelected.data));
                    });
                },
            });
            event.preventDefault();
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                           PREREGISTRO DE ENVÍO EN PEDIDOS                            //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        /* Añadimos una nueva regla que compruebe que las dimensiones son 10x15x1 como mínimo,
        es decir, que sean mayores que 0, uno mayor que 10 y otro mayor de 15 */
        jQuery.validator.addMethod(
            'dimensionesValidadas',
            function (value, element) {
                // comprobamos que el carrier seleccionado se paq ligera o city paq, si no no validamos estos campos
                var carriers_default_dimensions = ['S0179', 'S0176', 'S0178'];
                if (!carriers_default_dimensions.includes(jQuery('#input_select_carrier').find('option:selected').val())) {
                    return true;
                }

                var container = element.closest('.container-bulto').id;
                var values = jQuery('#' + container)
                    .find('.validate-dimensions')
                    .map(function () {
                        return parseInt(jQuery(this).val());
                    })
                    .get();

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
            },
            jQuery.validator.format(valuesDimensionDefault)
        );

        // Para añdir la regla de validación dinámicamente hacemos uso de esta class "validate-dimensions"
        jQuery.validator.addClassRules('validate-dimensions', { dimensionesValidadas: true });

        // Preregistro de envío
        jQuery('#order_form').validate({
            onkeyup: function (element) {
                jQuery(element).valid();
            },

            rules: {
                // DESTINATARIO
                customer_firstname: {
                    required: function (element) {
                        return jQuery('#order_form #customer_company').val() == '';
                    },
                    maxlength: 40,
                },
                customer_lastname: {
                    required: false,
                    maxlength: 40,
                },
                customer_company: {
                    required: function (element) {
                        return !(jQuery('#order_form #customer_firstname').val() != '');
                    },
                    maxlength: 40,
                },
                customer_contact: {
                    required: false,
                    maxlength: 40,
                },
                customer_address: {
                    required: true,
                    maxlength: 300,
                },
                customer_city: {
                    required: true,
                    maxlength: 40,
                },
                customer_cp: {
                    required: false,
                    maxlength: 8,
                },
                customer_email: {
                    required: false,
                    email: true,
                    maxlength: 50,
                },
                customer_dni: {
                    required: false,
                    maxlength: 15,
                    validate_nif_cif_nie: false,
                },
                order_reference: {
                    required: false,
                    maxlength: 20,
                },
                desc_reference_1: {
                    required: false,
                    maxlength: 100,
                },
                desc_reference_2: {
                    required: false,
                    maxlength: 100,
                },
                code_at: {
                    required: false,
                    maxlength: 30,
                },
                // VALORES AÑADIDOS
                cash_on_delivery_value: {
                    required: false,
                    number: true,
                    maxlength: 6,
                },
                insurance_value: {
                    required: false,
                    number: true,
                    maxlength: 100,
                },
                bank_acc_number: {
                    required: false,
                    maxlength: 34,
                    validate_acc_iban: false,
                },
                total_weight: {
                    required: true,
                    number: true,
                },
                packageWeight_1: {
                    required: true,
                    number: true,
                },
                packageWeight_2: {
                    required: true,
                    number: true,
                },
                packageWeight_3: {
                    required: true,
                    number: true,
                },
                packageWeight_4: {
                    required: true,
                    number: true,
                },
                packageWeight_5: {
                    required: true,
                    number: true,
                },
                packageWeight_6: {
                    required: true,
                    number: true,
                },
                packageWeight_7: {
                    required: true,
                    number: true,
                },
                packageWeight_8: {
                    required: true,
                    number: true,
                },
                packageWeight_9: {
                    required: true,
                    number: true,
                },
                packageWeight_10: {
                    required: true,
                    number: true,
                },
                PickupDateRegister: {
                    required: function (element) {
                        const checkDateRegister = jQuery('#inputCheckSavePickup');
                        return checkDateRegister.checked;
                    },
                    date: true,
                },
                // Selector puntos de recoogida (Oficinas, CityPaqs o Pudos)
                office_address: {
                    required: function (element) {
                        const products = ['44'];
                        return products.includes(jQuery('#input_select_carrier').val())
                    },
                },
            },
            messages: {
                // DESTINATARIO
                customer_firstname: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 40 ' + characters,
                },
                customer_lastname: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 40 ' + characters,
                },
                customer_company: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 40 ' + characters,
                },
                customer_contact: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 40 ' + characters,
                },
                customer_address: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 300 ' + characters,
                },
                customer_city: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 40 ' + characters,
                },
                customer_cp: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 8 ' + characters,
                },
                /* customer_phone: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                    maxlength: maxLengthMessage + ' 9 ' + characters,
                },*/
                customer_email: {
                    required: requiredCustomMessage,
                    email: invalidEmail,
                    maxlength: maxLengthMessage + ' 50 ' + characters,
                },
                customer_dni: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 15 ' + characters,
                    validate_nif_cif_nie: wrongDniCif,
                },
                order_reference: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 20 ' + characters,
                },
                desc_reference_1: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 100 ' + characters,
                },
                desc_reference_2: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 100 ' + characters,
                },
                code_at: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 30 ' + characters,
                },
                // VALORES AÑADIDOS
                cash_on_delivery_value: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                    maxlength: maxLengthMessage + ' 6 ' + characters,
                },
                insurance_value: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                    maxlength: maxLengthMessage + ' 100 ' + characters,
                },
                bank_acc_number: {
                    required: requiredCustomMessage,
                    maxlength: maxLengthMessage + ' 34 ' + characters,
                    validate_acc_iban: wrongACCAndIBAN,
                },
                total_weight: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_1: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_2: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_3: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_4: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_5: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_6: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_7: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_8: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_9: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
                packageWeight_10: {
                    required: requiredCustomMessage,
                    number: invalidNumber,
                },
            },
            // Añadimos los grupos para que solo aparezca un mensaje por bloque de inputs
            groups: {
                valuesDimensionDefault1: 'packageLarge_1 packageWidth_1 packageHeight_1',
                valuesDimensionDefault2: 'packageLarge_2 packageWidth_2 packageHeight_2',
                valuesDimensionDefault3: 'packageLarge_3 packageWidth_3 packageHeight_3',
                valuesDimensionDefault4: 'packageLarge_4 packageWidth_4 packageHeight_4',
                valuesDimensionDefault5: 'packageLarge_5 packageWidth_5 packageHeight_5',
                valuesDimensionDefault6: 'packageLarge_6 packageWidth_6 packageHeight_6',
                valuesDimensionDefault7: 'packageLarge_7 packageWidth_7 packageHeight_7',
                valuesDimensionDefault8: 'packageLarge_8 packageWidth_8 packageHeight_8',
                valuesDimensionDefault9: 'packageLarge_9 packageWidth_9 packageHeight_9',
                valuesDimensionDefault10: 'packageLarge_10 packageWidth_10 packageHeight_10',
            },

        submitHandler: function () {
            jQuery('#processingOrderButtonMsg').removeClass('hidden-block');
            jQuery('#generateOrderButtonMsg').addClass('hidden-block');
            jQuery('#generateOrderButton').prop('disabled', true);

                let order_id = jQuery('#id_order_hidden').val();
                let order_form = getFormData('order_form');
                let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
                let company = selected_carrier.data('company');
                let delivery_mode = selected_carrier.data('carrier_type');
                let id_carrier = selected_carrier.data('id_carrier');
                let id_product = selected_carrier.data('id_product');
                let max_packages = selected_carrier.data('max_packages');
                let packages = jQuery('#correos-num-parcels').val();
                let id_sender = jQuery('#senderSelect').val();
                let added_values_cash_on_delivery = jQuery('#contrareembolsoCheckbox').is(':checked');
                let added_values_insurance = jQuery('#seguroCheckbox').is(':checked');
                let added_values_partial_delivery = jQuery('#partial_delivery').is(':checked');
                let added_values_delivery_saturday = jQuery('#delivery_saturday').is(':checked');
                let added_values_cash_on_delivery_iban = jQuery('#bank_acc_number').val();
                let added_values_cash_on_delivery_value = jQuery('#cash_on_delivery_value').val();
                let added_values_insurance_value = jQuery('#insurance_value').val();
                let at_code = jQuery('#code_at').val();
                let request_data = jQuery('#request_data').val();
                let reference_code = jQuery('#reference_code').val();

                if(request_data) {
                    request_data = JSON.parse(request_data);
                }
                /* Recogemos los datos de todos los bultos */
                var info_bultos = {};
                jQuery('.container-bulto-info').each(function () {
                    var reference = jQuery(this).find('input[name^="packageRef"]').val();
                    var weight = jQuery(this).find('input[name^="packageWeight"]').val();
                    var large = jQuery(this).find('input[name^="packageLarge"]').val();
                    var width = jQuery(this).find('input[name^="packageWidth"]').val();
                    var height = jQuery(this).find('input[name^="packageHeight"]').val();
                    var observations = jQuery(this).find('textarea[name^="deliveryRemarks"]').val();

                    info_bultos[jQuery(this).attr('id').split('_')[1]] = { reference: reference, weight: weight, large: large, width: width, height: height, observations, observations };
                });
                
                info_bultos = JSON.stringify(info_bultos);

                let pickupCheck = jQuery('#inputCheckSavePickup');
                let printLablPickupCheck = jQuery('#inputCheckPrintLabel');

                let needPickup = 'N';
                let PickupDateRegister = '';
                let PickupFromRegister = '';
                let PickupToRegister = '';
                let needPrintLablPickup = 'N';
                let select_input_tamanio_paquete = '';

            if (jQuery(pickupCheck).is(':checked')) {
                needPickup = 'S';
                PickupDateRegister = jQuery('#PickupDateRegister').val();
                PickupFromRegister = jQuery('#PickupFromRegister').val();
                PickupToRegister = jQuery('#PickupToRegister').val();
                select_input_tamanio_paquete = jQuery('#input_tamanio_paquete').val();
                if (company == 'Correos' && select_input_tamanio_paquete == 0) {
                    jQuery('#error_register strong').html('Error:  Debe seleccionar el tamaño del paquete');
                    jQuery('#error_register').removeClass('hidden-block');
                    jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                    jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                    jQuery('#generateOrderButton').prop('disabled', false);
                    return;
                }
                let pickupDateComplete = new Date(PickupDateRegister);
                pickupDateComplete.setHours(23);
                pickupDateComplete.setMinutes(59);
                pickupDateComplete.setSeconds(59);
                if (pickupDateComplete < new Date() || (PickupFromRegister == '00:00:00' && PickupToRegister == '00:00:00')) {
                    jQuery('#error_register strong').html('Error:  Debe seleccionar fecha y rango de horas válidos en la recogida');
                    jQuery('#error_register').removeClass('hidden-block');
                    jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                    jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                    jQuery('#generateOrderButton').prop('disabled', false);
                    return;
                }
            }

                if (jQuery(printLablPickupCheck).is(':checked')) {
                    needPrintLablPickup = 'S';
                }

                if (packages <= max_packages) {
                    let modifiedOrderForm = {};
                    order_form['AT_code'] = at_code;

                    jQuery.ajax({
                        type: 'post',
                        url: varsAjax.ajaxUrl,
                        data: {
                            action: 'correosOficialDispacher',
                            _nonce: varsAjax.nonce,
                            dispatcher: {
                                controller: 'CorreosOficialAdminOrderModuleFrontController',
                                action: 'generateOrder',
                                order_id: order_id,
                                id_carrier: id_carrier,
                                id_product: id_product,
                                id_sender: id_sender,
                                company: company,
                                delivery_mode: delivery_mode,
                                order_form: order_form,
                                needPickup: needPickup,
                                pickupDateRegister: PickupDateRegister,
                                pickupFromRegister: PickupFromRegister,
                                pickupToRegister: PickupToRegister,
                                needPrintLablPickup: needPrintLablPickup,
                                packetSize: select_input_tamanio_paquete,
                                added_values_cash_on_delivery: added_values_cash_on_delivery,
                                added_values_insurance: added_values_insurance,
                                added_values_partial_delivery: added_values_partial_delivery,
                                added_values_delivery_saturday: added_values_delivery_saturday,
                                added_values_cash_on_delivery_iban: added_values_cash_on_delivery_iban,
                                added_values_cash_on_delivery_value: added_values_cash_on_delivery_value,
                                added_values_insurance_value: added_values_insurance_value,
                                info_bultos: info_bultos,
                                request_data: request_data,
                                reference_code: reference_code
                            },
                        },
                        cache: false,
                        processData: true,
                        error: function(xhr, status, error) {
                            let errors = '<ul class="mb-0">';

                            if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                                errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
                            } else {
                                // Hacemos lista de errores
                                xhr.responseJSON.forEach((error) => {
                                    errors += '<li>' + error.mensajeRetorno + '</li>';
                                });
                            }
                            errors += '</ul>';

                            // Mostrar errores relacionados con Excepciones
                            jQuery('#generateOrderButton').prop('disabled', false);
                            jQuery('#success_register').addClass('hidden-block');
                            jQuery('#error_register strong').html(errors);
                            jQuery('#error_register').removeClass('hidden-block');
                            jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                            jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                        },
                        success: function (parsed_data) {

                            loadOpacity();

                            if (parsed_data.codigoRetorno == '0') {
                                // Deshabilitamos formularios 
                                disableForm('#container_sender');
                                disableForm('#container_customer');
                                disableForm('#container_shipping');
                                disableForm('#added_values');

                                // TRATAR que hace esto? para que ocultamos el exp_number
                                jQuery('#order_exp_number_hidden').val(parsed_data.exp_number);

                                // TRATAR para que necesitamos esta variable????
                                // Verificar si la variable company
                                if (company === 'Correos') {
                                    jQuery('#correos_provider').val('Correos');
                                } else if (company === 'CEX') {
                                    jQuery('#correos_provider').val('CEX');
                                }

                                // TRACKIN METABOX
                                let tracking_shipping_number = parsed_data.bultos[0].shipping_number;
                                let co_tracking_link = 'https://www.correos.es/es/es/herramientas/localizador/envios/detalle?tracking-number=' + tracking_shipping_number;
                                jQuery('#correos_tracking_number').val(tracking_shipping_number);
                                jQuery('#correos_tracking_link').val(co_tracking_link);
                                jQuery('#correos_tracking_date').val(coGetToday());

                                // MOSTRAR/OCULTAR Componentes
                                jQuery('#order-done-info').removeClass('hidden-block');
                                jQuery('#input_format_etiqueta_container_reimpresion').removeClass('hidden-block');
                                jQuery('.cancel-container').removeClass('hidden-block');
                                jQuery('#general-pickup-container').removeClass('hidden-block');
                                jQuery('#input_grabar_recogida_container').addClass('hidden-block');
                                jQuery('#cancelOrderButton').removeClass('hidden-block');
                                jQuery('#cancelOrderButton').prop("disabled", false);
                                jQuery('.send-container').addClass('hidden-block');

                                                 
                                // Añadir números en el contenedor
                                const col_container = document.querySelector('#shipping-numbers-col');
                                col_container.innerHTML = "";

                                const p = document.createElement('p');
                                p.textContent = parsed_data.bultos.length > 1 ? "Códigos de envío:" : "Código de envío:";
                                col_container.appendChild(p);

                                const divShippingNumbers = document.createElement('div');
                                divShippingNumbers.classList.add('shipping-numbers-container');
                                col_container.appendChild(divShippingNumbers);

                                parsed_data.bultos.forEach((bulto, index) => {
                                    const span = document.createElement('span');
                                    span.classList.add('order-done-info-text');
                                    span.innerHTML = `Bulto ${index + 1}: ${bulto.shipping_number}`;
                                    divShippingNumbers.appendChild(span);
                                    divShippingNumbers.appendChild(document.createElement('br')); // Agrega un salto de línea
                                });

                                if (company == 'Correos') {
                                    jQuery('#correos-options-pickup-container').removeClass('hidden-block');
                                } else {
                                    jQuery('#correos-options-pickup-container').addClass('hidden-block');
                                }

                                // Ocultamos posibles avisos
                                jQuery('#error_register').addClass('hidden-block');
                                jQuery('#success_register').addClass('hidden-block');

                                // Componente etiquetas
                                managePrintLabel(parsed_data.bultos.length);

                                // Componente Tabla histórico seguimiento
                                jQuery('.history-container.hidden-block').removeClass('hidden-block');
                                setDatatableHistory();

                                // Cambiamos estado del pedido
                                changeOrderStatusFromSelector(parsed_data.changeStatus);
                        
                            }
                            
                            // Tenemos solo Pre-Registro pero no recogida
                            if (!parsed_data.codigoRetorno && !parsed_data.codigoRetornoPick && !parsed_data.cod_pickup) {
                                if (company === 'Correos') {
                                    jQuery('#data-pickup-container').addClass('hidden-block');
                                    jQuery('#input_grabar_recogida_container').addClass('hidden-block');
                                    jQuery('#save-pickup-container').removeClass('hidden-block');
                                } else if (company === 'CEX') {
                                    jQuery('#masive_pickup_container').addClass('hidden-block');
                                    jQuery('#inputCheckSavePickup').addClass('hidden-block');
                                    jQuery('#save-pickup-container').addClass('hidden-block');
                                    jQuery('#data-pickup-container').addClass('hidden-block');
                                    jQuery('#input_grabar_recogida_container').addClass('hidden-block');
                                }

                            // Tenemos Pre-Registro y Recogida OK
                            }else if (!parsed_data.codigoRetorno && !parsed_data.codigoRetornoPick && parsed_data.cod_pickup) {
                                jQuery('#masive_pickup_container').addClass('hidden-block');
                                jQuery('#general-pickup-container').addClass('hidden-block');

                                // Recarga de página
                                location.reload();
                            
                            // Tenemos Pre-Registro y Recogida KO
                            }else if (!parsed_data.codigoRetorno && parsed_data.codigoRetornoPick && !parsed_data.cod_pickup) {
                                jQuery('#masive_pickup_container').addClass('hidden-block');

                                // Actualizamos valores de recogida
                                jQuery('#pickup_date').val(PickupDateRegister);
                                jQuery('#sender_from_time').val(PickupFromRegister);
                                jQuery('#sender_to_time').val(PickupToRegister);
                                if (jQuery(printLablPickupCheck).is(':checked')) {
                                    jQuery('#print_label').prop('checked', true);
                                }
                                jQuery('#package_type').val(select_input_tamanio_paquete);
                            }

                            // Mostramos errores si vienen
                            if (parsed_data.codigoRetorno == '1' || parsed_data.codigoRetornoPick == '1') {
                                jQuery('#generateOrderButton').prop('disabled', false);
                                jQuery('#success_register').addClass('hidden-block');
                                jQuery('#error_register strong').html(parsed_data.codigoRetorno == '1' ? parsed_data.mensajeRetorno : parsed_data.mensajeRetornoPick);
                                jQuery('#error_register').removeClass('hidden-block');
                                jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                                jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                            }
                        },
                    });
            } else if (id_carrier == 0) {
                jQuery('#error_register strong').html('Error:  Seleccione transportista antes de generar el envío');
                jQuery('#error_register').removeClass('hidden-block');
                jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                jQuery('#input_select_carrier').addClass('error');
                jQuery('#generateOrderButton').prop('disabled', false);
            } else {
                jQuery('#success_register').hide();
                jQuery('#error_register strong').html('Error bultos: El transportista seleccionado no permite envíos de varios bultos');
                jQuery('#error_register').removeClass('hidden-block');
                jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                jQuery('#generateOrderButton').prop('disabled', false);
            }
        },
    });

        function changeOrderStatusFromSelector(status) {
            if (status) {
                let selector = jQuery('#order_status');
                let valueToSelect = status;
                let selectionElement = selector.siblings('.select2-container').find('.select2-selection__rendered');

                selector.find('option').removeAttr('selected');
                selector.val(valueToSelect);
                selectionElement.text(selector.find('option:selected').text());
            }
        }

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                         CANCELACION DE PREREGISTRO DE ENVIO                          //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#cancelOrderButton').on('click', function (event) {

            // Disabled boton
            jQuery('#cancelOrderButton').prop("disabled", true);

            let oficinaOrCityPaq = false;
            let selectedValue = jQuery('#input_select_carrier').val();

            if (selectedValue === 'S0176' || selectedValue === 'S0178' || selectedValue === '44' || selectedValue === 'S0236' || selectedValue === 'S0133') {
                oficinaOrCityPaq = true;
            }

            jQuery('#processingCancelOrderButtonMsg').removeClass('hidden-block');
            jQuery('#cancelOrderButtonMsg').addClass('hidden-block');

            // Eliminar tracking_number al cancelar un envío en Prestashop
            removeTrackingNumberInfo();

            let pickup_number = jQuery('#pickup_code_hidden').val();
            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let company = selected_carrier.data('company');

            if (company == 'CEX' || company == 'Correos' && (pickup_number == '' || !pickup_number)) {
                cancelOrder();
            } else {
                jQuery('#success_register').addClass('hidden-block');
                jQuery('#error_register strong').html('El envío tiene una recogida grabada. Para cancelar el envío, es necesario cancelar la recogida');
                jQuery('#error_register').removeClass('hidden-block');
                jQuery('#processingCancelOrderButtonMsg').addClass('hidden-block');
                jQuery('#cancelOrderButtonMsg').removeClass('hidden-block');
                jQuery('#cancelOrderButton').prop("disabled", false);
            }

            event.preventDefault();
        });

        function cancelOrder() {
            let order_id = jQuery('#id_order_hidden').val();
            let lang = jQuery('#customer_country').val();
            let expedition_number = jQuery('#order_exp_number_hidden').val();
            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let id_carrier = selected_carrier.data('id_carrier');
            let company = selected_carrier.data('company');
            var id_sender = jQuery('#senderSelect').val();

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'cancelOrder',
                        order_id: order_id,
                        id_carrier: id_carrier,
                        company: company,
                        lang: lang,
                        expedition_number: expedition_number,
                        sender_id: id_sender,
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                        errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#error_register strong').html(errors);
                    jQuery('#error_register').removeClass('hidden-block');
                    jQuery('#processingCancelOrderButtonMsg').addClass('hidden-block');
                    jQuery('#cancelOrderButtonMsg').removeClass('hidden-block');
                    jQuery('#cancelOrderButton').prop("disabled", false);
                },
                success: function (data) {
                    loadOpacity();
                    if (data.codigoRetorno == '0') {

                        jQuery('#generateOrderButton').prop('disabled', false);
                        jQuery('#myModal').modal('hide');
                        enableForm('#container_customer');
                        enableForm('#container_shipping');
                        enableForm('#added_values');

                        jQuery('#senderSelect').attr('disabled', false);
                        jQuery('#client_code').attr('disabled', true);

                        jQuery('#order-done-info').addClass('hidden-block');

                        jQuery('.cancel-container').addClass('hidden-block');
                        jQuery('.send-container').removeClass('hidden-block');

                        jQuery('#save-pickup-container').addClass('hidden-block');
                        jQuery('#data-pickup-container').hide();

                        if(data.mensajeRetorno) {
                            jQuery('#success_register strong').html(data.mensajeRetorno);
                            jQuery('#success_register').removeClass('hidden-block');
                            jQuery('#error_register').addClass('hidden-block');
                        }

                        jQuery('#input_grabar_recogida_container').removeClass('hidden-block');                        
                        jQuery('#inputCheckSavePickup').removeClass('hidden-block');
                        jQuery('#inputCheckSavePickup').prop('checked', false);

                        if(company == 'CEX') {
                            jQuery('#inputCheckSavePickup').prop('checked', true);
                            jQuery('#masive_pickup_container').removeClass('hidden-block');
                        }

                        /* Limpieza de metabox: proveedor, tracking_number, tracking_link, tracking_date */
                        jQuery('#correos_tracking_number').val('');
                        jQuery('#correos_tracking_link').val('');
                        jQuery('#correos_tracking_date').val('');
                        jQuery('#correos_provider').val('');

                        cleanStatusDatatable();
                        changeOrderStatusFromSelector(data.changeStatus);

                        if (data.isPickupPoint) {
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        }
                    } else if (data.status_code == 401) {
                        jQuery('#success_register').addClass('hidden-block');
                        jQuery('#error_register strong').html(data.mensajeRetorno);
                        jQuery('#error_register').removeClass('hidden-block');
                    } else {
                        jQuery('#success_register').addClass('hidden-block');
                        jQuery('#error_register strong').html(data.mensajeRetorno);
                        jQuery('#error_register').removeClass('hidden-block');
                    }
                    jQuery('#processingOrderButtonMsg').addClass('hidden-block');
                    jQuery('#generateOrderButtonMsg').removeClass('hidden-block');
                    jQuery('#processingCancelOrderButtonMsg').addClass('hidden-block');
                    jQuery('#cancelOrderButtonMsg').removeClass('hidden-block');
                },
            });
        }

        jQuery('body').on('click', '#myModalCancelButton', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            jQuery('#myModal').modal('hide');
            jQuery('#processingCancelOrderButtonMsg').addClass('hidden-block');
            jQuery('#cancelOrderButtonMsg').removeClass('hidden-block');
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                                   GENERAR RECOGIDA                                   //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#generate_pickup').on('click', function (event) {
            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let company = selected_carrier.data('company');
            let id_carrier = selected_carrier.data('id_carrier');
            let print_label = 0;
            let id_product = selected_carrier.data('id_product');

            // Si es correo package_type requerido
            if (!jQuery('#package_type').val() && company == 'Correos') {
                jQuery('#package_type').addClass('error');
                return;
            }

            jQuery('#processingPickupButtonMsg').removeClass('hidden-block');
            jQuery('#pickupButtonMsg').addClass('hidden-block');
            jQuery('#generate_pickup').attr('disabled', true);

            if (jQuery('#print_label').is(':checked')) {
                print_label = 1;
            }

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'generatePickup',
                        mode_pickup: 'pickup',
                        order_id: jQuery('#id_order_hidden').val(),
                        bultos: jQuery('#correos-num-parcels').val(),
                        expedition_number: jQuery('#order_exp_number_hidden').val(),
                        order_reference: jQuery('#order_reference').val(),
                        pickup_date: jQuery('#pickup_date').val(),
                        sender_from_time: jQuery('#sender_from_time').val(),
                        sender_to_time: jQuery('#sender_to_time').val(),
                        sender_address: jQuery('#sender_address').val(),
                        sender_city: jQuery('#sender_city').val(),
                        sender_cp: jQuery('#sender_cp').val(),
                        sender_name: jQuery('#sender_name').val(),
                        sender_contact: jQuery('#sender_contact').val(),
                        sender_phone: jQuery('#sender_phone').val(),
                        sender_email: jQuery('#sender_email').val(),
                        sender_nif_cif: jQuery('#sender_nif_cif').val(),
                        sender_country: jQuery('#sender_country').val(),
                        producto: selected_carrier.val(),
                        package_type: jQuery('#package_type').val(),
                        print_label: print_label,
                        company: company,
                        id_carrier: id_carrier,
                        id_sender: jQuery('#senderSelect').val(),
                        id_product: id_product
                    },
                },
                error: function(xhr, status, error) {

                    // Hacemos lista de errores
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                        errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#error_register strong').html(errors);
                    jQuery('#error_register').removeClass('hidden-block');
                    jQuery('#processingPickupButtonMsg').addClass('hidden-block');
                    jQuery('#pickupButtonMsg').removeClass('hidden-block');
                    jQuery('#generate_pickup').attr('disabled', false);
                },
                success: function (data) {

                    loadOpacity();
                    
                    if (!data.codigoRetorno || data.codigoRetorno != 1) {
                        jQuery('#pickup_code_hidden').val(data.codSolicitud);
                        location.reload();
                        return;
                    } else {
                        jQuery('#error_register strong').html(data.mensajeRetorno);
                        jQuery('#error_register').removeClass('hidden-block');
                        jQuery('#success_register').addClass('hidden-block');
                    }
                    jQuery('#processingPickupButtonMsg').addClass('hidden-block');
                    jQuery('#pickupButtonMsg').removeClass('hidden-block');
                    jQuery('#generate_pickup').attr('disabled', false);
                },
            });
            event.preventDefault();
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                                  CANCELAR RECOGIDA                                   //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#cancel_pickup').on('click', function (event) {
            jQuery('#processingCancelPickupButtonMsg').removeClass('hidden-block');
            jQuery('#pickupCancelButtonMsg').addClass('hidden-block');

            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            let company = selected_carrier.data('company');
            let sender_id = jQuery('#senderSelect').val();
                        
            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'cancelPickup',
                        order_id: jQuery('#id_order_hidden').val(),
                        sender_id: sender_id,
                        pickup_number: jQuery('#pickup_code_hidden').val(),
                        company: company,
                        mode_pickup: 'order'
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                        errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#success_register').addClass('hidden-block');
                    jQuery('#error_register strong').html(errors);
                    jQuery('#error_register').removeClass('hidden-block');
                    jQuery('#processingCancelPickupButtonMsg').addClass('hidden-block');
                    jQuery('#pickupCancelButtonMsg').removeClass('hidden-block');
                },
                success: function (parsed_data) {
                    loadOpacity();
                    if (parsed_data.codigoRetorno == '0') {
                        jQuery('#success_register strong').html(parsed_data.mensajeRetorno);
                        jQuery('#success_register').removeClass('hidden-block');
                        jQuery('#error_register').addClass('hidden-block');

                        jQuery('#pickup_code_hidden').val('');

                        jQuery('#save-pickup-container').removeClass('hidden-block');
                        jQuery('#data-pickup-container').hide();
                    } else {
                        jQuery('#error_register strong').html(parsed_data.mensajeRetorno);
                        jQuery('#error_register').removeClass('hidden-block');
                        jQuery('#success_register').addClass('hidden-block');
                    }
                    jQuery('#processingCancelPickupButtonMsg').addClass('hidden-block');
                    jQuery('#pickupCancelButtonMsg').removeClass('hidden-block');
                },
            });
            event.preventDefault();
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                       IMPRIMIR ETIQUETA DE ENVÍO PREREGISTRADO                       //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#ReimprimirEtiquetasButton').on('click', function (event) {


            let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
            
            let id_order = jQuery('#id_order_hidden').val();
            let company = selected_carrier.data('company');
            let product_id = selected_carrier.data('id_product');
            let exp_number = jQuery('#order_exp_number_hidden').val();
            let sender_id = jQuery('#senderSelect').val();
            let selectedTipoEtiquetaReimpresion = jQuery('#input_tipo_etiqueta_reimpresion').val();
            let selectedFormatEtiquetaReimpresion = jQuery('#input_format_etiqueta_reimpresion').val();
            let selectedPosicionEtiquetaReimpresion = jQuery('#input_pos_etiqueta_reimpresion').val();

            jQuery('#processingPrintLabelButtonMsg').removeClass('hidden-block');
            jQuery('#PrintLabelMessageButton').addClass('hidden-block');

            if(company == 'Correos' && selectedFormatEtiquetaReimpresion == '1' ) {
                jQuery('#processingPrintLabelButtonMsg').addClass('hidden-block');
                jQuery('#PrintLabelMessageButton').removeClass('hidden-block');
                showWrongLabelFormat();
                return;
            }
            
            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'printLabel',
                        order_id: id_order,
                        company: company,
                        exp_number: exp_number,
                        product_id: product_id,
                        sender_id: sender_id,
                        selectedTipoEtiquetaReimpresion: selectedTipoEtiquetaReimpresion,
                        selectedFormatEtiquetaReimpresion: selectedFormatEtiquetaReimpresion,
                        selectedPosicionEtiquetaReimpresion: selectedPosicionEtiquetaReimpresion,
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                        errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#error_register strong').html(errors);
                    jQuery('#error_register').removeClass('hidden-block');
                    jQuery('#processingPrintLabelButtonMsg').addClass('hidden-block');
                    jQuery('#PrintLabelMessageButton').removeClass('hidden-block');
                },
                success: function (parsed_data) {
                    if (parsed_data.status_code == '404') {
                        jQuery('#error_register').removeClass('hidden-block');
                        jQuery('#error_register strong').html(parsed_data.mensajeRetorno);
                    } else {
                        printGeneratedLabels(parsed_data.filePath, varsAjax.path_to_module);
                    }
                    jQuery('#processingPrintLabelButtonMsg').addClass('hidden-block');
                    jQuery('#PrintLabelMessageButton').removeClass('hidden-block');
                },
            });
            event.preventDefault();
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                           IMPRIMIR DOCS ADUANA PREREGISTRO                           //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#ImprimirCN23Button').on('click', function (event) {
            handleButtonClick('CN23', 'order');
        });

        jQuery('#ImprimirCN23ButtonReturn').on('click', function (event) {
            handleButtonClick('CN23', 'return');
        });
        
        jQuery('#ImprimirDUAButton').on('click', function (event) {
            handleButtonClick('DUA', 'order');
        });
        
        jQuery('#ImprimirDDPButton').on('click', function (event) {
            handleButtonClick('DDP', 'order');
        });
        
        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                                 DEVOLUCION DE ENVIO                                  //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#generateReturnButton').on('click', function (event) {
            let selected_carrier = jQuery('#input_select_carrier_return').find('option:selected');
            let company = selected_carrier.data('company');

            if (company == 'Correos') {
                if (jQuery('#packageWeightReturn_1').val() == '' || jQuery('#packageAmountReturn_1').val() == '') {
                    jQuery('#packageWeightReturn_1').addClass('error');
                    jQuery('#packageAmountReturn_1').addClass('error');
                } else {
                    generateReturn();
                    jQuery('#ImprimirCN23Button2').removeClass('hidden-block');
                }
            } else {
                if (!jQuery('#packageWeightReturn_1').val()) {
                    jQuery('#packageWeightReturn_1').addClass('error');
                    jQuery('#packageAmountReturn_1').addClass('error');
                } else {
                    generateReturn();
                    jQuery('#ImprimirCN23Button2').addClass('hidden-block');
                }
            }
        });

        function generateReturn(event) {
            jQuery('#processingReturnButtonMsg').removeClass('hidden-block');
            jQuery('#generateReturnButtonMsg').addClass('hidden-block');

            let order_id = jQuery('#id_order_hidden').val();
            let order_reference = jQuery('#order_reference').val();
            let order_form = getFormData('order_form');
            let expedition_number = '';
            let parsed_data = '';
            let id_sender = jQuery('#senderSelect').val();
            let selected_carrier = jQuery('#input_select_carrier_return').find('option:selected');
            let id_product = jQuery('#input_select_carrier_return').val();
            let company = selected_carrier.attr('data-company')?.trim();
            let needPickup = '';

            /* Recogemos los datos de todos los bultos */
            let info_bulto = {};
            let i = 1;
            jQuery('.container-bultos-return').each(function () {
                var reference = jQuery('.container-bulto-info').find('input[name^="packageRef"]').val();
                var weight = jQuery(this).find('input[name^="packageWeight"]').val();
                var large = jQuery(this).find('input[name^="packageLarge"]').val();
                var width = jQuery(this).find('input[name^="packageWidth"]').val();
                var height = jQuery(this).find('input[name^="packageHeight"]').val();
                var observations = '';

                info_bulto[i] = { reference: reference, weight: weight, large: large, width: width, height: height, observations, observations };
                i++;
            });
            
            info_bulto = JSON.stringify(info_bulto);

            if (company == 'CEX') {
                needPickup = 'S'
            } else {
                needPickup = 'N';
            }

            let modifiedOrderForm = {};
        jQuery('#generateReturnButton').prop('disabled', true);

            for (const key in order_form) {
                if (order_form.hasOwnProperty(key)) {
                    const value = order_form[key];
                    let matches = RegExp(/^customs_desc\[(\d+)\]\[(\d+)\]$/).exec(key);
                    if (matches) {
                        let descNumber1 = matches[1];
                        let descNumber2 = matches[2];
                        if (!modifiedOrderForm.customs_desc) {
                            modifiedOrderForm.customs_desc = {};
                        }
                        if (!modifiedOrderForm.customs_desc[descNumber1]) {
                            modifiedOrderForm.customs_desc[descNumber1] = {};
                        }
                        modifiedOrderForm.customs_desc[descNumber1][descNumber2] = value;
                    } else {
                        modifiedOrderForm[key] = value;
                    }
                }
            }

            if (modifiedOrderForm.customs_desc) {
                modifiedOrderForm = {
                    ...modifiedOrderForm,
                    ...modifiedOrderForm.customs_desc,
                };
                delete modifiedOrderForm.customs_desc;
            }

            order_form = modifiedOrderForm;

            order_form['PickupDateRegister'] = jQuery('#return_pickup_date').val();
            order_form['PickupFromRegister'] = jQuery('#return_sender_from_time').val();
            order_form['PickupToRegister'] = jQuery('#return_sender_to_time').val();

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'generateReturn',
                        order_id: order_id,
                        order_reference: order_reference,
                        id_product: id_product,
                        company: company,
                        delivery_mode: 'return',
                        needPickup: needPickup,
                        order_form: order_form,
                        id_sender: id_sender,
                        info_bulto: info_bulto
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                        errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#success_register_return').addClass('hidden-block');
                    jQuery('#error_register_return strong').html(errors);
                    jQuery('#error_register_return').removeClass('hidden-block');
                    jQuery('#generateReturnButton').prop('disabled', false);
                    jQuery('#processingReturnButtonMsg').addClass('hidden-block');
                    jQuery('#generateReturnButtonMsg').removeClass('hidden-block');
                },
                success: function (parsed_data) {
                    loadOpacity();

                    jQuery('#error_register_return').addClass('hidden-block');
                    jQuery('#generate-return-container').addClass('hidden-block');
                    jQuery('#general-return-pickup-container').removeClass('hidden-block');
                    jQuery('#cancel-return-container').removeClass('hidden-block');
                    jQuery('.container-bultos-return').addClass('hidden-block');
                    jQuery('#return-status').text('Prerregistrado');
                    jQuery('#generateReturnButton').addClass('hidden-block');
                    jQuery('#cancelReturnButton').removeClass('hidden-block');
                    jQuery('#save-return-pickup-container').removeClass('hidden-block');
                    changeOrderStatusFromSelector(parsed_data['changeStatus']);

                    if (parsed_data['codigoRetorno'] == 0) {
                        let return_codes = '';
                        parsed_data['bultos'].forEach(function (item, index) {
                            let num_bulto = index + 1;
                            return_codes = return_codes + '<span class="return-done-info-text">' + 'Bulto ' + num_bulto + ': ' + item.shipping_number + '<span><br>';
                            expedition_number = item.exp_number;
                        });

                        jQuery('.shipping-numbers-container-return').html(return_codes);
                        jQuery('#return-done-info').removeClass('hidden-block');
                        jQuery('#select_label_return_options').removeClass('hidden-block');
                        jQuery('#success_register_return').addClass('hidden-block');
                        jQuery('#return_exp_number_hidden').val(parsed_data['expedition_number']);
                        jQuery('#pickup_return_code_hidden').val(parsed_data['cod_pickup']);

                    if (company == 'CEX') {
                        location.reload();
                    }
                } else {
                    jQuery('#success_register_return').addClass('hidden-block');
                    jQuery('#generate-return-container').removeClass('hidden-block');
                    jQuery('#cancel-return-container').addClass('hidden-block');
                    jQuery('.container-bultos-return').removeClass('hidden-block');
                    jQuery('#error_register_return').removeClass('hidden-block');
                    jQuery('#cancelReturnButton').addClass('hidden-block');
                    jQuery('#error_register_return strong').html(parsed_data.mensajeRetorno);
                    jQuery('#generateReturnButtonMsg').prop('disabled', false);
                }

                jQuery('#processingReturnButtonMsg').addClass('hidden-block');
                jQuery('#generateReturnButtonMsg').removeClass('hidden-block');
                jQuery('#generateReturnButton').prop('disabled', false);
            }
        });
    }

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                        IMPRIMIR ETIQUETAS   //   DEVOLUCIONES                        //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#ReimprimirEtiquetasDevolucionButton').on('click', function (event) {
            let order_id = jQuery('#id_order_hidden').val();
            let selected_carrier = jQuery('#input_select_carrier_return').find('option:selected');
            let company = selected_carrier.data('company');
            let selectedTipoEtiquetaReimpresionReturn = jQuery('#input_tipo_etiqueta_reimpresion_return').val();
            let selectedPosicionEtiquetaReimpresionReturn = jQuery('#input_pos_etiqueta_reimpresion_return').val();
            let sender_id = jQuery('#senderSelect').val();

            jQuery('#ProcessingMsgEtiquetasDevolucionButton').addClass('hidden-block');
            jQuery('#ProcessingReimprimirEtiquetasDevolucionButton').removeClass('hidden-block');

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'printLabelReturn',
                        order_id: order_id,
                        sender_id: sender_id,
                        selectedTipoEtiquetaReimpresion: selectedTipoEtiquetaReimpresionReturn,
                        selectedPosicionEtiquetaReimpresion: selectedPosicionEtiquetaReimpresionReturn,
                        company: company,
                        delivery_mode: 'return',
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500) {
                        errors += '<li>' + xhr.responseJSON[0].mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#success_register_return').addClass('hidden-block');
                    jQuery('#error_register_return strong').html(errors);
                    jQuery('#error_register_return').removeClass('hidden-block');
                    jQuery('#ProcessingMsgEtiquetasDevolucionButton').removeClass('hidden-block');
                    jQuery('#ProcessingReimprimirEtiquetasDevolucionButton').addClass('hidden-block');
                },
                success: function (parsed_data) {
                    if (parsed_data.status_code == '404') {
                        jQuery('#error_register_return').removeClass('hidden-block');
                        jQuery('#error_register_return strong').html(parsed_data.mensajeRetorno);
                    } else {
                        printGeneratedLabels(parsed_data.filePath, varsAjax.path_to_module);
                    }
                    jQuery('#ProcessingMsgEtiquetasDevolucionButton').removeClass('hidden-block');
                    jQuery('#ProcessingReimprimirEtiquetasDevolucionButton').addClass('hidden-block');
                },
            });

            event.preventDefault();
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                  ENVIAR DOCUMENTACION POR CORREO  //  DEVOLUCIONES                   //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        jQuery('#SendDocumentationByEmail').on('click', function (event) {
            let selected_carrier = jQuery('#input_select_carrier_return').find('option:selected');
            let company = selected_carrier.data('company');
            let sender_id = jQuery('#senderSelect').val();
            let selectedTipoEtiquetaReimpresionReturn = jQuery('#input_tipo_etiqueta_reimpresion_return').val();
            let order_form = getFormData('order_form');

            jQuery('#ProcessingSendDocumentationByEmailButton').removeClass('hidden-block');
            jQuery('#ProcessingMsgSendDocumentationByEmailButton').addClass('hidden-block');

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'sendEmail',
                        order_id: jQuery('#id_order_hidden').val(),
                        sender_id: sender_id,
                        pickup_date: jQuery('#pickup_date').val(),
                        sender_from_time: jQuery('#sender_from_time').val(),
                        sender_address: jQuery('#sender_address').val(),
                        sender_city: jQuery('#sender_city').val(),
                        company: company,
                        customer_email: jQuery('#customer_email').val(),
                        customer_cp: jQuery('#customer_cp').val(),
                        send_email: true,
                        selectedTipoEtiquetaReimpresionReturn: selectedTipoEtiquetaReimpresionReturn,
                        customer_country: jQuery('#customer_country').val(),
                        return_code_1: jQuery('#hidden_return_code_1').val(),
                        return_code_2: jQuery('#hidden_return_code_2').val(),
                        return_code_3: jQuery('#hidden_return_code_3').val(),
                        return_code_4: jQuery('#hidden_return_code_4').val(),
                        return_code_5: jQuery('#hidden_return_code_5').val(),
                        return_code_6: jQuery('#hidden_return_code_6').val(),
                        return_code_7: jQuery('#hidden_return_code_7').val(),
                        return_code_8: jQuery('#hidden_return_code_8').val(),
                        return_code_9: jQuery('#hidden_return_code_9').val(),
                        return_code_10: jQuery('#hidden_return_code_10').val(),
                        delivery_mode: 'return',
                        order_form: order_form
                    },
                },
                cache: false,
                processData: true,
                error: function(xhr, status, error) {
                    let errors = '<ul class="mb-0">';

                    if (xhr.status == 500) {
                        errors += '<li>' + xhr.responseJSON[0].mensajeRetorno + '</li>';
                    } else {
                        // Hacemos lista de errores
                        xhr.responseJSON.forEach((error) => {
                            errors += '<li>' + error.mensajeRetorno + '</li>';
                        });
                    }
                    errors += '</ul>';

                    jQuery('#error_register_return strong').html(errors);
                    jQuery('#success_register_return_email').addClass('hidden-block');
                    jQuery('#error_register_return_email').removeClass('hidden-block');
                },
                success: function (parsed_data) {
                    loadOpacity();
                    jQuery('#ProcessingSendDocumentationByEmailButton').addClass('hidden-block');
                    jQuery('#ProcessingMsgSendDocumentationByEmailButton').removeClass('hidden-block');

                    if (parsed_data.codigoRetorno == '0') {
                        jQuery('#success_register_return_email strong').html(parsed_data.mensajeRetorno);
                        jQuery('#success_register_return_email').removeClass('hidden-block');
                        jQuery('#error_register_return_email').addClass('hidden-block');
                        return parsed_data;
                    } else {
                        jQuery('#error_register_return_email strong').html('Error 22020: ' + parsed_data.mensajeRetorno);
                        jQuery('#success_register_return_email').addClass('hidden-block');
                        jQuery('#error_register_return_email').removeClass('hidden-block');
                    }
                },
            });
        });

        //--------------------------------------------------------------------------------------//
        //                                                                                      //
        //                         CANCELAR RECOGIDA  //  DEVOLUCIONES                          //
        //                                                                                      //
        //--------------------------------------------------------------------------------------//

        // CANCELAR DEVOLUCION
        jQuery('#cancelReturnButton').on('click', function (event) {
            jQuery('#processingCancelReturnButtonMsg').removeClass('hidden-block');
            jQuery('#cancelReturnButtonMsg').addClass('hidden-block');

            let pickup_number_return = jQuery('#pickup_return_code_hidden').val();
            let selected_carrier = jQuery('#input_select_carrier_return').find('option:selected');
            let company = selected_carrier.data('company');

            if (company == 'CEX' || company == 'Correos' && (pickup_number_return == '' || !pickup_number_return)) {
                let order_id = jQuery('#id_order_hidden').val();
                let lang = jQuery('#customer_country').val();
                let require_customs_doc = jQuery('#require_customs_doc').val();
                let sender_id = jQuery('#senderSelect').val();

                if (company == 'Correos' && require_customs_doc == 1) {
                    jQuery('.customs-correos-container-return').removeClass('hidden-block');
                } else {
                    jQuery('.customs-correos-container-return').addClass('hidden-block');
                }
                jQuery.ajax({
                    type: 'post',
                    url: varsAjax.ajaxUrl,
                    data: {
                        action: 'correosOficialDispacher',
                        _nonce: varsAjax.nonce,
                        dispatcher: {
                            controller: 'CorreosOficialAdminOrderModuleFrontController',
                            action: 'cancelReturn',
                            order_id: order_id,
                            sender_id: sender_id,
                            company: company,
                            lang: lang,
                            expedition_number: '',
                            pickup_number_return: pickup_number_return,
                        },
                    },
                    cache: false,
                    processData: true,
                    error: function(xhr, status, error) {
                        let errors = '<ul class="mb-0">';

                        if (xhr.status == 500) {
                            errors += '<li>' + xhr.responseJSON[0].mensajeRetorno + '</li>';
                        } else {
                            // Hacemos lista de errores
                            xhr.responseJSON.forEach((error) => {
                                errors += '<li>' + error.mensajeRetorno + '</li>';
                            });
                        }
                        errors += '</ul>';

                        jQuery('#success_register_return').addClass('hidden-block');
                        jQuery('#error_register_return strong').html(errors);
                        jQuery('#error_register_return').removeClass('hidden-block');
                        jQuery('#processingCancelReturnButtonMsg').addClass('hidden-block');
                        jQuery('#cancelReturnButtonMsg').removeClass('hidden-block');
                    },
                    success: function (parsed_data) {
                        loadOpacity();
                        if (parsed_data.codigoRetorno == '0') {
                            jQuery('#success_register_return strong').html(parsed_data.mensajeRetorno);
                            jQuery('#success_register_return').removeClass('hidden-block');
                            jQuery('#error_register_return').addClass('hidden-block');

                        jQuery('#generate-return-container').removeClass('hidden-block');
                        jQuery('#cancel-return-container').addClass('hidden-block');
                        jQuery('.container-bultos-return').removeClass('hidden-block');
                        jQuery('#return-done-info').addClass('hidden-block');
                        jQuery('#save-return-pickup-container').addClass('hidden-block');

                        if (!require_customs_doc) {
                            jQuery('#customs_correos_container_return').addClass('hidden-block');
                        }

                        if (company !== 'CEX') {
                            jQuery('#save-return-pickup-container').addClass('hidden-block');
                        } else if(company == 'CEX') {
                            jQuery('#save-return-pickup-container').removeClass('hidden-block');
                        }
                        
                    }
                    jQuery('#processingCancelReturnButtonMsg').addClass('hidden-block');
                    jQuery('#cancelReturnButtonMsg').removeClass('hidden-block');
                    jQuery('#generateReturnButton').removeClass('hidden-block');
                    jQuery('#cancelReturnButton').addClass('hidden-block');
                },
            });
        } else {
            jQuery('#success_register_return').addClass('hidden-block');
            jQuery('#error_register_return strong').html('La devolución tiene una recogida grabada. Para cancelar la devolución, es necesario cancelar la recogida');
            jQuery('#error_register_return').removeClass('hidden-block');
            jQuery('#processingCancelReturnButtonMsg').addClass('hidden-block');
            jQuery('#cancelReturnButtonMsg').removeClass('hidden-block');
        }

            event.preventDefault();
        });

        // CANCELAR RECOGIDA
        jQuery('#cancel_return_pickup').on('click', function (event) {
            jQuery('#processingCancelReturnPickupButtonMsg').removeClass('hidden-block');
            jQuery('#returnPickupCancelButtonMsg').addClass('hidden-block');

            let selected_carrier_return = jQuery('#input_select_carrier_return').find('option:selected');
            let company = selected_carrier_return.data('company');
            let id_carrier = 0;
            let sender_id = jQuery('#senderSelect').val();
            let pickup_number_return = jQuery('.pickup-codSolicitud').text();

            jQuery.ajax({
                type: 'post',
                url: varsAjax.ajaxUrl,
                data: {
                    action: 'correosOficialDispacher',
                    _nonce: varsAjax.nonce,
                    dispatcher: {
                        controller: 'CorreosOficialAdminOrderModuleFrontController',
                        action: 'cancelPickup',
                        mode_pickup: 'return',
                        order_id: jQuery('#id_order_hidden').val(),
                        codSolicitud: jQuery('#pickup_return_code_hidden').val(),
                        company: company,
                        id_carrier: id_carrier,
                        sender_id: sender_id,
                        pickup_number_return: pickup_number_return
                    },
                },
                cache: false,
                processData: true,
                success: function (parsed_data) {
                    loadOpacity();
                    if (parsed_data.codigoRetorno == '0') {
                        jQuery('#success_register_return strong').html(parsed_data.mensajeRetorno);
                        jQuery('#success_register_return').removeClass('hidden-block');
                        jQuery('#error_register_return').addClass('hidden-block');

                        jQuery('#pickup_return_code_hidden').val('');

                        jQuery('#save-return-pickup-container').removeClass('hidden-block');
                        jQuery('#data-return-pickup-container').addClass('hidden-block');
                    } else {
                        jQuery('#error_register_return strong').html(parsed_data.mensajeRetorno);
                        jQuery('#error_register_return').removeClass('hidden-block');
                        jQuery('#success_register_return').addClass('hidden-block');
                    }
                    jQuery('#processingCancelReturnPickupButtonMsg').addClass('hidden-block');
                    jQuery('#returnPickupCancelButtonMsg').removeClass('hidden-block');
                },
            });

            event.preventDefault();
        });
    }
});

//--------------------------------------------------------------------------------------//
//                                                                                      //
//                                      AUXILIARES                                      //
//                                                                                      //
//--------------------------------------------------------------------------------------//

/**
* function para imprimir etiquetas
* @param {string} data nombre del archivo PDF
* @param {string} co_path_to_module ruta http del archivo PDF
*/
function printGeneratedLabels(data, co_path_to_module) {
    /**
     * @TODO Instanciar ruta local ya que la de woocommerce no la esta detectando.
     */
    let alternativeRoute = woocommerceVars.pluginsUrl + '/correosoficial';

    if (isHttps()) {
        alternativeRoute = alternativeRoute.replace('http://', 'https://');
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
            let fileHref = alternativeRoute + '/pdftmp/' + filename;

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

function isHttps(){
    return (document.location.protocol == 'https:');
}


/**
 * Ajusta la opacidad de los elementos 'error_register' y 'success_register'
 * a 1 para mantener su visibilidad, ignorando estilos externos.
 */
function loadOpacity() {
    jQuery('#error_register').css('opacity', '1');
    jQuery('#success_register').css('opacity', '1');
}

/**
 * Detecta que el dato que le pasamos sea un Json válido
 */
function isValidJson(data) {
    try {
        JSON.parse(data);
        return true;
    } catch (e) {
        return false;
    }
}

function handleButtonClick(buttonType, type) {
    
    let button = jQuery(`#Imprimir${buttonType}Button`);
    let sender_id = jQuery('#senderSelect').val();
    let order_id = jQuery('#id_order_hidden').val();
    let selected_carrier = jQuery('#input_select_carrier').find('option:selected');
    let company = selected_carrier.data('company');

    if (type == 'return') {
        button = jQuery(`#Imprimir${buttonType}ButtonReturn`);
    }

    button.find('.spin').removeClass('hidden-block');
    button.find('.label-message').addClass('hidden-block');

    jQuery.ajax({
        type: 'POST',
        url: varsAjax.ajaxUrl,
        data: {
            action: 'correosOficialDispacher',
            _nonce: varsAjax.nonce,
            dispatcher: {
                controller: 'CorreosOficialAdminOrderModuleFrontController',
                action: 'getCustomsDoc',
                order_id: order_id,
                sender_id: sender_id,
                company: company,
                exp_number: jQuery('#order_exp_number_hidden').val(),
                postal_code: jQuery('#order_form input[name="customer_cp"]').val(),
                customer_country: jQuery("#customer_country option:selected").text(),
                customer_iso: jQuery('#customer_country').val(),
                adressed_name: jQuery('#customer_firstname').val() + ' ' + jQuery('#customer_lastname').val(),
                customer_company: jQuery('#customer_company').val(),
                optionButton: `Imprimir${buttonType}Button`,
                token: static_token,
                type: type
            },
        },
        cache: false,
        error: function(xhr, status, error) {

            // Hacemos lista de errores
            let errors = '<ul class="mb-0">';

            if (xhr.status == 500 && xhr.responseJSON.codigoRetorno == 500 || xhr.responseJSON.codigoRetorno == 1) {
                errors += '<li>' + xhr.responseJSON.mensajeRetorno + '</li>';
            } else {
                // Hacemos lista de errores
                xhr.responseJSON.forEach((error) => {
                    errors += '<li>' + error.mensajeRetorno + '</li>';
                });
            }
            errors += '</ul>';

            jQuery('#error_register strong').html(errors);
            jQuery('#error_register').removeClass('hidden-block');
            jQuery('#processingPrintLabelButtonMsg').addClass('hidden-block');
            jQuery('#PrintLabelMessageButton').removeClass('hidden-block');
        },
        success: function (parsed_data) {
            if (parsed_data.status_code == '404') {
                jQuery('#error_register').removeClass('hidden-block');
                jQuery('#error_register strong').html(parsed_data.mensajeRetorno);
            } else {
                printGeneratedLabels(parsed_data.filePath, varsAjax.path_to_module);
            }

                button.find('.spin').addClass('hidden-block');
                button.find('.label-message').removeClass('hidden-block');
        }
    });

    event.preventDefault();
}

function showWrongLabelFormat() {
    promiseModal = new Promise((resolve, reject) => {
        revolvePromise = resolve;
        let confirmationTitle = atention;
        let description = messageWrongLabelFormat;
        jQuery('#myModalTitle').html(confirmationTitle);
        jQuery('#myModalDescription p').html(description);
        jQuery('#myModalCancelButton').html(cancelStr);
        jQuery('#myModalActionButton').hide();

        jQuery('#myModal').modal({
            backdrop: 'static',
            keyboard: false,
        });

        jQuery('#myModal').modal('show');
    });

    return promiseModal;
}

/**
 * Actualiza los campos de dirección del formulario "Datos Destinatario"
 * con los datos del punto de recogida seleccionado
 */
function updateShippingAddressFields(locationData) {
    if (!locationData || !locationData.data) {
        console.log('No hay datos de ubicación disponibles');
        return;
    }
    
    console.log('locationData completo:', locationData);
    console.log('locationData.data:', locationData.data);
    
    const pickupData = locationData.data;
    
    // Intentar extraer los datos de diferentes posibles estructuras
    const pickupName = locationData.name || pickupData.name || pickupData.unitName || '';
    const pickupAddress = locationData.address || pickupData.address || '';
    const pickupReference = locationData.reference || pickupData.unitCode || '';
    const pickupCity = locationData.city || pickupData.city || '';
    const pickupPostcode = locationData.zipcode || pickupData.zipcode || '';
    
    console.log('Actualizando dirección de envío:', {
        name: pickupName,
        address: pickupAddress,
        reference: pickupReference,
        city: pickupCity,
        postcode: pickupPostcode
    });
    
    // Actualizar los campos del formulario "Datos Destinatario" del módulo
    
    // Campo: Empresa (nombre del punto)
    const companyField = jQuery('#customer_company');
    if (companyField.length && pickupName) {
        companyField.val(pickupName);
    }
    
    // Campo: Dirección (dirección física del punto)
    const addressField = jQuery('#customer_address');
    if (addressField.length && pickupAddress) {
        addressField.val(pickupAddress);
    }
    
    // Campo: Ciudad
    const cityField = jQuery('#customer_city');
    if (cityField.length && pickupCity) {
        cityField.val(pickupCity);
    }
    
    // Campo: Código postal
    const postcodeField = jQuery('#customer_cp');
    if (postcodeField.length && pickupPostcode) {
        postcodeField.val(pickupPostcode);
    }
}

/**
 * Detecta cambios en el selector de producto/método de envío
 * y muestra un modal si se cambia de punto de entrega a envío normal
 */
jQuery(document).ready(function() {
    let previousCarrierData = null;
    
    // Función para determinar si un producto es de punto de entrega
    function isPickupProduct(selectedOption) {
        if (!selectedOption || !selectedOption.length) return false;
        const carrierType = selectedOption.data('carrier_type') || selectedOption.data('carrier-type') || '';
        return carrierType === 'office' || carrierType === 'citypaq' || carrierType === 'pudocex';
    }
    
    // Guardar el estado inicial al cargar la página
    const initialCarrier = jQuery('#input_select_carrier').find('option:selected');
    if (initialCarrier.length) {
        previousCarrierData = {
            isPickup: isPickupProduct(initialCarrier),
            value: initialCarrier.val(),
            carrierType: initialCarrier.data('carrier_type') || ''
        };
        
        // Cargar datos guardados del punto de recogida si existen
        loadSavedPickupPointData(previousCarrierData.carrierType);
    }
    
    /**
     * Carga los datos guardados del punto de recogida desde los campos ocultos
     */
    function loadSavedPickupPointData(carrierType) {
        const referenceCode = jQuery('#reference_code').val();
        const requestDataRaw = jQuery('#request_data').val();
        
        if (!referenceCode || !requestDataRaw) {
            return;
        }
        
        try {
            const requestData = JSON.parse(requestDataRaw);
            
            switch(carrierType) {
                case 'office':
                    loadOfficeData(referenceCode, requestData);
                    break;
                case 'citypaq':
                    loadCityPaqData(referenceCode, requestData);
                    break;
                case 'pudocex':
                    loadPudoCEXData(referenceCode, requestData);
                    break;
            }
        } catch (e) {
            console.error('Error parseando datos del punto de recogida:', e);
        }
    }
    
    /**
     * Carga datos de Office guardados
     */
    function loadOfficeData(referenceCode, data) {
        const name = data.nombreOficina || data.unitName || data.nombre || '';
        const address = data.direccionOficina || data.address || data.direccion || '';
        const city = data.poblacionOficina || data.municipalityName || data.descLocalidad || '';
        const zipcode = data.codigoPostalOficina || data.postalCode || data.cp || '';
        
        document.getElementById('dir-office').innerHTML = address;
        document.getElementById('loc-office').innerHTML = city;
        document.getElementById('cp-office').innerHTML = zipcode;
        document.getElementById('cod_office').value = referenceCode;
        document.getElementById('office_address').value = address;
        document.getElementById('office_city').value = city;
        document.getElementById('office_cp').value = zipcode;
        
        jQuery('.office-container').removeClass('hidden-block');
    }
    
    /**
     * Carga datos de CityPaq guardados
     */
    function loadCityPaqData(referenceCode, data) {
        const name = data.alias || '';
        const address = data.direccion || data.addressName || '';
        const city = data.desc_localidad || data.municipality || '';
        const zipcode = data.cod_postal || data.postalCode || '';
        
        document.getElementById('dir-citypaq').innerHTML = address;
        document.getElementById('loc-citypaq').innerHTML = city;
        document.getElementById('cp-citypaq').innerHTML = zipcode;
        document.getElementById('cod_homepaq').value = referenceCode;
        document.getElementById('citypaq_address').value = address;
        document.getElementById('citypaq_city').value = city;
        document.getElementById('citypaq_cp').value = zipcode;
        
        jQuery('.citypaq-container').removeClass('hidden-block');
    }
    
    /**
     * Carga datos de PudoCEX guardados
     */
    function loadPudoCEXData(referenceCode, data) {
        const name = data.nombrePtoConv || '';
        const address = data.direccionPtoConv || '';
        const city = data.ciudadPtoConv || '';
        const zipcode = data.codigoPostalPtoConv || '';
        
        document.getElementById('dir-pudocex').innerHTML = address;
        document.getElementById('loc-pudocex').innerHTML = city;
        document.getElementById('cp-pudocex').innerHTML = zipcode;
        document.getElementById('cod_pudocex').value = referenceCode;
        document.getElementById('pudocex_address').value = address;
        document.getElementById('pudocex_city').value = city;
        document.getElementById('pudocex_cp').value = zipcode;
        
        jQuery('.pudocex-container').removeClass('hidden-block');
    }
    
    function handleCarrierChange() {
        const $this = jQuery(this);
        const currentCarrier = $this.find('option:selected');
        const currentIsPickup = isPickupProduct(currentCarrier);
        const currentCarrierType = currentCarrier.data('carrier_type') || '';
        
        // Si el cambio es desde punto de entrega a envío normal
        if (previousCarrierData && previousCarrierData.isPickup && !currentIsPickup) {
            
            const previousValue = previousCarrierData.value;
            
            // Mostrar modal de advertencia
            showRestoreAddressModal().then(function(confirmed) {
                if (confirmed) {
                    // Restaurar dirección de facturación
                    restoreBillingAddressToShipping();
                    // Actualizar el estado anterior con el nuevo valor
                    previousCarrierData = {
                        isPickup: currentIsPickup,
                        value: currentCarrier.val(),
                        carrierType: currentCarrierType
                    };
                    // Mostrar/ocultar contenedores según el tipo de carrier
                    togglePickupContainers(currentCarrierType);
                } else {
                    // Revertir el select al valor anterior sin disparar el evento change
                    $this.off('change', handleCarrierChange);
                    $this.val(previousValue);
                    // Re-vincular el evento después de un pequeño delay
                    setTimeout(function() {
                        $this.on('change', handleCarrierChange);
                    }, 0);
                    // Restaurar la visibilidad del contenedor anterior
                    togglePickupContainers(previousCarrierData.carrierType);
                    // No actualizar previousCarrierData, mantener el estado anterior
                }
            });
        } else {
            // Para cualquier otro cambio, actualizar el estado anterior normalmente
            previousCarrierData = {
                isPickup: currentIsPickup,
                value: currentCarrier.val(),
                carrierType: currentCarrierType
            };
            // Mostrar/ocultar contenedores según el tipo de carrier
            togglePickupContainers(currentCarrierType);
        }
    }
    
    /**
     * Muestra u oculta los contenedores de punto de recogida según el tipo
     */
    function togglePickupContainers(carrierType) {
        // Ocultar todos los contenedores de pickup
        jQuery('.office-container').addClass('hidden-block');
        jQuery('.citypaq-container').addClass('hidden-block');
        jQuery('.pudocex-container').addClass('hidden-block');
        
        // Mostrar el contenedor correspondiente
        switch(carrierType) {
            case 'office':
                jQuery('.office-container').removeClass('hidden-block');
                break;
            case 'citypaq':
                jQuery('.citypaq-container').removeClass('hidden-block');
                break;
            case 'pudocex':
                jQuery('.pudocex-container').removeClass('hidden-block');
                break;
            default:
                break;
        }
    }
    
    jQuery('#input_select_carrier').on('change', handleCarrierChange);
    /**
     * Muestra un modal pidiendo confirmación para restaurar la dirección
     */
    function showRestoreAddressModal() {
        return new Promise(function(resolve) {
            const modalTitle = 'Cambio de método de envío';
            const modalDescription = 'Has cambiado de un punto de entrega a envío a domicilio. ' +
                                   '¿Deseas usar la dirección de facturación como dirección de envío?';
            
            jQuery('#myModalTitle').html(modalTitle);
            jQuery('#myModalDescription p').html(modalDescription);
            jQuery('#myModalCancelButton').html('Cancelar').show();
            jQuery('#myModalActionButton').html('Sí, restaurar dirección').show();
            
            jQuery('#myModal').modal({
                backdrop: 'static',
                keyboard: false
            });
            
            jQuery('#myModal').modal('show');
            
            // Handler para el botón de acción (Sí)
            jQuery('#myModalActionButton').off('click').on('click', function() {
                jQuery('#myModal').modal('hide');
                resolve(true);
            });
            
            // Handler para el botón de cancelar (No)
            jQuery('#myModalCancelButton').off('click').on('click', function() {
                jQuery('#myModal').modal('hide');
                resolve(false);
            });
        });
    }
    
    /**
     * Restaura la dirección de facturación en los campos de envío
     */
    function restoreBillingAddressToShipping() {
        // Obtener dirección de facturación
        const billingCompany = jQuery('#_billing_company').val() || '';
        const billingAddress = jQuery('#_billing_address_1').val() || '';
        const billingAddress2 = jQuery('#_billing_address_2').val() || '';
        const billingCity = jQuery('#_billing_city').val() || '';
        const billingPostcode = jQuery('#_billing_postcode').val() || '';
        const billingState = jQuery('#_billing_state').val() || '';
        const billingCountry = jQuery('#_billing_country').val() || '';
        
        // Actualizar campos del formulario "Datos Destinatario"
        jQuery('#customer_company').val(billingCompany);
        jQuery('#customer_address').val(billingAddress);
        jQuery('#customer_address2').val(billingAddress2);
        jQuery('#customer_city').val(billingCity);
        jQuery('#customer_cp').val(billingPostcode);
        
        // Si existen campos de estado y país, también actualizarlos
        const stateField = jQuery('#customer_state');
        const countryField = jQuery('#customer_country');
        
        if (stateField.length) {
            stateField.val(billingState);
        }
        
        if (countryField.length) {
            countryField.val(billingCountry).trigger('change');
        }
    }
});

} // Cierre del if (!sga_module)
