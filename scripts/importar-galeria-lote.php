<?php
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

// Lotes: sufijo de fichero => [substring que debe tener el titulo, plantilla de alt].
$lotes = [
  '_bebe'    => ['babero',          fn($t) => str_starts_with($t, 'Pack')
                                       ? "Uno de los baberos del $t, puesto por un bebé"
                                       : "$t, puesto por un bebé"],
  '_cerrada' => ['Bolsa', fn($t) => "$t, vista de la bolsa cerrada"],
];

$dir = 'public://2026-08';
$fs = \Drupal::service('file_system');
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ok = 0; $yaEstaba = 0; $saltados = 0;

foreach ($lotes as $sufijo => [$debeContener, $altFn]) {
  $ficheros = glob($fs->realpath($dir) . "/*{$sufijo}.jpg");
  sort($ficheros);
  print "\n--- lote $sufijo: " . count($ficheros) . " ficheros\n";
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
      'name' => $titulo . ($sufijo === '_bebe' ? ' (bebé)' : ' (cerrada)'),
      'field_media_image' => ['target_id' => $file->id(), 'alt' => $altFn($titulo), 'title' => ''],
    ]);
    $media->save();

    array_unshift($galeria, ['target_id' => $media->id()]);
    $producto->set('field_galeria', $galeria);
    $producto->save();
    printf("%d %-50s media %d | galeria: %d\n", $pid, $titulo, $media->id(), count($galeria));
    $ok++;
  }
}
print "\nimportadas: $ok | ya estaban: $yaEstaba | saltadas: $saltados\n";
