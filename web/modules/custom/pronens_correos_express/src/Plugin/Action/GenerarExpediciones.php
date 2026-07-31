<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Da de alta las expediciones de varios pedidos a la vez.
 *
 * Aparece sola en el desplegable de operaciones de /admin/commerce/orders: esa
 * vista ya trae el formulario masivo con todas las acciones habilitadas, así
 * que no hay que tocar su configuración.
 *
 * La acción no llama a la API: guarda la selección y lleva a un formulario de
 * confirmación donde se revisan pesos y bultos antes de crear nada. Dar de alta
 * es irreversible, así que no puede ser un clic en un desplegable.
 */
#[Action(
  id: 'pronens_cex_generar_expediciones',
  label: new TranslatableMarkup('Generar expediciones de Correos Express'),
  type: 'commerce_order',
  confirm_form_route_name: 'pronens_correos_express.generar_multiple',
)]
final class GenerarExpediciones extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * Colección del almacén temporal donde viaja la selección.
   */
  public const COLECCION = 'pronens_cex_expediciones_multiples';

  /**
   * Constructor.
   *
   * @param array<string, mixed> $configuration
   *   Configuración de la instancia del plugin.
   * @param string $plugin_id
   *   Identificador del plugin.
   * @param mixed $plugin_definition
   *   Definición del plugin.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   Factoría del almacén temporal privado.
   * @param \Drupal\Core\Session\AccountInterface $usuarioActual
   *   Usuario que ejecuta la acción.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    // Protegidas y no privadas: PluginBase usa DependencySerializationTrait,
    // que no admite propiedades privadas.
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly AccountInterface $usuarioActual,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('tempstore.private'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int, \Drupal\Core\Entity\EntityInterface> $entities
   *   Pedidos seleccionados.
   */
  public function executeMultiple(array $entities): void {
    $seleccion = [];
    foreach ($entities as $entidad) {
      $seleccion[] = (int) $entidad->id();
    }

    $this->tempStoreFactory
      ->get(self::COLECCION)
      ->set((string) $this->usuarioActual->id(), $seleccion);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Pedido sobre el que se ejecuta la acción.
   */
  public function execute($entity = NULL): void {
    if ($entity instanceof EntityInterface) {
      $this->executeMultiple([$entity]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $cuenta = $account ?? $this->usuarioActual;
    $permitido = $cuenta->hasPermission('generar expediciones correos express')
      && $object->access('view', $cuenta);

    return $return_as_object ? AccessResult::allowedIf($permitido) : $permitido;
  }

}
