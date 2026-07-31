<?php

declare(strict_types=1);

namespace Drupal\pronens_comision_pago\Form;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pronens_comision_pago\ComisionCalculator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ajustes de la comisión por medio de pago.
 */
final class SettingsForm extends ConfigFormBase {

  private const NOMBRE_CONFIG = 'pronens_comision_pago.settings';

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
    return 'pronens_comision_pago_settings';
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

    $form['aviso'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Antes de activar esto: las condiciones de uso de PayPal prohíben cobrar un recargo por pagarle salvo que la ley lo permita, y la ley de consumidores prohíbe cobrar más de lo que le cuesta el medio de pago al comercio. Pon aquí la comisión real que te cobra la pasarela, nunca más.') . '</p>',
    ];

    $form['porcentaje'] = [
      '#type' => 'number',
      '#title' => $this->t('Porcentaje de comisión'),
      '#description' => $this->t('Se suma al total del pedido, con el bordado, los extras, el envío y el IVA ya dentro, que es sobre lo que cobra la pasarela. A cero no se aplica nada ni se avisa en el selector de pago.'),
      '#default_value' => $config->get('porcentaje'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#field_suffix' => '%',
      '#required' => TRUE,
    ];

    $form['pasarelas'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Pasarelas que repercuten la comisión'),
      '#description' => $this->t('El TPV del banco no puede llevar recargo: la normativa europea de servicios de pago lo prohíbe para las tarjetas de consumidor.'),
      '#options' => $this->opcionesDePasarela(),
      '#default_value' => array_map('strval', (array) ($config->get('pasarelas') ?? [])),
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

    $porcentaje = (string) $form_state->getValue('porcentaje');
    if (!ComisionCalculator::esPorcentajeValido($porcentaje)) {
      $form_state->setErrorByName('porcentaje', $this->t('El porcentaje tiene que ser un número positivo, con punto decimal (por ejemplo 1.5).'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pasarelas = array_values(array_filter((array) $form_state->getValue('pasarelas')));

    $this->config(self::NOMBRE_CONFIG)
      ->set('porcentaje', (string) $form_state->getValue('porcentaje'))
      ->set('pasarelas', array_map('strval', $pasarelas))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Las pasarelas de pago activas, para elegir a cuáles se les aplica.
   *
   * @return array<string, string>
   *   Etiquetas indexadas por identificador de pasarela.
   */
  private function opcionesDePasarela(): array {
    $opciones = [];
    $pasarelas = $this->entityTypeManager
      ->getStorage('commerce_payment_gateway')
      ->loadMultiple();

    foreach ($pasarelas as $pasarela) {
      if (!$pasarela instanceof PaymentGatewayInterface) {
        continue;
      }
      $opciones[(string) $pasarela->id()] = $pasarela->status()
        ? (string) $pasarela->label()
        : (string) $this->t('@etiqueta (desactivada)', ['@etiqueta' => $pasarela->label()]);
    }

    return $opciones;
  }

}
