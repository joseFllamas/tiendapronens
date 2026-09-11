<?php

declare(strict_types=1);

namespace Drupal\pronens_referral\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\pronens_referral\AtribuidorDePedidos;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Los dos momentos en los que se anota de dónde viene el pedido.
 *
 * @see \Drupal\pronens_referral\AtribuidorDePedidos
 */
final class AtribucionSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AtribuidorDePedidos $atribuidor,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CartEvents::CART_ENTITY_ADD => 'alAnadirAlCarrito',
      'commerce_order.place.pre_transition' => 'alColocarse',
    ];
  }

  /**
   * Copia la cookie al carrito en cuanto hay carrito.
   */
  public function alAnadirAlCarrito(CartEntityAddEvent $evento): void {
    $this->atribuidor->capturarEnCarrito($evento->getCart());
  }

  /**
   * Consolida la procedencia y el método al colocarse el pedido.
   */
  public function alColocarse(WorkflowTransitionEvent $evento): void {
    $pedido = $evento->getEntity();
    if ($pedido instanceof OrderInterface) {
      $this->atribuidor->consolidar($pedido);
    }
  }

}
