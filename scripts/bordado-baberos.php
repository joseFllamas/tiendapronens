<?php

/**
 * @file
 * Copia la calibración del babero Baby Shark (162) al resto de baberos (180).
 *
 * El cliente calibró el Baby Shark con la nube activada (fondos 225 y 226,
 * como las bolsas), el nombre en 64,27 / 76,72, fuente unicase, hilo blanco y
 * sin mayúsculas. El tamaño de letra y el ancho de la nube los dejó **vacíos**
 * a propósito (caen a los defectos de la ficha) y aquí se copian igual de
 * vacíos: copiar el defecto como número congelaría un valor que el tema puede
 * ajustar globalmente.
 *
 * La colocación vale para toda la categoría: verificado dibujando la nube
 * sobre las 47 fotos, cae dentro del babero en todas (la lámina impresa llena
 * el encuadre en la familia microfibra, y en los antiguos y los packs el
 * punto sigue sobre la prenda).
 *
 * **Solo escribe en productos sin colocación**: un babero con `field_inicial_x`
 * o `field_inicial_y` relleno cuenta como calibrado a mano y se salta entero
 * (el propio 162 y el Lorito 338, afinado a mano con 64,61 / 47,52 y letra 3).
 *
 * Uso: ddev drush php:script scripts/bordado-baberos.php
 */

// La calibración del Baby Shark (162). Sin tamaño de letra ni ancho de nube:
// vacíos también en la referencia.
$calibracion = [
  'field_bordado_fuente' => 'unicase',
  'field_bordado_color' => ['color' => '#FFFFFF', 'opacity' => NULL],
  'field_bordado_mayusculas' => 0,
  'field_inicial_x' => 64.27,
  'field_inicial_y' => 76.72,
  'field_fondos_disponibles' => [['target_id' => 225], ['target_id' => 226]],
];

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ids = \Drupal::entityQuery('commerce_product')->accessCheck(FALSE)
  ->condition('field_tipo_de_producto', 180)
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
  if (!$producto->get('field_inicial_x')->isEmpty() || !$producto->get('field_inicial_y')->isEmpty()) {
    printf("= %d %s: calibrado a mano (%.2f / %.2f), se respeta\n",
      $producto->id(), $producto->label(),
      (float) $producto->get('field_inicial_x')->value,
      (float) $producto->get('field_inicial_y')->value);
    $respetados++;
    continue;
  }

  $cambiado = FALSE;
  foreach ($calibracion as $campo => $valor) {
    if ($producto->get($campo)->isEmpty()) {
      $producto->set($campo, $valor);
      $cambiado = TRUE;
    }
  }
  if ($cambiado) {
    $producto->save();
    printf("+ %d %s\n", $producto->id(), $producto->label());
    $puestos++;
  }
}

printf("\nCopiada la calibración a %d, respetados %d calibrados a mano, %d sin bordado de nombre.\n",
  $puestos, $respetados, $saltados);
