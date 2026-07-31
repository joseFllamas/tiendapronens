<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Kernel;

use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Api\CorreosExpressClient;
use Drupal\pronens_correos_express\Api\CorreosExpressClientInterface;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\Api\EstadoSeguimiento;
use Drupal\pronens_correos_express\Api\RespuestaAlta;
use Drupal\pronens_correos_express\Api\RespuestaEtiqueta;
use Drupal\pronens_correos_express\Api\RespuestaSeguimiento;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\pronens_correos_express\GestorExpediciones;
use Drupal\pronens_correos_express\OpcionesExpedicion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del alta de expediciones sobre un pedido real.
 */
#[CoversClass(GestorExpediciones::class)]
#[Group('pronens_correos_express')]
final class GestorExpedicionesTest extends CorreosExpressKernelTestBase {

  /**
   * Doble del cliente HTTP, para no llamar a la API.
   */
  private ClienteFalso $cliente;

  /**
   * Servicio bajo prueba.
   */
  private GestorExpediciones $gestor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cliente = new ClienteFalso();
    // Se sustituyen los dos identificadores: el contenedor resuelve el alias de
    // la interfaz en tiempo de compilación, así que quien inyecta el cliente
    // apunta a la clase concreta y cambiar solo la interfaz no serviría de
    // nada.
    $this->container->set(CorreosExpressClientInterface::class, $this->cliente);
    $this->container->set(CorreosExpressClient::class, $this->cliente);
    $this->gestor = $this->container->get(GestorExpediciones::class);
  }

  /**
   * El alta guarda el número, los bultos y el código de seguimiento.
   */
  public function testElAltaGuardaLosDatosEnElEnvio(): void {
    $envio = $this->crearEnvio();

    $respuesta = $this->gestor->generar($envio, $this->opciones());

    $this->assertSame('0808000123456789', $respuesta->expedicion);
    $this->assertSame('0808000123456789', $this->gestor->expedicion($envio));
    // En Correos Express el código de seguimiento es el número de expedición, y
    // se duplica en la columna para poder filtrar en una vista.
    $this->assertSame('0808000123456789', $envio->getTrackingCode());
    $this->assertSame('paq24', $envio->getData(GestorExpediciones::CLAVE_SERVICIO));
    $this->assertSame('63', $envio->getData(GestorExpediciones::CLAVE_PRODUCTO));
    $this->assertSame([1 => '080800012345678901'], $envio->getData(GestorExpediciones::CLAVE_BULTOS));
    $this->assertSame('1.20', $envio->getData(GestorExpediciones::CLAVE_KILOS));
    // El entorno se guarda porque un número de preproducción no es real.
    $this->assertSame('PRE', $envio->getData(GestorExpediciones::CLAVE_ENTORNO));
    $this->assertIsInt($envio->getData(GestorExpediciones::CLAVE_FECHA_ALTA));
  }

  /**
   * El envío queda preparado, no enviado.
   *
   * La mercancía sigue en el almacén hasta que el transportista la recoge, y
   * eso lo detecta el seguimiento.
   */
  public function testElAltaDejaElEnvioPreparado(): void {
    $envio = $this->crearEnvio();
    $this->assertSame('draft', $envio->getState()->getId());

    $this->gestor->generar($envio, $this->opciones());

    $this->assertSame('ready', $envio->getState()->getId());
    $this->assertNull($envio->getShippedTime());
  }

  /**
   * Un segundo alta se rechaza sin volver a llamar a la API.
   *
   * Es la protección más importante del módulo: la API no permite anular
   * expediciones, así que dar de alta dos veces son dos envíos facturados.
   */
  public function testUnSegundoAltaSeRechaza(): void {
    $envio = $this->crearEnvio();
    $this->gestor->generar($envio, $this->opciones());
    $this->assertSame(1, $this->cliente->altas);

    try {
      $this->gestor->generar($envio, $this->opciones());
      $this->fail('El segundo alta debe lanzar excepción.');
    }
    catch (CorreosExpressException $e) {
      $this->assertStringContainsString('0808000123456789', $e->getMessage());
      $this->assertSame(1, $this->cliente->altas, 'No debe haber una segunda llamada a la API.');
    }
  }

  /**
   * El payload que se manda lleva los datos del pedido.
   */
  public function testElPayloadLlevaLosDatosDelPedido(): void {
    $envio = $this->crearEnvio();
    $this->gestor->generar($envio, $this->opciones());

    $payload = $this->cliente->ultimoPayload;
    $this->assertSame('P123456', $payload['solicitante']);
    $this->assertSame('2026-000123', $payload['ref']);
    $this->assertSame('Mónica Ferrer Puig', $payload['nomDest']);
    $this->assertSame('Carrer del Bruc 145', $payload['dirDest']);
    $this->assertSame('08037', $payload['codPosNacDest']);
    $this->assertSame('ES', $payload['paisISODest']);
    $this->assertSame('monica@example.com', $payload['emailDest']);
    $this->assertSame('63', $payload['producto']);
    $this->assertSame('1.20', $payload['kilos']);
  }

  /**
   * El alta queda registrada en la actividad del pedido.
   */
  public function testElAltaSeRegistraEnElPedido(): void {
    $envio = $this->crearEnvio();
    $this->gestor->generar($envio, $this->opciones());

    $registros = $this->container->get('entity_type.manager')
      ->getStorage('commerce_log')
      ->loadByProperties(['template_id' => 'pronens_cex_expedicion_creada']);

    $this->assertCount(1, $registros);
  }

  /**
   * Un rechazo de la API también se registra, y no deja el envío a medias.
   */
  public function testUnRechazoSeRegistraYNoExpide(): void {
    $envio = $this->crearEnvio();
    $this->cliente->error = CorreosExpressException::negocio('-3', 'El codigo postal no es valido');

    try {
      $this->gestor->generar($envio, $this->opciones());
      $this->fail('Debe propagar el rechazo de la API.');
    }
    catch (CorreosExpressException $e) {
      $this->assertSame('-3', $e->getCodigoRetorno());
    }

    $this->assertFalse($this->gestor->estaExpedido($envio));
    $this->assertSame('draft', $envio->getState()->getId());
    $registros = $this->container->get('entity_type.manager')
      ->getStorage('commerce_log')
      ->loadByProperties(['template_id' => 'pronens_cex_expedicion_fallida']);
    $this->assertCount(1, $registros);
  }

  /**
   * Sin expedición no hay etiqueta que pedir.
   */
  public function testSinExpedicionNoHayEtiqueta(): void {
    $envio = $this->crearEnvio();

    $this->expectException(CorreosExpressException::class);
    $this->expectExceptionMessageMatches('/todavía no tiene expedición/');
    $this->gestor->etiquetas($envio);
  }

  /**
   * El seguimiento en curso marca el envío como enviado.
   */
  public function testElSeguimientoEnCursoMarcaElEnvioComoEnviado(): void {
    $envio = $this->crearEnvio();
    $this->gestor->generar($envio, $this->opciones());
    $this->cliente->seguimiento = new RespuestaSeguimiento([
      new EstadoSeguimiento(3, 'TR', 'EN TRANSITO', new \DateTimeImmutable('2026-07-30 07:30:00')),
    ], '63');

    $ultimo = $this->gestor->sincronizarSeguimiento($envio);

    $this->assertSame(3, $ultimo?->codigo);
    $this->assertSame('shipped', $envio->getState()->getId());
    $this->assertNotNull($envio->getShippedTime());
    $this->assertFalse($this->gestor->seguimientoTerminado($envio));
  }

  /**
   * Un envío entregado deja de consultarse.
   */
  public function testUnEnvioEntregadoDejaDeConsultarse(): void {
    $envio = $this->crearEnvio();
    $this->gestor->generar($envio, $this->opciones());
    $this->cliente->seguimiento = new RespuestaSeguimiento([
      new EstadoSeguimiento(12, 'EN', 'ENTREGADO', new \DateTimeImmutable('2026-07-30 13:12:45')),
    ], '63');

    $this->gestor->sincronizarSeguimiento($envio);

    $this->assertSame('shipped', $envio->getState()->getId());
    $this->assertTrue($this->gestor->seguimientoTerminado($envio));
    $estado = $envio->getData(GestorExpediciones::CLAVE_ULTIMO_ESTADO);
    $this->assertSame('entregado', $estado['situacion']);
  }

  /**
   * Una anulación después de enviar no rompe nada.
   *
   * El workflow no tiene transición de enviado a cancelado, así que la
   * situación se guarda y el estado se queda como está.
   */
  public function testUnaAnulacionDespuesDeEnviarNoRompeNada(): void {
    $envio = $this->crearEnvio();
    $this->gestor->generar($envio, $this->opciones());
    $envio->getState()->applyTransitionById('ship');
    $envio->save();

    $this->cliente->seguimiento = new RespuestaSeguimiento([
      new EstadoSeguimiento(31, 'AN', 'ANULADO', new \DateTimeImmutable('2026-07-31 09:00:00')),
    ], '63');
    $this->gestor->sincronizarSeguimiento($envio);

    $this->assertSame('shipped', $envio->getState()->getId());
    $estado = $envio->getData(GestorExpediciones::CLAVE_ULTIMO_ESTADO);
    $this->assertSame('anulado', $estado['situacion']);
    $this->assertTrue($this->gestor->seguimientoTerminado($envio));
  }

  /**
   * Opciones de referencia: Paq 24, un bulto y 1,2 kg.
   */
  private function opciones(): OpcionesExpedicion {
    return new OpcionesExpedicion(
      servicio: ServicioCex::Paq24,
      numeroBultos: 1,
      pesoTotal: new Weight('1.2', WeightUnit::KILOGRAM),
      observaciones: 'Timbre 3r 2a',
    );
  }

}

/**
 * Cliente de Correos Express que no llama a nada.
 */
final class ClienteFalso implements CorreosExpressClientInterface {

  /**
   * Cuántas veces se ha pedido un alta.
   */
  public int $altas = 0;

  /**
   * Último payload recibido.
   *
   * @var array<string, mixed>
   */
  public array $ultimoPayload = [];

  /**
   * Error que debe lanzar el alta, si se quiere probar un rechazo.
   */
  public ?CorreosExpressException $error = NULL;

  /**
   * Respuesta que devuelve el seguimiento.
   */
  public ?RespuestaSeguimiento $seguimiento = NULL;

  /**
   * {@inheritdoc}
   */
  public function grabarEnvio(array $payload): RespuestaAlta {
    $this->altas++;
    $this->ultimoPayload = $payload;
    if ($this->error !== NULL) {
      throw $this->error;
    }

    return new RespuestaAlta(
      '0808000123456789',
      [1 => '080800012345678901'],
      'Envio grabado correctamente',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function obtenerEtiquetas(string $expedicion, int $tipoEtiqueta = 1, int $posicionEnHoja = 1, string $logoBase64 = ''): RespuestaEtiqueta {
    return new RespuestaEtiqueta(["%PDF-1.4\n%%EOF\n"]);
  }

  /**
   * {@inheritdoc}
   */
  public function seguimientoEnvio(string $expedicion, string $idioma = 'ES'): RespuestaSeguimiento {
    return $this->seguimiento ?? new RespuestaSeguimiento([], NULL);
  }

}
