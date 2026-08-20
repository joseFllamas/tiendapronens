<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\pronens_correos_express\Catalogo\ModoEntrega;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del catálogo de productos de Correos Express.
 *
 * Los límites sirven para no gastar una llamada en un envío que la API va a
 * rechazar, así que conviene que estén bien.
 */
#[CoversClass(ServicioCex::class)]
#[CoversClass(ModoEntrega::class)]
#[Group('pronens_correos_express')]
final class ServicioCexTest extends UnitTestCase {

  #[DataProvider('proveedorCodigos')]
  public function testCodigoProducto(ServicioCex $servicio, string $codigo): void {
    $this->assertSame($codigo, $servicio->codigoProducto());
    $this->assertSame($servicio, ServicioCex::desdeCodigo($codigo));
  }

  /**
   * @return array<string, array{\Drupal\pronens_correos_express\Catalogo\ServicioCex, string}>
   *   Los casos de prueba.
   */
  public static function proveedorCodigos(): array {
    return [
      'Paq 10' => [ServicioCex::Paq10, '61'],
      'Paq 14' => [ServicioCex::Paq14, '62'],
      'Paq 24' => [ServicioCex::Paq24, '63'],
      'Paq Empresa 14' => [ServicioCex::PaqEmpresa14, '92'],
      'ePaq 24' => [ServicioCex::Epaq24, '93'],
      'Islas Express' => [ServicioCex::IslasExpress, '26'],
      'Islas Documentación' => [ServicioCex::IslasDocumentacion, '46'],
      'Islas Marítimo' => [ServicioCex::IslasMaritimo, '79'],
      'Internacional Express' => [ServicioCex::InternacionalExpress, '91'],
      'Internacional Estándar' => [ServicioCex::InternacionalEstandar, '90'],
      'Entrega Plus' => [ServicioCex::EntregaPlus, '54'],
      'Campaña' => [ServicioCex::Campana, '27'],
      'Portugal Óptica' => [ServicioCex::PortugalOptica, '73'],
      'Paquetería Óptica' => [ServicioCex::PaqueteriaOptica, '76'],
      'Paq 24 Oficina Elegida' => [ServicioCex::Paq24Oficina, '44'],
      'PaqPunto' => [ServicioCex::Paqpunto, '18'],
      'PaqEcommerce' => [ServicioCex::PaqEcommerce, '24'],
      'Baleares Express' => [ServicioCex::BalearesExpress, '66'],
      'Canarias Express' => [ServicioCex::CanariasExpress, '67'],
      'Canarias Aéreo' => [ServicioCex::CanariasAereo, '68'],
      'Canarias Marítimo' => [ServicioCex::CanariasMaritimo, '69'],
    ];
  }

  public function testLosCodigosNoSeRepiten(): void {
    $codigos = array_map(
      static fn (ServicioCex $servicio): string => $servicio->codigoProducto(),
      ServicioCex::cases(),
    );

    $this->assertSame($codigos, array_unique($codigos), 'Dos productos no pueden compartir código.');
  }

  public function testUnCodigoDesconocidoNoDevuelveNingunProducto(): void {
    $this->assertNull(ServicioCex::desdeCodigo('99'));
    $this->assertNull(ServicioCex::desdeCodigo(''));
  }

  #[DataProvider('proveedorLimites')]
  public function testLimitesDePesoYBultos(ServicioCex $servicio, int $gramos, int $bultos): void {
    $this->assertSame($gramos, $servicio->pesoMaximoGramos());
    $this->assertSame($bultos, $servicio->bultosMaximos());
  }

  /**
   * @return array<string, array{\Drupal\pronens_correos_express\Catalogo\ServicioCex, int, int}>
   *   Los casos de prueba.
   */
  public static function proveedorLimites(): array {
    return [
      'Paq 24 admite 40 kilos y 99 bultos' => [ServicioCex::Paq24, 40_000, 99],
      'la oficina elegida baja a 30 kilos' => [ServicioCex::Paq24Oficina, 30_000, 99],
      'PaqPunto baja a 15 kilos y un bulto' => [ServicioCex::Paqpunto, 15_000, 1],
      'PaqEcommerce igual que PaqPunto' => [ServicioCex::PaqEcommerce, 15_000, 1],
      // Según la hoja oficial de códigos, solo el Estándar es monobulto.
      'el Internacional Estándar va en un solo bulto' => [ServicioCex::InternacionalEstandar, 40_000, 1],
      'el Internacional Express admite varios bultos' => [ServicioCex::InternacionalExpress, 40_000, 99],
    ];
  }

  #[DataProvider('proveedorPaises')]
  public function testAdmitePais(ServicioCex $servicio, string $pais, bool $esperado): void {
    $this->assertSame($esperado, $servicio->admitePais($pais));
  }

  /**
   * @return array<string, array{\Drupal\pronens_correos_express\Catalogo\ServicioCex, string, bool}>
   *   Los casos de prueba.
   */
  public static function proveedorPaises(): array {
    return [
      'Paq 24 llega a España' => [ServicioCex::Paq24, 'ES', TRUE],
      'Paq 24 llega a Portugal' => [ServicioCex::Paq24, 'PT', TRUE],
      'Paq 24 llega a Andorra' => [ServicioCex::Paq24, 'AD', TRUE],
      'Paq 24 no llega a Francia' => [ServicioCex::Paq24, 'FR', FALSE],
      // El producto de Portugal Óptica solo se ofrece a Portugal.
      'Portugal Óptica solo a Portugal' => [ServicioCex::PortugalOptica, 'PT', TRUE],
      'Portugal Óptica no a España' => [ServicioCex::PortugalOptica, 'ES', FALSE],
      // Islas Documentación y Paquetería Óptica no se ofrecen a Portugal.
      'Islas Documentación no a Portugal' => [ServicioCex::IslasDocumentacion, 'PT', FALSE],
      'Islas Documentación sí a España' => [ServicioCex::IslasDocumentacion, 'ES', TRUE],
      'Internacional Estándar llega a Francia' => [ServicioCex::InternacionalEstandar, 'FR', TRUE],
      'Internacional Estándar llega al Reino Unido' => [ServicioCex::InternacionalEstandar, 'GB', TRUE],
      // Fuera de la lista cerrada, aunque sea un destino habitual.
      'Internacional Estándar no llega a Estados Unidos' => [ServicioCex::InternacionalEstandar, 'US', FALSE],
      'Internacional Estándar no se usa dentro de España' => [ServicioCex::InternacionalEstandar, 'ES', FALSE],
      'Internacional Express llega a Estados Unidos' => [ServicioCex::InternacionalExpress, 'US', TRUE],
      'Internacional Express no se usa dentro de España' => [ServicioCex::InternacionalExpress, 'ES', FALSE],
      // La hoja oficial define el Paq 24 como peninsular, Portugal, Andorra,
      // Gibraltar y entre islas.
      'el Paq 24 llega a Gibraltar' => [ServicioCex::Paq24, 'GI', TRUE],
      'Baleares Express es un trayecto español' => [ServicioCex::BalearesExpress, 'ES', TRUE],
      'Baleares Express no sale de España' => [ServicioCex::BalearesExpress, 'PT', FALSE],
      'el país en minúsculas también vale' => [ServicioCex::Paq24, 'es', TRUE],
      'un país vacío no lo admite nadie' => [ServicioCex::Paq24, '', FALSE],
    ];
  }

  #[DataProvider('proveedorModos')]
  public function testModoEntrega(ServicioCex $servicio, ModoEntrega $modo, bool $necesitaSeleccion): void {
    $this->assertSame($modo, $servicio->modoEntrega());
    $this->assertSame($necesitaSeleccion, $servicio->modoEntrega()->necesitaSeleccion());
  }

  /**
   * @return array<string, array{\Drupal\pronens_correos_express\Catalogo\ServicioCex, \Drupal\pronens_correos_express\Catalogo\ModoEntrega, bool}>
   *   Los casos de prueba.
   */
  public static function proveedorModos(): array {
    return [
      'Paq 24 entrega a domicilio' => [ServicioCex::Paq24, ModoEntrega::Domicilio, FALSE],
      'la oficina elegida necesita que el cliente elija' => [
        ServicioCex::Paq24Oficina, ModoEntrega::Oficina, TRUE,
      ],
      'PaqPunto también' => [
        ServicioCex::Paqpunto, ModoEntrega::PuntoConveniencia, TRUE,
      ],
    ];
  }

  public function testLasOpcionesTraenTodosLosProductos(): void {
    $opciones = ServicioCex::opciones();

    $this->assertCount(count(ServicioCex::cases()), $opciones);
    $this->assertSame('Paq 24 (63)', $opciones['paq24']);
  }

  public function testPaisesNacionales(): void {
    $this->assertTrue(ServicioCex::esPaisNacional('ES'));
    $this->assertTrue(ServicioCex::esPaisNacional('pt'));
    $this->assertTrue(ServicioCex::esPaisNacional('AD'));
    $this->assertFalse(ServicioCex::esPaisNacional('FR'));
  }

}
