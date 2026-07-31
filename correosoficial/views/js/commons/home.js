/* *************************************************************************************************
 *                                  CORREOS OFICIAL HOME
 ****************************************************************************************************/
jQuery(document).ready(function () {
    jQuery('#form_incompleted').hide();
    jQuery('#form_registered').hide();

    /* Validaciones de campos */
    jQuery.validator.addMethod(
        'validate_nif_cif_nie',
        function (value) {
            result = validate_nif_cif_nie(value);
            return result.valid;
        },
        jQuery.validator.format(wrongDniCif)
    ); /* Literal traducible en home.tpl que mostramos si el DNI/CIF es incorrecto. */

    /* Reglas */
    jQuery('#LeadForm').validate({
        rules: {
            input_company: {
                required: true,
                minlength: 3,
                maxlength: 150,
            },
            input_cif: {
                required: true,
                validate_nif_cif_nie: false,
            },
            input_contact_name: {
                required: true,
                minlength: 3,
                maxlength: 150,
            },
            input_mobile_phone: {
                required: true,
            },
            input_phone: {
                required: false,
            },
            input_email: {
                required: true,
                maxlength: 150,
            },
            product_category: {
                required: true,
                // minlength: 10
            },
            check_policy: {
                required: true,
                // minlength: 10
            },
            /*
            weight: {
              required: {
                depends: function(elem) {
                  return jQuery("#age").val() > 50
                }
              },
              number: true,
              min: 0
            } */
        },
        /* Mensaje custom por campo  */
        messages: {
            input_company: {
                required: requiredCustomMessage,
                minlength: minLengthMessage + ' 3 ' + characters,
                maxlength: maxLengthMessage + ' 150 ' + characters,
            },
            input_cif: {
                required: requiredCustomMessage,
            },
            input_contact_name: {
                required: requiredCustomMessage,
                minlength: minLengthMessage + ' 3 ' + characters,
                maxlength: maxLengthMessage + ' 150 ' + characters,
            },
            input_mobile_phone: {
                required: requiredCustomMessage,
            },
            input_email: {
                required: requiredCustomMessage,
                maxlength: maxLengthMessage + ' 150 ' + characters,
                email: invalidEmail,
            },
            product_category: {
                required: requiredCustomMessage,
            },
            check_policy: {
                required: requiredCustomMessage,
            },
        },
        /* Fin Validaciones */

        submitHandler: function (form) {
            jQuery.ajax({
                url: AdminHomeSendMail,
                type: 'POST',
                cache: false,
                processData: false,
                data: jQuery(form).serialize(),
                success: function (data) {
                    var emailEnviado = data;

                    if (emailEnviado == 'Enviado') {
                        jQuery('#form_register').hide();
                        jQuery('#form_registered').show();
                    } else {
                        jQuery('#form_incompleted').hide();
                        jQuery('#form_registered').hide();
                        showModalErrorWindow('ERROR 16000: ' + homeTechnicalError);
                    }
                },
                error: function (e) {
                    showModalErrorWindow('ERROR 16001: ' + homeTechnicalError);
                },
            });
        },
    });
});

jQuery('#privacy_policy').click(function (e) {
    var width = screen.width / 3;
    var height = screen.height;
    e.preventDefault();
    window.open('https://www.correos.es/es/es/legales/otros/formulario-paqueteria--proteccion-datos', '_blank', 'toolbar=yes,scrollbars=yes,resizable=yes,top=0,left=500,width=' + width + ',height=' + height + '');
});
