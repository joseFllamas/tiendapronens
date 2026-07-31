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
jQuery(document).ready(function (e) {
    let cloneId = 1;
    let wrsq = 'woocommerce_request_shipping_quote_';
    let rule_num_field = '';

    jQuery(document).on('click', '#addRule', function (event) {
        jQuery('#btn-ok').prop('disabled', true);

        cloneId = jQuery('tr[id^="ruleSet"]:last td:nth-child(1) > input:nth-child(2)').val();
        cloneId = parseInt(cloneId, 10) + 1;

        let instance_id = jQuery('tr[id^="ruleSet"]:last td input').val();

        let value_id = wrsq + instance_id + '[' + cloneId + '][value]';
        let rule_num_id = wrsq + instance_id + '[' + cloneId + '][rule_num]';
        let classes_id = wrsq + instance_id + '[' + cloneId + '][classes]';
        let conditions_id = wrsq + instance_id + '[' + cloneId + '][conditions]';
        let from_id = wrsq + instance_id + '[' + cloneId + '][from]';
        let to_id = wrsq + instance_id + '[' + cloneId + '][to]';
        let cost_id = wrsq + instance_id + '[' + cloneId + '][cost]';

        let tr = jQuery('tr[id^="ruleSet"]:last');
        let cloned = tr.clone().prop('id', 'ruleSet' + cloneId);
        let num = 0;

        tr.after(cloned);

        if (cloneId == 2) {
            cloned.find("input[name='" + wrsq + instance_id + "[1][value]']").prop('name', value_id);
            rule_num_field = cloned.find("input[name='" + wrsq + instance_id + "[1][rule_num]']").prop('name', rule_num_id);

            cloned.find("select[name='" + wrsq + instance_id + "[1][classes]']").prop('name', classes_id);
            cloned.find("select[name='" + wrsq + instance_id + "[1][conditions]']").prop('name', conditions_id);
            cloned.find("input[name='" + wrsq + instance_id + "[1][from]']").prop('name', from_id);
            cloned.find("input[name='" + wrsq + instance_id + "[1][to]']").prop('name', to_id);
            cloned.find("input[name='" + wrsq + instance_id + "[1][cost]']").prop('name', cost_id);
        } else {
            num = cloneId - 1;
            cloned.find("input[name='" + wrsq + instance_id + '[' + num + "][value]']").prop('name', value_id);
            rule_num_field = cloned.find("input[name='" + wrsq + instance_id + '[' + num + "][rule_num]']").prop('name', rule_num_id);

            cloned.find("select[name='" + wrsq + instance_id + '[' + num + "][classes]']").prop('name', classes_id);
            cloned.find("select[name='" + wrsq + instance_id + '[' + num + "][conditions]']").prop('name', conditions_id);
            cloned.find("input[name='" + wrsq + instance_id + '[' + num + "][from]']").prop('name', from_id);
            cloned.find("input[name='" + wrsq + instance_id + '[' + num + "][to]']").prop('name', to_id);
            cloned.find("input[name='" + wrsq + instance_id + '[' + num + "][cost]']").prop('name', cost_id);

            cloned.find("input[name='" + from_id + "']").val('0.00');
            cloned.find("input[name='" + to_id + "']").val('0.00');
            cloned.find("input[name='" + cost_id + "']").val('0.00');
        }

        rule_num_field.val(cloneId);
    });

    jQuery(document).on('click', '#deleteRule', function (event) {
        let totalInputs = 0;
        let checkedInputs = 0;
        let noErasableItem = false;

        jQuery('input:checkbox:checked').each(function () {
            checkedInputs++;
        });

        jQuery('input:checkbox').each(function () {
            totalInputs++;
        });

        jQuery('input:checkbox:checked').each(function () {
            if (totalInputs == checkedInputs) {
                noErasableItem = true;
            } else if (totalInputs > checkedInputs) {
                jQuery(this).parent().parent().remove();
            }
        });

        if (checkedInputs == 0) {
            jQuery('.mustselectrecord').fadeIn(1000);
            jQuery('.mustselectrecord').fadeOut(5000);
        }
        if (noErasableItem) {
            jQuery('.leaveatleastonerecord').fadeIn(1000);
            jQuery('.leaveatleastonerecord').fadeOut(5000);
        }
    });
});
