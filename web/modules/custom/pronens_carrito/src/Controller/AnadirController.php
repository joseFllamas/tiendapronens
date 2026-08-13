<?php

namespace Drupal\pronens_carrito\Controller;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Añade al carrito la única variación de un producto, sin pasar por la ficha.
 *
 * Es el destino del botón "+ Añadir" de las sugerencias del flyout ("Completa
 * el conjunto"). Solo funciona con productos de UNA variación: si hay talla o
 * medida que elegir, el que llama debe enlazar a la ficha en su lugar, y si
 * aun así alguien llega aquí, se le redirige a la ficha para que elija.
 *
 * No toca la personalización: la línea entra lisa, igual que si en la ficha no
 * se marcara el bordado. Quien quiera bordado puede editarla desde la cesta.
 */
class AnadirController extends ControllerBase {

  public function __construct(
    protected CartProviderInterface $cartProvider,
    protected CartManagerInterface $cartManager,
    protected CurrentStoreInterface $currentStore,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_store.current_store'),
    );
  }

  /**
   * Añade la única variación del producto y vuelve a donde estaba el cliente.
   */
  public function anadir(ProductInterface $commerce_product, Request $request): RedirectResponse {
    $volver = $this->volver($request);

    $variaciones = $commerce_product->getVariations();
    $activas = array_values(array_filter(
      $variaciones,
      static fn($variacion): bool => $variacion->isPublished()
    ));
    if (!$commerce_product->isPublished() || count($activas) !== 1) {
      // Hay algo que elegir (o nada que comprar): a la ficha.
      return new RedirectResponse($commerce_product->toUrl()->toString());
    }

    $tienda = $this->currentStore->getStore();
    if ($tienda === NULL) {
      return $volver;
    }
    $carrito = $this->cartProvider->getCart('default', $tienda)
      ?? $this->cartProvider->createCart('default', $tienda);
    // El aviso lo pone el subscriber de Commerce al saltar CART_ENTITY_ADD (y
    // con JS ni se ve: el flyout se abre solo enseñando el producto dentro).
    $this->cartManager->addEntity($carrito, $activas[0]);

    return $volver;
  }

  /**
   * A donde volver tras añadir: destination, el referer, o la ficha.
   */
  protected function volver(Request $request): RedirectResponse {
    $destino = $request->query->get('destination');
    if (is_string($destino) && str_starts_with($destino, '/') && !str_starts_with($destino, '//')) {
      return new RedirectResponse($destino);
    }
    $referer = $request->headers->get('referer');
    $base = $request->getSchemeAndHttpHost();
    if (is_string($referer) && str_starts_with($referer, $base . '/')) {
      return new RedirectResponse($referer);
    }

    return new RedirectResponse(Url::fromRoute('commerce_cart.page')->toString());
  }

}
