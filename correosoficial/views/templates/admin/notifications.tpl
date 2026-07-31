{**
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
 *}
{include file='./header.tpl'}
<h2 class="correosNotificationsTitle pt-4">{l s='Messages center' mod='correosoficial'}</h2>
{if $notifications}
    <div class="correosNotificationsContent">
        <div class="correosNotificationsLeft">
            {foreach from=$notifications item=item}
                <div class="correosNotificationItem" data-id="{$item->notificationId}">
                    <p>{$item->notificationText}</p>
                </div>
            {/foreach}
        </div>
        <div class="correosNotificationsRight">
            {l s='Click on any notification to see it in this space in more detail' mod='correosoficial'}
        </div>
    </div>
    <div class="notificationLoader d-none">
        <img src="{$co_base_dir}/views/img/ajax-loader.gif" alt="loader">
    </div>
{else}
    <div class="correosNotificationsError">
        {$noNotifications}
    </div>
{/if}