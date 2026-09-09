<?php

/**
 * @file
 * Tanda: el blusón de educadora con estampado, en cuatro colores.
 *
 * Es el mismo blusón del 269 con el estampado "Teachers are the real
 * influencers." serigrafiado en el pecho, en blanco, pistacho, rojo y lila.
 * Los cuatro se clonan del 269 (el pistacho liso) y no unos de otros: es el
 * único origen que existe en todos los entornos con el mismo id, y el lila no
 * existe todavía en ningún color.
 *
 * Dos cosas que conviene mirar antes de lanzarlo:
 * - EL PRECIO. Se copia el del liso (24,14 €). Si el estampado cuesta más, se
 *   pone 'precio' => '26.50' (o lo que sea) en cada bloque y el script lo
 *   aplica a las cinco tallas.
 * - EL TEXTO. La primera frase del body pasa a nombrar el estampado, en los
 *   cinco idiomas. Es copy nuevo y hay que aprobarlo; el resto del cuerpo es
 *   el del liso y vale igual.
 *
 * Los nombres de foto salen de dos patrones, así que si los ficheros se llaman
 * de otra forma se corrige arriba una vez y valen los ocho.
 *
 * Uso:
 *   drush php:script scripts/clonar-producto.php \
 *     -- scripts/tandas/269-estampado.php --crear
 */

declare(strict_types=1);

// Patrones de nombre de fichero, dentro de fotos-clones/.
$principal = 'fotos-clones/bluson-estampado-%s.jpg';
$puesto = 'fotos-clones/bluson-estampado-%s-puesto.jpg';

// El estampado, tal cual va serigrafiado: no se traduce.
$lema = 'Teachers are the real influencers';

// Un bloque por color. 'foto' es el trozo que va en el nombre del fichero y
// 'sku' el que sustituye a PIST en el SKU del origen (BLUS.PIST.T-S).
$colores = [
  [
    'foto' => 'blanco',
    'sku' => 'EST.BLAN',
    'titulos' => [
      'es' => 'Blusón Educadora Infantil estampado color blanco',
      'ca' => 'Brusa d’educadora infantil estampada de color blanc',
      'en' => 'Printed White Early Years Educator Tunic',
      'fr' => 'Blouse d’éducatrice de jeunes enfants imprimée couleur blanche',
      'it' => 'Blusa da educatrice dell’infanzia stampata color bianco',
    ],
    'frase' => [
      'es' => 'de color blanco con el estampado «' . $lema . '».',
      'ca' => 'de color blanc amb l’estampat «' . $lema . '».',
      'en' => 'in white with the “' . $lema . '” print.',
      'fr' => 'de couleur blanche avec l’imprimé « ' . $lema . ' ».',
      'it' => 'di colore bianco con la stampa «' . $lema . '».',
    ],
  ],
  [
    'foto' => 'pistacho',
    'sku' => 'EST.PIST',
    'titulos' => [
      'es' => 'Blusón Educadora Infantil estampado color pistacho',
      'ca' => 'Brusa d’educadora infantil estampada de color festuc',
      'en' => 'Printed Pistachio-coloured Early Years Educator Tunic',
      'fr' => 'Blouse d’éducatrice de jeunes enfants imprimée couleur pistache',
      'it' => 'Blusa da educatrice dell’infanzia stampata color pistacchio',
    ],
    'frase' => [
      'es' => 'de color pistacho con el estampado «' . $lema . '».',
      'ca' => 'de color pistatxo amb l’estampat «' . $lema . '».',
      'en' => 'in pistachio green with the “' . $lema . '” print.',
      'fr' => 'de couleur pistache avec l’imprimé « ' . $lema . ' ».',
      'it' => 'di colore pistacchio con la stampa «' . $lema . '».',
    ],
  ],
  [
    'foto' => 'rojo',
    'sku' => 'EST.ROJO',
    'titulos' => [
      'es' => 'Blusón Educadora Infantil estampado color rojo',
      'ca' => 'Brusa d’educadora infantil estampada de color vermell',
      'en' => 'Printed Red Early Years Educator Tunic',
      'fr' => 'Blouse d’éducatrice de jeunes enfants imprimée couleur rouge',
      'it' => 'Blusa da educatrice dell’infanzia stampata color rosso',
    ],
    'frase' => [
      'es' => 'de color rojo con el estampado «' . $lema . '».',
      'ca' => 'de color vermell amb l’estampat «' . $lema . '».',
      'en' => 'in red with the “' . $lema . '” print.',
      'fr' => 'de couleur rouge avec l’imprimé « ' . $lema . ' ».',
      'it' => 'di colore rosso con la stampa «' . $lema . '».',
    ],
  ],
  [
    'foto' => 'lila',
    'sku' => 'EST.LILA',
    'titulos' => [
      'es' => 'Blusón Educadora Infantil estampado color lila',
      'ca' => 'Brusa d’educadora infantil estampada de color lila',
      'en' => 'Printed Lilac Early Years Educator Tunic',
      'fr' => 'Blouse d’éducatrice de jeunes enfants imprimée couleur lilas',
      'it' => 'Blusa da educatrice dell’infanzia stampata color lilla',
    ],
    'frase' => [
      'es' => 'de color lila con el estampado «' . $lema . '».',
      'ca' => 'de color lila amb l’estampat «' . $lema . '».',
      'en' => 'in lilac with the “' . $lema . '” print.',
      'fr' => 'de couleur lilas avec l’imprimé « ' . $lema . ' ».',
      'it' => 'di colore lilla con la stampa «' . $lema . '».',
    ],
  ],
];

// La frase del origen que se sustituye, por idioma. Es la primera del body y
// la única que nombra el color: comprobado, el color aparece una sola vez en
// cada uno de los cinco textos.
$frase_origen = [
  'es' => 'de color pistacho.',
  'ca' => 'de color pistatxo.',
  'en' => 'in pistachio green.',
  'fr' => 'de couleur pistache.',
  'it' => 'di colore pistacchio.',
];

$tandas = [];
foreach ($colores as $color) {
  $texto = [];
  foreach ($frase_origen as $idioma => $origen) {
    $texto[$idioma] = [$origen => $color['frase'][$idioma]];
  }
  $tandas[] = [
    'origen' => 269,
    'titulos' => $color['titulos'],
    'texto' => $texto,
    'sku' => ['PIST' => $color['sku']],
    'skus' => [],
    'stock' => 0,
    'color_attr' => NULL,
    'hilo' => NULL,
    'fotos' => [
      'principal' => sprintf($principal, $color['foto']),
      'galeria' => [sprintf($puesto, $color['foto'])],
    ],
  ];
}

return $tandas;
