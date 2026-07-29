<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_personalizacion\Unit;

use Drupal\commerce_price\Price;
use Drupal\pronens_personalizacion\ExtrasCalculator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de las reglas de precio de los extras.
 */
#[CoversClass(ExtrasCalculator::class)]
#[Group('pronens_personalizacion')]
final class ExtrasCalculatorTest extends UnitTestCase {

  private ExtrasCalculator $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new ExtrasCalculator();
  }

  #[DataProvider('proveedorTexto')]
  public function testHasText(?string $texto, bool $esperado): void {
    $this->assertSame($esperado, $this->calculator->hasText($texto));
  }

  /**
   * @return array<string, array{string|null, bool}>
   */
  public static function proveedorTexto(): array {
    return [
      'nombre normal' => ['Mónica', TRUE],
      'con espacios alrededor' => ['  Nico  ', TRUE],
      'solo espacios' => ['   ', FALSE],
      'cadena vacía' => ['', FALSE],
      'nulo' => [NULL, FALSE],
    ];
  }

  public function testExtraGratuitoNoCobra(): void {
    $this->assertNull($this->calculator->calculate(new Price('0', 'EUR'), 3));
  }

  public function testSinPrecioNoCobra(): void {
    $this->assertNull($this->calculator->calculate(NULL, 3));
  }

  public function testPrecioNegativoNoCobra(): void {
    $this->assertNull($this->calculator->calculate(new Price('-6.00', 'EUR'), 1));
  }

  public function testCantidadInvalidaNoCobra(): void {
    $this->assertNull($this->calculator->calculate(new Price('6.00', 'EUR'), 0));
  }

  /**
   * El extra se cobra por unidad: dos mochilas llevan dos llaveros.
   */
  public function testSeCobraPorUnidad(): void {
    $importe = $this->calculator->calculate(new Price('6.00', 'EUR'), 3);

    $this->assertInstanceOf(Price::class, $importe);
    // Se comparan objetos Price: getNumber() normaliza y devolvería '18', no
    // '18.00'.
    $this->assertTrue($importe->equals(new Price('18.00', 'EUR')));
    $this->assertSame('EUR', $importe->getCurrencyCode());
  }

  public function testUnaUnidadCobraElPrecioUnitario(): void {
    $importe = $this->calculator->calculate(new Price('6.00', 'EUR'));

    $this->assertInstanceOf(Price::class, $importe);
    $this->assertTrue($importe->equals(new Price('6.00', 'EUR')));
  }

}
