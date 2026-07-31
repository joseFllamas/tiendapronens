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
jQuery(document).ready(function ($) {
    jQuery('#ShippingStatusProcess').hide();
    jQuery('#ShippingStatusProcessBlock').hide();
    jQuery('#NifFieldRadioBlock').hide();

    if (jQuery('#ActivateAutomaticTracking').is(':checked')) {
        jQuery('#ShippingStatusProcessBlock').show('slow');
    } else {
        jQuery('#ShippingStatusProcessBlock').hide('slow');
    }

    if (jQuery('#ActivateNifFieldCheckout').is(':checked')) {
        jQuery('#NifFieldRadioBlock').show('slow');
    } else {
        jQuery('#NifFieldRadioBlock').hide('slow');
    }

    /* Validaciones de campos */
    jQuery.validator.addMethod(
        'validate_acc_iban',
        function (value) {
            if (value.substring(0, 4) === '****' || value == '') {
                return true;
            } else {
                return validate_acc_iban(value);
            }
        },
        wrongACCAndIBAN
    ); /* Retornamos el literal traducible del settings-user-configuration.tpl */

    /* Comportamiento de Tiempo de actualización de estados */
    jQuery('#CronInterval').change(function () {
        let valor = jQuery('#CronInterval').val();
        switch (valor) {
            case '2':
                jQuery('#CronInterval_TEXT').html('2 ' + hours);
                break;
            case '3':
                jQuery('#CronInterval_TEXT').html('3 ' + hours);
                break;
            case '4':
                jQuery('#CronInterval_TEXT').html('4 ' + hours);
                break;
            case '5':
                jQuery('#CronInterval_TEXT').html('5 ' + hours);
                break;
            case '6':
                jQuery('#CronInterval_TEXT').html('6 ' + hours);
                break;
            case '7':
                jQuery('#CronInterval_TEXT').html('7 ' + hours);
                break;
            case '8':
                jQuery('#CronInterval_TEXT').html('8 ' + hours);
                break;
        }
    });

    /* Evento para controlar visibilidad de bloque del progreso del envío en la tienda */
    jQuery('#ActivateAutomaticTracking').on('click', function () {
        if (jQuery(this).is(':checked')) {
            jQuery('#ShippingStatusProcessBlock').show('slow');

            if (jQuery('#ShowShippingStatusProcess').is(':checked')) {
                jQuery('#ShippingStatusProcess').show('slow');
            } else {
                jQuery('#ShippingStatusProcess').hide('slow');
            }
        } else {
            jQuery('#ShippingStatusProcessBlock').hide('slow');
            jQuery('#ShippingStatusProcess').hide('slow');
        }
    });

    /* Mostramos/ocultamos progreso del estado del envío en la tienda */
    jQuery('#ShowShippingStatusProcess').on('click', function () {
        if (jQuery(this).is(':checked')) {
            jQuery('#ShippingStatusProcess').show('slow');
        } else {
            jQuery('#ShippingStatusProcess').hide('slow');
        }
    });

    if (jQuery('#ShowShippingStatusProcess').is(':checked')) {
        jQuery('#ShippingStatusProcess').show();
    }

    jQuery('#clean-upload').on('click', function() {
        jQuery('#UploadLogoLabels').val('');
    });
});
