jQuery(document).ready(function ($) {

    // ── Track previous state of ActivateMarketplace checkbox ─────────────────
    var _marketplaceWasActive = $('#ActivateMarketplace').is(':checked');

    // ── Form submit ─────────────────────────────────────────────────────────
    $('#MarketplaceConfigurationDataForm').validate({
        rules: {},
        messages: {},

        submitHandler: function () {

            let formElement = document.getElementById('MarketplaceConfigurationDataForm');
            let formData = new FormData(formElement);

            // Asegurar que el checkbox envíe su valor ('on' / '')
            const checkbox = document.getElementById('ActivateMarketplace');
            formData.set('ActivateMarketplace', checkbox && checkbox.checked ? 'on' : '');

            formData.append('action', 'correosOficialDispacher');
            formData.append('dispatcher[controller]', 'AdminCorreosOficialMarketplaceProcessController');
            formData.append('dispatcher[action]', 'saveMarketplaceConfig');
            formData.append('_nonce', varsAjax.nonce);

            $.ajax({
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
                        // Lock or unlock Senders and Fulfillment accordions
                        setMarketplaceAccordionLock(data.marketplaceActive);

                        // Update API credentials panel
                        updateMarketplaceApiInfoBlock(
                            data.marketplaceActive,
                            data.consumerKey,
                            data.consumerSecret,
                            data.apiBaseUrl
                        );

                        // Show confirmation modal with reload on close
                        showMarketplaceInfoAndReload(marketplaceConfigurationSaved);
                    }
                },
                error: function (e) {
                    showModalErrorWindow('Error sending Marketplace Configuration form.');
                }
            });
        }
    });

    // ── Checkbox toggle ──────────────────────────────────────────────────────
    $('#ActivateMarketplace').change(function () {
        const isChecked = $(this).is(':checked');

        // Deactivation: warn the user that API access will be revoked
        if (!isChecked && _marketplaceWasActive) {
            // Revert checkbox visually until confirmed
            $(this).prop('checked', true);

            showMarketplaceConfirmDeactivation(function () {
                // User confirmed → uncheck and toggle UI
                $('#ActivateMarketplace').prop('checked', false);
                $('#UserConfigNonMarketplaceBlock').show();
                $('#UserConfigNifBlock').show();
                $('#MarketplaceConfigurationDataBlock').hide();
            });
            return;
        }

        $('#UserConfigNonMarketplaceBlock').toggle(!isChecked);
        $('#UserConfigNifBlock').toggle(!isChecked);
        $('#MarketplaceConfigurationDataBlock').toggle(isChecked);
    });

    // ── Copy-to-clipboard (data-co-copy="inputId") ───────────────────────────
    $(document).on('click', '[data-co-copy]', function () {
        const targetId = $(this).data('co-copy');
        const $input = $('#' + targetId);
        if (!$input.length) return;

        const prevType = $input.attr('type');
        $input.attr('type', 'text');
        $input[0].select();
        document.execCommand('copy');
        $input.attr('type', prevType);

        const $btn = $(this);
        const originalText = $btn.text().trim();
        $btn.text('✓');
        setTimeout(function () { $btn.text(originalText); }, 1500);
    });

    // ── Toggle Consumer Secret visibility ────────────────────────────────────
    $('#MarketplaceToggleSecret').on('click', function () {
        const $input = $('#MarketplaceConsumerSecretInput');
        const isPassword = $input.attr('type') === 'password';
        $input.attr('type', isPassword ? 'text' : 'password');
    });

});

/**
 * Updates the API info / pending blocks inside MarketplaceConfigurationDataBlock
 * after a successful save.
 *
 * @param {boolean} isActive
 * @param {string}  consumerKey
 * @param {string}  consumerSecret
 * @param {string}  apiBaseUrl
 */
function updateMarketplaceApiInfoBlock(isActive, consumerKey, consumerSecret, apiBaseUrl) {
    if (isActive && consumerKey) {
        jQuery('#MarketplaceConsumerKeyInput').val(consumerKey);
        jQuery('#MarketplaceConsumerSecretInput').val(consumerSecret);
        jQuery('#MarketplaceApiBaseUrlInput').val(apiBaseUrl);
        jQuery('#MarketplaceApiInfoBlock').show();
        jQuery('#MarketplaceApiPendingBlock').hide();
    } else if (isActive) {
        // Active but key not ready yet
        jQuery('#MarketplaceApiInfoBlock').hide();
        jQuery('#MarketplaceApiPendingBlock').show();
    } else {
        // Deactivated – hide both
        jQuery('#MarketplaceApiInfoBlock').hide();
        jQuery('#MarketplaceApiPendingBlock').hide();
    }
}

/**
 * Locks or unlocks the Senders and Fulfillment accordion buttons
 * depending on whether Marketplace mode is active.
 *
 * @param {boolean} lock  true = disable buttons, false = re-enable
 */
function setMarketplaceAccordionLock(lock) {
    var $buttons = jQuery('#sender_block, #fulfillment_block');
    var lockedTitle = (typeof marketplaceAccordionLockedTitle !== 'undefined')
        ? marketplaceAccordionLockedTitle : '';

    if (lock) {
        $buttons.each(function () {
            var $btn = jQuery(this);
            // Collapse the panel if currently open
            var targetId = $btn.data('bs-target');
            if (targetId) {
                var $panel = jQuery(targetId);
                if ($panel.hasClass('show')) {
                    $btn.trigger('click');
                }
            }
            $btn.prop('disabled', true)
                .attr('data-co-marketplace-locked', 'true')
                .attr('title', lockedTitle)
                .addClass('co-accordion-locked');
        });
    } else {
        $buttons.each(function () {
            jQuery(this).prop('disabled', false)
                .removeAttr('data-co-marketplace-locked')
                .removeAttr('title')
                .removeClass('co-accordion-locked');
        });
    }

    // Ocultar/mostrar campos de configuración de usuario irrelevantes en modo marketplace
    jQuery('#UserConfigNonMarketplaceBlock').toggle(!lock);
    jQuery('#UserConfigNifBlock').toggle(!lock);
}

/**
 * Shows the info modal and reloads the page when the user closes it.
 *
 * @param {string} message  Text to display
 */
function showMarketplaceInfoAndReload(message) {
    jQuery("#myModalTitle").html(informationTitle);
    jQuery("#myModalDescription p").html(message);
    jQuery("#myModalActionButtonCustomerData").hide();
    jQuery("#myModalActionButtonSenders").hide();
    jQuery("#myModalCancelButton").hide();
    jQuery("#myModalAcceptButton").html(acceptButton).show();
    jQuery("#myModal").modal("show");

    // Reload on close (accept or dismiss)
    jQuery("#myModal").one("hidden.bs.modal", function () {
        location.reload();
    });
}

/**
 * Shows a confirmation modal warning that deactivation will revoke API access.
 * If the user accepts, the onConfirm callback is executed.
 *
 * @param {Function} onConfirm  Called when the user confirms deactivation
 */
function showMarketplaceConfirmDeactivation(onConfirm) {
    var msg = (typeof marketplaceDeactivateWarning !== 'undefined')
        ? marketplaceDeactivateWarning
        : 'If you save, the current API access will be revoked. When re-enabling Marketplace a new key will be generated.';

    jQuery("#myModalTitle").html(informationTitle);
    jQuery("#myModalDescription p").html(msg);
    jQuery("#myModalActionButtonCustomerData").hide();
    jQuery("#myModalActionButtonSenders").hide();
    jQuery("#myModalCancelButton").show();
    jQuery("#myModalAcceptButton").html(acceptButton).show();
    jQuery("#myModal").modal("show");

    // Accept → proceed with deactivation
    jQuery("#myModalAcceptButton").one("click", function () {
        jQuery("#myModal").modal("hide");
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });
}