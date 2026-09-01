<?php
$agujas = ['mochila infantil'];
$max_imagenes = 1;
$destino = 'salida_unificacion/listados/mochilas-infantiles-una-imagen.csv';
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$fs = \Drupal::service('file_system');
$consulta = $almacen->getQuery()->accessCheck(FALSE)->condition('langcode', 'es')->sort('title');
$o = $consulta->orConditionGroup();
foreach ($agujas as $aguja) {
  $o->condition('title', $aguja, 'CONTAINS');
}
$ids = $consulta->condition($o)->execute();
$ruta = dirname(DRUPAL_ROOT) . '/' . $destino;
@mkdir(dirname($ruta), 0777, TRUE);
$fh = fopen($ruta, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, ['producto_id','titulo','ruta','media_id','file_id','ancho','alto','bytes','alt','publicado','fotos_galeria','fotos_total']);
$n = 0;
$descartados = 0;
foreach ($almacen->loadMultiple($ids) as $p) {
  $m = $p->get('field_imagen_principal')->entity ?? NULL;
  $f = $m ? ($m->get('field_media_image')->entity ?? NULL) : NULL;
  $g = $p->get('field_galeria')->count();
  $total = ($m ? 1 : 0) + $g;
  if ($total > $max_imagenes) {
    $descartados++;
    continue;
  }
  if (!$f) {
    fputcsv($fh, [$p->id(), $p->label(), '', $m ? $m->id() : '', '', '', '', '', '', $p->isPublished() ? 'si' : 'no', $g, $total]);
    $n++; continue;
  }
  $uri = $f->getFileUri();
  $real = $fs->realpath($uri);
  $dim = ($real && is_file($real)) ? getimagesize($real) : ['', ''];
  fputcsv($fh, [
    $p->id(), $p->label(), str_replace('public://', 'sites/default/files/', $uri),
    $m->id(), $f->id(), $dim[0], $dim[1], $f->getSize(),
    $m->get('field_media_image')->alt, $p->isPublished() ? 'si' : 'no', $g, $total,
  ]);
  $n++;
}
fclose($fh);
print "$destino -> $n filas ($descartados descartados por tener más de $max_imagenes imagen/es)\n";
