<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_seo\Unit;

use Drupal\pronens_seo\AliasCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\pronens_seo\AliasCalculator
 * @group pronens_seo
 */
final class AliasCalculatorTest extends TestCase {

  /**
   * Sin cambio de nombre no hay nada que regenerar.
   */
  public function testSinCambioNoHayIdiomas(): void {
    $nombres = ['es' => 'Bolsas y sacos', 'ca' => 'Bosses i sacs'];
    self::assertSame([], AliasCalculator::idiomasConNombreCambiado($nombres, $nombres));
  }

  /**
   * Solo vuelven los idiomas cuyo nombre ha cambiado de verdad.
   */
  public function testSoloLosIdiomasQueCambian(): void {
    $antes = ['es' => 'Bolsas guardería y escolares', 'ca' => 'Bosses i sacs', 'en' => 'Nursery bags'];
    $despues = ['es' => 'Bolsas y sacos', 'ca' => 'Bosses i sacs ', 'en' => 'Nursery and lunch bags'];
    self::assertSame(['es', 'en'], AliasCalculator::idiomasConNombreCambiado($antes, $despues));
  }

  /**
   * Una traducción nueva también entra: no tenía alias.
   */
  public function testUnaTraduccionNuevaEntra(): void {
    self::assertSame(['fr'], AliasCalculator::idiomasConNombreCambiado(['es' => 'Packs'], ['es' => 'Packs', 'fr' => 'Packs']));
  }

  /**
   * El sufijo del uniquificador se reconoce aunque el crudo esté recortado.
   */
  public function testReconoceElSufijo(): void {
    self::assertTrue(AliasCalculator::tieneSufijoDeUnicidad('/productos/bodys-bebe/body-liso-0', '/productos/bodys-bebe/body-liso'));
    self::assertTrue(AliasCalculator::tieneSufijoDeUnicidad('/productos/bodys-bebe/body-liso-12', '/productos/bodys-bebe/body-liso'));
    // Recortado para que quepa el sufijo.
    self::assertTrue(AliasCalculator::tieneSufijoDeUnicidad('/productos/cojines/cojin-dragon-0', '/productos/cojines/cojin-dragon-ball-mikoshin'));
  }

  /**
   * Un título que acaba en número no es un sufijo.
   */
  public function testUnNumeroDelTituloNoEsSufijo(): void {
    self::assertFalse(AliasCalculator::tieneSufijoDeUnicidad('/productos/cojines/cojin-pop-culture-001', '/productos/cojines/cojin-pop-culture-001'));
    self::assertTrue(AliasCalculator::tieneSufijoDeUnicidad('/productos/cojines/cojin-pop-culture-001-0', '/productos/cojines/cojin-pop-culture-001'));
  }

  /**
   * Otro alias con número al final no cuenta si no sale del crudo.
   */
  public function testOtroAliasNoEsSufijo(): void {
    self::assertFalse(AliasCalculator::tieneSufijoDeUnicidad('/productos/otra-cosa-0', '/productos/bodys-bebe/body-liso'));
    self::assertFalse(AliasCalculator::tieneSufijoDeUnicidad('/productos/body-liso-', '/productos/body-liso'));
    self::assertFalse(AliasCalculator::tieneSufijoDeUnicidad('/productos/body-liso-0', ''));
  }

  /**
   * La escuela entra como segundo tramo, con cualquier prefijo de idioma.
   */
  public function testLaEscuelaEsElSegundoTramo(): void {
    self::assertSame(
      '/productos/uniformes-de-colegio-goar/pantalon-corto',
      AliasCalculator::conTramoDeEscuela('/productos/pantalon-corto', 'uniformes-de-colegio-goar'),
    );
    self::assertSame(
      '/productes/uniformes-de-colegio-goar/pantalons-curts',
      AliasCalculator::conTramoDeEscuela('/productes/pantalons-curts', '/uniformes-de-colegio-goar/'),
    );
  }

  /**
   * Un alias que ya tiene categoría, o que no tiene forma de producto, no se toca.
   */
  public function testSoloLosAliasDeDosTramos(): void {
    self::assertSame('/productos/bodys-bebe/body-liso', AliasCalculator::conTramoDeEscuela('/productos/bodys-bebe/body-liso', 'goar'));
    self::assertSame('/aviso-legal', AliasCalculator::conTramoDeEscuela('/aviso-legal', 'goar'));
    self::assertSame('/productos/pantalon-corto', AliasCalculator::conTramoDeEscuela('/productos/pantalon-corto', ''));
    self::assertSame('/productos/', AliasCalculator::conTramoDeEscuela('/productos/', 'goar'));
  }

}
