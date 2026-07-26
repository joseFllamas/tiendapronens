<?php

declare(strict_types=1);

namespace Drupal\pronens_migrate\EventSubscriber;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_stock\StockTransactionsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Da de alta el stock migrado como transacción de entrada.
 *
 * commerce_stock_local no guarda el nivel en el campo: lo lleva en un libro de
 * transacciones, en commerce_stock_transaction. Escribir field_stock desde una
 * migración no tiene ningún efecto y el catálogo entero quedaría a cero sin
 * ningún error visible, así que el alta se hace aquí, después de guardar la
 * variación.
 */
final class StockTransactionSubscriber implements EventSubscriberInterface {

  /**
   * Migración cuyas filas llevan stock que dar de alta.
   */
  private const MIGRACION = 'pronens_variacion';

  public function __construct(
    private readonly StockTransactionsInterface $stockServiceManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::POST_ROW_SAVE => ['onPostRowSave'],
    ];
  }

  /**
   * Crea la transacción de stock inicial de una variación recién migrada.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event): void {
    if ($event->getMigration()->id() !== self::MIGRACION) {
      return;
    }

    $cantidad = (float) $event->getRow()->getSourceProperty('stock');
    if ($cantidad <= 0) {
      // Sin stock no hay nada que registrar: el nivel ya es cero por defecto.
      return;
    }

    $ids = $event->getDestinationIdValues();
    $variation_id = reset($ids);
    if ($variation_id === FALSE) {
      return;
    }

    $variation = $this->entityTypeManager
      ->getStorage('commerce_product_variation')
      ->load($variation_id);
    if (!$variation instanceof PurchasableEntityInterface) {
      return;
    }

    $location = $this->defaultLocationId();
    if ($location === NULL) {
      $this->logger->error('No hay ninguna ubicación de stock definida, el stock migrado se pierde.');
      return;
    }

    $this->stockServiceManager->receiveStock(
      $variation,
      $location,
      // La columna location_zone de la tabla no admite NULL.
      '',
      $cantidad,
      // Coste unitario desconocido: el D7 no guardaba coste de compra. La
      // columna admite NULL, aunque el docblock de contrib lo tipe como float.
      NULL,
      'EUR',
      'Stock inicial migrado desde el Drupal 7.',
    );
  }

  /**
   * Devuelve la ubicación de stock a usar, o NULL si no hay ninguna.
   */
  private function defaultLocationId(): ?int {
    $ubicaciones = $this->entityTypeManager
      ->getStorage('commerce_stock_location')
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('location_id')
      ->range(0, 1)
      ->execute();

    $id = reset($ubicaciones);
    return $id === FALSE ? NULL : (int) $id;
  }

}
