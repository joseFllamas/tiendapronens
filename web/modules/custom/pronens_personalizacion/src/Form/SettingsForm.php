<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Form;

use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ajustes de la personalización del bordado.
 */
final class SettingsForm extends ConfigFormBase {

  private const NOMBRE_CONFIG = 'pronens_personalizacion.settings';

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    // Protegida y no privada: DependencySerializationTrait, que usan los
    // formularios al serializarse, no soporta propiedades privadas.
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pronens_personalizacion_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, string>
   */
  protected function getEditableConfigNames(): array {
    return [self::NOMBRE_CONFIG];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::NOMBRE_CONFIG);

    $form['recargo'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Recargo por bordado'),
      '#description' => $this->t('Se cobra por unidad y solo en las líneas que llevan texto bordado. Un producto puede llevar su propio recargo en el campo "Recargo", que manda sobre este.'),
      '#default_value' => $config->get('recargo'),
      '#required' => TRUE,
    ];

    $form['longitud_maxima'] = [
      '#type' => 'number',
      '#title' => $this->t('Longitud máxima del texto'),
      '#description' => $this->t('El máximo real observado en los 3374 bordados del Drupal 7 es de 47 caracteres, con una media de 9,2.'),
      '#default_value' => $config->get('longitud_maxima'),
      '#min' => 1,
      '#max' => 255,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $recargo = $form_state->getValue('recargo');
    if (is_array($recargo) && isset($recargo['number']) && (float) $recargo['number'] < 0) {
      $form_state->setErrorByName('recargo', $this->t('El recargo no puede ser negativo. Si no quieres cobrar el bordado, déjalo a cero.'));
    }

    if (is_array($recargo) && !empty($recargo['currency_code'])) {
      $moneda = $this->entityTypeManager
        ->getStorage('commerce_currency')
        ->load($recargo['currency_code']);
      if (!$moneda instanceof CurrencyInterface) {
        $form_state->setErrorByName('recargo', $this->t('La moneda %moneda no existe en esta tienda.', [
          '%moneda' => $recargo['currency_code'],
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recargo = $form_state->getValue('recargo');
    $this->config(self::NOMBRE_CONFIG)
      ->set('recargo', [
        'number' => (string) ($recargo['number'] ?? '0'),
        'currency_code' => (string) ($recargo['currency_code'] ?? 'EUR'),
      ])
      ->set('longitud_maxima', (int) $form_state->getValue('longitud_maxima'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
