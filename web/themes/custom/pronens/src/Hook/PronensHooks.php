<?php

namespace Drupal\pronens\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for pronens.
 */
class PronensHooks {
  /**
   * @file
   * Functions to support theming.
   */

  /**
   * Implements hook_preprocess_page().
   *
   * @param array<string, mixed> $variables
   *   Variables del template de página.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // Las pantallas del rediseño (home, categoría, ficha) son full-width y
    // cada componente acota con .pro-container; el resto de páginas (CMS,
    // checkout, usuario…) usa el contenedor central. De momento solo la
    // portada es full-width; las fases 4 y 5 ampliarán la condición.
    $variables['main_boxed'] = empty($variables['is_front']);
  }

  /**
   * Implements hook_preprocess_image_widget().
   *
   * @param array<string, mixed> $variables
   *   Variables del template del widget de imagen.
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

}
