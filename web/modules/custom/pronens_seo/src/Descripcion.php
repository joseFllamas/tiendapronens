<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Convierte el HTML de una descripción en el texto de una meta description.
 *
 * Metatag hace strip_tags a secas, así que dos párrafos seguidos salían
 * pegados ("…de la letra.Disponible en dos tamaños…") y el texto entero de la
 * descripción (551 caracteres en las categorías migradas del D7) viajaba en la
 * etiqueta. Aquí los bloques se separan con un espacio y el resultado se corta
 * en una frase o, si no hay ninguna que quepa, en una palabra.
 *
 * Lógica pura, sin dependencias de Drupal: se prueba en unitario.
 */
final class Descripcion {

  /**
   * Longitud máxima de la meta description.
   *
   * Google enseña unos 155-160 caracteres en escritorio; por encima corta él.
   */
  public const MAXIMO = 160;

  /**
   * Por debajo de esto no vale la pena cortar en frase: se corta en palabra.
   */
  private const MINIMO_FRASE = 90;

  /**
   * Texto plano completo, con los bloques separados por un espacio.
   */
  public static function texto(string $html): string {
    // Un espacio donde acaba un bloque o hay un salto de línea, para que no se
    // peguen dos frases al quitar las etiquetas.
    $con_espacios = preg_replace('#</(p|div|li|h[1-6]|blockquote|tr|td|th)>|<br\s*/?>#i', ' ', $html) ?? $html;
    $plano = html_entity_decode(strip_tags($con_espacios), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Los espacios duros del editor también cuentan como espacio.
    $plano = str_replace("\u{00A0}", ' ', $plano);

    return trim(preg_replace('/\s+/u', ' ', $plano) ?? $plano);
  }

  /**
   * El texto recortado para la etiqueta.
   *
   * @param string $html
   *   La descripción con su HTML.
   * @param int $maximo
   *   Longitud máxima en caracteres.
   */
  public static function resumir(string $html, int $maximo = self::MAXIMO): string {
    $texto = self::texto($html);
    if (mb_strlen($texto) <= $maximo) {
      return $texto;
    }
    $corte = mb_substr($texto, 0, $maximo);

    // Mejor una frase entera que media: se busca el último punto (o cierre de
    // frase) que quepa, siempre que deje algo sustancial.
    if (preg_match_all('/[.!?…](?=\s|$)/u', $corte, $coincidencias, PREG_OFFSET_CAPTURE) > 0) {
      $ultima = end($coincidencias[0]);
      // La posición viene en bytes; se pasa a caracteres.
      $posicion = mb_strlen(substr($corte, 0, $ultima[1])) + 1;
      if ($posicion >= self::MINIMO_FRASE) {
        return rtrim(mb_substr($corte, 0, $posicion));
      }
    }

    // Sin frase que quepa: en la última palabra completa, sin puntuación
    // colgando.
    $espacio = mb_strrpos($corte, ' ');
    $palabras = $espacio !== FALSE ? mb_substr($corte, 0, $espacio) : $corte;

    return rtrim($palabras, " ,;:-–—(¿¡");
  }

}
