<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\Component;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Interfaz del correo de expedición.
 */
interface EnvioMailerInterface extends ComponentMailerInterface {

  /**
   * Avisa al cliente de que su pedido ha salido.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $envio
   *   El envío que acaba de recoger el transportista.
   *
   * @return bool
   *   Si se ha enviado.
   */
  public function avisar(ShipmentInterface $envio): bool;

}
