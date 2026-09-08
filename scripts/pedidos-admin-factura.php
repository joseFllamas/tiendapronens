<?php

/**
 * @file
 * Añade la columna y el filtro de factura a la lista de pedidos.
 *
 * Uso: `ddev drush php:script scripts/pedidos-admin-factura.php`.
 *
 * Se ejecuta DESPUÉS de scripts/pedidos-admin-envio.php, porque coloca la
 * columna detrás de la de expedición.
 *
 * El problema, con palabras del cliente: «la logística se encalla en la
 * descarga de factura». Había que entrar en cada pedido, abrir su pestaña de
 * facturas, bajar el PDF y volver, y no quedaba constancia de cuáles ya estaban
 * hechas, así que se repetían y se olvidaban.
 *
 * Lo que deja montado:
 *
 * - Columna «Factura»: botón de descarga directo (una fila por factura, aunque
 *   aquí se emite una por pedido) y debajo «Sin descargar» o «Descargada 8
 *   sep», con el número de veces si se bajó más de una.
 * - Filtro expuesto «Factura», con «Sin descargar» como lista de trabajo.
 *
 * El indicador se apunta en el registro de actividad del pedido
 * (`commerce_log`) cuando la descarga SALE de verdad, no cuando se pinta el
 * botón, y distingue quién la bajó: la descarga que hace el cliente desde su
 * área queda anotada pero no marca la factura como hecha. Ver
 * RegistroDescargas y DescargaFacturaSubscriber.
 *
 * La vista es CONFIGURACIÓN: lo que escribe este script viaja en config/sync.
 * Los registros de descarga son CONTENIDO y se van creando con el uso; no hay
 * nada que migrar, un pedido sin registro sale como «Sin descargar», que es lo
 * correcto para el histórico.
 *
 * Idempotente: se puede relanzar.
 */

declare(strict_types=1);

use Drupal\views\Entity\View;

$vista = View::load('commerce_orders');
if ($vista === NULL) {
  echo "No existe la vista commerce_orders.\n";
  return;
}

$display = $vista->getDisplay('default');
$opciones = $display['display_options'];

/**
 * Inserta entradas detrás de una clave, o al final si no está.
 *
 * @param array<string, mixed> $original
 *   Array de partida.
 * @param string $despuesDe
 *   Clave de referencia.
 * @param array<string, mixed> $nuevas
 *   Entradas a insertar.
 *
 * @return array<string, mixed>
 *   El array recompuesto.
 */
$insertarDetrasDe = static function (array $original, string $despuesDe, array $nuevas): array {
  foreach (array_keys($nuevas) as $clave) {
    unset($original[$clave]);
  }
  if (!array_key_exists($despuesDe, $original)) {
    return $original + $nuevas;
  }
  $resultado = [];
  foreach ($original as $clave => $valor) {
    $resultado[$clave] = $valor;
    if ($clave === $despuesDe) {
      foreach ($nuevas as $claveNueva => $valorNuevo) {
        $resultado[$claveNueva] = $valorNuevo;
      }
    }
  }

  return $resultado;
};

// ---------------------------------------------------------------------------
// Campo.
// ---------------------------------------------------------------------------
$campo = [
  'pronens_factura_pedido' => [
    'id' => 'pronens_factura_pedido',
    'table' => 'commerce_order',
    'field' => 'pronens_factura_pedido',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'pronens_factura_pedido',
    'label' => 'Factura',
    'exclude' => FALSE,
    'alter' => [
      'alter_text' => FALSE,
      'text' => '',
      'make_link' => FALSE,
      'path' => '',
      'absolute' => FALSE,
      'external' => FALSE,
      'replace_spaces' => FALSE,
      'path_case' => 'none',
      'trim_whitespace' => FALSE,
      'alt' => '',
      'rel' => '',
      'link_class' => '',
      'prefix' => '',
      'suffix' => '',
      'target' => '',
      'nl2br' => FALSE,
      'max_length' => 0,
      'word_boundary' => TRUE,
      'ellipsis' => TRUE,
      'more_link' => FALSE,
      'more_link_text' => '',
      'more_link_path' => '',
      'strip_tags' => FALSE,
      'trim' => FALSE,
      'preserve_tags' => '',
      'html' => FALSE,
    ],
    'element_type' => '',
    'element_class' => '',
    'element_label_type' => '',
    'element_label_class' => '',
    'element_label_colon' => FALSE,
    'element_wrapper_type' => '',
    'element_wrapper_class' => '',
    'element_default_classes' => TRUE,
    'empty' => '',
    // Un pedido sin factura deja la celda vacía y no hay nada que anunciar.
    'hide_empty' => TRUE,
    'empty_zero' => FALSE,
    'hide_alter_empty' => TRUE,
  ],
];

// Detrás de la expedición: el orden de la fila sigue el del trabajo, primero
// preparar y expedir y luego la factura que va en la caja.
$opciones['fields'] = $insertarDetrasDe($opciones['fields'], 'pronens_expedicion_pedido', $campo);

// ---------------------------------------------------------------------------
// Filtro expuesto.
// ---------------------------------------------------------------------------
$filtro = [
  'pronens_factura_descargada' => [
    'id' => 'pronens_factura_descargada',
    'table' => 'commerce_order',
    'field' => 'pronens_factura_descargada',
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'plugin_id' => 'pronens_factura_descargada',
    'operator' => 'in',
    'value' => [],
    'group' => 1,
    'exposed' => TRUE,
    'expose' => [
      'operator_id' => 'pronens_factura_descargada_op',
      'label' => 'Factura',
      'description' => '',
      'use_operator' => FALSE,
      'operator' => 'pronens_factura_descargada_op',
      'operator_limit_selection' => FALSE,
      'identifier' => 'factura',
      'required' => FALSE,
      'remember' => FALSE,
      'multiple' => FALSE,
      'remember_roles' => ['authenticated' => 'authenticated'],
      'reduce' => FALSE,
    ],
    'is_grouped' => FALSE,
    'group_info' => [
      'label' => '',
      'description' => '',
      'identifier' => '',
      'optional' => TRUE,
      'widget' => 'select',
      'multiple' => FALSE,
      'remember' => FALSE,
      'default_group' => 'All',
      'default_group_multiple' => [],
      'group_items' => [],
    ],
  ],
];

$opciones['filters'] = $insertarDetrasDe($opciones['filters'], 'pronens_situacion_envio', $filtro);

// ---------------------------------------------------------------------------
// Columna de la tabla.
// ---------------------------------------------------------------------------
$opciones['style']['options']['columns'] = $insertarDetrasDe(
  $opciones['style']['options']['columns'],
  'pronens_expedicion_pedido',
  ['pronens_factura_pedido' => 'pronens_factura_pedido'],
);

$opciones['style']['options']['info'] = $insertarDetrasDe(
  $opciones['style']['options']['info'],
  'pronens_expedicion_pedido',
  [
    'pronens_factura_pedido' => [
      // El valor se calcula al pintar: no hay columna por la que ordenar. Para
      // agrupar el trabajo está el filtro.
      'sortable' => FALSE,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => FALSE,
      'responsive' => '',
    ],
  ],
);

$display['display_options'] = $opciones;
$displays = $vista->get('display');
$displays['default'] = $display;
$vista->set('display', $displays);
$vista->save();

// ---------------------------------------------------------------------------
// Etiquetas por idioma.
//
// La vista tiene override de configuración en los cinco idiomas, así que el
// valor base no se ve nunca (ver scripts/pedidos-admin-etiquetas.php).
// ---------------------------------------------------------------------------
$etiquetas = [
  'es' => 'Factura',
  'ca' => 'Factura',
  'en' => 'Invoice',
  'fr' => 'Facture',
  'it' => 'Fattura',
];

$gestorIdiomas = \Drupal::languageManager();
$disponibles = array_keys($gestorIdiomas->getLanguages());
foreach ($etiquetas as $idioma => $etiqueta) {
  if (!in_array($idioma, $disponibles, TRUE)) {
    continue;
  }
  $override = $gestorIdiomas->getLanguageConfigOverride($idioma, 'views.view.commerce_orders');
  $override->set('display.default.display_options.fields.pronens_factura_pedido.label', $etiqueta);
  $override->set('display.default.display_options.filters.pronens_factura_descargada.expose.label', $etiqueta);
  $override->save();
  echo "$idioma: $etiqueta\n";
}

echo "\nVista commerce_orders actualizada.\n";
echo "  Campos:  " . implode(', ', array_keys($opciones['fields'])) . "\n";
echo "  Filtros: " . implode(', ', array_keys($opciones['filters'])) . "\n";
