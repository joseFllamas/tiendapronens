<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
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
    private readonly GestorExpediciones $gestorExpediciones,
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
   * Cuántos candidatos se leen por cada hueco que hay que llenar.
   *
   * El seguimiento terminado no se puede descartar en la consulta (vive en el
   * campo `data`, que es un blob serializado), así que se pide una ventana más
   * ancha que el tope y se filtra en PHP.
   */
  private const CANDIDATOS_POR_HUECO = 4;

  /**
   * Envíos que toca consultar.
   *
   * Los que tienen expedición y están preparados O enviados. Que incluya
   * `shipped` es lo que permite enterarse de la ENTREGA, y es el fallo que
   * tenía esto: al detectar que el paquete se movía, la sincronización aplica
   * la transición `ship` y el envío pasa a `shipped`; filtrando solo por
   * `ready`, ese envío no volvía a consultarse nunca y se quedaba clavado en
   * «en reparto» para siempre. Se vio en el pedido P-2026-0004, parado en el
   * estado del 4 de septiembre cuatro días después.
   *
   * Un borrador no se ha dado de alta y un cancelado no se mueve: esos siguen
   * fuera.
   *
   * Se ordenan por fecha de cambio ascendente, que es columna indexada, así que
   * el más olvidado es el primero, y se descartan en PHP los que ya han
   * terminado su recorrido (entregados, devueltos o anulados), que no cambian
   * más. Sin ese filtro los entregados ocuparían la cabeza de la lista para
   * siempre: su `changed` se queda quieto el día de la entrega mientras el de
   * los vivos avanza con cada consulta.
   *
   * @return list<int>
   *   Identificadores de envío.
   */
  private function enviosPendientes(int $limite): array {
    $limite = max(1, $limite);
    $ids = $this->entityTypeManager
      ->getStorage('commerce_shipment')
      ->getQuery()
      ->accessCheck(FALSE)
      ->exists('tracking_code')
      ->condition('state', ['ready', 'shipped'], 'IN')
      ->sort('changed', 'ASC')
      ->range(0, $limite * self::CANDIDATOS_POR_HUECO)
      ->execute();
    if ($ids === []) {
      return [];
    }

    $candidatos = $this->entityTypeManager
      ->getStorage('commerce_shipment')
      ->loadMultiple($ids);

    $pendientes = [];
    foreach ($candidatos as $envio) {
      if (!$envio instanceof ShipmentInterface) {
        continue;
      }
      if ($this->gestorExpediciones->seguimientoTerminado($envio)) {
        continue;
      }
      $pendientes[] = (int) $envio->id();
      if (count($pendientes) === $limite) {
        break;
      }
    }

    // Si la ventana entera eran envíos ya terminados puede haber vivos más allá
    // que se estén quedando sin consultar. A este volumen no pasa, pero si
    // algún día pasa conviene verlo en el registro y no descubrirlo por un
    // cliente preguntando dónde está su paquete.
    if ($pendientes === [] && count($ids) >= $limite * self::CANDIDATOS_POR_HUECO) {
      $this->logger->warning('Los @candidatos envíos más antiguos ya han terminado su seguimiento y no se ha consultado ninguno. Conviene subir el tope de envíos por ejecución en /admin/commerce/config/correos-express.', [
        '@candidatos' => count($ids),
      ]);
    }

    return $pendientes;
  }

}
