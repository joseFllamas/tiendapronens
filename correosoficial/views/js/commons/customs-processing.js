jQuery.validator.addMethod(
    'TariffLength',
    function (element) {
        return element.length == 6 || element.length == 8 || element.length == 10 ? true : false;
    },
    jQuery.validator.format('Input data must be 6, 8 or 10 characters long')
);

jQuery(document).ready(function ($) {

    function updateSelectedCustomsTab(value) {
        jQuery('#CustomsDesriptionAndTariffSelected').val(value);
    }

    // Almacenamos las cadenas de idioma en este objeto.
    var languagesObject;
    // Convertimos la cadena JSON de la bbdd a un objeto JSON (Javascript Object)
    var jsonstring = $('#TranslatableInputH').val();
    languagesObject = $.parseJSON(jsonstring);

    // Al salir del selector actualizamos el input de TranslatableInput
    $('#FormSwitchLanguage').change(function (e) {
        // Recuperamos el id del idioma y lo convertimos a String
        var language_id = $('#FormSwitchLanguage :selected').val();
        var i = language_id.toString();

        $('#TranslatableInput').val(languagesObject[i]);
    });

    // Actualizamos idiomas en el front.
    $('#TranslatableInput').blur(function (e) {
        // Id del lenguaje seleccionado
        var language_id = $('#FormSwitchLanguage :selected').val();
        // Convertimos el id lenguage a string para poder utilizarlo como índice.
        var i = language_id.toString();

        languagesObject[i] = $('#TranslatableInput').val();
        $('#TranslatableInputH').val(JSON.stringify(languagesObject));
        // Añadimos ventana modal informativa.
        /* showModalInfoWindow('El cambio solo se se hará efectivo al guardar'); */
    });

    var initiallyChecked = $("input[name='CustomsDesriptionAndTariff[]']:checked").val();
    if (typeof initiallyChecked !== 'undefined') {
        updateSelectedCustomsTab(initiallyChecked);
    }

    if ($("input[type='radio'][id='DescriptionRadio']:checked").val()) {
        // Keep fields enabled; only show the appropriate tab.
    } else if ($("input[type='radio'][id='TariffRadio']:checked").val()) {
        // Keep fields enabled; only show the appropriate tab.
    }

    /** Ocultamos-Mostramos los radios de TRAMITACIÓN ADUANERA*/
    $('Form input:radio').change(function () {
        if ($(this).val() == '0') {
            // Description selected: do not disable any fields, just update selected tab
            updateSelectedCustomsTab('0');
        } else if ($(this).val() == '1') {
            // Tariff selected: do not disable any fields, just update selected tab
            updateSelectedCustomsTab('1');
        } else if ($(this).val() == '2') {
            updateSelectedCustomsTab('2');
        }
    });

    /**
     * Tabs de documentación aduanera
     */
    var addingDesc = true;
    var addingTarriffCode = false;

    jQuery('.nav-link').on('click', function (event) {
        event.preventDefault();
        jQuery(this).addClass('active');

        if (jQuery(this).attr('data-type') == 'customs_desc') {
            addingDesc = true;
            addingTarriffCode = false;
            showCustomsDesc();
            jQuery('#DescriptionRadio').prop('checked', true);
            jQuery('#TariffRadio').prop('checked', false);
            jQuery('#ProductRadio').prop('checked', false);
            updateSelectedCustomsTab('0');
        } else if (jQuery(this).attr('data-type') == 'customs_code') {
            addingDesc = false;
            addingTarriffCode = true;
            showCustomsCode();
            jQuery('#TariffRadio').prop('checked', true);
            jQuery('#DescriptionRadio').prop('checked', false);
            jQuery('#ProductRadio').prop('checked', false);
            updateSelectedCustomsTab('1');
        } else if (jQuery(this).attr('data-type') == 'customs_product') {
            addingDesc = false;
            addingTarriffCode = false;
            showCustomsProduct();
            jQuery('#ProductRadio').prop('checked', true);
            jQuery('#DescriptionRadio').prop('checked', false);
            jQuery('#TariffRadio').prop('checked', false);
            updateSelectedCustomsTab('2');
        }
    });

    function showCustomsDesc() {
        jQuery('#customs_desc_tab').removeClass('hidden-block');
        jQuery('#customs_code_tab').addClass('hidden-block');
        jQuery('#customs_product_tab').addClass('hidden-block');
        jQuery('#customs_code').removeClass('active');
        jQuery('#customs_product').removeClass('active');
    }

    function showCustomsCode() {
        jQuery('#customs_desc_tab').addClass('hidden-block');
        jQuery('#customs_code_tab').removeClass('hidden-block');
        jQuery('#customs_product_tab').addClass('hidden-block');
        jQuery('#customs_desc').removeClass('active');
        jQuery('#customs_product').removeClass('active');
    }

    function showCustomsProduct() {
        jQuery('#customs_desc_tab').addClass('hidden-block');
        jQuery('#customs_code_tab').addClass('hidden-block');
        jQuery('#customs_product_tab').removeClass('hidden-block');
        jQuery('#customs_desc').removeClass('active');
        jQuery('#customs_code').removeClass('active');
    }

    // Ensure UI matches the visually active tab on initial load
    // Trigger the click handler on the nav-link that already has the `active` class
    // so the correct fields are enabled/disabled even if radios or saved state differ.
    jQuery('.nav-link.active').trigger('click');

    /**
     * Sincronizar campos de país de origen entre las dos tabs
     */
    jQuery('#CountryOriginByDefault').on('input', function() {
        jQuery('#CountryOriginByDefault2').val(jQuery(this).val());
    });

    jQuery('#CountryOriginByDefault2').on('input', function() {
        jQuery('#CountryOriginByDefault').val(jQuery(this).val());
    });

    /**
     * Deshabilitar/habilitar selectores de mapeo según el estado del checkbox UseModuleFeatures
     */
    function toggleMappingSelects() {
        if (jQuery('#UseModuleFeatures').is(':checked')) {
            // Deshabilitar los selectores
            jQuery('#MappedHsFeature').attr('disabled', true);
            jQuery('#MappedHsFeature').css('opacity', '.5');
            jQuery('#MappedOriginFeature').attr('disabled', true);
            jQuery('#MappedOriginFeature').css('opacity', '.5');
            
            // Resetear a "-- Select attribute --"
            jQuery('#MappedHsFeature').val('');
            jQuery('#MappedOriginFeature').val('');
        } else {
            // Habilitar los selectores
            jQuery('#MappedHsFeature').attr('disabled', false);
            jQuery('#MappedHsFeature').css('opacity', '1');
            jQuery('#MappedOriginFeature').attr('disabled', false);
            jQuery('#MappedOriginFeature').css('opacity', '1');
        }
    }

    // Aplicar el estado inicial al cargar la página
    toggleMappingSelects();

    // Aplicar cuando cambie el estado del checkbox
    jQuery('#UseModuleFeatures').on('change', function() {
        toggleMappingSelects();
    });
});
