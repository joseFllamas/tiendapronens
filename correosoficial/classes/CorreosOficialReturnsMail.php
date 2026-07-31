<?php
namespace CorreosOficial\Classes;

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
class CorreosOficialReturnsMail {

	private $label_pdfs = [];
	private $cn23_pdfs = [];

	public function sendEmail($payload) {
		$wc_order = wc_get_order($payload['order_id']);

		if (!$wc_order || empty($payload['customer_email'])) {
			return false;
		}

		$mailer = WC()->mailer();

		$this->prepareTempFolder();

		// Guardar etiquetas (puede ser una o varias)
		if (!empty($payload['label'])) {
			$labels = is_array($payload['label']) ? $payload['label'] : [$payload['label']];
			foreach ($labels as $label_base64) {
				$pdf_path = $this->saveBase64Pdf($label_base64, 'label_');
				if ($pdf_path) {
					$this->label_pdfs[] = $pdf_path;
				}
			}
		}

		// Guardar CN23 (puede ser uno o varios)
		if (!empty($payload['cn23'])) {
			$cn23_list = is_array($payload['cn23']) ? $payload['cn23'] : [$payload['cn23']];
			foreach ($cn23_list as $cn23_base64) {
				$pdf_path = $this->saveBase64Pdf($cn23_base64, 'cn23_');
				if ($pdf_path) {
					$this->cn23_pdfs[] = $pdf_path;
				}
			}
		}

		$to = $payload['customer_email'];
		$subject = '[' . get_bloginfo('name') . '] ' . __('Return package information', 'correosoficial') .
		' - ' . __('Order: ', 'correosoficial') . $payload['order_id'];

		$message = $this->getEmailContent($wc_order, $payload);
		$headers = ['Content-Type: text/html; charset=UTF-8'];

		// Combinar todos los adjuntos
		$attachments = array_merge($this->label_pdfs, $this->cn23_pdfs);

		$sent = $mailer->send($to, $subject, $message, $headers, $attachments);

		// Eliminar archivos temporales
		foreach ($attachments as $file) {
			if (file_exists($file)) {
				unlink($file);
			}
		}

		return $sent;
	}

	private function prepareTempFolder() {
		$upload_dir = wp_upload_dir();
		$temp_folder = $upload_dir['basedir'] . '/correos_oficial_pdftmp';
		if (!file_exists($temp_folder)) {
			mkdir($temp_folder, 0755, true);
		}
	}

	private function saveBase64Pdf($base64, $prefix) {
		$upload_dir = wp_upload_dir();
		$temp_folder = $upload_dir['basedir'] . '/correos_oficial_pdftmp';
		$filepath = $temp_folder . '/' . $prefix . uniqid() . '.pdf';

		if (!is_string($base64) || $base64 === '') {
			return false;
		}

		if (strpos($base64, '%PDF-') === 0) {
			file_put_contents($filepath, $base64);
			return $filepath;
		}

		$normalized_base64 = preg_replace('/^data:application\/pdf;base64,/', '', trim($base64));
		$decoded = base64_decode($normalized_base64, true);

		if ($decoded === false) {
			error_log('[CorreosOficial] No se pudo decodificar PDF adjunto en ReturnsMail');
			return false;
		}

		file_put_contents($filepath, $decoded);
		return $filepath;
	}

	private function getEmailContent($order, $payload) {
		$template_path = plugin_dir_path(__FILE__) . '../emails/es/to_recipient.php';
		if (file_exists($template_path)) {
			// Variables comunes del mensaje
			$recipient_return_hello  = __('Hello', 'correosoficial');
			$recipient_return_thanks = __('Thank you', 'correosoficial');
			$recipient_return_bye    = __('Sincerely', 'correosoficial');
			$recipient_return_footer = get_bloginfo('name');
			$shop_name               = get_bloginfo('name');
			$sender_email            = $payload['sender_email'];
			$company                 = $payload['company'];
			$shipping_number         = $payload['shipping_number'];
			$return_code_cex         = !empty($payload['return_code_cex']) ? $payload['return_code_cex'] : $shipping_number;
			
			// Recipient Return variables Correos
			$recipient_return_doc_cn23 = __('CN23/CP71 Documentation', 'correosoficial');
			$recipient_return_text1    = __('Attached is a label that you can print out and attach to the package. If you prefer, you can write down the package code', 'correosoficial');
			$recipient_return_text2    = __('and provide it at your nearest post office or contact us to arrange collection. If in this email you receive the', 'correosoficial');
			$recipient_return_text3    = __('must accompany the shipment printed and signed by you', 'correosoficial');

			// Recipient Return variables Cex
			$recipient_return_pickup_date = $payload['pickup_date'];
			$recipient_return_pickup_time = $payload['sender_from_time'];
			$recipient_return_shop_name   = get_bloginfo('name');
			$recipient_return_text1_cex   = __('We would like to inform you that the', 'correosoficial');
			$recipient_return_text2_cex   = __('from the', 'correosoficial');
			$recipient_return_text3_cex   = __('Correos Express will proceed to carry out a collection requested by', 'correosoficial');
			$recipient_return_text4_cex   = __('we kindly ask you, in order to avoid any unnecessary delays please have the shipment ready before the driver picks it up. Please find enclosed the label to be printed and attached to the package', 'correosoficial');
			$recipient_return_text5_cex   = __('Once the shipment is made, you can track it using the following code:', 'correosoficial');
			$recipient_return_text6_cex   = __('at', 'correosoficial');
			$recipient_return_text7_cex   = __('Track your shipping - correosexpress.com', 'correosoficial');

			$recipient_return_recommendations     = __('RECOMMENDATIONS', 'correosoficial');
			$recipient_return_recommendation_info = __('In order to ensure that the service is performed correctly and that your shipments are not delayed, we recommend that you', 'correosoficial');
			$recipient_return_recommendation1     = __('Have prepared any documentation accompanying the goods', 'correosoficial');
			$recipient_return_recommendation2     = __('The goods must be perfectly closed and sealed before the indicated collection time', 'correosoficial');
			$recipient_return_recommendation3     = __('On the outside of the box, in a visible place, attach the label included in this mailing', 'correosoficial');

			ob_start();
			include $template_path;
			return ob_get_clean();
		}
		return '<p>Contenido no disponible.</p>';
	}
}

