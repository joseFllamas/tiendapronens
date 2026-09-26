<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\EventSubscriber;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\pronens_mail\Component\EnvioMailerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Avisa al cliente cuando el transportista recoge su pedido.
 */
class EnvioSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EnvioMailerInterface $envioMailer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_shipment.ship.post_transition' => 'avisar',
    ];
  }

  /**
   * Manda el aviso de expedición.
   *
   * Es post_transition y no pre: el correo dice que el pedido ya está en
   * camino, así que solo se manda si la transición ha cuajado de verdad.
   *
   * Y no se manda en una recogida en tienda: el botón «Recogido» de la lista de
   * pedidos aplica esta misma transición cuando el cliente se lleva el paquete
   * en mano, y antes deja la marca `pronens_recogido` en el envío
   * (GestorExpediciones::CLAVE_RECOGIDO, con el literal para no depender de
   * pronens_correos_express).
   */
  public function avisar(WorkflowTransitionEvent $evento): void {
    $envio = $evento->getEntity();
    if ($envio instanceof ShipmentInterface && $envio->getData('pronens_recogido') === NULL) {
      $this->envioMailer->avisar($envio);
    }
  }

}
