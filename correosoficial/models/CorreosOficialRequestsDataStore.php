<?php
namespace CorreosOficial\Models;

use CorreosOficial\Classes\CorreosOficialHelpers;
use WC_Data_Store_WP;

defined('ABSPATH') || exit;

class CorreosOficialRequestsDataStore extends WC_Data_Store_WP {

	private $table;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'correos_oficial_requests';
	}

	public function create( &$request ) {
		$data = $request->get_data();
		unset($data['id']);
		unset($data['meta_data']);
		
		$this->wpdb->insert(
			$this->table,
			$data,
			array_fill(0, count($data), '%s')
		);
		$request->set_id_order($data['id_order']);
	}

	public function read( &$request ) {
		$id_order = $request->get_id_order();
		if (! $id_order) {
			return;
		}

		$data = $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . esc_sql($this->table) . ' WHERE id_order = %d', $id_order), ARRAY_A); //phpcs:ignore

		if ($data) {
			$request->set_props($data);
		}
	}

	public function update( &$request ) {
		$id_order = $request->get_id_order();
		if (! $id_order) {
			return;
		}

		$data = $request->get_data();
		unset($data['id']);
		unset($data['meta_data']);
		$this->wpdb->update(
			$this->table,
			$data,
			array( 'id_order' => $id_order ),
			array_fill(0, count($data), '%s'),
			array( '%d' )
		);
	}

	public function getCartHashFromWooTable( $order_id ) {

		if (! $order_id) {
			return;
		}

		$table = $this->wpdb->prefix . 'wc_order_operational_data';
			$cart_hash = $this->wpdb->get_var( $this->wpdb->prepare(
				"SELECT cart_hash FROM {$table} WHERE order_id = %d",
				$order_id
			) );

		return $cart_hash;
	}

	public function deleteByOrderID(&$request) {
		$data = $request->get_data();
		$id_order = $data['id_order'];

		unset($data['id']);
		unset($data['meta_data']);
		return $this->wpdb->delete(
			$this->table,
			array('id_order' => $id_order),
			array('%s')
		);
	}

	public function getRequestByIdOrder( $id_order ) {
		if ( empty( $id_order ) ) {
			return null;
		}

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE id_order = %s",
			$id_order
		);

		return $this->wpdb->get_row( $query, ARRAY_A );
	}

	public function getRequestData($id_order, $product_type) {

		// Si el tipo de producto no es citypaq ni office, retornamos un array vacío
		if ($product_type != 'citypaq' && $product_type != 'office' && $product_type != 'pudocex') {
            return [];
        }

		// Realizamos la consulta a la base de datos para obtener los datos del request
		$result = $this->getRequestByIdOrder($id_order);
		
		if ($result){

            // SOLUCION TEMPORAL.
			$raw = $result['data'];

            // 2. Reemplazar barras invertidas simples por dobles
            $raw = str_replace('\\', '\\\\', $raw);

            // 3. Intentar decodificar
            $data = json_decode($raw, true);

			// Si los datos ya están en formato normalizado (con campo 'data' que contiene datos crudos),
			// extraer los datos crudos para pasarlos a normalize()
			$raw_data = isset($data['data']) ? $data['data'] : $data;

			// Normalizamos los datos según el tipo de producto
			switch ($product_type) {
				case 'citypaq':
					return $this->normalize($raw_data);
				case 'office':
					return $this->normalize($raw_data);
				case 'pudocex':
                    return $this->normalize($raw_data);
			}
		}

		return false;
	}
	
	public function normalize($item) {

        $helpers = new CorreosOficialHelpers();

		// Citypaqs Correos (PS2C y P3)
        if (
            array_key_exists('terminalId', $item) ||
            array_key_exists('cod_homepaq', $item)
        ){

            // Construir la dirección según el formato de datos recibido
            $address = '';
            
            // Formato API P3 (location con campos separados)
            if ($helpers::getOneValue($item, 'location')) {
                $address_parts = [];
                
                // Concatenar tipo de vía y nombre
                if (!empty($item['roadType'])) {
                    $address_parts[] = $item['roadType'];
                }
                if (!empty($item['addressName'])) {
                    $address_parts[] = $item['addressName'];
                }
                
                // Añadir número si existe
                if (!empty($item['addressNumber']) && $item['addressNumber'] !== 'S/N') {
                    $address_parts[] = $item['addressNumber'];
                } elseif (!empty($item['addressNumber']) && $item['addressNumber'] === 'S/N') {
                    $address_parts[] = 'S/N';
                }
                
                // Construir dirección base
                $address = implode(' ', $address_parts);
                
                // Añadir detalles adicionales si existen (portal, bloque, escalera)
                $additional_parts = [];
                if (!empty($item['portalNumber'])) {
                    $additional_parts[] = 'Portal ' . $item['portalNumber'];
                }
                if (!empty($item['blockNumber'])) {
                    $additional_parts[] = 'Bloque ' . $item['blockNumber'];
                }
                if (!empty($item['stairNumber'])) {
                    $additional_parts[] = 'Escalera ' . $item['stairNumber'];
                }
                
                if (!empty($additional_parts)) {
                    $address .= ', ' . implode(', ', $additional_parts);
                }
            } 
            // Formato API Legacy
            else {
                $address = trim("{$item['des_via']} {$item['direccion']}, {$item['numero']}");
            }

            return array(
                'reference'  => $helpers::getOneValue($item, 'terminalId', 'cod_homepaq'),
                'name'       => $helpers::getOneValue($item, 'alias'),
                'address'    => trim($address),
                'zipcode'    => $helpers::getOneValue($item, 'postalCode', 'cod_postal'),
                'city'       => $helpers::getOneValue($item, 'municipality', 'desc_localidad'),
                'scheduleLV' => '',
                'scheduleS'  => '',
                'scheduleF'  => '',
                'schedule'   => $helpers::getOneValue($item, 'fullSchedule', 'ind_horario'), // ind_horario es un flag comprobar
                'lat'        => $helpers::getOneValue($item, 'latitudeWGS84', 'latitudWGS84'),
                'long'       => $helpers::getOneValue($item, 'longitudeWGS84', 'longitudWGS84'),
                'type'       => 'citypaq',
                'data'       => $item,
            );

		// Oficinas Correos (PS2C y P3)
        } elseif (array_key_exists('unitCode', $item) || array_key_exists('unidad', $item)){

            return array(
                'reference'  => $helpers::getOneValue($item, 'unitCode', 'unidad'),
                'name'       => $helpers::getOneValue($item, 'unitName', 'nombre'),
                'address'    => $helpers::getOneValue($item, 'address', 'direccion'),
                'zipcode'    => $helpers::getOneValue($item, 'postalCode', 'cp'),
                'city'       => $helpers::getOneValue($item, 'municipalityName', 'descLocalidad'),
                'phone'      => $helpers::getOneValue($item, 'phoneNumber', 'telefono'),
                'scheduleLV' => $helpers::getOneValue($item, 'mondaySchedules', 'horarioLV'),
                'scheduleS'  => $helpers::getOneValue($item, 'saturdaySchedule', 'horarioS'),
                'scheduleF'  => $helpers::getOneValue($item, 'holydaySchedule', 'horarioF'),
                'schedule'   => '',
                'lat'        => $helpers::getOneValue($item, 'coorLatWGS84', 'latitudWGS84'),
                'long'       => $helpers::getOneValue($item, 'coorLonWGS84', 'longitudWGS84'),
                'type'       => 'office',
                'data'       => $item,
            );
		} elseif (
            array_key_exists('codigoOficina', $item)
        ){
			$reference =  $item['codigoOficina'];
            $name      =  $item['nombreOficina'];
            $address   =  $item['direccionOficina'];
            $zipcode   =  $item['codigoPostalOficina'];
            $city      =  $item['poblacionOficina'];
            $phone     =  $item['telefonoOficina'];

			$horarioOficina       = explode('/', $item['horarioOficina']);
            $horarioOficinaVerano = explode('/', $item['horarioOficinaVerano']);
            $geoposicionOficina   = explode(',', $item["geoposicionOficina"]);

			 // DIARIO
            $scheduleLV = array();
            if (isset($horarioOficina[0]) ){
                $scheduleLV[] = $horarioOficina[0];
            }
            if (isset($horarioOficinaVerano[0])) {
                $scheduleLV[] = 'Verano: ' . $horarioOficinaVerano[0];
            }

			$scheduleLV = implode(', ', $scheduleLV);
            $scheduleLV = str_replace('L-V:', '', $scheduleLV);

			// SÁBADOS
            $scheduleS = array();
            if(isset($horarioOficina[1])){
                $scheduleS[] = $horarioOficina[1];
            }
            if(isset($horarioOficinaVerano[1])){
                $scheduleS[] = 'Verano: ' . $horarioOficinaVerano[1];
            }
            $scheduleS = implode(', ', $scheduleS);
            $scheduleS = str_replace('S:', '', $scheduleS);

            // FESTIVOS
            $scheduleF = array();
            if(isset($horarioOficina[2])){
                $scheduleF[] = $horarioOficina[2];
            }
            if(isset($horarioOficinaVerano[2])){
                $scheduleF[] = 'Verano: ' . $horarioOficinaVerano[2];
            }
            $scheduleF = implode(', ', $scheduleF);
            $scheduleF = str_replace('Festivos:', '', $scheduleF);

			// COORDENADAS
            $lat        = $geoposicionOficina[0];
            $long       = $geoposicionOficina[1];

            return array(
                'reference'  => $reference ,
                'name'       => $name,
                'address'    => $address,
                'zipcode'    => $zipcode,
                'city'       => $city,
                'phone'      => $phone,
                'scheduleLV' => $scheduleLV,
                'scheduleS'  => $scheduleS,
                'scheduleF'  => $scheduleF,
                'schedule'   => '',
                'lat'        => $lat,
                'long'       => $long,
                'type'       => 'office',
                'data'       => $item,
            );
		// PUDOS CEX
        } elseif (
            array_key_exists('idPtoExterno', $item)
        ){

            // HORARIOS
            $scheduleLV = "";
            $scheduleS  = "";
            $scheduleF  = "";

            if(isset($item["listaHorariosPtoConv"])){

                $listaHorarios = $item["listaHorariosPtoConv"];

                // Entrega diara, obtenemos el lunes como referencia
                if(isset($listaHorarios[0])){
                    $scheduleLV = $listaHorarios[0]['horario1'] . ', ' .
                                $listaHorarios[0]['horario2'] . ', ' .
                                $listaHorarios[0]['horario3'] . ', ' .
                                $listaHorarios[0]['horario4'];

                    $scheduleLV = str_replace(", , ", "", $scheduleLV);
                }

                // Entrega en sábado
                if(isset($listaHorarios[5])){
                    $scheduleS = $listaHorarios[5]['horario1'] . ', ' .
                                $listaHorarios[5]['horario2'] . ', ' .
                                $listaHorarios[5]['horario3'] . ', ' .
                                $listaHorarios[5]['horario4'];

                    $scheduleS = str_replace(", , ", "", $scheduleS);
                }

                // Entrega en domingos y festivos
                if(isset($listaHorarios[6])){
                    $scheduleF = $listaHorarios[6]['horario1'] . ', ' .
                                $listaHorarios[6]['horario2'] . ', ' .
                                $listaHorarios[6]['horario3'] . ', ' .
                                $listaHorarios[6]['horario4'];

                    $scheduleF = str_replace(", , ", "", $scheduleF);
                }

            }

            return array(
                'reference'  => $item['idPtoExterno'],
                'name'       => $item['nombrePtoConv'],
                'address'    => $item['direccionPtoConv'],
                'zipcode'    => $item['codigoPostalPtoConv'],
                'city'       => $item['ciudadPtoConv'],
                'phone'      => "",
                'scheduleLV' => $scheduleLV,
                'scheduleS'  => $scheduleS,
                'scheduleF'  => $scheduleF,
                'schedule'   => '',
                'lat'        => $item['latitudPtoConv'],
                'long'       => $item['longitudPtoConv'],
                'type'       => 'pudocex',
                'data'       => $item,
            );

        }
        
    }

    public function normalizeLocations($data) {
        if (!is_array($data) || (isset($data['code']) && !isset($data[0]))) {
            return [];
        }
        return array_map(function ( $item ) {
            return $this->normalize($item);
        }, $data);
    }
}
