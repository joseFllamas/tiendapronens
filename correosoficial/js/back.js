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
jQuery(document).ready(function() {
   // Inicio de los tooltips del formulario
   jQuery('.tt_settings').tooltip({
      position: {
      my: "center bottom-20",
      at: "center top",
      collision: "flipfit"
      }
   });

    var checkbox = jQuery('#changeShip'), chShipBlock = jQuery('#changeShipInputs');
    var ShowActivateWeightByDefaultInput = jQuery('#ActivateWeightByDefault'), BlockWeightByDefaultInput = jQuery('#ActivateWeightByDefaultInput');
    var ShowChangeLogoOnLabel = jQuery('#ChangeLogoOnLabel'), BlockChangeLogoOnLabel = jQuery('#UploadLogoLabelsBlock');
    var ShowLabelAlternativeText = jQuery('#CustomerAlternativeText'), BlockLabelAlternativeText = jQuery('#LabelAlternativeTextInput');
    var ActivateOrderStatusChangeAfterSaveCheckbox = jQuery('#ActivateOrderStatusChangeAfterSave'), BlockStatusSelector = jQuery('#StatusSelectorInput');
    var ShowNifFieldRadioBlock = jQuery('#ActivateNifFieldCheckout'), BlockNifFieldRadioBlock = jQuery('#NifFieldRadioBlock');
    var ActivateDimensionsByDefault = jQuery('#ActivateDimensionsByDefault'), ActivateDimensionsByDefaultBLock = jQuery('#ActivateDimensionsByDefaultBLock');
    
    var MessageToWarnBuyerCheckBox = jQuery('#MessageToWarnBuyer');
    var MessageToWarnBuyer2 = jQuery('#MessageToWarnBuyer2');

    chShipBlock.hide();
    BlockWeightByDefaultInput.hide();
    BlockChangeLogoOnLabel.hide();
    BlockLabelAlternativeText.hide();
    BlockStatusSelector.hide();
    MessageToWarnBuyer2.hide();
    ActivateDimensionsByDefaultBLock.hide()

    if (jQuery('#ActivateWeightByDefault').is(':checked')){
        jQuery('#ActivateWeightByDefaultInput').show();
    };
    if (jQuery('#ActivateDimensionsByDefault').is(':checked')){
        jQuery('#ActivateDimensionsByDefaultBLock').show();
    };
    if (jQuery('#ChangeLogoOnLabel').is(':checked')){
        jQuery('#UploadLogoLabelsBlock').show();
    };
    if (jQuery('#CustomerAlternativeText').is(':checked')){
        jQuery('#LabelAlternativeTextInput').show();
    };
   
    // Si Activar cambio de estado de la orden tras grabación mostramos Selecciona un estado
    if (jQuery('#ActivateOrderStatusChangeAfterSave').is(':checked')){
        jQuery('#StatusSelectorInput').show();
    }

    checkbox.on('click', function() {
       if(jQuery(this).is(':checked')) {
          chShipBlock.show();
          chShipBlock.find('input').attr('required', true);
       } else {
          chShipBlock.hide();
          chShipBlock.find('input').attr('required', false);
       }
    });

     ShowActivateWeightByDefaultInput.on('click', function() {
        if(jQuery(this).is(':checked')) {
           BlockWeightByDefaultInput.show("slow");
           BlockWeightByDefaultInput.find('input').attr('required', true);
        } else {
           BlockWeightByDefaultInput.hide("slow");
           BlockWeightByDefaultInput.find('input').attr('required', false);
        }
     });

     ActivateDimensionsByDefault.on('click', function() {
      if(jQuery(this).is(':checked')) {
         ActivateDimensionsByDefaultBLock.show("slow");
         ActivateDimensionsByDefaultBLock.find('input').attr('required', true);
      } else {
         ActivateDimensionsByDefaultBLock.hide("slow");
         ActivateDimensionsByDefaultBLock.find('input').attr('required', false);
      }
   });

     ShowChangeLogoOnLabel.on('click', function() {
      if(jQuery(this).is(':checked')) {
         BlockChangeLogoOnLabel.show("slow");
         BlockChangeLogoOnLabel.find('input').attr('required', true);
      } else {
         BlockChangeLogoOnLabel.hide("slow");
         BlockChangeLogoOnLabel.find('input').attr('required', false);
      }
   });

     ShowLabelAlternativeText.on('click', function() {
      if(jQuery(this).is(':checked')) {
         BlockLabelAlternativeText.show("slow");
         BlockLabelAlternativeText.find('input').attr('required', true);
      } else {
         BlockLabelAlternativeText.hide("slow");
         BlockLabelAlternativeText.find('input').attr('required', false);
      }
   });
   
   ActivateOrderStatusChangeAfterSaveCheckbox.on('click', function() {
      if(jQuery(this).is(':checked')) {
         BlockStatusSelector.show();
         BlockStatusSelector.find('input').attr('required', true);
      } else {
         BlockStatusSelector.hide();
         BlockStatusSelector.find('input').attr('required', false);
      }
   });

   ShowNifFieldRadioBlock.on('click', function() {
      if(jQuery(this).is(':checked')) {
         BlockNifFieldRadioBlock.show("slow");
      } else {
         BlockNifFieldRadioBlock.hide("slow");
      }
   });

    /* **********************************************************************
    *             TRAMITES ADUANEROS: MENSAJE PARA ADVERTIR AL COMMPRADOR.       
    *********************************************************************** */
   MessageToWarnBuyerCheckBox.on('click', function() {
      if(jQuery(this).is(':checked')) {
         MessageToWarnBuyer2.show("slow");
         MessageToWarnBuyer2.find('input').attr('required', true);
      } else {
         MessageToWarnBuyer2.hide("slow");
         MessageToWarnBuyer2.find('input').attr('required', false);
      }
   });

   if ( MessageToWarnBuyerCheckBox.is(':checked')){
      MessageToWarnBuyer2.show();
   }

   /* **********************************************************************************************************
    *                                  MODAL      
    ********************************************************************************************************* */
   jQuery("#myModalCancelButton").click(function(){
      closeMyModalConfirmation();
    });
    jQuery("#myModalAcceptButton").click(function(){
      closeMyModalConfirmation();
    });

   // Cerramos modal al pulsar la tecla enter
   jQuery( "#myModal" ).keydown(function() {
      jQuery(this).keypress(function(e) {
         if(e.which == 13) {
            closeMyModalConfirmation();
         }
      });
   });

});

/* **********************************************************************************************************
 *                                  MODAL      
 ********************************************************************************************************* */
function closeMyModalConfirmation(){
   jQuery("#myModal").modal('hide');
   jQuery("#myModal").modal('dispose');
 }

  // Mostrar ventana Modal
  function showModalErrorWindow(desc) {
    jQuery("#myModalTitle").html(errorTitle);
    jQuery("#myModalDescription p").html(desc);
    jQuery("#myModalActionButtonCustomerData").hide();
    jQuery("#myModalActionButtonSenders").hide();
    jQuery("#myModalCancelButton").hide();
    jQuery("#myModalAcceptButton").html(acceptButton);
    jQuery("#myModalAcceptButton").show();
    jQuery("#myModal").modal("show");
  }

  // Mostrar ventana Modal de Información
 function showModalInfoWindow(desc) {
   jQuery("#myModalTitle").html(informationTitle);
   jQuery("#myModalDescription p").html(desc);
   jQuery("#myModalActionButtonCustomerData").hide();
   jQuery("#myModalActionButtonSenders").hide();
   jQuery("#myModalCancelButton").hide();
   jQuery("#myModalAcceptButton").html(acceptButton);
   jQuery("#myModalAcceptButton").show();
   jQuery("#myModal").modal("show");
   jQuery("#myModal").focus();
 }

