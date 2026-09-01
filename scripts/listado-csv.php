<?php
$grupos = ['babero' => 'baberos.csv', 'Bolsa guardería' => 'bolsas-guarderia.csv'];
$dir = 'salida_unificacion/listados';
@mkdir($dir, 0777, TRUE);
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$fs = \Drupal::service('file_system');
foreach ($grupos as $aguja => $nombre) {
  $ids = $almacen->getQuery()->accessCheck(FALSE)
    ->condition('title', $aguja, 'CONTAINS')->condition('langcode', 'es')
    ->sort('title')->execute();
  $fh = fopen("$dir/$nombre", 'w');
  fwrite($fh, "\xEF\xBB\xBF");
  fputcsv($fh, ['producto_id', 'titulo', 'ruta', 'media_id', 'file_id', 'ancho', 'alto', 'bytes', 'alt', 'publicado']);
  $n = 0;
  foreach ($almacen->loadMultiple($ids) as $p) {
    $m = $p->get('field_imagen_principal')->entity ?? NULL;
    $f = $m ? ($m->get('field_media_image')->entity ?? NULL) : NULL;
    if (!$f) {
      fputcsv($fh, [$p->id(), $p->label(), '', $m ? $m->id() : '', '', '', '', '', '', $p->isPublished() ? 'si' : 'no']);
      $n++; continue;
    }
    $uri = $f->getFileUri();
    $real = $fs->realpath($uri);
    $dim = ($real && is_file($real)) ? getimagesize($real) : [ '', '' ];
    fputcsv($fh, [
      $p->id(), $p->label(),
      str_replace('public://', 'sites/default/files/', $uri),
      $m->id(), $f->id(), $dim[0], $dim[1], $f->getSize(),
      $m->get('field_media_image')->alt, $p->isPublished() ? 'si' : 'no',
    ]);
    $n++;
  }
  fclose($fh);
  print "$dir/$nombre -> $n filas\n";
}
