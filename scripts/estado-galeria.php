<?php
$aguja = 'Bolsa guardería';
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ids = $almacen->getQuery()->accessCheck(FALSE)
  ->condition('title', $aguja, 'CONTAINS')->condition('langcode', 'es')->sort('title')->execute();
$sin = 0;
foreach ($almacen->loadMultiple($ids) as $p) {
  $g = $p->get('field_galeria')->referencedEntities();
  if (!$g) { $sin++; continue; }
  $n = [];
  foreach ($g as $m) { $f = $m->get('field_media_image')->entity ?? NULL; $n[] = $f ? basename($f->getFileUri()) : '?'; }
  printf("%d %-50s (%d) %s\n", $p->id(), $p->label(), count($g), implode(' | ', $n));
}
print "total: " . count($ids) . " | sin galeria: $sin\n";
