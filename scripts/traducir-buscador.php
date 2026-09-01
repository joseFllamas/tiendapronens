<?php

/**
 * @file
 * Traduce las cadenas nuevas del buscador y del detalle de pedido admin.
 *
 * Las cadenas fuente están en inglés (la convención del tema) y son nuevas,
 * así que no las trae traducidas nadie: sin esto saldrían en inglés en los
 * cinco idiomas, castellano incluido. Son el overlay del buscador, la página
 * /buscar y los dos enlaces del detalle de línea del backoffice.
 *
 * Uso: ddev drush php:script scripts/traducir-buscador.php
 */

use Drupal\Component\Gettext\PoItem;
use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

$cadenas = [
  'Search by name or reference' => [
    'es' => 'Busca por nombre o referencia',
    'ca' => 'Cerca per nom o referència',
    'fr' => 'Recherchez par nom ou référence',
    'it' => 'Cerca per nome o riferimento',
  ],
  'From @price' => [
    'es' => 'Desde @price',
    'ca' => 'Des de @price',
    'fr' => 'À partir de @price',
    'it' => 'Da @price',
  ],
  'No results for "@term"' => [
    'es' => 'No hay resultados para "@term"',
    'ca' => 'No hi ha resultats per a "@term"',
    'fr' => 'Aucun résultat pour "@term"',
    'it' => 'Nessun risultato per "@term"',
  ],
  'View all results (@total)' => [
    'es' => 'Ver todos los resultados (@total)',
    'ca' => 'Veure tots els resultats (@total)',
    'fr' => 'Voir tous les résultats (@total)',
    'it' => 'Vedi tutti i risultati (@total)',
  ],
  'No results for "@term". Try the product name or its reference (SKU).' => [
    'es' => 'No hay resultados para "@term". Prueba con el nombre del producto o su referencia (SKU).',
    'ca' => 'No hi ha resultats per a "@term". Prova amb el nom del producte o la seva referència (SKU).',
    'fr' => 'Aucun résultat pour "@term". Essayez le nom du produit ou sa référence (SKU).',
    'it' => 'Nessun risultato per "@term". Prova con il nome del prodotto o il suo riferimento (SKU).',
  ],
  'Type what you are looking for: a product name or its reference (SKU).' => [
    'es' => 'Escribe lo que buscas: el nombre de un producto o su referencia (SKU).',
    'ca' => 'Escriu el que cerques: el nom d\'un producte o la seva referència (SKU).',
    'fr' => 'Écrivez ce que vous cherchez : le nom d\'un produit ou sa référence (SKU).',
    'it' => 'Scrivi cosa cerchi: il nome di un prodotto o il suo riferimento (SKU).',
  ],
  // Recuento de la página /buscar (plural).
  '1 result for "@term"' . PoItem::DELIMITER . '@count results for "@term"' => [
    'es' => '1 resultado para "@term"' . PoItem::DELIMITER . '@count resultados para "@term"',
    'ca' => '1 resultat per a "@term"' . PoItem::DELIMITER . '@count resultats per a "@term"',
    'fr' => '1 résultat pour "@term"' . PoItem::DELIMITER . '@count résultats pour "@term"',
    'it' => '1 risultato per "@term"' . PoItem::DELIMITER . '@count risultati per "@term"',
  ],
  // Detalle de línea del pedido en el backoffice (PedidoAdminHooks).
  'Edit variation' => [
    'es' => 'Editar variación',
    'ca' => 'Edita la variació',
    'fr' => 'Modifier la déclinaison',
    'it' => 'Modifica la variante',
  ],
  'View in the shop with this variation selected' => [
    'es' => 'Ver en la tienda con esta variación seleccionada',
    'ca' => 'Veure a la botiga amb aquesta variació seleccionada',
    'fr' => 'Voir dans la boutique avec cette déclinaison sélectionnée',
    'it' => 'Vedi nel negozio con questa variante selezionata',
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
    print "Cadena creada (lid {$string->lid}): " . str_replace(PoItem::DELIMITER, ' / ', $fuente) . "\n";
  }
  else {
    print "Cadena ya existente (lid {$string->lid}): " . str_replace(PoItem::DELIMITER, ' / ', $fuente) . "\n";
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
}

\Drupal::service('cache.default')->invalidateAll();
print "\nHecho.\n";
