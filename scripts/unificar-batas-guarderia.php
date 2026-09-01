<?php

/**
 * @file
 * Unifica "Batas guardería" (tid 200) dentro de "Batas Babis Escolares"
 * (tid 176) y borra el término, conservando la palabra "guardería" en el menú.
 *
 * Decisión del cliente (2026-08-12): la categoría tenía 6 productos (1
 * publicado y 5 despublicados) frente a los 23 de batas babis escolares, y
 * separar ambas no aporta. El enlace del panel "Batas" se repunta al término
 * superviviente conservando su etiqueta "Batas guardería" en los 5 idiomas,
 * de modo que quien busca esa palabra la sigue encontrando en la navegación.
 *
 * Los 5 alias del término borrado (uno por idioma) se sustituyen por
 * redirecciones 301 al término 176: son URLs que venían del D7 y sin esto
 * pasarían a 404. Se crean DESPUÉS del borrado, porque al borrar la entidad
 * el módulo redirect limpia las redirecciones que apuntan a ella.
 *
 * Uso: ddev drush php:script scripts/unificar-batas-guarderia.php
 */

use Drupal\redirect\Entity\Redirect;

$gestor = \Drupal::entityTypeManager();
$origen = 200;
$destino = 176;

// ---------------------------------------------------------------------------
// 1. Los 6 productos pasan al término superviviente.
// ---------------------------------------------------------------------------
$almacen_producto = $gestor->getStorage('commerce_product');
$ids = \Drupal::database()->select('commerce_product__field_tipo_de_producto', 'f')
  ->fields('f', ['entity_id'])
  ->condition('field_tipo_de_producto_target_id', $origen)
  ->distinct()
  ->execute()
  ->fetchCol();

foreach ($almacen_producto->loadMultiple($ids) as $producto) {
  // field_tipo_de_producto es campo compartido entre traducciones, así que
  // basta con escribir en la traducción por defecto. Se sustituye solo el
  // valor 200 y se deduplica por si el producto ya tuviera el 176.
  $tids = array_column($producto->get('field_tipo_de_producto')->getValue(), 'target_id');
  $tids = array_values(array_unique(array_map(
    static fn ($tid) => (int) $tid === $origen ? $destino : (int) $tid,
    $tids
  )));
  $producto->set('field_tipo_de_producto', $tids);
  $producto->save();
  print sprintf("  producto %-4s %-40s → tid %s\n", $producto->id(), $producto->label(), implode(',', $tids));
}
print sprintf("Movidos %d productos.\n", count($ids));

// ---------------------------------------------------------------------------
// 2. El enlace del menú apunta al término superviviente, con su etiqueta.
// ---------------------------------------------------------------------------
$enlace = $gestor->getStorage('menu_link_content')->load(16);
if ($enlace !== NULL) {
  foreach ($enlace->getTranslationLanguages() as $idioma) {
    $traduccion = $enlace->getTranslation($idioma->getId());
    $traduccion->set('link', ['uri' => 'entity:taxonomy_term/' . $destino])->save();
  }
  print "Enlace 16 repuntado al tid $destino conservando \"Batas guardería\" en los 5 idiomas.\n";
}

// ---------------------------------------------------------------------------
// 3. Se guardan los alias antes de que el borrado se los lleve por delante.
// ---------------------------------------------------------------------------
$alias = \Drupal::database()->select('path_alias', 'a')
  ->fields('a', ['alias', 'langcode'])
  ->condition('path', '/taxonomy/term/' . $origen)
  ->execute()
  ->fetchAllKeyed();

// ---------------------------------------------------------------------------
// 4. Borrado del término.
// ---------------------------------------------------------------------------
$termino = $gestor->getStorage('taxonomy_term')->load($origen);
if ($termino !== NULL) {
  $termino->delete();
  print "Borrado el término $origen.\n";
}

// ---------------------------------------------------------------------------
// 5. Un 301 por idioma desde el alias viejo al término superviviente.
// ---------------------------------------------------------------------------
foreach ($alias as $ruta => $idioma) {
  $redireccion = Redirect::create();
  $redireccion->setSource(ltrim($ruta, '/'));
  $redireccion->setRedirect('taxonomy/term/' . $destino);
  $redireccion->setStatusCode(301);
  $redireccion->setLanguage($idioma);
  $redireccion->save();
  print sprintf("  301  %-40s → /taxonomy/term/%d  [%s]\n", $ruta, $destino, $idioma);
}

print "\nListo.\n";
