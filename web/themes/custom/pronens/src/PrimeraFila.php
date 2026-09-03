<?php

declare(strict_types=1);

namespace Drupal\pronens;

use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Carga inmediata de las fotos de la primera fila del catálogo.
 *
 * La tarjeta pinta su foto con loading="lazy", que es lo correcto para las
 * que están por debajo del pliegue, pero la primera fila es el LCP de la
 * categoría y con lazy el navegador la pide tarde. La tarjeta se cachea en
 * render por producto y NO sabe en qué posición sale, así que no se puede
 * decidir dentro de ella: se hace aquí, en un post_render del envoltorio de
 * la fila, que no se cachea y ve la fila ya pintada. Cache-safe por
 * construcción: la tarjeta cacheada sigue siendo lazy y solo cambia la copia
 * que sale en la primera fila de la página.
 */
final class PrimeraFila implements TrustedCallbackInterface {

  /**
   * Tarjetas que se marcan eager: una fila de escritorio (dos de móvil).
   */
  public const TARJETAS = 4;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['eager'];
  }

  /**
   * Sustituye el loading de la PRIMERA imagen de la tarjeta.
   *
   * Solo la primera: la segunda foto del slide de la tarjeta sigue siendo
   * lazy, que no se ve hasta el hover.
   *
   * @param \Drupal\Core\Render\Markup|string $children
   *   El HTML de la fila ya renderizado.
   * @param array<string, mixed> $elements
   *   El render array del envoltorio (no se usa).
   *
   * @return \Drupal\Core\Render\Markup
   *   El mismo HTML con la primera foto en carga inmediata y prioritaria.
   */
  public static function eager($children, array $elements): Markup {
    $html = (string) $children;
    $marcado = preg_replace(
      '/(<img\b[^>]*?)\sloading="lazy"/',
      '$1 loading="eager" fetchpriority="high"',
      $html,
      1
    );

    return Markup::create($marcado ?? $html);
  }

}
