<?php

/**
 * @file
 * Punto focal en los recortes y botón de editar en los campos de medios.
 *
 * Se ejecuta con `ddev drush php:script scripts/focal-point.php`.
 *
 * Nueve de los estilos de imagen del sitio recortan a una proporción fija
 * (la tarjeta a 3:4, el hero a 1920x600, la miniatura del carrito a un
 * cuadrado…) y hasta ahora lo hacían siempre por el centro, que es lo único
 * que sabe hacer `image_scale_and_crop`. En un catálogo de bodegones y de
 * prendas fotografiadas de frente eso corta cabezas y deja fuera el motivo:
 * la decisión de qué parte de la foto se conserva es de cada foto, no del
 * estilo. `focal_point` la guarda una vez por FICHERO (una entidad `Crop` con
 * el punto en píxeles) y la aplican todos los estilos, así que la misma foto
 * se recorta con el mismo criterio en la tarjeta, en la ficha y en el hero.
 *
 * El punto se marca sobre la propia foto en el formulario del medio, y con
 * `media_library_edit` se llega ahí sin salir del producto: el botón del lápiz
 * de cada foto del widget abre el medio en un diálogo. Es el mismo gesto que
 * ya hace el widget de montaje del bordado (marcar sobre la foto en vez de
 * teclear coordenadas) y de paso deja corregir el texto alternativo desde
 * donde se usa la foto: `scripts/alt-fotos.php` resolvió 864 medios por la
 * entidad que los referencia, pero los 144 que no referencia nadie siguen con
 * el nombre del fichero y esos hay que verlos uno a uno.
 *
 * OJO CON EL BORDADO: `field_inicial_x/_y` se miden en % de
 * `pronens_ficha_principal`, que es uno de los estilos que pasan a punto
 * focal. Mientras el punto se quede donde está por defecto (el centro) el
 * recorte es el mismo que antes y no se mueve nada, pero cambiar el punto
 * focal de la foto de montaje de un producto ya calibrado le desplaza el
 * bordado. Son unos 180 productos (bodys, bolsas, baberos, sudaderas de
 * inicial y la mochila 373): si se toca su punto focal, hay que repasar la
 * colocación en el formulario del producto.
 *
 * Los puntos focales son CONTENIDO (entidades `Crop` atadas al URI del
 * fichero), así que no viajan en config/sync: lo que este script deja es la
 * maquinaria, y el punto de cada foto se marca en cada entorno. La
 * configuración sí se exporta.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\image\Entity\ImageStyle;

/**
 * Estilos que recortan: el efecto de core pasa al de punto focal.
 *
 * No se toca ninguno de los que solo escalan (lightbox, zoom, og, fondo…):
 * sin recorte no hay nada que decidir.
 */
$convertidos = [];
$ya_estaban = [];
foreach (ImageStyle::loadMultiple() as $estilo) {
  $cambiado = FALSE;
  foreach ($estilo->getEffects() as $efecto) {
    $id = $efecto->getPluginId();
    if ($id === 'focal_point_scale_and_crop' || $id === 'focal_point_crop') {
      $ya_estaban[] = $estilo->id();
      continue;
    }
    if ($id !== 'image_scale_and_crop' && $id !== 'image_crop') {
      continue;
    }

    $configuracion = $efecto->getConfiguration();
    $datos = $configuracion['data'];
    // `anchor` desaparece: lo sustituye el punto focal de cada fichero.
    unset($datos['anchor']);
    $datos['crop_type'] = 'focal_point';

    $estilo->deleteImageEffect($efecto);
    $estilo->addImageEffect([
      'id' => $id === 'image_crop' ? 'focal_point_crop' : 'focal_point_scale_and_crop',
      'weight' => $configuracion['weight'],
      'data' => $datos,
    ]);
    $cambiado = TRUE;
  }

  if ($cambiado) {
    // Guardar un estilo con los efectos cambiados vacía sus derivados, así
    // que las fotos se vuelven a generar bajo demanda.
    $estilo->save();
    $convertidos[] = $estilo->id();
  }
}

print "Estilos de imagen\n";
foreach ($convertidos as $id) {
  printf("  %-28s recorte por punto focal\n", $id);
}
foreach (array_unique($ya_estaban) as $id) {
  printf("  %-28s ya lo tenía\n", $id);
}
if (!$convertidos && !$ya_estaban) {
  print "  ninguno recorta\n";
}

/**
 * El widget que marca el punto sobre la foto, en el campo imagen del medio.
 *
 * Va en los dos modos de formulario: `default` es el que se abre en
 * /admin/content/media y `media_library` el que abre el botón del lápiz desde
 * el widget de un producto o de un párrafo.
 *
 * La previsualización pasa de `thumbnail` (100 px) a `max_650x650`: el punto
 * se arrastra sobre esa imagen y en 100 px no se acierta.
 */
print "\nWidget del punto focal\n";
foreach (['media.image.default', 'media.image.media_library'] as $nombre) {
  $display = EntityFormDisplay::load($nombre);
  if ($display === NULL) {
    printf("  %-34s no existe\n", $nombre);
    continue;
  }

  $componente = $display->getComponent('field_media_image');
  if ($componente === NULL) {
    printf("  %-34s sin campo de imagen\n", $nombre);
    continue;
  }
  if ($componente['type'] === 'image_focal_point') {
    printf("  %-34s ya lo tenía\n", $nombre);
    continue;
  }

  $componente['type'] = 'image_focal_point';
  $componente['settings'] = [
    'progress_indicator' => $componente['settings']['progress_indicator'] ?? 'throbber',
    'preview_image_style' => 'max_650x650',
    'preview_link' => TRUE,
    'offsets' => '50,50',
  ];
  $display->setComponent('field_media_image', $componente)->save();
  printf("  %-34s image_image -> image_focal_point\n", $nombre);
}

/**
 * El botón de editar en todos los campos de medios.
 *
 * Se recorren los campos que apuntan a un medio y sus formularios en vez de ir
 * con una lista escrita a mano: así el campo de medios que se añada mañana lo
 * hereda relanzando esto. El widget de biblioteca es condición para el botón,
 * de modo que los dos campos que seguían con el autocompletar (los fondos de
 * bordado y las guías de tallas) pasan a la biblioteca.
 *
 * Un campo escondido del formulario se queda fuera y se avisa al final: no
 * tiene widget donde colgar el botón, y sacarlo a la luz es una decisión de
 * quién edita qué, no de este script.
 */
print "\nBotón de editar (media_library_edit)\n";

$gestor_campos = \Drupal::service('entity_field.manager');
$info_bundles = \Drupal::service('entity_type.bundle.info');
$repositorio = \Drupal::service('entity_display.repository');

// Los campos de medios del sitio, agrupados por tipo de entidad y paquete.
$instancias = [];
foreach (\Drupal::entityTypeManager()->getStorage('field_storage_config')->loadMultiple() as $storage) {
  if ($storage->getType() !== 'entity_reference' || $storage->getSetting('target_type') !== 'media') {
    continue;
  }
  $tipo_entidad = $storage->getTargetEntityTypeId();
  $campo = $storage->getName();
  foreach (array_keys($info_bundles->getBundleInfo($tipo_entidad)) as $bundle) {
    if (isset($gestor_campos->getFieldDefinitions($tipo_entidad, $bundle)[$campo])) {
      $instancias[$tipo_entidad . '.' . $bundle][] = $campo;
    }
  }
}

/**
 * Campos de medios que pasan a verse en el formulario (cliente, 2026-09-10).
 *
 * Estaban escondidos, y un campo escondido no tiene widget donde colgar el
 * botón de editar. La foto de la categoría es el caso de más peso: alimenta
 * las teselas de la home (900x700) y el mega menú (720x420), que son los dos
 * recortes donde más se nota el punto focal, y hasta ahora no se podía cambiar
 * desde el término. Las guías de tallas nunca tuvieron formulario propio, así
 * que su foto (4 términos, los 4 con una) no se había podido tocar nunca.
 *
 * Se queda fuera `fuente_bordado.field_muestra`: ese vocabulario está dormido
 * desde que se decidió que la fuente del bordado no la elige quien compra.
 */
$a_la_luz = [
  // Después de la descripción del término.
  'taxonomy_term.tipo_de_producto.default' => ['field_imagen' => 1],
  'taxonomy_term.guia_tallas.default' => ['field_imagen' => 1],
  // Detrás de los atributos de la variación, delante del estado.
  'commerce_product_variation.default.default' => ['field_imagenes' => 6],
];

$activados = 0;
$ya_activos = 0;
$escondidos = [];
foreach ($instancias as $destino => $campos) {
  [$tipo_entidad, $bundle] = explode('.', $destino, 2);

  // Todos los modos de formulario que existan, más el `default`, que se crea
  // si el paquete nunca tuvo uno propio (era el caso de las guías de tallas).
  $modos = array_keys($repositorio->getFormModeOptionsByBundle($tipo_entidad, $bundle));
  // `getFormModeOptionsByBundle()` devuelve vacío cuando el paquete no tiene
  // ni un formulario guardado, que es lo que pasa con lo creado por script.
  $modos = array_unique(array_merge(['default'], $modos));
  foreach ($modos as $modo) {
    $display = $repositorio->getFormDisplay($tipo_entidad, $bundle, $modo);
    if ($display->isNew() && $modo !== 'default') {
      continue;
    }

    $guardar = FALSE;
    foreach ($campos as $campo) {
      $componente = $display->getComponent($campo);
      if ($componente === NULL) {
        $peso = $a_la_luz[$display->id()][$campo] ?? NULL;
        if ($peso === NULL) {
          $escondidos[] = sprintf('%s.%s.%s  %s', $tipo_entidad, $bundle, $modo, $campo);
          continue;
        }
        $componente = ['type' => '', 'weight' => $peso, 'region' => 'content', 'settings' => [], 'third_party_settings' => []];
      }

      $ajustes = $componente['third_party_settings']['media_library_edit'] ?? [];
      $tipo_widget = $componente['type'] ?? '';
      if ($tipo_widget === 'media_library_widget'
        && ($ajustes['show_edit'] ?? FALSE)
        && ($ajustes['edit_form_mode'] ?? '') === 'media_library') {
        $ya_activos++;
        continue;
      }

      $anterior = $tipo_widget;
      if ($tipo_widget !== 'media_library_widget') {
        $componente['type'] = 'media_library_widget';
        $componente['settings'] = ['media_types' => []];
      }
      $componente['third_party_settings']['media_library_edit'] = [
        'show_edit' => TRUE,
        // El formulario reducido de la biblioteca: la foto con su texto
        // alternativo y el punto focal, sin el autor ni el alias del medio.
        'edit_form_mode' => 'media_library',
      ];
      $display->setComponent($campo, $componente);
      $guardar = TRUE;
      $activados++;
      $que = match (TRUE) {
        $anterior === '' => 'escondido -> a la luz',
        $anterior === 'media_library_widget' => 'botón',
        default => $anterior . ' -> biblioteca',
      };
      printf("  %-46s %-22s %s\n", $display->id(), $campo, $que);
    }

    if ($guardar) {
      $display->save();
    }
  }
}
printf("  activados: %d, ya estaban: %d\n", $activados, $ya_activos);

print "\nCampos de medios escondidos del formulario\n";
foreach (array_unique($escondidos) as $linea) {
  print "  $linea\n";
}
if (!$escondidos) {
  print "  ninguno\n";
}
