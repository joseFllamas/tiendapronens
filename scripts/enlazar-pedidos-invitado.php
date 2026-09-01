<?php

/**
 * @file
 * Enlaza una vez los pedidos de invitado ya existentes con su cuenta.
 *
 * pronens_cuenta cubre todo lo que pase de aquí en adelante (al realizarse el
 * pedido y al crearse la cuenta), pero no lo que ya estaba en la base de datos
 * cuando se activó. Este script recorre los pedidos con uid 0 que no son
 * carritos y, si su correo tiene cuenta, se los asigna con el mismo servicio
 * (OrderAssignment), que además copia las direcciones a la libreta.
 *
 * Es idempotente: un pedido ya asignado deja de tener uid 0 y no vuelve a
 * entrar. Pensado para ejecutarse en producción tras el despliegue.
 *
 * Uso: ddev drush php:script scripts/enlazar-pedidos-invitado.php
 */

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\user\UserInterface;

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_order');
$usuarios = \Drupal::entityTypeManager()->getStorage('user');
$asignador = \Drupal::service('commerce_order.order_assignment');

$ids = $almacen->getQuery()
  ->accessCheck(FALSE)
  ->condition('uid', 0)
  ->condition('state', 'draft', '<>')
  ->sort('order_id')
  ->execute();

if ($ids === []) {
  print "No hay pedidos de invitado que enlazar.\n";
  return;
}

$asignados = 0;
$sin_cuenta = 0;
foreach ($almacen->loadMultiple($ids) as $pedido) {
  assert($pedido instanceof OrderInterface);
  $correo = (string) ($pedido->getEmail() ?? '');
  if ($correo === '') {
    $sin_cuenta++;
    continue;
  }
  $cuentas = $usuarios->loadByProperties(['mail' => $correo]);
  $cuenta = reset($cuentas);
  if (!$cuenta instanceof UserInterface) {
    $sin_cuenta++;
    continue;
  }
  $asignador->assign($pedido, $cuenta);
  print "Pedido {$pedido->id()} ({$correo}) → cuenta {$cuenta->id()} ({$cuenta->getAccountName()}).\n";
  $asignados++;
}

print "Asignados: {$asignados}. Sin cuenta con ese correo: {$sin_cuenta}.\n";
