<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Component;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\InvoiceFileManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\pronens_mail\IdiomaPedido;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\Attachment;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\Component\ComponentMailerBase;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;

/**
 * El correo "tu factura" con el PDF adjunto.
 *
 * El módulo commerce_invoice manda su confirmación por el gestor de correo de
 * Commerce (`commerce.mail_handler`, id `invoice_confirmation`), con asunto
 * "Invoice #N" y una plantilla propia de otra marca. Aquí eso se sustituye por
 * un correo de Mailer Plus con política editable (asunto y cuerpo en
 * /admin/config/system/mailer/policy), el envoltorio de la tienda y el mismo
 * PDF adjunto. La conexión la hace FacturaOverride, que intercepta
 * `commerce.invoice_confirmation` y llama a enviar().
 *
 * El idioma es el del pedido, como en el recibo: el checkout es de invitado y
 * un correo suelto no lleva idioma, así que sin esto la factura de una compra
 * hecha en /fr/ saldría con el asunto en castellano.
 */
#[MailerInfo(
  base_tag: "pronens_factura",
  label: new TranslatableMarkup("Pronens: factura"),
  sub_defs: ["confirmacion" => new TranslatableMarkup("Factura emitida")],
  required_config: ["email_subject", "email_body"],
  variables: [
    'commerce_invoice' => new TranslatableMarkup("Invoice entity object"),
    'invoice_number' => new TranslatableMarkup("Invoice number"),
    'order_number' => new TranslatableMarkup("Order number"),
    'total' => new TranslatableMarkup("Invoice total"),
    'fecha' => new TranslatableMarkup("Invoice date"),
    'url_descarga' => new TranslatableMarkup("Download URL (only for customers with an account)"),
    'url_pedidos' => new TranslatableMarkup("My orders URL (only for customers with an account)"),
  ],
)]
class FacturaMailer extends ComponentMailerBase implements FacturaMailerInterface {

  /**
   * Constructor.
   *
   * El $baseTag se declara pero no se pasa desde el contenedor: lo inyecta
   * MailerPass por nombre al compilar, después de leer el atributo MailerInfo.
   */
  public function __construct(
    MailerPlusInterface $mailer,
    MailerLookupInterface $mailerLookup,
    string $baseTag,
    protected readonly IdiomaPedido $idiomaPedido,
    protected readonly InvoiceFileManagerInterface $ficheros,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly CurrencyFormatterInterface $currencyFormatter,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag);
  }

  /**
   * {@inheritdoc}
   */
  public function enviar(InvoiceInterface $factura, ?string $para = NULL, ?string $bcc = NULL): bool {
    $para = $para ?: (string) $factura->getEmail();
    if ($para === '') {
      return FALSE;
    }
    $pedido = $this->pedido($factura);
    $langcode = $pedido !== NULL
      ? $this->idiomaPedido->resolver($pedido)
      : $this->languageManager->getDefaultLanguage()->getId();
    $cliente = $factura->getCustomer();

    $correo = $this->newEmail('confirmacion')
      ->setEntityParam($factura)
      ->setTo($cliente->isAuthenticated() && $cliente->getEmail() === $para
        ? new Address($para, (string) $cliente->getDisplayName(), $langcode, $cliente)
        : new Address($para, '', $langcode));
    if ($bcc) {
      $correo->setBcc($bcc);
    }

    return $correo->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $factura */
    $factura = $email->getParam('commerce_invoice');
    $pedido = $this->pedido($factura);
    $total = $factura->getTotalPrice();
    $cliente = $factura->getCustomer();

    $email->addLibrary('pronens/email-commerce')
      ->setVariable('invoice_number', (string) $factura->getInvoiceNumber())
      ->setVariable('order_number', $pedido?->getOrderNumber() ?? '')
      ->setVariable('total', $total === NULL ? '' : $this->currencyFormatter->format($total->getNumber(), $total->getCurrencyCode()))
      ->setVariable('fecha', $this->dateFormatter->format($factura->getInvoiceDateTime(), 'custom', 'j/n/Y'))
      ->setVariable('url_descarga', '')
      ->setVariable('url_pedidos', '');

    // Los enlaces solo tienen sentido para quien tiene cuenta: la descarga
    // pide ser el dueño de la factura y un invitado no puede identificarse.
    if ($cliente->isAuthenticated()) {
      $email->setVariable('url_descarga', Url::fromRoute('entity.commerce_invoice.download', [
        'commerce_invoice' => $factura->id(),
      ], ['absolute' => TRUE])->toString());
      $email->setVariable('url_pedidos', Url::fromRoute('view.commerce_user_orders.order_page', [
        'user' => $cliente->id(),
      ], ['absolute' => TRUE])->toString());
    }

    $tienda = $factura->getStore();
    if ($tienda !== NULL && ($remitente = $tienda->get('mail')->value)) {
      $email->setFrom($remitente);
    }

    // El PDF: el mismo fichero que se descarga desde la cuenta. El gestor lo
    // genera si aún no existe y lo guarda en la factura.
    $fichero = $this->ficheros->getInvoiceFile($factura);
    if ($fichero !== NULL) {
      $ruta = $this->fileSystem->realpath((string) $fichero->getFileUri());
      if ($ruta) {
        $email->attach(Attachment::fromPath($ruta, $fichero->getFilename(), $fichero->getMimeType())
          ->setAccess(AccessResult::allowed()));
      }
    }
  }

  /**
   * El pedido de la factura (aquí siempre es uno por factura).
   */
  protected function pedido(InvoiceInterface $factura): ?OrderInterface {
    $pedidos = $factura->getOrders();

    return $pedidos === [] ? NULL : reset($pedidos);
  }

}
