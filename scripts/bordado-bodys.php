<?php

/**
 * @file
 * Configura el bordado del nombre en los bodys de bebé (categoría 177).
 *
 * Replica la calibración que el cliente hizo sobre el body de esmoquin (76):
 * fuente unicase, sin mayúsculas, nombre centrado en el pecho (50,36 / 30,30)
 * con 2,5 % de altura de letra. El encuadre del catálogo JHK es el mismo en
 * los 41 bodys, así que la colocación vale para todos; las dos excepciones
 * medidas sobre la foto son el iPood (55), cuyo rótulo ocupa justo esa zona y
 * el nombre baja a la barriga, y el Perezoso (56), con la cabeza del oso en el
 * pecho, donde el nombre sube por encima del dibujo.
 *
 * El color del hilo se decide por producto, medido con ImageMagick sobre la
 * franja de tela donde cae el nombre y el estampado de cada foto:
 * - Prenda oscura (negro, marino con print blanco, rojo, azulón): blanco,
 *   igual que el esmoquin de referencia.
 * - Prenda clara con estampado monocromo: el hilo repite el color del print
 *   (negro sobre los prints negros, gris grafito en el iPood, frambuesa en
 *   los prints magenta, verde bosque en el Young wild).
 * - Lisos: el complementario de la tela dentro de la carta de 30 hilos
 *   (verde agua sobre rosa, naranja sobre celeste, violeta sobre amarillo,
 *   marino sobre naranja, ámbar sobre azulón y marino), y blanco donde el
 *   complementario no contrasta (fucsia, rojo, negro).
 *
 * **Solo escribe donde está vacío**: lo calibrado a mano (el propio 76) no se
 * pisa, y volver a lanzarlo no deshace ningún ajuste posterior.
 *
 * Uso: ddev drush php:script scripts/bordado-bodys.php
 */

// La calibración del esmoquin, común a toda la categoría.
$base = [
  'field_bordado_fuente' => 'unicase',
  'field_bordado_mayusculas' => 0,
  'field_inicial_x' => 50.36,
  'field_inicial_y' => 30.30,
  'field_inicial_tamano' => 2.50,
];

// Hilo por producto (hex de la carta de 30) y, donde el estampado ocupa la
// zona del pecho, la colocación alternativa medida sobre la foto.
$porProducto = [
  45 => ['color' => '#ffffff'],                // Vader: negro, print blanco.
  46 => ['color' => '#000000'],                // Amor infinito blanco: print negro.
  47 => ['color' => '#ffffff'],                // Baby Rock: negro, print blanco.
  48 => ['color' => '#ffffff'],                // El Padrino: negro, print blanco.
  49 => ['color' => '#ffffff'],                // Milk Mom Rock: negro, print blanco.
  50 => ['color' => '#2e9daa'],                // Liso rosa: verde agua (complementario).
  51 => ['color' => '#c2551f'],                // Liso celeste: naranja oscuro (complementario).
  52 => ['color' => '#2e9daa'],                // Liso blanco: turquesa de la marca.
  53 => ['color' => '#ffffff'],                // Liso negro: blanco.
  54 => ['color' => '#ffffff'],                // Liso rojo: blanco.
  55 => ['color' => '#4a4a4a', 'y' => 66.00],  // iPood: gris del print; el rótulo ocupa el pecho, el nombre baja bajo la rueda.
  56 => ['color' => '#000000', 'y' => 24.00],  // Perezoso: la cabeza del oso llega al pecho, el nombre sube encima del dibujo.
  57 => ['color' => '#000000'],                // Low Battery: blanco, print negro.
  58 => ['color' => '#ffffff'],                // On Off: negro, print blanco.
  59 => ['color' => '#000000'],                // Hombrecito: blanco, print negro.
  60 => ['color' => '#000000'],                // Love: rosa claro, print negro.
  61 => ['color' => '#e6007e'],                // Amor infinito: rosa, print magenta.
  62 => ['color' => '#0d2b5e'],                // Feliz: celeste con smiley amarillo, marino.
  68 => ['color' => '#000000'],                // Pink Ladies: rosa claro, print negro.
  69 => ['color' => '#e6007e'],                // Princesa: rosa claro, corona fucsia.
  77 => ['color' => '#000000'],                // Keep Calm: rosa claro, print negro.
  241 => ['color' => '#ffffff'],               // Liso fucsia: blanco (el verde complementario no contrasta).
  242 => ['color' => '#7b4397'],               // Liso amarillo: violeta (complementario).
  243 => ['color' => '#0d2b5e'],               // Liso naranja: marino (complementario).
  244 => ['color' => '#ff9f1c'],               // Liso marino: ámbar (complementario).
  245 => ['color' => '#0d2b5e'],               // Liso gris: marino.
  246 => ['color' => '#ff9f1c'],               // Liso azulón: ámbar (complementario).
  247 => ['color' => '#ffffff'],               // Monstruo galletas: azulón, print negro.
  248 => ['color' => '#ffffff'],               // Monstruo azul: azulón, print negro.
  249 => ['color' => '#ffffff'],               // Grease Rydell: rojo, print blanco.
  250 => ['color' => '#000000'],               // Monstruo rojo: rojo, print negro.
  251 => ['color' => '#000000'],               // Monstruo amarillo: amarillo, print negro.
  252 => ['color' => '#000000'],               // Monstruo gris: gris, print negro.
  253 => ['color' => '#000000'],               // Monstruo rosa: fucsia, print negro.
  254 => ['color' => '#e6007e'],               // Good Vibes: gris, script magenta.
  255 => ['color' => '#000000'],               // Wild Leopard: amarillo, print negro.
  256 => ['color' => '#0f5c3a'],               // Young wild: gris, print verde bosque.
  257 => ['color' => '#9b9b9b'],               // Not tired: marino, print gris plata.
  258 => ['color' => '#000000'],               // Girl Power: fucsia, print negro.
  259 => ['color' => '#000000'],               // Wildflower: rosa claro, print negro.
];

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$ids = \Drupal::entityQuery('commerce_product')->accessCheck(FALSE)
  ->condition('field_tipo_de_producto', 177)
  ->condition('status', 1)
  ->execute();

$puestos = $saltados = 0;
foreach ($almacen->loadMultiple($ids) as $producto) {
  $id = (int) $producto->id();
  if (!isset($porProducto[$id])) {
    printf("= %d %s: sin decisión, no se toca\n", $id, $producto->label());
    $saltados++;
    continue;
  }
  if ($producto->get('field_modo_personalizacion')->value !== 'texto') {
    printf("= %d %s: no es modo texto, no se toca\n", $id, $producto->label());
    $saltados++;
    continue;
  }

  $valores = $base;
  $valores['field_inicial_y'] = $porProducto[$id]['y'] ?? $base['field_inicial_y'];
  $valores['field_bordado_color'] = ['color' => $porProducto[$id]['color'], 'opacity' => NULL];

  $cambiado = FALSE;
  foreach ($valores as $campo => $valor) {
    if ($producto->get($campo)->isEmpty()) {
      $producto->set($campo, $valor);
      $cambiado = TRUE;
    }
  }
  if ($cambiado) {
    $producto->save();
    printf("+ %d %s: hilo %s%s\n", $id, $producto->label(),
      $porProducto[$id]['color'],
      isset($porProducto[$id]['y']) ? sprintf(' (y=%.2f)', $porProducto[$id]['y']) : '');
    $puestos++;
  }
  else {
    printf("= %d %s: ya configurado, no se pisa\n", $id, $producto->label());
    $saltados++;
  }
}

printf("\nConfigurados %d, sin tocar %d.\n", $puestos, $saltados);
