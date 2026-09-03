<?php

/**
 * @file
 * Convierte la categoría vacía "Producto Personalizado" (tid 185) en
 * "Iniciales" y mete en ella todos los productos de modo `inicial`.
 *
 * Decisión del cliente (2026-09-03): el término 185 llevaba desde la migración
 * sin un solo producto, pero tenía entrada propia en la barra del menú
 * ("Personaliza") y en el pie ("Personalización"), o sea dos enlaces a una
 * página de 0 productos. Pasa a ser la puerta de la línea de inicial bordada:
 * los productos cuyo `field_modo_personalizacion` es `inicial` (la letra en
 * Graduate con el formato de dos colores). Hoy son 9: las 8 sudaderas del
 * término 201 y la mochila 373. Los de modo `texto` (nombre bordado) NO entran.
 *
 * Qué hace, y por qué así:
 * - Renombra el término en los 5 idiomas. Está en estado pathauto automático,
 *   así que el alias nuevo sale del patrón (`/productos/iniciales`,
 *   `/ca/productes/inicials`…) y `redirect` deja un 301 desde el alias viejo.
 *   Pathauto solo regenera el idioma del objeto que se guarda, de modo que
 *   hay que pedírselo traducción a traducción.
 * - Renombra el enlace 21 del menú `main` ("Personaliza") en los 5 idiomas. El
 *   enlace 29 del pie ("Personalización") se deja como está: sigue apuntando
 *   al término y funciona; cambiarle la etiqueta es decisión aparte.
 * - AÑADE el término 185 a `field_tipo_de_producto` de cada producto de modo
 *   inicial, detrás de los que ya tenga: el tema, el patrón de alias y
 *   "También te puede gustar" leen el PRIMER término, así que las sudaderas
 *   siguen siendo de "Sudaderas con iniciales" y la mochila de "Mochilas".
 *   El campo es compartido entre traducciones: se escribe una vez.
 * - Reindexa el índice `catalogo`: la página de categoría y las facetas leen
 *   de Search API, no de la base de datos.
 *
 * Idempotente: relanzarlo no duplica el término en ningún producto ni toca
 * lo que ya esté con el nombre nuevo.
 *
 * Uso: ddev drush php:script scripts/categoria-iniciales.php
 *   (en producción: drush php:script scripts/categoria-iniciales.php)
 */

use Drupal\search_api\Entity\Index;

$gestor = \Drupal::entityTypeManager();
$pathauto = \Drupal::service('pathauto.generator');
$tid = 185;
$enlace_menu = 21;

$nombres = [
  'es' => 'Iniciales',
  'ca' => 'Inicials',
  'en' => 'Initials',
  'fr' => 'Initiales',
  'it' => 'Iniziali',
];

// ---------------------------------------------------------------------------
// 1. El término cambia de nombre en los 5 idiomas y regenera sus alias.
// ---------------------------------------------------------------------------
$termino = $gestor->getStorage('taxonomy_term')->load($tid);
if ($termino === NULL) {
  print "No existe el término $tid. Nada que hacer.\n";
  return;
}

$alias_antes = \Drupal::database()->select('path_alias', 'a')
  ->fields('a', ['langcode', 'alias'])
  ->condition('path', '/taxonomy/term/' . $tid)
  ->execute()
  ->fetchAllKeyed();

foreach ($nombres as $idioma => $nombre) {
  if (!$termino->hasTranslation($idioma)) {
    print "  aviso: el término $tid no tiene traducción $idioma, se salta.\n";
    continue;
  }
  $traduccion = $termino->getTranslation($idioma);
  print sprintf("  término %-3s %-28s → %s\n", $idioma, $traduccion->label(), $nombre);
  $traduccion->setName($nombre);
}
$termino->save();

// Pathauto solo ha regenerado el alias del idioma por defecto: el resto, a
// mano. Con el alias ya al día la llamada no hace nada, así que es seguro
// repetirla. El módulo redirect deja el 301 en hook_path_alias_update.
foreach ($termino->getTranslationLanguages() as $idioma) {
  $pathauto->updateEntityAlias($termino->getTranslation($idioma->getId()), 'update');
}

$alias_despues = \Drupal::database()->select('path_alias', 'a')
  ->fields('a', ['langcode', 'alias'])
  ->condition('path', '/taxonomy/term/' . $tid)
  ->execute()
  ->fetchAllKeyed();
foreach ($alias_despues as $idioma => $alias) {
  $viejo = $alias_antes[$idioma] ?? '(sin alias)';
  print sprintf("  alias   %-3s %-42s → %s%s\n", $idioma, $viejo, $alias, $viejo === $alias ? '  (sin cambio)' : '  (301 desde el viejo)');
}

// ---------------------------------------------------------------------------
// 2. El enlace de la barra del menú se llama como la categoría.
// ---------------------------------------------------------------------------
$enlace = $gestor->getStorage('menu_link_content')->load($enlace_menu);
if ($enlace === NULL || $enlace->getUrlObject()->toString() !== $termino->toUrl()->toString()) {
  print "  aviso: el enlace $enlace_menu no existe o ya no apunta al término $tid; no se toca.\n";
}
else {
  foreach ($nombres as $idioma => $nombre) {
    if (!$enlace->hasTranslation($idioma)) {
      print "  aviso: el enlace $enlace_menu no tiene traducción $idioma, se salta.\n";
      continue;
    }
    $traduccion = $enlace->getTranslation($idioma);
    print sprintf("  menú    %-3s %-28s → %s\n", $idioma, $traduccion->getTitle(), $nombre);
    $traduccion->set('title', $nombre);
  }
  $enlace->save();
}

// ---------------------------------------------------------------------------
// 3. Todos los productos de modo `inicial` entran en la categoría.
// ---------------------------------------------------------------------------
$almacen_producto = $gestor->getStorage('commerce_product');
$ids = $almacen_producto->getQuery()
  ->accessCheck(FALSE)
  ->condition('field_modo_personalizacion', 'inicial')
  ->sort('product_id')
  ->execute();

$anadidos = 0;
foreach ($almacen_producto->loadMultiple($ids) as $producto) {
  $tids = array_map('intval', array_column($producto->get('field_tipo_de_producto')->getValue(), 'target_id'));
  if (in_array($tid, $tids, TRUE)) {
    print sprintf("  producto %-4s %-48s ya estaba (%s)\n", $producto->id(), $producto->label(), implode(',', $tids));
    continue;
  }
  $tids[] = $tid;
  $producto->set('field_tipo_de_producto', $tids);
  $producto->save();
  $anadidos++;
  print sprintf("  producto %-4s %-48s → %s\n", $producto->id(), $producto->label(), implode(',', $tids));
}
print sprintf("Productos de modo inicial: %d; añadidos a la categoría: %d.\n", count($ids), $anadidos);

// ---------------------------------------------------------------------------
// 4. Search API: la página de categoría y las facetas leen del índice.
// ---------------------------------------------------------------------------
$indice = Index::load('catalogo');
if ($indice !== NULL) {
  $indexados = $indice->indexItems();
  print "Reindexados $indexados elementos pendientes del índice catalogo.\n";
}

print "\nListo. Comprueba el alias de cada idioma y que el viejo responde 301.\n";
