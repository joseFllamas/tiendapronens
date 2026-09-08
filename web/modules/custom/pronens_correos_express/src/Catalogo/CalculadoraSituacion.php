<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Catalogo;

/**
 * Deduce la situación de un pedido a partir de sus envíos.
 *
 * Lógica pura, sin contenedor ni entidades: quien la usa le pasa el estado del
 * pedido y una descripción de cada envío en forma de array, y recibe la
 * situación. Así las reglas de precedencia se prueban con PHPUnit sin base de
 * datos, igual que MapaEstados.
 *
 * La regla de fondo: un pedido con varios envíos dice lo que le queda por
 * hacer, no lo más adelantado. Si una caja está en la calle y otra sin
 * expedir, el pedido está «Por expedir», porque eso es lo que hay que
 * atender. Y un envío devuelto manda sobre todo lo demás, porque es una
 * excepción que alguien tiene que resolver.
 */
final class CalculadoraSituacion {

  /**
   * De más urgente a menos: la primera que aparezca es la que se enseña.
   *
   * Cancelado no está en la lista: un envío anulado se descarta y solo si no
   * queda ninguno vivo el pedido entero se considera cancelado.
   *
   * @var array<int, string>
   */
  private const PRIORIDAD = [
    SituacionPedido::Devuelto->value,
    SituacionPedido::PorExpedir->value,
    SituacionPedido::Expedido->value,
    SituacionPedido::Enviado->value,
    SituacionPedido::RecogeEnTienda->value,
    SituacionPedido::Entregado->value,
  ];

  /**
   * Situación de un pedido.
   *
   * @param string $estadoPedido
   *   Estado del workflow del pedido: draft, completed o canceled.
   * @param array<int, array{estado?: string, expedido?: bool, seExpide?: bool, situacion?: string|null}> $envios
   *   Un array por envío:
   *   - estado: estado del shipment (draft, ready, shipped, canceled).
   *   - expedido: si ya tiene número de expedición de Correos Express.
   *   - seExpide: FALSE cuando el método de envío no se expide (recogida en
   *     tienda), que se marca en los ajustes del módulo.
   *   - situacion: lo último que informó el seguimiento, si informó
   *     (SituacionEnvio::value), o NULL.
   */
  public function calcular(string $estadoPedido, array $envios): SituacionPedido {
    // Un pedido anulado lo está aunque sus envíos digan otra cosa.
    if ($estadoPedido === 'canceled') {
      return SituacionPedido::Cancelado;
    }
    if ($envios === []) {
      return SituacionPedido::SinEnvio;
    }

    $vivas = [];
    foreach ($envios as $envio) {
      $situacion = $this->deUnEnvio($envio);
      if ($situacion !== SituacionPedido::Cancelado) {
        $vivas[$situacion->value] = $situacion;
      }
    }

    // @todo s los envíos anulados: no queda nada que preparar.
    if ($vivas === []) {
      return SituacionPedido::Cancelado;
    }

    foreach (self::PRIORIDAD as $valor) {
      if (isset($vivas[$valor])) {
        return $vivas[$valor];
      }
    }

    return reset($vivas);
  }

  /**
   * Situación de un solo envío.
   *
   * @param array{estado?: string, expedido?: bool, seExpide?: bool, situacion?: string|null} $envio
   *   Descripción del envío.
   */
  private function deUnEnvio(array $envio): SituacionPedido {
    $estado = (string) ($envio['estado'] ?? 'draft');
    $seguimiento = $envio['situacion'] ?? NULL;

    if ($estado === 'canceled' || $seguimiento === SituacionEnvio::Anulado->value) {
      return SituacionPedido::Cancelado;
    }
    if ($seguimiento === SituacionEnvio::Devuelto->value) {
      return SituacionPedido::Devuelto;
    }

    // La recogida en tienda se mira antes que el seguimiento porque no tiene:
    // no hay expedición ninguna. Que el envío esté en «shipped» significa que
    // el cliente ya pasó a por el paquete, así que ahí sí está entregado.
    if (($envio['seExpide'] ?? TRUE) === FALSE) {
      return $estado === 'shipped'
        ? SituacionPedido::Entregado
        : SituacionPedido::RecogeEnTienda;
    }

    if ($seguimiento === SituacionEnvio::Entregado->value) {
      return SituacionPedido::Entregado;
    }
    // «shipped» lo aplica la sincronización cuando el transportista recoge, no
    // el alta de la expedición: hasta entonces el paquete sigue en el taller.
    if ($estado === 'shipped') {
      return SituacionPedido::Enviado;
    }

    return ($envio['expedido'] ?? FALSE)
      ? SituacionPedido::Expedido
      : SituacionPedido::PorExpedir;
  }

}
