<?php

/**
 * @file
 * Reescribe "Envíos y devoluciones" y corrige "Formas de pago" (nodos 1 y 2).
 *
 * Los dos textos venían del D7 y contradecían al resto de la tienda en la
 * misma sesión de compra. Lo que decía la página de envíos:
 *
 * - Tarifas de 6 €, 7 €, 9 € (Francia) y 10 € cuando Commerce cobra 5,95 /
 *   7,95 / 12 / 9,95 / 15 €, y ofrecía envíos "a cualquier otra población
 *   mundial" cuando fuera de la UE no se envía.
 * - "siete días" para devolver, frente a los 30 que prometen la ficha, la home
 *   y el pie desde scripts/politicas-copy.php.
 * - "No se realizarán abonos económicos": solo cambio o saldo a favor.
 * - "mientras dure la pandemia del COVID19 no se admitirá ningún tipo de
 *   devolución", texto de 2020 todavía publicado en 2026.
 *
 * Las tres últimas chocan además con el derecho de desistimiento de 14 días
 * naturales del artículo 71 del TRLGDCU. Y la de pagos no nombraba Bizum, que
 * lleva activo desde el 2026-09-03.
 *
 * Se reescribe en los CINCO idiomas: los nodos están traducidos, y dejar el
 * francés o el italiano con el texto viejo sería peor que no tocar nada.
 *
 * Decisiones del cliente (2026-09-03) que el texto da por firmes:
 * - 30 días naturales para devolver (la ley pide 14 como mínimo).
 * - El envío de vuelta lo paga el cliente, salvo defecto de fabricación.
 * - Se devuelve el dinero por el mismo medio de pago, no un vale.
 * - Las prendas bordadas no se devuelven salvo defecto (ya estaba así).
 *
 * Cambia también el correo de contacto, que era victor@pronens.com, por el
 * remitente único pronens@pronens.com que se decidió al montar el correo.
 *
 * Los textos viejos quedan en la revisión anterior de cada nodo.
 *
 * Idempotente. Uso: ddev drush php:script scripts/politicas-envios-pagos.php
 * Es contenido: hay que ejecutarlo también en producción.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;

$idiomas = ['es', 'ca', 'en', 'fr', 'it'];
$carpeta = __DIR__ . '/textos';

// ---------------------------------------------------------------------------
// Nodo 1: Envíos y devoluciones. Reescritura completa.
// ---------------------------------------------------------------------------
$envios = Node::load(1);
if ($envios === NULL) {
  throw new \RuntimeException('No existe el nodo 1 (Envíos y devoluciones).');
}
$envios->setNewRevision(TRUE);
$envios->setRevisionLogMessage('Reescritura de envíos y devoluciones: tarifas reales, 30 días y sin el texto del COVID.');
$envios->setRevisionCreationTime(\Drupal::time()->getRequestTime());

foreach ($idiomas as $idioma) {
  $fichero = $carpeta . '/envios-' . $idioma . '.html';
  if (!is_file($fichero)) {
    echo "  ! falta $fichero\n";
    continue;
  }
  if (!$envios->hasTranslation($idioma)) {
    echo "  ! el nodo 1 no tiene traducción $idioma\n";
    continue;
  }
  $traduccion = $envios->getTranslation($idioma);
  $traduccion->set('body', [
    'value' => trim((string) file_get_contents($fichero)),
    'format' => 'full_html',
  ]);
  echo "  nodo 1 [$idioma]: reescrito\n";
}
$envios->save();

// ---------------------------------------------------------------------------
// Nodo 2: Formas de pago. Solo la frase que enumera los medios de pago.
// ---------------------------------------------------------------------------
// El resto de la página (moneda, impuestos, seguridad de la pasarela y motivos
// de rechazo de una tarjeta) es correcto y no se toca.
$frases = [
  'es' => [
    'de' => 'El pago puede realizarse a &nbsp;través de tarjeta de crédito (VISA, Master Card, American Express) o mediante PayPal',
    'a' => 'Puedes pagar con <strong>tarjeta</strong> (Visa y Mastercard, a través de la pasarela segura de Redsys), con <strong>Bizum</strong> o con <strong>PayPal</strong>. En los tres casos el pago se realiza en el entorno seguro de la entidad y Pronens no llega a ver los datos de tu tarjeta.',
  ],
  'ca' => [
    'de' => 'El pagament es pot fer amb targeta de crèdit (VISA, Master Card, American Express) o mitjançant PayPal',
    'a' => 'Pots pagar amb <strong>targeta</strong> (Visa i Mastercard, a través de la passarel·la segura de Redsys), amb <strong>Bizum</strong> o amb <strong>PayPal</strong>. En els tres casos el pagament es fa a l\'entorn segur de l\'entitat i Pronens no arriba a veure les dades de la teva targeta.',
  ],
  'en' => [
    'de' => 'Payment can be made by credit card (VISA, Master Card, American Express) or via PayPal',
    'a' => 'You can pay by <strong>card</strong> (Visa and Mastercard, through the secure Redsys gateway), with <strong>Bizum</strong> or with <strong>PayPal</strong>. In all three cases the payment is made in the bank\'s secure environment and Pronens never sees your card details.',
  ],
  'fr' => [
    'de' => 'Le paiement peut être effectué par carte bancaire (VISA, Master Card, American Express) ou via PayPal',
    'a' => 'Vous pouvez payer par <strong>carte bancaire</strong> (Visa et Mastercard, via la passerelle sécurisée Redsys), avec <strong>Bizum</strong> ou avec <strong>PayPal</strong>. Dans les trois cas, le paiement s\'effectue dans l\'environnement sécurisé de la banque et Pronens n\'a jamais accès aux données de votre carte.',
  ],
  'it' => [
    'de' => 'Il pagamento può essere effettuato con carta di credito (VISA, Master Card, American Express) o tramite PayPal',
    'a' => 'Puoi pagare con <strong>carta</strong> (Visa e Mastercard, tramite il gateway sicuro di Redsys), con <strong>Bizum</strong> o con <strong>PayPal</strong>. In tutti e tre i casi il pagamento avviene nell\'ambiente sicuro dell\'istituto e Pronens non vede mai i dati della tua carta.',
  ],
];

$pagos = Node::load(2);
if ($pagos === NULL) {
  throw new \RuntimeException('No existe el nodo 2 (Formas de pago).');
}
$pagos->setNewRevision(TRUE);
$pagos->setRevisionLogMessage('Formas de pago: se añade Bizum, activo desde el 2026-09-03.');
$pagos->setRevisionCreationTime(\Drupal::time()->getRequestTime());

foreach ($frases as $idioma => $frase) {
  if (!$pagos->hasTranslation($idioma)) {
    echo "  ! el nodo 2 no tiene traducción $idioma\n";
    continue;
  }
  $traduccion = $pagos->getTranslation($idioma);
  $cuerpo = (string) $traduccion->get('body')->value;
  if (str_contains($cuerpo, 'Bizum')) {
    echo "  nodo 2 [$idioma]: ya nombraba Bizum\n";
    continue;
  }
  if (!str_contains($cuerpo, $frase['de'])) {
    echo "  ! nodo 2 [$idioma]: no se encuentra la frase a sustituir, se salta\n";
    continue;
  }
  $traduccion->set('body', [
    'value' => str_replace($frase['de'], $frase['a'], $cuerpo),
    'format' => 'full_html',
  ]);
  echo "  nodo 2 [$idioma]: Bizum añadido\n";
}
$pagos->save();

echo "Listo. Los textos anteriores quedan en la revisión previa de cada nodo.\n";
