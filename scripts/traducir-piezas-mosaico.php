<?php

/**
 * @file
 * Traduce el recuento de las teselas del mosaico de la home.
 *
 * La tesela enseña "89 PIEZAS" sobre la foto. La cadena plural es
 * "1 piece"/"@count pieces" con contexto "category tile" y no viene traducida
 * en ningún idioma (el "1 product"/"@count products" del catálogo sí, salvo en
 * italiano), así que las cuatro traducciones se crean aquí. Contexto propio
 * para no pisar los "piece(s)" de core, que hablan de contenido, no de prendas.
 *
 * Uso: ddev drush php:script scripts/traducir-piezas-mosaico.php
 */

use Drupal\Component\Gettext\PoItem;
use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

$source = '1 piece' . PoItem::DELIMITER . '@count pieces';
$context = 'category tile';

$traducciones = [
  'es' => '1 pieza' . PoItem::DELIMITER . '@count piezas',
  'ca' => '1 peça' . PoItem::DELIMITER . '@count peces',
  'fr' => '1 pièce' . PoItem::DELIMITER . '@count pièces',
  'it' => '1 pezzo' . PoItem::DELIMITER . '@count pezzi',
];

$string = $storage->findString(['source' => $source, 'context' => $context]);
if ($string === NULL) {
  $string = new SourceString();
  $string->setString($source);
  $string->setStorage($storage);
  $string->context = $context;
  $string->save();
  print "Cadena creada (lid {$string->lid}).\n";
}
else {
  print "Cadena ya existente (lid {$string->lid}).\n";
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
  print "  {$idioma}: " . str_replace(PoItem::DELIMITER, ' / ', $texto) . "\n";
}

// Sin esto la interfaz sigue sirviendo el inglés desde la caché de cadenas.
_locale_refresh_translations(array_keys($traducciones), [$string->lid]);
print "Traducciones refrescadas.\n";
