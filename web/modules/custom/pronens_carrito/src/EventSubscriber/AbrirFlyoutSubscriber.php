<?php

namespace Drupal\pronens_carrito\EventSubscriber;

use Drupal\commerce_cart\Event\CartEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Marca la sesión cuando entra algo al carrito, para abrir el flyout.
 *
 * La marca la lee (y la borra) CarritoHooks::pageAttachments() en el render
 * siguiente, que puede ser esta misma petición (el add-to-cart no redirige) o
 * la vuelta del 302 del añadir directo del flyout. El evento cubre los dos
 * caminos porque ambos pasan por CartManager::addEntity().
 */
class AbrirFlyoutSubscriber implements EventSubscriberInterface {

  public const CLAVE = 'pronens_carrito_abrir';

  public function __construct(protected RequestStack $requestStack) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [CartEvents::CART_ENTITY_ADD => 'marcar'];
  }

  /**
   * Deja la marca en la sesión.
   */
  public function marcar(): void {
    $peticion = $this->requestStack->getCurrentRequest();
    if ($peticion !== NULL && $peticion->hasSession()) {
      $peticion->getSession()->set(self::CLAVE, TRUE);
    }
  }

}
