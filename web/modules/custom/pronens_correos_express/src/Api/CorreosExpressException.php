<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Fallo al hablar con Correos Express.
 *
 * Distingue tres familias, porque el operario necesita saber cuál es: no hay
 * credenciales, la red falló, o la API contestó rechazando la petición. Solo la
 * última trae código y mensaje de la API.
 */
final class CorreosExpressException extends \RuntimeException {

  /**
   * Código de la API cuando el fallo es de negocio.
   */
  private string $codigoRetorno = '';

  /**
   * Mensaje literal de la API, ya convertido a UTF-8.
   */
  private string $mensajeRetorno = '';

  /**
   * Indica si el fallo es de red o de la infraestructura, no de la petición.
   */
  private bool $esDeRed = FALSE;

  /**
   * Código de negocio devuelto por la API.
   *
   * Puede venir negativo, así que se trata como cadena y nunca se compara con
   * un mayor o menor que.
   */
  public function getCodigoRetorno(): string {
    return $this->codigoRetorno;
  }

  /**
   * Mensaje devuelto por la API.
   */
  public function getMensajeRetorno(): string {
    return $this->mensajeRetorno;
  }

  /**
   * Indica si merece la pena reintentar más tarde.
   */
  public function esDeRed(): bool {
    return $this->esDeRed;
  }

  /**
   * No hay credenciales guardadas.
   */
  public static function credencialesIncompletas(): self {
    return new self('Faltan las credenciales de Correos Express. Rellénalas en /admin/commerce/config/correos-express/credenciales.');
  }

  /**
   * La petición no llegó a completarse.
   */
  public static function red(string $detalle, ?\Throwable $anterior = NULL): self {
    $excepcion = new self('No se pudo contactar con Correos Express: ' . $detalle, 0, $anterior);
    $excepcion->esDeRed = TRUE;

    return $excepcion;
  }

  /**
   * La respuesta no es un JSON que se pueda interpretar.
   */
  public static function respuestaIlegible(string $cuerpo): self {
    $excepcion = new self('Correos Express devolvió una respuesta que no se puede interpretar: ' . mb_substr($cuerpo, 0, 300));
    $excepcion->esDeRed = TRUE;

    return $excepcion;
  }

  /**
   * La API rechazó la petición.
   */
  public static function negocio(string $codigo, string $mensaje): self {
    $texto = $mensaje !== ''
      ? sprintf('Correos Express rechazó la petición (%s): %s', $codigo, $mensaje)
      : sprintf('Correos Express rechazó la petición con el código %s y sin mensaje.', $codigo);

    $excepcion = new self($texto);
    $excepcion->codigoRetorno = $codigo;
    $excepcion->mensajeRetorno = $mensaje;

    return $excepcion;
  }

}
