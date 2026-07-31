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
<div class="clear"></div>
<div class="notificationContent">
    <div class="correosImg">
        <img src="{$img}" alt="correos">
    </div>
    <div class="notificationsMsgs">
        {$msg1}
        <span class="notificationsCount">{$notifications}</span>
        {$msg2}
    </div>
    <div class="notificationsButton">
        <a href="{$link}">
            <button class="btn btn-primary co_primary_button">{$msgButton}</button>
        </a>
    </div>
</div>