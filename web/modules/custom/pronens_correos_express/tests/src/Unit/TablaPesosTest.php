<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\pronens_correos_express\Peso\TablaPesos;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de la estimación de peso por tipo de producto.
 */
#[CoversClass(TablaPesos::class)]
#[Group('pronens_correos_express')]
final class TablaPesosTest extends UnitTestCase {

  /**
   * Pesos configurados de ejemplo, con los identificadores reales del sitio.
   *
   * @var array<int, int>
   */
  private const POR_CATEGORIA = [
    // Bolsas guardería y escolares.
    182 => 120,
    // Batas Babis Escolares.
    176 => 300,
    // Mochilas infantiles y escolares.
    179 => 400,
    // Prendas sanitarias.
    175 => 220,
    // Colchonetas, márfegas y sábanas.
    193 => 700,
  ];

  #[DataProvider('proveedorGramos')]
  public function testGramos(?int $termino, ?string $talla, int $esperado): void {
    $tabla = new TablaPesos(self::POR_CATEGORIA, 300);

    $this->assertSame($esperado, $tabla->gramos($termino, $talla));
  }

  /**
   * @return array<string, array{int|null, string|null, int}>
   *   Los casos de prueba.
   */
  public static function proveedorGramos(): array {
    return [
      'una bolsa pesa lo configurado' => [182, NULL, 120],
      'una mochila pesa lo configurado' => [179, NULL, 400],
      // Hay 33 productos sin categoría, así que este caso es real.
      'un producto sin categoría cae al peso por defecto' => [NULL, NULL, 300],
      'una categoría sin peso configurado cae al peso por defecto' => [177, NULL, 300],
      // Etiquetas reales del catálogo, no valores canónicos.
      'una talla de bebé de 3 meses baja el peso' => [182, '3 meses', 72],
      'una talla de bebé de 18 meses lo baja menos' => [182, '18 meses', 96],
      'la talla infantil 4 pesa algo menos' => [176, '4 (3-4 años)', 270],
      'la talla infantil 14 pesa más' => [176, '14 (13-14 años)', 345],
      'una talla de adulto sube el peso' => [175, '22 / L', 286],
      'la talla de adulto más grande sube más' => [175, '26 / XXL', 319],
      'la etiqueta descriptiva de adulto también sube' => [179, 'Adulto (7-99 años), 20 litros', 520],
      'la etiqueta descriptiva infantil no cambia nada' => [179, 'Infantil (0-6 años), 9 litros', 400],
      'una talla que no se reconoce no altera el peso' => [179, 'única', 400],
      'la talla con espacios también se reconoce' => [179, '  22 / L  ', 520],
    ];
  }

  /**
   * Todas las tallas reales del catálogo dan un multiplicador razonable.
   *
   * Si alguna cae fuera del rango es que el reconocimiento de etiquetas se ha
   * roto y los pesos empezarían a desviarse en silencio.
   */
  public function testTodasLasTallasRealesSeReconocen(): void {
    $tabla = new TablaPesos([], 300);
    $tallas = [
      '3 meses', '6 meses', '9 meses', '12 meses', '18 meses',
      '000 (6 meses)', '00 (8 meses)',
      '0 (0-1 años)', '2 (2-3 años)', '4 (3-4 años)', '6 (5-6 años)',
      '8 (7-8 años)', '10 (9-10 años)', '12 (11-12 años)', '14 (13-14 años)',
      '16 / XS', '18 / S', '20 / M', '22 / L', '24 / XL', '26 / XXL',
      'Infantil (0-6 años), 9 litros', 'Adulto (7-99 años), 20 litros',
    ];

    foreach ($tallas as $talla) {
      $multiplicador = $tabla->multiplicador($talla);
      $this->assertGreaterThanOrEqual(0.6, $multiplicador, sprintf('La talla "%s" da un peso demasiado bajo.', $talla));
      $this->assertLessThanOrEqual(1.45, $multiplicador, sprintf('La talla "%s" da un peso demasiado alto.', $talla));
    }
  }

  /**
   * El peso crece con la talla, de bebé a infantil y de infantil a adulto.
   */
  public function testElOrdenDeLasTallasEsCoherente(): void {
    $tabla = new TablaPesos([], 300);

    $this->assertLessThan($tabla->multiplicador('4 (3-4 años)'), $tabla->multiplicador('3 meses'));
    $this->assertLessThan($tabla->multiplicador('14 (13-14 años)'), $tabla->multiplicador('4 (3-4 años)'));
    $this->assertLessThan($tabla->multiplicador('22 / L'), $tabla->multiplicador('14 (13-14 años)'));
    $this->assertLessThan($tabla->multiplicador('26 / XXL'), $tabla->multiplicador('22 / L'));
  }

  /**
   * Sin talla no hay ajuste.
   */
  public function testSinTallaNoHayAjuste(): void {
    $tabla = new TablaPesos([], 300);

    $this->assertSame(1.0, $tabla->multiplicador(NULL));
    $this->assertSame(1.0, $tabla->multiplicador(''));
    $this->assertSame(1.0, $tabla->multiplicador('   '));
  }

  /**
   * Un peso a cero en la configuración no vale como estimación.
   */
  public function testUnPesoAceroCaeAlPorDefecto(): void {
    $tabla = new TablaPesos([182 => 0], 250);

    $this->assertSame(250, $tabla->gramos(182));
    $this->assertFalse($tabla->tieneEstimacion(182));
  }

  /**
   * Nunca se devuelve un peso a cero, porque la API lo rechazaría.
   */
  public function testElPesoNuncaEsCero(): void {
    $tabla = new TablaPesos([], 0);

    $this->assertGreaterThan(0, $tabla->gramos(NULL));
    $this->assertGreaterThan(0, $tabla->gramos(182, '0-3 meses'));
  }

  public function testTieneEstimacion(): void {
    $tabla = new TablaPesos(self::POR_CATEGORIA, 300);

    $this->assertTrue($tabla->tieneEstimacion(182));
    $this->assertFalse($tabla->tieneEstimacion(177));
    $this->assertFalse($tabla->tieneEstimacion(NULL));
  }

  /**
   * La semilla cubre las categorías con productos.
   */
  public function testLaSemillaCubreLasCategoriasConProductos(): void {
    // Las cuatro con más productos del catálogo, que entre ellas suman más de
    // doscientos.
    foreach ([
      'Bolsas guardería y escolares',
      'Baberos bebé',
      'Bodys bebé',
      'Cojines divertidos',
      'Colchonetas Márfegas y Sábanas Ajustables',
    ] as $nombre) {
      $this->assertIsInt(TablaPesos::semilla($nombre), sprintf('Falta la semilla de "%s".', $nombre));
    }
  }

  /**
   * Un nombre que no está en la semilla devuelve NULL, no un peso inventado.
   */
  public function testUnNombreDesconocidoNoTieneSemilla(): void {
    $this->assertNull(TablaPesos::semilla('Categoría que no existe'));
  }

}
