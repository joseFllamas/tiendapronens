<?php

/**
 * @file
 * Traduce las cadenas de interfaz que usan las plantillas de correo.
 *
 * Uso: `ddev drush php:script scripts/traducir-correo.php`.
 *
 * El COPY de los correos se queda en castellano por decisión del cliente, pero
 * el marco no es copy: son cadenas de interfaz de la plantilla, las mismas que
 * ya se traducen en el resto del sitio, y salían en inglés dentro de un correo
 * español ("Tracking number: PRUEBA123"). Las demás que usa el recibo (Order,
 * Quantity, Embroidery, Subtotal, Total, Shipping address, Billing address) ya
 * venían traducidas por core y Commerce; estas dos no existían en ningún
 * idioma.
 */

declare(strict_types=1);

use Drupal\locale\SourceString;

$almacen = \Drupal::service('locale.storage');

$cadenas = [
  'Tracking number' => [
    'es' => 'Nº de seguimiento',
    'ca' => 'Núm. de seguiment',
    'fr' => 'Numéro de suivi',
    'it' => 'Numero di tracciamento',
  ],
  'Order comments' => [
    'es' => 'Comentarios del pedido',
    'ca' => 'Comentaris de la comanda',
    'fr' => 'Commentaires de la commande',
    'it' => 'Note dell’ordine',
  ],
];

foreach ($cadenas as $origen => $traducciones) {
  $cadena = $almacen->findString(['source' => $origen, 'context' => '']);
  if ($cadena === NULL) {
    $cadena = new SourceString();
    $cadena->setString($origen);
    $cadena->setStorage($almacen);
    $cadena->context = '';
    $cadena->save();
  }
  print "$origen (lid {$cadena->lid})\n";

  foreach ($traducciones as $idioma => $texto) {
    $existente = $almacen->findTranslation(['language' => $idioma, 'lid' => $cadena->lid]);
    if ($existente !== NULL && $existente->translation === $texto) {
      print "  $idioma: sin cambios\n";
      continue;
    }
    $almacen->createTranslation([
      'lid' => $cadena->lid,
      'language' => $idioma,
      'translation' => $texto,
    ])->save();
    print "  $idioma: $texto\n";
  }
}

print "Listo.\n";
