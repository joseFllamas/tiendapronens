<?php
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

namespace CorreosOficial\Controllers\Admin;

if (!defined('WC_VERSION')) {
	die;
}



use CorreosOficial\Classes\CorreosOficialUtils;
use CorreosOficial\Models\CorreosOficialConfig;
use CorreosOficial\Classes\CorreosOficialNormalization;
use CorreosOficial\Classes\CorreosOficialCrypto;
use CorreosOficial\Classes\Analitica;
use LogicException;

class AdminCorreosOficialUserConfigurationProcessController {

	const MAX_LOGO_SIZE = 200000;

	public function __construct() {
		global $wpdb;
		try {
			$betatester = false;
			if (isset($_POST['dispatcher']['betatester']) && $_POST['dispatcher']['betatester'] === 'on') {
				$betatester = true;

				$wpdb->update(
					$wpdb->prefix . 'correos_oficial_configuration',
					array( 'value' => 1 ),
					array( 'name' => 'betatester', 'type' => 'analitica' )
				);
			} else {
				$wpdb->update(
					$wpdb->prefix . 'correos_oficial_configuration',
					array( 'value' => 0 ),
					array( 'name' => 'betatester', 'type' => 'analitica' )
				);
			}

			$file = isset($_FILES['dispatcher']) ? CorreosOficialUtils::sanitize($_FILES['dispatcher']) : array(); // phpcs:ignoreFile
			$fileSize = isset($file['size']['UploadLogoLabels']) ? intval($file['size']['UploadLogoLabels']) : '';

			if ($fileSize > self::MAX_LOGO_SIZE) {
				throw new LogicException(__('Image too big', 'correosoficial'));
			}

			if (!empty($file)) {
				$file_name = $file['name']['UploadLogoLabels'];
			}

			// Obtenemos campos de los formularios
			$DefaultPackages = CorreosOficialNormalization::normalizeData('DefaultPackages');
			$CashOnDeliveryMethod = CorreosOficialNormalization::normalizeData('CashOnDeliveryMethod');
			$DefaultLabel = CorreosOficialNormalization::normalizeData('DefaultLabel');
			$CorreosModify = CorreosOficialNormalization::normalizeData('CorreosModify');

			if (substr(CorreosOficialNormalization::normalizeData('BankAccNumberAndIBAN'), 0, 4) == '****') {
				$BankAccNumberAndIBAN = CorreosOficialNormalization::normalizeData('BankAccNumberAndIBAN_hidden','nospaces');
			} else {
				$BankAccNumberAndIBAN = CorreosOficialNormalization::normalizeData('BankAccNumberAndIBAN','nospaces');
			}

			$ActivateTrackingLink = CorreosOficialNormalization::normalizeData('ActivateTrackingLink');
			$ActivateWeightByDefault = CorreosOficialNormalization::normalizeData('ActivateWeightByDefault');
			$WeightByDefault = CorreosOficialNormalization::normalizeData('WeightByDefault');
			$ActivateDimensionsByDefault = CorreosOficialNormalization::normalizeData('ActivateDimensionsByDefault');
			$DimensionsByDefaultHeight = CorreosOficialNormalization::normalizeData('DimensionsByDefaultHeight');
			$DimensionsByDefaultWidth = CorreosOficialNormalization::normalizeData('DimensionsByDefaultWidth');
			$DimensionsByDefaultLarge = CorreosOficialNormalization::normalizeData('DimensionsByDefaultLarge');
			$AgreeToAlterReferences = CorreosOficialNormalization::normalizeData('AgreeToAlterReferences');
			$ShowLabelData = CorreosOficialNormalization::normalizeData('ShowLabelData');
			$CustomerAlternativeText = CorreosOficialNormalization::normalizeData('CustomerAlternativeText');
			$LabelAlternativeText = CorreosOficialNormalization::normalizeData('LabelAlternativeText');
			$GoogleMapsApi = CorreosOficialNormalization::normalizeData('GoogleMapsApi', 'no_uppercase');
			$ChangeLogoOnLabel = CorreosOficialNormalization::normalizeData('ChangeLogoOnLabel');
			$FormSwitchLanguage = CorreosOficialNormalization::normalizeData('FormSwitchLanguage');
			$LabelObservations = CorreosOficialNormalization::normalizeData('LabelObservations');
			$SSLAlternative = CorreosOficialNormalization::normalizeData('SSLAlternative');
			$ShowShippingStatusProcess = CorreosOficialNormalization::normalizeData('ShowShippingStatusProcess');
			$ShipmentPreregistered = CorreosOficialNormalization::normalizeData('ShipmentPreregistered', 'no_uppercase');
			$ShipmentInProgress = CorreosOficialNormalization::normalizeData('ShipmentInProgress', 'no_uppercase');
			$ShipmentDelivered = CorreosOficialNormalization::normalizeData('ShipmentDelivered', 'no_uppercase');
			$ShipmentCanceled = CorreosOficialNormalization::normalizeData('ShipmentCanceled', 'no_uppercase');
			$ShipmentReturned = CorreosOficialNormalization::normalizeData('ShipmentReturned', 'no_uppercase');
			$CronInterval = CorreosOficialNormalization::normalizeData('CronInterval');
			$ActivateAutomaticTracking = CorreosOficialNormalization::normalizeData('ActivateAutomaticTracking');
			$ActivateNifFieldCheckout = CorreosOficialNormalization::normalizeData('ActivateNifFieldCheckout');
			$NifFieldRadio = CorreosOficialNormalization::normalizeData('NifFieldRadio');
			$NifFieldPersonalizedValue = CorreosOficialNormalization::normalizeData('NifFieldPersonalizedValue', 'no_uppercase');

			if (isset($file) && !empty($file)) {
				$UploadLogoLabels = $file_name;
			}

			// Los metemos en un array
			$fields = array(
				'DefaultPackages' => $DefaultPackages,
				'CashOnDeliveryMethod' => strtolower($CashOnDeliveryMethod),
				'DefaultLabel' => $DefaultLabel,
				'CorreosModify' => $CorreosModify,
				'BankAccNumberAndIBAN' => $BankAccNumberAndIBAN,
				'ActivateTrackingLink' => $ActivateTrackingLink,
				'ActivateWeightByDefault' => $ActivateWeightByDefault,
				'WeightByDefault' => $WeightByDefault,
				'ActivateDimensionsByDefault' => $ActivateDimensionsByDefault,
				'DimensionsByDefaultHeight' => ( $ActivateDimensionsByDefault == 'on' ) ? $DimensionsByDefaultHeight : 0,
				'DimensionsByDefaultWidth' => ( $ActivateDimensionsByDefault == 'on' ) ? $DimensionsByDefaultWidth : 0,
				'DimensionsByDefaultLarge' => ( $ActivateDimensionsByDefault == 'on' ) ? $DimensionsByDefaultLarge : 0,
				'AgreeToAlterReferences' => $AgreeToAlterReferences,
				'ShowLabelData' => $ShowLabelData,
				'CustomerAlternativeText' => $CustomerAlternativeText,
				'LabelAlternativeText' => $LabelAlternativeText,
				'GoogleMapsApi' => $GoogleMapsApi,
				'ChangeLogoOnLabel' => $ChangeLogoOnLabel,
				'FormSwitchLanguage' => $FormSwitchLanguage,
				'LabelObservations' => $LabelObservations,
				'SSLAlternative' => $SSLAlternative,
				'ShowShippingStatusProcess' => $ShowShippingStatusProcess,
				'ShipmentPreregistered' => $ShipmentPreregistered,
				'ShipmentInProgress' => $ShipmentInProgress,
				'ShipmentDelivered' => $ShipmentDelivered,
				'ShipmentCanceled' => $ShipmentCanceled,
				'ShipmentReturned' => $ShipmentReturned,
				'CronInterval' => $CronInterval,
				'ActivateAutomaticTracking' => $ActivateAutomaticTracking,
				'ActivateNifFieldCheckout' => $ActivateNifFieldCheckout,
				'NifFieldRadio' => $NifFieldRadio,
				'NifFieldPersonalizedValue' => $NifFieldPersonalizedValue,
			);

			if ($fields['ShowShippingStatusProcess'] == 'on' && ! wp_next_scheduled('correosoficial_tracking_cron_event')) {
				wp_schedule_event(time(), 'correosoficial_cron', 'correosoficial_tracking_cron_event');
			}

			if (substr(CorreosOficialNormalization::normalizeData('BankAccNumberAndIBAN'), 0, 4) != '****') {
				$fields['BankAccNumberAndIBAN'] = CorreosOficialCrypto::encrypt(CorreosOficialNormalization::normalizeData('BankAccNumberAndIBAN','nospaces'));
			}

			if (isset($file) && !empty($file)) {
				$fields['UploadLogoLabels'] = $UploadLogoLabels;
			}

			$fields['ChangeLogoOnLabel'] = !isset($_REQUEST['ChangeLogoOnLabel']) ? '' : 'on';
			$fields['ActivateWeightByDefault'] = !isset($_REQUEST['ActivateWeightByDefault']) ? '' : 'on';
			$fields['ActivateDimensionsByDefault'] = !isset($_REQUEST['ActivateDimensionsByDefault']) ? '' : 'on';
			$fields['AgreeToAlterReferences'] = !isset($_REQUEST['AgreeToAlterReferences']) ? '' : 'on';
			$fields['ActivateTrackingLink'] = !isset($_REQUEST['ActivateTrackingLink']) ? '' : 'on';
			$fields['CustomerAlternativeText'] = !isset($_REQUEST['CustomerAlternativeText']) ? '' : 'on';
			$fields['SSLAlternative'] = !isset($_REQUEST['SSLAlternative']) ? '' : 'on';
			$fields['ShowShippingStatusProcess'] = !isset($_REQUEST['ShowShippingStatusProcess']) ? '' : 'on';

			$fields['ErrorLogoLabels'] = '';

			$tmpFileName = sanitize_text_field($file['tmp_name']['UploadLogoLabels']);
			$getUserLogo = CorreosOficialConfig::getConfigValue('UploadLogoLabels');

			if ($tmpFileName != '' && wp_is_file_mod_allowed('file_upload') || $getUserLogo == 'default.jpg') {
				$sourcePath = $tmpFileName;
				$result = CorreosOficialNormalization::filterFiles($file['name']['UploadLogoLabels']);

				if (!str_contains($result, 'ERROR:  12010')) {

					$uploadDir = wp_upload_dir();
					$targetDir = $uploadDir['path'];
					$targetFile = $targetDir . '/' . $result;

					if(!move_uploaded_file($sourcePath, $targetFile)) {
						throw new LogicException(__('Could not upload logo. Check permissions of the wordpress upload directory', 'correosoficial'));
					}

					$fields['UploadLogoLabels'] = $uploadDir['url'] . '/' . $result;
				
				} else {
					$fields['ErrorLogoLabels'] = $result;
				}
			} elseif($getUserLogo != '') {
				$fields['UploadLogoLabels'] = $getUserLogo;
			}
			// Guardar configuración del usuario
			foreach ( $fields as $field => $value ) {
				$type = ( $field === 'ActivateNifFieldCheckout' ) ? 'checkbox' : 'text';
				CorreosOficialConfig::save( $field, $value, $type );
			}

			$obj = array(
				'savedLogo' => $fields['UploadLogoLabels']
			);

			( new Analitica() )->configurationCall(false);

			die(json_encode($obj));
		} catch (Exception $e) {
			$obj = array(
				'error' => 'Error',
				'desc' => __('ERROR 12040: Han error has ocurred when submitting data. ' . $e->getMessage(), 'correosoficial'),
			);

			die(json_encode($obj));
		}
	}
}
