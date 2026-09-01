<?php

/**
 * @file
 * Patrones de pathauto migrados del Drupal 7.
 *
 * Se ejecuta con `ddev drush php:script scripts/pathauto-patrones.php`.
 *
 * El D7 tenía cuatro patrones de nodo para los productos (`productos/[title]`,
 * `productos/[categoría]/[title]`, `escuelas/[escuela]/[title]` y el de página),
 * porque allí el alias colgaba del nodo de display. Aquí el producto es una
 * entidad propia con un solo bundle, así que se colapsan en uno.
 *
 * El prefijo por idioma va como override de configuración y no como condición
 * "Language" del patrón: PathautoPattern::applies() resuelve los contextos con
 * getRuntimeContexts(), o sea el idioma de INTERFAZ de la petición, mientras que
 * PathautoGenerator::getPatternByEntity() lee el override con el idioma de la
 * ENTIDAD, que es el que toca al guardar una traducción o en un bulk update.
 */

use Drupal\pathauto\Entity\PathautoPattern;

/**
 * Prefijo del catálogo en cada idioma.
 *
 * ca, en e it no son una elección nuestra: son los prefijos que ya trae el
 * corpus migrado (8 alias /productes/, 9 /products/, 20 /prodotti/). Para
 * francés no hay precedente en los datos.
 */
const PREFIJOS = [
  'es' => 'productos',
  'ca' => 'productes',
  'en' => 'products',
  'fr' => 'produits',
  'it' => 'prodotti',
];

/**
 * Patrón de cada entidad, con %s por el prefijo del idioma.
 */
const PATRONES = [
  'producto' => '/%s/[commerce_product:field_tipo_de_producto:entity:name]/[commerce_product:title]',
  'categoria_producto' => '/%s/[term:name]',
];

$almacen = \Drupal::entityTypeManager()->getStorage('pathauto_pattern');
$idiomas = \Drupal::languageManager();

// 1. El producto pasa a llevar la categoría, como el `producto_costumizado` del
// D7. Los 28 productos sin categoría no necesitan un patrón de reserva:
// AliasCleaner::cleanAlias() colapsa la barra sobrante y deja /productos/titulo.
foreach (PATRONES as $id => $plantilla) {
  $patron = $almacen->load($id);
  $patron->setPattern(sprintf($plantilla, PREFIJOS['es']));
  $patron->save();
  printf("patrón %-20s es → %s\n", $id, $patron->getPattern());
}

// 2. Prefijo traducido en los otros cuatro idiomas.
foreach (PATRONES as $id => $plantilla) {
  foreach (PREFIJOS as $lc => $prefijo) {
    if ($lc === 'es') {
      continue;
    }
    $override = $idiomas->getLanguageConfigOverride($lc, 'pathauto.pattern.' . $id);
    $override->set('pattern', sprintf($plantilla, $prefijo))->save();
    printf("patrón %-20s %s → %s\n", $id, $lc, $override->get('pattern'));
  }
}

// 3. Página estática, igual que `pathauto_node_page_pattern` del D7. El tipo
// `home` no lleva patrón: es la portada.
if (!$almacen->load('pagina')) {
  $pagina = PathautoPattern::create([
    'id' => 'pagina',
    'label' => 'Página',
    'type' => 'canonical_entities:node',
    'pattern' => '/[node:title]',
  ]);
  $pagina->addSelectionCondition([
    'id' => 'entity_bundle:node',
    'bundles' => ['page' => 'page'],
    'negate' => FALSE,
    'context_mapping' => ['node' => 'node'],
  ]);
  $pagina->save();
  printf("patrón %-20s es → %s\n", 'pagina', $pagina->getPattern());
}

// 4. Ajustes generales.
$ajustes = \Drupal::configFactory()->getEditable('pathauto.settings');

// La lista de palabras a ignorar es la inglesa de fábrica en una tienda
// es/ca/fr/it: no filtra "de", "la", "con" ni "para", que es lo que aparece en
// estos títulos, y en cambio borra palabras con significado en los otros
// idiomas (a, in, on, per, via, like). Vacía, el alias reproduce el título.
$ajustes->set('ignore_words', '');

// `user` inyecta un campo "URL alias" en el perfil de 1580 usuarios sin que
// exista ningún patrón de usuario ni un solo alias. El D7 sí tenía
// users/[user:name], pero el cliente ha decidido no replicarlo.
$ajustes->set('enabled_entity_types', []);

$ajustes->save();
print "ajustes: ignore_words vaciada, enabled_entity_types sin 'user'\n";
