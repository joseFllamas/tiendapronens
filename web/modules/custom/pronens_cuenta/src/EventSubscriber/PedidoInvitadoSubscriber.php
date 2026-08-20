<?php

declare(strict_types=1);

namespace Drupal\pronens_cuenta\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\pronens_cuenta\EnlazadorDePedidos;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Al realizarse un pedido de invitado, lo asigna a la cuenta de su correo.
 *
 * Escucha el PRE_transition de place a propósito: ahí el pedido todavía no se
 * ha guardado, así que basta con ponerle el cliente y el save de la propia
 * transición lo persiste, sin un save anidado. Además el AddressBookSubscriber
 * de Commerce (post_transition, prioridad 100) y el recibo (-100) ya ven el
 * pedido asignado: la libreta se rellena y el correo sale con su enlace
 * "Ver pedido".
 */
final class PedidoInvitadoSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EnlazadorDePedidos $enlazador,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.pre_transition' => 'alRealizarsePedido',
    ];
  }

  /**
   * Asigna el pedido si su correo ya tiene cuenta.
   */
  public function alRealizarsePedido(WorkflowTransitionEvent $evento): void {
    $pedido = $evento->getEntity();
    if ($pedido instanceof OrderInterface) {
      $this->enlazador->alRealizarsePedido($pedido);
    }
  }

}
