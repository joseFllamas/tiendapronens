<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Peso\EstimadorPeso;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del relleno de pesos al preparar un envío.
 */
#[CoversClass(EstimadorPeso::class)]
#[Group('pronens_correos_express')]
final class EstimadorPesoTest extends UnitTestCase {

  /**
   * El estimador bajo prueba, con 300 g por defecto y 100 g de mínimo.
   */
  private EstimadorPeso $estimador;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->estimador = new EstimadorPeso(300, 100);
  }

  /**
   * El peso guardado en la variación manda sobre cualquier estimación.
   */
  public function testElPesoGuardadoManda(): void {
    $resultado = $this->estimador->pesoUnitario(new Weight('250', WeightUnit::GRAM), 400);

    $this->assertSame('250', $resultado->getNumber());
    $this->assertSame(WeightUnit::GRAM, $resultado->getUnit());
  }

  /**
   * Sin peso guardado se usa la estimación de la categoría.
   */
  public function testSinPesoSeUsaLaEstimacion(): void {
    $resultado = $this->estimador->pesoUnitario(NULL, 120);

    $this->assertSame('120', $resultado->getNumber());
  }

  /**
   * Sin peso ni estimación se usa el valor por defecto.
   */
  public function testSinEstimacionSeUsaElPorDefecto(): void {
    $resultado = $this->estimador->pesoUnitario(NULL, NULL);

    $this->assertSame('300', $resultado->getNumber());
  }

  /**
   * Un peso a cero en la variación es lo mismo que no tenerlo.
   *
   * Es el valor que deja el empaquetador de Commerce en las variaciones sin
   * dato, y sin esta comprobación la expedición saldría a cero kilos.
   */
  public function testUnPesoAceroEquivaleANoTenerlo(): void {
    $resultado = $this->estimador->pesoUnitario(new Weight('0', WeightUnit::GRAM), 150);

    $this->assertSame('150', $resultado->getNumber());
  }

  /**
   * El peso de la línea se multiplica por las unidades.
   */
  public function testPesoDeLaLinea(): void {
    $resultado = $this->estimador->pesoLinea(new Weight('120', WeightUnit::GRAM), '3');

    $this->assertSame(360.0, (float) $resultado->convert(WeightUnit::GRAM)->getNumber());
  }

  /**
   * Una línea con estimación y varias unidades suma bien.
   */
  public function testPesoDeLaLineaConEstimacion(): void {
    $resultado = $this->estimador->pesoLinea(NULL, '4', 250);

    $this->assertSame(1000.0, (float) $resultado->convert(WeightUnit::GRAM)->getNumber());
  }

  /**
   * Una cantidad a cero no anula la línea: siempre pesa al menos una unidad.
   */
  public function testUnaCantidadAceroPesaAlMenosUnaUnidad(): void {
    $resultado = $this->estimador->pesoLinea(NULL, '0', 200);

    $this->assertSame(200.0, (float) $resultado->convert(WeightUnit::GRAM)->getNumber());
  }

  /**
   * El envío nunca baja del mínimo, porque la API rechaza el cero.
   */
  public function testElEnvioNuncaBajaDelMinimo(): void {
    $this->assertSame(
      100.0,
      (float) $this->estimador->pesoEnvio(NULL)->convert(WeightUnit::GRAM)->getNumber(),
    );
    $this->assertSame(
      100.0,
      (float) $this->estimador->pesoEnvio(new Weight('0', WeightUnit::GRAM))->convert(WeightUnit::GRAM)->getNumber(),
    );
    $this->assertSame(
      100.0,
      (float) $this->estimador->pesoEnvio(new Weight('40', WeightUnit::GRAM))->convert(WeightUnit::GRAM)->getNumber(),
    );
  }

  /**
   * Un envío por encima del mínimo conserva su peso y su unidad.
   */
  public function testUnEnvioConPesoConservaSuValor(): void {
    $resultado = $this->estimador->pesoEnvio(new Weight('1.4', WeightUnit::KILOGRAM));

    $this->assertSame('1.4', $resultado->getNumber());
    $this->assertSame(WeightUnit::KILOGRAM, $resultado->getUnit());
  }

}
