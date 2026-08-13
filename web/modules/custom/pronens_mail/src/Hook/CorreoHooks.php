<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pronens_mail\IdiomaPedido;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Hooks de correo de la tienda.
 *
 * Estos hooks NO pueden vivir en el tema: HooksEmailProcessor los invoca con
 * moduleHandler->invokeAll(), así que los temas no los reciben. El reparto es
 * ese: aquí lo que hay que decidir antes de renderizar (hoja de estilo e idioma
 * del destinatario) y en el tema las plantillas, el CSS y las variables de
 * presentación, que es donde se pueden editar sin tocar PHP.
 *
 * @see \Drupal\pronens\Hook\CorreoHooks
 */
class CorreoHooks {

  public function __construct(
    protected IdiomaPedido $idiomaPedido,
  ) {
  }

  /**
   * Implements hook_mailer_build().
   *
   * La hoja del envoltorio se adjunta a TODOS los correos, tengan override
   * propio o no: con mailer_override activo cualquier correo del sitio pasa por
   * aquí, así que uno nuevo de un contrib sale con la marca sin tocar nada.
   *
   * Tiene que ser en la fase build y no en init: Email::addLibrary() valida que
   * se esté en PHASE_BUILD y lanza una excepción en cualquier otra.
   */
  #[Hook('mailer_build')]
  public function build(EmailInterface $email): void {
    $email->addLibrary('pronens/email');
  }

  /**
   * Implements hook_mailer_commerce_order_build().
   */
  #[Hook('mailer_commerce_order_build')]
  public function buildPedido(EmailInterface $email): void {
    $email->addLibrary('pronens/email-commerce');
  }

  /**
   * Implements hook_mailer_commerce_order_init().
   */
  #[Hook('mailer_commerce_order_init')]
  public function initPedido(EmailInterface $email): void {
    $this->destinatarioDelPedido($email);
  }

  /**
   * Implements hook_mailer_pronens_envio_init().
   */
  #[Hook('mailer_pronens_envio_init')]
  public function initEnvio(EmailInterface $email): void {
    $this->destinatarioDelPedido($email);
  }

  /**
   * Pone como destinatario al cliente del pedido, en el idioma que le toca.
   *
   * MailerPlus::doSend() deduce el idioma SOLO de la dirección de destino, y
   * Address::create() devuelve langcode vacío para un correo suelto, con lo que
   * cae en el idioma por defecto del sitio. Aquí el checkout es de invitado
   * (allow_guest_checkout, con allow_registration desactivado), o sea que todos
   * los clientes nuevos son correos sueltos: sin esto, el 100% de los recibos
   * saldría en castellano, incluidos los de una compra hecha en /fr/.
   *
   * Va en la fase init porque es donde setTo() es válido.
   */
  protected function destinatarioDelPedido(EmailInterface $email): void {
    $pedido = $email->getParam('commerce_order');
    if (!$pedido instanceof OrderInterface) {
      return;
    }

    $langcode = $this->idiomaPedido->resolver($pedido);
    $cliente = $pedido->getCustomer();
    $email->setTo($cliente->isAuthenticated()
      ? new Address($cliente->getEmail(), (string) $cliente->getDisplayName(), $langcode, $cliente)
      : new Address((string) $pedido->getEmail(), '', $langcode));
  }

}
