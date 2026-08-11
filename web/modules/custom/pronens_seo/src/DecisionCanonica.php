<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Lo que hay que declarar en una página de categoría: canónica y robots.
 *
 * Es el resultado de CanonicalCalculator: dice qué parámetros de la URL
 * sobreviven en la canónica y si la página debe salir del índice. La URL
 * absoluta la compone quien lo usa, sobre la canónica del término en el idioma
 * de la página.
 */
final class DecisionCanonica {

  /**
   * Construye la decisión.
   *
   * @param int|null $pagina
   *   Valor del parámetro "page" que conserva la canónica, o NULL si la
   *   canónica es la URL limpia del término. Es el índice de Drupal, que
   *   empieza en 0: la página 2 de cara al usuario es page=1.
   * @param string|null $robots
   *   Directiva para robots, o NULL si no hay que tocar la que haya.
   */
  public function __construct(
    public readonly ?int $pagina = NULL,
    public readonly ?string $robots = NULL,
  ) {
  }

  /**
   * Los parámetros de consulta de la canónica.
   *
   * @return array<string, int>
   *   Vacío para la URL limpia del término.
   */
  public function queryCanonica(): array {
    return $this->pagina === NULL ? [] : ['page' => $this->pagina];
  }

  /**
   * Número de página de cara al usuario, el que pinta el paginador.
   *
   * @return int|null
   *   La primera página es la 1; NULL cuando la canónica no lleva página.
   */
  public function numeroVisible(): ?int {
    return $this->pagina === NULL ? NULL : $this->pagina + 1;
  }

}
