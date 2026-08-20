<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Attribute\CommerceShippingMethod;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\SupportsTrackingInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Método de envío de Correos Express.
 *
 * Un solo plugin para los diecisiete productos del transportista: el atributo
 * CommerceShippingMethod y getServices() de la clase base existen justamente
 * para "un transportista, N servicios". La integración oficial de WooCommerce
 * tiene diecisiete clases porque allí un método de envío es una fila con un
 * precio, no un transportista.
 *
 * Cada método de envío de la tienda despacha con un único producto de Correos
 * Express, así que el formulario ofrece un desplegable en lugar de la lista de
 * casillas de la clase base: dos productos activos en el mismo método darían
 * dos opciones idénticas en el checkout.
 *
 * No se cachean las tarifas y no hace falta: calculateRates() no hace ninguna
 * llamada de red porque Correos Express no tiene consulta de tarifas. Es la
 * diferencia más grande frente a commerce_ups o commerce_fedex, y conviene
 * dejarlo escrito para que nadie añada una caché que solo sirva para servir
 * precios rancios.
 */
#[CommerceShippingMethod(
  id: 'correos_express',
  label: new TranslatableMarkup('Correos Express'),
  services: [
    'paq10' => new TranslatableMarkup('Paq 10'),
    'paq14' => new TranslatableMarkup('Paq 14'),
    'paq24' => new TranslatableMarkup('Paq 24'),
    'paq_empresa_14' => new TranslatableMarkup('Paq Empresa 14'),
    'epaq24' => new TranslatableMarkup('ePaq 24'),
    'islas_express' => new TranslatableMarkup('Islas Express'),
    'islas_documentacion' => new TranslatableMarkup('Islas Documentación'),
    'islas_maritimo' => new TranslatableMarkup('Islas Marítimo'),
    'internacional_express' => new TranslatableMarkup('Internacional Express'),
    'internacional_estandar' => new TranslatableMarkup('Internacional Estándar'),
    'entrega_plus' => new TranslatableMarkup('Entrega Plus'),
    'campana' => new TranslatableMarkup('Campaña'),
    'portugal_optica' => new TranslatableMarkup('Portugal Óptica'),
    'paqueteria_optica' => new TranslatableMarkup('Paquetería Óptica'),
    'paq24_oficina' => new TranslatableMarkup('Paq 24 Oficina Elegida'),
    'paqpunto' => new TranslatableMarkup('PaqPunto'),
    'paq_ecommerce' => new TranslatableMarkup('PaqEcommerce'),
    'baleares_express' => new TranslatableMarkup('Baleares Express'),
    'canarias_express' => new TranslatableMarkup('Canarias Express'),
    'canarias_aereo' => new TranslatableMarkup('Canarias Aéreo'),
    'canarias_maritimo' => new TranslatableMarkup('Canarias Marítimo'),
  ],
)]
final class CorreosExpress extends ShippingMethodBase implements SupportsTrackingInterface {

  /**
   * URL pública de seguimiento, con el número al final.
   */
  public const URL_SEGUIMIENTO = 'https://s.correosexpress.com/c?n=';

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   *   Configuración de la instancia del plugin.
   * @param string $plugin_id
   *   Identificador del plugin.
   * @param mixed $plugin_definition
   *   Definición del plugin.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   Gestor de tipos de paquete.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   Gestor de workflows.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManagerInterface $package_type_manager,
    WorkflowManagerInterface $workflow_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);

    // El cliente ve el nombre comercial de la tienda ("Envío España
    // peninsular"), no el del producto del transportista ("Paq 24"). El
    // identificador del servicio no cambia, así que el back-office sigue
    // sabiendo con qué producto se despacha.
    $servicio = $this->servicio();
    $publica = trim((string) $this->configuration['etiqueta_publica']);
    if ($servicio !== NULL && $publica !== '') {
      $this->services[$servicio->value] = new ShippingService($servicio->value, $publica);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Contenedor de servicios.
   * @param array<string, mixed> $configuration
   *   Configuración de la instancia del plugin.
   * @param string $plugin_id
   *   Identificador del plugin.
   * @param mixed $plugin_definition
   *   Definición del plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   La configuración por defecto del método de envío.
   */
  public function defaultConfiguration(): array {
    return [
      'etiqueta_publica' => '',
      'descripcion' => '',
      'importe' => NULL,
      'default_package_type' => 'pronens_caja_estandar',
    ] + parent::defaultConfiguration();
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
   *   El formulario con los ajustes del método de envío.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Fuera las casillas de la clase base: aquí se elige un solo producto.
    unset($form['services']);

    $importe = $this->configuration['importe'];
    // El elemento plugin_select entrega el precio a medias en algunos pasos.
    if (is_array($importe) && !isset($importe['number'], $importe['currency_code'])) {
      $importe = NULL;
    }

    $form['servicio'] = [
      '#type' => 'select',
      '#title' => $this->t('Producto de Correos Express'),
      '#description' => $this->t('Con qué producto se despacha este método de envío. Entre paréntesis, el código que viaja en el alta de la expedición. Confirma con tu comercial cuáles tienes contratados.'),
      '#options' => ServicioCex::opciones(),
      '#default_value' => ($this->servicio() ?? ServicioCex::Paq24)->value,
      '#required' => TRUE,
    ];
    $form['etiqueta_publica'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre que ve el cliente'),
      '#description' => $this->t('Se muestra en el checkout en lugar del nombre del producto del transportista.'),
      '#default_value' => $this->configuration['etiqueta_publica'],
      '#required' => TRUE,
    ];
    $form['descripcion'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Detalle para el cliente'),
      '#description' => $this->t('Texto opcional bajo el nombre, por ejemplo el plazo de entrega.'),
      '#default_value' => $this->configuration['descripcion'],
    ];
    $form['importe'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Importe del envío'),
      '#description' => $this->t('Correos Express no tiene consulta de tarifas, así que el precio se fija aquí. Las zonas se siguen definiendo con las condiciones de este método.'),
      '#default_value' => $importe,
      '#required' => TRUE,
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getErrors()) {
      $valores = $form_state->getValue($form['#parents']);
      $servicio = (string) ($valores['servicio'] ?? ServicioCex::Paq24->value);

      // La clase base espera 'services' con la forma de un grupo de casillas.
      // Se le da el producto elegido con esa forma para no duplicar aquí el
      // guardado del tipo de paquete ni del workflow.
      $form_state->setValue(
        array_merge($form['#parents'], ['services']),
        [$servicio => $servicio],
      );

      $this->configuration['etiqueta_publica'] = (string) ($valores['etiqueta_publica'] ?? '');
      $this->configuration['descripcion'] = (string) ($valores['descripcion'] ?? '');
      $this->configuration['importe'] = $valores['importe'] ?? NULL;
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, \Drupal\commerce_shipping\ShippingRate>
   *   La única tarifa del método, o un array vacío si está sin configurar.
   */
  public function calculateRates(ShipmentInterface $shipment): array {
    $servicio = $this->servicio();
    $importe = $this->configuration['importe'];
    if ($servicio === NULL || !is_array($importe)) {
      return [];
    }

    return [
      new ShippingRate([
        'shipping_method_id' => $this->parentEntity->id(),
        'service' => $this->services[$servicio->value],
        'amount' => Price::fromArray($importe),
        'description' => (string) $this->configuration['descripcion'],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate): void {
    parent::selectRate($shipment, $rate);

    // Con esto el back-office ya sabe qué producto hay que despachar, sin tener
    // que volver a mirar la configuración del método de envío.
    $servicio = ServicioCex::tryFrom($rate->getService()->getId());
    if ($servicio !== NULL) {
      $shipment->setData('cex_servicio', $servicio->value);
      $shipment->setData('cex_producto', $servicio->codigoProducto());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies(ShipmentInterface $shipment): bool {
    $servicio = $this->servicio();
    if ($servicio === NULL) {
      return FALSE;
    }

    $pais = $this->paisDestino($shipment);
    if ($pais !== NULL && !$servicio->admitePais($pais)) {
      return FALSE;
    }

    $peso = $shipment->getWeight();
    if ($peso !== NULL) {
      $gramos = (float) $peso->convert(WeightUnit::GRAM)->getNumber();
      if ($gramos > $servicio->pesoMaximoGramos()) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * El docblock se repite porque el de la interfaz de contrib escribe en
   * minúscula el espacio de nombres de Url y el análisis estático lo marca.
   *
   * @return \Drupal\Core\Url|null
   *   Enlace público de seguimiento, o NULL si el envío no tiene expedición.
   */
  public function getTrackingUrl(ShipmentInterface $shipment): ?Url {
    $codigo = trim((string) $shipment->getTrackingCode());
    if ($codigo === '') {
      return NULL;
    }

    return Url::fromUri(self::URL_SEGUIMIENTO . $codigo);
  }

  /**
   * Producto de Correos Express configurado en este método.
   */
  public function servicio(): ?ServicioCex {
    foreach ($this->configuration['services'] as $id) {
      $servicio = ServicioCex::tryFrom((string) $id);
      if ($servicio !== NULL) {
        return $servicio;
      }
    }

    return NULL;
  }

  /**
   * País de destino del envío, si ya se conoce la dirección.
   */
  private function paisDestino(ShipmentInterface $shipment): ?string {
    $perfil = $shipment->getShippingProfile();
    if ($perfil === NULL || !$perfil->hasField('address') || $perfil->get('address')->isEmpty()) {
      return NULL;
    }
    $pais = $perfil->get('address')->first()?->get('country_code')?->getValue();

    return is_string($pais) && $pais !== '' ? $pais : NULL;
  }

}
