<?php
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

$dir = 'public://2026-08';
$fs = \Drupal::service('file_system');
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ficheros = glob($fs->realpath($dir) . '/*_bebe.jpg');
sort($ficheros);
$ok = 0; $saltados = 0;

foreach ($ficheros as $ruta) {
  $base = basename($ruta);
  $pid = (int) strtok($base, '_');
  $producto = $almacen->load($pid);
  if (!$producto) { print "SALTADO $base: no existe el producto $pid\n"; $saltados++; continue; }
  if (stripos($producto->label(), 'babero') === FALSE) {
    print "SALTADO $base: el producto $pid no es un babero ({$producto->label()})\n"; $saltados++; continue;
  }

  // Fichero.
  $uri = "$dir/$base";
  $existentes = \Drupal::entityTypeManager()->getStorage('file')
    ->loadByProperties(['uri' => $uri]);
  $file = $existentes ? reset($existentes) : File::create([
    'uri' => $uri, 'status' => 1, 'uid' => 1,
  ]);
  if (!$file->id()) { $file->save(); }

  // Texto alternativo descriptivo (no el nombre del fichero).
  $titulo = $producto->label();
  $alt = str_starts_with($titulo, 'Pack')
    ? "Uno de los baberos del $titulo, puesto por un bebé"
    : "$titulo, puesto por un bebé";

  $media = Media::create([
    'bundle' => 'image',
    'uid' => 1,
    'status' => 1,
    'name' => "$titulo (bebé)",
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt, 'title' => ''],
  ]);
  $media->save();

  // Insertar en cabeza de la galeria: la foto de bebe es la SEGUNDA del producto.
  $galeria = array_map(fn($m) => ['target_id' => $m->id()], $producto->get('field_galeria')->referencedEntities());
  array_unshift($galeria, ['target_id' => $media->id()]);
  $producto->set('field_galeria', $galeria);
  $producto->save();

  printf("%d %-50s media %d | galeria ahora: %d\n", $pid, $titulo, $media->id(), count($galeria));
  $ok++;
}
print "\nimportadas: $ok | saltadas: $saltados\n";
