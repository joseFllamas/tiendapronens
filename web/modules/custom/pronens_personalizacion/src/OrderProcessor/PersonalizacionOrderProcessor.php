<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\OrderProcessor;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\pronens_personalizacion\SurchargeCalculator;

/**
 * Añade a cada línea con bordado su recargo, como ajuste de tipo tarifa.
 *
 * Se aplica como ajuste y no subiendo el precio unitario para que el cliente
 * vea desglosado qué paga por el bordado, y para que la contabilidad lo pueda
 * separar del precio de la prenda.
 */
final class PersonalizacionOrderProcessor implements OrderProcessorInterface {

  use StringTranslationTrait;

  /**
   * Campo de la línea de pedido con el texto a bordar.
   */
  public const CAMPO_TEXTO = 'field_texto_bordado';

  /**
   * Campo del producto con su recargo propio, que manda sobre el general.
   */
  public const CAMPO_RECARGO_PRODUCTO = 'field_recargo';

  /**
   * Campo del producto con el modo de personalización.
   */
  public const CAMPO_MODO = 'field_modo_personalizacion';

  /**
   * Identificador del origen del ajuste, para poder reconocerlo y reemplazarlo.
   */
  private const SOURCE_ID = 'pronens_personalizacion';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly SurchargeCalculator $calculator,
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    $por_defecto = $this->recargoPorDefecto();

    foreach ($order->getItems() as $item) {
      // El refresco del pedido vuelve a pasar por aquí, así que primero se
      // retira el ajuste anterior para no acumular recargos.
      $this->removeExistingAdjustment($item);

      $texto = $this->texto($item);
      if (!$this->calculator->hasPersonalization($texto)) {
        continue;
      }

      // La regla de que la inicial no se cobra vive en el calculador, con el
      // resto de reglas de precio y con sus pruebas unitarias.
      $recargo = $this->calculator->calculate(
        TRUE,
        $this->recargoDelProducto($item),
        $por_defecto,
        (int) $item->getQuantity(),
        $this->esModoInicial($item),
      );
      if ($recargo === NULL) {
        continue;
      }

      $item->addAdjustment(new Adjustment([
        'type' => 'fee',
        'label' => $this->t('Bordado personalizado'),
        'amount' => $recargo,
        'source_id' => self::SOURCE_ID,
      ]));
    }
  }

  /**
   * Si el producto de la línea se personaliza con una inicial.
   */
  private function esModoInicial(OrderItemInterface $item): bool {
    $variacion = $item->getPurchasedEntity();
    if (!$variacion instanceof ProductVariationInterface) {
      return FALSE;
    }
    $producto = $variacion->getProduct();
    if ($producto === NULL || !$producto->hasField(self::CAMPO_MODO)) {
      return FALSE;
    }

    return (string) $producto->get(self::CAMPO_MODO)->value === 'inicial';
  }

  /**
   * Recargo general de la tienda, o NULL si no está configurado.
   */
  private function recargoPorDefecto(): ?Price {
    $config = $this->configFactory->get('pronens_personalizacion.settings')->get('recargo');
    if (!is_array($config) || !isset($config['number'], $config['currency_code'])) {
      return NULL;
    }
    if ((string) $config['number'] === '') {
      return NULL;
    }
    return new Price((string) $config['number'], (string) $config['currency_code']);
  }

  /**
   * Recargo propio del producto de la línea, si lo tiene.
   */
  private function recargoDelProducto(OrderItemInterface $item): ?Price {
    $variacion = $item->getPurchasedEntity();
    // getPurchasedEntity() devuelve PurchasableEntityInterface, que no tiene
    // getProduct(): solo lo tiene la variación de producto. Sin esta comprobación
    // el procesador reventaría con cualquier otro tipo de entidad comprable.
    if (!$variacion instanceof ProductVariationInterface) {
      return NULL;
    }
    $producto = $variacion->getProduct();
    if ($producto === NULL || !$producto->hasField(self::CAMPO_RECARGO_PRODUCTO)) {
      return NULL;
    }
    $campo = $producto->get(self::CAMPO_RECARGO_PRODUCTO);
    if ($campo->isEmpty()) {
      return NULL;
    }
    // toPrice() lo aporta PriceItem de commerce, no FieldItemInterface.
    $item_precio = $campo->first();
    return $item_precio instanceof PriceItem ? $item_precio->toPrice() : NULL;
  }

  /**
   * Texto a bordar de la línea, o NULL si el campo no existe o está vacío.
   */
  private function texto(OrderItemInterface $item): ?string {
    if (!$item->hasField(self::CAMPO_TEXTO)) {
      return NULL;
    }
    $campo = $item->get(self::CAMPO_TEXTO);
    return $campo->isEmpty() ? NULL : (string) $campo->value;
  }

  /**
   * Retira el ajuste que puso una pasada anterior de este procesador.
   */
  private function removeExistingAdjustment(OrderItemInterface $item): void {
    foreach ($item->getAdjustments(['fee']) as $ajuste) {
      if ($ajuste->getSourceId() === self::SOURCE_ID) {
        $item->removeAdjustment($ajuste);
      }
    }
  }

}
