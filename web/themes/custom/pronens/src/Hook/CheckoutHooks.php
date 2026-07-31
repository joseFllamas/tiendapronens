<?php

namespace Drupal\pronens\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\pronens\LineaPedidoTrait;

/**
 * Hooks del checkout de una sola pantalla.
 *
 * El flujo se reconfiguró para que todo el formulario quepa en el paso
 * order_information (login y review desactivados), así que aquí solo se viste:
 * dos columnas con el resumen del pedido pegado a la derecha, y las secciones
 * en el orden de Shopify (Contacto, Entrega, Método de envío, Pago).
 *
 * Cuidado con el AJAX: al cambiar la dirección o la tarifa, Commerce reemplaza
 * el formulario ENTERO (AjaxFormTrait::ajaxRefreshForm hace un ReplaceCommand
 * sobre el <form>), y commerce_shipping/shipping_checkout pulsa el botón de
 * recalcular en cuanto se completa un campo obligatorio de la dirección. Como
 * el layout de dos columnas vive dentro del formulario, se reconstruye varias
 * veces mientras el cliente teclea. Por eso el sticky es CSS puro y el plegado
 * móvil es un <details> nativo: sobreviven al reemplazo sin reengancharse, y
 * este componente no necesita JavaScript.
 */
class CheckoutHooks {

  use LineaPedidoTrait;
  use StringTranslationTrait;

  /**
   * Menú del que salen los enlaces legales del aviso bajo el CTA.
   */
  protected const MENU_LEGAL = 'footer';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
    protected MenuLinkTreeInterface $menuLinkTree,
  ) {
  }

  /**
   * Implements hook_preprocess_commerce_checkout_form().
   *
   * @param array<string, mixed> $variables
   *   Variables del template del formulario de checkout.
   */
  #[Hook('preprocess_commerce_checkout_form')]
  public function preprocessCommerceCheckoutForm(array &$variables): void {
    $paso = $variables['form']['#step_id'] ?? '';
    $variables['paso'] = $paso;
    // El h1 lo pinta esta plantilla, no el bloque de título: la ruta se llama
    // "Tramitar pedido" en los tres pasos y el cliente necesita saber si está
    // rellenando datos, esperando al banco o ya ha comprado.
    $variables['titulo'] = match ($paso) {
      'complete' => $this->t('Thank you for your order'),
      'payment' => $this->t('Connecting to the bank'),
      default => $this->t('Checkout'),
    };
    $variables['cart_url'] = Url::fromRoute('commerce_cart.page')->toString();
    if ($paso === 'order_information') {
      $variables['legal'] = $this->enlacesLegales($variables);
    }
    $variables['#attached']['library'][] = 'pronens/checkout';
  }

  /**
   * Implements hook_preprocess_commerce_checkout_pane().
   *
   * @param array<string, mixed> $variables
   *   Variables del template de un pane.
   */
  #[Hook('preprocess_commerce_checkout_pane')]
  public function preprocessCommerceCheckoutPane(array &$variables): void {
    $pane_id = $variables['elements']['#pane_id'] ?? '';
    $variables['pane_id'] = $pane_id;

    if ($pane_id === 'shipping_information') {
      // La plantilla parte el pane en "Entrega" y "Método de envío", y el
      // segundo encabezado no debe salir cuando todavía no hay dirección y por
      // tanto no hay tarifas. No se puede decidir en Twig con |render, porque
      // render() marca el elemento como impreso y al volver a imprimirlo
      // saldría vacío.
      $variables['hay_tarifas'] = isset($variables['elements']['shipments'])
        && Element::getVisibleChildren($variables['elements']['shipments']) !== [];
      return;
    }

    if ($pane_id !== 'contact_information') {
      return;
    }
    // Enlace "Iniciar sesión" dentro de la sección Contacto, como en la
    // referencia. Al volver del login el pedido sigue siendo accesible porque
    // CommerceCartHooks::userLogin() reasigna el carrito de la sesión anónima
    // conservando el mismo id: solo le cambia el uid.
    $pedido = $this->pedidoActual();
    $destino = $pedido !== NULL
      ? Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $pedido->id(),
        'step' => 'order_information',
      ])->toString()
      : NULL;
    $variables['login_url'] = Url::fromRoute('user.login', [], [
      'query' => $destino !== NULL ? ['destination' => $destino] : [],
    ])->toString();
  }

  /**
   * Implements hook_preprocess_commerce_checkout_order_summary().
   *
   * Monta el resumen con las mismas líneas que el flyout: foto, opciones de la
   * variación, texto del bordado y desglose de los ajustes. Se hace aquí y no
   * con la view commerce_checkout_order_summary porque los ajustes de tipo fee
   * son una propiedad computada de la línea y no existen como campo de Views:
   * desglosar "+5,00 € bordado" con la view exigiría un plugin de campo custom.
   *
   * @param array<string, mixed> $variables
   *   Variables del template del resumen.
   */
  #[Hook('preprocess_commerce_checkout_order_summary')]
  public function preprocessCommerceCheckoutOrderSummary(array &$variables): void {
    $pedido = $variables['order_entity'] ?? NULL;
    if (!$pedido instanceof OrderInterface) {
      return;
    }
    $metadatos = new CacheableMetadata();
    $metadatos->addCacheableDependency($pedido);

    $lineas = [];
    foreach ($pedido->getItems() as $linea) {
      $lineas[] = $this->lineaDePedido($linea, $metadatos);
    }
    $variables['lineas'] = $lineas;

    // Se usan el subtotal y los ajustes tal como los da Commerce, sin sumar los
    // recargos dentro de las líneas. Dos razones: es la misma presentación que
    // la página de la cesta (que pinta el área de totales de Commerce y no se
    // puede rehacer sin reimplementar su aritmética), y así el importe de cada
    // línea cuadra con el subtotal. El recargo del bordado no se esconde: sale
    // como detalle bajo la línea y como línea propia en los totales.
    $subtotal = $variables['totals']['subtotal'] ?? NULL;
    $variables['subtotal'] = $subtotal !== NULL ? $this->precio($subtotal) : NULL;
    $variables['ajustes'] = $this->ajustesDelPedido($variables['totals'] ?? []);

    $total = $pedido->getTotalPrice();
    $variables['total'] = $total !== NULL ? $this->precio($total) : NULL;

    // Barra de envío gratuito: mientras no se llegue al umbral es el mejor
    // argumento de la pantalla, y es coherente con lo que ya ve en el flyout.
    // Se compara contra el total, que es lo que compara la condición real.
    // En la pantalla de gracias no pinta nada: ya no puede añadir al pedido.
    $variables['envio'] = $total !== NULL && ($variables['checkout_step'] ?? '') !== 'complete'
      ? $this->progresoEnvio($total, $this->umbralEnvioGratis($metadatos))
      : NULL;

    $render = $variables;
    $metadatos->applyTo($render);
    $variables['#cache'] = $render['#cache'] ?? [];
  }

  /**
   * Implements hook_form_commerce_checkout_flow_multistep_default_alter().
   *
   * Se usa el hook de FORM_ID y no form_alter para no cruzarse con
   * FichaHooks::formAlter(), que ya implementa el genérico.
   *
   * @param array<string, mixed> $form
   *   El formulario de checkout.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  #[Hook('form_commerce_checkout_flow_multistep_default_alter')]
  public function formCheckoutAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attributes']['class'][] = 'pro-co__form';

    // Los grupos de radios de envío y de pago son elecciones excluyentes: sin
    // esto un lector de pantalla no anuncia cuántas opciones hay ni de qué.
    $this->marcarGrupoRadios($form, ['payment_information', 'payment_method'], $this->t('Payment method'));
    $this->marcarGrupoRadios($form, ['shipping_information', 'shipments', 0, 'shipping_method'], $this->t('Shipping method'));

    if (($form['#step_id'] ?? '') !== 'order_information') {
      return;
    }
    // "Pay and complete purchase" es largo y no dice el importe. El texto va
    // por traducción de interfaz, así que sirve en los cuatro idiomas.
    if (isset($form['actions']['next'])) {
      $form['actions']['next']['#value'] = $this->t('Pay now');
      $form['actions']['next']['#attributes']['class'][] = 'pro-co__submit';
    }
  }

  /**
   * Ajustes del pedido para el pie del resumen.
   *
   * @param array<string, mixed> $totales
   *   Los totales que monta OrderTotalSummary::buildTotals().
   *
   * @return array<int, array<string, mixed>>
   *   Etiqueta, importe y si es informativo (el IVA incluido no se suma).
   */
  protected function ajustesDelPedido(array $totales): array {
    $ajustes = [];
    foreach ($totales['adjustments'] ?? [] as $ajuste) {
      $ajustes[] = [
        'etiqueta' => $ajuste['label'] ?? '',
        'importe' => $this->precio($ajuste['total']),
        // El IVA de la tienda es incluido (display_inclusive), así que
        // buildTotals() lo deja en la lista por obligación legal pero no lo
        // suma. Enseñarlo como una línea más engañaría.
        'incluido' => ($ajuste['type'] ?? '') === 'tax' && !empty($ajuste['included']),
      ];
    }

    return $ajustes;
  }

  /**
   * Enlaces legales del aviso que va debajo del CTA.
   *
   * Al no haber paso de revisión, este aviso es la confirmación informada que
   * exige la venta a distancia, así que los enlaces tienen que llevar a las
   * condiciones y a la privacidad de verdad. No se escriben a mano: se leen del
   * menú del pie, que es donde el cliente ya mantiene sus páginas legales. Hoy
   * ese menú solo tiene "Aviso legal"; en cuanto añada "Condiciones de venta" y
   * "Política de privacidad" aparecen aquí sin tocar código.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan las dependencias de caché del menú).
   *
   * @return array<int, array<string, string>>
   *   Título y URL de cada página legal.
   */
  protected function enlacesLegales(array &$variables): array {
    $parametros = new MenuTreeParameters();
    $parametros->setMaxDepth(1)->onlyEnabledLinks();
    $arbol = $this->menuLinkTree->load(self::MENU_LEGAL, $parametros);
    $arbol = $this->menuLinkTree->transform($arbol, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $enlaces = [];
    foreach ($arbol as $elemento) {
      $enlaces[] = [
        'titulo' => (string) $elemento->link->getTitle(),
        'url' => $elemento->link->getUrlObject()->toString(),
      ];
    }
    $variables['#cache']['tags'][] = 'config:system.menu.' . self::MENU_LEGAL;
    $variables['#cache']['contexts'][] = 'user.permissions';

    return $enlaces;
  }

  /**
   * Marca un grupo de radios como radiogroup con su etiqueta.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   * @param array<int, int|string> $ruta
   *   Camino hasta el elemento de radios.
   * @param string $etiqueta
   *   Nombre del grupo para el lector de pantalla.
   */
  protected function marcarGrupoRadios(array &$form, array $ruta, string $etiqueta): void {
    $elemento = &$form;
    foreach ($ruta as $clave) {
      if (!isset($elemento[$clave]) || !is_array($elemento[$clave])) {
        return;
      }
      $elemento = &$elemento[$clave];
    }
    $elemento['#attributes']['role'] = 'radiogroup';
    $elemento['#attributes']['aria-label'] = (string) $etiqueta;
  }

  /**
   * El pedido que se está tramitando.
   */
  protected function pedidoActual(): ?OrderInterface {
    // Ni el formulario ni los panes llevan el pedido en su render array
    // (CheckoutFlowBase no pone ningún #order), pero la ruta sí: el checkout
    // siempre lleva el pedido en la URL.
    $pedido = $this->routeMatch->getParameter('commerce_order');

    return $pedido instanceof OrderInterface ? $pedido : NULL;
  }

}
