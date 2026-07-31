<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Form;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\Api\Entorno;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\pronens_correos_express\GestorExpediciones;
use Drupal\pronens_correos_express\OpcionesExpedicion;
use Drupal\pronens_correos_express\Payload\DatosRecogida;
use Drupal\pronens_correos_express\Payload\Normalizador;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Da de alta la expedición de un envío en Correos Express.
 *
 * Viene todo prerrellenado para que el caso normal sea pulsar Enter.
 */
final class GenerarExpedicionForm extends FormBase {

  public function __construct(
    // Protegidas y no privadas: DependencySerializationTrait, que usan los
    // formularios al serializarse, no soporta propiedades privadas.
    protected GestorExpediciones $gestorExpediciones,
    protected Normalizador $normalizador,
    protected RouteMatchInterface $rutaActual,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(GestorExpediciones::class),
      $container->get(Normalizador::class),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pronens_correos_express_generar_expedicion';
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
    $envio = $this->envio();
    if ($envio === NULL) {
      $form['error'] = ['#markup' => $this->t('No se encuentra el envío.')];
      return $form;
    }

    if ($this->gestorExpediciones->estaExpedido($envio)) {
      $form['ya_expedido'] = [
        '#type' => 'item',
        '#markup' => $this->t('Este envío ya tiene la expedición <strong>@expedicion</strong>. Correos Express no permite anular expediciones, así que no se puede crear otra: si hay que rectificar algo, hay que llamar al comercial.', [
          '@expedicion' => (string) $this->gestorExpediciones->expedicion($envio),
        ]),
      ];

      return $form;
    }

    $entorno = Entorno::desdeConfiguracion(
      $this->config('pronens_correos_express.settings')->get('entorno'),
    );
    if (!$entorno->esProduccion()) {
      $this->messenger()->addWarning($this->t('Estás en preproducción: la expedición que se cree no es real.'));
    }
    else {
      $this->messenger()->addWarning($this->t('Estás en producción: al continuar se crea un envío real que Correos Express factura, y la API no permite anularlo.'));
    }

    $servicio = $this->gestorExpediciones->servicioPorDefecto($envio);
    $destinatario = $envio->getShippingProfile();
    $pais = '';
    $codigoPostal = '';
    if ($destinatario !== NULL && $destinatario->hasField('address') && !$destinatario->get('address')->isEmpty()) {
      $direccion = $destinatario->get('address')->first();
      $pais = (string) ($direccion?->get('country_code')?->getValue() ?? '');
      $codigoPostal = (string) ($direccion?->get('postal_code')?->getValue() ?? '');
    }

    if ($this->normalizador->necesitaAduanas('ES', $pais, $codigoPostal)) {
      $this->messenger()->addWarning($this->t('Este destino requiere documentación aduanera. La API no la gestiona: hay que tramitarla con Correos Express.'));
    }
    if ($this->telefono($envio) === '') {
      $this->messenger()->addWarning($this->t('Este envío no tiene teléfono del destinatario, así que el cliente no recibirá el aviso por SMS y el repartidor no podrá llamar.'));
    }

    $form['servicio'] = [
      '#type' => 'select',
      '#title' => $this->t('Producto de Correos Express'),
      '#options' => ServicioCex::opciones(),
      '#default_value' => $servicio->value,
      '#required' => TRUE,
    ];
    $form['numero_bultos'] = [
      '#type' => 'number',
      '#title' => $this->t('Número de bultos'),
      '#min' => 1,
      '#max' => 99,
      '#default_value' => 1,
      '#required' => TRUE,
    ];
    $form['peso_kg'] = [
      '#type' => 'number',
      '#title' => $this->t('Peso total'),
      '#field_suffix' => $this->t('kg'),
      '#step' => 0.01,
      '#min' => 0.01,
      '#default_value' => $this->pesoEnKilos($envio),
      '#required' => TRUE,
      '#description' => $this->t('Sale del peso de los artículos más la tara del embalaje. Cámbialo si la báscula dice otra cosa: Correos Express factura por peso medido.'),
    ];
    $form['observaciones'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Observaciones para el repartidor'),
      '#maxlength' => Normalizador::MAX_OBSERVACIONES,
      '#description' => $this->t('Se imprime en la etiqueta. Máximo @maximo caracteres.', [
        '@maximo' => Normalizador::MAX_OBSERVACIONES,
      ]),
    ];
    $form['entrega_sabado'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pedir entrega en sábado'),
    ];

    $form['recogida'] = [
      '#type' => 'details',
      '#title' => $this->t('Pedir una recogida'),
      '#description' => $this->t('Solo si no hay recogida diaria concertada. Es la única forma que da la API de crear una: hay que hacerlo ahora, con el alta.'),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    $form['recogida']['solicitar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Solicitar recogida'),
    ];
    $form['recogida']['fecha'] = [
      '#type' => 'date',
      '#title' => $this->t('Fecha'),
      '#default_value' => date('Y-m-d'),
      '#states' => [
        'visible' => [':input[name="recogida[solicitar]"]' => ['checked' => TRUE]],
        'required' => [':input[name="recogida[solicitar]"]' => ['checked' => TRUE]],
      ],
    ];
    $form['recogida']['desde'] = [
      '#type' => 'time',
      '#title' => $this->t('Desde'),
      '#default_value' => '16:00',
      '#states' => [
        'visible' => [':input[name="recogida[solicitar]"]' => ['checked' => TRUE]],
        'required' => [':input[name="recogida[solicitar]"]' => ['checked' => TRUE]],
      ],
    ];
    $form['recogida']['hasta'] = [
      '#type' => 'time',
      '#title' => $this->t('Hasta'),
      '#default_value' => '19:00',
      '#states' => [
        'visible' => [':input[name="recogida[solicitar]"]' => ['checked' => TRUE]],
        'required' => [':input[name="recogida[solicitar]"]' => ['checked' => TRUE]],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Dar de alta la expedición'),
        '#button_type' => 'primary',
      ],
      'cancelar' => [
        '#type' => 'link',
        '#title' => $this->t('Cancelar'),
        '#url' => $this->urlDelPedido($envio),
        '#attributes' => ['class' => ['button']],
      ],
    ];

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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $servicio = ServicioCex::tryFrom((string) $form_state->getValue('servicio'));
    if ($servicio === NULL) {
      $form_state->setErrorByName('servicio', $this->t('Ese producto de Correos Express no existe.'));
      return;
    }

    $bultos = (int) $form_state->getValue('numero_bultos');
    if ($bultos > $servicio->bultosMaximos()) {
      $form_state->setErrorByName('numero_bultos', $this->t('@servicio admite como máximo @maximo bulto(s).', [
        '@servicio' => $servicio->etiqueta(),
        '@maximo' => $servicio->bultosMaximos(),
      ]));
    }

    $gramos = (float) $form_state->getValue('peso_kg') * 1000;
    if ($gramos > $servicio->pesoMaximoGramos()) {
      $form_state->setErrorByName('peso_kg', $this->t('@servicio admite como máximo @maximo kg.', [
        '@servicio' => $servicio->etiqueta(),
        '@maximo' => $servicio->pesoMaximoGramos() / 1000,
      ]));
    }

    $recogida = $form_state->getValue('recogida');
    if (!empty($recogida['solicitar'])) {
      if (($recogida['desde'] ?? '') >= ($recogida['hasta'] ?? '')) {
        $form_state->setErrorByName('recogida][hasta', $this->t('La hora de fin de la recogida tiene que ser posterior a la de inicio.'));
      }
      if (($recogida['fecha'] ?? '') < date('Y-m-d')) {
        $form_state->setErrorByName('recogida][fecha', $this->t('La recogida no puede ser en una fecha pasada.'));
      }
    }
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
    $envio = $this->envio();
    $servicio = ServicioCex::tryFrom((string) $form_state->getValue('servicio'));
    if ($envio === NULL || $servicio === NULL) {
      return;
    }

    $opciones = new OpcionesExpedicion(
      servicio: $servicio,
      numeroBultos: (int) $form_state->getValue('numero_bultos'),
      pesoTotal: new Weight((string) $form_state->getValue('peso_kg'), WeightUnit::KILOGRAM),
      observaciones: (string) $form_state->getValue('observaciones'),
      entregaSabado: (bool) $form_state->getValue('entrega_sabado'),
      recogida: $this->recogida($form_state),
    );

    try {
      $respuesta = $this->gestorExpediciones->generar($envio, $opciones);
      $this->messenger()->addStatus($this->t('Expedición @expedicion creada. Ya puedes imprimir la etiqueta.', [
        '@expedicion' => $respuesta->expedicion,
      ]));
      $form_state->setRedirectUrl(Url::fromRoute('pronens_correos_express.etiqueta', [
        'commerce_order' => $envio->getOrder()?->id(),
        'commerce_shipment' => $envio->id(),
      ]));
    }
    catch (CorreosExpressException $e) {
      $this->messenger()->addError($this->t('No se pudo crear la expedición: @mensaje', [
        '@mensaje' => $e->getMessage(),
      ]));
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Construye los datos de la recogida si el operario la ha pedido.
   */
  private function recogida(FormStateInterface $form_state): ?DatosRecogida {
    $valores = $form_state->getValue('recogida');
    if (empty($valores['solicitar'])) {
      return NULL;
    }

    $fecha = (string) ($valores['fecha'] ?? date('Y-m-d'));
    $desde = new \DateTimeImmutable($fecha . ' ' . (string) ($valores['desde'] ?? '16:00'));
    $hasta = new \DateTimeImmutable($fecha . ' ' . (string) ($valores['hasta'] ?? '19:00'));

    $envio = $this->envio();

    return new DatosRecogida(
      fecha: new \DateTimeImmutable($fecha),
      desde: $desde,
      hasta: $hasta,
      referencia: (string) ($envio?->getOrder()?->getOrderNumber() ?? ''),
    );
  }

  /**
   * Envío al que se refiere el formulario.
   */
  private function envio(): ?ShipmentInterface {
    $envio = $this->rutaActual->getParameter('commerce_shipment');

    return $envio instanceof ShipmentInterface ? $envio : NULL;
  }

  /**
   * Peso del envío en kilos, con dos decimales.
   */
  private function pesoEnKilos(ShipmentInterface $envio): string {
    return $this->normalizador->kilos($envio->getWeight());
  }

  /**
   * Teléfono del destinatario, si lo hay.
   */
  private function telefono(ShipmentInterface $envio): string {
    $perfil = $envio->getShippingProfile();
    if ($perfil === NULL || !$perfil->hasField('field_telefono') || $perfil->get('field_telefono')->isEmpty()) {
      return '';
    }

    return (string) $perfil->get('field_telefono')->first()?->get('value')?->getValue();
  }

  /**
   * Enlace de vuelta al pedido.
   */
  private function urlDelPedido(ShipmentInterface $envio): Url {
    $pedido = $envio->getOrder();
    if ($pedido === NULL) {
      return Url::fromRoute('entity.commerce_order.collection');
    }

    return Url::fromRoute('entity.commerce_order.canonical', ['commerce_order' => $pedido->id()]);
  }

}
