<?php
$busquedas = ['babero', 'Bolsa guardería'];
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
foreach ($busquedas as $aguja) {
  print "\n===== TITULO CONTIENE: \"$aguja\" =====\n";
  $ids = $almacen->getQuery()
    ->accessCheck(FALSE)
    ->condition('title', $aguja, 'CONTAINS')
    ->condition('langcode', 'es')
    ->sort('title')
    ->execute();
  print "productos: " . count($ids) . "\n\n";
  foreach ($almacen->loadMultiple($ids) as $p) {
    $estado = $p->isPublished() ? '' : ' [NO PUBLICADO]';
    print "ID {$p->id()} | {$p->label()}$estado\n";
    $media = $p->get('field_imagen_principal')->entity ?? NULL;
    if (!$media) { print "   principal: (VACIA)\n\n"; continue; }
    $file = $media->get('field_media_image')->entity ?? NULL;
    if (!$file) { print "   principal: media {$media->id()} sin fichero\n\n"; continue; }
    $uri = $file->getFileUri();
    $ruta = \Drupal::service('file_system')->realpath($uri);
    $dim = ($ruta && is_file($ruta)) ? implode('x', array_slice(getimagesize($ruta), 0, 2)) : 'FICHERO NO EXISTE';
    $alt = $media->get('field_media_image')->alt;
    print "   media {$media->id()} | fid {$file->id()} | $dim | " . round($file->getSize()/1024) . " KB\n";
    print "   " . str_replace('public://', 'sites/default/files/', $uri) . "\n";
    print "   alt: " . ($alt ?: '(vacio)') . "\n\n";
  }
}
