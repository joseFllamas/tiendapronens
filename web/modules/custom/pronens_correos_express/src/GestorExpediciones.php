<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Api\CorreosExpressClientInterface;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\Api\Entorno;
use Drupal\pronens_correos_express\Api\EstadoSeguimiento;
use Drupal\pronens_correos_express\Api\RespuestaAlta;
use Drupal\pronens_correos_express\Api\RespuestaEtiqueta;
use Drupal\pronens_correos_express\Catalogo\MapaEstados;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\pronens_correos_express\Payload\ConstructorPayloadEnvio;
use Psr\Log\LoggerInterface;

/**
 * Da de alta expediciones, pide etiquetas y sincroniza el seguimiento.
 *
 * Es el único sitio donde se escribe en el envío, y donde se decide qué se
 * puede y qué no se puede repetir.
 */
final class GestorExpediciones {

  /**
   * Claves que este módulo guarda en el campo de datos del envío.
   *
   * Se usa el campo "data", que es un campo base que contrib documenta para
   * esto y que sobrevive a que el pedido se vuelva a empaquetar. El número de
   * expedición se duplica además en "tracking_code", que sí es una columna y
   * por tanto se puede filtrar en una vista: "sin código de seguimiento" es la
   * lista de pedidos pendientes de expedir.
   */
  public const CLAVE_EXPEDICION = 'cex_expedicion';
  public const CLAVE_BULTOS = 'cex_bultos';
  public const CLAVE_PRODUCTO = 'cex_producto';
  public const CLAVE_SERVICIO = 'cex_servicio';
  public const CLAVE_FECHA_ALTA = 'cex_fecha_alta';
  public const CLAVE_ENTORNO = 'cex_entorno';
  public const CLAVE_KILOS = 'cex_kilos';
  public const CLAVE_RECOGIDA = 'cex_recogida';
  public const CLAVE_ULTIMO_ESTADO = 'cex_ultimo_estado';
  public const CLAVE_ULTIMA_CONSULTA = 'cex_ultima_consulta';

  public function __construct(
    private readonly CorreosExpressClientInterface $cliente,
    private readonly MapeadorEnvio $mapeador,
    private readonly ConstructorPayloadEnvio $constructor,
    private readonly MapaEstados $mapaEstados,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Da de alta la expedición de un envío.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si el envío ya tiene expedición, si otra petición lo está procesando o si
   *   la API rechaza el alta.
   */
  public function generar(ShipmentInterface $envio, OpcionesExpedicion $opciones): RespuestaAlta {
    // Dar de alta dos veces son dos envíos reales facturados, y la API no
    // permite anular expediciones. Así que esto no es un aviso, es un cierre.
    $existente = $this->expedicion($envio);
    if ($existente !== NULL) {
      throw new CorreosExpressException(sprintf(
        'Este envío ya tiene la expedición %s. Correos Express no permite anular expediciones, así que no se puede dar de alta otra.',
        $existente,
      ));
    }

    // Dos pestañas abiertas o un lote reintentado no deben duplicar el alta.
    $clave = 'pronens_cex_' . $envio->id();
    if (!$this->lock->acquire($clave, 30)) {
      throw new CorreosExpressException('Ya se está dando de alta la expedición de este envío. Espera unos segundos y recarga.');
    }

    try {
      $datos = $this->mapeador->mapear($envio, $opciones);
      $respuesta = $this->cliente->grabarEnvio($this->constructor->construir($datos));
      $this->guardar($envio, $opciones, $respuesta);
      $this->registrar($envio, 'pronens_cex_expedicion_creada', [
        'expedicion' => $respuesta->expedicion,
        'servicio' => $opciones->servicio->etiqueta(),
        'bultos' => count($respuesta->bultos) ?: $opciones->numeroBultos,
        'kilos' => $this->kilos($envio, $opciones),
        'entorno' => $this->entorno()->value,
      ]);

      return $respuesta;
    }
    catch (CorreosExpressException $e) {
      $this->registrar($envio, 'pronens_cex_expedicion_fallida', [
        'mensaje' => $e->getMensajeRetorno() !== '' ? $e->getMensajeRetorno() : $e->getMessage(),
        'codigo' => $e->getCodigoRetorno() !== '' ? $e->getCodigoRetorno() : 'sin código',
      ]);
      throw $e;
    }
    finally {
      $this->lock->release($clave);
    }
  }

  /**
   * Pide las etiquetas de un envío ya expedido.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si el envío no tiene expedición o si la API no devuelve etiquetas.
   */
  public function etiquetas(ShipmentInterface $envio): RespuestaEtiqueta {
    $expedicion = $this->expedicion($envio);
    if ($expedicion === NULL) {
      throw new CorreosExpressException('Este envío todavía no tiene expedición, así que no hay etiqueta que imprimir.');
    }

    $etiqueta = $this->configFactory->get('pronens_correos_express.settings')->get('etiqueta');

    return $this->cliente->obtenerEtiquetas(
      $expedicion,
      (int) ($etiqueta['tipo'] ?? 1),
      (int) ($etiqueta['posicion'] ?? 1),
      (string) ($etiqueta['logo_base64'] ?? ''),
      (bool) ($etiqueta['ocultar_remitente'] ?? FALSE),
      (string) ($etiqueta['texto_remitente_alternativo'] ?? ''),
    );
  }

  /**
   * Consulta el seguimiento y actualiza el estado del envío.
   *
   * @return \Drupal\pronens_correos_express\Api\EstadoSeguimiento|null
   *   El último evento, o NULL si la API no devolvió ninguno.
   */
  public function sincronizarSeguimiento(ShipmentInterface $envio): ?EstadoSeguimiento {
    $expedicion = $this->expedicion($envio);
    if ($expedicion === NULL) {
      return NULL;
    }

    $seguimiento = $this->cliente->seguimientoEnvio($expedicion);
    $ultimo = $seguimiento->ultimoEstado();

    $envio->setData(self::CLAVE_ULTIMA_CONSULTA, $this->time->getRequestTime());
    if ($ultimo === NULL) {
      $envio->save();
      return NULL;
    }

    $situacion = $this->mapaEstados->situacion($ultimo->codigo);
    $envio->setData(self::CLAVE_ULTIMO_ESTADO, [
      'codigo' => $ultimo->codigo,
      'cod' => $ultimo->codigoTexto,
      'descripcion' => $ultimo->descripcion,
      'situacion' => $situacion->value,
      'fecha' => $ultimo->fecha?->format('Y-m-d H:i:s'),
    ]);

    $transicion = $this->mapaEstados->transicion($situacion, $envio->getState()->getId());
    if ($transicion !== NULL && $envio->getState()->isTransitionAllowed($transicion)) {
      $envio->getState()->applyTransitionById($transicion);
      if ($transicion === 'ship' && $envio->getShippedTime() === NULL) {
        $envio->setShippedTime($ultimo->fecha?->getTimestamp() ?? $this->time->getRequestTime());
      }
    }
    elseif ($transicion !== NULL) {
      // Pasa con una anulación después de marcar el envío como enviado: el
      // workflow no tiene esa transición y no se le va a añadir por esto.
      $this->logger->notice('La expedición @expedicion está @situacion pero el envío @envio no admite la transición @transicion desde @estado.', [
        '@expedicion' => $expedicion,
        '@situacion' => $situacion->value,
        '@envio' => $envio->id(),
        '@transicion' => $transicion,
        '@estado' => $envio->getState()->getId(),
      ]);
    }

    $envio->save();
    $this->registrar($envio, 'pronens_cex_estado_actualizado', [
      'expedicion' => $expedicion,
      'situacion' => $situacion->etiqueta(),
      'descripcion' => $ultimo->descripcion,
    ]);

    return $ultimo;
  }

  /**
   * Número de expedición de un envío, si ya está dado de alta.
   */
  public function expedicion(ShipmentInterface $envio): ?string {
    $valor = $envio->getData(self::CLAVE_EXPEDICION);

    return is_string($valor) && $valor !== '' ? $valor : NULL;
  }

  /**
   * Indica si el envío ya está expedido.
   */
  public function estaExpedido(ShipmentInterface $envio): bool {
    return $this->expedicion($envio) !== NULL;
  }

  /**
   * Indica si ya no hace falta volver a consultar el seguimiento.
   */
  public function seguimientoTerminado(ShipmentInterface $envio): bool {
    $estado = $envio->getData(self::CLAVE_ULTIMO_ESTADO);
    if (!is_array($estado) || !isset($estado['codigo'])) {
      return FALSE;
    }

    return $this->mapaEstados->situacion((int) $estado['codigo'])->esFinal();
  }

  /**
   * Producto de Correos Express que corresponde a un envío.
   *
   * Primero lo que guardó el método de envío al elegirse la tarifa, y si no hay
   * nada, Paq 24, que es el producto estándar a domicilio.
   */
  public function servicioPorDefecto(ShipmentInterface $envio): ServicioCex {
    $guardado = $envio->getData(self::CLAVE_SERVICIO);
    if (is_string($guardado)) {
      $servicio = ServicioCex::tryFrom($guardado);
      if ($servicio !== NULL) {
        return $servicio;
      }
    }

    return ServicioCex::Paq24;
  }

  /**
   * Escribe el resultado del alta en el envío.
   */
  private function guardar(ShipmentInterface $envio, OpcionesExpedicion $opciones, RespuestaAlta $respuesta): void {
    $envio->setData(self::CLAVE_EXPEDICION, $respuesta->expedicion);
    $envio->setData(self::CLAVE_BULTOS, $respuesta->bultos);
    $envio->setData(self::CLAVE_SERVICIO, $opciones->servicio->value);
    $envio->setData(self::CLAVE_PRODUCTO, $opciones->servicio->codigoProducto());
    $envio->setData(self::CLAVE_FECHA_ALTA, $this->time->getRequestTime());
    // Se guarda el entorno porque un número de expedición de preproducción no
    // es real, y seis meses después no hay forma de distinguirlo.
    $envio->setData(self::CLAVE_ENTORNO, $this->entorno()->value);
    $envio->setData(self::CLAVE_KILOS, $this->kilos($envio, $opciones));

    if ($respuesta->tieneRecogida()) {
      $envio->setData(self::CLAVE_RECOGIDA, [
        'numero' => $respuesta->numeroRecogida,
        'fecha' => $respuesta->fechaRecogida,
        'desde' => $respuesta->horaRecogidaDesde,
        'hasta' => $respuesta->horaRecogidaHasta,
      ]);
    }

    // En Correos Express el código de seguimiento es el número de expedición.
    $envio->setTrackingCode($respuesta->expedicion);

    // El envío queda preparado, no enviado: la mercancía sigue en el almacén
    // hasta que el transportista la recoge, y eso lo detecta el seguimiento.
    if ($envio->getState()->getId() === 'draft' && $envio->getState()->isTransitionAllowed('finalize')) {
      $envio->getState()->applyTransitionById('finalize');
    }

    $envio->save();
  }

  /**
   * Kilos declarados, como cadena con dos decimales.
   */
  private function kilos(ShipmentInterface $envio, OpcionesExpedicion $opciones): string {
    $peso = $opciones->pesoTotal ?? $envio->getWeight();
    if ($peso === NULL) {
      return '0.00';
    }

    return number_format((float) $peso->convert(WeightUnit::KILOGRAM)->getNumber(), 2, '.', '');
  }

  /**
   * Entorno configurado.
   */
  private function entorno(): Entorno {
    $valor = $this->configFactory->get('pronens_correos_express.settings')->get('entorno');

    return Entorno::desdeConfiguracion(is_string($valor) ? $valor : NULL);
  }

  /**
   * Anota un evento en la pestaña de actividad del pedido.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $envio
   *   Envío al que se refiere el evento.
   * @param string $plantilla
   *   Identificador de la plantilla de registro.
   * @param array<string, mixed> $parametros
   *   Valores de la plantilla.
   */
  private function registrar(ShipmentInterface $envio, string $plantilla, array $parametros): void {
    $pedido = $envio->getOrder();
    if ($pedido === NULL) {
      return;
    }

    // Defensivo a propósito: el rastro de auditoría es valioso, pero perderlo
    // no puede impedir que una expedición se dé de alta ni, peor, dejarla
    // creada en Correos Express y con la excepción sin capturar en Drupal.
    if (!$this->entityTypeManager->hasDefinition('commerce_log')) {
      return;
    }
    $almacen = $this->entityTypeManager->getStorage('commerce_log');
    if (!method_exists($almacen, 'generate')) {
      return;
    }
    $almacen->generate($pedido, $plantilla, $parametros)->save();
  }

}
