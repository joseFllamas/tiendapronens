<?php

namespace Drupal\pronens\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\pronens\LineaPedidoTrait;
use Drupal\user\UserInterface;

/**
 * El área del cliente: login, "Mis pedidos", seguimiento y direcciones.
 *
 * Todo lo que ve un cliente con cuenta (los 1578 migrados del D7) cuelga de
 * aquí: la pantalla de acceso, el panel con la lista de pedidos, la ficha de un
 * pedido con la línea de tiempo del envío y la cáscara común (navegación
 * lateral) que comparten esas pantallas con las direcciones y los datos de la
 * cuenta.
 *
 * Las líneas de pedido se pintan con el MISMO trait que el flyout, la cesta,
 * el checkout y el recibo por correo: el cliente compara la ficha del pedido
 * con el correo que recibió y los números y el bordado tienen que coincidir.
 *
 * Ojo con el reparto de preprocess: un tema no puede implementar el mismo
 * preprocess dos veces (ThemeManager lanza excepción), así que preprocess_page
 * despacha desde PronensHooks, preprocess_views_view desde CatalogoHooks y
 * preprocess_commerce_order desde CorreoHooks. Aquí solo viven los hooks que
 * nadie más implementa.
 */
class CuentaHooks {

  use LineaPedidoTrait;
  use StringTranslationTrait;

  /**
   * Página pública de seguimiento de Correos Express.
   *
   * La misma que enlaza el correo de expedición (EnvioMailer). Se repite aquí
   * porque el tema no puede depender de una constante protegida de un módulo.
   */
  protected const URL_SEGUIMIENTO = 'https://s.correosexpress.com/c?n=';

  /**
   * Cuántas miniaturas enseña la tarjeta de un pedido en la lista.
   */
  protected const MINIATURAS_TARJETA = 3;

  /**
   * Rutas de cada sección del área de cliente.
   *
   * La clave es la sección activa del menú lateral; las direcciones se
   * reconocen por prefijo porque el address book de Commerce tiene varias
   * rutas (overview, añadir, editar, borrar).
   *
   * @var array<string, array<int, string>>
   */
  protected const SECCIONES = [
    'resumen' => ['user.page', 'entity.user.canonical'],
    'pedidos' => ['view.commerce_user_orders.order_page', 'entity.commerce_order.user_view'],
    'direcciones' => ['commerce_order.address_book.overview'],
    'datos' => ['entity.user.edit_form'],
  ];

  /**
   * Rutas de acceso anónimo (login y recuperación de contraseña).
   *
   * @var array<int, string>
   */
  protected const RUTAS_ACCESO = ['user.login', 'user.pass', 'user.register'];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected RouteMatchInterface $routeMatch,
    protected AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * La cáscara del área de cliente, llamada desde PronensHooks::preprocessPage.
   *
   * Pone la navegación lateral cuando la página es una sección de la cuenta
   * PROPIA (un administrador mirando el perfil de otro usuario ve la página a
   * pelo, que es lo que espera un backoffice), y en las pantallas de acceso
   * quita el bloque de título, porque el H1 lo pinta la tarjeta del formulario.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de página.
   */
  public function buildShell(array &$variables): void {
    $ruta = (string) $this->routeMatch->getRouteName();

    if (in_array($ruta, self::RUTAS_ACCESO, TRUE)) {
      $this->quitarBloqueDeTitulo($variables);
      $variables['#attached']['library'][] = 'pronens/login';
      return;
    }

    $seccion = $this->seccionActiva($ruta);
    if ($seccion === NULL) {
      return;
    }

    // El H1 de estas pantallas lo pinta su propia plantilla, mire quien mire
    // (también un administrador, o el bloque duplicaría el H1): en la lista de
    // pedidos es "Mis pedidos" por traducción de interfaz (el título de la
    // view solo existe en castellano), en la ficha lleva el número del pedido
    // (el bloque imprimiría el CAMPO order_number renderizado dentro del h1) y
    // en el resumen el título de la ruta es el nombre de usuario.
    if ($seccion !== 'direcciones' && $seccion !== 'datos') {
      $this->quitarBloqueDeTitulo($variables);
    }

    // La navegación lateral solo en la cuenta PROPIA: un administrador
    // mirando a otro usuario conserva su backoffice (las pestañas de core se
    // vacían en preprocessMenuLocalTasks, con el mismo criterio).
    if (!$this->esCuentaPropia()) {
      return;
    }

    $uid = (int) $this->currentUser->id();
    $navegacion = [];
    foreach (self::SECCIONES as $clave => $rutas) {
      $navegacion[] = [
        'clave' => $clave,
        'etiqueta' => $this->etiquetaDeSeccion($clave),
        'url' => Url::fromRoute($rutas[0] === 'user.page' ? 'entity.user.canonical' : $rutas[0], ['user' => $uid])->toString(),
        'activa' => $clave === $seccion,
      ];
    }

    $variables['cuenta'] = [
      'nav' => $navegacion,
      // La URL de logout lleva token CSRF, que es de la sesión: el contexto de
      // caché va abajo para que otra sesión del mismo usuario no herede un
      // token ajeno de la Dynamic Page Cache.
      'salir' => Url::fromRoute('user.logout')->toString(),
    ];
    $variables['#attached']['library'][] = 'pronens/cuenta';
    $variables['#cache']['contexts'][] = 'session';
  }

  /**
   * Las tarjetas de "Mis pedidos", llamado desde CatalogoHooks.
   *
   * Se montan desde las entidades del resultado y no desde los campos de la
   * view: la miniatura del producto, el estado de cara al cliente (que sale
   * del ENVÍO, no del workflow del pedido, ver estadoDelPedido()) y el desglose
   * no existen como campos de Views.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de la view commerce_user_orders.
   */
  public function buildPedidos(array &$variables): void {
    $metadatos = new CacheableMetadata();
    $pedidos = [];
    foreach ($variables['view']->result as $fila) {
      $pedido = $fila->_entity ?? NULL;
      if ($pedido instanceof OrderInterface) {
        $pedidos[] = $this->tarjetaDePedido($pedido, $metadatos);
      }
    }
    $variables['pedidos'] = $pedidos;
    $variables['url_tienda'] = Url::fromRoute('<front>')->toString();
    $variables['#attached']['library'][] = 'pronens/cuenta';
    $this->anotaCache($variables, $metadatos);
  }

  /**
   * La ficha de un pedido, llamada desde CorreoHooks::preprocessCommerceOrder.
   *
   * Misma construcción que el recibo por correo (líneas del trait, totales del
   * servicio de Commerce, direcciones desde los perfiles) más lo que solo tiene
   * sentido en la web: la línea de tiempo del envío y el enlace al seguimiento
   * público de Correos Express.
   *
   * @param array<string, mixed> $variables
   *   Variables del template commerce-order--user.
   */
  public function buildPedido(array &$variables): void {
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
    $variables['numero'] = $pedido->getOrderNumber();
    $variables['fecha'] = $pedido->getPlacedTime() ?? $pedido->getCreatedTime();
    $variables['estado'] = $this->estadoDelPedido($pedido, $metadatos);

    // Los mismos totales que el checkout y el recibo, del mismo servicio.
    $totales = $this->totalSummary()->buildTotals($pedido);
    $variables['subtotal'] = isset($totales['subtotal']) ? $this->precio($totales['subtotal']) : NULL;
    $variables['ajustes'] = $this->ajustesDelPedido($totales);
    $total = $pedido->getTotalPrice();
    $variables['total'] = $total !== NULL ? $this->precio($total) : NULL;

    $variables['facturacion'] = $this->direccion($pedido->getBillingProfile());
    $variables['pago'] = $this->medioDePago($pedido, $metadatos);
    $variables['envio'] = $this->datosDeEnvio($pedido, $metadatos);
    $variables['url_pedidos'] = Url::fromRoute('view.commerce_user_orders.order_page', [
      'user' => $pedido->getCustomerId(),
    ])->toString();

    $variables['#attached']['library'][] = 'pronens/cuenta';
    $this->anotaCache($variables, $metadatos);
  }

  /**
   * Implements hook_preprocess_user().
   *
   * El resumen de la cuenta: saludo, accesos a las secciones y el último
   * pedido. Sustituye a la ficha de usuario de core ("Miembro desde…"), que en
   * una tienda no dice nada.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de usuario.
   */
  #[Hook('preprocess_user')]
  public function preprocessUser(array &$variables): void {
    if (($variables['elements']['#view_mode'] ?? '') !== 'full') {
      return;
    }
    $usuario = $variables['user'] ?? NULL;
    if (!$usuario instanceof UserInterface) {
      return;
    }

    $metadatos = new CacheableMetadata();
    $uid = (int) $usuario->id();
    $ultimo = $this->ultimoPedido($uid);

    // El recuento y el último pedido cambian al comprar, no al editar la
    // cuenta: sin la etiqueta de lista el resumen se quedaría congelado.
    $metadatos->addCacheTags(['commerce_order_list']);

    $variables['resumen'] = [
      'nombre' => $usuario->getDisplayName(),
      'correo' => $usuario->getEmail(),
      'pedidos_url' => Url::fromRoute('view.commerce_user_orders.order_page', ['user' => $uid])->toString(),
      'direcciones_url' => Url::fromRoute('commerce_order.address_book.overview', ['user' => $uid])->toString(),
      'datos_url' => Url::fromRoute('entity.user.edit_form', ['user' => $uid])->toString(),
      'total_pedidos' => $this->cuentaPedidos($uid),
      'ultimo' => $ultimo !== NULL ? $this->tarjetaDePedido($ultimo, $metadatos) : NULL,
      'url_tienda' => Url::fromRoute('<front>')->toString(),
    ];
    $variables['#attached']['library'][] = 'pronens/cuenta';
    $this->anotaCache($variables, $metadatos);
  }

  /**
   * Implements hook_preprocess_page_title().
   *
   * En la cuenta propia, el título de la ruta de editar es el NOMBRE DE
   * USUARIO (el de la migración: "victor1"), y el del address book dice
   * "Agenda de direcciones" cuando el menú lateral dice "Direcciones". Se
   * alinean con las etiquetas del menú; para un administrador mirando a otro
   * usuario no se toca nada.
   *
   * @param array<string, mixed> $variables
   *   Variables del template del título de página.
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(array &$variables): void {
    // El mismo H1 cambia según QUIÉN mira (dueño o administrador): sin el
    // contexto, el primero en pasar dejaría su versión en la caché de render.
    $variables['#cache']['contexts'][] = 'user';
    if (!$this->esCuentaPropia()) {
      return;
    }
    $titulo = match ($this->seccionActiva((string) $this->routeMatch->getRouteName())) {
      'datos' => $this->t('Account details'),
      'direcciones' => $this->t('Addresses'),
      default => NULL,
    };
    if ($titulo !== NULL) {
      $variables['title'] = $titulo;
    }
  }

  /**
   * Implements hook_form_user_login_form_alter().
   *
   * @param array<string, mixed> $form
   *   El formulario de login.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  #[Hook('form_user_login_form_alter')]
  public function formLoginAlter(array &$form, FormStateInterface $form_state): void {
    // El correo por delante: los clientes del D7 recuerdan su correo, no el
    // nombre de usuario que les puso la migración. login_emailusername admite
    // los dos; aquí solo se reordena la etiqueta.
    $form['name']['#title'] = $this->t('Email address or username');
    // La etiqueta ya lo dice todo; la descripción del módulo solo mete ruido.
    $form['name']['#description'] = '';
    $form['pass']['#description'] = '';
    $form['#attributes']['class'][] = 'pro-acceso__form';
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#attributes']['class'][] = 'pro-acceso__submit';
    }
  }

  /**
   * Implements hook_form_user_pass_alter().
   *
   * @param array<string, mixed> $form
   *   El formulario de recuperación de contraseña.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  #[Hook('form_user_pass_alter')]
  public function formPassAlter(array &$form, FormStateInterface $form_state): void {
    $form['name']['#title'] = $this->t('Email address or username');
    // El aviso de core ("las instrucciones se enviarán…") lo dice ya la
    // entradilla de la tarjeta, y en el tono de la tienda.
    if (isset($form['mail'])) {
      $form['mail']['#access'] = FALSE;
    }
    $form['#attributes']['class'][] = 'pro-acceso__form';
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#attributes']['class'][] = 'pro-acceso__submit';
    }
  }

  /**
   * Implements hook_theme_suggestions_form_alter().
   *
   * Core no sugiere plantilla por formulario; con esto el login y la
   * recuperación tienen la suya (form--user-login-form, form--user-pass) y el
   * resto sigue cayendo en form.html.twig.
   *
   * @param array<int, string> $suggestions
   *   Sugerencias de plantilla.
   * @param array<string, mixed> $variables
   *   Variables, con el elemento del formulario.
   */
  #[Hook('theme_suggestions_form_alter')]
  public function themeSuggestionsFormAlter(array &$suggestions, array $variables): void {
    $form_id = $variables['element']['#form_id'] ?? '';
    if ($form_id !== '') {
      $suggestions[] = 'form__' . $form_id;
    }
  }

  /**
   * La tarjeta de un pedido: número, fecha, estado, total y miniaturas.
   *
   * @return array<string, mixed>
   *   Datos para la plantilla de la lista y para el resumen de la cuenta.
   */
  protected function tarjetaDePedido(OrderInterface $pedido, CacheableMetadata $metadatos): array {
    $metadatos->addCacheableDependency($pedido);

    $fotos = [];
    $articulos = 0;
    foreach ($pedido->getItems() as $linea) {
      $articulos += (int) $linea->getQuantity();
      if (count($fotos) < self::MINIATURAS_TARJETA) {
        $foto = $this->fotoDeLinea($linea, $metadatos);
        if ($foto !== NULL) {
          $fotos[] = $foto;
        }
      }
    }
    $total = $pedido->getTotalPrice();

    return [
      'numero' => $pedido->getOrderNumber(),
      'url' => Url::fromRoute('entity.commerce_order.user_view', [
        'user' => $pedido->getCustomerId(),
        'commerce_order' => $pedido->id(),
      ])->toString(),
      'fecha' => $pedido->getPlacedTime() ?? $pedido->getCreatedTime(),
      'estado' => $this->estadoDelPedido($pedido, $metadatos),
      'total' => $total !== NULL ? $this->precio($total) : NULL,
      'fotos' => $fotos,
      'articulos' => $articulos,
    ];
  }

  /**
   * El estado de un pedido con los ojos del cliente.
   *
   * El workflow del pedido no sirve para esto: order_default pasa a
   * "completed" en el momento de comprar, así que todos los pedidos dirían
   * "Completado" con la caja aún en el taller. Lo que el cliente quiere saber
   * es dónde está su paquete, y eso lo dice el ENVÍO: el estado de la entidad
   * shipment y, cuando Correos Express ya ha informado, la situación del
   * seguimiento (cex_ultimo_estado, que escribe el sincronizador).
   *
   * @return array<string, mixed>
   *   Clave para el CSS y etiqueta traducible.
   */
  protected function estadoDelPedido(OrderInterface $pedido, CacheableMetadata $metadatos): array {
    if ($pedido->getState()->getId() === 'canceled') {
      return ['clave' => 'cancelado', 'etiqueta' => $this->t('Canceled', [], ['context' => 'order status'])];
    }

    $enviado = FALSE;
    foreach ($this->enviosDelPedido($pedido, $metadatos) as $envio) {
      $situacion = $this->situacionDeSeguimiento($envio);
      if ($situacion === 'entregado') {
        return ['clave' => 'entregado', 'etiqueta' => $this->t('Delivered', [], ['context' => 'order status'])];
      }
      if ($situacion === 'devuelto') {
        return ['clave' => 'devuelto', 'etiqueta' => $this->t('Returned', [], ['context' => 'order status'])];
      }
      if ($envio->getState()->getId() === 'shipped') {
        $enviado = TRUE;
      }
    }

    return $enviado
      ? ['clave' => 'enviado', 'etiqueta' => $this->t('Shipped', [], ['context' => 'order status'])]
      : ['clave' => 'preparacion', 'etiqueta' => $this->t('In preparation', [], ['context' => 'order status'])];
  }

  /**
   * Dirección, método, seguimiento y línea de tiempo del envío del pedido.
   *
   * @return array<string, mixed>|null
   *   Datos del envío, o NULL si el pedido no tiene.
   */
  protected function datosDeEnvio(OrderInterface $pedido, CacheableMetadata $metadatos): ?array {
    $envios = $this->enviosDelPedido($pedido, $metadatos);
    $envio = reset($envios);
    if ($envio === FALSE) {
      return NULL;
    }
    $metodo = $envio->getShippingMethod();
    $seguimiento = (string) ($envio->getTrackingCode() ?? '');

    return [
      'direccion' => $this->direccion($envio->getShippingProfile()),
      'metodo' => $metodo !== NULL ? $this->etiqueta($metodo) : NULL,
      'seguimiento' => $seguimiento !== '' ? $seguimiento : NULL,
      'url_seguimiento' => $seguimiento !== '' ? self::URL_SEGUIMIENTO . $seguimiento : NULL,
      'pasos' => $pedido->getState()->getId() === 'canceled' ? [] : $this->pasosDelEnvio($pedido, $envio),
    ];
  }

  /**
   * La línea de tiempo del envío: recibido, en preparación, enviado, entregado.
   *
   * Las fechas salen de lo que de verdad pasó: la de compra del pedido, la de
   * expedición del shipment (la pone la sincronización cuando el transportista
   * recoge) y la del evento de entrega que informó Correos Express.
   *
   * @return array<int, array<string, mixed>>
   *   Pasos con etiqueta, fecha (timestamp o NULL), hecho y actual.
   */
  protected function pasosDelEnvio(OrderInterface $pedido, ShipmentInterface $envio): array {
    $situacion = $this->situacionDeSeguimiento($envio);
    $entregado = $situacion === 'entregado';
    $enviado = $entregado || $envio->getState()->getId() === 'shipped';

    $fecha_entrega = NULL;
    if ($entregado) {
      $estado = $envio->getData('cex_ultimo_estado');
      $fecha_entrega = is_array($estado) && !empty($estado['fecha'])
        ? strtotime((string) $estado['fecha']) ?: NULL
        : NULL;
    }

    $pasos = [
      [
        'etiqueta' => $this->t('Order received'),
        'fecha' => $pedido->getPlacedTime() ?? $pedido->getCreatedTime(),
        'hecho' => TRUE,
        'actual' => FALSE,
      ],
      [
        'etiqueta' => $this->t('In preparation', [], ['context' => 'order status']),
        'fecha' => NULL,
        'hecho' => TRUE,
        'actual' => !$enviado,
      ],
      [
        'etiqueta' => $this->t('Shipped', [], ['context' => 'order status']),
        'fecha' => $envio->getShippedTime(),
        'hecho' => $enviado,
        'actual' => $enviado && !$entregado,
      ],
      [
        'etiqueta' => $this->t('Delivered', [], ['context' => 'order status']),
        'fecha' => $fecha_entrega,
        'hecho' => $entregado,
        'actual' => $entregado,
      ],
    ];

    return $pasos;
  }

  /**
   * La situación del seguimiento de Correos Express de un envío, si la hay.
   */
  protected function situacionDeSeguimiento(ShipmentInterface $envio): ?string {
    $estado = $envio->getData('cex_ultimo_estado');

    return is_array($estado) && isset($estado['situacion']) ? (string) $estado['situacion'] : NULL;
  }

  /**
   * Los envíos de un pedido, anotados en la caché.
   *
   * @return array<int, \Drupal\commerce_shipping\Entity\ShipmentInterface>
   *   Los envíos, o vacío.
   */
  protected function enviosDelPedido(OrderInterface $pedido, CacheableMetadata $metadatos): array {
    if (!$pedido->hasField('shipments')) {
      return [];
    }
    $envios = array_filter(
      $pedido->get('shipments')->referencedEntities(),
      static fn ($envio) => $envio instanceof ShipmentInterface,
    );
    foreach ($envios as $envio) {
      $metadatos->addCacheableDependency($envio);
    }

    return $envios;
  }

  /**
   * La etiqueta del medio de pago con el que se pagó el pedido.
   */
  protected function medioDePago(OrderInterface $pedido, CacheableMetadata $metadatos): ?string {
    if (!$pedido->hasField('payment_gateway') || $pedido->get('payment_gateway')->isEmpty()) {
      return NULL;
    }
    $pasarela = $pedido->get('payment_gateway')->entity;
    if ($pasarela === NULL) {
      return NULL;
    }
    $metadatos->addCacheableDependency($pasarela);

    return (string) $pasarela->label();
  }

  /**
   * La dirección de un perfil, sin etiqueta ni envoltorio de administración.
   *
   * @return array<string, mixed>|null
   *   Render array de la dirección, o NULL si no hay.
   */
  protected function direccion(?ProfileInterface $perfil): ?array {
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
   * El último pedido hecho (no carrito) de un usuario, o NULL.
   */
  protected function ultimoPedido(int $uid): ?OrderInterface {
    $ids = $this->entityTypeManager->getStorage('commerce_order')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $uid)
      ->condition('state', 'draft', '<>')
      ->sort('placed', 'DESC')
      ->range(0, 1)
      ->execute();
    $id = reset($ids);

    if ($id === FALSE) {
      return NULL;
    }
    $pedido = $this->entityTypeManager->getStorage('commerce_order')->load($id);

    return $pedido instanceof OrderInterface ? $pedido : NULL;
  }

  /**
   * Cuántos pedidos hechos (no carritos) tiene un usuario.
   */
  protected function cuentaPedidos(int $uid): int {
    return (int) $this->entityTypeManager->getStorage('commerce_order')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $uid)
      ->condition('state', 'draft', '<>')
      ->count()
      ->execute();
  }

  /**
   * La sección del área de cliente a la que pertenece una ruta, o NULL.
   */
  protected function seccionActiva(string $ruta): ?string {
    foreach (self::SECCIONES as $clave => $rutas) {
      if (in_array($ruta, $rutas, TRUE)) {
        return $clave;
      }
    }
    // El address book tiene rutas de añadir, editar y borrar además de la
    // portada; todas cuentan como la sección de direcciones.
    if (str_starts_with($ruta, 'commerce_order.address_book.')) {
      return 'direcciones';
    }

    return NULL;
  }

  /**
   * ¿La página es de la cuenta del usuario que la está mirando?
   */
  protected function esCuentaPropia(): bool {
    if (!$this->currentUser->isAuthenticated()) {
      return FALSE;
    }
    $usuario = $this->routeMatch->getParameter('user');
    if ($usuario === NULL) {
      return TRUE;
    }
    $uid = $usuario instanceof UserInterface ? (int) $usuario->id() : (int) $usuario;

    return $uid === (int) $this->currentUser->id();
  }

  /**
   * La etiqueta de una sección del menú lateral.
   */
  protected function etiquetaDeSeccion(string $clave): mixed {
    return match ($clave) {
      'resumen' => $this->t('Overview', [], ['context' => 'account']),
      'pedidos' => $this->t('My orders'),
      'direcciones' => $this->t('Addresses'),
      'datos' => $this->t('Account details'),
      default => $clave,
    };
  }

  /**
   * Suma metadatos de caché a los que la plantilla ya tuviera.
   *
   * No vale array_merge_recursive sobre #cache: con max-age a los dos lados
   * los convertiría en un array y DrupalRender revienta. CacheableMetadata ya
   * sabe fusionar (unión de tags y contexts, mínimo de max-age).
   *
   * @param array<string, mixed> $variables
   *   Variables del template.
   */
  protected function anotaCache(array &$variables, CacheableMetadata $metadatos): void {
    $render = ['#cache' => $variables['#cache'] ?? []];
    CacheableMetadata::createFromRenderArray($render)
      ->merge($metadatos)
      ->applyTo($render);
    $variables['#cache'] = $render['#cache'];
  }

  /**
   * Quita el bloque de título: el H1 lo pinta la plantilla de la sección.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de página.
   */
  protected function quitarBloqueDeTitulo(array &$variables): void {
    foreach (array_keys($variables['page']['content'] ?? []) as $key) {
      $bloque = $variables['page']['content'][$key];
      if (is_array($bloque) && ($bloque['#base_plugin_id'] ?? '') === 'page_title_block') {
        unset($variables['page']['content'][$key]);
      }
    }
  }

  /**
   * Implements hook_preprocess_menu_local_tasks().
   *
   * Vacía las pestañas de core (Ver, Editar, Medios de pago…) en la cuenta
   * PROPIA y en el acceso: duplican la navegación lateral con etiquetas de
   * backoffice, y en el login ofrecen "Reinicializar su contraseña" cuando la
   * tarjeta ya lleva su propio enlace. Un administrador mirando a otro usuario
   * conserva las suyas.
   *
   * Se hace aquí y no quitando el bloque en preprocess_page: para el anónimo
   * el bloque llega a la página como placeholder de #lazy_builder (sin
   * #base_plugin_id) y no hay forma fiable de reconocerlo ahí. Este preprocess
   * corre DENTRO del lazy builder, así que vale en todos los caminos.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de las pestañas.
   */
  #[Hook('preprocess_menu_local_tasks')]
  public function preprocessMenuLocalTasks(array &$variables): void {
    // La decisión depende de quién mira (dueño o administrador), no solo de la
    // ruta que ya trae el contexto por defecto de las local tasks.
    $variables['#cache']['contexts'][] = 'user';
    $ruta = (string) $this->routeMatch->getRouteName();
    $es_acceso = in_array($ruta, self::RUTAS_ACCESO, TRUE);
    if ($es_acceso || ($this->seccionActiva($ruta) !== NULL && $this->esCuentaPropia())) {
      $variables['primary'] = [];
      $variables['secondary'] = [];
    }
  }

  /**
   * El servicio de totales de Commerce, resuelto al usarlo.
   *
   * Mismo motivo que en CorreoHooks: un tema no puede declarar servicios y la
   * inyección por el contenedor de hooks arriesga la referencia circular que
   * ya apareció con el formateador de moneda.
   */
  protected function totalSummary(): \Drupal\commerce_order\OrderTotalSummaryInterface {
    // @phpstan-ignore-next-line
    return \Drupal::service('commerce_order.order_total_summary');
  }

}
