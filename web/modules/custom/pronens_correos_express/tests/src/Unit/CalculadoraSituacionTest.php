<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\pronens_correos_express\Catalogo\CalculadoraSituacion;
use Drupal\pronens_correos_express\Catalogo\SituacionEnvio;
use Drupal\pronens_correos_express\Catalogo\SituacionPedido;
use Drupal\Tests\UnitTestCase;

/**
 * Reglas de la situación que enseña la lista de pedidos.
 *
 * @coversDefaultClass \Drupal\pronens_correos_express\Catalogo\CalculadoraSituacion
 * @group pronens_correos_express
 */
final class CalculadoraSituacionTest extends UnitTestCase {

  /**
   * La calculadora.
   */
  private CalculadoraSituacion $calculadora;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculadora = new CalculadoraSituacion();
  }

  /**
   * Un pedido con un solo envío dice lo que le pasa a ese envío.
   *
   * @param array<string, mixed> $envio
   *   Descripción del envío.
   * @param \Drupal\pronens_correos_express\Catalogo\SituacionPedido $esperada
   *   Situación que se espera.
   *
   * @covers ::calcular
   * @dataProvider proveedorUnEnvio
   */
  public function testUnEnvio(array $envio, SituacionPedido $esperada): void {
    $this->assertSame($esperada, $this->calculadora->calcular('completed', [$envio]));
  }

  /**
   * Casos de un solo envío.
   *
   * @return array<string, array{0: array<string, mixed>, 1: \Drupal\pronens_correos_express\Catalogo\SituacionPedido}>
   *   Envío y situación esperada.
   */
  public static function proveedorUnEnvio(): array {
    return [
      'recién comprado, sin expedir' => [
        ['estado' => 'draft', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
        SituacionPedido::PorExpedir,
      ],
      // Marcar «preparado» a mano no da número de expedición: sigue pendiente.
      'preparado a mano, sin expedición' => [
        ['estado' => 'ready', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
        SituacionPedido::PorExpedir,
      ],
      'expedición dada de alta' => [
        ['estado' => 'ready', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => NULL],
        SituacionPedido::Expedido,
      ],
      'prerregistrado en Correos Express' => [
        [
          'estado' => 'ready',
          'expedido' => TRUE,
          'seExpide' => TRUE,
          'situacion' => SituacionEnvio::Prerregistrado->value,
        ],
        SituacionPedido::Expedido,
      ],
      'recogido por el transportista' => [
        ['estado' => 'shipped', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => SituacionEnvio::EnCurso->value],
        SituacionPedido::Enviado,
      ],
      'entregado al cliente' => [
        ['estado' => 'shipped', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => SituacionEnvio::Entregado->value],
        SituacionPedido::Entregado,
      ],
      'devuelto al remitente' => [
        ['estado' => 'shipped', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => SituacionEnvio::Devuelto->value],
        SituacionPedido::Devuelto,
      ],
      'anulado en Correos Express' => [
        ['estado' => 'shipped', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => SituacionEnvio::Anulado->value],
        SituacionPedido::Cancelado,
      ],
      'envío cancelado' => [
        ['estado' => 'canceled', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
        SituacionPedido::Cancelado,
      ],
      // Recoger en tienda: no hay nada que expedir, así que no puede figurar
      // nunca como trabajo pendiente.
      'recogida en tienda, esperando al cliente' => [
        ['estado' => 'draft', 'expedido' => FALSE, 'seExpide' => FALSE, 'situacion' => NULL],
        SituacionPedido::RecogeEnTienda,
      ],
      'recogida en tienda ya entregada' => [
        ['estado' => 'shipped', 'expedido' => FALSE, 'seExpide' => FALSE, 'situacion' => NULL],
        SituacionPedido::Entregado,
      ],
    ];
  }

  /**
   * Sin envíos no hay nada que preparar.
   *
   * @covers ::calcular
   */
  public function testSinEnvios(): void {
    $this->assertSame(SituacionPedido::SinEnvio, $this->calculadora->calcular('completed', []));
  }

  /**
   * Un pedido anulado lo está aunque su envío diga otra cosa.
   *
   * @covers ::calcular
   */
  public function testPedidoCanceladoMandaSobreElEnvio(): void {
    $envio = [
      'estado' => 'shipped',
      'expedido' => TRUE,
      'seExpide' => TRUE,
      'situacion' => SituacionEnvio::Entregado->value,
    ];

    $this->assertSame(SituacionPedido::Cancelado, $this->calculadora->calcular('canceled', [$envio]));
  }

  /**
   * Con varias cajas manda lo que queda por hacer, no lo más adelantado.
   *
   * @covers ::calcular
   */
  public function testConVariosEnviosMandaElTrabajoPendiente(): void {
    $situacion = $this->calculadora->calcular('completed', [
      ['estado' => 'shipped', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => SituacionEnvio::Entregado->value],
      ['estado' => 'draft', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
    ]);

    $this->assertSame(SituacionPedido::PorExpedir, $situacion);
  }

  /**
   * Una devolución pide atención por encima de todo lo demás.
   *
   * @covers ::calcular
   */
  public function testLaDevolucionManda(): void {
    $situacion = $this->calculadora->calcular('completed', [
      ['estado' => 'draft', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
      ['estado' => 'shipped', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => SituacionEnvio::Devuelto->value],
    ]);

    $this->assertSame(SituacionPedido::Devuelto, $situacion);
  }

  /**
   * Un envío anulado se descarta si queda otro vivo.
   *
   * @covers ::calcular
   */
  public function testElEnvioAnuladoNoTapaAlQueSigueVivo(): void {
    $situacion = $this->calculadora->calcular('completed', [
      ['estado' => 'canceled', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
      ['estado' => 'ready', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => NULL],
    ]);

    $this->assertSame(SituacionPedido::Expedido, $situacion);
  }

  /**
   * Si todos los envíos están anulados, el pedido está cancelado.
   *
   * @covers ::calcular
   */
  public function testTodosLosEnviosAnulados(): void {
    $situacion = $this->calculadora->calcular('completed', [
      ['estado' => 'canceled', 'expedido' => FALSE, 'seExpide' => TRUE, 'situacion' => NULL],
      ['estado' => 'canceled', 'expedido' => TRUE, 'seExpide' => TRUE, 'situacion' => NULL],
    ]);

    $this->assertSame(SituacionPedido::Cancelado, $situacion);
  }

  /**
   * Los valores por omisión describen un envío recién creado.
   *
   * Importa porque quien construye el array puede no tener todas las claves:
   * un envío sin datos es trabajo pendiente, que es el lado seguro.
   *
   * @covers ::calcular
   */
  public function testEnvioSinDatos(): void {
    $this->assertSame(SituacionPedido::PorExpedir, $this->calculadora->calcular('completed', [[]]));
  }

  /**
   * Solo «Por expedir», «Expedido» y «Devuelto» piden trabajo.
   *
   * @covers \Drupal\pronens_correos_express\Catalogo\SituacionPedido::pideTrabajo
   */
  public function testQueSituacionesPidenTrabajo(): void {
    $piden = array_values(array_filter(
      SituacionPedido::cases(),
      static fn (SituacionPedido $situacion): bool => $situacion->pideTrabajo(),
    ));

    $this->assertSame(
      [SituacionPedido::Devuelto, SituacionPedido::PorExpedir, SituacionPedido::Expedido],
      $piden,
    );
  }

}
