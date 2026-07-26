<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Hook;

use Drupal\commerce_cart\Form\AddToCartFormInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens_personalizacion\OrderProcessor\PersonalizacionOrderProcessor;

/**
 * Adapta el formulario de añadir al carrito a la card de personalización.
 *
 * El diseño (design/README.md, ficha de producto) resuelve la personalización
 * en una sola pantalla: una card con checkbox "Bordar su nombre +5 €", el
 * nombre, la tipografía y el color del hilo. Los campos los aporta el form
 * display add_to_cart de la línea de pedido; aquí se decide cuándo se ven y
 * con qué opciones.
 */
final class PersonalizacionHooks {

  use StringTranslationTrait;

  /**
   * Campos de personalización expuestos en el add-to-cart.
   */
  private const CAMPOS = [
    PersonalizacionOrderProcessor::CAMPO_TEXTO,
    'field_fuente',
    'field_color_bordado',
  ];

  /**
   * Altera todos los formularios de añadir al carrito.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  #[Hook('form_commerce_order_item_add_to_cart_form_alter')]
  public function addToCartFormAlter(array &$form, FormStateInterface $form_state): void {
    $objeto = $form_state->getBuildInfo()['callback_object'] ?? NULL;
    if (!$objeto instanceof AddToCartFormInterface) {
      return;
    }
    $producto = $form_state->get('product');
    if (!$producto instanceof ProductInterface) {
      return;
    }

    // En un producto sin bordado los campos sobran por completo: 81 de los 370
    // migrados no lo admiten.
    if (!$this->esPersonalizable($producto)) {
      foreach (self::CAMPOS as $campo) {
        $form[$campo]['#access'] = FALSE;
      }
      return;
    }

    // La card del diseño: checkbox que pliega y despliega la personalización.
    // No es un campo, es UX; que haya bordado o no lo decide el texto.
    $form['personalizacion_activa'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bordar su nombre'),
      '#default_value' => FALSE,
      '#weight' => 1,
    ];

    $estados = [
      'visible' => [
        ':input[name="personalizacion_activa"]' => ['checked' => TRUE],
      ],
    ];
    foreach (self::CAMPOS as $campo) {
      if (isset($form[$campo])) {
        $form[$campo]['#states'] = $estados;
        // Detrás del checkbox, delante del botón.
        $form[$campo]['#weight'] = 2;
      }
    }

    // Si el producto restringe las fuentes, se recortan las opciones. Vacío
    // significa todas.
    $permitidas = $this->fuentesPermitidas($producto);
    if ($permitidas !== [] && isset($form['field_fuente']['widget']['#options'])) {
      $opciones = &$form['field_fuente']['widget']['#options'];
      foreach (array_keys($opciones) as $clave) {
        if ($clave !== '_none' && !in_array((int) $clave, $permitidas, TRUE)) {
          unset($opciones[$clave]);
        }
      }
    }

    $form['#validate'][] = [self::class, 'validarPersonalizacion'];
  }

  /**
   * Impide que llegue texto a bordar sin la casilla marcada o en blanco.
   *
   * Los datos del D7 contienen bordados de solo espacios: se normaliza aquí
   * para que la línea de pedido guarde texto real o nada.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  public static function validarPersonalizacion(array &$form, FormStateInterface $form_state): void {
    $campo = PersonalizacionOrderProcessor::CAMPO_TEXTO;
    $valores = $form_state->getValue($campo);
    $texto = trim((string) ($valores[0]['value'] ?? ''));

    $activa = (bool) $form_state->getValue('personalizacion_activa');
    if (!$activa || $texto === '') {
      // Sin casilla o sin texto no hay bordado: se vacía todo para que ni el
      // recargo ni el taller reciban restos.
      $form_state->setValue($campo, []);
      $form_state->setValue('field_fuente', []);
      $form_state->setValue('field_color_bordado', []);
      return;
    }

    $form_state->setValue([$campo, 0, 'value'], $texto);
  }

  /**
   * Si el producto admite bordado.
   */
  private function esPersonalizable(ProductInterface $producto): bool {
    return $producto->hasField('field_personalizable')
      && !$producto->get('field_personalizable')->isEmpty()
      && (bool) $producto->get('field_personalizable')->value;
  }

  /**
   * IDs de término de las fuentes permitidas por el producto.
   *
   * @return list<int>
   */
  private function fuentesPermitidas(ProductInterface $producto): array {
    if (!$producto->hasField('field_fuentes_permitidas')) {
      return [];
    }
    $ids = [];
    foreach ($producto->get('field_fuentes_permitidas')->getValue() as $item) {
      if (isset($item['target_id'])) {
        $ids[] = (int) $item['target_id'];
      }
    }
    return $ids;
  }

}
