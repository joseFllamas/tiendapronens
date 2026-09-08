<?php

/**
 * @file
 * Traduce los estados del pedido, del pago y de la factura en el backoffice.
 *
 * Uso: `ddev drush php:script scripts/traducir-pedidos-admin.php`.
 *
 * Hermano de scripts/traducir-envios.php, que hizo lo mismo con la pantalla de
 * envíos. Quedaba el resto del área de pedidos: la lista de
 * /admin/commerce/orders decía «Completed» en la columna de estado, la pestaña
 * de facturas «Paid» y la de pagos «Completed» y «New». Commerce no traduce sus
 * workflows y las cadenas venían sin traducir en los cinco idiomas.
 *
 * Lo que hay que saber para volver a tocarlo:
 *
 * - **Los CONTEXTOS son obligatorios.** `WorkflowState::getLabel()` pide la
 *   cadena con contexto `workflow state` y `WorkflowTransition::getLabel()` con
 *   `workflow transition`. Traducidas sin contexto no las mira nadie y la
 *   pantalla sigue en inglés, sin ningún aviso.
 * - **Una cadena la comparten todos los workflows.** «Completed» es la misma
 *   entrada para el pedido y para el pago, y «Refunded» para el pago y la
 *   factura. Así que las etiquetas se eligen para que valgan en los dos sitios
 *   y no concuerdan con el género de ninguna palabra: son chips sueltos, no
 *   frases. De ahí «Reembolsado» para el pago y la factura, y «Pagada» solo
 *   para la factura, que es donde aparece ese estado.
 * - **«Refunded» se traduce por «Reembolsado» y no por «Devuelto»** a
 *   propósito: en esta tienda «Devuelto» es el paquete que Correos Express trae
 *   de vuelta, y son dos cosas distintas que conviene no llamar igual.
 * - **Las transiciones se traducen por lo que hacen**, no palabra por palabra,
 *   igual que en la pantalla de envíos: son botones.
 * - Draft, Ready, Shipped, Canceled y «Resend confirmation» ya las cubre
 *   traducir-envios.php. Este script solo escribe donde no hay traducción, así
 *   que se pueden lanzar los dos en cualquier orden.
 *
 * Las traducciones de interfaz son CONTENIDO de la base de datos: no viajan en
 * config/sync y hay que ejecutar el script también en producción.
 */

declare(strict_types=1);

use Drupal\locale\SourceString;

$almacen = \Drupal::service('locale.storage');

$porContexto = [];

$porContexto['workflow state'] = [
  // Pedido: order_default (el que usa la tienda) y los tres workflows que
  // Commerce ofrece por si algún día se cambia el tipo de pedido.
  'Completed' => [
    'es' => 'Completado',
    'ca' => 'Completat',
    'fr' => 'Terminé',
    'it' => 'Completato',
  ],
  'Validation' => [
    'es' => 'Pendiente de validar',
    'ca' => 'Pendent de validar',
    'fr' => 'En attente de validation',
    'it' => 'In attesa di convalida',
  ],
  'Fulfillment' => [
    'es' => 'En preparación',
    'ca' => 'En preparació',
    'fr' => 'En préparation',
    'it' => 'In preparazione',
  ],

  // Pago.
  'New' => [
    'es' => 'Nuevo',
    'ca' => 'Nou',
    'fr' => 'Nouveau',
    'it' => 'Nuovo',
  ],
  'Pending' => [
    'es' => 'Pendiente',
    'ca' => 'Pendent',
    'fr' => 'En attente',
    'it' => 'In attesa',
  ],
  'Authorization' => [
    'es' => 'Autorizado',
    'ca' => 'Autoritzat',
    'fr' => 'Autorisé',
    'it' => 'Autorizzato',
  ],
  'Authorization (Voided)' => [
    'es' => 'Autorización anulada',
    'ca' => 'Autorització anul·lada',
    'fr' => 'Autorisation annulée',
    'it' => 'Autorizzazione annullata',
  ],
  'Authorization (Expired)' => [
    'es' => 'Autorización caducada',
    'ca' => 'Autorització caducada',
    'fr' => 'Autorisation expirée',
    'it' => 'Autorizzazione scaduta',
  ],
  'Capture denied' => [
    'es' => 'Cobro denegado',
    'ca' => 'Cobrament denegat',
    'fr' => 'Encaissement refusé',
    'it' => 'Incasso negato',
  ],
  'Voided' => [
    'es' => 'Anulado',
    'ca' => 'Anul·lat',
    'fr' => 'Annulé',
    'it' => 'Annullato',
  ],
  'Partially refunded' => [
    'es' => 'Reembolsado en parte',
    'ca' => 'Reemborsat en part',
    'fr' => 'Partiellement remboursé',
    'it' => 'Parzialmente rimborsato',
  ],
  'Refunded' => [
    'es' => 'Reembolsado',
    'ca' => 'Reemborsat',
    'fr' => 'Remboursé',
    'it' => 'Rimborsato',
  ],

  // Factura. «Paid» solo existe en el workflow de factura, así que aquí sí se
  // puede concordar en femenino.
  'Paid' => [
    'es' => 'Pagada',
    'ca' => 'Pagada',
    'fr' => 'Payée',
    'it' => 'Pagata',
  ],
  'Pending refund' => [
    'es' => 'Pendiente de reembolso',
    'ca' => 'Pendent de reemborsament',
    'fr' => 'Remboursement en attente',
    'it' => 'Rimborso in attesa',
  ],
];

$porContexto['workflow transition'] = [
  // Pedido. «Place order» coloca el pedido, que en el backoffice es darlo por
  // bueno; «Fulfill order» lo pasa a preparación, y no se traduce por «Marcar
  // como preparado» porque eso ya es lo que dice el botón del ENVÍO.
  'Place order' => [
    'es' => 'Confirmar el pedido',
    'ca' => 'Confirmar la comanda',
    'fr' => 'Valider la commande',
    'it' => "Confermare l'ordine",
  ],
  'Validate order' => [
    'es' => 'Validar el pedido',
    'ca' => 'Validar la comanda',
    'fr' => 'Valider la commande',
    'it' => "Convalidare l'ordine",
  ],
  'Fulfill order' => [
    'es' => 'Pasar a preparación',
    'ca' => 'Passar a preparació',
    'fr' => 'Mettre en préparation',
    'it' => 'Mettere in preparazione',
  ],
  'Cancel order' => [
    'es' => 'Cancelar el pedido',
    'ca' => 'Cancel·lar la comanda',
    'fr' => 'Annuler la commande',
    'it' => "Annullare l'ordine",
  ],

  // Pago.
  'Authorize payment' => [
    'es' => 'Autorizar el pago',
    'ca' => 'Autoritzar el pagament',
    'fr' => 'Autoriser le paiement',
    'it' => 'Autorizzare il pagamento',
  ],
  'Authorize and capture payment' => [
    'es' => 'Autorizar y cobrar',
    'ca' => 'Autoritzar i cobrar',
    'fr' => 'Autoriser et encaisser',
    'it' => 'Autorizzare e incassare',
  ],
  'Capture payment' => [
    'es' => 'Cobrar el pago',
    'ca' => 'Cobrar el pagament',
    'fr' => 'Encaisser le paiement',
    'it' => 'Incassare il pagamento',
  ],
  'Void payment' => [
    'es' => 'Anular el pago',
    'ca' => 'Anul·lar el pagament',
    'fr' => 'Annuler le paiement',
    'it' => 'Annullare il pagamento',
  ],
  'Expire payment' => [
    'es' => 'Caducar la autorización',
    'ca' => 'Caducar l\'autorització',
    'fr' => "Faire expirer l'autorisation",
    'it' => "Far scadere l'autorizzazione",
  ],
  'Partially refund payment' => [
    'es' => 'Reembolsar en parte',
    'ca' => 'Reemborsar en part',
    'fr' => 'Rembourser en partie',
    'it' => 'Rimborsare in parte',
  ],
  'Refund payment' => [
    'es' => 'Reembolsar el pago',
    'ca' => 'Reemborsar el pagament',
    'fr' => 'Rembourser le paiement',
    'it' => 'Rimborsare il pagamento',
  ],
  'Create payment' => [
    'es' => 'Registrar el pago',
    'ca' => 'Registrar el pagament',
    'fr' => 'Enregistrer le paiement',
    'it' => 'Registrare il pagamento',
  ],
  'Receive payment' => [
    'es' => 'Marcar como cobrado',
    'ca' => 'Marcar com a cobrat',
    'fr' => 'Marquer comme encaissé',
    'it' => 'Segnare come incassato',
  ],

  // Factura. Son verbos sueltos en inglés («Confirm», «Pay», «Refund»,
  // «Cancel») y se completan con el objeto para que se entienda qué se toca.
  'Confirm' => [
    'es' => 'Confirmar la factura',
    'ca' => 'Confirmar la factura',
    'fr' => 'Confirmer la facture',
    'it' => 'Confermare la fattura',
  ],
  'Pay' => [
    'es' => 'Marcar como pagada',
    'ca' => 'Marcar com a pagada',
    'fr' => 'Marquer comme payée',
    'it' => 'Segnare come pagata',
  ],
  'Refund' => [
    'es' => 'Marcar como reembolsada',
    'ca' => 'Marcar com a reemborsada',
    'fr' => 'Marquer comme remboursée',
    'it' => 'Segnare come rimborsata',
  ],
  'Cancel' => [
    'es' => 'Cancelar la factura',
    'ca' => 'Cancel·lar la factura',
    'fr' => 'Annuler la facture',
    'it' => 'Annullare la fattura',
  ],
];

$escritas = 0;
$existentes = 0;
foreach ($porContexto as $contexto => $cadenas) {
  printf("\n--- contexto «%s» ---\n", $contexto);
  foreach ($cadenas as $origen => $traducciones) {
    $cadena = $almacen->findString(['source' => $origen, 'context' => $contexto]);
    if ($cadena === NULL) {
      $cadena = new SourceString();
      $cadena->setString($origen);
      $cadena->setStorage($almacen);
      $cadena->context = $contexto;
      $cadena->save();
    }
    printf("%s\n", $origen);

    foreach ($traducciones as $idioma => $texto) {
      $existente = $almacen->findTranslation(['language' => $idioma, 'lid' => $cadena->lid]);
      if ($existente !== NULL && ($existente->translation ?? '') !== '') {
        $existentes++;
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
}

printf("\n%d traducciones nuevas, %d que ya estaban.\n", $escritas, $existentes);
