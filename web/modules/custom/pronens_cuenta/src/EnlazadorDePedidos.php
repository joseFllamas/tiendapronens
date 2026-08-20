<?php

declare(strict_types=1);

namespace Drupal\pronens_cuenta;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderAssignmentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Enlaza los pedidos de invitado con la cuenta de su correo (2026-08-20).
 *
 * El checkout es de invitado, así que sin esto los pedidos de un cliente con
 * cuenta se quedaban en uid 0 y "Mis pedidos" no los enseñaba nunca. Son dos
 * caminos, y entre los dos cubren todos los casos hacia delante:
 *
 * - Al REALIZARSE un pedido de invitado cuyo correo ya tiene cuenta, el pedido
 *   se le asigna en el momento (quien controla ese correo recibe igualmente el
 *   recibo, así que no se le enseña nada que no fuera a ver).
 * - Al CREARSE una cuenta (el pane de registro de la pantalla de gracias, o un
 *   alta del administrador), se le asignan los pedidos de invitado ANTERIORES
 *   con su correo.
 *
 * Los pedidos que ya eran de invitado antes de desplegar esto se enlazan una
 * vez con scripts/enlazar-pedidos-invitado.php.
 */
final class EnlazadorDePedidos {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OrderAssignmentInterface $orderAssignment,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Asigna un pedido de invitado a la cuenta de su correo, si existe.
   *
   * Se llama desde el pre_transition de place y por eso NO guarda: el save del
   * propio place persiste el uid. Guardar aquí sería un save anidado dentro
   * del save que está disparando la transición. El evento de asignación sí se
   * dispara (vía OrderAssignment::assign con $save_order = FALSE), que es lo
   * que copia las direcciones del pedido a la libreta del cliente.
   */
  public function alRealizarsePedido(OrderInterface $pedido): void {
    if (!$pedido->getCustomer()->isAnonymous()) {
      return;
    }
    $usuario = $this->cuentaDelCorreo((string) ($pedido->getEmail() ?? ''));
    if ($usuario === NULL) {
      return;
    }

    $this->orderAssignment->assign($pedido, $usuario, FALSE);
    $this->logger->info('Pedido @pedido de invitado asignado a la cuenta @usuario (@correo) al realizarse.', [
      '@pedido' => $pedido->id(),
      '@usuario' => $usuario->id(),
      '@correo' => $usuario->getEmail(),
    ]);
  }

  /**
   * Asigna a una cuenta recién creada sus pedidos de invitado anteriores.
   *
   * Los carritos (draft) se quedan fuera: los gobierna commerce_cart con la
   * sesión anónima y reasignarlos aquí pisaría esa mecánica.
   *
   * @return int
   *   Cuántos pedidos se han asignado.
   */
  public function alCrearseCuenta(UserInterface $cuenta): int {
    $correo = (string) ($cuenta->getEmail() ?? '');
    if ($correo === '') {
      return 0;
    }
    $almacen = $this->entityTypeManager->getStorage('commerce_order');
    $ids = $almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0)
      ->condition('mail', $correo)
      ->condition('state', 'draft', '<>')
      ->execute();
    if ($ids === []) {
      return 0;
    }

    /** @var array<int, \Drupal\commerce_order\Entity\OrderInterface> $pedidos */
    $pedidos = $almacen->loadMultiple($ids);
    $this->orderAssignment->assignMultiple($pedidos, $cuenta);
    $this->logger->info('@total pedidos de invitado asignados a la cuenta nueva @usuario (@correo).', [
      '@total' => count($pedidos),
      '@usuario' => $cuenta->id(),
      '@correo' => $correo,
    ]);

    return count($pedidos);
  }

  /**
   * La cuenta cuyo correo coincide, o NULL.
   *
   * La comparación la hace la base de datos, que aquí no distingue mayúsculas,
   * igual que el propio login de Drupal.
   */
  private function cuentaDelCorreo(string $correo): ?UserInterface {
    if ($correo === '') {
      return NULL;
    }
    $cuentas = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $correo]);
    $cuenta = reset($cuentas);

    return $cuenta instanceof UserInterface ? $cuenta : NULL;
  }

}
