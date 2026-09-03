<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_seo\Unit;

use Drupal\pronens_seo\Descripcion;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\pronens_seo\Descripcion
 * @group pronens_seo
 */
final class DescripcionTest extends TestCase {

  /**
   * Dos párrafos seguidos se separan con un espacio, no se pegan.
   */
  public function testLosParrafosNoSePegan(): void {
    $html = '<p>Elige la letra.</p><p>Disponible en dos tamaños.</p>';
    self::assertSame('Elige la letra. Disponible en dos tamaños.', Descripcion::texto($html));
  }

  /**
   * Entidades HTML y espacios duros se normalizan.
   */
  public function testEntidadesConEspaciosDuros(): void {
    $html = "<p>Bordado&nbsp;en 72&nbsp;h &amp; envío\n\n gratis</p>";
    self::assertSame('Bordado en 72 h & envío gratis', Descripcion::texto($html));
  }

  /**
   * Un texto corto se devuelve entero.
   */
  public function testCortoSeDevuelveEntero(): void {
    self::assertSame('Mochila infantil.', Descripcion::resumir('<p>Mochila infantil.</p>'));
  }

  /**
   * Un texto largo se corta en la última frase que cabe.
   */
  public function testLargoSeCortaEnFrase(): void {
    $frase = 'Mochila con la inicial bordada en parche de 9 x 9 cm, para el colegio, la guardería o el día a día.';
    $html = '<p>' . $frase . '</p><p>Elige tamaño, color de mochila y la combinación de colores de la letra. Disponible en dos tamaños.</p>';
    $resumen = Descripcion::resumir($html);
    self::assertSame($frase, $resumen);
    self::assertLessThanOrEqual(Descripcion::MAXIMO, mb_strlen($resumen));
  }

  /**
   * Sin frase que quepa, se corta en palabra y sin coma colgando.
   */
  public function testSinFraseSeCortaEnPalabra(): void {
    $html = '<p>' . str_repeat('mochilas guardería originales personalizadas, ', 10) . '</p>';
    $resumen = Descripcion::resumir($html);
    self::assertLessThanOrEqual(Descripcion::MAXIMO, mb_strlen($resumen));
    self::assertStringEndsNotWith(',', $resumen);
    self::assertStringEndsNotWith(' ', $resumen);
    self::assertMatchesRegularExpression('/\\p{L}$/u', $resumen);
  }

  /**
   * El corte respeta los caracteres multibyte (tildes, eñes).
   */
  public function testMultibyte(): void {
    $html = '<p>' . str_repeat('ñandú ', 40) . '</p>';
    $resumen = Descripcion::resumir($html, 20);
    self::assertSame('ñandú ñandú ñandú', $resumen);
  }

}
