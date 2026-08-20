<?php

declare(strict_types=1);

namespace Drupal\pronens_cuenta\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pronens_cuenta\EnlazadorDePedidos;
use Drupal\user\UserInterface;

/**
 * Al crearse una cuenta, le asigna sus pedidos de invitado anteriores.
 *
 * Cubre el pane de registro de la pantalla de gracias y las altas del
 * administrador. En el caso del pane no estorba: este hook corre durante el
 * save de la cuenta y asigna los pedidos viejos; el propio pane asigna después
 * el pedido recién hecho, que a esas alturas ya es suyo y no cambia nada.
 */
final class UsuarioHooks {

  public function __construct(
    private readonly EnlazadorDePedidos $enlazador,
  ) {}

  /**
   * Implements hook_user_insert().
   */
  #[Hook('user_insert')]
  public function userInsert(UserInterface $cuenta): void {
    $this->enlazador->alCrearseCuenta($cuenta);
  }

}
