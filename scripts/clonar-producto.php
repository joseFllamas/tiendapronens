<?php

/**
 * @file
 * Clona un producto entero para un color nuevo: sus variaciones, sus
 * traducciones, el stock, las fotos y el alias.
 *
 * Por qué un producto por color y no un selector de color en la misma ficha:
 * la galería de la ficha lee las fotos DEL PRODUCTO (FichaHooks::fotos, campos
 * field_imagen_principal y field_galeria) y no las de la variación, así que
 * elegir "Rojo" no cambiaría ni una foto; y en los productos personalizables la
 * vista previa del bordado va anclada a field_imagen_principal por decisión del
 * cliente (2026-07-29), o sea a un solo color. Además el eje color está muerto
 * en los datos (39 de 1123 variaciones) y el catálogo ya hace un producto por
 * color en 43 de los 360 publicados (Chándal azul/rojo/pistacho, Body bebé liso
 * Rosa/Azul celeste/Blanco). Unificar colores en una ficha es una
 * funcionalidad aparte (swap de galería por variación) y para todo el catálogo.
 *
 * Lo que el script resuelve solo, y vale para cualquier producto:
 * - Duplica producto y variaciones, sean 1 o 20 (el catálogo va de 1 a 20).
 * - Copia las traducciones que tenga el origen.
 * - Crea la transacción de stock de cada variación nueva: sin ella la ficha
 *   sale "Agotado" con el botón desactivado aunque el original tenga 100.
 * - Registra las fotos nuevas como media y las coloca como estaban en el
 *   origen (si allí las variaciones compartían la foto principal, aquí igual).
 * - Genera el alias con pathauto, idioma por idioma.
 * - Reindexa el catálogo, que es de donde lee la página de categoría.
 *
 * Lo que hay que decirle en la tanda, porque no se puede deducir:
 * - Los títulos, uno por idioma.
 * - Las sustituciones de color dentro del texto, por idioma: el color va
 *   escrito en el body y cada idioma usa su palabra (el catalán dos, "festuc"
 *   en el título y "pistatxo" en el cuerpo). Se aplican EN ORDEN, así que las
 *   claves largas van primero ("couleur pistache" antes que "pistache", porque
 *   en femenino pide "blanche" y no "blanc").
 * - Los SKU. No hay patrón que valga en este catálogo: conviven BLUS.PIST.T-S,
 *   MO.ACOLCHADA PANDA, Cojin31 - Vader y MaskPineapples - T.Infantill-L.
 * - El stock, que no se copia: copiarlo sería inventarse inventario.
 * - Las fotos, que son de otro color.
 *
 * Trampas que resuelve, y que conviene no reintroducir:
 * - ProductVariation::createDuplicate() NO limpia el SKU (solo resetea las
 *   fechas) y el SKU es único: sin cambiarlo, Commerce rechaza el guardado.
 * - La variación duplicada conserva product_id apuntando al ORIGEN. Si no se
 *   limpia, Product::postSave ve la referencia puesta, no vuelve a guardar la
 *   variación y el título se queda con el nombre del producto viejo.
 * - El producto duplicado hereda el campo path. Ponerle alias con el pid del
 *   original dentro hace que PathItem::postSave actualice ESA fila, que sigue
 *   apuntando a /product/<origen>: le cambiaría la URL al original. Aquí se
 *   vacía el campo y lo genera pathauto (el clon nace en estado automático
 *   aunque el origen esté en manual, como están los 366 migrados).
 * - Pathauto solo genera el alias del idioma del objeto que se guarda: hay que
 *   pedírselo traducción a traducción.
 * - created se copia, y es el campo por el que ordenan las novedades y la view
 *   de destacados: se pone a ahora.
 *
 * El clon nace DESPUBLICADO en todos sus idiomas y sin destacar. Se publica a
 * mano cuando las fotos y el stock estén revisados.
 *
 * Idempotente: si los SKU nuevos ya existen, la tanda se da por hecha y se
 * salta. Si existen solo algunos, se para y lo dice (clonado a medias).
 *
 * Uso:
 *   ddev drush php:script scripts/clonar-producto.php             (simulación)
 *   ddev drush php:script scripts/clonar-producto.php -- --crear  (escribe)
 * En producción, lo mismo sin el ddev de delante.
 *
 * Guía de uso, con el procedimiento en producción y qué revisar antes de
 * publicar: scripts/clonar-producto.md.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\search_api\Entity\Index;

// ---------------------------------------------------------------------------
// La tanda. Esto es lo único que cambia de un encargo a otro.
//
// Rutas de foto relativas a la raíz del repo. La principal es obligatoria si
// se quiere crear con fotos; la galería es opcional.
// ---------------------------------------------------------------------------
$tandas = [
  [
    'origen' => 269,
    'titulos' => [
      'es' => 'Blusón Educadora Infantil color rojo',
      'ca' => 'Brusa d’educadora infantil de color vermell',
      'en' => 'Red Early Years Educator Tunic',
      'fr' => 'Blouse d’éducatrice de jeunes enfants couleur rouge',
      'it' => 'Blusa da educatrice dell’infanzia color rosso',
    ],
    'texto' => [
      'es' => ['pistacho' => 'rojo'],
      'ca' => ['pistatxo' => 'vermell', 'festuc' => 'vermell'],
      'en' => ['pistachio green' => 'red', 'Pistachio-coloured' => 'Red', 'pistachio' => 'red'],
      'fr' => ['couleur pistache' => 'couleur rouge', 'pistache' => 'rouge'],
      'it' => ['pistacchio' => 'rosso'],
    ],
    'sku' => ['PIST' => 'ROJO'],
    'skus' => [],
    'stock' => 0,
    'color_attr' => NULL,
    'hilo' => NULL,
    'fotos' => [
      'principal' => 'fotos-clones/bluson-rojo.jpg',
      'galeria' => [],
    ],
  ],
  [
    'origen' => 269,
    'titulos' => [
      'es' => 'Blusón Educadora Infantil color blanco',
      'ca' => 'Brusa d’educadora infantil de color blanc',
      'en' => 'White Early Years Educator Tunic',
      'fr' => 'Blouse d’éducatrice de jeunes enfants couleur blanche',
      'it' => 'Blusa da educatrice dell’infanzia color bianco',
    ],
    'texto' => [
      'es' => ['pistacho' => 'blanco'],
      'ca' => ['pistatxo' => 'blanc', 'festuc' => 'blanc'],
      'en' => ['pistachio green' => 'white', 'Pistachio-coloured' => 'White', 'pistachio' => 'white'],
      'fr' => ['couleur pistache' => 'couleur blanche', 'pistache' => 'blanc'],
      'it' => ['pistacchio' => 'bianco'],
    ],
    'sku' => ['PIST' => 'BLAN'],
    'skus' => [],
    'stock' => 0,
    'color_attr' => NULL,
    'hilo' => NULL,
    'fotos' => [
      'principal' => 'fotos-clones/bluson-blanco.jpg',
      'galeria' => [],
    ],
  ],
];

// Campos del producto que NO se copian: son fotos del color viejo.
$vaciar_producto = ['field_imagen_principal', 'field_galeria', 'field_bordado_foto'];

$argumentos = $extra ?? [];
$crear = in_array('--crear', $argumentos, TRUE);

$gestor = \Drupal::entityTypeManager();
$almacen_producto = $gestor->getStorage('commerce_product');
$conexion = \Drupal::database();
$pathauto = \Drupal::service('pathauto.generator');
$ficheros = \Drupal::service('file_system');
$ahora = \Drupal::time()->getRequestTime();
$carpeta = 'public://' . date('Y-m', $ahora);
$raiz = dirname(DRUPAL_ROOT);

print $crear
  ? "Modo ESCRITURA: se van a crear productos.\n\n"
  : "Modo simulación: no se escribe nada. Añade  -- --crear  para crearlo de verdad.\n\n";

/**
 * Aplica las sustituciones de color en orden (las claves largas primero).
 */
$sustituir = static function (?string $texto, array $mapa): ?string {
  if ($texto === NULL || $texto === '' || $mapa === []) {
    return $texto;
  }
  return str_replace(array_keys($mapa), array_values($mapa), $texto);
};

/**
 * Copia una foto del repo a public:// y devuelve el media, o NULL.
 */
$registrarFoto = static function (string $ruta, string $alt, string $nombre) use ($ficheros, $carpeta): ?Media {
  $ficheros->prepareDirectory($carpeta, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $destino = $ficheros->copy($ruta, $carpeta . '/' . basename($ruta), FileExists::Rename);
  if ($destino === FALSE) {
    return NULL;
  }
  $fichero = File::create(['uri' => $destino, 'status' => 1, 'uid' => 1]);
  $fichero->save();
  $media = Media::create([
    'bundle' => 'image',
    'uid' => 1,
    'status' => 1,
    'name' => $nombre,
    'field_media_image' => ['target_id' => $fichero->id(), 'alt' => $alt, 'title' => ''],
  ]);
  $media->save();
  return $media;
};

$creados = [];

foreach ($tandas as $numero => $tanda) {
  $etiqueta = $tanda['titulos']['es'] ?? ('tanda ' . $numero);
  print "=== $etiqueta (clon de " . $tanda['origen'] . ")\n";

  $origen = $almacen_producto->load($tanda['origen']);
  if ($origen === NULL) {
    print "  ERROR: no existe el producto " . $tanda['origen'] . ". Se salta.\n\n";
    continue;
  }

  $variaciones = $origen->getVariations();
  if ($variaciones === []) {
    print "  ERROR: el producto " . $tanda['origen'] . " no tiene variaciones. Se salta.\n\n";
    continue;
  }

  // -------------------------------------------------------------------------
  // 1. SKU nuevos. Sin patrón que valga en este catálogo: o mapa explícito, o
  //    sustitución sobre el SKU viejo. strtr aplica la clave más larga.
  // -------------------------------------------------------------------------
  $skus = [];
  $error = FALSE;
  foreach ($variaciones as $variacion) {
    $viejo = (string) $variacion->getSku();
    $nuevo = $tanda['skus'][$viejo] ?? ($tanda['sku'] !== [] ? strtr($viejo, $tanda['sku']) : '');
    if ($nuevo === '' || $nuevo === $viejo) {
      print "  ERROR: no sé qué SKU poner para $viejo. Añádelo al mapa 'skus'.\n";
      $error = TRUE;
      continue;
    }
    $skus[$variacion->id()] = $nuevo;
  }
  if ($error) {
    print "\n";
    continue;
  }
  if (count(array_unique($skus)) !== count($skus)) {
    print "  ERROR: la sustitución produce SKU repetidos entre variaciones.\n\n";
    continue;
  }

  // Idempotencia: los SKU existentes dicen si esta tanda ya está hecha.
  $existentes = $conexion->select('commerce_product_variation_field_data', 'v')
    ->fields('v', ['sku'])
    ->condition('sku', array_values($skus), 'IN')
    ->execute()
    ->fetchCol();
  if (count($existentes) === count($skus)) {
    print "  Ya estaba clonado (los " . count($skus) . " SKU existen). Se salta.\n\n";
    continue;
  }
  if ($existentes !== []) {
    print "  ERROR: clonado a medias, ya existen estos SKU: " . implode(', ', $existentes) . ".\n";
    print "  Bórralos o cámbialos antes de seguir.\n\n";
    continue;
  }

  // -------------------------------------------------------------------------
  // 2. Fotos. Son de otro color, así que no se copian: se registran las nuevas.
  // -------------------------------------------------------------------------
  $rutas = array_filter(array_merge(
    [$tanda['fotos']['principal'] ?? NULL],
    $tanda['fotos']['galeria'] ?? []
  ));
  $faltan = [];
  foreach ($rutas as $ruta) {
    if (!is_file($raiz . '/' . $ruta)) {
      $faltan[] = $ruta;
    }
  }
  if ($faltan !== []) {
    print "  ERROR: no encuentro estas fotos: " . implode(', ', $faltan) . "\n";
    print "  (rutas relativas a $raiz)\n\n";
    continue;
  }
  if ($rutas === []) {
    print "  AVISO: sin fotos. El clon se crea sin galería y hay que subirlas a mano.\n";
  }

  // ¿En el origen las variaciones comparten la foto principal? Si es así, el
  // clon hace lo mismo con la suya; si no, se dejan vacías y se avisa.
  $principal_origen = $origen->get('field_imagen_principal')->isEmpty()
    ? NULL
    : (int) $origen->get('field_imagen_principal')->first()->target_id;
  $comparten = $principal_origen !== NULL;
  foreach ($variaciones as $variacion) {
    $medias = array_column($variacion->get('field_imagenes')->getValue(), 'target_id');
    if (count($medias) !== 1 || (int) $medias[0] !== $principal_origen) {
      $comparten = FALSE;
    }
  }

  print sprintf(
    "  %d variaciones | idiomas: %s | fotos: %d | stock: %s | foto por variación: %s\n",
    count($variaciones),
    implode(',', array_keys($origen->getTranslationLanguages())),
    count($rutas),
    $tanda['stock'] > 0 ? (string) $tanda['stock'] . ' uds' : 'SIN transacción (saldrá Agotado)',
    $comparten ? 'la principal, como en el origen' : 'vacía, en el origen no era la principal'
  );
  foreach ($variaciones as $variacion) {
    print sprintf("    SKU %-30s → %s\n", $variacion->getSku(), $skus[$variacion->id()]);
  }
  foreach ($tanda['titulos'] as $idioma => $titulo) {
    $aviso = $origen->hasTranslation($idioma) ? '' : '   (el origen NO tiene este idioma, se ignora)';
    print sprintf("    %-3s %s%s\n", $idioma, $titulo, $aviso);
  }

  if (!$crear) {
    print "\n";
    continue;
  }

  // -------------------------------------------------------------------------
  // 3. El producto.
  // -------------------------------------------------------------------------
  $media_principal = NULL;
  $medias_galeria = [];
  if (isset($tanda['fotos']['principal'])) {
    $media_principal = $registrarFoto(
      $raiz . '/' . $tanda['fotos']['principal'],
      $tanda['titulos']['es'],
      $tanda['titulos']['es']
    );
  }
  foreach ($tanda['fotos']['galeria'] ?? [] as $indice => $ruta) {
    $media = $registrarFoto(
      $raiz . '/' . $ruta,
      $tanda['titulos']['es'] . ', foto ' . ($indice + 2),
      $tanda['titulos']['es'] . ' (' . ($indice + 2) . ')'
    );
    if ($media !== NULL) {
      $medias_galeria[] = ['target_id' => $media->id()];
    }
  }

  $clon = $origen->createDuplicate();
  // Las variaciones del duplicado son las DEL ORIGEN: fuera, se ponen luego.
  $clon->set('variations', []);
  // El campo path viene con el pid del original dentro: vaciarlo o le
  // cambiaríamos la URL al origen. Pathauto genera el alias al guardar.
  $clon->set('path', ['pathauto' => 1]);
  $clon->setCreatedTime($ahora);
  $clon->setChangedTime($ahora);

  foreach ($clon->getTranslationLanguages() as $idioma) {
    $codigo = $idioma->getId();
    $traduccion = $clon->getTranslation($codigo);
    $traduccion->setUnpublished();
    if (isset($tanda['titulos'][$codigo])) {
      $traduccion->setTitle($tanda['titulos'][$codigo]);
    }
    else {
      print "    AVISO: sin título para $codigo, se queda el del origen.\n";
    }
    $mapa = $tanda['texto'][$codigo] ?? [];
    if (!$traduccion->get('body')->isEmpty()) {
      $body = $traduccion->get('body')->first();
      $traduccion->set('body', [
        'value' => $sustituir($body->value, $mapa),
        'summary' => $sustituir($body->summary, $mapa),
        'format' => $body->format,
      ]);
    }
    foreach ($vaciar_producto as $campo) {
      if ($traduccion->hasField($campo)) {
        $traduccion->set($campo, []);
      }
    }
    if ($traduccion->hasField('field_destacado')) {
      $traduccion->set('field_destacado', 0);
    }
    if ($tanda['hilo'] !== NULL && $traduccion->hasField('field_bordado_color')) {
      $traduccion->set('field_bordado_color', $tanda['hilo']);
    }
  }
  if ($media_principal !== NULL) {
    $clon->set('field_imagen_principal', ['target_id' => $media_principal->id()]);
  }
  if ($medias_galeria !== []) {
    $clon->set('field_galeria', $medias_galeria);
  }

  // -------------------------------------------------------------------------
  // 4. Las variaciones. Dos cosas que hay que hacer sí o sí:
  //    - product_id a NULL, para que Product::postSave ponga la referencia
  //      buena y regenere el título con el nombre del clon. El duplicado lo
  //      trae apuntando al ORIGEN.
  //    - meterlas con set() y no con addVariation(): addVariation() descarta
  //      las repetidas comparando ids, y las nuevas tienen el id a NULL, así
  //      que array_search(NULL, [NULL]) devuelve 0 y de las cinco solo entra
  //      la primera (comprobado: el clon se quedaba con una variación).
  // -------------------------------------------------------------------------
  $nuevas = [];
  foreach ($variaciones as $variacion) {
    $nueva = $variacion->createDuplicate();
    $nueva->set('product_id', NULL);
    $nueva->setSku($skus[$variacion->id()]);
    $nueva->setCreatedTime($ahora);
    $nueva->setChangedTime($ahora);
    if ($nueva->hasField('field_imagenes')) {
      $nueva->set(
        'field_imagenes',
        $comparten && $media_principal !== NULL ? [['target_id' => $media_principal->id()]] : []
      );
    }
    if ($tanda['color_attr'] !== NULL && $nueva->hasField('attribute_color')) {
      $nueva->set('attribute_color', ['target_id' => $tanda['color_attr']]);
    }
    $nuevas[] = $nueva;
  }
  $clon->set('variations', $nuevas);

  $clon->save();
  print "  Creado el producto " . $clon->id() . ".\n";

  // -------------------------------------------------------------------------
  // 5. Stock: sin transacción la ficha sale "Agotado" con el botón apagado.
  // -------------------------------------------------------------------------
  if ($tanda['stock'] > 0) {
    $stock = \Drupal::service('commerce_stock.service_manager');
    foreach ($clon->getVariations() as $variacion) {
      $stock->createTransaction(
        $variacion,
        1,
        '',
        $tanda['stock'],
        NULL,
        'EUR',
        1,
        ['message' => 'Stock inicial del clon de ' . $tanda['origen'] . '.']
      );
    }
    print "  Stock: " . $tanda['stock'] . " uds en cada una de las " . count($clon->getVariations()) . " variaciones.\n";
  }
  else {
    print "  AVISO: sin stock. Ponlo en cada variación antes de publicar o saldrá Agotado.\n";
  }

  // -------------------------------------------------------------------------
  // 6. Alias: pathauto solo genera el del idioma que se guarda.
  // -------------------------------------------------------------------------
  foreach ($clon->getTranslationLanguages() as $idioma) {
    $pathauto->updateEntityAlias($clon->getTranslation($idioma->getId()), 'insert');
  }
  $alias = $conexion->select('path_alias', 'a')
    ->fields('a', ['langcode', 'alias'])
    ->condition('path', '/product/' . $clon->id())
    ->execute()
    ->fetchAllKeyed();
  foreach ($alias as $codigo => $ruta) {
    print sprintf("    alias %-3s %s\n", $codigo, $ruta);
  }

  $creados[] = $clon;
  print "\n";
}

// ---------------------------------------------------------------------------
// 7. Search API: la categoría y las facetas leen del índice, no de la BBDD.
// ---------------------------------------------------------------------------
if ($creados !== [] && $crear) {
  $indice = Index::load('catalogo');
  if ($indice !== NULL) {
    print "Reindexados " . $indice->indexItems() . " elementos del índice catalogo.\n";
  }
  print "\nCreados " . count($creados) . " productos, DESPUBLICADOS. Antes de publicar:\n";
  foreach ($creados as $clon) {
    print "  /product/" . $clon->id() . "/edit  " . $clon->label() . "\n";
  }
  print "  1. Revisa las fotos (principal, galería y la de cada variación).\n";
  print "  2. Pon el stock de cada talla.\n";
  print "  3. Repasa el texto en los 5 idiomas: la sustitución es literal.\n";
  print "  4. Publícalo en cada idioma.\n";
  print "  5. Si quiere salir en 'Completa el conjunto' de otros productos,\n";
  print "     añádelo a su field_complementarios.\n";
}
