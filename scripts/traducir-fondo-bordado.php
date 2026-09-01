<?php

/**
 * @file
 * Traduce las cadenas del fondo del bordado.
 *
 * Son dos: la etiqueta del selector de la ficha, que se repite en el carrito,
 * la cesta, el resumen del checkout y el correo del pedido, y el error de
 * validación de cuando llega un fondo que el producto no ofrece.
 *
 * "Background" va con contexto propio, `Embroidery`: a secas es una palabra
 * genérica (fondo de pantalla, fondo de un bloque) que cualquier otro módulo
 * puede registrar con otro significado y acabaría compartiendo traducción.
 * Aquí es la nube sobre la que se borda el nombre.
 *
 * Uso: ddev drush php:script scripts/traducir-fondo-bordado.php
 */

use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

$cadenas = [
  ['Background', 'Embroidery', [
    'es' => 'Fondo',
    'ca' => 'Fons',
    'fr' => 'Fond',
    'it' => 'Sfondo',
  ]],
  ['Choose the background for the embroidery.', '', [
    'es' => 'Elige el fondo sobre el que va el bordado.',
    'ca' => 'Tria el fons sobre el qual va el brodat.',
    'fr' => 'Choisissez le fond sur lequel va la broderie.',
    'it' => 'Scegli lo sfondo su cui va il ricamo.',
  ]],
];

foreach ($cadenas as [$fuente, $contexto, $traducciones]) {
  $string = $storage->findString(['source' => $fuente, 'context' => $contexto]);
  if ($string === NULL) {
    $string = new SourceString();
    $string->setString($fuente);
    $string->setStorage($storage);
    $string->context = $contexto;
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
print "\nHecho.\n";
