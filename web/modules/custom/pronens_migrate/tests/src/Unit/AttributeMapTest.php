<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_migrate\Unit;

use Drupal\pronens_migrate\AttributeMap;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del clasificador de valores de variación del Drupal 7.
 */
#[CoversClass(AttributeMap::class)]
#[Group('pronens_migrate')]
final class AttributeMapTest extends UnitTestCase {

  private AttributeMap $map;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->map = new AttributeMap();
  }

  #[DataProvider('proveedorClasificacion')]
  public function testClassify(string $token, ?string $axis, ?string $value): void {
    $resultado = $this->map->classify($token);
    if ($axis === NULL) {
      $this->assertNull($resultado, sprintf('"%s" no debería clasificarse.', $token));
      return;
    }
    $this->assertNotNull($resultado, sprintf('"%s" debería clasificarse.', $token));
    $this->assertSame($axis, $resultado['axis']);
    $this->assertSame($value, $resultado['value']);
  }

  /**
   * Casos reales tomados de los datos del Drupal 7.
   *
   * @return array<string, array{string, string|null, string|null}>
   */
  public static function proveedorClasificacion(): array {
    return [
      // Tallas de bebé por meses: 199 productos del D7.
      'meses abreviado' => ['18M', 'talla', '18 meses'],
      'meses en rango' => ['12-18 meses', 'talla', '18 meses'],
      'serie triple cero' => ['6 months', 'talla', '000 (6 meses)'],
      // Tallas numéricas y su equivalente en inglés.
      'numerica suelta' => ['4', 'talla', '4 (3-4 años)'],
      'rango en ingles' => ['3-4 years', 'talla', '4 (3-4 años)'],
      'espacio doble del D7' => ['2  (2-3 years)', 'talla', '2 (2-3 años)'],
      // Tallas por letra, incluidas las que vienen con espacio delante.
      'letra con espacio' => [' XL', 'talla', '24 / XL'],
      'letra con numero' => ['24-XL', 'talla', '24 / XL'],
      'large en ingles' => ['22-Large', 'talla', '22 / L'],
      'decision del 16' => ['16', 'talla', '16 / XS'],
      // Medidas físicas, que no son tallas.
      'medida de cojin' => ['40 x 40cm', 'medida', '40 x 40 cm'],
      'medida sin espacios' => ['50x70 cm', 'medida', '50 x 70 cm'],
      'etiqueta infantil' => ['6 - 9 años', 'medida', 'Infantil M (6-9 años), 6 x 15 cm'],
      'etiqueta con medida' => [
        'Infantil Large (9-12 años) 8,5 x 17 cm',
        'medida',
        'Infantil L (9-12 años), 8,5 x 17 cm',
      ],
      'etiqueta adulto' => ['+12 años', 'medida', 'Adulto (+12 años), 12 x 18 cm'],
      // Piezas del conjunto de guardería.
      'pieza almuerzo' => ['almuerzo', 'pieza', 'Bolsa de almuerzo'],
      'pieza muda' => ['muda', 'pieza', 'Bolsa de muda'],
      // Formatos de venta.
      'funda sin relleno' => ['sin relleno', 'formato', 'Solo funda'],
      'funda en ingles' => ['Funda cojin (cushion cover only)', 'formato', 'Solo funda'],
      'pack en uds' => ['Pack 10 uds', 'formato', 'Pack de 10'],
      // Colores, deduplicados de los cuatro idiomas del D7.
      'color castellano' => ['Blanco', 'color', 'Blanco'],
      'color catalan' => ['Blanc', 'color', 'Blanco'],
      'color ingles' => ['White', 'color', 'Blanco'],
      'color frances' => ['Jaune', 'color', 'Amarillo'],
      'errata del D7' => ['bkue ducados', 'color', 'Azul ducados'],
      'errata benetton' => ['Verde Benneton', 'color', 'Verde Benetton'],
      'celeste no es azul celeste' => ['Celeste', 'color', 'Celeste'],
      'azul celeste' => ['Azul Celeste', 'color', 'Azul celeste'],
      // Valores que legítimamente no son variaciones.
      'manta es otro producto' => ['Manta', NULL, NULL],
      'cadena vacia' => ['', NULL, NULL],
      'solo espacios' => ['   ', NULL, NULL],
      'desconocido' => ['no existe esto', NULL, NULL],
    ];
  }

  /**
   * @param array<string, string> $esperado
   */
  #[DataProvider('proveedorTitulos')]
  public function testFromTitle(string $title, array $esperado): void {
    $this->assertSame($esperado, $this->map->fromTitle($title));
  }

  /**
   * Títulos reales del Drupal 7.
   *
   * @return array<string, array{string, array<string, string>}>
   */
  public static function proveedorTitulos(): array {
    return [
      'talla y color juntos' => [
        'CHÁNDAL AZUL SIRENITA (2, Azul Celeste)',
        ['talla' => '2 (2-3 años)', 'color' => 'Azul celeste'],
      ],
      'solo color' => [
        'CHÁNDAL PISTACHO ZOMBI (Verde pistacho)',
        ['color' => 'Verde pistacho'],
      ],
      'solo talla en meses' => [
        'Body bebé Vader (18M)',
        ['talla' => '18 meses'],
      ],
      'pieza de conjunto' => [
        'Bolsa guardería impermeable Oso Panda (almuerzo)',
        ['pieza' => 'Bolsa de almuerzo'],
      ],
      'medida de cojin' => [
        'Cojín personalizado (40 x 40cm)',
        ['medida' => '40 x 40 cm'],
      ],
      // Regresión: los títulos del D7 anidan paréntesis y la primera versión
      // extraía "almuerzo)" con el cierre pegado, perdiendo las 224 piezas y
      // las medidas del nivel exterior.
      'parentesis anidados' => [
        'Bolsa mochila impermeable guardería Oso Tribal (Pequeño 25x30cm (almuerzo))',
        ['medida' => 'Pequeño 25 x 30 cm', 'pieza' => 'Bolsa de almuerzo'],
      ],
      // Regresión: partir por coma suelta rompía "8,5 x 17 cm" en un "8" que se
      // clasificaba como talla, metiendo un falso positivo en 33 productos.
      'coma decimal no parte el valor' => [
        'Etiquetas identificativas (Infantil Large (9-12 años) 8,5 x 17 cm)',
        ['medida' => 'Infantil L (9-12 años), 8,5 x 17 cm'],
      ],
      'sin parentesis' => ['Bata escolar personalizada', []],
      'parentesis sin valor util' => ['Manta polar (Manta)', []],
      'parentesis vacio' => ['Producto ()', []],
      // Un paréntesis sin cerrar no debe hacer perder el valor: el D7 tiene
      // títulos escritos a mano y descartar el dato sería peor que leerlo.
      'parentesis mal cerrado' => ['Producto (2', ['talla' => '2 (2-3 años)']],
      'varios parentesis, gana el ultimo' => [
        'Camiseta (algodón) (4)',
        ['talla' => '4 (3-4 años)'],
      ],
    ];
  }

}
