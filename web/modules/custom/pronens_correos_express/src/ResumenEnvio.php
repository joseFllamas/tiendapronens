<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\pronens_correos_express\Catalogo\SituacionPedido;

/**
 * Lo que hay que saber del envío de un pedido para pintar una fila de lista.
 *
 * Un objeto de solo lectura, para que la plantilla y los plugins de Views no
 * tengan que volver a preguntarle nada a las entidades.
 */
final class ResumenEnvio {

  /**
   * Construye el resumen.
   *
   * @param \Drupal\pronens_correos_express\Catalogo\SituacionPedido $situacion
   *   En qué punto está la preparación del pedido.
   * @param array<int, string> $metodos
   *   Nombre de los métodos de envío elegidos, sin repetir. Es el «tipo de
   *   envío»: «Envío España peninsular», «Recoger en Pronens»….
   * @param array<int, array{codigo: string, url: string|null, envio: string, pedido: string}> $expediciones
   *   Una por envío con código de seguimiento. En Correos Express el número de
   *   expedición y el de seguimiento son el mismo, así que se lee del campo
   *   `tracking_code`, que es donde queda; los ids sirven para el enlace a la
   *   etiqueta. `url` es NULL cuando el código NO lo puso este módulo (se
   *   escribió a mano, o viene de otro transportista): ahí no hay página de
   *   seguimiento que enlazar ni etiqueta que imprimir.
   */
  public function __construct(
    public readonly SituacionPedido $situacion,
    public readonly array $metodos = [],
    public readonly array $expediciones = [],
  ) {}

}
