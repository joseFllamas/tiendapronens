<?php

/**
 * @file
 * Devuelve a los productos 138 y 139 el orden de fotos que tenían en el D7.
 *
 * El D7 alimentaba la ficha solo con field_imagen_galeria_ficha e ignoraba
 * field_imagen_representativa, así que la foto grande era B21/B17 (la bata de
 * adulto sobre modelo) y no la que se veía en la tarjeta del catálogo. En D11
 * manda un único field_imagen_principal, de modo que se le pone la del D7 y la
 * galería conserva el resto en el mismo orden: la secuencia completa de la
 * ficha (principal + galería) queda idéntica a la del D7.
 *
 * Ejecutar con: ddev drush php:script scripts/fotos-prendas-sanitarias.php
 */

use Drupal\commerce_product\Entity\Product;

// product_id => [principal, galería en orden].
// Los mid son los medias supervivientes del dedupe, ya verificados contra los
// fid del D7 en migrate_map_pronens_media_imagen.
$plan = [
  // Batas impermeables reutilizables (D7 nid 242).
  // D7: B21, B23, B28, IMG_2671, 452, microfibra.
  138 => [480, [481, 482, 465, 471, 1654]],
  // Batas impermeables desechables (D7 nid 243).
  // D7: B17, B20, B18, B19, img_9577, 450.
  139 => [476, [477, 478, 479, 472, 467]],
];

foreach ($plan as $id => [$principal, $galeria]) {
  $producto = Product::load($id);
  if (!$producto) {
    echo "Producto $id no encontrado.\n";
    continue;
  }

  $antes = [
    $producto->get('field_imagen_principal')->target_id,
    array_column($producto->get('field_galeria')->getValue(), 'target_id'),
  ];

  $producto->set('field_imagen_principal', ['target_id' => $principal]);
  $producto->set('field_galeria', array_map(
    static fn(int $mid): array => ['target_id' => $mid],
    $galeria,
  ));
  $producto->save();

  printf(
    "%d %s\n  antes: principal %d, galería %s\n  ahora: principal %d, galería %s\n",
    $id,
    $producto->label(),
    $antes[0],
    implode(', ', $antes[1]),
    $principal,
    implode(', ', $galeria),
  );
}
