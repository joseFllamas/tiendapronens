<?php

/**
 * @file
 * Tanda: el blusón de educadora (269, pistacho) en rojo y en blanco.
 *
 * Ejecutada en producción el 2026-09-09. Se queda como registro de lo que se
 * hizo y de con qué datos.
 *
 * Se clonan los dos del 269 y no uno del otro: el origen es el único que
 * existe en todos los entornos con el mismo id.
 *
 * Uso:
 *   drush php:script scripts/clonar-producto.php \
 *     -- scripts/tandas/269-colores.php --crear
 */

declare(strict_types=1);

return [
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
