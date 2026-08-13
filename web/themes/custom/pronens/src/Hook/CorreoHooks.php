<?php

namespace Drupal\pronens\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\pronens\LineaPedidoTrait;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Maquetación de los correos.
 *
 * El envoltorio (cabecera con el wordmark, pie con el contacto y los enlaces
 * legales) es el mismo para todos los correos del sitio, y el recibo de pedido
 * pinta la línea de pedido con el MISMO trait que el flyout, la cesta y el
 * resumen del checkout: si el bordado o los extras cambian de forma, cambian en
 * los cuatro sitios a la vez.
 *
 * Los hooks de correo propiamente dichos (hook_mailer_*) no pueden estar aquí:
 * los invoca moduleHandler->invokeAll() y un tema no los recibe. Viven en
 * pronens_mail.
 *
 * @see \Drupal\pronens_mail\Hook\CorreoHooks
 */
class CorreoHooks {

  use LineaPedidoTrait;
  use StringTranslationTrait;

  /**
   * Bloque de marca y contacto del pie, editable en /admin/content/block.
   */
  protected const BLOQUE_PIE = 2;

  /**
   * Menús de los que salen los enlaces del pie del correo.
   *
   * Los de ayuda van delante del legal a propósito: quien abre un recibo busca
   * la política de devoluciones o las formas de pago mucho antes que el aviso
   * legal, que va al final porque tiene que estar.
   *
   * @var array<int, string>
   */
  protected const MENUS_PIE = ['footer-ayuda', 'footer'];

  /**
   * Lado de la miniatura de la línea de pedido en el correo, en píxeles.
   */
  protected const ANCHO_MINIATURA = 72;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_preprocess_email_wrap().
   *
   * @param array<string, mixed> $variables
   *   Variables del envoltorio del correo.
   */
  #[Hook('preprocess_email_wrap')]
  public function preprocessEmailWrap(array &$variables): void {
    // El idioma se pregunta al CORREO, no al gestor de idiomas. Dos motivos, y
    // el segundo se comprobó aquí: en una petición web el idioma de interfaz es
    // el de la página que dispara el envío (un pedido hecho en /es/ para un
    // cliente francés), y en el propio envío el idioma activo ya ha vuelto al
    // del sitio cuando se pinta el envoltorio, así que las URLs del pie salían
    // sin prefijo mientras el cuerpo sí estaba en francés.
    $idioma = $this->idiomaDelCorreo($variables['email'] ?? NULL);
    $variables['idioma'] = $idioma->getId();
    $variables['anyo'] = date('Y');
    $variables['url_inicio'] = Url::fromRoute('<front>', [], [
      'absolute' => TRUE,
      'language' => $idioma,
    ])->toString();
    $variables['enlaces_legales'] = $this->enlacesLegales($idioma);

    // El pie sale del bloque de contenido que el cliente ya edita para el
    // footer de la web: así el teléfono y el correo de contacto se cambian en
    // un solo sitio y no hay una copia escondida dentro de una plantilla.
    [$variables['pie'], $variables['pie_texto']] = $this->pieDeMarca($idioma);
  }

  /**
   * El idioma en el que se está escribiendo un correo.
   */
  protected function idiomaDelCorreo(?EmailInterface $correo): LanguageInterface {
    $langcode = $correo?->getLangcode() ?? '';

    return $this->languageManager->getLanguage($langcode)
      ?? $this->languageManager->getCurrentLanguage();
  }

  /**
   * Implements hook_preprocess_commerce_order().
   *
   * @param array<string, mixed> $variables
   *   Variables del pedido renderizado.
   */
  #[Hook('preprocess_commerce_order')]
  public function preprocessCommerceOrder(array &$variables): void {
    if (($variables['elements']['#view_mode'] ?? '') !== 'email') {
      return;
    }
    $pedido = $variables['order_entity'] ?? NULL;
    if (!$pedido instanceof OrderInterface) {
      return;
    }

    $metadatos = new CacheableMetadata();
    $metadatos->addCacheableDependency($pedido);

    $lineas = [];
    foreach ($pedido->getItems() as $linea) {
      $datos = $this->lineaDePedido($linea, $metadatos);
      $datos['foto'] = $this->fotoDeCorreo($datos['foto']);
      $lineas[] = $datos;
    }
    $variables['lineas'] = $lineas;
    $variables['numero'] = $pedido->getOrderNumber();
    $variables['fecha'] = $pedido->getPlacedTime() ?? $pedido->getCreatedTime();

    // Los mismos totales que el resumen del checkout, del mismo servicio de
    // Commerce: el cliente compara el correo con la pantalla en la que pagó y
    // los números tienen que ser idénticos, recargos incluidos.
    $totales = $this->totalSummary()->buildTotals($pedido);
    $variables['subtotal'] = isset($totales['subtotal']) ? $this->precio($totales['subtotal']) : NULL;
    $variables['ajustes'] = $this->ajustesDelPedido($totales);
    $total = $pedido->getTotalPrice();
    $variables['total'] = $total !== NULL ? $this->precio($total) : NULL;

    $variables['url_pedido'] = $pedido->getCustomer()->isAuthenticated()
      ? Url::fromRoute('entity.commerce_order.user_view', [
        'user' => $pedido->getCustomerId(),
        'commerce_order' => $pedido->id(),
      ], ['absolute' => TRUE])->toString()
      : NULL;

    // Las direcciones se pintan aquí y no con los campos del modo de vista por
    // dos motivos comprobados sobre el recibo real: el formateador
    // `commerce_shipping_information` devuelve la tarjeta del BACKOFFICE (con
    // su título "Información de envío", el método y el importe) y ni siquiera
    // incluye la dirección de entrega, que es justo lo que el cliente busca; y
    // `billing_profile` sale vacío, porque symfony_mailer_preprocess_commerce_
    // order() borra `billing_information` en el modo `email` como rodeo del
    // issue 2949726 de Commerce.
    $variables['facturacion'] = $this->direccion($pedido->getBillingProfile());
    $variables['envio'] = $this->datosDeEnvio($pedido);
  }

  /**
   * La miniatura de una línea, adaptada al correo.
   *
   * En la web basta con el CSS, pero en correo no: Outlook usa el motor de Word
   * y hace caso a los ATRIBUTOS width y height del <img> antes que a la hoja de
   * estilo, así que la miniatura de 148px del estilo `pronens_carrito` se vería
   * al doble de lo que pide el diseño. Se sirve el fichero de 148 (cuadrado,
   * image_scale_and_crop) y se declara a 72, que además la deja nítida en las
   * pantallas de densidad doble, donde está casi todo el correo que se abre.
   *
   * Van en `#attributes` y no en `#width`/`#height`: esos dos son las medidas
   * del ORIGINAL, que ImagePreprocess vuelve a pasar por el estilo de imagen, y
   * además los atributos ya definidos ganan (AttributeHelper::attributeExists).
   * Por lo mismo se fija `loading`, que si no lo pone core a `lazy`.
   *
   * @param array<string, mixed>|null $foto
   *   El render array de la miniatura.
   *
   * @return array<string, mixed>|null
   *   La misma miniatura con el tamaño del correo.
   */
  protected function fotoDeCorreo(?array $foto): ?array {
    if ($foto === NULL) {
      return NULL;
    }
    $foto['#attributes']['width'] = self::ANCHO_MINIATURA;
    $foto['#attributes']['height'] = self::ANCHO_MINIATURA;
    $foto['#attributes']['loading'] = 'eager';

    return $foto;
  }

  /**
   * La dirección de un perfil, sin etiqueta ni envoltorio de administración.
   *
   * @return array<string, mixed>|null
   *   Render array de la dirección, o NULL si no hay.
   */
  protected function direccion(?EntityInterface $perfil): ?array {
    if ($perfil === NULL || !$perfil->hasField('address') || $perfil->get('address')->isEmpty()) {
      return NULL;
    }

    return $this->entityTypeManager->getViewBuilder('profile')
      ->viewField($perfil->get('address'), [
        'type' => 'address_default',
        'label' => 'hidden',
      ]);
  }

  /**
   * Dirección de entrega, método y seguimiento del envío del pedido.
   *
   * @return array<string, mixed>|null
   *   Dirección, método y código de seguimiento, o NULL si no hay envío.
   */
  protected function datosDeEnvio(OrderInterface $pedido): ?array {
    if (!$pedido->hasField('shipments')) {
      return NULL;
    }
    $envios = $pedido->get('shipments')->referencedEntities();
    $envio = reset($envios);
    if ($envio === FALSE) {
      return NULL;
    }
    $metodo = $envio->getShippingMethod();

    return [
      'direccion' => $this->direccion($envio->getShippingProfile()),
      'metodo' => $metodo !== NULL ? $this->etiqueta($metodo) : NULL,
      'seguimiento' => $envio->getTrackingCode(),
    ];
  }

  /**
   * El servicio de totales de Commerce, resuelto al usarlo.
   *
   * Mismo motivo que en PrecioTrait: un tema no puede declarar servicios (el
   * kernel solo lee los *.services.yml de los módulos), así que la alternativa
   * sería inyectarlo por el contenedor de hooks y arriesgarse a la referencia
   * circular que ya apareció con el formateador de moneda.
   */
  protected function totalSummary(): OrderTotalSummaryInterface {
    // @phpstan-ignore-next-line
    return \Drupal::service('commerce_order.order_total_summary');
  }

  /**
   * Enlaces legales del pie, en el idioma del correo.
   *
   * @return array<int, array<string, string>>
   *   Lista de enlaces con título y URL absoluta.
   */
  protected function enlacesLegales(LanguageInterface $idioma): array {
    $enlaces = [];
    $almacen = $this->entityTypeManager->getStorage('menu_link_content');

    foreach (self::MENUS_PIE as $menu) {
      $ids = $almacen->getQuery()
        ->accessCheck(FALSE)
        ->condition('menu_name', $menu)
        ->condition('enabled', 1)
        ->sort('weight')
        ->execute();

      foreach ($almacen->loadMultiple($ids) as $enlace) {
        $traducido = $this->entityRepository->getTranslationFromContext($enlace, $idioma->getId());
        $enlaces[] = [
          'titulo' => (string) $traducido->label(),
          'url' => $traducido->getUrlObject()
            ->setAbsolute()
            ->setOption('language', $idioma)
            ->toString(),
        ];
      }
    }

    return $enlaces;
  }

  /**
   * Bloque de marca y contacto del pie, renderizado y en texto plano.
   *
   * @return array{0: array<string, mixed>|null, 1: string|null}
   *   El render array del cuerpo del bloque y su versión sin etiquetas.
   */
  protected function pieDeMarca(LanguageInterface $idioma): array {
    $bloque = $this->entityTypeManager->getStorage('block_content')->load(self::BLOQUE_PIE);
    if ($bloque === NULL || !$bloque->hasField('body')) {
      return [NULL, NULL];
    }
    $traducido = $this->entityRepository->getTranslationFromContext($bloque, $idioma->getId());
    $html = (string) ($traducido->get('body')->value ?? '');
    if ($html === '') {
      return [NULL, NULL];
    }

    $render = $this->entityTypeManager->getViewBuilder('block_content')
      ->viewField($traducido->get('body'), 'default');

    return [$render, trim(strip_tags(str_replace(['</p>', '<br>', '<br />'], "\n", $html)))];
  }

}
