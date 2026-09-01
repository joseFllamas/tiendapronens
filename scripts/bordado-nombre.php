<?php

/**
 * @file
 * Opciones de bordado del producto: tres para los que NO son de inicial, y la
 * rotación, que vale para los dos modos.
 *
 * En modo `inicial` la letra ya está resuelta: la rejilla A-Z, Graduate y el
 * formato (perfil + interior) que elige el cliente en la ficha. En modo `texto`
 * no había nada de eso: el nombre se pintaba siempre en la cursiva Caveat y en
 * el color de la tinta del tema, y eso no es lo que sale del taller. La bolsa
 * gris de referencia lleva "MÓNICA" en mayúsculas y en rosa, y eso es una
 * característica del producto, no una elección del cliente: se decide una vez
 * al montar la ficha y vale para todos los pedidos de ese producto.
 *
 * De ahí los tres campos, que viven junto a la colocación (x / y / tamaño)
 * porque son la misma decisión: cómo y dónde va el bordado en esa prenda.
 * - field_bordado_fuente: cursiva, unicase o parche. Es un juego cerrado (cada
 *   opción necesita su WOFF2 en el tema), así que va como lista y no como
 *   referencia a taxonomía; el vocabulario `fuente_bordado` del D7 sigue
 *   dormido, que era un selector para el CLIENTE y esto es del backoffice.
 * - field_bordado_color: el color del hilo, con color_field, que ya está en el
 *   proyecto para los formatos de la inicial.
 * - field_bordado_mayusculas: el taller borda muchos nombres en caja alta.
 *   Fuerza el texto en servidor, así que lo que se guarda en la línea de pedido
 *   es lo que se va a bordar.
 *
 * Y aparte, field_bordado_rotacion, que es colocación pura y por eso se aplica
 * también al parche de la inicial.
 *
 * Los tres son COMPARTIDOS entre traducciones (regla del proyecto: solo se
 * traduce lo que hay que redactar; una fuente, un color y un booleano no
 * dependen del idioma).
 *
 * Uso: ddev drush php:script scripts/bordado-nombre.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$displays = \Drupal::service('entity_display.repository');
$entidad = 'commerce_product';
$bundle = 'default';

// --- Storages --------------------------------------------------------------
$storages = [
  'field_bordado_fuente' => [
    'type' => 'list_string',
    'settings' => [
      'allowed_values' => [
        ['value' => 'unicase', 'label' => 'Unicase (mayúsculas y minúsculas a la misma altura)'],
        ['value' => 'script', 'label' => 'Cursiva'],
        ['value' => 'letra', 'label' => 'Parche, la misma letra que las iniciales'],
      ],
    ],
  ],
  'field_bordado_color' => [
    'type' => 'color_field_type',
    'settings' => ['format' => '#HEXHEX'],
  ],
  // En grados, y no en porcentaje como la posición y el tamaño: un porcentaje
  // necesita algo contra lo que medirse (el ancho de la foto) y una rotación no
  // lo tiene. Mismo tipo y precisión que los otros tres, para que el widget
  // pueda escribir decimales al arrastrar la barra.
  'field_bordado_rotacion' => [
    'type' => 'decimal',
    'settings' => ['precision' => 5, 'scale' => 2],
  ],
  'field_bordado_mayusculas' => [
    'type' => 'boolean',
    'settings' => [],
  ],
];
foreach ($storages as $nombre => $datos) {
  if (FieldStorageConfig::loadByName($entidad, $nombre) !== NULL) {
    echo "= storage $nombre ya existe\n";
    continue;
  }
  // Los valores de una lista no se pueden pasar en create(): Config cachea el
  // esquema antes de tener los datos completos y resuelve `label` como si fuera
  // un array ("settings.allowed_values.0.label.0 doesn't exist", Drupal
  // 11.4.4). Se crea con la lista vacía y se rellena por el config factory.
  $valores = $datos['settings']['allowed_values'] ?? NULL;
  if ($valores !== NULL) {
    $datos['settings']['allowed_values'] = [];
  }
  FieldStorageConfig::create([
    'field_name' => $nombre,
    'entity_type' => $entidad,
    'type' => $datos['type'],
    'cardinality' => 1,
    'settings' => $datos['settings'],
  ])->save();
  if ($valores !== NULL) {
    \Drupal::configFactory()
      ->getEditable('field.storage.' . $entidad . '.' . $nombre)
      ->set('settings.allowed_values', $valores)
      ->save();
  }
  echo "+ storage $nombre\n";
}

// --- Instancias ------------------------------------------------------------
// Los pesos continúan la serie de la colocación (x 9, y 10, tamaño 11): el
// formulario los agrupa en el mismo details, así que conviene que el orden de
// la config y el del grupo coincidan.
$instancias = [
  'field_bordado_rotacion' => [
    'label' => 'Rotación del bordado (grados)',
    'description' => 'Inclinación del bordado sobre la foto. 0 es horizontal, los negativos giran a la izquierda y los positivos a la derecha. Vale igual para la inicial y para el nombre.',
    'weight' => 12,
    'widget' => ['type' => 'number', 'settings' => ['placeholder' => '']],
  ],
  'field_bordado_fuente' => [
    'label' => 'Fuente del bordado',
    'description' => 'Con qué letra se borda el nombre. Sin elegir nada se borda en unicase. No se usa en los productos de inicial, que llevan siempre la letra de parche.',
    'weight' => 13,
    'widget' => ['type' => 'options_select', 'settings' => []],
    'default_value' => [['value' => 'unicase']],
  ],
  'field_bordado_color' => [
    'label' => 'Color del hilo',
    'description' => 'El color con el que se borda el nombre en este producto. Sin color, la vista previa lo pinta en el gris del texto.',
    'weight' => 14,
    'settings' => ['opacity' => 0],
    'widget' => [
      'type' => 'color_field_widget_box',
      'settings' => [
        // Carta de hilos por familias, de claro a oscuro dentro de cada fila:
        // neutros, rosas y rojos, naranjas y amarillos, verdes, y azules y
        // violetas. Están dentro los que ya usa el taller (los seis formatos de
        // la inicial) y los de las fotos de referencia, así que ampliar la carta
        // no deselecciona ningún producto ya configurado. El selector sigue
        // dejando elegir cualquier otro color a mano.
        'default_colors' => "\n"
        . "#000000,#4a4a4a,#9b9b9b,#ffffff,#f0e4cf,#7b5230\n"
        . "#f4a0c0,#f06eaa,#e6007e,#ff6f61,#d81f26,#8e1b2e\n"
        . "#f4854e,#ff9f1c,#f2c200,#cfa000,#c2551f,#8a4b1a\n"
        . "#a4c639,#7ab648,#1a7f4b,#0f5c3a,#7fd4dd,#2e9daa\n"
        . "#9fc6e7,#4986e7,#1f5fbf,#0d2b5e,#b99aff,#7b4397\n",
      ],
    ],
  ],
  'field_bordado_mayusculas' => [
    'label' => 'Bordar en mayúsculas',
    'description' => 'El nombre se pasa a mayúsculas al añadir al carrito, así que el pedido y el taller reciben ya el texto tal como se borda.',
    'weight' => 15,
    'widget' => ['type' => 'boolean_checkbox', 'settings' => ['display_label' => TRUE]],
  ],
];
$form_display = $displays->getFormDisplay($entidad, $bundle);
$view_display = $displays->getViewDisplay($entidad, $bundle);
foreach ($instancias as $nombre => $datos) {
  if (FieldConfig::loadByName($entidad, $bundle, $nombre) === NULL) {
    FieldConfig::create([
      'field_name' => $nombre,
      'entity_type' => $entidad,
      'bundle' => $bundle,
      'label' => $datos['label'],
      'description' => $datos['description'],
      'required' => FALSE,
      // Compartido entre traducciones.
      'translatable' => FALSE,
      'settings' => $datos['settings'] ?? [],
      'default_value' => $datos['default_value'] ?? [],
    ])->save();
    echo "+ campo $nombre\n";
  }
  else {
    echo "= campo $nombre ya existe\n";
  }

  $form_display->setComponent($nombre, [
    'type' => $datos['widget']['type'],
    'weight' => $datos['weight'],
    'region' => 'content',
    'settings' => $datos['widget']['settings'],
    'third_party_settings' => [],
  ]);
  // El bordado no se pinta como campo: lo dibuja la vista previa de la ficha
  // desde FichaHooks, igual que la colocación.
  $view_display->removeComponent($nombre);
}
$form_display->save();
$view_display->save();
echo "· form display y view display actualizados\n";

// La colocación ya no es solo de la inicial: el nombre bordado usa los mismos
// tres números. Se aclara qué significa el tamaño en cada modo, que es lo
// único que cambia de sentido (lado del parche / altura de la letra).
$etiquetas = [
  'field_inicial_x' => ['Posición del bordado: X (%)', 'Distancia desde el borde izquierdo de la foto, en porcentaje de su ancho.'],
  'field_inicial_y' => ['Posición del bordado: Y (%)', 'Distancia desde el borde superior de la foto, en porcentaje de su alto.'],
  'field_inicial_tamano' => ['Tamaño del bordado (%)', 'En porcentaje del ancho de la foto: en los productos de inicial es el lado del parche, y en los de nombre la altura de la letra.'],
];
foreach ($etiquetas as $nombre => [$label, $descripcion]) {
  $campo = FieldConfig::loadByName($entidad, $bundle, $nombre);
  if ($campo === NULL) {
    echo "! falta $nombre\n";
    continue;
  }
  $campo->setLabel($label)->setDescription($descripcion)->save();
  echo "· $nombre → $label\n";
}

echo "Listo.\n";
