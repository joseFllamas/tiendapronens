<?php

namespace Drupal\pronens\Hook;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens\CamposTrait;
use Drupal\views\ViewExecutable;

/**
 * Hooks del carrito flyout.
 *
 * El panel lo pinta el bloque de carrito de Commerce con "dropdown" activado,
 * que es un lazy builder: los datos de sesión no rompen el Page Cache. Aquí se
 * añade lo que el bloque no trae: el progreso hacia el envío gratuito y la foto
 * de cada línea.
 */
class CarritoHooks {

  use CamposTrait;
  use StringTranslationTrait;

  /**
   * Id del método de envío que regala el porte a partir de cierto importe.
   *
   * El umbral no se escribe a mano: se lee de la condición del método, así que
   * si el cliente lo cambia en /admin/commerce/config/shipping-methods la barra
   * del flyout lo sigue.
   */
  protected const METODO_ENVIO_GRATIS = '7';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrencyFormatterInterface $currencyFormatter,
    protected CartProviderInterface $cartProvider,
  ) {
  }

  /**
   * Implements hook_preprocess_commerce_cart_block().
   *
   * @param array<string, mixed> $variables
   *   Variables del template del bloque de carrito.
   */
  #[Hook('preprocess_commerce_cart_block')]
  public function preprocessCommerceCartBlock(array &$variables): void {
    $metadatos = new CacheableMetadata();
    $total = $this->totalDeCarritos($metadatos);
    $umbral = $this->umbralEnvioGratis($metadatos);

    $carritos = $this->carritos();
    $variables['carrito'] = [
      'total_texto' => $this->currencyFormatter->format($total->getNumber(), $total->getCurrencyCode()),
      'envio' => $this->progresoEnvio($total, $umbral),
      // Con un solo carrito el CTA va directo al checkout; con varios (varias
      // tiendas) a /cart, que es donde se eligen.
      'checkout_url' => \count($carritos) === 1
        ? Url::fromRoute('commerce_checkout.form', ['commerce_order' => $carritos[0]->id()])->toString()
        : NULL,
    ];
    $render = $variables;
    $metadatos->applyTo($render);
    $variables['#cache'] = $render['#cache'] ?? [];
    $variables['#attached']['library'][] = 'pronens/carrito';
  }

  /**
   * Implements hook_preprocess_views_view_fields().
   *
   * Añade a cada línea del carrito la foto del producto y las opciones de la
   * variación, que la view no trae y el diseño sí pide.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de campos de la view.
   */
  #[Hook('preprocess_views_view_fields')]
  public function preprocessViewsViewFields(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable || $view->id() !== 'commerce_cart_block') {
      return;
    }
    $linea = $variables['row']->_relationship_entities['order_items'] ?? NULL;
    if (!$linea instanceof OrderItemInterface) {
      return;
    }

    $variacion = $linea->getPurchasedEntity();
    $producto = $variacion instanceof ProductVariationInterface ? $variacion->getProduct() : NULL;
    $variables['linea'] = [
      // El título de la línea de pedido lleva la variación pegada; el diseño
      // quiere el nombre del producto arriba y las opciones debajo.
      'nombre' => $producto?->label() ?? $linea->label(),
      'url' => $producto?->toUrl()->toString(),
      'foto' => $this->fotoDeLinea($variables, $linea),
      'opciones' => $this->opcionesDeLinea($linea),
      'bordado' => $this->bordadoDeLinea($linea),
      // El total de línea de Commerce no incluye los ajustes, así que sin esto
      // las líneas no suman el subtotal del pie.
      'recargo' => $this->recargoDeLinea($linea),
    ];
  }

  /**
   * Foto del producto de una línea, con la variación por delante.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<string, mixed>|null
   *   Render array de la imagen.
   */
  protected function fotoDeLinea(array &$variables, OrderItemInterface $linea): ?array {
    $variacion = $linea->getPurchasedEntity();
    if (!$variacion instanceof ProductVariationInterface) {
      return NULL;
    }
    // La variación puede traer sus propias fotos; si no, la del producto.
    $medias = $this->mediasFromFields($variacion, ['field_imagenes']);
    $producto = $variacion->getProduct();
    if ($medias === [] && $producto !== NULL) {
      $medias = $this->mediasFromFields($producto, ['field_imagen_principal', 'field_galeria']);
    }
    $media = reset($medias);
    if ($media === FALSE) {
      return NULL;
    }
    $render = $variables;
    CacheableMetadata::createFromRenderArray($render)
      ->addCacheableDependency($media)
      ->applyTo($render);
    $variables['#cache'] = $render['#cache'] ?? [];

    return $this->buildStyledImage($media, 'pronens_carrito');
  }

  /**
   * Opciones de la variación en texto, para el subtítulo de la línea.
   */
  protected function opcionesDeLinea(OrderItemInterface $linea): ?string {
    $variacion = $linea->getPurchasedEntity();
    if (!$variacion instanceof ProductVariationInterface) {
      return NULL;
    }
    $partes = [];
    foreach (array_keys($variacion->getAttributeValueIds()) as $campo) {
      $valor = $variacion->getAttributeValue($campo);
      if ($valor !== NULL) {
        $partes[] = (string) $valor->label();
      }
    }

    return $partes === [] ? NULL : implode(' · ', $partes);
  }

  /**
   * Texto del bordado de una línea, si lleva.
   */
  protected function bordadoDeLinea(OrderItemInterface $linea): ?string {
    if (!$linea->hasField('field_texto_bordado') || $linea->get('field_texto_bordado')->isEmpty()) {
      return NULL;
    }

    return (string) $linea->get('field_texto_bordado')->value;
  }

  /**
   * Recargo por bordado de una línea, ya formateado, o NULL.
   */
  protected function recargoDeLinea(OrderItemInterface $linea): ?string {
    $suma = NULL;
    foreach ($linea->getAdjustments(['fee']) as $ajuste) {
      $importe = $ajuste->getAmount();
      $suma = $suma === NULL ? $importe : $suma->add($importe);
    }

    return $suma === NULL
      ? NULL
      : $this->currencyFormatter->format($suma->getNumber(), $suma->getCurrencyCode());
  }

  /**
   * Suma de los totales de los carritos del usuario.
   *
   * Se usa el total y no el subtotal porque es lo que compara la condición del
   * método de envío gratuito, así que la barra y la regla real coinciden.
   */
  protected function totalDeCarritos(CacheableMetadata $metadatos): Price {
    $total = new Price('0', 'EUR');
    foreach ($this->carritos() as $carrito) {
      $metadatos->addCacheableDependency($carrito);
      $suma = $carrito->getTotalPrice();
      if ($suma !== NULL && $suma->getCurrencyCode() === $total->getCurrencyCode()) {
        $total = $total->add($suma);
      }
    }

    return $total;
  }

  /**
   * Carritos del usuario actual.
   *
   * @return array<int, \Drupal\commerce_order\Entity\OrderInterface>
   *   Los pedidos en estado carrito.
   */
  protected function carritos(): array {
    $carritos = [];
    foreach ($this->cartProvider->getCarts() as $carrito) {
      if ($carrito->hasItems()) {
        $carritos[] = $carrito;
      }
    }

    return $carritos;
  }

  /**
   * Importe a partir del cual el envío es gratis, o NULL si no hay regla.
   */
  protected function umbralEnvioGratis(CacheableMetadata $metadatos): ?Price {
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface|null $metodo */
    $metodo = $this->entityTypeManager->getStorage('commerce_shipping_method')
      ->load(self::METODO_ENVIO_GRATIS);
    if ($metodo === NULL) {
      return NULL;
    }
    $metadatos->addCacheableDependency($metodo);
    foreach ($metodo->getConditions() as $condicion) {
      if ($condicion->getPluginId() !== 'order_total_price') {
        continue;
      }
      $config = $condicion->getConfiguration();
      $importe = $config['amount'] ?? NULL;
      if (is_array($importe) && isset($importe['number'], $importe['currency_code'])) {
        return new Price((string) $importe['number'], (string) $importe['currency_code']);
      }
    }

    return NULL;
  }

  /**
   * Mensaje y porcentaje de la barra de envío gratuito.
   *
   * @return array<string, mixed>|null
   *   Datos de la barra, o NULL si no hay regla de envío gratuito.
   */
  protected function progresoEnvio(Price $total, ?Price $umbral): ?array {
    if ($umbral === NULL || (float) $umbral->getNumber() <= 0) {
      return NULL;
    }
    $conseguido = $total->greaterThanOrEqual($umbral);
    $falta = $umbral->subtract($total);
    $porcentaje = min(100, (int) round(((float) $total->getNumber() / (float) $umbral->getNumber()) * 100));

    return [
      'conseguido' => $conseguido,
      'porcentaje' => $porcentaje,
      'mensaje' => $conseguido
        ? $this->t('Free shipping unlocked!')
        : $this->t('@amount away from free shipping', [
          '@amount' => $this->currencyFormatter->format($falta->getNumber(), $falta->getCurrencyCode()),
        ]),
    ];
  }


  /**
   * Implements hook_form_views_form_commerce_cart_block_default_alter().
   *
   * El botón de quitar de Commerce es un input submit, y un input no admite
   * pseudoelementos, así que el icono ✕ del diseño tiene que ir en su valor.
   * El submit general de la view sobra: sin campo de cantidad no hay nada que
   * guardar, y quitar trae su propio submit.
   *
   * Commerce coloca los botones en $form[<id del campo>][<índice de fila>]
   * (RemoveButton::viewsForm()).
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  #[Hook('form_views_form_commerce_cart_block_default_alter')]
  public function formViewsFormCartBlockAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['actions'])) {
      $form['actions']['#access'] = FALSE;
    }
    if (!isset($form['remove_button']) || !is_array($form['remove_button'])) {
      return;
    }
    foreach (array_keys($form['remove_button']) as $fila) {
      if (!is_numeric($fila)) {
        continue;
      }
      $form['remove_button'][$fila]['#value'] = '✕';
      $form['remove_button'][$fila]['#attributes']['aria-label'] = (string) $this->t('Remove from basket');
      $form['remove_button'][$fila]['#attributes']['class'][] = 'pro-line__remove-btn';
    }
  }

}
