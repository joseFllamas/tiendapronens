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
    var myModal = new bootstrap.Modal(document.getElementById('myModal'));

    function split_time_seconds(time_to_split) {
        var time_split = time_to_split.split(':');
        return time_split[0] + ':' + time_split[1];
    }


    /* Validaciones de campos */
    jQuery.validator.addMethod(
        'validate_nif_cif_nie',
        function (value) {
            result = validate_nif_cif_nie(value);
            return result.valid;
        },
        jQuery.validator.format(wrongDniCif)
    ); /* Literal traducible en senders.tpl que mostramos si el DNI/CIF es incorrecto. */

    $.validator.addMethod(
        'selectOneRequired',
        function (value, element, options) {
            var correosCodeValue = $('#correos_code').val();
            var cexCodeValue = $('#cex_code').val();
            return correosCodeValue !== '' || cexCodeValue !== '';
        },
        selectAContract
    );
    
    jQuery('#SendersDataTable').DataTable({
        searching: false,
        paging: false,
        ordering: false,
        info: false,
        ajax: {
            type: 'post',
            url: varsAjax.ajaxUrl,
            data: {
                action: 'correosOficialDispacher',
                _nonce: varsAjax.nonce,
                dispatcher: {
                    controller: 'AdminCorreosOficialSettings',
                    action: 'getDataTable',
                },
            },
            dataSrc: '',
        },
        columns: [
            { data: 'id' },
            { data: 'sender_name' },
            {
                data: null,
                render: function (data, type, row) {
                    return row.CorreosCustomer;
                }
            },
            { data: 'CEXCustomer' },
            { data: 'sender_address' },
            { data: 'sender_cp' },
            { data: 'sender_nif_cif' },
            { data: 'sender_city' },
            { data: 'sender_contact' },
            { data: 'sender_phone' },
            {
                data: null,
                render: function (data, type, row) {
                    return split_time_seconds(row.sender_from_time);
                },
            },
            {
                data: null,
                render: function (data, type, row) {
                    return split_time_seconds(row.sender_to_time);
                },
            },
            { data: 'sender_iso_code_pais' },
            { data: 'sender_email' },
            {
                data: 'sender_default',
                render: function (data, type, row) {
                    const isDefault = data == 1 ? 'checked disabled' : '';
                    return '<input type="checkbox" class="correosSenderDefault" data-id="' + row.id + '"' + isDefault + '>';
                },
            },
            {
                data: null,
                className: '',
                defaultContent: '<a class="btn btn-primary edit"><i class="far fa-edit"></i></a>',
                orderable: false,
            },
            {
                orderable: false,
                defaultContent: '',
                render: function (data, type, full, meta) {
                    if (full.sender_default == 1) {
                        return '<a class="btn btn-danger remove disabled"><i class="far fa-trash-alt"></i></a>';
                    } else {
                        return '<a class="btn btn-danger remove"><i class="far fa-trash-alt"></i></a>';
                    }
                },
            },
        ],
    });

    // Scroll y focus al editar remitente
    $('#SendersDataTable').on('click', '.edit', function () {
        scrollToAnchor('#sender-anchor');
        $('#sender_name').focus();
    });

    function scrollToAnchor(aid) {
        var aTag = $(aid);
        $('html,body').animate({ scrollTop: aTag.offset().top }, 'slow');
    }

    // Si viene de pedido abrimos bloque Remitente en Ajustes
    if (document.location.hash == '#sender-anchor') {
        scrollToAnchor('#sender_block');
        $('#sender_block').click();
    }

    // Edición de remitente
    jQuery('#SendersDataTable').on('click', '.edit', function () {
        document.getElementById('SendersEditButton').disabled = false;
        document.getElementById('SendersSaveButton').disabled = true;

        var table = $('#SendersDataTable').DataTable();
        var row = table.row($(this).parents('tr')[0]);

        let correos_code = parseInt(table.row(row).data().correos_code);
        let cex_code = parseInt(table.row(row).data().cex_code);

        document.getElementById('sender_id').value = table.row(row).data().id;
        document.getElementById('sender_name').value = table.row(row).data().sender_name;
        document.getElementById('sender_address').value = table.row(row).data().sender_address;
        document.getElementById('sender_cp').value = table.row(row).data().sender_cp;
        document.getElementById('sender_nif_cif').value = table.row(row).data().sender_nif_cif;
        document.getElementById('sender_city').value = table.row(row).data().sender_city;
        document.getElementById('sender_contact').value = table.row(row).data().sender_contact;
        document.getElementById('sender_phone').value = table.row(row).data().sender_phone;
        document.getElementById('sender_from_time').value = split_time_seconds(table.row(row).data().sender_from_time);
        document.getElementById('sender_to_time').value = split_time_seconds(table.row(row).data().sender_to_time);
        document.getElementById('sender_iso_code_pais').value = table.row(row).data().sender_iso_code_pais;
        document.getElementById('sender_email').value = table.row(row).data().sender_email;
        document.getElementById('correos_code').value = correos_code != 0 ? correos_code : '';
        document.getElementById('cex_code').value = cex_code != 0 ? cex_code : '';
    });

    // Guarda remitente por defecto
    $('#SendersDataTable').on('click', '.correosSenderDefault', function () {
        let data_sender_default_request = {
            action: 'CorreosSenderSaveDefaultForm',
            sender_default_id: $(this).data('id'),
        };

        jQuery.post(AdminCorreosOficialSendersProcess, data_sender_default_request, function (response) {
            showModalInfoWindow(senderDefaultSaved);
            $('#SendersDataTable').DataTable().ajax.reload();
        });
    });

    //Limpia formulario
    $('#SendersCleanButton').click(function (event) {
        // Limpiar validaciones
        $('#CorreosSendersForm').validate().resetForm();
        document.getElementById('SendersEditButton').disabled = true;
        document.getElementById('SendersSaveButton').disabled = false;
    });
});
