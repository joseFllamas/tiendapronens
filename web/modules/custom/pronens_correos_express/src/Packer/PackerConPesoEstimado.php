<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Packer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Packer\DefaultPacker;
use Drupal\commerce_shipping\ProposedShipment;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\physical\Calculator;
use Drupal\pronens_correos_express\Peso\ResolutorPesos;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Empaqueta el pedido poniendo un peso estimado donde no hay dato.
 *
 * Es igual que el empaquetador de Commerce salvo en una línea: donde este pone
 * cero gramos si la variación no tiene peso, aquí entra la estimación por tipo
 * de producto. Con las 1096 variaciones vacías, esa línea es la diferencia
 * entre una expedición con peso y una que Correos Express rechaza.
 *
 * Se hace en el empaquetador y no solo al construir el payload porque así el
 * peso queda dentro del envío de Commerce, y por tanto disponible para las
 * tarifas por tramo, para la condición de peso de commerce_shipping y para la
 * consulta de puntos de recogida, no solo para la llamada al alta.
 *
 * Tampoco escribe en la variación: el empaquetador de Commerce le asigna el
 * cero a la entidad en memoria, y aquí se prefiere no tocarla.
 */
final class PackerConPesoEstimado extends DefaultPacker {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    TranslationInterface $string_translation,
    private readonly ResolutorPesos $resolutorPesos,
  ) {
    parent::__construct($entity_type_manager, $string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public function pack(OrderInterface $order, ProfileInterface $shipping_profile) {
    $estimador = $this->resolutorPesos->estimadorPeso();
    $items = [];

    foreach ($order->getItems() as $order_item) {
      // Líneas a medio crear o mal formadas: las salta también el empaquetador
      // de Commerce.
      if (!$order_item->getUnitPrice() || $order_item->isNew()) {
        continue;
      }
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity === NULL || !$purchased_entity->hasField('weight')) {
        continue;
      }
      $quantity = $order_item->getQuantity();
      if (Calculator::compare($quantity, '0') === 0) {
        continue;
      }

      $items[] = new ShipmentItem([
        'order_item_id' => $order_item->id(),
        'title' => $order_item->getTitle(),
        'quantity' => $quantity,
        'weight' => $estimador->pesoLinea(
          $this->resolutorPesos->pesoGuardado($purchased_entity),
          $quantity,
          $this->resolutorPesos->gramosEstimados($purchased_entity),
        ),
        'declared_value' => $order_item->getUnitPrice()->multiply($quantity),
      ]);
    }

    if ($items === []) {
      return [];
    }

    return [
      new ProposedShipment([
        'type' => $this->getShipmentType($order),
        'order_id' => $order->id(),
        'title' => $this->t('Shipment #1'),
        'items' => $items,
        'shipping_profile' => $shipping_profile,
      ]),
    ];
  }

}
