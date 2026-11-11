<?php

/**
 * @file
 * Traduce el campo de stock del formulario de la variación.
 *
 * Uso: `ddev drush php:script scripts/traducir-stock.php`.
 *
 * El stock se edita en la propia variación con el widget «Absolute stock
 * level» de commerce_stock_field: se escribe el stock que se quiere tener y el
 * módulo crea la transacción de diferencia. Sus textos no vienen traducidos, y
 * la descripción original además asusta («may end in unexpected results»), así
 * que se reescribe diciendo lo que hace de verdad. Los textos se piden sin
 * contexto. Solo escribe donde no hay traducción.
 */

declare(strict_types=1);

use Drupal\locale\SourceString;

$almacen = \Drupal::service('locale.storage');

$cadenas = [
  'Absolute stock level settings' => [
    'es' => 'Stock disponible',
    'ca' => 'Estoc disponible',
    'fr' => 'Stock disponible',
    'it' => 'Scorte disponibili',
  ],
  'Sets the stock level. Current stock level: @stock_level. Note: Under the hood we create a transaction. Setting the absolute stock level may end in unexpected results. Learn more about transactional inventory management in the docs.' => [
    'es' => 'Stock actual: @stock_level. Escribe las unidades que hay ahora y se guardará la diferencia como un movimiento de stock. Déjalo vacío para no cambiarlo.',
    'ca' => 'Estoc actual: @stock_level. Escriu les unitats que hi ha ara i es desarà la diferència com un moviment d\'estoc. Deixa-ho buit per no canviar-lo.',
    'fr' => 'Stock actuel : @stock_level. Indiquez les unités disponibles maintenant ; la différence sera enregistrée comme un mouvement de stock. Laissez vide pour ne rien changer.',
    'it' => 'Scorte attuali: @stock_level. Indica le unità disponibili ora e la differenza verrà registrata come movimento di magazzino. Lascia vuoto per non modificarle.',
  ],
];

$escritas = 0;
foreach ($cadenas as $origen => $traducciones) {
  $cadena = $almacen->findString(['source' => $origen, 'context' => '']);
  if ($cadena === NULL) {
    $cadena = new SourceString();
    $cadena->setString($origen);
    $cadena->setStorage($almacen);
    $cadena->context = '';
    $cadena->save();
  }
  printf("%s\n", mb_substr($origen, 0, 60));
  foreach ($traducciones as $idioma => $texto) {
    $existente = $almacen->findTranslation(['language' => $idioma, 'lid' => $cadena->lid]);
    if ($existente !== NULL && ($existente->translation ?? '') !== '') {
      printf("  %s: ya traducida (%s)\n", $idioma, $existente->translation);
      continue;
    }
    $almacen->createTranslation([
      'lid' => $cadena->lid,
      'language' => $idioma,
      'translation' => $texto,
    ])->save();
    $escritas++;
    printf("  %s: %s\n", $idioma, $texto);
  }
}

printf("\n%d traducciones nuevas.\n", $escritas);
