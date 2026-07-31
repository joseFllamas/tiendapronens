<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_comision_pago\Unit;

use Drupal\commerce_price\Price;
use Drupal\pronens_comision_pago\ComisionCalculator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de las reglas de la comisión por medio de pago.
 */
#[CoversClass(ComisionCalculator::class)]
#[Group('pronens_comision_pago')]
final class ComisionCalculatorTest extends UnitTestCase {

  private ComisionCalculator $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new ComisionCalculator();
  }

  /**
   * @param array<int, string> $configuradas
   *   Las pasarelas configuradas para repercutir comisión.
   */
  #[DataProvider('proveedorPasarelas')]
  public function testAplicaA(?string $pasarela, array $configuradas, bool $esperado): void {
    $this->assertSame($esperado, $this->calculator->aplicaA($pasarela, $configuradas));
  }

  /**
   * @return array<string, array{string|null, array<int, string>, bool}>
   */
  public static function proveedorPasarelas(): array {
    return [
      'paypal cobra comisión' => ['paypal', ['paypal'], TRUE],
      'el TPV del banco no' => ['redsys', ['paypal'], FALSE],
      'la transferencia tampoco' => ['transferencia', ['paypal'], FALSE],
      'sin pasarela elegida todavía' => [NULL, ['paypal'], FALSE],
      'cadena vacía' => ['', ['paypal'], FALSE],
      'sin ninguna configurada' => ['paypal', [], FALSE],
      'varias configuradas' => ['transferencia', ['paypal', 'transferencia'], TRUE],
    ];
  }

  public function testComisionSobreElTotal(): void {
    $comision = $this->calculator->calculate(new Price('60.00', 'EUR'), '1.5');

    $this->assertInstanceOf(Price::class, $comision);
    // Price recorta los ceros de la derecha, así que 0.900000 se guarda 0.9.
    $this->assertSame('0.9', $comision->getNumber());
    $this->assertSame('EUR', $comision->getCurrencyCode());
  }

  public function testLaBaseIncluyeTodoLoQueCobraLaPasarela(): void {
    // 24,81 de prenda + 5,00 de bordado + 6,00 de extra + 4,95 de envío: la
    // comisión se calcula sobre los 40,76, no sobre el precio de la prenda.
    $comision = $this->calculator->calculate(new Price('40.76', 'EUR'), '2.9');

    $this->assertInstanceOf(Price::class, $comision);
    $this->assertSame('1.18204', $comision->getNumber());
  }

  #[DataProvider('proveedorSinComision')]
  public function testNoHayComision(?Price $total, string $porcentaje): void {
    $this->assertNull($this->calculator->calculate($total, $porcentaje));
  }

  /**
   * @return array<string, array{\Drupal\commerce_price\Price|null, string}>
   */
  public static function proveedorSinComision(): array {
    return [
      'sin total' => [NULL, '1.5'],
      'pedido a cero' => [new Price('0', 'EUR'), '1.5'],
      'total negativo' => [new Price('-10.00', 'EUR'), '1.5'],
      'porcentaje a cero' => [new Price('60.00', 'EUR'), '0'],
      'porcentaje negativo' => [new Price('60.00', 'EUR'), '-1.5'],
      'porcentaje vacío' => [new Price('60.00', 'EUR'), ''],
      'porcentaje no numérico' => [new Price('60.00', 'EUR'), 'gratis'],
      // La notación científica no la entiende bcmath: daría cero en silencio.
      'notación científica' => [new Price('60.00', 'EUR'), '1e2'],
    ];
  }

  #[DataProvider('proveedorFormato')]
  public function testFormatearPorcentaje(string $porcentaje, string $esperado): void {
    $this->assertSame($esperado, $this->calculator->formatearPorcentaje($porcentaje));
  }

  /**
   * @return array<string, array{string, string}>
   */
  public static function proveedorFormato(): array {
    return [
      'coma decimal' => ['1.5', '1,5'],
      'sin decimales sobrantes' => ['3', '3'],
      'dos decimales' => ['2.75', '2,75'],
      'cero a la derecha' => ['1.50', '1,5'],
      'no numérico se devuelve tal cual' => ['gratis', 'gratis'],
    ];
  }

}
