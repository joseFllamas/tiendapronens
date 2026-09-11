<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Títulos SEO que no coinciden con el nombre de la entidad.
 *
 * Dos casos, decididos con el cliente para "Bolsas y sacos" (2026-09-11):
 * - Una categoría puede tener un H1 y un <title> más largos que su nombre
 *   ("Bolsas de guardería y sacos de almuerzo" frente a "Bolsas y sacos", que
 *   es lo que cabe en el menú y en la miga).
 * - Los productos de una categoría pueden llevar un <title> con patrón
 *   ("Saco de almuerzo o muda escolar, diseño Sakura") mientras el H1 y el
 *   nombre del producto siguen siendo los suyos.
 *
 * Aquí solo se compone el texto: de dónde salen el patrón y el diseño lo
 * decide SeoHooks. Lógica pura, sin dependencias de Drupal, probada en
 * unitario.
 */
final class TituloSeo {

  /**
   * Marcador del patrón que se sustituye por el diseño del producto.
   */
  public const MARCADOR_DISENO = '@diseno';

  /**
   * Título de la ficha a partir del patrón de su categoría.
   *
   * @param string $patron
   *   El patrón de la categoría, con el marcador @diseno dentro.
   * @param string $diseno
   *   El diseño del producto (Sakura, Caperucita Roja…).
   *
   * @return string|null
   *   El título compuesto, o NULL si falta el patrón o el diseño: entonces la
   *   ficha conserva el título de siempre.
   */
  public static function deProducto(string $patron, string $diseno): ?string {
    $patron = trim($patron);
    $diseno = trim($diseno);
    if ($patron === '' || $diseno === '') {
      return NULL;
    }

    return self::limpia(str_replace(self::MARCADOR_DISENO, $diseno, $patron));
  }

  /**
   * Sustituye un token de metatag por un valor literal.
   *
   * Se toca solo el token y no la etiqueta entera para respetar lo que el
   * cliente tenga configurado alrededor (el " | [site:name]" de la cola, o lo
   * que ponga mañana).
   *
   * @param string $etiqueta
   *   El valor de la etiqueta con sus tokens todavía dentro.
   * @param string $token
   *   El token a sustituir, corchetes incluidos.
   * @param string $valor
   *   El texto que ocupa su sitio.
   *
   * @return string|null
   *   La etiqueta con el valor puesto, o NULL si el token no estaba: en ese
   *   caso el cliente ha cambiado la etiqueta por defecto y no se pisa.
   */
  public static function sustituye(string $etiqueta, string $token, string $valor): ?string {
    if (!str_contains($etiqueta, $token)) {
      return NULL;
    }

    return str_replace($token, $valor, $etiqueta);
  }

  /**
   * Espacios repetidos a uno y sin restos alrededor.
   */
  private static function limpia(string $texto): string {
    return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
  }

}
