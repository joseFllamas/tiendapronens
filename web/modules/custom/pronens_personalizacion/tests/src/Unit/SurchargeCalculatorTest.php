<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_personalizacion\Unit;

use Drupal\commerce_price\Price;
use Drupal\pronens_personalizacion\SurchargeCalculator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de las reglas del recargo por bordado.
 */
#[CoversClass(SurchargeCalculator::class)]
#[Group('pronens_personalizacion')]
final class SurchargeCalculatorTest extends UnitTestCase {

  private SurchargeCalculator $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new SurchargeCalculator();
  }

  #[DataProvider('proveedorPersonalizacion')]
  public function testHasPersonalization(?string $texto, bool $esperado): void {
    $this->assertSame($esperado, $this->calculator->hasPersonalization($texto));
  }

  /**
   * @return array<string, array{string|null, bool}>
   */
  public static function proveedorPersonalizacion(): array {
    return [
      'un nombre' => ['Mónica', TRUE],
      'una inicial' => ['A', TRUE],
      // El D7 tiene textos de solo espacios: sin este filtro se cobraría un
      // recargo por un bordado que no existe.
      'solo espacios' => ['   ', FALSE],
      'cadena vacía' => ['', FALSE],
      'nulo' => [NULL, FALSE],
      'texto largo real del D7' => ['ayqueguapo', TRUE],
    ];
  }

  public function testSinPersonalizacionNoHayRecargo(): void {
    $resultado = $this->calculator->calculate(FALSE, NULL, new Price('3.00', 'EUR'), 1);
    $this->assertNull($resultado, 'Una línea sin bordado no debe llevar recargo.');
  }

  public function testUsaElRecargoPorDefecto(): void {
    $resultado = $this->calculator->calculate(TRUE, NULL, new Price('3.00', 'EUR'), 1);
    $this->assertInstanceOf(Price::class, $resultado);
    // Se comparan objetos Price: getNumber() normaliza y devolvería '3', no '3.00'.
    $this->assertTrue($resultado->equals(new Price('3.00', 'EUR')));
    $this->assertSame('EUR', $resultado->getCurrencyCode());
  }

  public function testElRecargoDelProductoMandaSobreElGeneral(): void {
    $resultado = $this->calculator->calculate(
      TRUE,
      new Price('5.50', 'EUR'),
      new Price('3.00', 'EUR'),
      1,
    );
    $this->assertInstanceOf(Price::class, $resultado);
    $this->assertTrue($resultado->equals(new Price('5.50', 'EUR')));
  }

  public function testSeCobraPorUnidad(): void {
    // Cada prenda se borda por separado, así que tres unidades son tres bordados.
    $resultado = $this->calculator->calculate(TRUE, NULL, new Price('3.00', 'EUR'), 3);
    $this->assertInstanceOf(Price::class, $resultado);
    $this->assertTrue($resultado->equals(new Price('9.00', 'EUR')), 'Tres unidades bordadas son tres recargos.');
  }

  public function testRecargoCeroNoGeneraAjuste(): void {
    $resultado = $this->calculator->calculate(TRUE, NULL, new Price('0', 'EUR'), 1);
    $this->assertNull($resultado, 'Un recargo de cero no debe ensuciar el pedido con un ajuste.');
  }

  public function testRecargoNegativoSeIgnora(): void {
    // Un recargo negativo sería un descuento encubierto: para eso están las
    // promociones, no este procesador.
    $resultado = $this->calculator->calculate(TRUE, new Price('-2.00', 'EUR'), NULL, 1);
    $this->assertNull($resultado);
  }

  public function testSinNingunRecargoConfigurado(): void {
    $this->assertNull($this->calculator->calculate(TRUE, NULL, NULL, 1));
  }

  public function testCantidadInvalida(): void {
    $this->assertNull($this->calculator->calculate(TRUE, NULL, new Price('3.00', 'EUR'), 0));
  }

  /**
   * En un producto de inicial el bordado no se cobra, aunque haya recargo.
   *
   * Es el reclamo con el que se vende el producto: la inicial va incluida.
   */
  public function testElModoInicialNoCobraNunca(): void {
    $resultado = $this->calculator->calculate(
      TRUE,
      new Price('5.00', 'EUR'),
      new Price('5.00', 'EUR'),
      3,
      TRUE
    );

    $this->assertNull($resultado, 'La inicial va incluida en el precio del producto.');
  }

  /**
   * El mismo caso sin modo inicial sí cobra, para que la prueba anterior no
   * pase por casualidad.
   */
  public function testFueraDelModoInicialSiCobra(): void {
    $resultado = $this->calculator->calculate(
      TRUE,
      new Price('5.00', 'EUR'),
      new Price('5.00', 'EUR'),
      3,
      FALSE
    );

    $this->assertInstanceOf(Price::class, $resultado);
    $this->assertTrue($resultado->equals(new Price('15.00', 'EUR')));
  }

}
