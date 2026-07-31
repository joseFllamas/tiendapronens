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

/**
 * Constantes de configuración usadas por el plugin Correos Oficial.
 *
 * Sustituye a vendor/ecommerce_common_lib/config.inc.php para el código del plugin.
 * Se usan guardas defined() porque algunos archivos vendor (CexRest, CorreosSoap,
 * CorreosRest) todavía cargan config.inc.php de forma interna.
 */

@ini_set( 'default_socket_timeout', 20 );

// Localizador de Correos.
defined( 'CORREOS_BASE_LOCATION' ) || define( 'CORREOS_BASE_LOCATION', 'https://localizador.correos.es/canonico/eventos_envio_servicio_auth' );

// CEX.
defined( 'CEX_BASE_LOCATION' )       || define( 'CEX_BASE_LOCATION', 'https://www.cexpr.es/wspsc/apiRestSeguimientoEnviosk8s/json/seguimientoEnvio' );
defined( 'CEX_BASE_LOCATION_LISTA' ) || define( 'CEX_BASE_LOCATION_LISTA', 'https://www.cexpr.es/wspsc/apiRestListaEnvios/json/listaEnvios' );
defined( 'CEX_BASE_LABELS' )         || define( 'CEX_BASE_LABELS', 'https://www.cexpr.es/wspsc/apiRestEtiquetaTransporte/json/etiquetaTransporte' );
defined( 'CEX_GRABAR_ENVIO' )        || define( 'CEX_GRABAR_ENVIO', 'https://www.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio' );
defined( 'CEX_GRABAR_RECOGIDA' )     || define( 'CEX_GRABAR_RECOGIDA', 'https://www.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/grabarRecogida' );
defined( 'CEX_ANULAR_RECOGIDA' )     || define( 'CEX_ANULAR_RECOGIDA', 'https://www.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida' );
defined( 'CEX_CONSULTAR_RECOGIDA' )  || define( 'CEX_CONSULTAR_RECOGIDA', 'https://www.cexpr.es/wspsc/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida' );

// CEX Pre.
defined( 'CEX_BASE_LOCATION_LISTA_PRE' ) || define( 'CEX_BASE_LOCATION_LISTA_PRE', 'https://www.test.cexpr.es/wspsc/apiRestListaEnvios/json/listaEnvios' );
defined( 'CEX_GRABAR_ENVIO_PRE' )        || define( 'CEX_GRABAR_ENVIO_PRE', 'https://www.test.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio' );
defined( 'CEX_ANULAR_RECOGIDA_PRE' )     || define( 'CEX_ANULAR_RECOGIDA_PRE', 'https://www.test.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida' );
defined( 'CEX_GRABAR_RECOGIDA_PRE' )     || define( 'CEX_GRABAR_RECOGIDA_PRE', 'https://www.test.cexpr.es/wsps/apiRestGrabacionRecogidaEnviok8s/json/grabarRecogida' );
defined( 'CEX_CONSULTAR_RECOGIDA_PRE' )  || define( 'CEX_CONSULTAR_RECOGIDA_PRE', 'https://www.test.cexpr.es/wsps/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida' );

// Tipos Etiquetas.
defined( 'LABEL_TYPE_ADHESIVE' ) || define( 'LABEL_TYPE_ADHESIVE', 0 );
defined( 'LABEL_TYPE_HALF' )     || define( 'LABEL_TYPE_HALF', 1 );
defined( 'LABEL_TYPE_THERMAL' )  || define( 'LABEL_TYPE_THERMAL', 2 );

// Formatos Etiquetas.
defined( 'LABEL_FORMAT_STANDAR' ) || define( 'LABEL_FORMAT_STANDAR', 0 );
defined( 'LABEL_FORMAT_3A4' )     || define( 'LABEL_FORMAT_3A4', 1 );
defined( 'LABEL_FORMAT_4A4' )     || define( 'LABEL_FORMAT_4A4', 2 );

// Formatos CEX.
defined( 'CEX_LABEL_THERMAL_ADHESIVE' ) || define( 'CEX_LABEL_THERMAL_ADHESIVE', 1 );
defined( 'CEX_LABEL_3A4' )              || define( 'CEX_LABEL_3A4', 3 );

// Mensajes.
defined( 'CO_TIMEOUT_MSG' ) || define( 'CO_TIMEOUT_MSG', 'El tiempo de espera se ha agotado' );

// Flag global de depuración de webservices.
if ( ! isset( $GLOBALS['co_debugCorreosOficial'] ) ) {
	$GLOBALS['co_debugCorreosOficial'] = false;
}
