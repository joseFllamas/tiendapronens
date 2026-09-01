<?php

/**
 * @file
 * Abre las páginas del CMS a Paragraphs y añade los tipos que pide el
 * prototipo de "Quiénes somos" (design/Tienda Pronens.dc.html → Nosotras).
 *
 * Decisión: NO se crea un tipo de contenido "landing". El tipo `page` ya
 * existe, ya tiene alias, menú y traducciones, y lo único que le faltaba era
 * el campo de secciones. Se le añade `field_secciones` reutilizando el
 * storage de la home, así que cualquier página puede montarse con párrafos
 * (y las que no lo necesiten siguen usando `body`). El campo acepta también
 * los párrafos de la home: una página puede reutilizar hero, mosaico,
 * best_sellers o newsletter sin duplicar nada.
 *
 * Tipos nuevos (los que la home no cubre):
 * - cifras / cifra: la franja de +40 años, +500 productos…
 * - texto_medios: texto a dos columnas con las fotos al lado.
 * - valores / valor: la rejilla de 4 tarjetas con icono.
 * - cta: la banda oscura de B2B y el cierre centrado (mismo tipo, campo
 *   `field_estilo` decide cuál de los dos).
 *
 * Reutilizados tal cual: hero (con foto de fondo y sin CTAs) y
 * pasos_personalizacion + paso para "Cómo lo hacemos"; a `paso` se le añade
 * un icono opcional, que la home deja vacío.
 *
 * Traducibilidad, según la regla del proyecto: se traduce lo que hay que
 * redactar (títulos, textos, etiquetas de enlace) y se comparten las
 * referencias (imágenes, iconos, estilo), que no dependen del idioma.
 *
 * Uso: ddev drush php:script scripts/landing-paragraphs.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\paragraphs\Entity\ParagraphsType;

$displays = \Drupal::service('entity_display.repository');

// --- Campos nuevos ---------------------------------------------------------
$storages = [
  'field_icono' => [
    'type' => 'list_string',
    'cardinality' => 1,
    'settings' => [
      'allowed_values' => [
        ['value' => 'paleta', 'label' => 'Paleta (color y diseño)'],
        ['value' => 'escudo', 'label' => 'Escudo (calidad)'],
        ['value' => 'chispas', 'label' => 'Chispas (personalización)'],
        ['value' => 'regla', 'label' => 'Regla (patronaje y tallas)'],
        ['value' => 'tijeras', 'label' => 'Tijeras (corte y tejidos)'],
        ['value' => 'fabrica', 'label' => 'Fábrica (taller propio)'],
        ['value' => 'camion', 'label' => 'Camión (envíos)'],
        ['value' => 'corazon', 'label' => 'Corazón (cariño)'],
        ['value' => 'edificio', 'label' => 'Edificio (colegios y empresas)'],
      ],
    ],
  ],
  'field_imagenes' => [
    'type' => 'entity_reference',
    'cardinality' => -1,
    'settings' => ['target_type' => 'media'],
  ],
  'field_estilo' => [
    'type' => 'list_string',
    'cardinality' => 1,
    'settings' => [
      'allowed_values' => [
        ['value' => 'oscuro', 'label' => 'Banda oscura'],
        ['value' => 'centrado', 'label' => 'Cierre centrado'],
      ],
    ],
  ],
];
foreach ($storages as $nombre => $datos) {
  $valores_lista = $datos['settings']['allowed_values'] ?? NULL;
  if (FieldStorageConfig::loadByName('paragraph', $nombre) !== NULL) {
    if ($valores_lista !== NULL) {
      \Drupal::configFactory()
        ->getEditable('field.storage.paragraph.' . $nombre)
        ->set('settings.allowed_values', $valores_lista)
        ->save();
    }
    echo "= storage $nombre ya existe\n";
    continue;
  }
  // Los valores de una lista no se pueden pasar en create(): al guardar la
  // entidad, Config cachea el esquema antes de tener los datos completos,
  // resuelve `label` como si fuera un array y aborta con "the configuration
  // property settings.allowed_values.0.label.0 doesn't exist" (Drupal 11.4.4;
  // pasa con cualquier entidad y también en un segundo save). Se crea el
  // storage con la lista vacía y se rellena por el config factory, que sí
  // parte de los datos completos.
  $valores = $datos['settings']['allowed_values'] ?? NULL;
  if ($valores !== NULL) {
    $datos['settings']['allowed_values'] = [];
    $datos['settings']['allowed_values_function'] = '';
  }
  FieldStorageConfig::create([
    'field_name' => $nombre,
    'entity_type' => 'paragraph',
    'type' => $datos['type'],
    'cardinality' => $datos['cardinality'],
    'settings' => $datos['settings'],
  ])->save();
  if ($valores !== NULL) {
    \Drupal::configFactory()
      ->getEditable('field.storage.paragraph.' . $nombre)
      ->set('settings.allowed_values', $valores)
      ->save();
  }
  echo "+ storage $nombre\n";
}

// --- Plantillas de campo ---------------------------------------------------
// Cada una devuelve [instancia, widget, formatter]; el formatter a NULL
// esconde el campo del display (lo pinta el twig desde el preprocess).
$texto_corto = fn(string $label, int $peso, string $desc = '') => [
  ['label' => $label, 'description' => $desc, 'translatable' => TRUE],
  ['type' => 'string_textfield', 'weight' => $peso],
  ['type' => 'string', 'label' => 'hidden', 'weight' => $peso, 'settings' => ['link_to_entity' => FALSE, 'link_rel' => 'canonical']],
];
$texto_largo = fn(string $label, int $peso, string $desc = '') => [
  ['label' => $label, 'description' => $desc, 'translatable' => TRUE],
  ['type' => 'string_textarea', 'weight' => $peso, 'settings' => ['rows' => 4]],
  ['type' => 'basic_string', 'label' => 'hidden', 'weight' => $peso],
];
$texto_rico = fn(string $label, int $peso, string $desc = '') => [
  ['label' => $label, 'description' => $desc, 'translatable' => TRUE],
  ['type' => 'text_textarea', 'weight' => $peso, 'settings' => ['rows' => 6]],
  ['type' => 'text_default', 'label' => 'hidden', 'weight' => $peso],
];
$enlace = fn(string $label, int $peso, string $desc = '') => [
  ['label' => $label, 'description' => $desc, 'translatable' => TRUE],
  ['type' => 'link_default', 'weight' => $peso],
  NULL,
];
// Compartido, como los demás campos de párrafos del sitio: la estructura es
// única y lo que se traduce es el texto de dentro de cada hijo. Traducible
// significaría traducción asimétrica, o sea rehacer los items en cada idioma,
// y además TMGMT trata el campo asimétrico de otra forma (duplica los hijos
// por idioma en vez de traducirlos).
$items = fn(string $label, int $peso, array $bundles) => [
  [
    'label' => $label,
    'translatable' => FALSE,
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => ['target_bundles' => array_combine($bundles, $bundles), 'negate' => 0],
    ],
  ],
  ['type' => 'paragraphs', 'weight' => $peso, 'settings' => ['edit_mode' => 'open']],
  ['type' => 'entity_reference_revisions_entity_view', 'label' => 'hidden', 'weight' => $peso, 'settings' => ['view_mode' => 'default', 'link' => '']],
];
$imagenes = fn(string $label, int $peso, string $desc = '') => [
  [
    'label' => $label,
    'description' => $desc,
    'translatable' => FALSE,
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => ['image' => 'image']]],
  ],
  ['type' => 'media_library_widget', 'weight' => $peso],
  NULL,
];
$lista = fn(string $label, int $peso, string $desc = '') => [
  ['label' => $label, 'description' => $desc, 'translatable' => FALSE],
  ['type' => 'options_select', 'weight' => $peso],
  NULL,
];

// --- Tipos de párrafo ------------------------------------------------------
$tipos = [
  'cifras' => [
    'label' => 'Cifras',
    'descripcion' => 'Franja con las cifras de la marca (+40 años, +500 productos…).',
    'campos' => [
      'field_items' => $items('Cifras', 0, ['cifra']),
    ],
  ],
  'cifra' => [
    'label' => 'Cifra',
    'descripcion' => 'Un dato de la franja de cifras.',
    'campos' => [
      'field_titulo' => $texto_corto('Cifra', 0, 'El número tal cual: +40, 100%, 3…'),
      'field_texto' => $texto_largo('Etiqueta', 1, 'Qué mide la cifra. Se pinta en mayúsculas.'),
    ],
  ],
  'texto_medios' => [
    'label' => 'Texto con imágenes',
    'descripcion' => 'Bloque a dos columnas: texto a un lado y una o dos fotos al otro.',
    'campos' => [
      'field_eyebrow' => $texto_corto('Antetítulo', 0),
      'field_titulo' => $texto_corto('Título', 1),
      'field_titulo_enfasis' => $texto_corto('Segunda línea del título', 2, 'Se pinta en cursiva fina debajo del título.'),
      'field_texto_largo' => $texto_rico('Texto', 3),
      'field_imagenes' => $imagenes('Imágenes', 4, 'Una imagen ocupa la columna entera; con dos se pintan escalonadas.'),
    ],
  ],
  'valores' => [
    'label' => 'Valores',
    'descripcion' => 'Rejilla de tarjetas con icono, título y texto.',
    'campos' => [
      'field_eyebrow' => $texto_corto('Antetítulo', 0),
      'field_titulo' => $texto_corto('Título', 1),
      'field_texto' => $texto_largo('Entradilla', 2),
      'field_items' => $items('Valores', 3, ['valor']),
    ],
  ],
  'valor' => [
    'label' => 'Valor',
    'descripcion' => 'Tarjeta de la rejilla de valores.',
    'campos' => [
      'field_icono' => $lista('Icono', 0),
      'field_titulo' => $texto_corto('Título', 1),
      'field_texto' => $texto_largo('Texto', 2),
    ],
  ],
  'cta' => [
    'label' => 'Llamada a la acción',
    'descripcion' => 'Bloque de cierre con título y hasta dos botones.',
    'campos' => [
      'field_estilo' => $lista('Estilo', 0, 'Banda oscura (texto a la izquierda, botón a la derecha) o cierre centrado.'),
      'field_icono' => $lista('Icono del antetítulo', 1, 'Opcional.'),
      'field_eyebrow' => $texto_corto('Antetítulo', 2),
      'field_titulo' => $texto_corto('Título', 3),
      'field_texto' => $texto_largo('Texto', 4),
      'field_enlace' => $enlace('Botón principal', 5),
      'field_enlace_secundario' => $enlace('Botón secundario', 6),
    ],
  ],
];

foreach ($tipos as $id => $tipo) {
  if (ParagraphsType::load($id) === NULL) {
    ParagraphsType::create([
      'id' => $id,
      'label' => $tipo['label'],
      'description' => $tipo['descripcion'],
    ])->save();
    echo "+ tipo $id\n";
  }

  $form_display = $displays->getFormDisplay('paragraph', $id, 'default');
  $view_display = $displays->getViewDisplay('paragraph', $id, 'default');
  foreach (['created', 'status'] as $base) {
    $form_display->removeComponent($base);
  }

  foreach ($tipo['campos'] as $campo => [$instancia, $widget, $formatter]) {
    if (FieldConfig::loadByName('paragraph', $id, $campo) === NULL) {
      FieldConfig::create([
        'field_name' => $campo,
        'entity_type' => 'paragraph',
        'bundle' => $id,
        'label' => $instancia['label'],
        'description' => $instancia['description'] ?? '',
        'translatable' => $instancia['translatable'],
        'settings' => $instancia['settings'] ?? [],
      ])->save();
      echo "  + $id.$campo\n";
    }
    $form_display->setComponent($campo, $widget);
    if ($formatter === NULL) {
      $view_display->removeComponent($campo);
    }
    else {
      $view_display->setComponent($campo, $formatter);
    }
  }
  $form_display->save();
  $view_display->save();

  // El párrafo es traducible; el idioma lo hereda del nodo que lo contiene.
  if (ContentLanguageSettings::loadByEntityTypeBundle('paragraph', $id)->isNew()) {
    $ajustes = ContentLanguageSettings::loadByEntityTypeBundle('paragraph', $id);
    $ajustes->setDefaultLangcode('site_default')
      ->setLanguageAlterable(FALSE)
      ->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->save();
    echo "  + traducible $id\n";
  }
}

// --- Entradilla opcional en la sección de pasos ----------------------------
// El prototipo de "Cómo lo hacemos" lleva un párrafo entre el título y los
// pasos; la home no lo usa y lo deja vacío.
if (FieldConfig::loadByName('paragraph', 'pasos_personalizacion', 'field_texto') === NULL) {
  FieldConfig::create([
    'field_name' => 'field_texto',
    'entity_type' => 'paragraph',
    'bundle' => 'pasos_personalizacion',
    'label' => 'Entradilla',
    'description' => 'Opcional. Va entre el título y los pasos.',
    'translatable' => TRUE,
  ])->save();
  $form = $displays->getFormDisplay('paragraph', 'pasos_personalizacion', 'default');
  $form->setComponent('field_texto', ['type' => 'string_textarea', 'weight' => 3, 'settings' => ['rows' => 3]])->save();
  $displays->getViewDisplay('paragraph', 'pasos_personalizacion', 'default')
    ->setComponent('field_texto', ['type' => 'basic_string', 'label' => 'hidden', 'weight' => 3])
    ->save();
  echo "+ pasos_personalizacion.field_texto\n";
}

// --- Icono opcional en los pasos ------------------------------------------
if (FieldConfig::loadByName('paragraph', 'paso', 'field_icono') === NULL) {
  FieldConfig::create([
    'field_name' => 'field_icono',
    'entity_type' => 'paragraph',
    'bundle' => 'paso',
    'label' => 'Icono',
    'description' => 'Opcional. Sin icono se pinta solo el número.',
    'translatable' => FALSE,
  ])->save();
  $form = $displays->getFormDisplay('paragraph', 'paso', 'default');
  $form->setComponent('field_icono', ['type' => 'options_select', 'weight' => -1])->save();
  $displays->getViewDisplay('paragraph', 'paso', 'default')->removeComponent('field_icono')->save();
  echo "+ paso.field_icono\n";
}

// --- Secciones en las páginas ---------------------------------------------
// Cualquier párrafo de sección vale, incluidos los que ya usa la home.
$secciones = [
  'hero', 'cifras', 'texto_medios', 'valores', 'pasos_personalizacion',
  'cta', 'beneficios', 'mosaico_categorias', 'best_sellers', 'historia',
  'newsletter',
];
if (FieldConfig::loadByName('node', 'page', 'field_secciones') === NULL) {
  FieldConfig::create([
    'field_name' => 'field_secciones',
    'entity_type' => 'node',
    'bundle' => 'page',
    'label' => 'Secciones',
    'description' => 'Deja el cuerpo vacío si montas la página con secciones.',
    'translatable' => FALSE,
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => ['target_bundles' => array_combine($secciones, $secciones), 'negate' => 0],
    ],
  ])->save();
  echo "+ page.field_secciones\n";
}
else {
  // Mantiene la lista al día si se añaden tipos nuevos más adelante.
  $campo = FieldConfig::loadByName('node', 'page', 'field_secciones');
  $ajustes = $campo->get('settings');
  $ajustes['handler_settings']['target_bundles'] = array_combine($secciones, $secciones);
  $campo->set('settings', $ajustes)->save();
  echo "= page.field_secciones actualizado\n";
}

$form = $displays->getFormDisplay('node', 'page', 'default');
$form->setComponent('field_secciones', [
  'type' => 'paragraphs',
  'weight' => 7,
  'settings' => [
    'title' => 'Sección',
    'title_plural' => 'Secciones',
    'edit_mode' => 'closed',
    'closed_mode' => 'summary',
    'autocollapse' => 'none',
    'closed_mode_threshold' => 0,
    'add_mode' => 'dropdown',
    'form_display_mode' => 'default',
    'default_paragraph_type' => '',
    'features' => ['collapse_edit_all' => 'collapse_edit_all', 'duplicate' => 'duplicate'],
  ],
])->save();
$displays->getViewDisplay('node', 'page', 'default')
  ->setComponent('field_secciones', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'weight' => -1,
    'settings' => ['view_mode' => 'default', 'link' => ''],
  ])
  ->save();
echo "+ displays de page\n";

echo "Listo.\n";
