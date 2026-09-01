<?php
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ids = $almacen->getQuery()->accessCheck(FALSE)
  ->condition('title', 'babero', 'CONTAINS')->condition('langcode', 'es')->sort('title')->execute();
$sinGaleria = 0; $conUna = 0; $conVarias = [];
foreach ($almacen->loadMultiple($ids) as $p) {
  $g = $p->get('field_galeria');
  $n = $g->count();
  if ($n === 0) { $sinGaleria++; continue; }
  if ($n === 1) { $conUna++; }
  else { $conVarias[$p->id()] = $n; }
  $nombres = [];
  foreach ($g->referencedEntities() as $m) {
    $f = $m->get('field_media_image')->entity ?? NULL;
    $nombres[] = $m->id() . ':' . ($f ? basename($f->getFileUri()) : 'sin fichero');
  }
  printf("%d %-50s galeria(%d): %s\n", $p->id(), $p->label(), $n, implode(' | ', $nombres));
}
print "\ntotal baberos: " . count($ids) . " | sin galeria: $sinGaleria | con 1: $conUna | con mas de 1: " . count($conVarias) . "\n";
