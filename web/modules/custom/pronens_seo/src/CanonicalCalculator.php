<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Decide la canónica y el robots de una página de categoría.
 *
 * Como ExtrasCalculator, es lógica pura y sin dependencias: entra el array de
 * parámetros de consulta y sale la decisión, sin tocar la petición ni el
 * contenedor. Así se prueba con PHPUnit sin base de datos.
 *
 * Las tres reglas, en este orden:
 *
 * 1. Con facetas (f[]) o con orden expuesto (sort_by): la canónica es la URL
 *    limpia del término y la página se marca "noindex, follow". Cada
 *    combinación de filtros genera una URL casi duplicada que no aporta nada al
 *    índice, pero sí tiene que seguir transmitiendo rastreo hacia las fichas,
 *    de ahí "follow" y no "nofollow".
 * 2. Con ?page=N y N > 0: la canónica es esa misma página. Google pide URL
 *    única y canónica propia por página; canonicalizar la 2 hacia la 1 es lo
 *    que dejaba fuera del índice a los productos enlazados desde la 2 en
 *    adelante.
 * 3. Sin parámetros: la URL limpia del término, que es lo que ya hacía el
 *    valor por defecto de metatag ([term:url]).
 */
final class CanonicalCalculator {

  /**
   * Directiva para las páginas filtradas.
   */
  public const ROBOTS_FILTRADO = 'noindex, follow';

  /**
   * Parámetro con el que facets serializa todas las facetas activas.
   */
  private const PARAM_FACETAS = 'f';

  /**
   * Parámetro del orden expuesto de la view.
   */
  private const PARAM_ORDEN = 'sort_by';

  /**
   * Parámetro del paginador de Drupal.
   */
  private const PARAM_PAGINA = 'page';

  /**
   * Resuelve qué canónica y qué robots corresponden a una petición.
   *
   * @param array<string, mixed> $query
   *   Los parámetros de consulta de la petición.
   */
  public function decide(array $query): DecisionCanonica {
    if ($this->estaFiltrada($query)) {
      return new DecisionCanonica(NULL, self::ROBOTS_FILTRADO);
    }

    return new DecisionCanonica($this->pagina($query));
  }

  /**
   * Si la petición lleva facetas marcadas o un orden elegido.
   *
   * @param array<string, mixed> $query
   *   Los parámetros de consulta de la petición.
   */
  private function estaFiltrada(array $query): bool {
    $facetas = $query[self::PARAM_FACETAS] ?? NULL;
    if (is_array($facetas) && array_filter($facetas, $this->noVacio(...)) !== []) {
      return TRUE;
    }

    return $this->noVacio($query[self::PARAM_ORDEN] ?? NULL);
  }

  /**
   * Número de página, normalizado, o NULL si es la primera.
   *
   * El paginador serializa una posición por paginador de la página separadas
   * por comas ("page=0,2"); esta view tiene uno solo, así que manda el primero.
   * Se normaliza a entero a propósito: así "?page=01" no genera una canónica
   * distinta de "?page=1".
   *
   * @param array<string, mixed> $query
   *   Los parámetros de consulta de la petición.
   */
  private function pagina(array $query): ?int {
    $valor = $query[self::PARAM_PAGINA] ?? NULL;
    if (!is_string($valor) && !is_int($valor)) {
      return NULL;
    }
    $primera = explode(',', (string) $valor)[0];
    // ctype_digit descarta de una vez la cadena vacía, los negativos y la
    // basura: cualquiera de esos casos es la primera página.
    if (!ctype_digit($primera)) {
      return NULL;
    }
    $numero = (int) $primera;

    return $numero > 0 ? $numero : NULL;
  }

  /**
   * Si un parámetro trae algo utilizable.
   */
  private function noVacio(mixed $valor): bool {
    return is_string($valor) && trim($valor) !== '';
  }

}
