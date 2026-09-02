<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Component;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * El correo con la factura en PDF.
 */
interface FacturaMailerInterface extends ComponentMailerInterface {

  /**
   * Manda la factura al cliente, con el PDF adjunto.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $factura
   *   La factura.
   * @param string|null $para
   *   Destinatario; si no se pasa, el correo de la factura.
   * @param string|null $bcc
   *   Copia oculta, si la política del tipo de factura la pide.
   */
  public function enviar(InvoiceInterface $factura, ?string $para = NULL, ?string $bcc = NULL): bool;

}
