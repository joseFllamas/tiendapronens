<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\Component;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\Component\ComponentMailerBase;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;

/**
 * El aviso a la tienda de que ha entrado un pedido.
 *
 * Commerce solo escribe al cliente: manda el recibo y nada más, así que un
 * pedido podía entrar sin que en Pronens se enterara nadie hasta abrir el
 * backoffice. Se descartó resolverlo con `receiptBcc` del tipo de pedido, que
 * es lo que trae Commerce de fábrica, porque eso manda a la tienda una copia
 * del correo DEL CLIENTE: llega "¡Gracias por tu pedido!", sin el teléfono,
 * sin la pasarela y sin enlace a la ficha del pedido, y encima en el idioma en
 * el que compró el cliente. Este correo es una herramienta de trabajo, así que
 * lleva lo que hace falta para preparar el pedido.
 *
 * Va en el MISMO evento que el recibo (`place.post_transition`) y con la misma
 * prioridad, de modo que los dos salen a la vez.
 */
#[MailerInfo(
  base_tag: "pronens_pedido_admin",
  label: new TranslatableMarkup("Pronens: aviso de pedido a la tienda"),
  sub_defs: ["nuevo" => new TranslatableMarkup("Pedido nuevo")],
  required_config: ["email_subject", "email_body", "email_to"],
  variables: [
    'commerce_order' => new TranslatableMarkup("Order entity object"),
    'order_number' => new TranslatableMarkup("Order number"),
    'total' => new TranslatableMarkup("Order total"),
    'cliente' => new TranslatableMarkup("Customer name"),
    'cliente_correo' => new TranslatableMarkup("Customer email"),
    'telefono' => new TranslatableMarkup("Customer phone"),
    'pago' => new TranslatableMarkup("Payment gateway"),
    'idioma_cliente' => new TranslatableMarkup("Customer language"),
    'url_backoffice' => new TranslatableMarkup("Backoffice order URL"),
  ],
)]
class PedidoAdminMailer extends ComponentMailerBase implements PedidoAdminMailerInterface {

  /**
   * Constructor.
   *
   * El $baseTag se declara pero no se pasa desde el contenedor: lo inyecta
   * MailerPass por nombre al compilar, después de leer el atributo MailerInfo.
   * De ahí la cadena vacía en la definición del servicio.
   */
  public function __construct(
    MailerPlusInterface $mailer,
    MailerLookupInterface $mailerLookup,
    string $baseTag,
    protected readonly CurrencyFormatterInterface $currencyFormatter,
  ) {
    parent::__construct($mailer, $mailerLookup, $baseTag);
  }

  /**
   * {@inheritdoc}
   */
  public function avisar(OrderInterface $pedido): bool {
    // Sin setTo(): el destinatario sale de la política, para que el cliente
    // pueda cambiarlo o añadir a alguien más del taller sin tocar código. Y sin
    // langcode, así que este correo va SIEMPRE en el idioma del sitio: lo lee
    // Pronens, no quien ha comprado.
    return $this->newEmail('nuevo')
      ->setEntityParam($pedido)
      ->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $pedido = $email->getParam('commerce_order');
    $perfil = $pedido->getBillingProfile();
    $pasarela = $pedido->get('payment_gateway')->entity ?? NULL;
    $total = $pedido->getTotalPrice();

    $email->setEntityVariable('commerce_order')
      ->addLibrary('pronens/email-commerce')
      ->setVariable('order_number', $pedido->getOrderNumber())
      ->setVariable('total', $total === NULL ? '' : $this->importe($total))
      ->setVariable('cliente', $this->nombreDelCliente($pedido))
      ->setVariable('cliente_correo', (string) $pedido->getEmail())
      ->setVariable('telefono', $this->telefono($perfil))
      ->setVariable('pago', $pasarela !== NULL ? (string) $pasarela->label() : '')
      ->setVariable('idioma_cliente', (string) $pedido->getData('pronens_langcode', ''))
      ->setVariable('url_backoffice', Url::fromRoute('entity.commerce_order.canonical', [
        'commerce_order' => $pedido->id(),
      ], ['absolute' => TRUE])->toString());

    // Responder a este correo escribe al cliente, que es lo que se acaba
    // haciendo cuando hay que preguntar algo del bordado.
    if ($correo = $pedido->getEmail()) {
      $email->setReplyTo($correo);
    }
  }

  /**
   * Un importe de Commerce con el formato del sitio.
   */
  protected function importe(Price $precio): string {
    return $this->currencyFormatter->format($precio->getNumber(), $precio->getCurrencyCode());
  }

  /**
   * Nombre de quien compra, del perfil de facturación.
   */
  protected function nombreDelCliente(OrderInterface $pedido): string {
    $perfil = $pedido->getBillingProfile();
    if ($perfil !== NULL && $perfil->hasField('address') && !$perfil->get('address')->isEmpty()) {
      $direccion = $perfil->get('address')->first();
      $nombre = trim(($direccion->given_name ?? '') . ' ' . ($direccion->family_name ?? ''));
      if ($nombre !== '') {
        return $nombre;
      }
    }
    $cliente = $pedido->getCustomer();

    return $cliente->isAuthenticated() ? (string) $cliente->getDisplayName() : '';
  }

  /**
   * Teléfono del perfil de facturación, que es donde vive en esta tienda.
   */
  protected function telefono(mixed $perfil): string {
    if ($perfil === NULL || !$perfil->hasField('field_telefono') || $perfil->get('field_telefono')->isEmpty()) {
      return '';
    }

    return (string) $perfil->get('field_telefono')->value;
  }

}
