<?php

declare(strict_types=1);

namespace Drupal\pronens_referral\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\pronens_referral\Atribucion;
use Drupal\pronens_referral\AtribuidorDePedidos;

/**
 * Ajustes de la atribución de partners.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pronens_referral_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, string>
   */
  protected function getEditableConfigNames(): array {
    return [AtribuidorDePedidos::NOMBRE_CONFIG];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(AtribuidorDePedidos::NOMBRE_CONFIG);

    $form['explicacion'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Marca los pedidos que llegan desde el directorio de centros educativos con el que hay convenio. Se reconocen de dos formas: por el enlace (una cookie propia que solo se escribe si el visitante acepta la analítica en el aviso de cookies) y por el cupón, que funciona siempre. El informe está en <a href=":url">Pedidos de educoland</a>.', [
        ':url' => Url::fromRoute('view.pronens_referral_educoland.page_1')->toString(),
      ]) . '</p>',
    ];

    $form['activo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Marcar los pedidos que vengan del partner'),
      '#description' => $this->t('Al desactivarlo deja de escribirse la cookie y los pedidos nuevos dejan de marcarse. Lo ya marcado no se toca.'),
      '#default_value' => (bool) $config->get('activo'),
    ];

    $form['fuente'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Valor de utm_source que se reconoce'),
      '#description' => $this->t('El que emite el partner en sus enlaces. Solo minúsculas, números, guion y guion bajo.'),
      '#default_value' => $config->get('fuente'),
      '#required' => TRUE,
      '#maxlength' => Atribucion::MAX_FUENTE,
      '#size' => 20,
    ];

    $form['dias_cookie'] = [
      '#type' => 'number',
      '#title' => $this->t('Ventana de atribución (días)'),
      '#description' => $this->t('Cuánto tiempo se recuerda que el visitante vino del partner. Cada visita nueva desde sus enlaces reinicia la cuenta.'),
      '#default_value' => (int) $config->get('dias_cookie'),
      '#min' => 1,
      '#max' => 730,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['prefijo_cupon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefijo de los cupones por centro'),
      '#description' => $this->t('Cualquier cupón que empiece por aquí cuenta como del partner, sin tener que darlo de alta uno a uno. Déjalo vacío para no usar prefijo.'),
      '#default_value' => $config->get('prefijo_cupon'),
      '#maxlength' => 32,
      '#size' => 20,
    ];

    $form['cupones'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cupones sueltos del convenio'),
      '#description' => $this->t('Uno por línea. Son los códigos públicos que el partner reparte en sus banners y correos, y que va rotando. No distinguen mayúsculas.'),
      '#default_value' => implode("\n", (array) ($config->get('cupones') ?? [])),
      '#rows' => 6,
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
    $fuente = (string) $form_state->getValue('fuente');
    if ($fuente !== Atribucion::sanear($fuente, Atribucion::MAX_FUENTE)) {
      $form_state->setErrorByName('fuente', $this->t('La fuente solo puede llevar minúsculas, números, guion y guion bajo: es lo que se compara con el utm_source de la URL.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cupones = array_values(array_filter(array_map(
      static fn (string $linea): string => trim($linea),
      preg_split('/\R/', (string) $form_state->getValue('cupones')) ?: [],
    )));
    $this->config(AtribuidorDePedidos::NOMBRE_CONFIG)
      ->set('activo', (bool) $form_state->getValue('activo'))
      ->set('fuente', Atribucion::sanear((string) $form_state->getValue('fuente'), Atribucion::MAX_FUENTE))
      ->set('dias_cookie', (int) $form_state->getValue('dias_cookie'))
      ->set('prefijo_cupon', trim((string) $form_state->getValue('prefijo_cupon')))
      ->set('cupones', $cupones)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
