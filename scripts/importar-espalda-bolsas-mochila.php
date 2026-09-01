<?php

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

// Fotos de modelo con la bolsa puesta a la espalda, generadas en
// salida_unificacion/bolsasmochila_espalda y ya copiadas a public://2026-08.
// El id del producto es el primer trozo del nombre del fichero (218_556-...).
$dir = 'public://2026-08';
$sufijo = '_espalda.png';
$debeContener = 'bolsa mochila';

$fs = \Drupal::service('file_system');
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ok = 0; $yaEstaba = 0; $saltados = 0;

$ficheros = glob($fs->realpath($dir) . "/*{$sufijo}");
sort($ficheros);
print count($ficheros) . " ficheros\n";

foreach ($ficheros as $ruta) {
  $base = basename($ruta);
  $pid = (int) strtok($base, '_');
  $producto = $almacen->load($pid);
  if (!$producto) { print "SALTADO $base: no existe el producto $pid\n"; $saltados++; continue; }
  if (stripos($producto->label(), $debeContener) === FALSE) {
    print "SALTADO $base: el producto $pid no encaja ({$producto->label()})\n"; $saltados++; continue;
  }
  // Idempotencia: si esa foto ya esta en la galeria, no repetir.
  $galeria = [];
  $repetida = FALSE;
  foreach ($producto->get('field_galeria')->referencedEntities() as $m) {
    $f = $m->get('field_media_image')->entity ?? NULL;
    if ($f && basename($f->getFileUri()) === $base) { $repetida = TRUE; }
    $galeria[] = ['target_id' => $m->id()];
  }
  if ($repetida) { $yaEstaba++; continue; }

  $uri = "$dir/$base";
  $previos = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
  $file = $previos ? reset($previos) : File::create(['uri' => $uri, 'status' => 1, 'uid' => 1]);
  if (!$file->id()) { $file->save(); }

  $titulo = $producto->label();
  $media = Media::create([
    'bundle' => 'image', 'uid' => 1, 'status' => 1,
    'name' => "$titulo (espalda)",
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => "$titulo, puesta a la espalda",
      'title' => '',
    ],
  ]);
  $media->save();

  // Segunda posicion de la ficha: la principal va antes que la galeria, asi que
  // la espalda entra la primera de la galeria y las que hubiera bajan un puesto.
  array_unshift($galeria, ['target_id' => $media->id()]);
  $producto->set('field_galeria', $galeria);
  $producto->save();
  printf("%d %-55s media %d | galeria: %d\n", $pid, $titulo, $media->id(), count($galeria));
  $ok++;
}
print "\nimportadas: $ok | ya estaban: $yaEstaba | saltadas: $saltados\n";
