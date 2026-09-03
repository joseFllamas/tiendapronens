<?php

/**
 * @file
 * Traduce las cadenas nuevas de la re-auditoría SEO/GEO del 2026-09-03.
 *
 * Son dos: la descripción de reserva de las categorías que llegaron de la
 * migración sin texto propio (8 de 30, entre ellas Iniciales), y la etiqueta
 * del enlace a la web del fabricante en el pie.
 *
 * La descripción de reserva NO es contenido inventado: repite los tres datos
 * que ya dicen el marquee, la home y la ficha (taller de Barcelona, bordado en
 * 72 h y envío gratis en España peninsular desde 60 €). En cuanto el cliente
 * escriba la descripción del término, la suya manda y esto deja de salir.
 *
 * Uso: ddev drush php:script scripts/traducir-seo-higiene.php
 */

declare(strict_types=1);

use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

$cadenas = [
  "@categoria by Pronens: personalised kids and school wear, embroidered with a name or initial at our Barcelona workshop. Embroidered in 72 h, free shipping in mainland Spain from €60." => [
    'es' => '@categoria de Pronens: ropa infantil y escolar personalizada con el nombre o la inicial bordada en nuestro taller de Barcelona. Bordado en 72 h y envío gratis en España peninsular desde 60 €.',
    'ca' => '@categoria de Pronens: roba infantil i escolar personalitzada amb el nom o la inicial brodada al nostre taller de Barcelona. Brodat en 72 h i enviament gratis a la Espanya peninsular des de 60 €.',
    'fr' => '@categoria de Pronens : vêtements pour enfants et scolaires personnalisés, brodés au nom ou à l\'initiale dans notre atelier de Barcelone. Brodé en 72 h et livraison gratuite en Espagne péninsulaire dès 60 €.',
    'it' => '@categoria di Pronens: abbigliamento per bambini e per la scuola personalizzato, ricamato con il nome o l\'iniziale nel nostro laboratorio di Barcellona. Ricamato in 72 h e spedizione gratuita nella Spagna peninsulare da 60 €.',
  ],
];

$lids = [];
foreach ($cadenas as $origen => $traducciones) {
  $string = $storage->findString(['source' => $origen, 'context' => '']);
  if ($string === NULL) {
    $string = new SourceString();
    $string->setString($origen);
    $string->setStorage($storage);
    $string->context = '';
    $string->save();
    print "Cadena creada (lid {$string->lid}).\n";
  }
  else {
    print "Cadena ya existente (lid {$string->lid}).\n";
  }
  $lids[] = $string->lid;

  foreach ($traducciones as $idioma => $texto) {
    $existente = $storage->findTranslation(['language' => $idioma, 'lid' => $string->lid]);
    if ($existente !== NULL && $existente->translation === $texto) {
      print "  {$idioma}: sin cambios.\n";
      continue;
    }
    $storage->createTranslation([
      'lid' => $string->lid,
      'language' => $idioma,
      'translation' => $texto,
    ])->save();
    print "  {$idioma}: " . mb_substr($texto, 0, 60) . "...\n";
  }
}

// Sin esto la interfaz sigue sirviendo el castellano desde la caché de cadenas.
_locale_refresh_translations(['es', 'ca', 'fr', 'it'], $lids);
print "Traducciones refrescadas.\n";
