<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

/**
 * Decisiones puras sobre los alias del catálogo.
 *
 * Sin dependencias de Drupal para poder probarlas con PHPUnit: qué idiomas de
 * una categoría han cambiado de nombre, si un alias lleva el sufijo numérico
 * que añade el uniquificador de pathauto, y cómo se mete el tramo de la
 * escuela en el alias de un producto que no tiene categoría.
 */
final class AliasCalculator {

  /**
   * Idiomas cuyo nombre ha cambiado, o que son nuevos, entre dos versiones.
   *
   * @param array<string, string> $antes
   *   Nombre por idioma antes de guardar.
   * @param array<string, string> $despues
   *   Nombre por idioma después de guardar.
   *
   * @return list<string>
   *   Códigos de idioma que hay que regenerar.
   */
  public static function idiomasConNombreCambiado(array $antes, array $despues): array {
    $cambiados = [];
    foreach ($despues as $idioma => $nombre) {
      if (!array_key_exists($idioma, $antes) || trim($antes[$idioma]) !== trim($nombre)) {
        $cambiados[] = (string) $idioma;
      }
    }
    return $cambiados;
  }

  /**
   * Si el alias es el crudo más el sufijo numérico del uniquificador.
   *
   * Pathauto recorta el crudo para que quepa el sufijo, así que el alias no
   * tiene por qué EMPEZAR por el crudo entero: basta con que el crudo empiece
   * por el alias sin su sufijo. Y un título que acaba en número
   * («Cojín POP Culture 001») no es un sufijo: el alias coincide con el crudo.
   */
  public static function tieneSufijoDeUnicidad(string $alias, string $crudo, string $separador = '-'): bool {
    if ($alias === $crudo || $crudo === '') {
      return FALSE;
    }
    if (!preg_match('/^(.*)' . preg_quote($separador, '/') . '\d+$/', $alias, $partes)) {
      return FALSE;
    }
    return $partes[1] !== '' && str_starts_with($crudo, $partes[1]);
  }

  /**
   * Mete la escuela como segundo tramo de un alias de dos tramos.
   *
   * Un producto sin categoría sale como /productos/titulo, porque el
   * limpiador colapsa la barra que deja el token vacío. Con escuela queda
   * /productos/uniformes-de-colegio-goar/titulo, que es la misma forma de dos
   * niveles del resto del catálogo. Cualquier otra forma se devuelve tal cual.
   */
  public static function conTramoDeEscuela(string $alias, string $escuela): string {
    $escuela = trim($escuela, '/');
    $tramos = explode('/', trim($alias, '/'));
    if ($escuela === '' || count($tramos) !== 2 || $tramos[0] === '' || $tramos[1] === '') {
      return $alias;
    }
    return '/' . $tramos[0] . '/' . $escuela . '/' . $tramos[1];
  }

}
