<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\pronens_correos_express\Api\CorreosExpressClient;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\Api\RepositorioCredenciales;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

/**
 * Pruebas del cliente HTTP, sin credenciales y sin tocar la API real.
 *
 * El manejador simulado de Guzzle cubre el comportamiento completo del cliente:
 * los códigos de error de la API, los fallos de red, la política de reintentos
 * y la conversión de codificación.
 */
#[CoversClass(CorreosExpressClient::class)]
#[Group('pronens_correos_express')]
final class CorreosExpressClientTest extends UnitTestCase {

  /**
   * Peticiones que ha hecho el cliente en la prueba en curso.
   *
   * Las rellena el middleware que instala cliente().
   *
   * @var list<\Psr\Http\Message\RequestInterface>
   */
  private array $historial = [];

  public function testAltaCorrecta(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_ok.json'))]);

    $respuesta = $cliente->grabarEnvio(['solicitante' => 'P123456']);

    $this->assertSame('0808000123456789', $respuesta->expedicion);
    $this->assertSame([
      1 => '080800012345678901',
      2 => '080800012345678902',
    ], $respuesta->bultos);
    $this->assertFalse($respuesta->tieneRecogida());
  }

  public function testAltaConRecogidaDevuelveLaFranja(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_ok_con_recogida.json'))]);

    $respuesta = $cliente->grabarEnvio([]);

    $this->assertTrue($respuesta->tieneRecogida());
    $this->assertSame('R0099887766', $respuesta->numeroRecogida);
    // La API la devuelve como 30072026 y aquí ya es una fecha manejable.
    $this->assertSame('2026-07-30', $respuesta->fechaRecogida);
    $this->assertSame('16:00', $respuesta->horaRecogidaDesde);
  }

  /**
   * Un código de retorno negativo es un rechazo, no un aviso.
   */
  public function testUnCodigoDeRetornoNegativoEsUnError(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_error.json'))]);

    try {
      $cliente->grabarEnvio([]);
      $this->fail('Un codigoRetorno distinto de cero debe lanzar excepción.');
    }
    catch (CorreosExpressException $e) {
      $this->assertSame('-3', $e->getCodigoRetorno());
      $this->assertSame('El codigo postal del destinatario no es valido', $e->getMensajeRetorno());
      $this->assertFalse($e->esDeRed(), 'Un rechazo de la API no es un fallo de red y no se reintenta.');
    }
  }

  /**
   * La API responde en ISO-8859-1 sin declararlo.
   *
   * La integración oficial convierte el mensaje después de interpretar el JSON,
   * así que en este caso pierde la respuesta entera. Aquí se convierte el
   * cuerpo antes de interpretarlo y el mensaje llega con sus acentos.
   */
  public function testUnCuerpoEnIso88591SeInterpretaYConservaLosAcentos(): void {
    $json = '{"codigoRetorno":-5,"mensajeRetorno":"La dirección del destinatario está incompleta"}';
    $latin1 = mb_convert_encoding($json, 'ISO-8859-1', 'UTF-8');
    // Comprobación de la premisa: sin la conversión, json_decode no puede.
    $this->assertNull(json_decode($latin1, TRUE));
    $this->assertSame(JSON_ERROR_UTF8, json_last_error());

    $cliente = $this->cliente([new Response(200, [], $latin1)]);

    try {
      $cliente->grabarEnvio([]);
      $this->fail('El alta debe fallar con el código de la API.');
    }
    catch (CorreosExpressException $e) {
      $this->assertSame('-5', $e->getCodigoRetorno());
      $this->assertSame('La dirección del destinatario está incompleta', $e->getMensajeRetorno());
      $this->assertTrue(mb_check_encoding($e->getMensajeRetorno(), 'UTF-8'));
    }
  }

  /**
   * Un cuerpo que no es JSON se reporta como respuesta ilegible.
   */
  public function testUnCuerpoQueNoEsJsonSeReportaComoIlegible(): void {
    $cliente = $this->cliente([new Response(200, [], '<html><body>Service Unavailable</body></html>')]);

    $this->expectException(CorreosExpressException::class);
    $this->expectExceptionMessageMatches('/no se puede interpretar/');
    $cliente->grabarEnvio([]);
  }

  /**
   * Un 500 se reintenta en las operaciones idempotentes.
   */
  public function testUn500SeReintentaEnElSeguimiento(): void {
    $cliente = $this->cliente([
      new Response(500, [], 'error interno'),
      new Response(200, [], $this->fixture('seguimiento_entregado.json')),
    ]);

    $respuesta = $cliente->seguimientoEnvio('0808000123456789');

    $this->assertCount(2, $this->historial, 'Debe haber reintentado una vez.');
    $this->assertSame(12, $respuesta->ultimoEstado()?->codigo);
  }

  /**
   * Un 4xx no se reintenta: la petición no va a mejorar sola.
   */
  public function testUn401NoSeReintenta(): void {
    $cliente = $this->cliente([
      new Response(401, [], 'no autorizado'),
      new Response(200, [], $this->fixture('seguimiento_entregado.json')),
    ]);

    try {
      $cliente->seguimientoEnvio('0808000123456789');
      $this->fail('Un 401 debe lanzar excepción.');
    }
    catch (CorreosExpressException $e) {
      $this->assertTrue($e->esDeRed());
      $this->assertCount(1, $this->historial, 'Un 4xx no se reintenta.');
    }
  }

  /**
   * El alta no se reintenta nunca, aunque falle la red.
   *
   * Es la decisión más importante del cliente: la API no tiene clave de
   * idempotencia y no permite anular expediciones, así que un reintento por
   * timeout puede acabar en dos envíos reales y facturados por el mismo pedido.
   */
  public function testElAltaNoSeReintentaAlFallarLaRed(): void {
    $cliente = $this->cliente([
      new ConnectException('se agotó el tiempo de espera', new Request('POST', 'https://www.test.cexpr.es/')),
      new Response(200, [], $this->fixture('alta_ok.json')),
    ]);

    try {
      $cliente->grabarEnvio([]);
      $this->fail('Un fallo de red en el alta debe lanzar excepción.');
    }
    catch (CorreosExpressException $e) {
      $this->assertTrue($e->esDeRed());
      $this->assertCount(1, $this->historial, 'El alta no se puede repetir: crearía dos envíos facturables.');
    }
  }

  /**
   * El seguimiento sí se reintenta: consultar dos veces no tiene consecuencias.
   */
  public function testElSeguimientoSeReintentaAlFallarLaRed(): void {
    $cliente = $this->cliente([
      new ConnectException('se agotó el tiempo de espera', new Request('POST', 'https://www.test.cexpr.es/')),
      new Response(200, [], $this->fixture('seguimiento_en_curso.json')),
    ]);

    $respuesta = $cliente->seguimientoEnvio('0808000123456789');

    $this->assertCount(2, $this->historial);
    $this->assertSame('63', $respuesta->producto);
  }

  /**
   * Las etiquetas llegan en base64 y salen como PDF.
   */
  public function testLasEtiquetasSeDecodificanComoPdf(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('etiqueta_ok.json'))]);

    $respuesta = $cliente->obtenerEtiquetas('0808000123456789');

    $this->assertCount(1, $respuesta->pdfs);
    $this->assertStringStartsWith('%PDF', $respuesta->pdfs[0]);
  }

  /**
   * La hoja de tres etiquetas siempre empieza en la primera posición.
   */
  public function testLaHojaDeTresEtiquetasFuerzaLaPrimeraPosicion(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('etiqueta_ok.json'))]);

    $cliente->obtenerEtiquetas('0808000123456789', tipoEtiqueta: 3, posicionEnHoja: 4);

    $enviado = $this->ultimoCuerpo();
    $this->assertSame(0, $enviado['posicionEtiqueta']);
    $this->assertSame(3, $enviado['tipo']);
  }

  /**
   * La posición de la interfaz empieza en 1 y la de la API en 0.
   */
  public function testLaPosicionDeLaEtiquetaSeConvierteABaseCero(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('etiqueta_ok.json'))]);

    $cliente->obtenerEtiquetas('0808000123456789', tipoEtiqueta: 1, posicionEnHoja: 3);

    $this->assertSame(2, $this->ultimoCuerpo()['posicionEtiqueta']);
  }

  /**
   * Una respuesta sin etiquetas es un error, no una etiqueta vacía.
   */
  public function testUnaRespuestaSinEtiquetasEsUnError(): void {
    $cliente = $this->cliente([new Response(200, [], '{"codErr":"0","listaEtiquetas":[]}')]);

    $this->expectException(CorreosExpressException::class);
    $this->expectExceptionMessageMatches('/no devolvió ninguna etiqueta/');
    $cliente->obtenerEtiquetas('0808000123456789');
  }

  /**
   * La etiqueta usa sus propios nombres de error, distintos del alta.
   */
  public function testElErrorDeLaEtiquetaUsaCodErr(): void {
    $cliente = $this->cliente([new Response(200, [], '{"codErr":"-10","desErr":"Envio no encontrado","listaEtiquetas":[]}')]);

    try {
      $cliente->obtenerEtiquetas('0000000000000000');
      $this->fail('Un codErr distinto de cero debe lanzar excepción.');
    }
    catch (CorreosExpressException $e) {
      $this->assertSame('-10', $e->getCodigoRetorno());
      $this->assertSame('Envio no encontrado', $e->getMensajeRetorno());
    }
  }

  /**
   * Sin credenciales no se llama a la API.
   */
  public function testSinCredencialesNoSeLlamaALaApi(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_ok.json'))], credenciales: FALSE);

    try {
      $cliente->grabarEnvio([]);
      $this->fail('Sin credenciales debe lanzar excepción.');
    }
    catch (CorreosExpressException $e) {
      $this->assertStringContainsString('Faltan las credenciales', $e->getMessage());
      $this->assertCount(0, $this->historial, 'No debe haber salido ninguna petición.');
    }
  }

  /**
   * El entorno configurado decide a qué host se llama.
   */
  public function testElEntornoDecideElHost(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_ok.json'))]);
    $cliente->grabarEnvio([]);
    $this->assertSame('www.test.cexpr.es', $this->peticion(0)->getUri()->getHost());

    $this->historial = [];
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_ok.json'))], entorno: 'PRO');
    $cliente->grabarEnvio([]);
    $this->assertSame('www.cexpr.es', $this->peticion(0)->getUri()->getHost());
  }

  /**
   * La autenticación es HTTP Basic con el usuario y la contraseña guardados.
   */
  public function testSeAutenticaConBasic(): void {
    $cliente = $this->cliente([new Response(200, [], $this->fixture('alta_ok.json'))]);
    $cliente->grabarEnvio([]);

    $cabecera = $this->peticion(0)->getHeaderLine('Authorization');
    $this->assertSame('Basic ' . base64_encode('usuario:secreto'), $cabecera);
  }

  /**
   * Construye el cliente con un manejador simulado.
   *
   * @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $respuestas
   *   Cola de respuestas o excepciones que devolverá Guzzle.
   * @param bool $credenciales
   *   Si hay credenciales guardadas.
   * @param string $entorno
   *   Entorno configurado.
   */
  private function cliente(array $respuestas, bool $credenciales = TRUE, string $entorno = 'PRE'): CorreosExpressClient {
    $pila = HandlerStack::create(new MockHandler($respuestas));
    // Middleware propio en lugar de Middleware::history(): el de Guzzle recibe
    // el contenedor por referencia con un tipo genérico invariante, que no hay
    // forma de satisfacer con una propiedad tipada. Aquí solo interesan las
    // peticiones, y así quedan tipadas.
    $pila->push(fn (callable $siguiente): callable =>
      function (RequestInterface $peticion, array $opciones) use ($siguiente) {
        $this->historial[] = $peticion;

        return $siguiente($peticion, $opciones);
      }
    );

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn($credenciales ? [
      'codigo_cliente' => '123456',
      'usuario' => 'usuario',
      'contrasena' => 'secreto',
    ] : NULL);

    return new CorreosExpressClient(
      new Client(['handler' => $pila]),
      $this->getConfigFactoryStub([
        'pronens_correos_express.settings' => [
          'entorno' => $entorno,
          'registro_detallado' => FALSE,
        ],
      ]),
      new RepositorioCredenciales($state),
      new NullLogger(),
    );
  }

  /**
   * Petición número N de las que ha hecho el cliente.
   */
  private function peticion(int $indice): RequestInterface {
    $this->assertArrayHasKey($indice, $this->historial);

    return $this->historial[$indice];
  }

  /**
   * Cuerpo de la última petición enviada, decodificado.
   *
   * @return array<string, mixed>
   *   El cuerpo.
   */
  private function ultimoCuerpo(): array {
    $cuerpo = json_decode((string) $this->peticion(count($this->historial) - 1)->getBody(), TRUE);
    $this->assertIsArray($cuerpo);

    return $cuerpo;
  }

  /**
   * Lee una respuesta de referencia.
   */
  private function fixture(string $nombre): string {
    $ruta = __DIR__ . '/../../fixtures/' . $nombre;
    $contenido = file_get_contents($ruta);
    $this->assertIsString($contenido, sprintf('No se pudo leer %s.', $ruta));

    return $contenido;
  }

}
