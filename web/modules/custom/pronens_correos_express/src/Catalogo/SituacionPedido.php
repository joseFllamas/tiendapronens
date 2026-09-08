<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Catalogo;

/**
 * En qué punto de la preparación está un pedido, de cara al taller.
 *
 * Existe porque el estado del pedido NO sirve para esto: el workflow
 * `order_default` pasa a `completed` en el momento de pagar, así que la lista
 * de pedidos decía «Completado» en todas las filas, con la caja aún sin
 * empaquetar. Quien trabaja en el taller necesita saber otra cosa: qué queda
 * por expedir, qué ya tiene número de expedición y qué recoge el cliente en
 * tienda.
 *
 * Es la lectura de administración, más fina que la del cliente: lo que en «Mis
 * pedidos» son cuatro pasos (en preparación, enviado, entregado, devuelto),
 * aquí distingue además si la expedición ya está dada de alta en Correos
 * Express y si el pedido ni se envía. El vocabulario del cliente vive en el
 * tema (CuentaHooks::estadoDelPedido) y se deja aparte a propósito: el tema no
 * depende de este módulo, y las dos pantallas no hablan igual.
 *
 * Las etiquetas van en castellano y sin `t()`, igual que SituacionEnvio: son
 * texto de backoffice y esto es lógica pura, sin contenedor, para poder
 * probarla con PHPUnit.
 */
enum SituacionPedido: string {

  // El pedido no tiene envío: no hay nada que preparar.
  case SinEnvio = 'sin_envio';

  // Correos Express lo devolvió al remitente. Pide atención.
  case Devuelto = 'devuelto';

  // Hay que darle de alta la expedición. Es el trabajo pendiente.
  case PorExpedir = 'por_expedir';

  // Expedición dada de alta y etiqueta lista; el paquete sigue en el taller.
  case Expedido = 'expedido';

  // Recogido por el transportista y de camino. Se llama «Enviado» y no «En
  // tránsito» para hablar igual que la pestaña de envíos, el correo de
  // expedición y «Mis pedidos»: es el mismo estado del envío (`shipped`) visto
  // desde cuatro pantallas.
  case Enviado = 'enviado';

  // Lo recoge el cliente en la tienda: no se expide.
  case RecogeEnTienda = 'recoge_en_tienda';

  // Entregado, o recogido en tienda por el cliente.
  case Entregado = 'entregado';

  // Pedido o envío anulado.
  case Cancelado = 'cancelado';

  /**
   * Nombre legible en la lista de pedidos.
   */
  public function etiqueta(): string {
    return match ($this) {
      self::SinEnvio => 'Sin envío',
      self::Devuelto => 'Devuelto',
      self::PorExpedir => 'Por expedir',
      self::Expedido => 'Expedido',
      self::Enviado => 'Enviado',
      self::RecogeEnTienda => 'Recoge en tienda',
      self::Entregado => 'Entregado',
      self::Cancelado => 'Cancelado',
    };
  }

  /**
   * Indica que el taller tiene trabajo pendiente con este pedido.
   */
  public function pideTrabajo(): bool {
    return match ($this) {
      self::PorExpedir, self::Expedido, self::Devuelto => TRUE,
      self::SinEnvio, self::Enviado, self::RecogeEnTienda, self::Entregado, self::Cancelado => FALSE,
    };
  }

}
