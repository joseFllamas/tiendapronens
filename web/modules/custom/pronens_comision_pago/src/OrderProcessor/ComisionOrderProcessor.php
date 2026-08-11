<?php

declare(strict_types=1);

namespace Drupal\pronens_comision_pago\OrderProcessor;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\pronens_comision_pago\ComisionCalculator;

/**
 * Suma al pedido la comisión de la pasarela elegida, como ajuste de tarifa.
 *
 * Un solo ajuste a nivel de pedido, no uno por línea: la comisión no depende de
 * lo que se compre, solo de cómo se paga. Eso lo hace mucho más simple que el
 * recargo del bordado y evita repartir céntimos entre líneas.
 */
final class ComisionOrderProcessor implements OrderProcessorInterface {

  use StringTranslationTrait;

  /**
   * Identificador del origen del ajuste, para reconocerlo y reemplazarlo.
   */
  public const SOURCE_ID = 'pronens_comision_pago';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ComisionCalculator $calculator,
    private readonly RounderInterface $rounder,
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    // El refresco vuelve a pasar por aquí, así que primero se retira el ajuste
    // anterior: si no, cambiar de pasarela dejaría las dos comisiones puestas.
    $this->removeExistingAdjustment($order);

    $pasarela = $this->pasarelaElegida($order);
    $config = $this->configFactory->get('pronens_comision_pago.settings');
    $pasarelas = $config->get('pasarelas');
    $pasarelas = is_array($pasarelas) ? array_values(array_map('strval', $pasarelas)) : [];

    if ($pasarela === NULL || !$this->calculator->aplicaA((string) $pasarela->id(), $pasarelas)) {
      return;
    }

    // El total guardado en el pedido es el de la última vez que se grabó, no el
    // que acaban de dejar los procesadores anteriores (el de envío es de los
    // últimos, en prioridad -100). Recalcularlo aquí es lo que garantiza que el
    // porcentaje se aplica sobre lo que el cliente va a pagar de verdad.
    $order->recalculateTotalPrice();

    $comision = $this->calculator->calculate(
      $order->getTotalPrice(),
      (string) ($config->get('porcentaje') ?? '0'),
    );
    if ($comision === NULL) {
      return;
    }

    $order->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => $this->t('@pasarela commission', [
        '@pasarela' => $pasarela->getPlugin()->getDisplayLabel(),
      ]),
      'amount' => $this->rounder->round($comision),
      'source_id' => self::SOURCE_ID,
    ]));
  }

  /**
   * Pasarela elegida por el cliente, o NULL si todavía no ha elegido.
   */
  private function pasarelaElegida(OrderInterface $order): ?PaymentGatewayInterface {
    if (!$order->hasField('payment_gateway')) {
      return NULL;
    }
    $pasarela = $order->get('payment_gateway')->entity;

    return $pasarela instanceof PaymentGatewayInterface ? $pasarela : NULL;
  }

  /**
   * Retira el ajuste que puso una pasada anterior de este procesador.
   */
  private function removeExistingAdjustment(OrderInterface $order): void {
    foreach ($order->getAdjustments(['fee']) as $ajuste) {
      if ($ajuste->getSourceId() === self::SOURCE_ID) {
        $order->removeAdjustment($ajuste);
      }
    }
  }

}
