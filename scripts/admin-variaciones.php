<?php

/**
 * @file
 * El backoffice de productos, buscable por SKU.
 *
 * Dos piezas, pedidas por el cliente (2026-09-01):
 *
 * 1. Una view nueva, "Variaciones de producto" (/admin/commerce/variaciones),
 *    que lista las 1123+ variaciones con su SKU, su producto, su precio y sus
 *    operaciones, filtrable por SKU, título y producto. La administración de
 *    productos de Commerce lista productos, no variaciones, y con productos de
 *    20 variaciones (la mochila de inicial) encontrar un SKU era imposible.
 *
 * 2. Un filtro de SKU en la view de productos (/admin/commerce/products), vía
 *    la relación con las variaciones y con DISTINCT para que un producto con
 *    varias variaciones coincidentes no salga repetido.
 *
 * Idempotente: la view se reemplaza si ya existe y el filtro solo se añade si
 * no está. Uso: ddev drush php:script scripts/admin-variaciones.php
 */

use Drupal\views\Entity\View;

// -----------------------------------------------------------------------
// 1. View de variaciones.
// -----------------------------------------------------------------------

$alter_defaults = ['alter_text' => FALSE];

$definicion = [
  'id' => 'pronens_variaciones',
  'label' => 'Variaciones de producto',
  'module' => 'views',
  'description' => 'Todas las variaciones del catálogo con su SKU, para localizar una referencia concreta.',
  'tag' => 'commerce',
  'base_table' => 'commerce_product_variation_field_data',
  'base_field' => 'variation_id',
  'status' => TRUE,
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Predeterminado',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Variaciones de producto',
        'access' => [
          'type' => 'perm',
          'options' => ['perm' => 'access commerce_product overview'],
        ],
        'cache' => ['type' => 'none', 'options' => []],
        'query' => [
          'type' => 'views_query',
          'options' => ['distinct' => FALSE],
        ],
        'exposed_form' => [
          'type' => 'basic',
          'options' => [
            'submit_button' => 'Filtrar',
            'reset_button' => TRUE,
            'reset_button_label' => 'Restablecer',
          ],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 50, 'offset' => 0],
        ],
        'style' => [
          'type' => 'table',
          'options' => [
            'grouping' => [],
            'row_class' => '',
            'default_row_class' => TRUE,
            'columns' => [],
            'default' => 'changed',
            'info' => [
              'sku' => ['sortable' => TRUE, 'default_sort_order' => 'asc'],
              'title' => ['sortable' => TRUE, 'default_sort_order' => 'asc'],
              'title_1' => ['sortable' => TRUE, 'default_sort_order' => 'asc'],
              'price__number' => ['sortable' => TRUE, 'default_sort_order' => 'asc'],
              'status' => ['sortable' => TRUE, 'default_sort_order' => 'asc'],
              'changed' => ['sortable' => TRUE, 'default_sort_order' => 'desc'],
            ],
            'override' => TRUE,
            'sticky' => TRUE,
            'empty_table' => TRUE,
          ],
        ],
        'row' => ['type' => 'fields'],
        'relationships' => [
          'product_id' => [
            'id' => 'product_id',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'product_id',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'product_id',
            'plugin_id' => 'standard',
            'admin_label' => 'Producto',
            'required' => TRUE,
          ],
        ],
        'fields' => [
          'sku' => [
            'id' => 'sku',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'sku',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'sku',
            'plugin_id' => 'field',
            'label' => 'SKU',
            'alter' => $alter_defaults,
            'click_sort_column' => 'value',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'title' => [
            'id' => 'title',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'title',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'title',
            'plugin_id' => 'field',
            'label' => 'Variación',
            'alter' => $alter_defaults,
            'click_sort_column' => 'value',
            'type' => 'string',
            'settings' => ['link_to_entity' => FALSE],
          ],
          'title_1' => [
            'id' => 'title_1',
            'table' => 'commerce_product_field_data',
            'field' => 'title',
            'relationship' => 'product_id',
            'entity_type' => 'commerce_product',
            'entity_field' => 'title',
            'plugin_id' => 'field',
            'label' => 'Producto',
            'alter' => $alter_defaults,
            'click_sort_column' => 'value',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'price__number' => [
            'id' => 'price__number',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'price__number',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'price',
            'plugin_id' => 'field',
            'label' => 'Precio',
            'alter' => $alter_defaults,
            'click_sort_column' => 'number',
            'type' => 'commerce_price_default',
            'settings' => [
              'strip_trailing_zeroes' => FALSE,
              'currency_display' => 'symbol',
            ],
          ],
          'status' => [
            'id' => 'status',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'status',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'status',
            'plugin_id' => 'field',
            'label' => 'Estado',
            'alter' => $alter_defaults,
            'click_sort_column' => 'value',
            'type' => 'boolean',
            'settings' => [
              'format' => 'custom',
              'format_custom_true' => 'Publicada',
              'format_custom_false' => 'No publicada',
            ],
          ],
          'changed' => [
            'id' => 'changed',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'changed',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'changed',
            'plugin_id' => 'field',
            'label' => 'Actualizada',
            'alter' => $alter_defaults,
            'click_sort_column' => 'value',
            'type' => 'timestamp',
            'settings' => [
              'date_format' => 'short',
              'custom_date_format' => '',
              'timezone' => '',
            ],
          ],
          'operations' => [
            'id' => 'operations',
            'table' => 'commerce_product_variation',
            'field' => 'operations',
            'plugin_id' => 'entity_operations',
            'label' => 'Operaciones',
            'alter' => $alter_defaults,
            'destination' => TRUE,
          ],
        ],
        'filters' => [
          'sku' => [
            'id' => 'sku',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'sku',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'sku',
            'plugin_id' => 'string',
            'operator' => 'contains',
            'value' => '',
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => 'sku_op',
              'label' => 'SKU',
              'operator' => 'sku_op',
              'identifier' => 'sku',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
            ],
          ],
          'title' => [
            'id' => 'title',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'title',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'title',
            'plugin_id' => 'string',
            'operator' => 'contains',
            'value' => '',
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => 'title_op',
              'label' => 'Variación',
              'operator' => 'title_op',
              'identifier' => 'titulo',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
            ],
          ],
          'title_1' => [
            'id' => 'title_1',
            'table' => 'commerce_product_field_data',
            'field' => 'title',
            'relationship' => 'product_id',
            'entity_type' => 'commerce_product',
            'entity_field' => 'title',
            'plugin_id' => 'string',
            'operator' => 'contains',
            'value' => '',
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => 'title_1_op',
              'label' => 'Producto',
              'operator' => 'title_1_op',
              'identifier' => 'producto',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
            ],
          ],
          'status' => [
            'id' => 'status',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'status',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'status',
            'plugin_id' => 'boolean',
            'operator' => '=',
            'value' => 'All',
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => '',
              'label' => 'Estado',
              'operator' => 'status_op',
              'identifier' => 'status',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
            ],
          ],
          // El producto tiene una fila por idioma en commerce_product_field_data
          // y el join las trae las cinco: sin esto cada variación salía 5 veces.
          'default_langcode_producto' => [
            'id' => 'default_langcode_producto',
            'table' => 'commerce_product_field_data',
            'field' => 'default_langcode',
            'relationship' => 'product_id',
            'entity_type' => 'commerce_product',
            'entity_field' => 'default_langcode',
            'plugin_id' => 'boolean',
            'operator' => '=',
            'value' => '1',
            'exposed' => FALSE,
          ],
          // Una fila por variación aunque algún día se traduzcan: la misma
          // regla que "las views tienen que filtrar por idioma" del catálogo,
          // aquí con el idioma original porque el SKU no se traduce.
          'default_langcode' => [
            'id' => 'default_langcode',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'default_langcode',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'default_langcode',
            'plugin_id' => 'boolean',
            'operator' => '=',
            'value' => '1',
            'exposed' => FALSE,
          ],
        ],
        'sorts' => [
          'changed' => [
            'id' => 'changed',
            'table' => 'commerce_product_variation_field_data',
            'field' => 'changed',
            'entity_type' => 'commerce_product_variation',
            'entity_field' => 'changed',
            'plugin_id' => 'date',
            'order' => 'DESC',
            'exposed' => FALSE,
          ],
        ],
        'empty' => [
          'area_text_custom' => [
            'id' => 'area_text_custom',
            'table' => 'views',
            'field' => 'area_text_custom',
            'plugin_id' => 'text_custom',
            'empty' => TRUE,
            'content' => 'No hay variaciones que coincidan con el filtro.',
          ],
        ],
        'display_extenders' => [],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Página',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'admin/commerce/variaciones',
        'menu' => [
          'type' => 'normal',
          'title' => 'Variaciones de producto',
          'description' => 'Buscar una variación concreta por SKU, título o producto.',
          'parent' => 'commerce.admin_commerce',
          'weight' => -8,
          'expanded' => FALSE,
        ],
        'display_extenders' => [],
      ],
    ],
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('view');
$existente = $storage->load('pronens_variaciones');
if ($existente !== NULL) {
  $existente->delete();
  print "View pronens_variaciones existente: reemplazada.\n";
}
View::create($definicion)->save();
print "View pronens_variaciones creada en /admin/commerce/variaciones.\n";

// -----------------------------------------------------------------------
// 2. Filtro de SKU en la view de productos.
// -----------------------------------------------------------------------

/** @var \Drupal\views\ViewEntityInterface|null $productos */
$productos = $storage->load('commerce_products');
if ($productos === NULL) {
  print "OJO: no existe la view commerce_products, no se añade el filtro.\n";
  return;
}
$display = &$productos->getDisplay('default');
if (isset($display['display_options']['filters']['sku'])) {
  print "El filtro de SKU ya estaba en commerce_products.\n";
  return;
}

$display['display_options']['relationships']['variations_target_id'] = [
  'id' => 'variations_target_id',
  'table' => 'commerce_product__variations',
  'field' => 'variations_target_id',
  'entity_type' => 'commerce_product',
  'entity_field' => 'variations',
  'plugin_id' => 'standard',
  'admin_label' => 'Variaciones',
  'required' => FALSE,
];
$display['display_options']['filters']['sku'] = [
  'id' => 'sku',
  'table' => 'commerce_product_variation_field_data',
  'field' => 'sku',
  'relationship' => 'variations_target_id',
  'entity_type' => 'commerce_product_variation',
  'entity_field' => 'sku',
  'plugin_id' => 'string',
  'operator' => 'contains',
  'value' => '',
  'exposed' => TRUE,
  'expose' => [
    'operator_id' => 'sku_op',
    'label' => 'SKU',
    'operator' => 'sku_op',
    'identifier' => 'sku',
    'required' => FALSE,
    'remember' => FALSE,
    'multiple' => FALSE,
  ],
];
// El join con las variaciones multiplica las filas: un producto con dos
// variaciones coincidentes saldría dos veces sin el DISTINCT.
$display['display_options']['query']['options']['distinct'] = TRUE;
$productos->save();
print "Filtro de SKU añadido a commerce_products (con DISTINCT).\n";
