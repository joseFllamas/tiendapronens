<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Da forma a los datos de las variaciones para los tokens del Product JSON-LD.
 *
 * El módulo schema_metatag no tiene bucle: para sacar una Offer por variación
 * hay que darle cada propiedad como una lista separada por comas y marcar el
 * bloque como "pivot", que es lo que lo convierte en N objetos. Estos métodos
 * son la parte sin Drupal de ese trabajo: reciben las variaciones ya leídas y
 * devuelven las listas alineadas (la posición i de cada una es la misma
 * variación). Lógica pura, con pruebas unitarias.
 */
final class OfertasCalculator {

  public const EN_STOCK = 'https://schema.org/InStock';
  public const AGOTADO = 'https://schema.org/OutOfStock';

  /**
   * Las listas alineadas de una serie de variaciones.
   *
   * @param array<int, array{precio: string|float|int, url: string, stock: float|int|null}> $variaciones
   *   Por variación: precio numérico, URL absoluta con ?v=ID y nivel de stock
   *   (NULL = no se controla, se considera disponible).
   *
   * @return array{precio: string, url: string, disponibilidad: string, minimo: string, maximo: string, total: string}
   *   Cada valor es la lista separada por comas, salvo minimo/maximo/total.
   */
  public static function listas(array $variaciones): array {
    $precios = [];
    $urls = [];
    $disponibilidades = [];
    foreach ($variaciones as $variacion) {
      $precios[] = self::precio($variacion['precio']);
      $urls[] = $variacion['url'];
      $disponibilidades[] = self::disponibilidad($variacion['stock']);
    }
    $numeros = array_map('floatval', $precios);

    return [
      'precio' => implode(',', $precios),
      'url' => implode(',', $urls),
      'disponibilidad' => implode(',', $disponibilidades),
      'minimo' => $numeros === [] ? '' : self::precio(min($numeros)),
      'maximo' => $numeros === [] ? '' : self::precio(max($numeros)),
      'total' => (string) \count($variaciones),
    ];
  }

  /**
   * Precio con dos decimales y punto, como pide schema.org.
   */
  public static function precio(string|float|int $precio): string {
    return number_format((float) $precio, 2, '.', '');
  }

  /**
   * La disponibilidad de schema.org para un nivel de stock.
   *
   * @param float|int|null $stock
   *   Unidades disponibles; NULL cuando la variación no controla stock.
   */
  public static function disponibilidad(float|int|null $stock): string {
    return $stock === NULL || $stock > 0 ? self::EN_STOCK : self::AGOTADO;
  }

}
