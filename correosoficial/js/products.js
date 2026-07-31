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
    var product_technical_error =
        'Error al guardar productos.\r\n\
     Revise su configuración. En caso de persistir el error\r\n\
     por favor, póngase en contacto con el Soporte Técnico de Correos';

    $('#go_to_customer_data').click(function () {
        scrollToAnchor('#customer_data');
        $('#customer_data').click();
    });

    function scrollToAnchor(aid) {
        var aTag = $(aid);
        $('html,body').animate({ scrollTop: aTag.offset().top }, 'slow');
    }
});
