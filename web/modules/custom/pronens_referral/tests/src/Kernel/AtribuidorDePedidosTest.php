<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_referral\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\pronens_referral\Atribucion;
use Drupal\pronens_referral\AtribuidorDePedidos;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Pruebas del marcado del pedido sobre pedidos de verdad.
 */
#[CoversClass(AtribuidorDePedidos::class)]
#[Group('pronens_referral')]
final class AtribuidorDePedidosTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_promotion',
    'klaro',
    'pronens_referral',
  ];

  /**
   * El servicio bajo prueba.
   */
  private AtribuidorDePedidos $atribuidor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installConfig(['pronens_referral']);
    $this->atribuidor = $this->container->get(AtribuidorDePedidos::class);
  }

  /**
   * Con la cookie puesta, el pedido sale marcado con su colocación.
   */
  public function testLaCookieMarcaElPedido(): void {
    $this->conCookie('ficha_publica', 'banner12-s31183');
    $pedido = $this->crearPedido();

    $this->atribuidor->consolidar($pedido);

    $this->assertSame('educoland', $pedido->get('field_ref_source')->value);
    $this->assertSame('ficha_publica', $pedido->get('field_ref_campaign')->value);
    $this->assertSame('banner12-s31183', $pedido->get('field_ref_content')->value);
    $this->assertSame(Atribucion::METODO_COOKIE, $pedido->get('field_ref_method')->value);
    $this->assertNotEmpty($pedido->get('field_ref_seen')->value);
  }

  /**
   * Sin consentimiento no hay cookie, pero el cupón salva la atribución.
   */
  public function testElCuponMarcaElPedidoSinCookie(): void {
    $pedido = $this->crearPedido();
    $this->conCupon($pedido, 'EDUCOFAM10');

    $this->atribuidor->consolidar($pedido);

    $this->assertSame('educoland', $pedido->get('field_ref_source')->value);
    $this->assertSame(Atribucion::METODO_CUPON, $pedido->get('field_ref_method')->value);
    // Sin cookie no se sabe de qué enlace vino: ni colocación ni creatividad.
    $this->assertTrue($pedido->get('field_ref_campaign')->isEmpty());
    $this->assertTrue($pedido->get('field_ref_seen')->isEmpty());
  }

  /**
   * El cupón por prefijo (B2B por centro) cuenta igual.
   */
  public function testElCuponPorPrefijoTambien(): void {
    $pedido = $this->crearPedido();
    $this->conCupon($pedido, 'EDUCO-12345678');

    $this->atribuidor->consolidar($pedido);

    $this->assertSame(Atribucion::METODO_CUPON, $pedido->get('field_ref_method')->value);
  }

  /**
   * Con las dos cosas, el método lo dice y la colocación se conserva.
   */
  public function testCookieYCupon(): void {
    $this->conCookie('email_oferta', '');
    $pedido = $this->crearPedido();
    $this->conCupon($pedido, 'EDUBABY10');

    $this->atribuidor->consolidar($pedido);

    $this->assertSame(Atribucion::METODO_AMBOS, $pedido->get('field_ref_method')->value);
    $this->assertSame('email_oferta', $pedido->get('field_ref_campaign')->value);
  }

  /**
   * Sin cookie y sin cupón, el pedido no se toca.
   */
  public function testSinNadaNoSeToca(): void {
    $pedido = $this->crearPedido();
    $this->conCupon($pedido, 'BIENVENIDA10');

    $this->atribuidor->consolidar($pedido);

    $this->assertTrue($pedido->get('field_ref_source')->isEmpty());
    $this->assertTrue($pedido->get('field_ref_method')->isEmpty());
  }

  /**
   * El interruptor general apaga la captura.
   */
  public function testApagadoNoMarcaNada(): void {
    $this->config(AtribuidorDePedidos::NOMBRE_CONFIG)->set('activo', FALSE)->save();
    $this->conCookie('ficha_publica', 'banner1');
    $pedido = $this->crearPedido();

    $this->atribuidor->consolidar($pedido);

    $this->assertTrue($pedido->get('field_ref_source')->isEmpty());
  }

  /**
   * La cookie de esta petición pisa lo que capturó el carrito (last-click).
   */
  public function testLaUltimaVisitaManda(): void {
    $this->conCookie('ficha_familia', 'banner3');
    $pedido = $this->crearPedido();
    $this->atribuidor->capturarEnCarrito($pedido);
    $this->assertSame('ficha_familia', $pedido->get('field_ref_campaign')->value);

    $this->conCookie('email_alta', '');
    $this->atribuidor->consolidar($pedido);

    $this->assertSame('email_alta', $pedido->get('field_ref_campaign')->value);
    $this->assertTrue($pedido->get('field_ref_content')->isEmpty());
  }

  /**
   * Lo del carrito sobrevive aunque el pedido se coloque sin cookie.
   *
   * Es el caso del pedido que se coloca a mano desde el backoffice días
   * después, que en esta tienda ha pasado de verdad (P-2026-0004).
   */
  public function testLoCapturadoEnElCarritoSobrevive(): void {
    $this->conCookie('dashboard_centro', 'banner7');
    $pedido = $this->crearPedido();
    $this->atribuidor->capturarEnCarrito($pedido);

    $this->sinCookie();
    $this->atribuidor->consolidar($pedido);

    $this->assertSame('dashboard_centro', $pedido->get('field_ref_campaign')->value);
    $this->assertSame(Atribucion::METODO_COOKIE, $pedido->get('field_ref_method')->value);
  }

  /**
   * La red de seguridad rellena, pero nunca pisa.
   */
  public function testRellenarSiFaltaNoPisa(): void {
    $this->conCookie('ficha_publica', 'banner12');
    $pedido = $this->crearPedido('completed');
    $this->atribuidor->rellenarSiFalta($pedido);
    $this->assertSame('ficha_publica', $pedido->get('field_ref_campaign')->value);

    $this->conCookie('landing_tipo', 'banner99');
    $this->atribuidor->rellenarSiFalta($pedido);

    $this->assertSame('ficha_publica', $pedido->get('field_ref_campaign')->value);
  }

  /**
   * Un carrito sin colocar no se rellena: todavía no es una venta.
   */
  public function testRellenarSiFaltaSoloEnPedidosColocados(): void {
    $this->conCookie('ficha_publica', 'banner12');
    $pedido = $this->crearPedido('draft');

    $this->atribuidor->rellenarSiFalta($pedido);

    $this->assertTrue($pedido->get('field_ref_source')->isEmpty());
  }

  /**
   * Un pedido de verdad: se coloca y queda marcado sin llamar a nadie a mano.
   */
  public function testAlColocarseElPedidoQuedaMarcado(): void {
    $this->conCookie('perfil_educadora', 'banner4-s12');
    $pedido = $this->crearPedido();

    $pedido->getState()->applyTransitionById('place');
    $pedido->save();

    $guardado = Order::load($pedido->id());
    $this->assertInstanceOf(Order::class, $guardado);
    $this->assertSame('educoland', $guardado->get('field_ref_source')->value);
    $this->assertSame('perfil_educadora', $guardado->get('field_ref_campaign')->value);
    $this->assertSame(Atribucion::METODO_COOKIE, $guardado->get('field_ref_method')->value);
  }

  /**
   * Deja una petición con la cookie de atribución puesta.
   */
  private function conCookie(string $colocacion, string $creatividad): void {
    $peticion = $this->peticion();
    $peticion->cookies->set(AtribuidorDePedidos::COOKIE, (string) json_encode([
      's' => 'educoland',
      'c' => $colocacion,
      'ct' => $creatividad !== '' ? $creatividad : NULL,
      't' => $this->container->get('datetime.time')->getRequestTime() - 60,
    ]));
    $this->container->get('request_stack')->push($peticion);
  }

  /**
   * Deja una petición sin cookie (el backoffice, por ejemplo).
   */
  private function sinCookie(): void {
    $this->container->get('request_stack')->push($this->peticion());
  }

  /**
   * Una petición de navegador con sesión: el carrito de Commerce la pide.
   */
  private function peticion(): Request {
    $peticion = Request::create('/');
    $peticion->setSession(new Session(new MockArraySessionStorage()));
    return $peticion;
  }

  /**
   * Un pedido con una línea.
   */
  private function crearPedido(string $estado = 'draft'): OrderInterface {
    $linea = OrderItem::create([
      'type' => 'test',
      'quantity' => '1',
      'unit_price' => new Price('30.25', 'USD'),
    ]);
    $linea->save();

    $pedido = Order::create([
      'type' => 'default',
      'state' => $estado,
      'mail' => 'familia@example.com',
      'store_id' => $this->store,
      'order_items' => [$linea],
      'uid' => 0,
    ]);
    $pedido->save();

    return $pedido;
  }

  /**
   * Aplica un cupón al pedido.
   */
  private function conCupon(OrderInterface $pedido, string $codigo): void {
    $promocion = Promotion::create([
      'name' => 'Convenio',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => ['percentage' => '0.10'],
      ],
      'status' => TRUE,
    ]);
    $promocion->save();
    $cupon = Coupon::create(['promotion_id' => $promocion->id(), 'code' => $codigo, 'status' => TRUE]);
    $cupon->save();
    $pedido->set('coupons', [$cupon]);
  }

}
