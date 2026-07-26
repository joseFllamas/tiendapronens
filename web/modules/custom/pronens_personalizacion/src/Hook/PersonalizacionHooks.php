<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Hook;

use Drupal\commerce_cart\Form\AddToCartFormInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pronens_personalizacion\OrderProcessor\PersonalizacionOrderProcessor;

/**
 * Adapta el formulario de añadir al carrito a la card de personalización.
 *
 * El cliente decide qué se borda con dos controles: el texto (una inicial o un
 * nombre, según el modo del producto) y el color del hilo. La tipografía dejó
 * de elegirse por decisión del cliente (2026-07-26): será siempre la misma, así
 * que el vocabulario fuente_bordado y field_fuente quedan en el modelo sin
 * exponerse, por si la decisión se revierte.
 */
final class PersonalizacionHooks {

  use StringTranslationTrait;

  /**
   * Campos de personalización expuestos en el add-to-cart.
   */
  private const CAMPOS = [
    PersonalizacionOrderProcessor::CAMPO_TEXTO,
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

    $modo = $this->modo($producto);

    // La card del diseño: checkbox que pliega y despliega la personalización.
    // No es un campo, es UX; que haya bordado o no lo decide el texto.
    $form['personalizacion_activa'] = [
      '#type' => 'checkbox',
      '#title' => $modo === 'inicial'
        ? $this->t('Bordar su inicial')
        : $this->t('Bordar su nombre'),
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

    // El modo del producto decide qué se puede escribir: una sola letra o un
    // nombre. El widget es un string_textfield; se ajusta su elemento de valor.
    $texto = &$form[PersonalizacionOrderProcessor::CAMPO_TEXTO];
    if (isset($texto['widget'][0]['value']) && $modo === 'inicial') {
      $texto['widget'][0]['value']['#title'] = $this->t('Inicial');
      $texto['widget'][0]['value']['#maxlength'] = 1;
      $texto['widget'][0]['value']['#attributes']['maxlength'] = 1;
      $texto['widget'][0]['value']['#placeholder'] = $this->t('Una letra');
    }

    $form['#validate'][] = [self::class, 'validarPersonalizacion'];
  }

  /**
   * Normaliza y valida el texto a bordar según el modo del producto.
   *
   * Los datos del D7 contienen bordados de solo espacios: se normaliza aquí
   * para que la línea de pedido guarde texto real o nada. El maxlength del
   * navegador no es una garantía, así que el modo inicial se reafirma en
   * servidor.
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
      $form_state->setValue('field_color_bordado', []);
      return;
    }

    $producto = $form_state->get('product');
    if ($producto instanceof ProductInterface
      && !$producto->get('field_modo_personalizacion')->isEmpty()
      && $producto->get('field_modo_personalizacion')->value === 'inicial'
      && mb_strlen($texto) > 1) {
      $form_state->setErrorByName($campo, new TranslatableMarkup('Este producto se personaliza con una sola inicial.'));
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
   * Modo de personalización del producto: inicial o texto.
   *
   * Sin valor se asume texto libre, que es lo que hacía el D7.
   */
  private function modo(ProductInterface $producto): string {
    if (!$producto->hasField('field_modo_personalizacion')
      || $producto->get('field_modo_personalizacion')->isEmpty()) {
      return 'texto';
    }
    return (string) $producto->get('field_modo_personalizacion')->value;
  }

}
