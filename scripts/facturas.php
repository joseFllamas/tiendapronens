<?php

/**
 * @file
 * Engancha la serie de facturas con la del D7 y factura lo ya vendido.
 *
 * Uso: `ddev drush php:script scripts/facturas.php -- 11 [--generar]
 * [--con-correo]`, donde 11 es el último número del D7 en el año en curso.
 *
 * En el Drupal 7 la factura la hacía commerce_billy con el patrón AÑO-N y
 * reinicio anual (2014-1 … 2026-11 en el dump del 26/07/2026), y el número de
 * factura pasaba a ser el número visible del pedido. Aquí las dos series van
 * separadas: el pedido lleva P-AÑO-NNNN y la factura sigue la serie fiscal de
 * siempre, `[pattern:year]-[pattern:number]` (config `invoice_default`).
 *
 * La continuidad NO se hace con el "número inicial" del patrón: el plugin
 * yearly vuelve a ese número cada enero, así que un initial_number de 12
 * daría 2027-12 como primera factura del año que viene. Lo que se hace es
 * SEMBRAR la fila de `commerce_number_pattern_sequence`: number = último del
 * D7 en el año en curso y generated = ahora. La siguiente factura sale con el
 * número que toca y en enero la serie arranca en 1.
 *
 * El último número del D7 es un parámetro a propósito: el dump es del 26 de
 * julio y la tienda antigua pudo facturar después. Hay que leerlo del D7 vivo
 * (order_number de los pedidos en estado invoiced) antes de ejecutar esto en
 * producción.
 *
 * Con --generar se emiten las facturas de los pedidos ya realizados que no la
 * tienen (los de antes de instalar commerce_invoice), en orden de compra, y se
 * marcan pagadas. SIN mandar el correo al cliente salvo que se pida con
 * --con-correo: son compras de hace semanas y el cliente debe decidir si las
 * quiere avisar. La fecha de factura es la de hoy, que es cuando se emiten.
 * Solo tiene sentido si esos pedidos no se facturaron ya por otra vía.
 *
 * La secuencia y las facturas son CONTENIDO: no viajan en config/sync y el
 * script hay que ejecutarlo también en producción.
 *
 * Copia previa: snapshot `pre-facturas`.
 */

declare(strict_types=1);

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_order\Entity\OrderInterface;

$patron_id = 'invoice_default';
$tipo_id = 'default';

$argumentos = $extra ?? [];
$ultimo_d7 = NULL;
foreach ($argumentos as $arg) {
  if (ctype_digit((string) $arg)) {
    $ultimo_d7 = (int) $arg;
  }
}
$generar = in_array('--generar', $argumentos, TRUE);
$con_correo = in_array('--con-correo', $argumentos, TRUE);

if ($ultimo_d7 === NULL) {
  print "Falta el último número de factura del D7 en el año en curso (en el dump del 26/07/2026 es 11).\n";
  print "Uso: ddev drush php:script scripts/facturas.php -- 11 [--generar] [--con-correo]\n";
  exit(1);
}

$conexion = \Drupal::database();
$ahora = \Drupal::time()->getRequestTime();
$anyo = date('Y', $ahora);
$store_id = 1;

// --- 1. La secuencia. ---
$fila = $conexion->select('commerce_number_pattern_sequence', 's')
  ->fields('s', ['number', 'generated'])
  ->condition('entity_id', $patron_id)
  ->condition('store_id', $store_id)
  ->execute()->fetchAssoc();

if ($fila && date('Y', (int) $fila['generated']) === $anyo && (int) $fila['number'] >= $ultimo_d7) {
  printf("  secuencia %s: ya va por el %d en %s, no se toca\n", $patron_id, $fila['number'], $anyo);
}
else {
  $conexion->merge('commerce_number_pattern_sequence')
    ->keys(['entity_id' => $patron_id, 'store_id' => $store_id])
    ->fields(['number' => $ultimo_d7, 'generated' => $ahora])
    ->execute();
  printf("  secuencia %s: sembrada en %d (la siguiente factura será %s-%d; en enero, %d-1)\n",
    $patron_id, $ultimo_d7, $anyo, $ultimo_d7 + 1, (int) $anyo + 1);
}

if (!$generar) {
  print "Listo (sin --generar no se emite ninguna factura).\n";
  return;
}

// --- 2. Facturas de lo ya vendido. ---
$tipo = InvoiceType::load($tipo_id);
$enviaba = (bool) $tipo->get('sendConfirmation');
if ($enviaba && !$con_correo) {
  // El subscriber de commerce_invoice mira el tipo al confirmar y al pagar;
  // apagado durante el script, no sale ningún correo.
  $tipo->set('sendConfirmation', FALSE)->save();
}

$pedidos = \Drupal::entityTypeManager()->getStorage('commerce_order');
$facturas = \Drupal::entityTypeManager()->getStorage('commerce_invoice');
$generador = \Drupal::service('commerce_invoice.invoice_generator');

$ids = $pedidos->getQuery()->accessCheck(FALSE)
  ->condition('state', 'draft', '<>')
  ->exists('placed')
  ->sort('placed')->sort('order_id')
  ->execute();

$emitidas = 0;
try {
  foreach ($pedidos->loadMultiple($ids) as $pedido) {
    assert($pedido instanceof OrderInterface);
    $tiene = $facturas->getQuery()->accessCheck(FALSE)
      ->condition('orders', $pedido->id())
      ->condition('state', 'canceled', '<>')
      ->count()->execute();
    if ($tiene) {
      printf("  pedido %s: ya tiene factura\n", $pedido->getOrderNumber());
      continue;
    }
    if ($pedido->getStoreId() !== NULL && (int) $pedido->getStoreId() !== $store_id) {
      printf("  pedido %s: de otra tienda, se salta\n", $pedido->getOrderNumber());
      continue;
    }

    $factura = $generador->generate([$pedido], $pedido->getStore(), $pedido->getBillingProfile(), [
      'type' => $tipo_id,
      'uid' => $pedido->getCustomerId(),
    ]);
    if (!$factura instanceof InvoiceInterface) {
      printf("  pedido %s: NO se pudo generar la factura (mira el log)\n", $pedido->getOrderNumber());
      continue;
    }
    // El pedido ya se pagó al comprar: la factura nace pagada. No basta con
    // isPaid(): el workflow order_default completa el pedido al comprar y hay
    // pedidos reales sin pago registrado (el retorno de Redsys falló y se
    // colocaron a mano), así que "completado" también cuenta como cobrado.
    $cobrado = $pedido->isPaid() || $pedido->getState()->getId() === 'completed';
    if ($cobrado && $factura->getState()->getId() === 'pending') {
      $factura->getState()->applyTransitionById('pay');
      $factura->save();
    }
    $emitidas++;
    printf("  pedido %s (%s): factura %s, %s, %s\n",
      $pedido->getOrderNumber(),
      date('Y-m-d', (int) $pedido->getPlacedTime()),
      $factura->getInvoiceNumber(),
      $factura->getState()->getLabel(),
      $factura->getTotalPrice()?->__toString() ?? '?');
  }
}
finally {
  if ($enviaba && !$con_correo) {
    InvoiceType::load($tipo_id)->set('sendConfirmation', TRUE)->save();
  }
}

printf("Listo: %d facturas emitidas%s.\n", $emitidas, $con_correo ? ' y enviadas por correo' : ' sin correo al cliente');
