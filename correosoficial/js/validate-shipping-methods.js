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
    function validate(element) {
        let value = element.val();
        if (!jQuery.isNumeric(value)) {
            return element.addClass('error');
        } else {
            return element.removeClass('error');
        }
    }

    // Validación de campos
    jQuery(document).on('change', '.co_shipping_inputs', function () {
        jQuery('.co_shipping_inputs').removeClass('error');
        jQuery('#btn-ok').prop('disabled', false);

        validate(jQuery(this));
    });

    // Validación Interreglas
    jQuery(document).on('change', '.range', function () {
        let costArray = [];
        let weightArray = [];
        let classArray = [];

        let classes = '';
        let condition = '';
        let from = 0;
        let to = 0;

        let i = 0;

        let input = '';

        jQuery(this)
            .parent()
            .parent()
            .find('select[data-type="class"] option')
            .each(function () {
                classArray.push(jQuery(this).val());
            });

        let totalArray = new Array(classArray.length);

        jQuery('.ruleOptions').each(function () {
            classes = jQuery(this).find('select[data-type="class"]').val();
            condition = jQuery(this).find('select[data-type="condition"]').val();
            from = jQuery(this).find('input[data-type="from"]').val();
            to = jQuery(this).find('input[data-type="to"]').val();

            totalArray[i] = [classes, condition, from, to];
            i++;
        });

        input = jQuery(this);

        classArray.forEach(function (currentValue, index, array) {
            costArray = getRuleArray(totalArray, currentValue, 'cost');
            weightArray = getRuleArray(totalArray, currentValue, 'weightkg');

            /* Comprobamos las reglas */
            if (costArray.length > 0) {
                checkRule(costArray, input);
            }

            if (weightArray.length > 0) {
                checkRule(weightArray, input);
            }
        });
    });

    jQuery(document).on('change', '.regular-input', function () {
        validate(jQuery(this));
    });

    jQuery(document).on('change', '.condition', function () {
        let type = jQuery(this).val();
        jQuery(this).parent().next().find('input').attr('data-condition', type);
        jQuery(this).parent().next().next().find('input').attr('data-condition', type);
    });

    function getRuleArray(totalArray, value, type) {
        let ruleArray = [];

        totalArray.forEach(function (currentValue, index, array) {
            if (totalArray[index][0] == value && totalArray[index][1] == type) {
                ruleArray.push(totalArray[index][2]);
                ruleArray.push(totalArray[index][3]);
            }
        });

        return ruleArray;
    }

    function checkRule(ruleArray, element) {
        let invalid = false;

        for (let i = 0; i < ruleArray.length; i++) {
            if (parseFloat(ruleArray[i + 1]) < parseFloat(ruleArray[i])) {
                invalid = true;
            }
        }

        if (invalid) {
            element.addClass('error');
            jQuery('#btn-ok').prop('disabled', true);
            return false;
        } else {
            return true;
        }
    }

    /**
     * Jquery para versiones por debajo de la 8.3.0
     * No permitimos escoger métodos de envío si no se han seleccionado regiones o
     * guardado previamente
     */
    jQuery(document).on('change', "select[name='add_method_id']", function () {
        let selectedVal = jQuery(this).find(':selected').val();
        let regions = jQuery('#zone_locations').val();
        let saveButtonDisabled = jQuery('#submit').prop('disabled');

        if (selectedVal.includes('request_shipping_quote_')) {
            if (!regions.length > 0 || !saveButtonDisabled || window.location.search.indexOf('&zone_id=new') != -1) {
                alert(validateShippingMethod.mustSaveBefore);
                jQuery('#btn-ok').prop('disabled', true);
            }
            // Zona ya grabada
            else {
                jQuery('#btn-ok').prop('disabled', false);
            }
        } else {
            jQuery('#btn-ok').prop('disabled', false);
        }
    });

    /**
     * Jquery para versiones por encima de la 8.2.0
     * Controla que los metodos de envio no se puedan añadir hasta que
     * se haya agregado como minimo una region a la zona
     */
    jQuery('body').on('click', '.wc-shipping-zone-add-method', function () {
        setTimeout(function () {
            let container = jQuery('.wc-shipping-zone-method-selector');
            let shippingRadios = container.find('.wc-shipping-zone-method-input input[value^="request_shipping"]');

            shippingRadios.each(function () {
                let radio = jQuery(this);
                jQuery(radio).on('change', function () {
                    // Estamos en la zona resto del mundo
                    if (window.location.search.includes('&zone_id=0')) {
                        jQuery('#btn-next').prop('disabled', false);
                    } else {
                        let tagsDiv = jQuery('.woocommerce-tag__text');
                        if (!tagsDiv.length > 0 || window.location.search.includes('&zone_id=new') || !jQuery('.wc-shipping-zone-method-save').prop('disabled')) {
                            alert('No puedes añadir un método de envío de Correos Oficial si la región no ha sido añadida y guardada previamente');
                            jQuery('#btn-next').prop('disabled', true);
                        } else {
                            jQuery('#btn-next').prop('disabled', false);
                        }
                    }
                });
            });
        }, 500);
    });
});
