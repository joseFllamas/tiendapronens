<?php
$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$fs = \Drupal::service('file_system');
$grupos = [
  'baberos'              => ['babero', '_bebe.jpg'],
  'bolsas guardería'     => ['Bolsa guardería', '_cerrada.jpg'],
  'bolsas impermeables'  => ['bolsa impermeable', '_cerrada.jpg'],
];
foreach ($grupos as $nombre => [$aguja, $sufijo]) {
  $ids = $almacen->getQuery()->accessCheck(FALSE)
    ->condition('title', $aguja, 'CONTAINS')->condition('langcode', 'es')->execute();
  $bien = 0; $mal = [];
  foreach ($almacen->loadMultiple($ids) as $p) {
    $g = $p->get('field_galeria')->referencedEntities();
    if (!$g) { $mal[] = $p->id() . ' sin galeria'; continue; }
    $item = $g[0]->get('field_media_image')->first();
    $f = $item->entity;
    $n = $f ? basename($f->getFileUri()) : '';
    if (!$f || !str_starts_with($n, $p->id() . '_') || !str_ends_with($n, $sufijo)) { $mal[] = $p->id() . " -> $n"; continue; }
    $real = $fs->realpath($f->getFileUri());
    if (!$real || !is_file($real)) { $mal[] = $p->id() . " fichero ausente"; continue; }
    [$w, $h] = getimagesize($real);
    if ((int) $item->width !== $w || (int) $item->height !== $h) { $mal[] = $p->id() . " dimensiones desfasadas"; continue; }
    if (!$item->alt || $item->alt === $n) { $mal[] = $p->id() . ' alt sospechoso'; continue; }
    $bien++;
  }
  printf("%-22s %2d productos | con foto nueva de 2a: %2d", $nombre, count($ids), $bien);
  print $mal ? " | PENDIENTES: " . implode(', ', $mal) . "\n" : " | sin incidencias\n";
}
$dup = \Drupal::database()->query("SELECT entity_id FROM {commerce_product__field_galeria}
  GROUP BY entity_id, field_galeria_target_id HAVING COUNT(*) > 1")->fetchAll();
print "\ngalerias con media repetido: " . count($dup) . "\n";
