<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Habla con la API de Correos Express.
 *
 * Solo tres operaciones, que son las que sostienen el despacho diario. La API
 * tiene más, pero no las que se podría esperar: no hay consulta de tarifas y no
 * hay anulación de expediciones. Ninguna de las dos existe, así que no están
 * aquí ni van a estar.
 */
interface CorreosExpressClientInterface {

  /**
   * Da de alta una expedición.
   *
   * @param array<string, mixed> $payload
   *   Cuerpo de la petición, tal como lo construye ConstructorPayloadEnvio.
   *
   * @return \Drupal\pronens_correos_express\Api\RespuestaAlta
   *   Número de expedición y números de bulto.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si faltan credenciales, si falla la red o si la API rechaza el alta.
   */
  public function grabarEnvio(array $payload): RespuestaAlta;

  /**
   * Descarga las etiquetas de una expedición.
   *
   * @param string $expedicion
   *   Número de expedición devuelto por el alta.
   * @param int $tipoEtiqueta
   *   1 para etiqueta térmica o adhesiva, 3 para hoja A4 con tres etiquetas.
   * @param int $posicionEnHoja
   *   Posición de la primera etiqueta en la hoja, contando desde 1. Se ignora
   *   con el tipo 3, que la API exige en la primera posición.
   * @param string $logoBase64
   *   Logotipo del cliente para imprimir en la etiqueta, en base64.
   *
   * @return \Drupal\pronens_correos_express\Api\RespuestaEtiqueta
   *   Las etiquetas en PDF.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si faltan credenciales, si falla la red o si no llega ninguna etiqueta.
   */
  public function obtenerEtiquetas(string $expedicion, int $tipoEtiqueta = 1, int $posicionEnHoja = 1, string $logoBase64 = ''): RespuestaEtiqueta;

  /**
   * Consulta el seguimiento de una expedición.
   *
   * @param string $expedicion
   *   Número de expedición.
   * @param string $idioma
   *   Código de idioma de las descripciones de estado.
   *
   * @return \Drupal\pronens_correos_express\Api\RespuestaSeguimiento
   *   Los eventos del seguimiento.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si faltan credenciales o si falla la red.
   */
  public function seguimientoEnvio(string $expedicion, string $idioma = 'ES'): RespuestaSeguimiento;

}
