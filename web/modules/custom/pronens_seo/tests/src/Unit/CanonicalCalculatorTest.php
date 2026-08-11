<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_seo\Unit;

use Drupal\pronens_seo\CanonicalCalculator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de las reglas de canónica y robots del catálogo.
 */
#[CoversClass(CanonicalCalculator::class)]
#[Group('pronens_seo')]
final class CanonicalCalculatorTest extends UnitTestCase {

  /**
   * El calculador que se prueba.
   */
  private CanonicalCalculator $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new CanonicalCalculator();
  }

  /**
   * Sin parámetros, la canónica es la URL limpia y no se toca robots.
   */
  public function testSinParametros(): void {
    $decision = $this->calculator->decide([]);

    $this->assertNull($decision->pagina);
    $this->assertNull($decision->robots);
    $this->assertSame([], $decision->queryCanonica());
    $this->assertNull($decision->numeroVisible());
  }

  /**
   * El page=0 es la primera página: canónica limpia, sin ?page=0.
   */
  public function testPrimeraPaginaExplicita(): void {
    $decision = $this->calculator->decide(['page' => '0']);

    $this->assertNull($decision->pagina);
    $this->assertSame([], $decision->queryCanonica());
  }

  /**
   * A partir de la segunda, cada página es canónica de sí misma.
   */
  public function testPaginaSiguienteEsCanonicaDeSiMisma(): void {
    $decision = $this->calculator->decide(['page' => '1']);

    $this->assertSame(1, $decision->pagina);
    $this->assertSame(['page' => 1], $decision->queryCanonica());
    // El paginador la pinta como la 2.
    $this->assertSame(2, $decision->numeroVisible());
    $this->assertNull($decision->robots);
  }

  /**
   * Los filtros no se indexan, pero sí se siguen: "follow", no "nofollow".
   */
  public function testFacetaSacaLaPaginaDelIndice(): void {
    $decision = $this->calculator->decide(['f' => ['talla:16']]);

    $this->assertSame(CanonicalCalculator::ROBOTS_FILTRADO, $decision->robots);
    $this->assertStringContainsString('follow', (string) $decision->robots);
    $this->assertStringNotContainsString('nofollow', (string) $decision->robots);
    $this->assertSame([], $decision->queryCanonica());
  }

  /**
   * Con faceta y página, manda la faceta: canónica limpia y sin page.
   */
  public function testFacetaConPaginaCanonicalizaAlTerminoLimpio(): void {
    $decision = $this->calculator->decide(['f' => ['talla:16'], 'page' => '2']);

    $this->assertNull($decision->pagina);
    $this->assertSame([], $decision->queryCanonica());
    $this->assertSame(CanonicalCalculator::ROBOTS_FILTRADO, $decision->robots);
  }

  /**
   * El orden expuesto ordena el mismo contenido: tampoco se indexa.
   */
  public function testOrdenSacaLaPaginaDelIndice(): void {
    $decision = $this->calculator->decide(['sort_by' => 'precio_asc']);

    $this->assertNull($decision->pagina);
    $this->assertSame(CanonicalCalculator::ROBOTS_FILTRADO, $decision->robots);
  }

  /**
   * Un f[] vacío lo deja facets al quitar el último filtro: no es un filtro.
   *
   * @param array<string, mixed> $query
   *   Parámetros de consulta de la petición.
   */
  #[DataProvider('proveedorSinFiltroReal')]
  public function testParametrosVaciosNoCuentanComoFiltro(array $query): void {
    $decision = $this->calculator->decide($query);

    $this->assertNull($decision->robots);
  }

  /**
   * @return array<string, array{array<string, mixed>}>
   *   Casos de parámetros que no filtran nada.
   */
  public static function proveedorSinFiltroReal(): array {
    return [
      'f vacío' => [['f' => []]],
      'f con valores vacíos' => [['f' => ['', '  ']]],
      'sort_by vacío' => [['sort_by' => '']],
      'sort_by con espacios' => [['sort_by' => '   ']],
      'f no es array' => [['f' => 'talla:16']],
    ];
  }

  /**
   * El paginador normaliza: "?page=01" no puede ser otra URL canónica.
   */
  #[DataProvider('proveedorPagina')]
  public function testNormalizacionDePagina(mixed $valor, ?int $esperada): void {
    $decision = $this->calculator->decide(['page' => $valor]);

    $this->assertSame($esperada, $decision->pagina);
  }

  /**
   * @return array<string, array{mixed, int|null}>
   *   Casos de valor del parámetro page y página esperada.
   */
  public static function proveedorPagina(): array {
    return [
      'cero a la izquierda' => ['01', 1],
      'entero' => [3, 3],
      // Con varios paginadores Drupal serializa "page=0,2"; esta view tiene
      // uno solo, así que manda la primera posición.
      'varios paginadores' => ['2,5', 2],
      'negativa' => ['-3', NULL],
      'basura' => ['abc', NULL],
      'vacía' => ['', NULL],
      'array' => [['1'], NULL],
      'nula' => [NULL, NULL],
    ];
  }

}
