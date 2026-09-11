<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_seo\Unit;

use Drupal\pronens_seo\TituloSeo;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\pronens_seo\TituloSeo
 * @group pronens_seo
 */
final class TituloSeoTest extends TestCase {

  /**
   * El marcador se sustituye por el diseño del producto.
   */
  public function testElDisenoOcupaElMarcador(): void {
    self::assertSame(
      'Saco de almuerzo o muda escolar, diseño Sakura',
      TituloSeo::deProducto('Saco de almuerzo o muda escolar, diseño @diseno', 'Sakura'),
    );
  }

  /**
   * El marcador vale en cualquier posición: en inglés va delante.
   */
  public function testElMarcadorPuedeIrDelante(): void {
    self::assertSame(
      'Little Red Riding Hood lunch bag',
      TituloSeo::deProducto('@diseno lunch bag', ' Little Red Riding Hood '),
    );
  }

  /**
   * Sin diseño no hay título con patrón: la ficha conserva el suyo.
   */
  public function testSinDisenoNoHayTitulo(): void {
    self::assertNull(TituloSeo::deProducto('Saco de almuerzo, diseño @diseno', ''));
    self::assertNull(TituloSeo::deProducto('Saco de almuerzo, diseño @diseno', '   '));
  }

  /**
   * Sin patrón tampoco.
   */
  public function testSinPatronNoHayTitulo(): void {
    self::assertNull(TituloSeo::deProducto('', 'Sakura'));
  }

  /**
   * Un patrón sin marcador se devuelve tal cual: es un título fijo.
   */
  public function testPatronSinMarcadorSeDevuelveEntero(): void {
    self::assertSame('Sacos de almuerzo', TituloSeo::deProducto('Sacos de almuerzo', 'Sakura'));
  }

  /**
   * Los espacios dobles que deje la sustitución se recogen.
   */
  public function testEspaciosRepetidosSeRecogen(): void {
    self::assertSame('Diseño Sakura para guardería', TituloSeo::deProducto('Diseño  @diseno  para guardería', 'Sakura'));
  }

  /**
   * Solo se toca el token: la cola configurada se conserva.
   */
  public function testSustituyeSoloElToken(): void {
    self::assertSame(
      'Bolsas de guardería y sacos de almuerzo | [site:name]',
      TituloSeo::sustituye('[term:name] | [site:name]', '[term:name]', 'Bolsas de guardería y sacos de almuerzo'),
    );
  }

  /**
   * Si el cliente ha quitado el token de la etiqueta por defecto, no se pisa.
   */
  public function testSinTokenNoSePisa(): void {
    self::assertNull(TituloSeo::sustituye('Catálogo de Pronens', '[term:name]', 'Bolsas'));
  }

}
