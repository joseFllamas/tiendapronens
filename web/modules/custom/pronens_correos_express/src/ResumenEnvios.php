<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pronens_correos_express\Catalogo\CalculadoraSituacion;
use Drupal\pronens_correos_express\Plugin\Commerce\ShippingMethod\CorreosExpress;

/**
 * Lee de las entidades lo que la calculadora necesita y devuelve el resumen.
 *
 * Es la única frontera entre las reglas puras de CalculadoraSituacion y las
 * entidades de Commerce, igual que ResolutorPesos lo es para los pesos.
 */
final class ResumenEnvios {

  public function __construct(
    private readonly GestorExpediciones $gestorExpediciones,
    private readonly CalculadoraSituacion $calculadora,
    private readonly EntityTypeManagerInterface $gestorEntidades,
    private readonly EntityRepositoryInterface $repositorioEntidades,
  ) {}

  /**
   * Resumen del envío de un pedido.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $pedido
   *   El pedido.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $metadatos
   *   Si se pasa, se le anotan los envíos leídos: la fila deja de valer cuando
   *   cambia el envío, no solo cuando cambia el pedido.
   */
  public function deUnPedido(OrderInterface $pedido, ?CacheableMetadata $metadatos = NULL): ResumenEnvio {
    $envios = $this->enviosDe($pedido);
    $descripciones = [];
    $metodos = [];
    $expediciones = [];

    foreach ($envios as $envio) {
      $metadatos?->addCacheableDependency($envio);

      $descripciones[] = [
        'estado' => $envio->getState()->getId(),
        'expedido' => $this->gestorExpediciones->estaExpedido($envio),
        'seExpide' => $this->gestorExpediciones->seExpide($envio),
        'situacion' => $this->situacionDeSeguimiento($envio),
      ];

      $metodo = $envio->getShippingMethod();
      if ($metodo !== NULL) {
        $metadatos?->addCacheableDependency($metodo);
        // El método se carga por referencia, así que llega en su idioma por
        // defecto: sin esto la ficha en francés diría «Recoger en Pronens».
        $metodos[(string) $metodo->id()] = (string) $this->repositorioEntidades
          ->getTranslationFromContext($metodo)
          ->label();
      }

      // El código se lee de `tracking_code` y no solo de los datos de la
      // expedición: hay envíos con seguimiento puesto a mano (los de antes de
      // esta integración, o los de otro transportista) y el cliente también
      // quiere verlos. Que venga de Correos Express es lo que decide si hay
      // enlace de seguimiento y etiqueta.
      $codigo = trim((string) ($envio->getTrackingCode() ?? ''));
      if ($codigo !== '') {
        $deCorreosExpress = $this->gestorExpediciones->expedicion($envio) === $codigo;
        $expediciones[] = [
          'codigo' => $codigo,
          'url' => $deCorreosExpress ? CorreosExpress::URL_SEGUIMIENTO . $codigo : NULL,
          'envio' => (string) $envio->id(),
          'pedido' => (string) $pedido->id(),
        ];
      }
    }

    return new ResumenEnvio(
      $this->calculadora->calcular($pedido->getState()->getId(), $descripciones),
      array_values($metodos),
      $expediciones,
    );
  }

  /**
   * Carga de golpe los envíos de varios pedidos.
   *
   * Sin esto, una lista de 50 pedidos hace 50 consultas de un envío cada una.
   * Se leen los ids del campo sin resolver la referencia (getValue() no carga
   * nada) y se piden todos juntos, de modo que el deUnPedido() de cada fila ya
   * los encuentra en la caché estática del almacén.
   *
   * @param array<int, \Drupal\commerce_order\Entity\OrderInterface> $pedidos
   *   Los pedidos de la lista.
   */
  public function precargar(array $pedidos): void {
    $ids = [];
    foreach ($pedidos as $pedido) {
      if (!$pedido->hasField('shipments')) {
        continue;
      }
      foreach ($pedido->get('shipments')->getValue() as $valor) {
        if (isset($valor['target_id'])) {
          $ids[] = $valor['target_id'];
        }
      }
    }
    if ($ids === []) {
      return;
    }

    $this->gestorEntidades->getStorage('commerce_shipment')->loadMultiple($ids);
  }

  /**
   * Los envíos de un pedido.
   *
   * @return array<int, \Drupal\commerce_shipping\Entity\ShipmentInterface>
   *   Los envíos, o vacío si el pedido no tiene el campo (no lleva envío).
   */
  private function enviosDe(OrderInterface $pedido): array {
    if (!$pedido->hasField('shipments')) {
      return [];
    }

    return array_values(array_filter(
      $pedido->get('shipments')->referencedEntities(),
      static fn ($envio): bool => $envio instanceof ShipmentInterface,
    ));
  }

  /**
   * Lo último que informó el seguimiento de Correos Express, si informó.
   */
  private function situacionDeSeguimiento(ShipmentInterface $envio): ?string {
    $estado = $envio->getData(GestorExpediciones::CLAVE_ULTIMO_ESTADO);

    return is_array($estado) && isset($estado['situacion'])
      ? (string) $estado['situacion']
      : NULL;
  }

}
