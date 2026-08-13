<?php

namespace Drupal\pronens\Hook;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\pronens\LineaPedidoTrait;
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

  use LineaPedidoTrait;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CartProviderInterface $cartProvider,
    protected EntityRepositoryInterface $entityRepository,
    protected RequestStack $requestStack,
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
      'total_texto' => $this->precio($total),
      'envio' => $this->progresoEnvio($total, $umbral),
      // Con un solo carrito el CTA va directo al checkout; con varios (varias
      // tiendas) a /cart, que es donde se eligen.
      'checkout_url' => \count($carritos) === 1
        ? Url::fromRoute('commerce_checkout.form', ['commerce_order' => $carritos[0]->id()])->toString()
        : NULL,
      'completa' => $this->completaElConjunto($carritos, $metadatos),
    ];

    // Señal de "abre el panel": la dejó pronens_carrito en la sesión al entrar
    // algo al carrito y es de un solo uso. Va AQUÍ y no en page_attachments
    // porque los attachments de página se guardan en la Dynamic Page Cache: en
    // una ficha ya visitada el hook de página ni corre y la señal se perdía.
    // Este bloque es un placeholder de BigPipe que se rehace en cada petición,
    // así que la señal llega también con la página cacheada. Consumirla vacía
    // la marca; max-age 0 evita que el render "abierto" se quede en la caché
    // de render y reabra el panel en cada página.
    $sesion = $this->requestStack->getCurrentRequest()?->getSession();
    if ($sesion !== NULL && $sesion->get('pronens_carrito_abrir')) {
      $sesion->remove('pronens_carrito_abrir');
      $variables['#attached']['drupalSettings']['pronensCarrito']['abrir'] = TRUE;
      $metadatos->setCacheMaxAge(0);
    }

    $render = $variables;
    $metadatos->applyTo($render);
    $variables['#cache'] = $render['#cache'] ?? [];
    $variables['#attached']['library'][] = 'pronens/carrito';
  }

  /**
   * Implements hook_preprocess_views_view_fields().
   *
   * Añade a cada línea la foto del producto, las opciones de la variación y el
   * bordado, que las views no traen y el diseño sí pide. Vale para el flyout y
   * para la página de la cesta: las dos pintan la misma línea de pedido, y la
   * monta el mismo trait que el resumen del checkout.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de campos de la view.
   */
  #[Hook('preprocess_views_view_fields')]
  public function preprocessViewsViewFields(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable
      || !in_array($view->id(), ['commerce_cart_block', 'commerce_cart_form'], TRUE)) {
      return;
    }
    $linea = $variables['row']->_relationship_entities['order_items'] ?? NULL;
    if (!$linea instanceof OrderItemInterface) {
      return;
    }

    $metadatos = new CacheableMetadata();
    $variables['linea'] = $this->lineaDePedido($linea, $metadatos);
    $render = $variables;
    $metadatos->applyTo($render);
    $variables['#cache'] = $render['#cache'] ?? [];
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
   * Sugerencias "Completa el conjunto" para el flyout.
   *
   * Los complementarios (field_complementarios) de lo que ya hay en el
   * carrito, quitando lo que ya está dentro y ordenados por cuántas líneas
   * los piden. Dos como mucho: el panel es estrecho y la sugerencia es un
   * empujón, no un catálogo.
   *
   * Cada sugerencia lleva o un enlace de añadir directo (producto de UNA
   * variación, ruta pronens_carrito.anadir con su token CSRF) o el enlace a
   * la ficha cuando hay talla o medida que elegir.
   *
   * @param array<int, \Drupal\commerce_order\Entity\OrderInterface> $carritos
   *   Carritos con líneas del usuario actual.
   *
   * @return array<int, array<string, mixed>>
   *   Sugerencias listas para el template.
   */
  protected function completaElConjunto(array $carritos, CacheableMetadata $metadatos): array {
    $en_carrito = [];
    $votos = [];
    foreach ($carritos as $carrito) {
      foreach ($carrito->getItems() as $linea) {
        $variacion = $linea->getPurchasedEntity();
        $producto = $variacion instanceof ProductVariationInterface ? $variacion->getProduct() : NULL;
        if ($producto === NULL) {
          continue;
        }
        $en_carrito[(int) $producto->id()] = TRUE;
        if (!$producto->hasField('field_complementarios')) {
          continue;
        }
        $lista = $producto->get('field_complementarios');
        if (!$lista instanceof EntityReferenceFieldItemListInterface) {
          continue;
        }
        // El orden del campo es la curación: el primero pesa más.
        $peso = 100;
        foreach ($lista->referencedEntities() as $complementario) {
          if ($complementario instanceof ProductInterface && $complementario->isPublished()) {
            $votos[(int) $complementario->id()] = ($votos[(int) $complementario->id()] ?? 0) + $peso;
            $peso--;
          }
        }
      }
    }
    $votos = array_diff_key($votos, $en_carrito);
    if ($votos === []) {
      return [];
    }
    arsort($votos);

    $sugerencias = [];
    $almacen = $this->entityTypeManager->getStorage('commerce_product');
    foreach (array_keys(array_slice($votos, 0, 2, TRUE)) as $pid) {
      $producto = $almacen->load($pid);
      if (!$producto instanceof ProductInterface) {
        continue;
      }
      $metadatos->addCacheableDependency($producto);
      $variaciones = array_values(array_filter(
        $producto->getVariations(),
        static fn($v): bool => $v->isPublished()
      ));
      if ($variaciones === []) {
        continue;
      }
      $precio = $variaciones[0]->getPrice();
      $media = $this->mediaFromField($producto, 'field_imagen_principal');
      $unica = \count($variaciones) === 1;
      $sugerencias[] = [
        'nombre' => $this->etiqueta($producto),
        'url' => $this->traducido($producto)->toUrl()->toString(),
        'foto' => $media !== NULL ? $this->buildStyledImage($media, self::ESTILO_MINIATURA) : NULL,
        'precio' => $precio !== NULL ? $this->precio($precio) : NULL,
        // Con varias variaciones el precio de la primera es orientativo.
        'desde' => !$unica,
        // Añadir directo solo si no hay nada que elegir; el enlace vuelve a la
        // página actual por el referer, sin destination, para no fragmentar la
        // caché del lazy builder por página.
        'anadir_url' => $unica
          ? Url::fromRoute('pronens_carrito.anadir', ['commerce_product' => $pid])->toString()
          : NULL,
      ];
    }

    return $sugerencias;
  }

  /**
   * Monta la página de la cesta: CSS y barra de envío gratuito.
   *
   * Lo llama CatalogoHooks::preprocessViewsView() porque un tema solo puede
   * implementar cada preprocess UNA vez: con dos, ThemeManager lanza
   * "Theme pronens should not implement preprocess_views_view more than once".
   * Es el mismo reparto que PronensHooks hace con FichaHooks.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de la view.
   */
  public function buildCesta(array &$variables): void {
    $metadatos = new CacheableMetadata();
    $total = $this->totalDeCarritos($metadatos);
    $variables['cesta'] = [
      'envio' => $this->progresoEnvio($total, $this->umbralEnvioGratis($metadatos)),
    ];
    $render = $variables;
    $metadatos->applyTo($render);
    $variables['#cache'] = $render['#cache'] ?? [];
    $variables['#attached']['library'][] = 'pronens/cesta';
  }

  /**
   * Implements hook_form_views_form_commerce_cart_form_default_alter().
   *
   * El botón de actualizar la cesta y el de tramitar el pedido son los dos
   * submits del formulario de la view. Aquí solo se les ponen clases: el de
   * tramitar es el CTA y el de actualizar es secundario, porque con el campo de
   * cantidad visible el cliente puede pulsar cualquiera de los dos.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  #[Hook('form_views_form_commerce_cart_form_default_alter')]
  public function formViewsFormCartPageAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#attributes']['class'][] = 'pro-cart-page__update';
    }
    if (isset($form['actions']['checkout'])) {
      $form['actions']['checkout']['#attributes']['class'][] = 'pro-btn';
      $form['actions']['checkout']['#attributes']['class'][] = 'pro-btn--cta';
      $form['actions']['checkout']['#attributes']['class'][] = 'pro-cart-page__checkout';
    }
    foreach (array_keys($form['remove_button'] ?? []) as $fila) {
      if (!is_numeric($fila)) {
        continue;
      }
      $form['remove_button'][$fila]['#value'] = (string) $this->t('Remove');
      $form['remove_button'][$fila]['#attributes']['class'][] = 'pro-cline__remove-btn';
    }
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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
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
