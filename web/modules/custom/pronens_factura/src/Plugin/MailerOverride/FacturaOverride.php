<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Plugin\MailerOverride;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;

/**
 * Intercepta la confirmación de factura de commerce_invoice.
 *
 * InvoiceConfirmationMail::send() llama al gestor de correo de Commerce con
 * `mail('commerce', 'invoice_confirmation', …)`. mailer_override busca un
 * plugin que declare `commerce.invoice_confirmation` y, si no lo hay, lo manda
 * como correo legacy con la plantilla del módulo. Este plugin lo reconduce al
 * FacturaMailer, cuyo id de base (`pronens_factura`) tiene que coincidir con el
 * id del plugin: OverrideBase::send() localiza el mailer por el id del plugin.
 */
#[Override(
  id: "pronens_factura",
  override: ["commerce.invoice_confirmation"],
  import: new TranslatableMarkup("Sin ajustes que importar: la política se crea con el módulo."),
)]
class FacturaOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $message
   *   El mensaje legacy de Commerce, con la factura en params['invoice'].
   */
  protected function fromArray(array $message): bool {
    $factura = $message['params']['invoice'] ?? NULL;
    if (!$factura instanceof InvoiceInterface) {
      return FALSE;
    }
    /** @var \Drupal\pronens_factura\Component\FacturaMailerInterface $mailer */
    $mailer = $this->mailer;

    return $mailer->enviar($factura, $message['to'] ?? NULL, $message['params']['bcc'] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {}

}
