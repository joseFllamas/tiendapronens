<?php
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ids = $almacen->getQuery()->accessCheck(FALSE)
  ->condition('title', 'babero', 'CONTAINS')->condition('langcode', 'es')->sort('title')->execute();
$bien = 0; $mal = [];
foreach ($almacen->loadMultiple($ids) as $p) {
  $g = $p->get('field_galeria')->referencedEntities();
  if (!$g) { $mal[] = $p->id() . ' sin galeria'; continue; }
  $primera = $g[0];
  $f = $primera->get('field_media_image')->entity ?? NULL;
  $nombre = $f ? basename($f->getFileUri()) : '';
  $esperado = $p->id() . '_';
  if (!$f || !str_starts_with($nombre, $esperado) || !str_ends_with($nombre, '_bebe.jpg')) {
    $mal[] = $p->id() . ' -> primera de galeria = ' . $nombre; continue;
  }
  if (!is_file(\Drupal::service('file_system')->realpath($f->getFileUri()))) {
    $mal[] = $p->id() . ' -> fichero inexistente ' . $nombre; continue;
  }
  $alt = $primera->get('field_media_image')->alt;
  if ($alt === $nombre || $alt === '') { $mal[] = $p->id() . ' -> alt sospechoso'; continue; }
  $bien++;
}
print "baberos: " . count($ids) . " | con la foto de bebe en 2a posicion: $bien\n";
print $mal ? "PENDIENTES:\n - " . implode("\n - ", $mal) . "\n" : "sin incidencias\n";

// Los cuatro que ya tenian una foto en galeria.
foreach ([157, 159, 160, 162] as $id) {
  $p = $almacen->load($id);
  $n = [];
  foreach ($p->get('field_galeria')->referencedEntities() as $i => $m) {
    $f = $m->get('field_media_image')->entity ?? NULL;
    $n[] = ($i + 2) . 'a:' . ($f ? basename($f->getFileUri()) : '?');
  }
  printf("%d %-40s %s\n", $id, $p->label(), implode('  ', $n));
}
// Idiomas: el campo es compartido, comprobamos en frances.
$fr = $almacen->load(157)->getTranslation('fr');
$f = $fr->get('field_galeria')->referencedEntities()[0]->get('field_media_image')->entity;
print "\ncomprobacion en frances (157): " . basename($f->getFileUri()) . " | titulo: " . $fr->label() . "\n";
