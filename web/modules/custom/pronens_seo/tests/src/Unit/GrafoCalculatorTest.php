<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_seo\Unit;

use Drupal\pronens_seo\GrafoCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del enriquecimiento del @graph.
 *
 * @group pronens
 */
#[CoversClass(GrafoCalculator::class)]
final class GrafoCalculatorTest extends TestCase {

  /**
   * Los datos que pasa JsonLdHooks, reducidos a lo imprescindible.
   *
   * @return array<string, mixed>
   *   Ver GrafoCalculator::enriquecer().
   */
  private function datos(): array {
    return [
      'empresa' => ['legalName' => 'Quien Sea', 'foundingDate' => '1984'],
      'base' => 'https://ejemplo.test/',
      'vendedor' => 'https://ejemplo.test/#store',
      'devolucion' => ['@type' => 'MerchantReturnPolicy', '@id' => 'https://ejemplo.test/#dev', 'merchantReturnDays' => 30],
      'devolucionRef' => 'https://ejemplo.test/#dev',
      'envio' => [['@type' => 'OfferShippingDetails']],
      'skus' => ['UNO', 'DOS'],
      'coleccion' => ['numberOfItems' => 9, 'itemListElement' => [['@type' => 'ListItem', 'position' => 1]]],
    ];
  }

  /**
   * La ficha de empresa recibe identidad y política de devolución.
   */
  public function testEmpresaRecibeIdentidadYDevolucion(): void {
    $grafo = [['@type' => 'OnlineStore', 'name' => 'Pronens']];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertSame('Quien Sea', $salida[0]['legalName']);
    self::assertSame('1984', $salida[0]['foundingDate']);
    self::assertSame(30, $salida[0]['hasMerchantReturnPolicy']['merchantReturnDays']);
  }

  /**
   * La tienda es una sola entidad en los cinco idiomas.
   *
   * [site:url] resuelve con el idioma de la página, así que metatag emite
   * …/ca#store en catalán. Sin normalizar, el grafo declara cinco tiendas y
   * el seller de cada Offer (que se forma con la raíz sin prefijo) apunta a
   * un nodo que no está en el documento.
   */
  public function testElIdDeLaTiendaPierdeElPrefijoDeIdioma(): void {
    $grafo = [
      ['@type' => 'OnlineStore', '@id' => 'https://ejemplo.test/ca#store'],
      ['@type' => 'Product', 'offers' => [['price' => '1.00']]],
      [
        '@type' => 'WebSite',
        '@id' => 'https://ejemplo.test/ca#website',
        'publisher' => ['@type' => 'Organization', '@id' => 'https://ejemplo.test/ca#store'],
      ],
    ];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertSame('https://ejemplo.test/#store', $salida[0]['@id']);
    self::assertSame('https://ejemplo.test/#store', $salida[1]['offers'][0]['seller']['@id']);
    self::assertSame('https://ejemplo.test/#store', $salida[2]['publisher']['@id']);
    // El WebSite sí es de cada idioma: tiene su portada y su inLanguage.
    self::assertSame('https://ejemplo.test/ca#website', $salida[2]['@id']);
  }

  /**
   * El fragmento lo decide la configuración, no el código.
   *
   * Si el cliente cambia schema_organization_id en el backoffice, el seller y
   * el publisher tienen que seguirlo sin tocar nada.
   */
  public function testElFragmentoDelIdSaleDeLaConfiguracion(): void {
    $grafo = [
      ['@type' => 'OnlineStore', '@id' => 'https://ejemplo.test/fr#empresa'],
      ['@type' => 'Product', 'offers' => [['price' => '1.00']]],
    ];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertSame('https://ejemplo.test/#empresa', $salida[0]['@id']);
    self::assertSame('https://ejemplo.test/#empresa', $salida[1]['offers'][0]['seller']['@id']);
  }

  /**
   * Sin @id en la ficha de empresa no se inventa ninguno.
   */
  public function testNoInventaUnIdQueMetatagNoPuso(): void {
    $grafo = [['@type' => 'OnlineStore', 'name' => 'Pronens']];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertArrayNotHasKey('@id', $salida[0]);
  }

  /**
   * Las relaciones con otras entidades llegan enteras, no aplanadas.
   *
   * Tanto parentOrganization como employee son objetos anidados (y employee,
   * una lista de ellos): si el enriquecimiento los tocara, el vínculo con la
   * matriz se quedaría en una cadena suelta y ningún grafo de conocimiento lo
   * uniría con la Organization que declara pronens.com.
   */
  public function testCopiaLasRelacionesAnidadasSinTocarlas(): void {
    $datos = $this->datos();
    $datos['empresa']['parentOrganization'] = [
      '@type' => 'WholesaleStore',
      '@id' => 'https://matriz.test/#organization',
      'name' => 'Matriz',
    ];
    $datos['empresa']['employee'] = [
      ['@type' => 'Person', '@id' => 'https://quien.test/#persona', 'jobTitle' => 'CEO'],
    ];
    $grafo = [['@type' => 'OnlineStore', 'name' => 'Pronens']];

    $salida = GrafoCalculator::enriquecer($grafo, $datos);

    self::assertSame($datos['empresa']['parentOrganization'], $salida[0]['parentOrganization']);
    self::assertSame('CEO', $salida[0]['employee'][0]['jobTitle']);
  }

  /**
   * Lo que ya venía de la configuración de metatag no se pisa.
   *
   * Es lo que permite al cliente corregir un dato desde el backoffice sin que
   * el código se lo vuelva a sobreescribir en el siguiente render.
   */
  public function testNoPisaLoQueYaVieneDeLaConfiguracion(): void {
    $grafo = [['@type' => 'OnlineStore', 'legalName' => 'El de la configuración']];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertSame('El de la configuración', $salida[0]['legalName']);
  }

  /**
   * Cada Offer recibe su SKU por posición, más vendedor y envío.
   */
  public function testCadaOfertaRecibeSuSkuEnOrden(): void {
    $grafo = [[
      '@type' => 'Product',
      'offers' => [['price' => '1.00'], ['price' => '2.00']],
    ]];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertSame('UNO', $salida[0]['offers'][0]['sku']);
    self::assertSame('DOS', $salida[0]['offers'][1]['sku']);
    self::assertSame('https://ejemplo.test/#store', $salida[0]['offers'][1]['seller']['@id']);
    self::assertCount(1, $salida[0]['offers'][0]['shippingDetails']);
    self::assertSame('https://ejemplo.test/#dev', $salida[0]['offers'][0]['hasMerchantReturnPolicy']['@id']);
  }

  /**
   * Con una sola variación schema_metatag no pivota y deja un objeto suelto.
   */
  public function testOfertaUnicaSigueSiendoUnObjeto(): void {
    $grafo = [['@type' => 'Product', 'offers' => ['price' => '9.99']]];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertArrayNotHasKey(0, $salida[0]['offers']);
    self::assertSame('UNO', $salida[0]['offers']['sku']);
  }

  /**
   * La página de categoría recibe su ItemList con el total de la categoría.
   */
  public function testColeccionRecibeItemList(): void {
    $grafo = [['@type' => 'CollectionPage']];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertSame('ItemList', $salida[0]['mainEntity']['@type']);
    self::assertSame(9, $salida[0]['mainEntity']['numberOfItems']);
  }

  /**
   * Un @type en array (varios tipos a la vez) también se reconoce.
   */
  public function testReconoceElTipoAunqueVengaEnArray(): void {
    $grafo = [['@type' => ['WebPage', 'CollectionPage']]];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertArrayHasKey('mainEntity', $salida[0]);
  }

  /**
   * Los ListItem se numeran desde donde empieza la página del paginador.
   */
  public function testLosItemsSeNumeranDesdeLaPagina(): void {
    $items = GrafoCalculator::items([
      ['url' => 'https://ejemplo.test/a', 'nombre' => 'A'],
      ['url' => 'https://ejemplo.test/b', 'nombre' => 'B'],
    ], 25);

    self::assertSame(25, $items[0]['position']);
    self::assertSame(26, $items[1]['position']);
    self::assertSame('A', $items[0]['name']);
  }

  /**
   * Un producto sin URL no genera ListItem: dejaría un hueco en las posiciones.
   */
  public function testSeDescartaElProductoSinUrl(): void {
    $items = GrafoCalculator::items([
      ['url' => '', 'nombre' => 'Sin enlace'],
      ['url' => 'https://ejemplo.test/b', 'nombre' => 'B'],
    ]);

    self::assertCount(1, $items);
    self::assertSame(1, $items[0]['position']);
  }

  /**
   * representativeOfPage sale como booleano, no como la cadena "1".
   */
  public function testRepresentativeOfPageEsBooleano(): void {
    $grafo = [[
      '@type' => 'Product',
      'image' => ['@type' => 'ImageObject', 'representativeOfPage' => '1'],
      'offers' => ['price' => '1.00'],
    ]];

    $salida = GrafoCalculator::enriquecer($grafo, $this->datos());

    self::assertTrue($salida[0]['image']['representativeOfPage']);
  }

  /**
   * Un grafo sin nada que enriquecer se devuelve tal cual.
   */
  public function testGrafoSinNodosConocidosNoCambia(): void {
    $grafo = [['@type' => 'BreadcrumbList', 'itemListElement' => []]];

    self::assertSame($grafo, GrafoCalculator::enriquecer($grafo, $this->datos()));
  }

}
