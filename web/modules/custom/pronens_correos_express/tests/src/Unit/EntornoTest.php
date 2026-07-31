<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\pronens_correos_express\Api\Entorno;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de las URLs de la API según el entorno.
 */
#[CoversClass(Entorno::class)]
#[Group('pronens_correos_express')]
final class EntornoTest extends UnitTestCase {

  public function testProduccionApuntaAlHostReal(): void {
    $this->assertSame('www.cexpr.es', Entorno::Pro->host());
    $this->assertSame(
      'https://www.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio',
      Entorno::Pro->urlAlta(),
    );
    $this->assertTrue(Entorno::Pro->esProduccion());
  }

  public function testPreproduccionApuntaAlHostDePruebas(): void {
    $this->assertSame('www.test.cexpr.es', Entorno::Pre->host());
    $this->assertSame(
      'https://www.test.cexpr.es/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio',
      Entorno::Pre->urlAlta(),
    );
    $this->assertFalse(Entorno::Pre->esProduccion());
  }

  /**
   * Las operaciones de recogida cuelgan de /wsps/ y no de /wspsc/.
   *
   * Es el detalle que se cuela al transcribir esta API, y una ruta equivocada
   * da un 404 que parece un problema de credenciales.
   */
  public function testAnularRecogidaUsaLaRutaCorta(): void {
    foreach ([Entorno::Pre, Entorno::Pro] as $entorno) {
      $url = $entorno->urlAnularRecogida();
      $this->assertStringContainsString('/wsps/', $url);
      $this->assertStringNotContainsString('/wspsc/', $url);
    }
  }

  /**
   * El listado de oficinas solo existe en producción.
   *
   * Por eso la entrega en oficina elegida no se puede probar en preproducción,
   * y por eso esa modalidad no entra en la primera entrega.
   */
  public function testElListadoDeOficinasNoExisteEnPreproduccion(): void {
    $this->assertNull(Entorno::Pre->urlOficinas());
    $this->assertNotNull(Entorno::Pro->urlOficinas());
  }

  public function testTodasLasUrlsSonHttps(): void {
    foreach ([Entorno::Pre, Entorno::Pro] as $entorno) {
      $urls = array_filter([
        $entorno->urlAlta(),
        $entorno->urlEtiqueta(),
        $entorno->urlSeguimientoEnvio(),
        $entorno->urlAnularRecogida(),
        $entorno->urlSeguimientoRecogida(),
        $entorno->urlOficinas(),
        $entorno->urlPudo(),
      ]);
      foreach ($urls as $url) {
        $this->assertStringStartsWith('https://' . $entorno->host() . '/', $url);
      }
    }
  }

  /**
   * Ante una configuración corrupta se cae a preproducción, no a producción.
   */
  public function testUnaConfiguracionInvalidaCaeAPreproduccion(): void {
    $this->assertSame(Entorno::Pre, Entorno::desdeConfiguracion(NULL));
    $this->assertSame(Entorno::Pre, Entorno::desdeConfiguracion(''));
    $this->assertSame(Entorno::Pre, Entorno::desdeConfiguracion('produccion'));
    $this->assertSame(Entorno::Pro, Entorno::desdeConfiguracion('PRO'));
  }

}
