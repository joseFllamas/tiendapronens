<?php

/**
 * @file
 * Fondos del bordado: la nube sobre la que va el nombre en las mochilas y las
 * bolsas.
 *
 * Las mochilas, las bolsas impermeables y las de guardería no bordan el nombre
 * sobre la tela: lo bordan dentro de una nube, que hoy viene en dos colores y
 * mañana en los que haga falta. De ahí un vocabulario y no una lista cerrada
 * como field_bordado_fuente: añadir un color o una forma nueva es crear un
 * término con su foto, sin tocar código ni desplegar nada.
 *
 * El modelo, calcado del de los extras (vocabulario + qué ofrece cada producto
 * + qué eligió cada línea de pedido):
 * - Vocabulario `fondos_bordado`, con la foto del fondo (PNG con transparencia),
 *   el color con el que se borda el nombre encima y la caja de texto que cabe
 *   dentro de la forma, en porcentaje del propio fondo. La caja es lo que
 *   permite que un fondo con otra silueta encaje sin tocar el CSS.
 * - `field_fondos_disponibles` en el producto: qué fondos ofrece ESE producto,
 *   con casillas, de modo que la nube no aparece en un polo sin tocar código.
 *   Y `field_fondo_tamano`, el ancho de la nube en % del ancho de la foto, que
 *   es lo que se coloca en el backoffice junto con la posición.
 * - `field_fondo_bordado` en la línea de pedido: cuál eligió el cliente, que es
 *   lo que va a leer el taller.
 *
 * Todo lo del producto va COMPARTIDO entre traducciones (regla del proyecto:
 * solo se traduce lo que hay que redactar; una referencia y un número no
 * dependen del idioma). El vocabulario tampoco es traducible, igual que
 * `extras` y `color_letra`.
 *
 * El fondo NO cuesta nada: no hay ajuste de precio, al revés que los extras.
 *
 * Uso: ddev drush php:script scripts/fondos-bordado.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

$displays = \Drupal::service('entity_display.repository');
$vid = 'fondos_bordado';

// --- Vocabulario -----------------------------------------------------------
if (Vocabulary::load($vid) === NULL) {
  Vocabulary::create([
    'vid' => $vid,
    'name' => 'Fondos del bordado',
    'description' => 'La forma sobre la que se borda el nombre: la nube de las mochilas y las bolsas, en sus distintos colores.',
  ])->save();
  echo "+ vocabulario $vid\n";
}
else {
  echo "= vocabulario $vid ya existe\n";
}

// --- Storages nuevos -------------------------------------------------------
// field_imagen y field_color ya existen en taxonomy_term (los usan `extras` y
// `color_letra`): aquí solo se instancian en el bundle nuevo.
$storages = [
  ['entity' => 'taxonomy_term', 'name' => 'field_caja_ancho', 'type' => 'decimal', 'cardinality' => 1, 'settings' => ['precision' => 5, 'scale' => 2]],
  ['entity' => 'taxonomy_term', 'name' => 'field_caja_alto', 'type' => 'decimal', 'cardinality' => 1, 'settings' => ['precision' => 5, 'scale' => 2]],
  // El ancho de la nube sobre la foto, en % del ancho de la foto, igual que la
  // posición y el tamaño del bordado: la misma foto se sirve en varios estilos
  // y a varios anchos de pantalla, así que en píxeles solo valdría para uno.
  ['entity' => 'commerce_product', 'name' => 'field_fondo_tamano', 'type' => 'decimal', 'cardinality' => 1, 'settings' => ['precision' => 5, 'scale' => 2]],
  ['entity' => 'commerce_product', 'name' => 'field_fondos_disponibles', 'type' => 'entity_reference', 'cardinality' => -1, 'settings' => ['target_type' => 'taxonomy_term']],
  ['entity' => 'commerce_order_item', 'name' => 'field_fondo_bordado', 'type' => 'entity_reference', 'cardinality' => 1, 'settings' => ['target_type' => 'taxonomy_term']],
];
foreach ($storages as $datos) {
  if (FieldStorageConfig::loadByName($datos['entity'], $datos['name']) !== NULL) {
    echo "= storage {$datos['entity']}.{$datos['name']} ya existe\n";
    continue;
  }
  FieldStorageConfig::create([
    'field_name' => $datos['name'],
    'entity_type' => $datos['entity'],
    'type' => $datos['type'],
    'cardinality' => $datos['cardinality'],
    'settings' => $datos['settings'],
  ])->save();
  echo "+ storage {$datos['entity']}.{$datos['name']}\n";
}

// --- Campos del término ----------------------------------------------------
$referencia = static fn (string $bundle): array => [
  'handler' => 'default:taxonomy_term',
  'handler_settings' => ['target_bundles' => [$bundle => $bundle], 'auto_create' => FALSE],
];

$campos = [
  [
    'entity' => 'taxonomy_term',
    'bundle' => $vid,
    'name' => 'field_imagen',
    'label' => 'Foto del fondo',
    'description' => 'El PNG del fondo, CON TRANSPARENCIA: se pinta encima de la foto del producto, así que un fondo con cuadros grises o con recuadro blanco se vería tal cual.',
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => ['image' => 'image']]],
    'widget' => ['type' => 'entity_reference_autocomplete', 'settings' => ['match_operator' => 'CONTAINS', 'size' => 60, 'placeholder' => '']],
    'weight' => 1,
  ],
  [
    'entity' => 'taxonomy_term',
    'bundle' => $vid,
    'name' => 'field_color',
    'label' => 'Color del nombre sobre este fondo',
    'description' => 'El hilo con el que se borda el nombre encima de este fondo. Sin color se usa el del producto (Color del hilo), que es lo que se borda cuando el nombre va directamente sobre la tela.',
    'settings' => ['opacity' => 0],
    'widget' => [
      'type' => 'color_field_widget_box',
      'settings' => [
        // La misma carta de hilos que field_bordado_color: es el mismo taller y
        // los mismos carretes. Ver scripts/bordado-nombre.php.
        'default_colors' => "\n"
        . "#000000,#4a4a4a,#9b9b9b,#ffffff,#f0e4cf,#7b5230\n"
        . "#f4a0c0,#f06eaa,#e6007e,#ff6f61,#d81f26,#8e1b2e\n"
        . "#f4854e,#ff9f1c,#f2c200,#cfa000,#c2551f,#8a4b1a\n"
        . "#a4c639,#7ab648,#1a7f4b,#0f5c3a,#7fd4dd,#2e9daa\n"
        . "#9fc6e7,#4986e7,#1f5fbf,#0d2b5e,#b99aff,#7b4397\n",
      ],
    ],
    'weight' => 2,
  ],
  [
    'entity' => 'taxonomy_term',
    'bundle' => $vid,
    'name' => 'field_caja_ancho',
    'label' => 'Ancho útil del fondo (%)',
    // El 50 no es a ojo: medido sobre el alfa de la nube, una caja centrada no
    // puede pasar del 58 % sin salirse. La nube es asimétrica (el lóbulo de
    // arriba a la derecha se queda en el 79 % del ancho mientras la panza llega
    // al 99 %), así que manda ese lóbulo. El resto, hasta el 50, es aire.
    'description' => 'Cuánto del ancho del fondo puede ocupar el nombre, en porcentaje. Una nube no es un rectángulo: el texto tiene que quedarse dentro de la panza y no llegar a los bordes. Sin valor se usa el 50 %.',
    'default_value' => [['value' => 50]],
    'widget' => ['type' => 'number', 'settings' => ['placeholder' => '']],
    'weight' => 3,
  ],
  [
    'entity' => 'taxonomy_term',
    'bundle' => $vid,
    'name' => 'field_caja_alto',
    'label' => 'Alto útil del fondo (%)',
    'description' => 'Lo mismo para el alto. Es el tope al que se encoge el nombre cuando es largo. Sin valor se usa el 34 %.',
    'default_value' => [['value' => 34]],
    'widget' => ['type' => 'number', 'settings' => ['placeholder' => '']],
    'weight' => 4,
  ],
  // --- Campos del producto ---
  [
    'entity' => 'commerce_product',
    'bundle' => 'default',
    'name' => 'field_fondos_disponibles',
    'label' => 'Fondos del bordado que ofrece',
    'description' => 'Sobre qué fondos se puede bordar el nombre en este producto. Si no marcas ninguno, el nombre se borda directamente sobre la tela y en la ficha no aparece el selector. El primero marcado es el que sale elegido de entrada.',
    'settings' => $referencia($vid),
    'widget' => ['type' => 'options_buttons', 'settings' => []],
    'weight' => 8,
  ],
  [
    'entity' => 'commerce_product',
    'bundle' => 'default',
    'name' => 'field_fondo_tamano',
    'label' => 'Ancho del fondo (%)',
    'description' => 'Lo que mide la nube de ancho, en porcentaje del ancho de la foto. El alto sale solo de la proporción de la foto del fondo. Sin valor se usa el 34 %.',
    'default_value' => [['value' => 34]],
    'widget' => ['type' => 'number', 'settings' => ['placeholder' => '']],
    'weight' => 12,
  ],
  // --- Campo de la línea de pedido ---
  [
    'entity' => 'commerce_order_item',
    'bundle' => 'default',
    'name' => 'field_fondo_bordado',
    'label' => 'Fondo del bordado',
    'description' => '',
    'settings' => $referencia($vid),
    'widget' => ['type' => 'options_buttons', 'settings' => []],
    'weight' => 3,
    'form_mode' => 'add_to_cart',
  ],
];

foreach ($campos as $datos) {
  $existente = FieldConfig::loadByName($datos['entity'], $datos['bundle'], $datos['name']);
  if ($existente === NULL) {
    FieldConfig::create([
      'field_name' => $datos['name'],
      'entity_type' => $datos['entity'],
      'bundle' => $datos['bundle'],
      'label' => $datos['label'],
      'description' => $datos['description'],
      'required' => FALSE,
      // Compartido entre traducciones, sin excepción: aquí no hay nada que
      // redactar, solo referencias y números.
      'translatable' => FALSE,
      'settings' => $datos['settings'] ?? [],
      'default_value' => $datos['default_value'] ?? [],
    ])->save();
    echo "+ campo {$datos['entity']}.{$datos['bundle']}.{$datos['name']}\n";
  }
  else {
    $existente->setLabel($datos['label'])->setDescription($datos['description'])->save();
    echo "= campo {$datos['entity']}.{$datos['bundle']}.{$datos['name']} ya existe\n";
  }

  $modo = $datos['form_mode'] ?? 'default';
  $form_display = $displays->getFormDisplay($datos['entity'], $datos['bundle'], $modo);
  $form_display->setComponent($datos['name'], [
    'type' => $datos['widget']['type'],
    'weight' => $datos['weight'],
    'region' => 'content',
    'settings' => $datos['widget']['settings'],
    'third_party_settings' => [],
  ])->save();

  // Ninguno de estos se pinta como campo suelto: el fondo lo dibuja la vista
  // previa de la ficha y el resto son datos de colocación.
  $view_display = $displays->getViewDisplay($datos['entity'], $datos['bundle']);
  $view_display->removeComponent($datos['name'])->save();
}

// El ancho del fondo se cuela entre el tamaño del bordado (11) y la rotación,
// porque es colocación y va en el mismo grupo del formulario. Los que venían
// detrás corren un puesto.
$form_producto = $displays->getFormDisplay('commerce_product', 'default');
foreach (['field_bordado_rotacion' => 13, 'field_bordado_fuente' => 14, 'field_bordado_color' => 15, 'field_bordado_mayusculas' => 16] as $nombre => $peso) {
  $componente = $form_producto->getComponent($nombre);
  if ($componente !== NULL) {
    $componente['weight'] = $peso;
    $form_producto->setComponent($nombre, $componente);
  }
}
$form_producto->save();
echo "· pesos del formulario del producto reordenados\n";

// --- Fotos de los fondos ---------------------------------------------------
// Los PNG viven en fondos/ del repo y se copian a public://fondos/. Se busca por
// URI antes de crear nada, así que volver a lanzar el script no duplica medias.
$origen = \Drupal::root() . '/../fondos';
$destino = 'public://fondos';
\Drupal::service('file_system')->prepareDirectory($destino, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

$fondos = [
  'nube-marron.png' => [
    'nombre' => 'Nube marrón',
    'alt' => 'Nube marrón para el nombre bordado',
    // El nombre va en blanco dentro de la nube, como en la foto de tienda.
    'color' => '#FFFFFF',
    'peso' => 0,
  ],
  'nube-rosa.png' => [
    'nombre' => 'Nube rosa',
    'alt' => 'Nube rosa para el nombre bordado',
    'color' => '#FFFFFF',
    'peso' => 1,
  ],
];

$almacen_media = \Drupal::entityTypeManager()->getStorage('media');
$almacen_file = \Drupal::entityTypeManager()->getStorage('file');
$almacen_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

foreach ($fondos as $fichero => $datos) {
  $ruta = "$origen/$fichero";
  if (!is_file($ruta)) {
    echo "! falta $ruta\n";
    continue;
  }
  $uri = "$destino/$fichero";
  if (!file_exists($uri)) {
    \Drupal::service('file_system')->copy($ruta, $uri, \Drupal\Core\File\FileExists::Replace);
  }
  $previos = $almacen_file->loadByProperties(['uri' => $uri]);
  $file = $previos !== [] ? reset($previos) : File::create(['uri' => $uri, 'status' => 1, 'uid' => 1]);
  if ($file->id() === NULL) {
    $file->save();
  }

  $medias = $almacen_media->loadByProperties(['name' => $datos['nombre'], 'bundle' => 'image']);
  $media = $medias !== [] ? reset($medias) : Media::create([
    'bundle' => 'image',
    'uid' => 1,
    'status' => 1,
    'name' => $datos['nombre'],
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $datos['alt'], 'title' => ''],
  ]);
  if ($media->id() === NULL) {
    $media->save();
    echo "+ media {$datos['nombre']} ({$media->id()})\n";
  }
  else {
    echo "= media {$datos['nombre']} ({$media->id()}) ya existe\n";
  }

  $terminos = $almacen_term->loadByProperties(['vid' => $vid, 'name' => $datos['nombre']]);
  if ($terminos !== []) {
    echo "= término {$datos['nombre']} ya existe\n";
    continue;
  }
  $termino = Term::create([
    'vid' => $vid,
    'name' => $datos['nombre'],
    'weight' => $datos['peso'],
    'status' => 1,
    'field_imagen' => ['target_id' => $media->id()],
    'field_color' => ['color' => $datos['color'], 'opacity' => NULL],
    'field_caja_ancho' => 50,
    'field_caja_alto' => 34,
  ]);
  $termino->save();
  echo "+ término {$datos['nombre']} ({$termino->id()})\n";
}

echo "Listo.\n";
