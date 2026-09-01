<?php

/**
 * @file
 * El buscador de la tienda: índice y página de resultados.
 *
 * El icono de lupa del header enlazaba a /buscar desde la fase del tema, pero
 * la ruta no existía: el buscador "no hacía nada". La referencia es el D10 de
 * pronens (activity_search_pro): buscar por nombre O por referencia (SKU) y
 * ver los resultados en vivo. Aquí se monta sobre el search_api que ya indexa
 * el catálogo, que corrige lo que allí estaba mal: filtra por publicado (el
 * procesador entity_status del índice), por idioma, tokeniza (allí "bolsa oso"
 * no encontraba "Bolsa guardería Oso Tribal") y no hace LIKE sin límite.
 *
 * Tres piezas:
 * 1. Campo `sku` en el índice catalogo (variations:entity:sku, fulltext): un
 *    producto responde por cualquiera de sus SKUs.
 * 2. `matching: partial` en el server: una referencia se busca a trozos
 *    ("OSOTRIB" tiene que encontrar BG.OSOTRIB.PEQ) y con palabras enteras no
 *    hay forma. Solo afecta a las consultas fulltext: el catálogo no las usa.
 * 3. View `buscar` en /buscar, con el filtro fulltext expuesto como `texto`
 *    (no `q`: Views lo descarta a propósito de la exposed input, es el viejo
 *    parámetro de ruta de Drupal) sobre titulo + sku, el filtro de idioma
 *    obligatorio de todas las views de productos y las tarjetas del catálogo.
 *
 * Después hay que reindexar: ddev drush sapi-c && ddev drush sapi-i
 *
 * Idempotente. Uso: ddev drush php:script scripts/buscador.php
 */

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Item\Field;
use Drupal\views\Entity\View;

// -----------------------------------------------------------------------
// 1. Campo sku en el índice.
// -----------------------------------------------------------------------

$indice = Index::load('catalogo');
if ($indice === NULL) {
  print "OJO: no existe el índice catalogo.\n";
  return;
}
if ($indice->getField('sku') === NULL) {
  $campo = new Field($indice, 'sku');
  $campo->setDatasourceId('entity:commerce_product');
  $campo->setPropertyPath('variations:entity:sku');
  $campo->setLabel('SKU');
  $campo->setType('text');
  // El SKU identifica: si la búsqueda parece una referencia, el producto que
  // la lleva tiene que salir el primero.
  $campo->setBoost(8.0);
  $indice->addField($campo);
  $indice->save();
  print "Campo sku añadido al índice catalogo (reindexar).\n";
}
else {
  print "El campo sku ya estaba en el índice.\n";
}

// El sku tiene que pasar por los mismos procesadores que el título: ignorecase
// lowercasea la CONSULTA entera (porque titulo está en sus campos), así que si
// el sku se indexa con mayúsculas ("BGOSOTRIBPEQ") ninguna búsqueda lo
// encuentra: la columna word es case-sensitive.
foreach (['ignorecase', 'transliteration'] as $procesador) {
  $plugin = $indice->getProcessor($procesador);
  if ($plugin === NULL) {
    continue;
  }
  $conf = $plugin->getConfiguration();
  if (!in_array('sku', $conf['fields'] ?? [], TRUE)) {
    $conf['fields'][] = 'sku';
    $plugin->setConfiguration($conf);
    $indice->save();
    print "Procesador {$procesador}: sku añadido.\n";
  }
  else {
    print "Procesador {$procesador}: sku ya estaba.\n";
  }
}

// -----------------------------------------------------------------------
// 2. Matching parcial en el server.
// -----------------------------------------------------------------------

$server = Server::load('pronens');
$backend_config = $server->getBackendConfig();
if (($backend_config['matching'] ?? 'words') !== 'partial') {
  $backend_config['matching'] = 'partial';
  $server->setBackendConfig($backend_config);
  $server->save();
  print "Server pronens: matching = partial.\n";
}
else {
  print "El server ya estaba en matching partial.\n";
}

// -----------------------------------------------------------------------
// 3. View de resultados en /buscar.
// -----------------------------------------------------------------------

$definicion = [
  'id' => 'buscar',
  'label' => 'Buscar',
  'module' => 'views',
  'description' => 'Resultados del buscador de la tienda: por nombre o por SKU.',
  'tag' => 'default',
  'base_table' => 'search_api_index_catalogo',
  'base_field' => 'search_api_id',
  'status' => TRUE,
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Predeterminado',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Buscar',
        'access' => ['type' => 'none', 'options' => []],
        'cache' => ['type' => 'search_api_tag', 'options' => []],
        'query' => [
          'type' => 'search_api_query',
          'options' => [
            'bypass_access' => FALSE,
            'skip_access' => FALSE,
            'preserve_facet_query_args' => FALSE,
          ],
        ],
        'exposed_form' => [
          'type' => 'basic',
          'options' => [
            'submit_button' => 'Buscar',
            'reset_button' => FALSE,
            'expose_sort_order' => FALSE,
          ],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 24, 'offset' => 0],
        ],
        'style' => [
          'type' => 'default',
          'options' => [
            'grouping' => [],
            'row_class' => 'pro-grid__cell',
            'default_row_class' => FALSE,
            'uses_fields' => FALSE,
          ],
        ],
        'row' => [
          'type' => 'search_api',
          'options' => [
            'view_modes' => [
              'entity:commerce_product' => [
                ':default' => 'tarjeta',
                'default' => 'tarjeta',
              ],
            ],
          ],
        ],
        'filters' => [
          'search_api_fulltext' => [
            'id' => 'search_api_fulltext',
            'table' => 'search_api_index_catalogo',
            'field' => 'search_api_fulltext',
            'plugin_id' => 'search_api_fulltext',
            'operator' => 'and',
            'value' => '',
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => 'texto_op',
              'label' => 'Buscar',
              'operator' => 'texto_op',
              'identifier' => 'texto',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
              'placeholder' => '',
            ],
            'parse_mode' => 'terms',
            'min_length' => 3,
            'fields' => ['sku', 'titulo'],
          ],
          // Solo productos con categoría: la basura publicada del D7 sin
          // término ("Test sudadera", "Pedido 7682") no debe salir en un
          // buscador global. Es la advertencia ya documentada del catálogo.
          'tipo' => [
            'id' => 'tipo',
            'table' => 'search_api_index_catalogo',
            'field' => 'tipo',
            'plugin_id' => 'search_api_term',
            'operator' => 'not empty',
            'value' => [],
            'exposed' => FALSE,
          ],
          // La regla de la casa: toda view que lista productos filtra por
          // idioma, o cada producto sale cinco veces (una por traducción).
          'search_api_language' => [
            'id' => 'search_api_language',
            'table' => 'search_api_index_catalogo',
            'field' => 'search_api_language',
            'plugin_id' => 'search_api_language',
            'operator' => 'in',
            'value' => ['***LANGUAGE_language_content***'],
            'exposed' => FALSE,
          ],
        ],
        'sorts' => [
          'search_api_relevance' => [
            'id' => 'search_api_relevance',
            'table' => 'search_api_index_catalogo',
            'field' => 'search_api_relevance',
            'plugin_id' => 'search_api',
            'order' => 'DESC',
            'exposed' => FALSE,
          ],
          'created' => [
            'id' => 'created',
            'table' => 'search_api_index_catalogo',
            'field' => 'created',
            'plugin_id' => 'search_api',
            'order' => 'DESC',
            'exposed' => FALSE,
          ],
        ],
        'rendering_language' => '***LANGUAGE_language_content***',
        'css_class' => 'pro-buscar',
        'header' => [],
        'footer' => [],
        'empty' => [],
        'relationships' => [],
        'arguments' => [],
        'display_extenders' => [],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Página',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'buscar',
        'display_extenders' => [],
      ],
    ],
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('view');
$existente = $storage->load('buscar');
if ($existente !== NULL) {
  $existente->delete();
  print "View buscar existente: reemplazada.\n";
}
View::create($definicion)->save();
print "View buscar creada en /buscar.\n";

// Título de la página en los otros cuatro idiomas, como override de config
// por idioma (el mismo mecanismo que los patrones de pathauto): las views no
// traen config_translation y el título sale en el <title> y el H1.
$titulos = ['ca' => 'Cerca', 'en' => 'Search', 'fr' => 'Recherche', 'it' => 'Ricerca'];
foreach ($titulos as $idioma => $titulo) {
  $override = \Drupal::languageManager()->getLanguageConfigOverride($idioma, 'views.view.buscar');
  $override->set('display.default.display_options.title', $titulo)->save();
  print "Título en {$idioma}: {$titulo}.\n";
}
