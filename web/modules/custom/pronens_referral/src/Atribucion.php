<?php

declare(strict_types=1);

namespace Drupal\pronens_referral;

/**
 * Reglas de la atribución, sin Drupal alrededor.
 *
 * @todo lo que entra aquí viene del navegador (la cookie la escribe el JS del
 * visitante, que puede editarla) o de un cupón que alguien teclea, así que se
 * trata como dato sucio: se sanea, se acota y se compara sin distinguir
 * mayúsculas. Es lógica pura para poder probarla sin levantar el sitio.
 */
final class Atribucion {

  /**
   * Longitud máxima de la colocación y de la creatividad.
   *
   * El contrato de educoland dice «texto libre corto»: 64 basta para
   * `email_lead_centro` o `banner12-s31183` con margen, y pone un techo a lo
   * que se puede colar en un campo del pedido.
   */
  public const MAX_LONGITUD = 64;

  /**
   * Longitud máxima de la fuente.
   */
  public const MAX_FUENTE = 32;

  public const METODO_COOKIE = 'cookie';
  public const METODO_CUPON = 'cupon';
  public const METODO_AMBOS = 'cookie+cupon';

  /**
   * Deja un valor de UTM en lo que admite el campo del pedido.
   *
   * Minúsculas y `[a-z0-9_-]`: el contrato de educoland solo emite eso, así que
   * cualquier otra cosa es ruido (o alguien probando). No se sustituye por
   * guiones, se BORRA: convertir «ficha publica» en «ficha-publica» inventaría
   * una colocación que no existe y ensuciaría el informe con una campaña nueva.
   *
   * @param mixed $valor
   *   El valor tal cual llega del navegador.
   * @param int $max
   *   Longitud máxima del resultado.
   *
   * @return string
   *   El valor saneado, o cadena vacía si no queda nada aprovechable.
   */
  public static function sanear(mixed $valor, int $max = self::MAX_LONGITUD): string {
    if (!is_string($valor) && !is_int($valor)) {
      return '';
    }
    $limpio = preg_replace('/[^a-z0-9_-]/', '', mb_strtolower((string) $valor));
    return mb_substr((string) $limpio, 0, $max);
  }

  /**
   * Dice si un código de cupón es de los que cuentan como del partner.
   *
   * Dos formas, y las dos configurables: el prefijo del B2B por centro
   * (`EDUCO-12345678`) y la lista de códigos públicos, que educoland rota. Se
   * compara sin distinguir mayúsculas porque Commerce guarda el código tal cual
   * lo escribió quien lo creó y el cliente lo teclea como quiere.
   *
   * @param string $codigo
   *   El código del cupón aplicado al pedido.
   * @param string $prefijo
   *   Prefijo del B2B, vacío para no usar ninguno.
   * @param array<int, string> $codigos
   *   Lista de códigos sueltos del convenio.
   */
  public static function esCuponDePartner(string $codigo, string $prefijo, array $codigos): bool {
    $codigo = mb_strtolower(trim($codigo));
    if ($codigo === '') {
      return FALSE;
    }
    $prefijo = mb_strtolower(trim($prefijo));
    if ($prefijo !== '' && str_starts_with($codigo, $prefijo)) {
      return TRUE;
    }
    foreach ($codigos as $suelto) {
      if (mb_strtolower(trim((string) $suelto)) === $codigo) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * El método con el que se ha reconocido el pedido.
   *
   * @return string|null
   *   Uno de los tres métodos, o NULL si el pedido no es del partner y por
   *   tanto no hay que tocarlo.
   */
  public static function metodo(bool $porCookie, bool $porCupon): ?string {
    return match (TRUE) {
      $porCookie && $porCupon => self::METODO_AMBOS,
      $porCookie => self::METODO_COOKIE,
      $porCupon => self::METODO_CUPON,
      default => NULL,
    };
  }

  /**
   * Lee la cookie de atribución.
   *
   * La cookie la escribe el navegador, así que puede venir troceada, caducada
   * en el reloj del servidor, de otra fuente o directamente inventada: si algo
   * no cuadra se devuelve NULL y el pedido se queda sin marcar por cookie.
   *
   * @param string|null $json
   *   El contenido de la cookie.
   * @param string $fuente
   *   La fuente que se reconoce (`educoland`).
   * @param int $diasVida
   *   Vida de la cookie en días; se comprueba también en servidor porque el
   *   navegador puede conservar una cookie con `Max-Age` manipulado.
   * @param int $ahora
   *   Marca de tiempo actual.
   *
   * @return \Drupal\pronens_referral\Referencia|null
   *   La referencia, o NULL si la cookie no sirve.
   */
  public static function desdeCookie(?string $json, string $fuente, int $diasVida, int $ahora): ?Referencia {
    if ($json === NULL || $json === '') {
      return NULL;
    }
    // Un JSON de más de 1 KB no es nuestro: ni se decodifica.
    if (strlen($json) > 1024) {
      return NULL;
    }
    try {
      $datos = json_decode($json, TRUE, 4, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }
    if (!is_array($datos)) {
      return NULL;
    }
    $leida = self::sanear($datos['s'] ?? '', self::MAX_FUENTE);
    if ($leida === '' || $leida !== self::sanear($fuente, self::MAX_FUENTE)) {
      return NULL;
    }
    $visto = isset($datos['t']) && is_numeric($datos['t']) ? (int) $datos['t'] : 0;
    // Una marca futura (reloj del visitante adelantado, o cookie a mano) se
    // trata como «ahora»: no se descarta la visita por eso, pero tampoco se
    // guarda una fecha imposible en el pedido.
    if ($visto > $ahora) {
      $visto = $ahora;
    }
    if ($visto <= 0 || $visto < $ahora - $diasVida * 86400) {
      return NULL;
    }
    return new Referencia(
      $leida,
      self::sanear($datos['c'] ?? ''),
      self::sanear($datos['ct'] ?? ''),
      $visto,
    );
  }

}
