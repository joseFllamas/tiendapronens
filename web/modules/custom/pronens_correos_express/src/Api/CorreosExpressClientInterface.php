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
   *   Formato oficial: 1 PDF de 10x15, 2 ZPL para impresora de etiquetas,
   *   3 PDF adhesivo de tres por hoja, 4 PDF de medio folio, 5 PDF térmico.
   * @param int $posicionEnHoja
   *   Posición de la primera etiqueta en la hoja, contando desde 1. Solo
   *   aplica a los tipos 3 y 4; en el resto se ignora.
   * @param string $logoBase64
   *   Logotipo del cliente para imprimir en la etiqueta, en base64.
   * @param bool $ocultarRemitente
   *   Si el remitente no se imprime en la etiqueta.
   * @param string $textoRemitenteAlternativo
   *   Remitente alternativo, que se muestra cuando se oculta el real.
   *
   * @return \Drupal\pronens_correos_express\Api\RespuestaEtiqueta
   *   Las etiquetas, en PDF o en ZPL según el tipo.
   *
   * @throws \Drupal\pronens_correos_express\Api\CorreosExpressException
   *   Si faltan credenciales, si falla la red o si no llega ninguna etiqueta.
   */
  public function obtenerEtiquetas(string $expedicion, int $tipoEtiqueta = 1, int $posicionEnHoja = 1, string $logoBase64 = '', bool $ocultarRemitente = FALSE, string $textoRemitenteAlternativo = ''): RespuestaEtiqueta;

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
