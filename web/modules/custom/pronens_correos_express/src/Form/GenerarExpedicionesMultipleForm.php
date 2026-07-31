<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\Api\Entorno;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\pronens_correos_express\GestorExpediciones;
use Drupal\pronens_correos_express\OpcionesExpedicion;
use Drupal\pronens_correos_express\Payload\Normalizador;
use Drupal\pronens_correos_express\Plugin\Action\GenerarExpediciones;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirma y ejecuta el alta de varias expediciones.
 *
 * Una fila por envío, con el peso y los bultos editables, y un lote de Drupal
 * que hace una llamada por envío. Un error en una fila no aborta el resto: cada
 * alta es independiente y repetir el lote no arreglaría nada, porque los que ya
 * salieron no se pueden anular.
 */
final class GenerarExpedicionesMultipleForm extends ConfirmFormBase {

  public function __construct(
    // Protegidas y no privadas: DependencySerializationTrait, que usan los
    // formularios al serializarse, no soporta propiedades privadas.
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected AccountInterface $usuarioActual,
    protected GestorExpediciones $gestorExpediciones,
    protected Normalizador $normalizador,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('current_user'),
      $container->get(GestorExpediciones::class),
      $container->get(Normalizador::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pronens_correos_express_generar_multiple';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Dar de alta las expediciones de Correos Express');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.commerce_order.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Dar de alta');
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
    $envios = $this->enviosPendientes();

    if ($envios === []) {
      $form['vacio'] = [
        '#type' => 'item',
        '#markup' => $this->t('Ninguno de los pedidos seleccionados tiene un envío pendiente de expedir. Los que ya tienen expedición no se vuelven a dar de alta.'),
      ];
      $form['volver'] = [
        '#type' => 'link',
        '#title' => $this->t('Volver a los pedidos'),
        '#url' => $this->getCancelUrl(),
        '#attributes' => ['class' => ['button']],
      ];

      return $form;
    }

    $entorno = Entorno::desdeConfiguracion(
      $this->config('pronens_correos_express.settings')->get('entorno'),
    );
    if ($entorno->esProduccion()) {
      $this->messenger()->addWarning($this->t('Estás en producción: se crearán @numero envíos reales que Correos Express factura, y la API no permite anularlos.', [
        '@numero' => count($envios),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('Estás en preproducción: las expediciones que se creen no son reales.'));
    }

    $filas = [];
    foreach ($envios as $id => $envio) {
      $pedido = $envio->getOrder();
      $filas[$id]['pedido'] = [
        '#plain_text' => (string) ($pedido?->getOrderNumber() ?? $id),
      ];
      $filas[$id]['destino'] = [
        '#plain_text' => $this->destino($envio),
      ];
      $filas[$id]['servicio'] = [
        '#type' => 'select',
        '#title' => $this->t('Producto'),
        '#title_display' => 'invisible',
        '#options' => ServicioCex::opciones(),
        '#default_value' => $this->gestorExpediciones->servicioPorDefecto($envio)->value,
      ];
      $filas[$id]['peso_kg'] = [
        '#type' => 'number',
        '#title' => $this->t('Peso'),
        '#title_display' => 'invisible',
        '#step' => 0.01,
        '#min' => 0.01,
        '#default_value' => $this->normalizador->kilos($envio->getWeight()),
      ];
      $filas[$id]['numero_bultos'] = [
        '#type' => 'number',
        '#title' => $this->t('Bultos'),
        '#title_display' => 'invisible',
        '#min' => 1,
        '#max' => 99,
        '#default_value' => 1,
      ];
    }

    $form['envios'] = $filas + [
      '#type' => 'table',
      '#header' => [
        $this->t('Pedido'),
        $this->t('Destino'),
        $this->t('Producto de Correos Express'),
        $this->t('Peso (kg)'),
        $this->t('Bultos'),
      ],
      '#tree' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
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
    $operaciones = [];
    foreach (($form_state->getValue('envios') ?? []) as $id => $valores) {
      $operaciones[] = [
        [self::class, 'procesarEnvio'],
        [
          (int) $id,
          (string) ($valores['servicio'] ?? ServicioCex::Paq24->value),
          (string) ($valores['peso_kg'] ?? '0.01'),
          (int) ($valores['numero_bultos'] ?? 1),
        ],
      ];
    }

    $this->tempStoreFactory
      ->get(GenerarExpediciones::COLECCION)
      ->delete((string) $this->usuarioActual->id());

    batch_set([
      'title' => $this->t('Dando de alta las expediciones'),
      'operations' => $operaciones,
      'finished' => [self::class, 'terminado'],
      'progress_message' => $this->t('Procesados @current de @total envíos.'),
    ]);
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Da de alta la expedición de un envío dentro del lote.
   *
   * @param int $envioId
   *   Identificador del envío.
   * @param string $servicio
   *   Producto de Correos Express elegido.
   * @param string $pesoKg
   *   Peso declarado, en kilos.
   * @param int $bultos
   *   Número de paquetes.
   * @param array<string, mixed>|\ArrayAccess<string, mixed> $context
   *   Contexto del lote.
   */
  public static function procesarEnvio(int $envioId, string $servicio, string $pesoKg, int $bultos, &$context): void {
    $context['results']['total'] = ($context['results']['total'] ?? 0) + 1;

    $envio = \Drupal::entityTypeManager()->getStorage('commerce_shipment')->load($envioId);
    if (!$envio instanceof ShipmentInterface) {
      return;
    }
    $producto = ServicioCex::tryFrom($servicio) ?? ServicioCex::Paq24;

    /** @var \Drupal\pronens_correos_express\GestorExpediciones $gestor */
    $gestor = \Drupal::service(GestorExpediciones::class);

    try {
      $respuesta = $gestor->generar($envio, new OpcionesExpedicion(
        servicio: $producto,
        numeroBultos: max(1, $bultos),
        pesoTotal: new Weight($pesoKg, WeightUnit::KILOGRAM),
      ));
      $context['results']['creadas'][] = $respuesta->expedicion;
      $context['message'] = t('Expedición @expedicion creada.', ['@expedicion' => $respuesta->expedicion]);
    }
    catch (CorreosExpressException $e) {
      // Se recoge el error por fila y el lote sigue: los envíos que ya salieron
      // no se pueden anular, así que abortar no arreglaría nada y dejaría el
      // resto sin despachar.
      $context['results']['errores'][] = t('Pedido @pedido: @mensaje', [
        '@pedido' => $envio->getOrder()?->getOrderNumber() ?? $envioId,
        '@mensaje' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Informa del resultado del lote.
   *
   * @param bool $exito
   *   Si el lote terminó sin excepciones.
   * @param array<string, mixed> $resultados
   *   Resultados acumulados.
   */
  public static function terminado(bool $exito, array $resultados): void {
    $mensajero = \Drupal::messenger();
    $creadas = $resultados['creadas'] ?? [];
    $errores = $resultados['errores'] ?? [];

    if ($creadas !== []) {
      $mensajero->addStatus(t('Creadas @numero expediciones de Correos Express.', [
        '@numero' => count($creadas),
      ]));
    }
    foreach ($errores as $error) {
      $mensajero->addError($error);
    }
    if ($creadas === [] && $errores === []) {
      $mensajero->addWarning(t('No se ha creado ninguna expedición.'));
    }
  }

  /**
   * Envíos de los pedidos seleccionados que todavía no están expedidos.
   *
   * @return array<int, \Drupal\commerce_shipping\Entity\ShipmentInterface>
   *   Los envíos, indexados por su identificador.
   */
  private function enviosPendientes(): array {
    $seleccion = $this->tempStoreFactory
      ->get(GenerarExpediciones::COLECCION)
      ->get((string) $this->usuarioActual->id());
    if (!is_array($seleccion) || $seleccion === []) {
      return [];
    }

    $pedidos = $this->entityTypeManager
      ->getStorage('commerce_order')
      ->loadMultiple($seleccion);

    $envios = [];
    foreach ($pedidos as $pedido) {
      if (!$pedido instanceof OrderInterface || !$pedido->hasField('shipments')) {
        continue;
      }
      foreach ($pedido->get('shipments')->referencedEntities() as $envio) {
        if ($envio instanceof ShipmentInterface && !$this->gestorExpediciones->estaExpedido($envio)) {
          $envios[(int) $envio->id()] = $envio;
        }
      }
    }

    return $envios;
  }

  /**
   * Resumen del destino de un envío, para reconocerlo en la tabla.
   */
  private function destino(ShipmentInterface $envio): string {
    $perfil = $envio->getShippingProfile();
    if ($perfil === NULL || !$perfil->hasField('address') || $perfil->get('address')->isEmpty()) {
      return (string) $this->t('sin dirección');
    }
    $direccion = $perfil->get('address')->first();

    return trim(sprintf(
      '%s %s, %s (%s)',
      (string) ($direccion?->get('given_name')?->getValue() ?? ''),
      (string) ($direccion?->get('family_name')?->getValue() ?? ''),
      (string) ($direccion?->get('locality')?->getValue() ?? ''),
      (string) ($direccion?->get('country_code')?->getValue() ?? ''),
    ));
  }

}
