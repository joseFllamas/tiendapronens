<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\Component;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Interfaz del aviso de pedido nuevo a la tienda.
 */
interface PedidoAdminMailerInterface extends ComponentMailerInterface {

  /**
   * Avisa a la tienda de que ha entrado un pedido.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $pedido
   *   El pedido recién confirmado.
   *
   * @return bool
   *   Si se ha enviado.
   */
  public function avisar(OrderInterface $pedido): bool;

}
