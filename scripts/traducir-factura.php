<?php

/**
 * @file
 * Traduce las cadenas de la factura (PDF y área de cliente).
 *
 * Son las cadenas nuevas de pronens_factura (la plantilla del PDF) y del botón
 * de descarga de la ficha del pedido. Las fuentes están en inglés, como el
 * resto del tema, y sin esto una factura generada desde una compra en /fr/
 * saldría con los rótulos en inglés.
 *
 * "Invoice" y "Order" existen ya en core y en Commerce con otras traducciones
 * ("Factura", "Pedido"), así que no se tocan aquí: solo se declaran las que no
 * traía nadie.
 *
 * Uso: ddev drush php:script scripts/traducir-factura.php
 */

use Drupal\Component\Gettext\PoItem;
use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

// [contexto, fuente, traducciones].
$cadenas = [
  // --- Área de cliente. ---
  ['', 'Download invoice (PDF)', [
    'es' => 'Descargar factura (PDF)',
    'ca' => 'Descarrega la factura (PDF)',
    'fr' => 'Télécharger la facture (PDF)',
    'it' => 'Scarica la fattura (PDF)',
  ]],
  // --- PDF. ---
  ['', 'Invoice number', [
    'es' => 'Número de factura',
    'ca' => 'Número de factura',
    'fr' => 'Numéro de facture',
    'it' => 'Numero di fattura',
  ]],
  ['', 'Invoice date', [
    'es' => 'Fecha de factura',
    'ca' => 'Data de factura',
    'fr' => 'Date de facture',
    'it' => 'Data della fattura',
  ]],
  ['', 'Bill to', [
    'es' => 'Facturar a',
    'ca' => 'Facturar a',
    'fr' => 'Facturé à',
    'it' => 'Fatturare a',
  ]],
  ['', 'Tax ID', [
    'es' => 'NIF / CIF',
    'ca' => 'NIF / CIF',
    'fr' => 'NIF / n° TVA',
    'it' => 'Codice fiscale / P. IVA',
  ]],
  ['', 'Taxable base', [
    'es' => 'Base imponible',
    'ca' => 'Base imposable',
    'fr' => 'Base imposable',
    'it' => 'Base imponibile',
  ]],
  ['', 'Unit price', [
    'es' => 'Precio unitario',
    'ca' => 'Preu unitari',
    'fr' => 'Prix unitaire',
    'it' => 'Prezzo unitario',
  ]],
  ['', 'VAT', [
    'es' => 'IVA',
    'ca' => 'IVA',
    'fr' => 'TVA',
    'it' => 'IVA',
  ]],
];

$lids = [];
foreach ($cadenas as [$contexto, $fuente, $traducciones]) {
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
  $lids[] = $string->lid;

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
}

// Sin esto la interfaz sigue sirviendo el inglés desde la caché de cadenas.
_locale_refresh_translations(['es', 'ca', 'fr', 'it'], $lids);
print "Traducciones refrescadas.\n";
