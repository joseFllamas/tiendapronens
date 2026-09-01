<?php
// Al sobreescribir el fichero cambian las dimensiones reales, pero el ancho y alto
// que guarda el campo de imagen se quedan con los de la version anterior.
$fs = \Drupal::service('file_system');
$almacen = \Drupal::entityTypeManager()->getStorage('media');
$ids = \Drupal::database()->query(
  "SELECT DISTINCT m.entity_id FROM {media__field_media_image} m
   INNER JOIN {file_managed} f ON f.fid = m.field_media_image_target_id
   WHERE f.uri LIKE 'public://2026-08/%'")->fetchCol();
$arreglados = 0; $iguales = 0;
foreach ($almacen->loadMultiple($ids) as $media) {
  $item = $media->get('field_media_image')->first();
  $file = $item->entity;
  $real = $fs->realpath($file->getFileUri());
  if (!$real || !is_file($real)) { print "AUSENTE " . $file->getFileUri() . "\n"; continue; }
  [$w, $h] = getimagesize($real);
  $bytes = filesize($real);
  if ((int) $item->width === $w && (int) $item->height === $h && (int) $file->getSize() === $bytes) { $iguales++; continue; }
  printf("%-34s %sx%s -> %sx%s\n", basename($real), $item->width, $item->height, $w, $h);
  $item->width = $w;
  $item->height = $h;
  $file->setSize($bytes);
  $file->save();
  $media->save();
  $arreglados++;
}
print "\nmedias en 2026-08: " . count($ids) . " | corregidos: $arreglados | ya correctos: $iguales\n";
