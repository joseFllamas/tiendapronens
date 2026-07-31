<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Plugin\QueueWorker;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\GestorExpediciones;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consulta el seguimiento de un envío en Correos Express.
 */
#[QueueWorker(
  id: 'pronens_correos_express_seguimiento',
  title: new TranslatableMarkup('Seguimiento de Correos Express'),
  cron: ['time' => 30],
)]
final class SincronizarSeguimiento extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructor.
   *
   * @param array<string, mixed> $configuration
   *   Configuración de la instancia del plugin.
   * @param string $plugin_id
   *   Identificador del plugin.
   * @param mixed $plugin_definition
   *   Definición del plugin.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Gestor de entidades.
   * @param \Drupal\pronens_correos_express\GestorExpediciones $gestorExpediciones
   *   Orquestador de expediciones.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Factoría de configuración.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Servicio de tiempo.
   * @param \Psr\Log\LoggerInterface $logger
   *   Canal de registro.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    // Protegidas y no privadas: PluginBase usa DependencySerializationTrait,
    // que no admite propiedades privadas.
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly GestorExpediciones $gestorExpediciones,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TimeInterface $time,
    protected readonly LoggerInterface $logger,
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
      $container->get('entity_type.manager'),
      $container->get(GestorExpediciones::class),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('logger.channel.pronens_correos_express'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_array($data) || !isset($data['shipment_id'])) {
      return;
    }

    $envio = $this->entityTypeManager
      ->getStorage('commerce_shipment')
      ->load($data['shipment_id']);
    if (!$envio instanceof ShipmentInterface) {
      return;
    }

    // Un envío entregado, anulado o devuelto ya no cambia: no se vuelve a
    // preguntar por él.
    if ($this->gestorExpediciones->seguimientoTerminado($envio)) {
      return;
    }
    if ($this->consultadoHacePoco($envio)) {
      return;
    }

    try {
      $this->gestorExpediciones->sincronizarSeguimiento($envio);
    }
    catch (CorreosExpressException $e) {
      // Un fallo de seguimiento no debe parar la cola: el envío se volverá a
      // encolar en la siguiente ejecución.
      $this->logger->warning('No se pudo consultar el seguimiento del envío @envio: @mensaje', [
        '@envio' => $envio->id(),
        '@mensaje' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Indica si este envío ya se consultó dentro del intervalo configurado.
   */
  private function consultadoHacePoco(ShipmentInterface $envio): bool {
    $ultima = $envio->getData(GestorExpediciones::CLAVE_ULTIMA_CONSULTA);
    if (!is_numeric($ultima)) {
      return FALSE;
    }

    $horas = (int) ($this->configFactory
      ->get('pronens_correos_express.settings')
      ->get('seguimiento.intervalo_horas') ?? 6);

    return ($this->time->getRequestTime() - (int) $ultima) < max(1, $horas) * 3600;
  }

}
