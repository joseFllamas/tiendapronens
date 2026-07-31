<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Decide qué envíos hay que consultar en Correos Express y los encola.
 *
 * Limita el ritmo en tres niveles, porque cada envío es una llamada a la API y
 * una tienda con cuarenta envíos vivos no debe hacer cuarenta llamadas en cada
 * ejecución del cron:
 *
 * 1. Un intervalo mínimo entre ejecuciones, guardado en State.
 * 2. Un tope de envíos por ejecución, y solo los más antiguos por fecha de
 *    cambio, así que la rueda va pasando por todos.
 * 3. En el trabajador, un salto si ese envío ya se consultó hace poco.
 */
final class SincronizadorSeguimiento {

  /**
   * Nombre de la cola.
   */
  public const COLA = 'pronens_correos_express_seguimiento';

  /**
   * Clave de State con la marca de la última ejecución.
   */
  private const CLAVE_ULTIMA_EJECUCION = 'pronens_correos_express.ultima_sincronizacion';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    private readonly StateInterface $state,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Encola los envíos pendientes de consultar.
   *
   * @return int
   *   Cuántos envíos se han encolado.
   */
  public function encolar(): int {
    $configuracion = $this->configFactory->get('pronens_correos_express.settings')->get('seguimiento');
    if (($configuracion['activo'] ?? TRUE) !== TRUE) {
      return 0;
    }

    $intervalo = max(1, (int) ($configuracion['intervalo_horas'] ?? 6)) * 3600;
    $ultima = (int) $this->state->get(self::CLAVE_ULTIMA_EJECUCION, 0);
    $ahora = $this->time->getRequestTime();
    if ($ahora - $ultima < $intervalo) {
      return 0;
    }
    $this->state->set(self::CLAVE_ULTIMA_EJECUCION, $ahora);

    $ids = $this->enviosPendientes((int) ($configuracion['envios_por_ejecucion'] ?? 25));
    if ($ids === []) {
      return 0;
    }

    $cola = $this->queueFactory->get(self::COLA);
    foreach ($ids as $id) {
      $cola->createItem(['shipment_id' => $id]);
    }

    $this->logger->info('Encolados @numero envíos para consultar su seguimiento.', [
      '@numero' => count($ids),
    ]);

    return count($ids);
  }

  /**
   * Envíos que toca consultar.
   *
   * Solo los que ya tienen expedición y están preparados: un envío en borrador
   * no se ha dado de alta, y uno cancelado no se mueve. Se ordenan por fecha de
   * cambio ascendente, que es una columna indexada, así que el más olvidado es
   * el primero.
   *
   * @return list<int>
   *   Identificadores de envío.
   */
  private function enviosPendientes(int $limite): array {
    $ids = $this->entityTypeManager
      ->getStorage('commerce_shipment')
      ->getQuery()
      ->accessCheck(FALSE)
      ->exists('tracking_code')
      ->condition('state', 'ready')
      ->sort('changed', 'ASC')
      ->range(0, max(1, $limite))
      ->execute();

    return array_map('intval', array_values($ids));
  }

}
