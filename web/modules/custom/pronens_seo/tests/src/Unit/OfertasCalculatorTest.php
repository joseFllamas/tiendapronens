<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_seo\Unit;

use Drupal\pronens_seo\OfertasCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\pronens_seo\OfertasCalculator
 * @group pronens_seo
 */
final class OfertasCalculatorTest extends TestCase {

  /**
   * Las listas van alineadas y el precio con dos decimales y punto.
   */
  public function testListasAlineadas(): void {
    $listas = OfertasCalculator::listas([
      ['precio' => '18.950000', 'url' => 'https://x/p?v=1', 'stock' => 100.0],
      ['precio' => 23.95, 'url' => 'https://x/p?v=2', 'stock' => 0],
      ['precio' => '7.8', 'url' => 'https://x/p?v=3', 'stock' => NULL],
    ]);
    self::assertSame('18.95,23.95,7.80', $listas['precio']);
    self::assertSame('https://x/p?v=1,https://x/p?v=2,https://x/p?v=3', $listas['url']);
    self::assertSame(
      OfertasCalculator::EN_STOCK . ',' . OfertasCalculator::AGOTADO . ',' . OfertasCalculator::EN_STOCK,
      $listas['disponibilidad']
    );
    self::assertSame('7.80', $listas['minimo']);
    self::assertSame('23.95', $listas['maximo']);
    self::assertSame('3', $listas['total']);
  }

  /**
   * Sin variaciones no hay listas ni mínimo.
   */
  public function testSinVariaciones(): void {
    $listas = OfertasCalculator::listas([]);
    self::assertSame('', $listas['precio']);
    self::assertSame('', $listas['minimo']);
    self::assertSame('0', $listas['total']);
  }

  /**
   * Stock NULL (sin control) cuenta como disponible; 0 o negativo, agotado.
   */
  public function testDisponibilidad(): void {
    self::assertSame(OfertasCalculator::EN_STOCK, OfertasCalculator::disponibilidad(NULL));
    self::assertSame(OfertasCalculator::EN_STOCK, OfertasCalculator::disponibilidad(1));
    self::assertSame(OfertasCalculator::AGOTADO, OfertasCalculator::disponibilidad(0));
    self::assertSame(OfertasCalculator::AGOTADO, OfertasCalculator::disponibilidad(-2.0));
  }

}
