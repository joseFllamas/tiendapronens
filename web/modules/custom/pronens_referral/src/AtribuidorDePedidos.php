<?php

declare(strict_types=1);

namespace Drupal\pronens_referral;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Escribe en el pedido de dónde viene, por cookie y por cupón.
 *
 * El reparto de papeles, y el porqué de cada uno:
 *
 * - La COOKIE la escribe el navegador (ver js/referral.js) y solo después de
 *   que Klaro tenga consentimiento. Aquí no se escribe ninguna cookie: hacerlo
 *   desde PHP en una página normal metería un `Set-Cookie` en una respuesta
 *   cacheada y la compartirían todos los visitantes.
 * - El CUPÓN no necesita consentimiento y es la red cuando no lo hay. Se mira
 *   el código de los cupones aplicados al pedido, no la promoción.
 *
 * La marca se pone DOS veces a propósito:
 *
 * 1. Al entrar algo en el carrito, porque ahí seguro que hay una petición del
 *    navegador del cliente con su cookie.
 * 2. Al colocarse el pedido, que es cuando se consolida el método y se miran
 *    los cupones, y donde la cookie de esa misma petición manda (last-click).
 *
 * Sin (1) se perderían los pedidos que se colocan sin el navegador del cliente
 * delante, que en esta tienda han pasado de verdad: el pedido P-2026-0004 se
 * colocó a mano desde el backoffice días después porque el retorno de Redsys
 * falló, y ahí la única cookie es la del administrador.
 */
final class AtribuidorDePedidos {

  public const NOMBRE_CONFIG = 'pronens_referral.settings';
  public const COOKIE = 'pronens_ref';

  public const CAMPO_FUENTE = 'field_ref_source';
  public const CAMPO_COLOCACION = 'field_ref_campaign';
  public const CAMPO_CREATIVIDAD = 'field_ref_content';
  public const CAMPO_METODO = 'field_ref_method';
  public const CAMPO_VISTO = 'field_ref_seen';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RequestStack $requestStack,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * La referencia que trae la petición en curso, si la hay.
   */
  public function referenciaDeLaPeticion(): ?Referencia {
    if (!$this->activo()) {
      return NULL;
    }
    $peticion = $this->requestStack->getCurrentRequest();
    if ($peticion === NULL) {
      return NULL;
    }
    $config = $this->configFactory->get(self::NOMBRE_CONFIG);
    return Atribucion::desdeCookie(
      $peticion->cookies->get(self::COOKIE),
      (string) ($config->get('fuente') ?? ''),
      (int) ($config->get('dias_cookie') ?? 90),
      $this->time->getRequestTime(),
    );
  }

  /**
   * Guarda la procedencia en el carrito, sin salvarlo.
   *
   * Lo salva quien dispara el evento (CartManager::addOrderItem guarda el
   * carrito justo después), así que aquí no se llama a save(): sería un
   * guardado anidado y una revisión de más.
   */
  public function capturarEnCarrito(OrderInterface $carrito): void {
    $referencia = $this->referenciaDeLaPeticion();
    if ($referencia === NULL || !$this->tieneLosCampos($carrito)) {
      return;
    }
    $this->escribirReferencia($carrito, $referencia);
    // Provisional: al colocarse se recalcula mirando también los cupones.
    $carrito->set(self::CAMPO_METODO, Atribucion::METODO_COOKIE);
  }

  /**
   * Consolida la procedencia al colocarse el pedido.
   *
   * Se llama en el PRE_transition de `place`, no en el post: ahí el pedido
   * todavía no se ha guardado, así que basta con ponerle los campos y el save
   * de la propia transición los persiste, sin un save anidado (el mismo motivo
   * por el que PedidoInvitadoSubscriber escucha el pre).
   */
  public function consolidar(OrderInterface $pedido): void {
    if (!$this->activo() || !$this->tieneLosCampos($pedido)) {
      return;
    }
    $referencia = $this->referenciaDeLaPeticion();
    if ($referencia !== NULL) {
      // Last-click: la llegada de esta petición manda sobre lo que hubiera
      // guardado el carrito.
      $this->escribirReferencia($pedido, $referencia);
    }
    // La marca de tiempo solo la escribe la cookie, así que es la señal de que
    // hubo cookie en algún momento de la compra.
    $porCookie = !$pedido->get(self::CAMPO_VISTO)->isEmpty();
    $porCupon = $this->tieneCuponDePartner($pedido);
    $metodo = Atribucion::metodo($porCookie, $porCupon);
    if ($metodo === NULL) {
      // Ni cookie ni cupón: el pedido no es del partner y no se toca.
      return;
    }
    if ($porCupon && !$porCookie) {
      // Sin cookie no se sabe la colocación ni la creatividad, pero el pedido
      // es del partner igual: eso es justo lo que el cupón viene a salvar.
      $pedido->set(self::CAMPO_FUENTE, $this->fuente());
    }
    $pedido->set(self::CAMPO_METODO, $metodo);
  }

  /**
   * Rellena un pedido ya colocado al que le falte la marca.
   *
   * Red de seguridad idempotente: NUNCA pisa lo que ya hay, solo escribe si los
   * campos están vacíos. Cubre el pedido que llega a `completed` por un camino
   * que no pasa por la transición (un import, un cambio de estado a mano) y el
   * que se coloca desde el backoffice con el cupón puesto.
   */
  public function rellenarSiFalta(OrderInterface $pedido): void {
    if (!$this->activo() || !$this->tieneLosCampos($pedido)) {
      return;
    }
    if (!$pedido->get(self::CAMPO_FUENTE)->isEmpty()) {
      return;
    }
    if (!in_array($pedido->getState()->getId(), ['completed', 'fulfillment'], TRUE)) {
      return;
    }
    $referencia = $this->referenciaDeLaPeticion();
    if ($referencia !== NULL) {
      $this->escribirReferencia($pedido, $referencia);
    }
    $metodo = Atribucion::metodo($referencia !== NULL, $this->tieneCuponDePartner($pedido));
    if ($metodo === NULL) {
      return;
    }
    $pedido->set(self::CAMPO_FUENTE, $this->fuente());
    $pedido->set(self::CAMPO_METODO, $metodo);
  }

  /**
   * Dice si alguno de los cupones aplicados es del convenio.
   */
  public function tieneCuponDePartner(OrderInterface $pedido): bool {
    if (!$pedido->hasField('coupons')) {
      return FALSE;
    }
    $config = $this->configFactory->get(self::NOMBRE_CONFIG);
    $prefijo = (string) ($config->get('prefijo_cupon') ?? '');
    $codigos = (array) ($config->get('cupones') ?? []);
    foreach ($pedido->get('coupons')->referencedEntities() as $cupon) {
      if (!$cupon instanceof CouponInterface) {
        continue;
      }
      $codigo = (string) $cupon->getCode();
      if (Atribucion::esCuponDePartner($codigo, $prefijo, $codigos)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * El interruptor general.
   */
  public function activo(): bool {
    return (bool) $this->configFactory->get(self::NOMBRE_CONFIG)->get('activo');
  }

  /**
   * La fuente que se reconoce, saneada.
   */
  public function fuente(): string {
    $config = $this->configFactory->get(self::NOMBRE_CONFIG);
    return Atribucion::sanear($config->get('fuente') ?? '', Atribucion::MAX_FUENTE);
  }

  /**
   * Vuelca la referencia en los campos del pedido.
   */
  private function escribirReferencia(OrderInterface $pedido, Referencia $referencia): void {
    $pedido->set(self::CAMPO_FUENTE, $referencia->fuente);
    $pedido->set(self::CAMPO_COLOCACION, $referencia->colocacion !== '' ? $referencia->colocacion : NULL);
    $pedido->set(self::CAMPO_CREATIVIDAD, $referencia->creatividad !== '' ? $referencia->creatividad : NULL);
    $pedido->set(self::CAMPO_VISTO, $referencia->visto);
  }

  /**
   * Los pedidos de otros paquetes no llevan estos campos.
   */
  private function tieneLosCampos(OrderInterface $pedido): bool {
    return $pedido->hasField(self::CAMPO_FUENTE) && $pedido->hasField(self::CAMPO_METODO);
  }

}
