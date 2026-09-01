<?php
$busquedas = ['babero', 'Bolsa guardería'];
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
foreach ($busquedas as $aguja) {
  $ids = $almacen->getQuery()->accessCheck(FALSE)
    ->condition('title', $aguja, 'CONTAINS')->condition('langcode', 'es')
    ->sort('title')->execute();
  print "\n### $aguja (" . count($ids) . ")\n";
  print "| ID | Producto | Imagen principal | Media | Dim | KB |\n|--|--|--|--|--|--|\n";
  foreach ($almacen->loadMultiple($ids) as $p) {
    $m = $p->get('field_imagen_principal')->entity ?? NULL;
    $f = $m ? ($m->get('field_media_image')->entity ?? NULL) : NULL;
    if (!$f) { print "| {$p->id()} | {$p->label()} | **(vacia)** | | | |\n"; continue; }
    $r = \Drupal::service('file_system')->realpath($f->getFileUri());
    $d = ($r && is_file($r)) ? implode('x', array_slice(getimagesize($r), 0, 2)) : 'NO EXISTE';
    printf("| %d | %s | %s | %d | %s | %d |\n", $p->id(), $p->label(),
      basename($f->getFileUri()), $m->id(), $d, round($f->getSize()/1024));
  }
}
