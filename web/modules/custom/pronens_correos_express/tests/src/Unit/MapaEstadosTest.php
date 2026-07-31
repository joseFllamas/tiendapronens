<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\pronens_correos_express\Catalogo\MapaEstados;
use Drupal\pronens_correos_express\Catalogo\SituacionEnvio;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de la traducción del seguimiento al workflow de Commerce.
 */
#[CoversClass(MapaEstados::class)]
#[CoversClass(SituacionEnvio::class)]
#[Group('pronens_correos_express')]
final class MapaEstadosTest extends UnitTestCase {

  /**
   * El mapa de estados bajo prueba.
   */
  private MapaEstados $mapa;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->mapa = new MapaEstados();
  }

  #[DataProvider('proveedorSituaciones')]
  public function testSituacion(int $codigo, SituacionEnvio $esperada): void {
    $this->assertSame($esperada, $this->mapa->situacion($codigo));
  }

  /**
   * @return array<string, array{int, \Drupal\pronens_correos_express\Catalogo\SituacionEnvio}>
   *   Los casos de prueba.
   */
  public static function proveedorSituaciones(): array {
    return [
      'el 1 es sin recepción, o sea prerregistrado' => [1, SituacionEnvio::Prerregistrado],
      'el 12 es entregado' => [12, SituacionEnvio::Entregado],
      'el 13 es anulado' => [13, SituacionEnvio::Anulado],
      'el 14 es anulado' => [14, SituacionEnvio::Anulado],
      'el 15 es anulado' => [15, SituacionEnvio::Anulado],
      'el 16 es anulado' => [16, SituacionEnvio::Anulado],
      'el 19 es anulado' => [19, SituacionEnvio::Anulado],
      'el 31 es anulado' => [31, SituacionEnvio::Anulado],
      'el 17 es devuelto al remitente' => [17, SituacionEnvio::Devuelto],
      // La mayoría de los eventos del seguimiento son movimientos y no tienen
      // código propio: todo lo que no esté en la lista es un envío en curso.
      'un código cualquiera es un envío en curso' => [3, SituacionEnvio::EnCurso],
      'el 25 también' => [25, SituacionEnvio::EnCurso],
      'el cero también' => [0, SituacionEnvio::EnCurso],
    ];
  }

  #[DataProvider('proveedorSituacionesFinales')]
  public function testEsFinal(SituacionEnvio $situacion, bool $esperado): void {
    $this->assertSame($esperado, $situacion->esFinal());
  }

  /**
   * @return array<string, array{\Drupal\pronens_correos_express\Catalogo\SituacionEnvio, bool}>
   *   Los casos de prueba.
   */
  public static function proveedorSituacionesFinales(): array {
    return [
      'entregado no se vuelve a consultar' => [SituacionEnvio::Entregado, TRUE],
      'anulado tampoco' => [SituacionEnvio::Anulado, TRUE],
      'devuelto tampoco' => [SituacionEnvio::Devuelto, TRUE],
      'prerregistrado sí se sigue consultando' => [SituacionEnvio::Prerregistrado, FALSE],
      'en curso también' => [SituacionEnvio::EnCurso, FALSE],
    ];
  }

  #[DataProvider('proveedorTransiciones')]
  public function testTransicion(SituacionEnvio $situacion, string $estadoActual, ?string $esperada): void {
    $this->assertSame($esperada, $this->mapa->transicion($situacion, $estadoActual));
  }

  /**
   * Cada situación cruzada con los cuatro estados del workflow.
   *
   * El workflow shipment_default solo permite "ship" desde ready y "cancel"
   * desde draft o ready, así que la mayoría de las casillas son un NULL con
   * motivo.
   *
   * @return array<string, array{\Drupal\pronens_correos_express\Catalogo\SituacionEnvio, string, string|null}>
   *   Los casos de prueba.
   */
  public static function proveedorTransiciones(): array {
    return [
      // Prerregistrado es donde queda el envío tras el alta.
      'prerregistrado en borrador no cambia nada' => [SituacionEnvio::Prerregistrado, 'draft', NULL],
      'prerregistrado en preparado no cambia nada' => [SituacionEnvio::Prerregistrado, 'ready', NULL],

      // El paquete se mueve: ha salido del almacén.
      'en curso desde preparado lo marca enviado' => [SituacionEnvio::EnCurso, 'ready', 'ship'],
      'en curso desde borrador no puede: ship solo sale de ready' => [SituacionEnvio::EnCurso, 'draft', NULL],
      'en curso desde enviado no repite la transición' => [SituacionEnvio::EnCurso, 'shipped', NULL],

      // Entregado implica que salió, así que si aún no estaba enviado se marca.
      'entregado desde preparado lo marca enviado' => [SituacionEnvio::Entregado, 'ready', 'ship'],
      'entregado desde enviado no cambia nada' => [SituacionEnvio::Entregado, 'shipped', NULL],

      // Cancelar sí sale de borrador.
      'anulado desde borrador lo cancela' => [SituacionEnvio::Anulado, 'draft', 'cancel'],
      'anulado desde preparado lo cancela' => [SituacionEnvio::Anulado, 'ready', 'cancel'],
      // El workflow no tiene transición de enviado a cancelado.
      'anulado desde enviado no se puede cancelar' => [SituacionEnvio::Anulado, 'shipped', NULL],
      'anulado desde cancelado no repite' => [SituacionEnvio::Anulado, 'canceled', NULL],

      // No hay estado de devolución y no se inventa.
      'devuelto no tiene estado en el workflow' => [SituacionEnvio::Devuelto, 'shipped', NULL],
      'devuelto desde preparado tampoco' => [SituacionEnvio::Devuelto, 'ready', NULL],
    ];
  }

}
