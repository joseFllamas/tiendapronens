<?php

/**
 * @file
 * Da de alta la nube en las mochilas y las bolsas.
 *
 * Los productos de esas dos categorías se bordan todos dentro de la nube (foto
 * de tienda de referencia), así que se les marcan los dos fondos y se les deja
 * una colocación de partida sacada de esa misma foto: la nube abajo a la
 * derecha, ocupando algo más de un cuarto del ancho.
 *
 * **La colocación es un punto de partida, no la definitiva**: los encuadres del
 * catálogo no son todos iguales y hay que afinarla producto a producto
 * arrastrando la nube en el formulario de edición. Por eso el script **solo
 * escribe donde está vacío**: lo ya calibrado a mano no se pisa, y volver a
 * lanzarlo no deshace ningún ajuste.
 *
 * Quedan fuera los productos de modo `inicial`: ahí el bordado es un parche de
 * una letra sobre la tela, no un nombre dentro de una nube.
 *
 * Uso: ddev drush php:script scripts/fondos-bordado-asignar.php
 */

// Mochilas infantiles y escolares, y Bolsas guardería y escolares.
$categorias = [179, 182];

// Medido sobre la foto de tienda de la bolsa con la nube: centro de la nube al
// 77 % del ancho y al 88 % del alto, y la nube ocupando el 26 % del ancho. La
// altura de la letra baja a 4 % porque el nombre va dentro de la nube y no
// suelto sobre la tela.
$partida = [
  'field_inicial_x' => 77,
  'field_inicial_y' => 88,
  'field_inicial_tamano' => 4,
  'field_fondo_tamano' => 26,
];

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$fondos = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'fondos_bordado', 'status' => 1]);
if ($fondos === []) {
  print "No hay fondos: lanza antes scripts/fondos-bordado.php\n";
  return;
}
// Por peso, que es el orden en que se ofrecen y el que decide cuál sale
// elegido de entrada en la ficha.
uasort($fondos, static fn ($a, $b) => $a->getWeight() <=> $b->getWeight());
$referencias = array_map(static fn ($f) => ['target_id' => $f->id()], array_values($fondos));
printf("Fondos: %s\n\n", implode(', ', array_map(static fn ($f) => $f->label(), $fondos)));

$ids = \Drupal::entityQuery('commerce_product')->accessCheck(FALSE)
  ->condition('field_tipo_de_producto', $categorias, 'IN')
  ->condition('status', 1)
  ->execute();

$puestos = $saltados = $ya = 0;
foreach ($almacen->loadMultiple($ids) as $producto) {
  if (!$producto->get('field_personalizable')->value
    || $producto->get('field_modo_personalizacion')->value === 'inicial') {
    $saltados++;
    continue;
  }
  $cambios = [];
  if ($producto->get('field_fondos_disponibles')->isEmpty()) {
    $producto->set('field_fondos_disponibles', $referencias);
    $cambios[] = 'fondos';
  }
  foreach ($partida as $campo => $valor) {
    if ($producto->get($campo)->isEmpty()) {
      $producto->set($campo, $valor);
      $cambios[] = $campo;
    }
  }
  if ($cambios === []) {
    $ya++;
    continue;
  }
  $producto->save();
  $puestos++;
  printf("%4d %-50s %s\n", $producto->id(), mb_substr($producto->label(), 0, 48), implode(' ', $cambios));
}

printf("\n%d productos con nube, %d ya la tenían completa, %d saltados (sin bordado o de inicial).\n", $puestos, $ya, $saltados);
print "Repasa la colocación producto a producto: el encuadre de las fotos no es el mismo en todo el catálogo.\n";
