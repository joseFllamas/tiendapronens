<?php

/**
 * @file
 * Traduce el aviso de la lupa del lightbox de la ficha.
 *
 * Son dos cadenas nuevas del tema (js/ficha.js), una para ratón y otra para
 * pantalla táctil, y no las trae traducidas nadie. Sin esto la barra del
 * lightbox saldría en inglés en los cinco idiomas, porque el castellano es el
 * idioma por defecto del sitio y las cadenas fuente están en inglés.
 *
 * Uso: ddev drush php:script scripts/traducir-lupa.php
 */

use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

$cadenas = [
  'Hover over the photo to zoom' => [
    'es' => 'Pasa el ratón por la foto para ampliarla',
    'ca' => 'Passa el ratolí per la foto per ampliar-la',
    'fr' => 'Survolez la photo pour l\'agrandir',
    'it' => 'Passa il mouse sulla foto per ingrandirla',
  ],
  'Tap the photo to zoom' => [
    'es' => 'Toca la foto para ampliarla',
    'ca' => 'Toca la foto per ampliar-la',
    'fr' => 'Touchez la photo pour l\'agrandir',
    'it' => 'Tocca la foto per ingrandirla',
  ],
];

foreach ($cadenas as $fuente => $traducciones) {
  $string = $storage->findString(['source' => $fuente, 'context' => '']);
  if ($string === NULL) {
    $string = new SourceString();
    $string->setString($fuente);
    $string->setStorage($storage);
    $string->context = '';
    $string->save();
    print "Cadena creada: {$fuente} (lid {$string->lid}).\n";
  }
  else {
    print "Cadena ya existente: {$fuente} (lid {$string->lid}).\n";
  }

  foreach ($traducciones as $idioma => $texto) {
    $existente = $storage->findTranslation([
      'language' => $idioma,
      'lid' => $string->lid,
    ]);
    if ($existente !== NULL && $existente->translation === $texto) {
      print "  {$idioma}: sin cambios.\n";
      continue;
    }
    $storage->createTranslation([
      'lid' => $string->lid,
      'language' => $idioma,
      'translation' => $texto,
    ])->save();
    print "  {$idioma}: {$texto}\n";
  }
}

\Drupal::service('cache.default')->invalidateAll();
print "\nHecho. Vacía la caché para que el JS sirva las cadenas nuevas.\n";
