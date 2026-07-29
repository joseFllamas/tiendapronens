<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\OrderProcessor;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\pronens_personalizacion\ExtrasCalculator;
use Drupal\taxonomy\TermInterface;

/**
 * Añade a cada línea un ajuste por cada extra elegido.
 *
 * Mismo criterio que el recargo del bordado: ajuste de tipo tarifa en vez de
 * subir el precio unitario, para que el cliente vea el desglose y para que la
 * contabilidad pueda separar el complemento del precio de la prenda. Un ajuste
 * por extra, con su nombre como etiqueta, para que en el pedido se lea qué se
 * cobró y por qué.
 */
final class ExtrasOrderProcessor implements OrderProcessorInterface {

  /**
   * Campo de la línea de pedido con los extras elegidos.
   */
  public const CAMPO_EXTRAS = 'field_extras';

  /**
   * Campo de la línea con el texto que pide el extra.
   */
  public const CAMPO_TEXTO = 'field_extras_texto';

  /**
   * Campo del extra con su precio unitario.
   */
  private const CAMPO_PRECIO = 'field_precio';

  /**
   * Campo del extra que indica si necesita un texto del cliente.
   */
  private const CAMPO_PIDE_TEXTO = 'field_pide_texto';

  /**
   * Prefijo del origen del ajuste, para reconocerlo y reemplazarlo.
   */
  private const SOURCE_PREFIX = 'pronens_extra:';

  public function __construct(
    private readonly ExtrasCalculator $calculator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    foreach ($order->getItems() as $item) {
      // El refresco del pedido vuelve a pasar por aquí: primero se retiran los
      // ajustes de la pasada anterior para no acumularlos.
      $this->removeExistingAdjustments($item);

      $texto = $this->texto($item);
      foreach ($this->extras($item) as $extra) {
        // Un extra que pide texto y no lo tiene no se cobra: el taller no
        // sabría qué poner. La validación del formulario ya lo impide, pero un
        // pedido creado por otra vía (admin, API) no pasa por ahí.
        if ($this->pideTexto($extra) && !$this->calculator->hasText($texto)) {
          continue;
        }

        $importe = $this->calculator->calculate(
          $this->precio($extra),
          (int) $item->getQuantity(),
        );
        if ($importe === NULL) {
          continue;
        }

        $item->addAdjustment(new Adjustment([
          'type' => 'fee',
          'label' => (string) $extra->label(),
          'amount' => $importe,
          'source_id' => self::SOURCE_PREFIX . $extra->id(),
        ]));
      }
    }
  }

  /**
   * Extras elegidos en la línea.
   *
   * @return array<int, \Drupal\taxonomy\TermInterface>
   *   Los términos de extra.
   */
  private function extras(OrderItemInterface $item): array {
    if (!$item->hasField(self::CAMPO_EXTRAS)) {
      return [];
    }
    $campo = $item->get(self::CAMPO_EXTRAS);
    if (!$campo instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    return array_filter(
      $campo->referencedEntities(),
      static fn ($extra) => $extra instanceof TermInterface
    );
  }

  /**
   * Precio unitario de un extra, o NULL si no lo tiene.
   */
  private function precio(TermInterface $extra): ?Price {
    if (!$extra->hasField(self::CAMPO_PRECIO) || $extra->get(self::CAMPO_PRECIO)->isEmpty()) {
      return NULL;
    }
    // toPrice() lo aporta PriceItem de commerce, no FieldItemInterface.
    $primero = $extra->get(self::CAMPO_PRECIO)->first();

    return $primero instanceof PriceItem ? $primero->toPrice() : NULL;
  }

  /**
   * Si el extra necesita un texto del cliente.
   */
  private function pideTexto(TermInterface $extra): bool {
    return $extra->hasField(self::CAMPO_PIDE_TEXTO)
      && !$extra->get(self::CAMPO_PIDE_TEXTO)->isEmpty()
      && (bool) $extra->get(self::CAMPO_PIDE_TEXTO)->value;
  }

  /**
   * Texto del extra en la línea, o NULL.
   */
  private function texto(OrderItemInterface $item): ?string {
    if (!$item->hasField(self::CAMPO_TEXTO)) {
      return NULL;
    }
    $campo = $item->get(self::CAMPO_TEXTO);

    return $campo->isEmpty() ? NULL : (string) $campo->value;
  }

  /**
   * Retira los ajustes que puso una pasada anterior de este procesador.
   */
  private function removeExistingAdjustments(OrderItemInterface $item): void {
    foreach ($item->getAdjustments(['fee']) as $ajuste) {
      if (str_starts_with((string) $ajuste->getSourceId(), self::SOURCE_PREFIX)) {
        $item->removeAdjustment($ajuste);
      }
    }
  }

}
