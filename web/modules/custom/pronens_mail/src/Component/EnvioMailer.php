<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\Component;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\Component\ComponentMailerBase;
use Drupal\symfony_mailer\EmailInterface;

/**
 * El correo de "tu pedido ha salido".
 *
 * No existía: la pantalla de gracias del checkout promete por escrito que
 * avisaremos con el número de seguimiento, y no lo mandaba nadie, aunque
 * pronens_correos_express ya tenía el dato desde el alta de la expedición.
 *
 * Se dispara con la transición `ship` del envío y no al dar de alta la
 * expedición: GestorExpediciones::generar() deja el envío en `ready` porque la
 * mercancía sigue en el almacén, y es la sincronización del seguimiento la que
 * aplica `ship` cuando el transportista la recoge de verdad. Avisar antes sería
 * mentir sobre dónde está el paquete.
 */
#[MailerInfo(
  base_tag: "pronens_envio",
  label: new TranslatableMarkup("Pronens: expedición"),
  sub_defs: ["aviso" => new TranslatableMarkup("Pedido enviado")],
  required_config: ["email_subject", "email_body"],
  variables: [
    'commerce_order' => new TranslatableMarkup("Order entity object"),
    'order_number' => new TranslatableMarkup("Order number"),
    'seguimiento' => new TranslatableMarkup("Tracking code"),
    'url_seguimiento' => new TranslatableMarkup("Tracking URL"),
  ],
)]
class EnvioMailer extends ComponentMailerBase implements EnvioMailerInterface {

  /**
   * Página pública de seguimiento de Correos Express.
   */
  protected const URL_SEGUIMIENTO = 'https://s.correosexpress.com/c?n=';

  /**
   * {@inheritdoc}
   */
  public function avisar(ShipmentInterface $envio): bool {
    $pedido = $envio->getOrder();
    if ($pedido === NULL) {
      return FALSE;
    }

    // El destinatario definitivo lo fija CorreoHooks en la fase init, con el
    // idioma del pedido: este aviso sale del cron, donde el idioma de interfaz
    // no dice nada del cliente.
    return $this->newEmail('aviso')
      ->setEntityParam($pedido)
      ->setParam('commerce_shipment', $envio)
      ->setTo((string) $pedido->getEmail())
      ->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $pedido = $email->getParam('commerce_order');
    $envio = $email->getParam('commerce_shipment');
    $codigo = (string) ($envio->getTrackingCode() ?? '');

    $email->setEntityVariable('commerce_order')
      ->addLibrary('pronens/email-commerce')
      ->setVariable('order_number', $pedido->getOrderNumber())
      ->setVariable('seguimiento', $codigo)
      ->setVariable('url_seguimiento', $codigo === '' ? '' : self::URL_SEGUIMIENTO . $codigo);

    $tienda = $pedido->getStore();
    if ($tienda !== NULL && ($correo = $tienda->get('mail')->value)) {
      $email->setFrom($correo);
    }
  }

}
