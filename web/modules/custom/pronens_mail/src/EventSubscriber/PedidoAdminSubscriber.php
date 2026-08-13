<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\pronens_mail\Component\PedidoAdminMailerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Avisa a la tienda de cada pedido nuevo.
 *
 * Mismo evento y misma prioridad que OrderReceiptSubscriber de Commerce, que
 * es quien manda el recibo al cliente: así los dos correos salen a la vez y no
 * hay una ventana en la que el cliente ya tiene su confirmación y en la tienda
 * todavía no se sabe nada.
 */
class PedidoAdminSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected PedidoAdminMailerInterface $pedidoAdminMailer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.post_transition' => ['avisar', -100],
    ];
  }

  /**
   * Manda el aviso de pedido nuevo.
   */
  public function avisar(WorkflowTransitionEvent $evento): void {
    $pedido = $evento->getEntity();
    if ($pedido instanceof OrderInterface) {
      $this->pedidoAdminMailer->avisar($pedido);
    }
  }

}
