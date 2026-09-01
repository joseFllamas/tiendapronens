<?php

/**
 * @file
 * Copia la calibración de la bolsa Caperucita (132) al resto de bolsas.
 *
 * El cliente calibró la nube sobre la bolsa guardería impermeable Caperucita
 * Roja (nube al 69,48 / 70,59 con la letra al 5,5 % y la nube al 26 % del
 * ancho) y esa colocación sustituye a la de partida del script de fondos
 * (77 / 88 / 4 / 26), que quedaba baja y pequeña. Vale para las tres familias
 * porque comparten tipo de foto (la lámina impresa llena el encuadre, la nube
 * cae siempre dentro, verificado dibujándola sobre las 87 fotos):
 * - Bolsas guardería y escolares (término 182), donde vive la propia 132.
 * - Las "Bolsa mochila…" de Mochilas (término 179), por título.
 *
 * A diferencia de bordado-bodys.php aquí hay que PISAR valores existentes: el
 * script de fondos ya dejó la colocación de partida en todas. La regla es
 * **solo se pisa lo que siga exactamente en esa partida** (77 / 88 / 4 / 26);
 * cualquier otro valor es una calibración manual y se respeta (la propia 132
 * y la bolsa Mamá 304, afinada a mano con 70,57 / 71,99 / 3,5 / 29,5).
 *
 * Ojo: las fotos de las 13 "Bolsa mochila" (218-230) traen un nombre de
 * ejemplo impreso en la lámina ("Lucas", "ERIC", "HUGO"…), así que la vista
 * previa pinta un segundo nombre. Ya pasaba con la colocación de partida; la
 * solución de fondo es foto sin nombre, como se hizo con las sudaderas.
 *
 * Uso: ddev drush php:script scripts/bordado-bolsas.php
 */

// La calibración del cliente sobre la Caperucita (132).
$calibracion = [
  'field_inicial_x' => 69.48,
  'field_inicial_y' => 70.59,
  'field_inicial_tamano' => 5.50,
  'field_fondo_tamano' => 26.00,
];

// La colocación de partida que dejó fondos-bordado-asignar.php: es lo único
// que este script se permite sustituir.
$partida = [
  'field_inicial_x' => 77.0,
  'field_inicial_y' => 88.0,
  'field_inicial_tamano' => 4.0,
  'field_fondo_tamano' => 26.0,
];

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ids = \Drupal::entityQuery('commerce_product')->accessCheck(FALSE)
  ->condition('field_tipo_de_producto', 182)
  ->condition('status', 1)
  ->execute();
$ids += \Drupal::entityQuery('commerce_product')->accessCheck(FALSE)
  ->condition('field_tipo_de_producto', 179)
  ->condition('title', 'Bolsa mochila%', 'LIKE')
  ->condition('status', 1)
  ->execute();

$puestos = $respetados = $saltados = 0;
foreach ($almacen->loadMultiple($ids) as $producto) {
  if (!$producto->get('field_personalizable')->value
    || $producto->get('field_modo_personalizacion')->value !== 'texto') {
    printf("= %d %s: no es de nombre, no se toca\n", $producto->id(), $producto->label());
    $saltados++;
    continue;
  }

  // Un solo campo fuera de la partida marca el producto entero como calibrado
  // a mano: la colocación es una decisión conjunta y no se pisa a medias.
  $manual = FALSE;
  foreach ($partida as $campo => $valor) {
    if (!$producto->get($campo)->isEmpty()
      && abs((float) $producto->get($campo)->value - $valor) > 0.001) {
      $manual = TRUE;
      break;
    }
  }
  if ($manual) {
    printf("= %d %s: calibrado a mano (%.2f / %.2f), se respeta\n",
      $producto->id(), $producto->label(),
      (float) $producto->get('field_inicial_x')->value,
      (float) $producto->get('field_inicial_y')->value);
    $respetados++;
    continue;
  }

  foreach ($calibracion as $campo => $valor) {
    $producto->set($campo, $valor);
  }
  $producto->save();
  printf("+ %d %s\n", $producto->id(), $producto->label());
  $puestos++;
}

printf("\nCopiada la calibración a %d, respetados %d calibrados a mano, %d sin bordado de nombre.\n",
  $puestos, $respetados, $saltados);
