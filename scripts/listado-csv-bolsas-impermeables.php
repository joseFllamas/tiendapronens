<?php
$aguja = 'bolsa impermeable';
$destino = 'salida_unificacion/listados/bolsas-impermeables.csv';
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$fs = \Drupal::service('file_system');
$ids = $almacen->getQuery()->accessCheck(FALSE)
  ->condition('title', $aguja, 'CONTAINS')->condition('langcode', 'es')->sort('title')->execute();
$ruta = dirname(DRUPAL_ROOT) . '/' . $destino;
@mkdir(dirname($ruta), 0777, TRUE);
$fh = fopen($ruta, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, ['producto_id','titulo','ruta','media_id','file_id','ancho','alto','bytes','alt','publicado','fotos_galeria']);
$n = 0;
foreach ($almacen->loadMultiple($ids) as $p) {
  $m = $p->get('field_imagen_principal')->entity ?? NULL;
  $f = $m ? ($m->get('field_media_image')->entity ?? NULL) : NULL;
  $g = $p->get('field_galeria')->count();
  if (!$f) {
    fputcsv($fh, [$p->id(), $p->label(), '', $m ? $m->id() : '', '', '', '', '', '', $p->isPublished() ? 'si' : 'no', $g]);
    $n++; continue;
  }
  $uri = $f->getFileUri();
  $real = $fs->realpath($uri);
  $dim = ($real && is_file($real)) ? getimagesize($real) : ['', ''];
  fputcsv($fh, [
    $p->id(), $p->label(), str_replace('public://', 'sites/default/files/', $uri),
    $m->id(), $f->id(), $dim[0], $dim[1], $f->getSize(),
    $m->get('field_media_image')->alt, $p->isPublished() ? 'si' : 'no', $g,
  ]);
  $n++;
}
fclose($fh);
print "$destino -> $n filas\n";
