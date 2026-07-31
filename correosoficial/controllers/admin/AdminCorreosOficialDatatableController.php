<?php
namespace CorreosOficial\Controllers\Admin;

use CorreosOficial\Classes\CorreosOficialUtilitiesDatatable;

class AdminCorreosOficialDatatableController {

	protected $datatableInstance;

	public function __construct() {
		$this->datatableInstance = new CorreosOficialUtilitiesDatatable();
	}

	public function datatableTabManager() {

		check_ajax_referer('dataTableNonce', 'nonce');

		$tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : '';
		$from = isset($_POST['FromDateOrdersReg']) ? gmdate('Y-m-d', strtotime(sanitize_text_field($_POST['FromDateOrdersReg']))) : '';
		$to = isset($_POST['ToDateOrdersReg']) ? gmdate('Y-m-d', strtotime(sanitize_text_field($_POST['ToDateOrdersReg']))) : '';
		
		// Filtros
		$searchByLabelingDate = ( isset($_POST['SearchByLabelingDate']) && $_POST['SearchByLabelingDate'] == 'true' ) ? true : false;
		$searchBySender = sanitize_text_field(isset($_POST['SearchBySender']) ? $_POST['SearchBySender'] : '');
		$pickupPage = isset($_POST['onlyCorreos']) == 'active' ? sanitize_text_field($_POST['onlyCorreos']) : false;
		$printLabelPage = isset($_POST['printLabelPage']) == 'active' ? sanitize_text_field($_POST['printLabelPage']) : false;

		switch ($tab) {
			case 'GestionDataTable':
				$this->datatableInstance->loadGestionDatablePage($from, $to);
				break;
			case 'DocAduaneraDataTable':
				$this->datatableInstance->loadDocAduaneraPage($from, $to);
				break;
			case 'EtiquetasDataTable':
				$this->datatableInstance->loadEtiquetasPage($from, $to, $pickupPage, $printLabelPage);
				break;
			case 'ResumenDataTable':
				$this->datatableInstance->loadResumenPage($from, $to, $searchByLabelingDate, $searchBySender);
				break;
		}
	}
}

$datatableController = new AdminCorreosOficialDatatableController();
add_action('wp_ajax_dataTableAjax', array( $datatableController, 'datatableTabManager' ));
add_action('wp_ajax_nopriv_dataTableAjax', array( $datatableController, 'datatableTabManager' ));
