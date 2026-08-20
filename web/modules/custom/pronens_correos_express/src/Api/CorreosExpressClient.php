<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Cliente REST de Correos Express.
 *
 * La API es JSON sobre HTTPS, todo por POST, con autenticación HTTP Basic. No
 * hay token ni clave de API.
 *
 * Tres decisiones se apartan a propósito de lo que hace la integración oficial
 * de WooCommerce, que es la única especificación pública de esta API:
 *
 * 1. Hay timeouts. La integración oficial no pone ninguno, y una llamada que se
 *    quedara colgada bloquearía un lote de cuarenta pedidos.
 * 2. Se verifica el certificado. La integración oficial lo desactiva. Si la
 *    cadena de certificación de Correos Express diera problemas, la solución es
 *    instalar la CA, no dejar de comprobarla.
 * 3. La conversión de codificación se hace sobre el cuerpo entero antes de
 *    interpretarlo. La integración oficial convierte solo el mensaje y después
 *    de interpretar el JSON, así que cuando la API responde con acentos en
 *    ISO-8859-1 pierde el mensaje de error completo.
 */
final class CorreosExpressClient implements CorreosExpressClientInterface {

  /**
   * Segundos de espera para establecer la conexión.
   */
  private const TIMEOUT_CONEXION = 5;

  /**
   * Segundos de espera de las operaciones que escriben.
   */
  private const TIMEOUT_ESCRITURA = 30;

  /**
   * Segundos de espera de las operaciones que solo consultan.
   */
  private const TIMEOUT_LECTURA = 15;

  /**
   * Código de retorno que la API usa para decir que todo fue bien.
   */
  private const RETORNO_CORRECTO = '0';

  /**
   * Campos del payload que no se escriben en el log.
   */
  private const CAMPOS_PERSONALES = [
    'nomDest',
    'nifDest',
    'dirDest',
    'contacDest',
    'telefDest',
    'emailDest',
    'telefRte',
    'emailRte',
  ];

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RepositorioCredenciales $repositorioCredenciales,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function grabarEnvio(array $payload): RespuestaAlta {
    $entorno = $this->entorno();

    // Sin reintento a propósito. La API no tiene clave de idempotencia y no
    // permite anular expediciones, así que reintentar un timeout puede acabar
    // en dos envíos reales y facturados por el mismo pedido.
    $datos = $this->peticion(
      $entorno->urlAlta(),
      $payload,
      self::TIMEOUT_ESCRITURA,
      reintentar: FALSE,
    );

    $codigo = $this->codigoRetorno($datos);
    if ($codigo !== self::RETORNO_CORRECTO) {
      $mensaje = $this->mensajeRetorno($datos);
      $this->logger->error('Correos Express rechazó el alta de la expedición con el código @codigo: @mensaje', [
        '@codigo' => $codigo,
        '@mensaje' => $mensaje,
      ]);
      throw CorreosExpressException::negocio($codigo, $mensaje);
    }

    $respuesta = RespuestaAlta::desdeRespuesta($datos);
    if ($respuesta->expedicion === '') {
      throw CorreosExpressException::negocio($codigo, 'La API aceptó el alta pero no devolvió número de expedición.');
    }

    return $respuesta;
  }

  /**
   * {@inheritdoc}
   */
  public function obtenerEtiquetas(string $expedicion, int $tipoEtiqueta = 1, int $posicionEnHoja = 1, string $logoBase64 = '', bool $ocultarRemitente = FALSE, string $textoRemitenteAlternativo = ''): RespuestaEtiqueta {
    $entorno = $this->entorno();
    $credenciales = $this->credenciales();

    // La posición solo existe en los formatos de hoja: la adhesiva de tres por
    // página (tipo 3, posiciones 0 a 2) y el medio folio (tipo 4, 0 o 1). En el
    // resto la API la ignora y se manda 0.
    $posicion = match ($tipoEtiqueta) {
      3 => min(max(0, $posicionEnHoja - 1), 2),
      4 => min(max(0, $posicionEnHoja - 1), 1),
      default => 0,
    };

    $datos = $this->peticion(
      $entorno->urlEtiqueta(),
      [
        'keyCli' => $credenciales->codigoCliente,
        'nenvio' => $expedicion,
        'posicionEtiqueta' => (string) $posicion,
        'tipo' => (string) $tipoEtiqueta,
        'hideSender' => $ocultarRemitente ? '1' : '0',
        'logoCliente' => $logoBase64,
        'textoRemiAlternativo' => $textoRemitenteAlternativo,
        'idioma' => 'ES',
      ],
      self::TIMEOUT_ESCRITURA,
      reintentar: TRUE,
    );

    // La etiqueta usa sus propios nombres para el error, distintos del alta.
    $codigoError = trim((string) ($datos['codErr'] ?? ''));
    if ($codigoError !== '' && $codigoError !== self::RETORNO_CORRECTO) {
      throw CorreosExpressException::negocio($codigoError, trim((string) ($datos['desErr'] ?? '')));
    }
    if (isset($datos['codigoRetorno']) && $this->codigoRetorno($datos) !== self::RETORNO_CORRECTO) {
      throw CorreosExpressException::negocio($this->codigoRetorno($datos), $this->mensajeRetorno($datos));
    }

    $respuesta = RespuestaEtiqueta::desdeRespuesta($datos, $tipoEtiqueta === 2);
    if ($respuesta->estaVacia()) {
      throw CorreosExpressException::negocio(
        $codigoError !== '' ? $codigoError : 'sin código',
        sprintf('Correos Express no devolvió ninguna etiqueta para la expedición %s.', $expedicion),
      );
    }

    return $respuesta;
  }

  /**
   * {@inheritdoc}
   */
  public function seguimientoEnvio(string $expedicion, string $idioma = 'ES'): RespuestaSeguimiento {
    $entorno = $this->entorno();
    $credenciales = $this->credenciales();

    $datos = $this->peticion(
      $entorno->urlSeguimientoEnvio(),
      [
        'codigoCliente' => $credenciales->codigoCliente,
        'dato' => $expedicion,
        'idioma' => $idioma,
      ],
      self::TIMEOUT_LECTURA,
      reintentar: TRUE,
    );

    // El seguimiento no usa codigoRetorno: su error de negocio viaja en el
    // campo "error", con el detalle en "mensajeError". Por ejemplo el 409,
    // "no existe el número de envío para el código cliente".
    if (isset($datos['error']) && is_numeric($datos['error']) && (int) $datos['error'] !== 0) {
      $mensaje = $datos['mensajeError'] ?? '';
      throw CorreosExpressException::negocio(
        (string) (int) $datos['error'],
        is_scalar($mensaje) ? trim((string) $mensaje) : '',
      );
    }

    return RespuestaSeguimiento::desdeRespuesta($datos);
  }

  /**
   * Ejecuta una petición y devuelve la respuesta decodificada.
   *
   * @param string $url
   *   URL de la operación.
   * @param array<string, mixed> $datos
   *   Cuerpo de la petición.
   * @param int $timeout
   *   Segundos de espera de la respuesta.
   * @param bool $reintentar
   *   Si se puede repetir la llamada cuando falla la red. Solo para operaciones
   *   idempotentes.
   *
   * @return array<string, mixed>
   *   Respuesta decodificada.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si faltan credenciales o si la respuesta no se puede interpretar.
   */
  private function peticion(string $url, array $datos, int $timeout, bool $reintentar): array {
    $credenciales = $this->credenciales();
    $this->registrarPeticion($url, $datos);

    $intentos = $reintentar ? 2 : 1;
    $ultimoFallo = NULL;

    for ($intento = 1; $intento <= $intentos; $intento++) {
      try {
        $respuesta = $this->httpClient->request('POST', $url, [
          'auth' => [$credenciales->usuario, $credenciales->contrasena],
          'json' => $datos,
          'headers' => ['Accept' => 'application/json'],
          'connect_timeout' => self::TIMEOUT_CONEXION,
          'timeout' => $timeout,
          // Sin esto Guzzle lanzaría antes de que podamos leer el cuerpo, y la
          // API describe el error en el cuerpo incluso cuando devuelve un 500.
          'http_errors' => FALSE,
          'verify' => TRUE,
        ]);
      }
      catch (GuzzleException $e) {
        $ultimoFallo = CorreosExpressException::red($e->getMessage(), $e);
        continue;
      }

      $estado = $respuesta->getStatusCode();
      $cuerpo = (string) $respuesta->getBody();

      if ($estado !== 200) {
        $this->logger->error('Correos Express respondió con el estado HTTP @estado en @url.', [
          '@estado' => $estado,
          '@url' => $url,
        ]);
        // Un 5xx puede ser pasajero; un 4xx es la petición y no va a mejorar.
        $ultimoFallo = CorreosExpressException::red(sprintf('estado HTTP %d.', $estado));
        if ($estado < 500) {
          throw $ultimoFallo;
        }
        continue;
      }

      return $this->decodificar($cuerpo);
    }

    throw $ultimoFallo ?? CorreosExpressException::red('sin respuesta.');
  }

  /**
   * Interpreta el cuerpo de la respuesta.
   *
   * @return array<string, mixed>
   *   Respuesta decodificada.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si el cuerpo no es un objeto JSON.
   */
  private function decodificar(string $cuerpo): array {
    $datos = json_decode($cuerpo, TRUE);

    if ($datos === NULL && json_last_error() === JSON_ERROR_UTF8) {
      // Correos Express responde en ISO-8859-1 sin declararlo. Hay que
      // convertir el cuerpo entero, porque json_decode rechaza los bytes crudos
      // y sin esto se perdería la respuesta completa, no solo los acentos.
      $convertido = mb_convert_encoding($cuerpo, 'UTF-8', 'ISO-8859-1');
      $datos = json_decode($convertido, TRUE);
    }

    if (!is_array($datos)) {
      throw CorreosExpressException::respuestaIlegible($cuerpo);
    }

    return $datos;
  }

  /**
   * Código de retorno de la respuesta, como cadena.
   *
   * Cadena y no entero porque la API devuelve códigos negativos y compararlos
   * como números invita a escribir un mayor que donde hace falta un igual.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada.
   */
  private function codigoRetorno(array $datos): string {
    $codigo = $datos['codigoRetorno'] ?? NULL;

    return is_scalar($codigo) ? trim((string) $codigo) : 'sin código';
  }

  /**
   * Mensaje de retorno de la respuesta, ya en UTF-8.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada.
   */
  private function mensajeRetorno(array $datos): string {
    $mensaje = $datos['mensajeRetorno'] ?? '';
    if (!is_scalar($mensaje)) {
      return '';
    }
    $mensaje = trim((string) $mensaje);

    // Segunda red: el cuerpo era UTF-8 válido pero el texto viene doblemente
    // codificado, que es el otro caso que se ve en esta API.
    if ($mensaje !== '' && !mb_check_encoding($mensaje, 'UTF-8')) {
      $mensaje = (string) mb_convert_encoding($mensaje, 'UTF-8', 'ISO-8859-1');
    }

    return $mensaje;
  }

  /**
   * Credenciales guardadas, o excepción si faltan.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si no están las tres.
   */
  private function credenciales(): Credenciales {
    $credenciales = $this->repositorioCredenciales->cargar();
    if (!$credenciales->estanCompletas()) {
      throw CorreosExpressException::credencialesIncompletas();
    }

    return $credenciales;
  }

  /**
   * Entorno configurado.
   */
  private function entorno(): Entorno {
    $valor = $this->configFactory->get('pronens_correos_express.settings')->get('entorno');

    return Entorno::desdeConfiguracion(is_string($valor) ? $valor : NULL);
  }

  /**
   * Escribe la petición en el log si el registro detallado está activo.
   *
   * @param string $url
   *   URL de la operación.
   * @param array<string, mixed> $datos
   *   Cuerpo de la petición.
   */
  private function registrarPeticion(string $url, array $datos): void {
    if ($this->configFactory->get('pronens_correos_express.settings')->get('registro_detallado') !== TRUE) {
      return;
    }

    $this->logger->debug('Petición a @url: @payload', [
      '@url' => $url,
      '@payload' => json_encode($this->redactar($datos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
  }

  /**
   * Sustituye los datos personales del payload antes de registrarlo.
   *
   * @param array<string, mixed> $datos
   *   Cuerpo de la petición.
   *
   * @return array<string, mixed>
   *   El mismo cuerpo con los datos personales tapados.
   */
  private function redactar(array $datos): array {
    foreach (self::CAMPOS_PERSONALES as $campo) {
      if (array_key_exists($campo, $datos) && $datos[$campo] !== '') {
        $datos[$campo] = '***';
      }
    }

    return $datos;
  }

}
