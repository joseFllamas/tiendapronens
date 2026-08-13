<?php

declare(strict_types=1);

namespace Drupal\pronens_mail\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\pronens_mail\IdiomaPedido;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Apunta en el pedido el idioma en el que se compró.
 *
 * `commerce_order` no es traducible y no tiene columna `langcode`, así que sin
 * esto no queda rastro de en qué idioma navegaba el cliente. Se guarda en la
 * columna `data`, que es serializada y no pide cambio de esquema.
 *
 * Se hace al confirmar el pedido y no antes porque es el único momento en que
 * el idioma de interfaz es con seguridad el del cliente: un reenvío del recibo
 * o un aviso de expedición salen del backoffice o del cron, y allí el idioma
 * de interfaz es el del administrador o el del sitio.
 */
class IdiomaPedidoSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.pre_transition' => 'apuntarIdioma',
    ];
  }

  /**
   * Guarda el idioma de la petición en el pedido.
   */
  public function apuntarIdioma(WorkflowTransitionEvent $evento): void {
    $pedido = $evento->getEntity();
    if (!$pedido instanceof OrderInterface || $pedido->getData(IdiomaPedido::CLAVE) !== NULL) {
      return;
    }

    // pre_transition y no post_transition: en la pre el pedido todavía se va a
    // guardar como parte de la transición, así que no hace falta un save() de
    // más ni se corre el riesgo de pisar otro cambio en curso.
    $pedido->setData(IdiomaPedido::CLAVE, $this->languageManager->getCurrentLanguage()->getId());
  }

}
