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
jQuery(document).ready(init);
function init() {
    clickNotification();
}

function clickNotification() {
    jQuery('.correosNotificationItem').on('click', function () {
        jQuery('.inView').removeClass('inView');
        jQuery(this).addClass('inView');
        inView();
    });
}

function inView() {
    if (jQuery('.correosNotificationItem.inView').lenght == 0) {
        return false;
    }

    const inView = jQuery('.correosNotificationItem.inView');

    const inViewId = inView.attr('data-id');
    const inViewText = jQuery('.correosNotificationItem.inView').text();

    const html = getComponentInView(inViewId, inViewText);
    jQuery('.correosNotificationsRight').html(html);
    jQuery('.correosNotificationsRight').css({
        'box-shadow': '0 4px 2px #CBCBCB',
        'border-radius': '8px',
        border: '1px solid #CBCBCB',
    });
    processChceck();
}

function getComponentInView(id, text) {
    return `
        <form id="notificationForm" method="post">
            <input type="hidden" name="notificationId" value="${id}">
            <input type="hidden" name="gdpr_nonce" value="${notificationsVar.gdpr_nonce}">
            <div class="notificationtext">
                ${text}
            </div>
            <div class="notificationCheck">
                <div id="notificationsSendForm" class="notificationsSendForm"></div>
                ${notificationsVar.correos_inView_check}
            </div>
        </form>
    `.trim();
}

function processChceck() {
    jQuery('#notificationsSendForm').on('click', function () {
        jQuery(this).addClass('clicked');
        jQuery('body, html').css('overflow', 'hidden');
        jQuery('.notificationLoader').removeClass('d-none');
        jQuery('#notificationForm').submit();
    });
}
