<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\pronens_correos_express\GestorExpediciones;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Marca (y desmarca) que el cliente ha recogido su pedido en la tienda.
 *
 * Es el equivalente del botón «EXPEDIR» para las recogidas (cliente,
 * 2026-09-26): la lista de pedidos no tenía forma de decir cuáles se habían
 * llevado ya. Al revés que el alta de una expedición, esto no cuesta dinero ni
 * sale de la tienda, así que no pasa por un formulario de confirmación: un
 * clic y vuelta a la lista, con un «Deshacer» en la misma casilla por si el
 * clic era de otra fila.
 */
final class RecogidaController extends ControllerBase {

  public function __construct(
    private readonly GestorExpediciones $gestorExpediciones,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(GestorExpediciones::class),
    );
  }

  /**
   * El cliente ha pasado a por el paquete.
   */
  public function marcar(OrderInterface $commerce_order, ShipmentInterface $commerce_shipment): RedirectResponse {
    $this->comprobarPedido($commerce_order, $commerce_shipment);

    if ($this->gestorExpediciones->marcarRecogido($commerce_shipment)) {
      // Sin enlace de deshacer en el mensaje: la propia fila lo ofrece, y un
      // enlace con token CSRF metido en un mensaje de estado no se resuelve
      // igual que el de la columna (se probó y daba 403).
      $this->messenger()->addStatus($this->t('Pedido @numero marcado como recogido. Si ha sido un error, pulsa «Deshacer» en su fila.', [
        '@numero' => $commerce_order->getOrderNumber() ?? $commerce_order->id(),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('El pedido @numero no está pendiente de recogida en tienda.', [
        '@numero' => $commerce_order->getOrderNumber() ?? $commerce_order->id(),
      ]));
    }

    return $this->volver();
  }

  /**
   * La recogida se marcó por error.
   */
  public function deshacer(OrderInterface $commerce_order, ShipmentInterface $commerce_shipment): RedirectResponse {
    $this->comprobarPedido($commerce_order, $commerce_shipment);

    if ($this->gestorExpediciones->deshacerRecogido($commerce_shipment)) {
      $this->messenger()->addStatus($this->t('El pedido @numero vuelve a estar pendiente de recogida.', [
        '@numero' => $commerce_order->getOrderNumber() ?? $commerce_order->id(),
      ]));
    }

    return $this->volver();
  }

  /**
   * El envío tiene que ser de ese pedido: la ruta lleva los dos ids.
   */
  private function comprobarPedido(OrderInterface $pedido, ShipmentInterface $envio): void {
    if ((string) $envio->getOrderId() !== (string) $pedido->id()) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * De vuelta a la lista (con sus filtros), o a la ficha si no hay destino.
   *
   * El destino lo pone el propio enlace y RedirectResponseSubscriber lo
   * respeta, con su comprobación de que sea una URL local.
   */
  private function volver(): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('entity.commerce_order.collection')->toString());
  }

}
