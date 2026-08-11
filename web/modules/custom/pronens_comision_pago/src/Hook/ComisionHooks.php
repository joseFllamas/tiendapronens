<?php

declare(strict_types=1);

namespace Drupal\pronens_comision_pago\Hook;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens_comision_pago\ComisionCalculator;
use Drupal\pronens_comision_pago\OrderProcessor\ComisionOrderProcessor;

/**
 * Enseña la comisión en el selector de pago y la aplica al elegirla.
 *
 * Dos cosas que el procesador de pedido no puede hacer solo:
 *
 * 1. Avisar antes de elegir. El cliente tiene que ver el sobrecoste cuando
 *    decide, no al llegar a la pantalla de PayPal, así que el importe va en la
 *    propia etiqueta del radio ("PayPal (+1,5% de comisión: 0,90 €)").
 * 2. Forzar el recálculo. Commerce bloquea el pedido al pasar al paso de pago
 *    (CheckoutFlowBase::onStepChange) y un pedido bloqueado ya no se refresca,
 *    así que el ajuste nunca llegaría a entrar si no se refresca a mano justo
 *    después de que el panel de pago guarde la pasarela elegida.
 */
final class ComisionHooks {

  use StringTranslationTrait;

  private const NOMBRE_CONFIG = 'pronens_comision_pago.settings';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ComisionCalculator $calculator,
    private readonly CurrencyFormatterInterface $currencyFormatter,
    private readonly RounderInterface $rounder,
  ) {
  }

  /**
   * Añade la comisión a la etiqueta de las pasarelas que la cobran.
   *
   * @param array<string, mixed> $form
   *   El formulario del proceso de compra.
   */
  #[Hook('form_commerce_checkout_flow_multistep_default_alter')]
  public function checkoutFormAlter(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->get(self::NOMBRE_CONFIG);
    $porcentaje = (string) ($config->get('porcentaje') ?? '0');
    $pasarelas = $this->pasarelasConComision($config->get('pasarelas'));
    if ($pasarelas === [] || !ComisionCalculator::esPorcentajeValido($porcentaje)) {
      return;
    }

    // El botón de continuar lleva sus propios manejadores, que sustituyen a
    // los del formulario: el refresco se encola ahí, no en $form['#submit'].
    if (isset($form['actions']['next']['#submit'])) {
      $form['actions']['next']['#submit'][] = [self::class, 'refrescarTrasElegirPago'];
    }

    if (!isset($form['payment_information']['#payment_options'], $form['payment_information']['payment_method']['#options'])) {
      return;
    }

    $base = $this->baseSinComision($this->pedido($form_state));
    foreach ($form['payment_information']['#payment_options'] as $id => $opcion) {
      if (!$opcion instanceof PaymentOption) {
        continue;
      }
      if (!$this->calculator->aplicaA($opcion->getPaymentGatewayId(), $pasarelas)) {
        continue;
      }
      if (!isset($form['payment_information']['payment_method']['#options'][$id])) {
        continue;
      }

      $form['payment_information']['payment_method']['#options'][$id] = $this->etiqueta(
        (string) $form['payment_information']['payment_method']['#options'][$id],
        $porcentaje,
        $base,
      );
    }
  }

  /**
   * Recalcula el pedido cuando el cliente acaba de elegir el medio de pago.
   *
   * Es estático porque los manejadores de envío de un formulario se serializan
   * con él, y una instancia con servicios dentro no se serializa bien.
   *
   * @param array<string, mixed> $form
   *   El formulario del proceso de compra.
   */
  public static function refrescarTrasElegirPago(array &$form, FormStateInterface $form_state): void {
    $flujo = $form_state->getFormObject();
    if (!$flujo instanceof CheckoutFlowInterface) {
      return;
    }
    $pedido = $flujo->getOrder();
    if ($pedido->getState()->getId() !== 'draft') {
      return;
    }

    $config = \Drupal::config(self::NOMBRE_CONFIG);
    /** @var \Drupal\pronens_comision_pago\ComisionCalculator $calculador */
    $calculador = \Drupal::service(ComisionCalculator::class);
    $pasarela = $pedido->get('payment_gateway')->target_id;
    $pasarelas = array_map('strval', (array) ($config->get('pasarelas') ?? []));

    // Solo se refresca si hay comisión en juego: o la pasarela recién elegida
    // la cobra, o el pedido arrastra una de una elección anterior que hay que
    // quitar. En los pedidos que se pagan con tarjeta esto no cuesta nada.
    // Sin pasarela elegida target_id es NULL, y el cast lo deja en cadena
    // vacía, que aplicaA() ya descarta.
    $lleva_comision = $calculador->aplicaA((string) $pasarela, $pasarelas);
    if (!$lleva_comision && !self::tieneAjuste($pedido)) {
      return;
    }

    // refresh() no mira el bloqueo del pedido, al revés que shouldRefresh(),
    // así que sirve también en el salto al paso de pago, que es justo cuando
    // Commerce lo bloquea.
    \Drupal::service('commerce_order.order_refresh')->refresh($pedido);
    $pedido->save();
  }

  /**
   * Si el pedido ya lleva puesto el ajuste de comisión.
   */
  private static function tieneAjuste(OrderInterface $pedido): bool {
    foreach ($pedido->getAdjustments(['fee']) as $ajuste) {
      if ($ajuste->getSourceId() === ComisionOrderProcessor::SOURCE_ID) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Compone la etiqueta del radio con el porcentaje y, si se puede, el importe.
   */
  private function etiqueta(string $etiqueta, string $porcentaje, ?Price $base): string {
    $comision = $this->calculator->calculate($base, $porcentaje);
    $formateado = $this->calculator->formatearPorcentaje($porcentaje);

    if ($comision === NULL) {
      return (string) $this->t('@etiqueta (+@porcentaje% commission)', [
        '@etiqueta' => $etiqueta,
        '@porcentaje' => $formateado,
      ]);
    }

    // Se redondea igual que el ajuste, si no la etiqueta anuncia 0,5808 € y en
    // el pedido se cobran 0,58 €.
    $comision = $this->rounder->round($comision);

    return (string) $this->t('@etiqueta (+@porcentaje% commission: @importe)', [
      '@etiqueta' => $etiqueta,
      '@porcentaje' => $formateado,
      '@importe' => $this->currencyFormatter->format($comision->getNumber(), $comision->getCurrencyCode()),
    ]);
  }

  /**
   * Total del pedido sin contar una comisión ya aplicada.
   *
   * Si el cliente eligió PayPal, volvió atrás y vuelve a mirar el selector, el
   * total ya lleva la comisión dentro. Calcular el porcentaje sobre ese total
   * daría una cifra inflada en la etiqueta.
   */
  private function baseSinComision(?OrderInterface $pedido): ?Price {
    if ($pedido === NULL) {
      return NULL;
    }
    $total = $pedido->getTotalPrice();
    if ($total === NULL) {
      return NULL;
    }

    foreach ($pedido->getAdjustments(['fee']) as $ajuste) {
      if ($ajuste->getSourceId() === ComisionOrderProcessor::SOURCE_ID) {
        $total = $total->subtract($ajuste->getAmount());
      }
    }

    return $total;
  }

  /**
   * El pedido, si el objeto del formulario es el flujo de compra.
   */
  private function pedido(FormStateInterface $form_state): ?OrderInterface {
    $flujo = $form_state->getFormObject();

    return $flujo instanceof CheckoutFlowInterface ? $flujo->getOrder() : NULL;
  }

  /**
   * Normaliza la lista de pasarelas configuradas.
   *
   * @param mixed $pasarelas
   *   El valor guardado en configuración.
   *
   * @return array<int, string>
   *   Identificadores de pasarela.
   */
  private function pasarelasConComision(mixed $pasarelas): array {
    if (!is_array($pasarelas)) {
      return [];
    }

    return array_values(array_map('strval', array_filter($pasarelas)));
  }

}
