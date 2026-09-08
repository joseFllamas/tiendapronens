<?php

/**
 * @file
 * Etiquetas por idioma de las columnas de envío de la lista de pedidos.
 *
 * Uso: `ddev drush php:script scripts/pedidos-admin-etiquetas.php`.
 *
 * Se ejecuta DESPUÉS de scripts/pedidos-admin-envio.php, que es el que crea las
 * columnas y el filtro.
 *
 * Por qué hace falta un script aparte: la vista `commerce_orders` tiene
 * override de configuración por idioma en los CINCO idiomas del sitio, así que
 * la etiqueta de la configuración base no se ve nunca. Se descubrió al cambiar
 * «Estado» por «Estado del pedido»: el script guardaba bien el valor base y la
 * pantalla seguía diciendo «Estado», porque el backoffice se navega en
 * castellano y el override `es` manda. Es el mismo mecanismo que ya se usó para
 * los prefijos de pathauto, el título del buscador y «Tracking link» de la
 * pantalla de envíos.
 *
 * Los textos no son traducción libre: salen del vocabulario que ya usa la
 * tienda en cada idioma (la pestaña de envíos, el correo de expedición y el
 * área de cliente), para que la misma cosa se llame igual en todas las
 * pantallas.
 *
 * Los overrides de idioma SON configuración: viajan en config/sync y en
 * producción entran con `drush cim`. El script se ejecuta aquí y se exporta.
 */

declare(strict_types=1);

$gestorIdiomas = \Drupal::languageManager();

/**
 * Etiquetas por idioma.
 *
 * - envio: columna con el chip de situación y el método debajo.
 * - expedicion: columna con el número de Correos Express.
 * - estado: la columna que ya existía, que era «Estado» a secas y ahora tiene
 *   al lado el estado del envío, así que hay que decir de qué estado habla.
 */
$etiquetas = [
  'es' => [
    'envio' => 'Envío',
    'expedicion' => 'Expedición',
    'estado' => 'Estado del pedido',
  ],
  'ca' => [
    'envio' => 'Enviament',
    'expedicion' => 'Expedició',
    'estado' => 'Estat de la comanda',
  ],
  'en' => [
    'envio' => 'Shipping',
    'expedicion' => 'Tracking',
    'estado' => 'Order status',
  ],
  'fr' => [
    'envio' => 'Livraison',
    'expedicion' => 'Suivi',
    'estado' => 'Statut de la commande',
  ],
  'it' => [
    'envio' => 'Spedizione',
    'expedicion' => 'Tracking',
    'estado' => "Stato dell'ordine",
  ],
];

$disponibles = array_keys($gestorIdiomas->getLanguages());
$hechos = 0;

foreach ($etiquetas as $idioma => $textos) {
  if (!in_array($idioma, $disponibles, TRUE)) {
    echo "Idioma $idioma no activo en el sitio: se salta.\n";
    continue;
  }

  $override = $gestorIdiomas->getLanguageConfigOverride($idioma, 'views.view.commerce_orders');

  // El override guarda solo las claves que sobreescribe, así que se escribe
  // cada una por su ruta y no se toca nada de lo que ya había traducido.
  $override->set('display.default.display_options.fields.pronens_envio_pedido.label', $textos['envio']);
  $override->set('display.default.display_options.fields.pronens_expedicion_pedido.label', $textos['expedicion']);
  $override->set('display.default.display_options.fields.state.label', $textos['estado']);

  // El filtro nuevo y el que ya había, con el mismo criterio.
  $override->set('display.default.display_options.filters.pronens_situacion_envio.expose.label', $textos['envio']);
  $override->set('display.default.display_options.filters.state.expose.label', $textos['estado']);

  $override->save();
  $hechos++;
  echo "$idioma: {$textos['estado']} · {$textos['envio']} · {$textos['expedicion']}\n";
}

echo "\nOverrides escritos: $hechos.\n";
