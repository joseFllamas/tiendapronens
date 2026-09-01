<?php

/**
 * @file
 * Monta la página "Quiénes somos" (nid 4) con las secciones del prototipo
 * (design/Tienda Pronens.dc.html → Nosotras) en lugar del texto corrido que
 * trajo la migración del D7.
 *
 * El texto no se inventa: sale del `body` que ya tenía la página, troceado en
 * las secciones del prototipo. Solo se ajusta lo que el dato desmentía:
 * - El prototipo pone "3 países con envío gratis" y el marquee real dice
 *   "España, Portugal y UE desde 60 €", así que esa cifra se cambia por las
 *   "72 h" del bordado, que sí están en el marquee.
 * - El año de fundación (1986) también sale del marquee, no del prototipo.
 * - El segundo botón del cierre no puede ir a la categoría "Personaliza"
 *   (185): está vacía. Lleva a la ficha de la mochila con inicial (373), que
 *   es el producto que mejor enseña el bordado.
 *
 * El body se vacía para que el texto no salga dos veces, y se guarda una
 * revisión nueva: el texto del D7 queda recuperable en el historial.
 *
 * FOTOS: las cuatro salen del propio prototipo (design/assets/nosotras-*.jpg,
 * extraídas de los base64 de pronens-nosotras-prototipo.html). OJO: su XMP
 * dice `trainedAlgorithmicMedia`, o sea que son IMÁGENES GENERADAS POR IA, no
 * fotos del taller de Pronens. Valen para ver la página terminada, pero
 * enseñan un taller y un equipo que no existen: antes de publicar hay que
 * sustituirlas por fotos reales.
 *
 * Uso: ddev drush php:script scripts/quienes-somos.php
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$nid = 4;
$node = Node::load($nid);
if ($node === NULL) {
  echo "No existe el nodo $nid.\n";
  return;
}

/**
 * Importa una imagen del repo como media, o devuelve la que ya se importó.
 *
 * Se reconoce por el nombre del media, así que relanzar el script no duplica
 * ficheros.
 */
$media = function (string $fichero, string $nombre, string $alt): int {
  $existentes = \Drupal::entityTypeManager()->getStorage('media')
    ->loadByProperties(['name' => $nombre]);
  if ($existentes !== []) {
    return (int) reset($existentes)->id();
  }
  $origen = \Drupal::root() . '/../design/assets/' . $fichero;
  $datos = file_get_contents($origen);
  if ($datos === FALSE) {
    throw new \RuntimeException("No se pudo leer $origen");
  }
  $carpeta = 'public://' . date('Y-m');
  \Drupal::service('file_system')->prepareDirectory($carpeta, FileSystemInterface::CREATE_DIRECTORY);
  /** @var \Drupal\file\FileRepositoryInterface $repo */
  $repo = \Drupal::service('file.repository');
  $file = $repo->writeData($datos, $carpeta . '/' . $fichero, FileExists::Replace);
  $media = Media::create([
    'bundle' => 'image',
    'name' => $nombre,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt],
    'status' => 1,
  ]);
  $media->save();
  echo "  + media {$media->id()} $nombre\n";
  return (int) $media->id();
};

$foto_hero = $media(
  'nosotras-taller.jpg',
  'Nosotras — taller',
  'Taller textil de Pronens con máquinas de coser y rollos de tela',
);
$fotos_historia = [
  $media(
    'nosotras-bordado.jpg',
    'Nosotras — bordado',
    'Máquina bordando un nombre sobre una bata escolar turquesa',
  ),
  $media(
    'nosotras-hilos.jpg',
    'Nosotras — hilos',
    'Prendas infantiles dobladas junto a bobinas de hilo de colores',
  ),
];
$foto_pasos = $media(
  'nosotras-equipo.jpg',
  'Nosotras — equipo',
  'Equipo de Pronens revisando muestras de ropa escolar en el taller',
);

/**
 * Crea un párrafo hijo y lo devuelve para meterlo en field_items.
 *
 * @param array<string, mixed> $valores
 *   Valores del párrafo.
 */
$hijo = function (string $tipo, array $valores): Paragraph {
  $p = Paragraph::create(['type' => $tipo] + $valores);
  $p->save();
  return $p;
};

$secciones = [];

// 1. Hero con la foto de fondo.
$secciones[] = Paragraph::create([
  'type' => 'hero',
  'field_eyebrow' => 'Desde 1986',
  'field_titulo' => 'Nosotras',
  'field_subtitulo' => 'Empresa líder en la fabricación de prendas escolares que lleva su experiencia y su colección de moda infantil directamente a las familias.',
  'field_imagen_media' => ['target_id' => $foto_hero],
]);

// 2. Franja de cifras.
$cifras = [
  ['+40', 'Años de experiencia'],
  ['+500', 'Productos que fabricamos'],
  ['100%', 'Fabricación propia'],
  ['72 h', 'Bordado personalizado'],
];
$secciones[] = Paragraph::create([
  'type' => 'cifras',
  'field_items' => array_map(
    fn(array $c): Paragraph => $hijo('cifra', [
      'field_titulo' => $c[0],
      'field_texto' => $c[1],
    ]),
    $cifras,
  ),
]);

// 3. La historia, con las fotos al lado.
$secciones[] = Paragraph::create([
  'type' => 'texto_medios',
  'field_eyebrow' => 'Nuestra historia',
  'field_titulo' => 'Cuatro décadas',
  'field_titulo_enfasis' => 'vistiendo el cole.',
  'field_texto_largo' => [
    'value' => implode("\n", [
      '<p>Pronens, empresa líder en la fabricación de prendas escolares avalada por más de 40 años de experiencia, lanza al mercado su colección de moda escolar para particulares.</p>',
      '<p>Basamos nuestro rápido crecimiento en el esfuerzo, la excelencia y la moda en su máxima expresión. Nuestro objetivo es crear prendas de máxima calidad al mejor precio posible, con los diseños más bonitos y originales, y personalizables para cada cliente.</p>',
      '<p><strong>¡Las prendas de Pronens te permiten echar a volar tu imaginación!</strong></p>',
    ]),
    'format' => 'basic_html',
  ],
  'field_imagenes' => array_map(fn(int $mid): array => ['target_id' => $mid], $fotos_historia),
]);

// 4. Lo que nos define.
$valores = [
  ['paleta', 'Color y diseño', 'Intensidad de color, estampados sorprendentes y un tratamiento innovador de los tejidos como protagonistas de cada colección.'],
  ['escudo', 'Calidad al mejor precio', 'Prendas de máxima calidad a precios atractivos, pensadas para resistir un curso escolar entero sin perder la forma ni el color.'],
  ['chispas', 'Personalización sin límites', 'Una amplísima gama de prendas personalizables: bordamos el nombre o la inicial de cada peque en 72 horas.'],
  ['regla', 'Comodidad ante todo', 'Patronaje pensado para el movimiento. Modelos exclusivos acordes con las tendencias, sin renunciar nunca a la comodidad.'],
];
$secciones[] = Paragraph::create([
  'type' => 'valores',
  'field_eyebrow' => 'Lo que nos define',
  'field_titulo' => 'Color, moda, diseño y calidad',
  'field_texto' => 'Es la tarjeta de presentación de una marca situada como una de las principales firmas de moda escolar infantil, con una colección completa que cubre todas las necesidades de los niños durante el curso.',
  'field_items' => array_map(
    fn(array $v): Paragraph => $hijo('valor', [
      'field_icono' => $v[0],
      'field_titulo' => $v[1],
      'field_texto' => $v[2],
    ]),
    $valores,
  ),
]);

// 5. Cómo lo hacemos (mismo párrafo que la personalización de la home).
$pasos = [
  ['paleta', 'Diseño', 'Creamos modelos exclusivos cada temporada, actuales, divertidos e innovadores.'],
  ['tijeras', 'Tejidos', 'Seleccionamos con mimo tejidos, colorido y detalles antes de cortar la primera pieza.'],
  ['fabrica', 'Fabricación', 'Confeccionamos íntegramente en nuestros propios talleres, con control en cada costura.'],
  ['chispas', 'Personalización', 'Bordamos el nombre o la inicial y preparamos el pedido para su envío.'],
];
$secciones[] = Paragraph::create([
  'type' => 'pasos_personalizacion',
  'field_eyebrow' => 'Cómo lo hacemos',
  'field_titulo' => 'De la idea a su nombre bordado',
  'field_texto' => 'Hacemos especial hincapié en la selección de tejidos, del colorido y los detalles y, por supuesto, ponemos todo nuestro cariño y esmero en la fabricación de los artículos, fabricados íntegramente en nuestros talleres.',
  'field_imagen_media' => ['target_id' => $foto_pasos],
  'field_items' => array_map(
    fn(array $p): Paragraph => $hijo('paso', [
      'field_icono' => $p[0],
      'field_titulo' => $p[1],
      'field_texto' => $p[2],
    ]),
    $pasos,
  ),
]);

// 6. Aviso de B2B, banda oscura.
$secciones[] = Paragraph::create([
  'type' => 'cta',
  'field_estilo' => 'oscuro',
  'field_icono' => 'edificio',
  'field_eyebrow' => '¿Eres un colegio o una empresa?',
  'field_titulo' => 'Esta tienda es para familias. Para B2B tenemos otra casa.',
  'field_texto' => 'Si eres titular de un colegio, escuela infantil, empresa o marca, consulta nuestra web específica con más de 500 productos diferentes que fabricamos y personalizamos para empresas.',
  'field_enlace' => ['uri' => 'https://www.pronens.com/', 'title' => 'Ir a Pronens B2B'],
]);

// 7. Cierre centrado.
$secciones[] = Paragraph::create([
  'type' => 'cta',
  'field_estilo' => 'centrado',
  'field_titulo' => 'Una amplia gama de moda infantil y escolar para los más peques de la casa',
  'field_enlace' => ['uri' => 'internal:/', 'title' => 'Descubrir la tienda'],
  'field_enlace_secundario' => ['uri' => 'entity:commerce_product/373', 'title' => 'Ver una prenda personalizada'],
]);

foreach ($secciones as $seccion) {
  $seccion->save();
}

// Los párrafos anteriores (si se vuelve a lanzar el script) se quedarían
// huérfanos: se borran antes de reasignar.
foreach ($node->get('field_secciones')->referencedEntities() as $viejo) {
  $viejo->delete();
}

$node->set('field_secciones', $secciones);
$node->set('body', ['value' => '', 'format' => 'basic_html']);
// El título migrado va sin tilde. Se corrige aquí porque el alias no se mueve:
// el nodo está en pathauto_state 0 (alias manual), así que /quienes-somos se
// queda como está y no hay coste de SEO.
$node->set('title', 'Quiénes somos');
$node->setNewRevision(TRUE);
$node->setRevisionLogMessage('Página montada con secciones (Paragraphs) siguiendo el prototipo. El texto del D7 queda en la revisión anterior.');
$node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
$node->save();

// El D7 solo trajo el alias castellano, así que cambiar de idioma desde esta
// página daba 404 (el nodo sí responde en los cinco: /ca/node/4 → 200). Se
// completan los otros cuatro con el mismo slug, que es lo que hace el Aviso
// Legal para ca/fr/it. Cuando se traduzca el título, pathauto no los moverá:
// el nodo está en estado manual.
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
foreach (['ca', 'en', 'fr', 'it'] as $idioma) {
  $existentes = $alias_storage->loadByProperties([
    'path' => '/node/' . $nid,
    'langcode' => $idioma,
  ]);
  if ($existentes !== []) {
    continue;
  }
  $alias_storage->create([
    'path' => '/node/' . $nid,
    'alias' => '/quienes-somos',
    'langcode' => $idioma,
  ])->save();
  echo "  + alias $idioma\n";
}

echo "Nodo $nid: " . count($secciones) . " secciones.\n";
foreach ($secciones as $seccion) {
  echo '  - ' . $seccion->bundle() . ' (' . $seccion->id() . ")\n";
}
