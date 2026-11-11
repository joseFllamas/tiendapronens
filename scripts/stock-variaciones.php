<?php

/**
 * @file
 * Columna y filtro de stock en la lista de variaciones (/admin/commerce/variaciones).
 *
 * Uso: `ddev drush php:script scripts/stock-variaciones.php` (después de
 * `drush cr`, para que Views conozca los plugins de pronens_personalizacion).
 *
 * No había ningún sitio donde ver el stock del catálogo ni sacar lo agotado
 * (cliente, 2026-11-11). Se añade a la view que ya existe, no a una nueva:
 * es la lista que se usa para buscar por SKU. La columna y el filtro calculan
 * el stock sumando los movimientos (ver StockSql), no con la tabla de totales
 * de Commerce Stock, que va por detrás hasta que pasa el cron.
 *
 * Solo añade lo que falta, sin rehacer la view: si en producción alguien la ha
 * tocado, se respeta. Idempotente.
 */

declare(strict_types=1);

use Drupal\views\Entity\View;

$view = View::load('pronens_variaciones');
if ($view === NULL) {
  print "No existe la view pronens_variaciones: lanza antes scripts/admin-variaciones.php.\n";
  return;
}

$display = &$view->getDisplay('default');
$opciones = &$display['display_options'];

if (!isset($opciones['fields']['pronens_stock'])) {
  $campo = [
    'id' => 'pronens_stock',
    'table' => 'commerce_product_variation_field_data',
    'field' => 'pronens_stock',
    'plugin_id' => 'pronens_stock',
    'label' => 'Stock',
    'alter' => ['alter_text' => FALSE],
  ];
  // Delante de «Estado», que es donde se mira junto al precio.
  $campos = [];
  foreach ($opciones['fields'] as $id => $definicion) {
    if ($id === 'status') {
      $campos['pronens_stock'] = $campo;
    }
    $campos[$id] = $definicion;
  }
  $campos['pronens_stock'] ??= $campo;
  $opciones['fields'] = $campos;
  $opciones['style']['options']['info']['pronens_stock'] = [
    'sortable' => TRUE,
    'default_sort_order' => 'asc',
  ];
  print "Columna Stock añadida.\n";
}
else {
  print "La columna Stock ya estaba.\n";
}

if (!isset($opciones['filters']['pronens_situacion_stock'])) {
  $filtro = [
    'id' => 'pronens_situacion_stock',
    'table' => 'commerce_product_variation_field_data',
    'field' => 'pronens_situacion_stock',
    'plugin_id' => 'pronens_situacion_stock',
    'operator' => 'in',
    'value' => [],
    'exposed' => TRUE,
    'expose' => [
      'operator_id' => 'pronens_situacion_stock_op',
      'label' => 'Stock',
      'operator' => 'pronens_situacion_stock_op',
      'identifier' => 'stock',
      'required' => FALSE,
      'remember' => FALSE,
      'multiple' => FALSE,
      'reduce' => FALSE,
    ],
  ];
  // Detrás de «Estado», el último de los expuestos.
  $filtros = [];
  foreach ($opciones['filters'] as $id => $definicion) {
    $filtros[$id] = $definicion;
    if ($id === 'status') {
      $filtros['pronens_situacion_stock'] = $filtro;
    }
  }
  $filtros['pronens_situacion_stock'] ??= $filtro;
  $opciones['filters'] = $filtros;
  print "Filtro Stock añadido.\n";
}
else {
  print "El filtro Stock ya estaba.\n";
}

$view->save();
print "View guardada.\n";
