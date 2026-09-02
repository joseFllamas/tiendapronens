<?php

/**
 * @file
 * Pasa los pedidos numerados «1, 2, 3…» a la serie P-AÑO-NNNN.
 *
 * Se ejecuta con `ddev drush php:script scripts/renumerar-pedidos.php`.
 *
 * La tienda arrancó con el patrón de fábrica de Commerce (`[pattern:number]`,
 * infinito), así que los primeros pedidos reales salieron como «1», «2», «3» y
 * «4». Eso choca con la tienda antigua, donde el número visible era el de la
 * factura (2014-1 … 2026-11, con reinicio anual), y un «pedido 4» en el correo
 * del cliente da imagen de tienda recién abierta. El cliente decidió
 * (2026-09-02) una serie propia con prefijo y reinicio anual, `P-2026-0001`,
 * separada de la serie fiscal de las facturas, y renumerar los ya cursados.
 *
 * El patrón nuevo va en config/sync (`order_default`, plugin `yearly`). Lo que
 * NO viaja en config es el contenido: los números de los pedidos existentes y
 * la fila de `commerce_number_pattern_sequence` que dice por dónde va la
 * serie. De eso se ocupa este script, que hay que ejecutar también en
 * producción (donde puede haber entrado algún pedido más desde el volcado).
 *
 * Reglas:
 * - Solo se tocan los pedidos cuyo número es puramente numérico (el formato
 *   viejo). Relanzarlo no cambia nada.
 * - El orden es el de `placed`, y el año del número es el del `placed`, así
 *   que un pedido de diciembre y otro de enero caen en series distintas.
 * - El contador de cada año arranca detrás del mayor `P-AÑO-NNNN` que ya
 *   exista, por si alguno se hubiera renumerado a mano.
 * - Al final la fila de secuencia del año en curso queda en el último número
 *   asignado: sin eso el siguiente pedido repetiría número o volvería al 1.
 *
 * Copia previa: snapshot `pre-numeracion-pedidos`.
 */

declare(strict_types=1);

use Drupal\commerce_order\Entity\OrderInterface;

$patron_id = 'order_default';
$prefijo = 'P';
$relleno = 4;

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_order');
$conexion = \Drupal::database();
$ahora = \Drupal::time()->getRequestTime();

// Pedidos con número: los carritos (draft) no tienen y no se tocan.
$ids = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->exists('order_number')
  ->sort('placed')
  ->sort('order_id')
  ->execute();

$pedidos = $almacen->loadMultiple($ids);

// Por dónde va ya cada año en el formato nuevo.
$ultimo_por_anyo = [];
$patron_nuevo = sprintf('/^%s-(\d{4})-(\d+)$/', preg_quote($prefijo, '/'));
foreach ($pedidos as $pedido) {
  if (preg_match($patron_nuevo, (string) $pedido->getOrderNumber(), $m)) {
    $ultimo_por_anyo[$m[1]] = max($ultimo_por_anyo[$m[1]] ?? 0, (int) $m[2]);
  }
}

$renumerados = 0;
$tienda = NULL;
foreach ($pedidos as $pedido) {
  $numero = (string) $pedido->getOrderNumber();
  if (!ctype_digit($numero)) {
    continue;
  }
  $fecha = $pedido->getPlacedTime() ?: $pedido->getCreatedTime();
  $anyo = date('Y', $fecha);
  $siguiente = ($ultimo_por_anyo[$anyo] ?? 0) + 1;
  $nuevo = sprintf('%s-%s-%s', $prefijo, $anyo, str_pad((string) $siguiente, $relleno, '0', STR_PAD_LEFT));

  $repetido = $almacen->getQuery()->accessCheck(FALSE)
    ->condition('order_number', $nuevo)->count()->execute();
  if ($repetido) {
    printf("  pedido %d: «%s» ya existe, se para aquí para no duplicar\n", $pedido->id(), $nuevo);
    exit(1);
  }

  $pedido->setOrderNumber($nuevo);
  // Un pedido completado no se refresca, pero se deja explícito: aquí no hay
  // que recalcular nada, solo cambiar la etiqueta.
  $pedido->setRefreshState(OrderInterface::REFRESH_SKIP);
  $pedido->save();
  $ultimo_por_anyo[$anyo] = $siguiente;
  $tienda = $pedido->getStoreId();
  $renumerados++;
  printf("  pedido %d (%s): %s -> %s\n", $pedido->id(), date('Y-m-d', $fecha), $numero, $nuevo);
}

// La secuencia del año en curso tiene que decir el último número dado. Si el
// patrón es por tienda, la fila va con el store_id; si no, con 0.
$patron = \Drupal::entityTypeManager()->getStorage('commerce_number_pattern')->load($patron_id);
$configuracion = $patron?->getPlugin()->getConfiguration() ?? [];
$store_id = !empty($configuracion['per_store_sequence']) ? ($tienda ?? 1) : 0;
$anyo_actual = date('Y', $ahora);

if (isset($ultimo_por_anyo[$anyo_actual])) {
  $conexion->merge('commerce_number_pattern_sequence')
    ->keys(['entity_id' => $patron_id, 'store_id' => $store_id])
    ->fields(['number' => $ultimo_por_anyo[$anyo_actual], 'generated' => $ahora])
    ->execute();
  printf("  secuencia %s: %s va por el %d (el siguiente será %s-%s-%s)\n",
    $patron_id, $anyo_actual, $ultimo_por_anyo[$anyo_actual], $prefijo, $anyo_actual,
    str_pad((string) ($ultimo_por_anyo[$anyo_actual] + 1), $relleno, '0', STR_PAD_LEFT));
}
else {
  // Sin pedidos este año, la fila vieja (con el número del formato antiguo)
  // sobra: el plugin arranca en el número inicial cuando no hay fila.
  $conexion->delete('commerce_number_pattern_sequence')
    ->condition('entity_id', $patron_id)
    ->execute();
  printf("  secuencia %s: sin pedidos en %s, se reinicia\n", $patron_id, $anyo_actual);
}

printf("Listo: %d pedidos renumerados.\n", $renumerados);
