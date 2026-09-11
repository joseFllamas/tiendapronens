<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_referral\Unit;

use Drupal\pronens_referral\Atribucion;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de las reglas de la atribución.
 */
#[CoversClass(Atribucion::class)]
#[Group('pronens_referral')]
final class AtribucionTest extends UnitTestCase {

  #[DataProvider('proveedorSaneado')]
  public function testSanear(mixed $entrada, string $esperado): void {
    $this->assertSame($esperado, Atribucion::sanear($entrada));
  }

  /**
   * @return array<string, array{mixed, string}>
   */
  public static function proveedorSaneado(): array {
    return [
      'una colocación del contrato' => ['ficha_publica', 'ficha_publica'],
      'una creatividad con guion' => ['banner12-s31183', 'banner12-s31183'],
      'las mayúsculas bajan' => ['Ficha_Publica', 'ficha_publica'],
      // Se borra, no se sustituye: convertir «ficha publica» en
      // «ficha-publica» inventaría una colocación que no existe.
      'el espacio se borra' => ['ficha publica', 'fichapublica'],
      'nada de etiquetas' => ['<script>alert(1)</script>', 'scriptalert1script'],
      'ni acentos ni eñes' => ['campaña_ñ', 'campaa_'],
      'no es una cadena' => [['ficha'], ''],
      'nulo' => [NULL, ''],
      'vacío' => ['', ''],
      'un número también vale' => [12, '12'],
    ];
  }

  /**
   * Más de 64 caracteres no entran en el campo del pedido.
   */
  public function testSanearCorta(): void {
    $this->assertSame(64, mb_strlen(Atribucion::sanear(str_repeat('a', 200))));
    $this->assertSame(32, mb_strlen(Atribucion::sanear(str_repeat('a', 200), Atribucion::MAX_FUENTE)));
  }

  #[DataProvider('proveedorCupones')]
  public function testEsCuponDePartner(string $codigo, bool $esperado): void {
    $this->assertSame($esperado, Atribucion::esCuponDePartner(
      $codigo,
      'EDUCO-',
      ['EDUCOFAM10', 'EDUBABY10', 'EDUCOLE10'],
    ));
  }

  /**
   * @return array<string, array{string, bool}>
   */
  public static function proveedorCupones(): array {
    return [
      'el B2B por centro, por prefijo' => ['EDUCO-12345678', TRUE],
      'el prefijo no distingue mayúsculas' => ['educo-12345678', TRUE],
      'un código público de la lista' => ['EDUCOFAM10', TRUE],
      'de la lista, en minúscula' => ['edubaby10', TRUE],
      'con espacios alrededor' => ['  EDUCOLE10  ', TRUE],
      // El cupón de otra campaña no es del convenio aunque se le parezca.
      'uno que solo se parece' => ['EDUCACION10', FALSE],
      'el de la newsletter' => ['BIENVENIDA10', FALSE],
      'vacío' => ['', FALSE],
    ];
  }

  /**
   * Sin prefijo configurado no se reconoce nada por prefijo.
   */
  public function testSinPrefijoSoloValeLaLista(): void {
    $this->assertFalse(Atribucion::esCuponDePartner('EDUCO-12345678', '', ['EDUCOFAM10']));
    $this->assertTrue(Atribucion::esCuponDePartner('EDUCOFAM10', '', ['EDUCOFAM10']));
  }

  #[DataProvider('proveedorMetodos')]
  public function testMetodo(bool $cookie, bool $cupon, ?string $esperado): void {
    $this->assertSame($esperado, Atribucion::metodo($cookie, $cupon));
  }

  /**
   * @return array<string, array{bool, bool, string|null}>
   */
  public static function proveedorMetodos(): array {
    return [
      'solo el enlace' => [TRUE, FALSE, 'cookie'],
      'solo el cupón' => [FALSE, TRUE, 'cupon'],
      'los dos' => [TRUE, TRUE, 'cookie+cupon'],
      'ninguno: el pedido no se toca' => [FALSE, FALSE, NULL],
    ];
  }

  /**
   * La cookie buena se lee entera.
   */
  public function testCookieValida(): void {
    $ahora = 1757600000;
    $json = (string) json_encode(['s' => 'educoland', 'c' => 'ficha_publica', 'ct' => 'banner12-s31183', 't' => $ahora - 3600]);
    $referencia = Atribucion::desdeCookie($json, 'educoland', 90, $ahora);

    $this->assertNotNull($referencia);
    $this->assertSame('educoland', $referencia->fuente);
    $this->assertSame('ficha_publica', $referencia->colocacion);
    $this->assertSame('banner12-s31183', $referencia->creatividad);
    $this->assertSame($ahora - 3600, $referencia->visto);
  }

  /**
   * La creatividad es opcional: hay enlaces que no vienen de un banner.
   */
  public function testCookieSinCreatividad(): void {
    $ahora = 1757600000;
    $json = (string) json_encode(['s' => 'educoland', 'c' => 'email_alta', 'ct' => NULL, 't' => $ahora]);
    $referencia = Atribucion::desdeCookie($json, 'educoland', 90, $ahora);

    $this->assertNotNull($referencia);
    $this->assertSame('email_alta', $referencia->colocacion);
    $this->assertSame('', $referencia->creatividad);
  }

  /**
   * Lo que llega dentro de la cookie también se sanea: lo escribe el navegador.
   */
  public function testCookieManipulada(): void {
    $ahora = 1757600000;
    $json = (string) json_encode(['s' => 'EDUCOLAND', 'c' => '<b>ficha publica</b>', 'ct' => str_repeat('x', 200), 't' => $ahora]);
    $referencia = Atribucion::desdeCookie($json, 'educoland', 90, $ahora);

    $this->assertNotNull($referencia);
    $this->assertSame('educoland', $referencia->fuente);
    $this->assertSame('bfichapublicab', $referencia->colocacion);
    $this->assertSame(64, mb_strlen($referencia->creatividad));
  }

  #[DataProvider('proveedorCookiesMalas')]
  public function testCookieQueNoSirve(?string $json): void {
    $this->assertNull(Atribucion::desdeCookie($json, 'educoland', 90, 1757600000));
  }

  /**
   * @return array<string, array{string|null}>
   */
  public static function proveedorCookiesMalas(): array {
    $ahora = 1757600000;
    return [
      'sin cookie' => [NULL],
      'vacía' => [''],
      'no es JSON' => ['esto no es json'],
      'JSON que no es un objeto' => ['"educoland"'],
      'de otra fuente' => [(string) json_encode(['s' => 'otraweb', 'c' => 'x', 't' => $ahora])],
      'sin fuente' => [(string) json_encode(['c' => 'ficha_publica', 't' => $ahora])],
      'sin fecha' => [(string) json_encode(['s' => 'educoland', 'c' => 'ficha_publica'])],
      // Fuera de la ventana de atribución: la visita ya no cuenta.
      'caducada' => [(string) json_encode(['s' => 'educoland', 'c' => 'x', 't' => $ahora - 91 * 86400])],
      // Un JSON enorme no es nuestro: ni se decodifica.
      'demasiado grande' => [(string) json_encode(['s' => 'educoland', 'c' => str_repeat('a', 2000), 't' => $ahora])],
    ];
  }

  /**
   * Una fecha futura (reloj adelantado) se recorta a ahora, no invalida.
   */
  public function testCookieDelFuturo(): void {
    $ahora = 1757600000;
    $json = (string) json_encode(['s' => 'educoland', 'c' => 'ficha_publica', 't' => $ahora + 86400]);
    $referencia = Atribucion::desdeCookie($json, 'educoland', 90, $ahora);

    $this->assertNotNull($referencia);
    $this->assertSame($ahora, $referencia->visto);
  }

}
