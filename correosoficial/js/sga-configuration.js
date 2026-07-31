jQuery(document).ready(function ($) {

    jQuery('#SGAConfigurationDataForm').validate({
        rules: {
            SGAOwner: {
                required: function (element) {
                    return jQuery('#ActivateSGA').is(':checked');
                },
            },
            SGACustomer: {
                required: function (element) {
                    return jQuery('#ActivateSGA').is(':checked');
                },
            },
            SGAStore: {
                required: function (element) {
                    return jQuery('#ActivateSGA').is(':checked');
                },
            },
        },
        /* Mensaje custom por campo  */
        messages: {
            SGAOwner: {
                required: sgaOwnerRequired,
            },
            SGACustomer: {
                required: sgaCustomerRequired,
            },
            SGAStore: {
                required: sgaStoreRequired,
            },
        },

        submitHandler: function () {

        let formElement = document.getElementById('SGAConfigurationDataForm');
        let formData = new FormData(formElement);

        // Asegurar que todos los checkboxes envíen su valor (1/0)
        ['ActivateSGA', 'SGAUpdateStock', 'SGAOrderUpdateStock', 'SGAOrderStatusTracking'].forEach(name => {
            const checkbox = document.getElementById(name);
            formData.set(name, checkbox && checkbox.checked ? 'on' : '');
        });

        formData.append('action', 'correosOficialDispacher');
        formData.append('dispatcher[controller]', 'AdminCorreosOficialSGAProcessController');
        formData.append('dispatcher[action]', 'saveSGAConfig');
        formData.append('_nonce', varsAjax.nonce);

            jQuery.ajax({
                type: 'POST',
                url: varsAjax.ajaxUrl,
                data: formData,
                dataType: 'json',
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    let data = response.data;

                    if (!data.success) {
                        showModalErrorWindow(data.message);
                    } else {
                        showModalInfoWindow(sgaConfigurationSaved);
                    }
                },
                error: function (e) {
                    alert('Error al enviar el formulario Configuración del Logística.');
                }
            });
        }
    });

    // Detectamos cambios en el checkbox de activar SGA
    jQuery('#ActivateSGA').change(function () {
            // Limpiamos estados de validación
            jQuery('#SGAOwner').valid();
            jQuery('#SGACustomer').valid();
            jQuery('#SGAStore').valid();

            // Si se activa el SGA, mostramos los campos de configuración
            if (jQuery(this).is(':checked')) {
                jQuery('#SGAConfigurationDataBlock').show();
            }
            // Si se desactiva el SGA, ocultamos los campos de configuración
            else {
                jQuery('#SGAConfigurationDataBlock').hide();
            }
        }
    );

    // Detectamos cambios en el checkbox de activar seguimiento automatico del pedido
    // Despliega opciones de estados
    jQuery('#SGAOrderStatusTracking').change(function () {
            // Si se activa el SGA, mostramos los campos de configuración
            if (jQuery(this).is(':checked')) {
                jQuery('#order-tracking-options').show();
            }
            // Si se desactiva el SGA, ocultamos los campos de configuración
            else {
                jQuery('#order-tracking-options').hide();
            }
        }
    );
    
    const selects = document.querySelectorAll(".sgaOrderStatusSelect");

    function actualizarOpciones() {
        // Obtener todos los valores seleccionados
        const seleccionados = Array.from(selects).map(s => s.value).filter(v => v !== "");

        // Para cada select
        selects.forEach(select => {
        // Guardar el valor actual
        const valorActual = select.value;

        // Recorrer opciones y habilitar/deshabilitar
        Array.from(select.options).forEach(opt => {
            if (opt.value === "" || opt.value === valorActual) {
            opt.disabled = false; // Mantener habilitado el placeholder y lo elegido
            } else {
            opt.disabled = seleccionados.includes(opt.value); // Deshabilitar si ya está en otro select
            }
        });
        });
    }

    // Ejecutar al cargar y cuando cambie cualquiera
    selects.forEach(select => {
        select.addEventListener("change", actualizarOpciones);
    });

    // Modificar valor visualmente del intervalo para el seguimiento del pedido SGA
    /* Comportamiento de Tiempo de actualización de estados */
    jQuery('#SGAOrderStatusTrackingCronInterval').change(function () {
        let valor = jQuery('#SGAOrderStatusTrackingCronInterval').val();
        switch (valor) {
            case '2':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('2 ');
                break;
            case '3':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('3 ');
                break;
            case '4':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('4 ');
                break;
            case '5':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('5 ');
                break;
            case '6':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('6 ');
                break;
            case '7':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('7 ');
                break;
            case '8':
                jQuery('#order_status_tracking_cron_interval_TEXT').html('8 ');
                break;
        }
    });

    actualizarOpciones();

});
