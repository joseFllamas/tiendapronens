<?php

/**
 * @file
 * Traduce la pantalla de envíos del backoffice.
 *
 * Uso: `ddev drush php:script scripts/traducir-envios.php`.
 *
 * Commerce Shipping no trae traducción de sus estados ni de sus transiciones,
 * así que la pestaña «Envíos» mezclaba castellano e inglés: la columna Estado
 * decía «Draft» y los botones «Finalize shipment» y «Cancel shipment». Cuesta
 * entender qué hace cada uno, y son los botones que mueven un envío hasta
 * «Enviado», que es lo que dispara el aviso al cliente.
 *
 * Dos decisiones sobre el copy:
 *
 * - Las transiciones NO se traducen literalmente. «Finalize shipment» sería
 *   «Finalizar el envío», que suena a terminarlo, cuando lo que hace es marcar
 *   que el paquete ya está preparado. Se traducen por lo que hacen y con el
 *   mismo vocabulario que los estados: «Marcar como preparado» lleva a
 *   «Preparado» y «Marcar como enviado» a «Enviado».
 * - «Shipment #1» se queda en inglés a propósito. No es una cadena de
 *   interfaz: los empaquetadores de Commerce la usan como TÍTULO del envío y se
 *   guarda en la entidad, en el idioma en que navegaba quien compró. Traducirla
 *   haría que el pedido de un francés apareciera en el backoffice como
 *   «Expédition n° 1» y el de un español como «Envío n.º 1».
 *
 * Ojo con los CONTEXTOS, que es lo que hace que una traducción se aplique o se
 * quede mirando: state_machine pide sus etiquetas con contexto propio,
 * `WorkflowTransition::getLabel()` con «workflow transition» y
 * `WorkflowState::getLabel()` con «workflow state». Traducidas sin contexto no
 * las usa nadie.
 *
 * Y «Tracking link» no es una cadena de interfaz: es la etiqueta de un campo de
 * la vista `order_shipments`, o sea configuración, así que va como override por
 * idioma igual que los prefijos de pathauto o el título del buscador.
 *
 * Solo escribe donde no hay traducción, así que no pisa las que ya existían
 * («Shipped» en francés y «Shipments» en tres idiomas venían traducidas).
 */

declare(strict_types=1);

use Drupal\Component\Gettext\PoItem;
use Drupal\locale\SourceString;

$almacen = \Drupal::service('locale.storage');

// Cadenas de interfaz, agrupadas por el contexto con el que las pide el código.
$porContexto = [];

$porContexto['workflow transition'] = [
  // Transiciones: son botones, así que dicen lo que hacen.
  'Finalize shipment' => [
    'es' => 'Marcar como preparado',
    'ca' => 'Marcar com a preparat',
    'fr' => 'Marquer comme prêt',
    'it' => 'Segna come pronto',
  ],
  'Send shipment' => [
    'es' => 'Marcar como enviado',
    'ca' => 'Marcar com a enviat',
    'fr' => 'Marquer comme expédié',
    'it' => 'Segna come spedito',
  ],
  'Cancel shipment' => [
    'es' => 'Cancelar el envío',
    'ca' => "Cancel·lar l'enviament",
    'fr' => "Annuler l'expédition",
    'it' => 'Annulla la spedizione',
  ],
];

$porContexto['workflow state'] = [
  // Estados del envío.
  'Draft' => [
    'es' => 'Borrador',
    'ca' => 'Esborrany',
    'fr' => 'Brouillon',
    'it' => 'Bozza',
  ],
  'Ready' => [
    'es' => 'Preparado',
    'ca' => 'Preparat',
    'fr' => 'Prêt',
    'it' => 'Pronto',
  ],
  'Shipped' => [
    'es' => 'Enviado',
    'ca' => 'Enviat',
    'fr' => 'Expédié',
    'it' => 'Spedito',
  ],
  'Canceled' => [
    'es' => 'Cancelado',
    'ca' => 'Cancel·lat',
    'fr' => 'Annulé',
    'it' => 'Annullato',
  ],
];

$porContexto[''] = [
  // Operaciones y enlaces de la pantalla.
  'Resend confirmation' => [
    'es' => 'Reenviar la confirmación',
    'ca' => 'Reenviar la confirmació',
    'fr' => 'Renvoyer la confirmation',
    'it' => 'Invia di nuovo la conferma',
  ],
  'Add shipment' => [
    'es' => 'Añadir envío',
    'ca' => 'Afegir enviament',
    'fr' => 'Ajouter une expédition',
    'it' => 'Aggiungi spedizione',
  ],
  'Shipments' => [
    'es' => 'Envíos',
    'ca' => 'Enviaments',
    'fr' => 'Expéditions',
    'it' => 'Spedizioni',
  ],
];

// El enlace del pie de la tarjeta del pedido es plural: los dos idiomas de la
// pareja van unidos por el delimitador de plurales, que en Drupal 11 es
// PoItem::DELIMITER (LOCALE_PLURAL_DELIMITER ya no existe).
$plural = 'Manage shipment →' . PoItem::DELIMITER . 'Manage shipments →';
$porContexto[''][$plural] = [
  'es' => 'Gestionar el envío →' . PoItem::DELIMITER . 'Gestionar los envíos →',
  'ca' => "Gestionar l'enviament →" . PoItem::DELIMITER . 'Gestionar els enviaments →',
  'fr' => "Gérer l'expédition →" . PoItem::DELIMITER . 'Gérer les expéditions →',
  'it' => 'Gestisci la spedizione →' . PoItem::DELIMITER . 'Gestisci le spedizioni →',
];

$escritas = 0;
foreach ($porContexto as $contexto => $cadenas) {
  printf("\n--- contexto «%s» ---\n", $contexto !== '' ? $contexto : 'sin contexto');
  foreach ($cadenas as $origen => $traducciones) {
    $cadena = $almacen->findString(['source' => $origen, 'context' => $contexto]);
    if ($cadena === NULL) {
      $cadena = new SourceString();
      $cadena->setString($origen);
      $cadena->setStorage($almacen);
      $cadena->context = $contexto;
      $cadena->save();
    }
    printf("%s\n", str_replace(PoItem::DELIMITER, ' | ', $origen));

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
      printf("  %s: %s\n", $idioma, str_replace(PoItem::DELIMITER, ' | ', $texto));
    }
  }
}

// La etiqueta de la columna de seguimiento vive en la vista, no en las cadenas
// de interfaz. La vista ya tiene overrides en los cinco idiomas, así que solo
// hay que añadirles la clave; el valor base se queda en inglés.
print "\n--- etiqueta de la vista order_shipments ---\n";
$ruta = 'display.default.display_options.fields.tracking_code.label';
$idiomas = \Drupal::service('language.config_factory_override');
$etiquetas = [
  'es' => 'Enlace de seguimiento',
  'ca' => 'Enllaç de seguiment',
  'en' => 'Tracking link',
  'fr' => 'Lien de suivi',
  'it' => 'Link di tracciamento',
];
foreach ($etiquetas as $idioma => $etiqueta) {
  $override = $idiomas->getOverride($idioma, 'views.view.order_shipments');
  $override->set($ruta, $etiqueta)->save();
  printf("  %s: %s\n", $idioma, $etiqueta);
}

// Las tres transiciones se tradujeron primero SIN contexto, que es donde no las
// mira nadie. Se limpian para no dejar cadenas muertas en la interfaz de
// traducción; los estados no, que «Borrador» o «Cancelado» sin contexto sí los
// usa el resto de Drupal.
print "\n--- limpieza de las traducciones sin contexto que no se usan ---\n";
foreach (['Finalize shipment', 'Send shipment', 'Cancel shipment'] as $origen) {
  $huerfana = $almacen->findString(['source' => $origen, 'context' => '']);
  if ($huerfana !== NULL) {
    $almacen->deleteStrings(['lid' => $huerfana->lid]);
    printf("  borrada «%s» sin contexto\n", $origen);
  }
}

printf("\n%d traducciones nuevas.\n", $escritas);
