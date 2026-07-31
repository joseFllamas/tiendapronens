<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pronens_correos_express\Api\Credenciales;
use Drupal\pronens_correos_express\Api\RepositorioCredenciales;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Credenciales de Correos Express.
 *
 * No es un ConfigFormBase a propósito: las credenciales van a State y no a
 * configuración, porque config/sync está versionado en git y una exportación
 * después de guardar este formulario comitearía la contraseña en texto plano.
 */
final class CredencialesForm extends FormBase {

  public function __construct(
    // Protegida y no privada: DependencySerializationTrait, que usan los
    // formularios al serializarse, no soporta propiedades privadas.
    protected RepositorioCredenciales $repositorioCredenciales,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(RepositorioCredenciales::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pronens_correos_express_credenciales';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   *
   * @return array<string, mixed>
   *   El formulario.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $credenciales = $this->repositorioCredenciales->cargar();

    $form['explicacion'] = [
      '#type' => 'item',
      '#markup' => $this->t('Los tres datos que te da tu comercial de Correos Express. No se guardan con el resto de la configuración del sitio, así que no viajan en una exportación ni acaban en el control de versiones: cada entorno tiene los suyos.'),
    ];

    $form['codigo_cliente'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Código de cliente'),
      '#description' => $this->t('Viaja dentro de cada petición. En el alta de la expedición va con una P delante, y de eso ya se encarga el módulo.'),
      '#default_value' => $credenciales->codigoCliente,
      '#required' => TRUE,
      '#maxlength' => 50,
    ];
    $form['usuario'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Usuario del servicio web'),
      '#default_value' => $credenciales->usuario,
      '#required' => TRUE,
      '#maxlength' => 50,
    ];
    $form['contrasena'] = [
      '#type' => 'password',
      '#title' => $this->t('Contraseña'),
      '#description' => $credenciales->contrasena === ''
        ? $this->t('Todavía no hay ninguna guardada.')
        : $this->t('Hay una guardada. Déjalo en blanco para conservarla.'),
      '#maxlength' => 50,
      // Solo obligatoria la primera vez: si no, cualquier cambio en los otros
      // dos campos forzaría a volver a teclearla.
      '#required' => $credenciales->contrasena === '',
    ];

    $form['acciones'] = [
      '#type' => 'actions',
      'guardar' => [
        '#type' => 'submit',
        '#value' => $this->t('Guardar credenciales'),
        '#button_type' => 'primary',
      ],
    ];

    if ($credenciales->estanCompletas()) {
      $form['acciones']['borrar'] = [
        '#type' => 'submit',
        '#value' => $this->t('Borrar credenciales'),
        '#submit' => ['::borrar'],
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $anteriores = $this->repositorioCredenciales->cargar();
    $contrasena = (string) $form_state->getValue('contrasena');

    $this->repositorioCredenciales->guardar(new Credenciales(
      trim((string) $form_state->getValue('codigo_cliente')),
      trim((string) $form_state->getValue('usuario')),
      $contrasena !== '' ? $contrasena : $anteriores->contrasena,
    ));

    $this->messenger()->addStatus($this->t('Credenciales de Correos Express guardadas.'));
  }

  /**
   * Borra las credenciales guardadas.
   *
   * @param array<string, mixed> $form
   *   Estructura del formulario.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado del formulario.
   */
  public function borrar(array &$form, FormStateInterface $form_state): void {
    $this->repositorioCredenciales->borrar();
    $this->messenger()->addStatus($this->t('Credenciales borradas. Hasta que las vuelvas a poner no se puede dar de alta ninguna expedición.'));
  }

}
