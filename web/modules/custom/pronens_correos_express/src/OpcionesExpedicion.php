<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\physical\Weight;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\pronens_correos_express\Payload\DatosRecogida;

/**
 * Lo que el operario decide al dar de alta una expedición.
 *
 * En el formulario viene todo prerrellenado, así que el caso normal es no
 * tocar nada. Esto es lo que se puede cambiar.
 */
final readonly class OpcionesExpedicion {

  /**
   * Constructor.
   *
   * @param \Drupal\pronens_correos_express\Catalogo\ServicioCex $servicio
   *   Producto de Correos Express con el que se despacha.
   * @param int $numeroBultos
   *   Cuántos paquetes se entregan al transportista.
   * @param \Drupal\physical\Weight|null $pesoTotal
   *   Peso de la expedición completa. Si es NULL se usa el del envío.
   * @param list<\Drupal\physical\Weight> $pesosPorBulto
   *   Peso de cada bulto, cuando no todos pesan lo mismo. Vacío significa que
   *   el peso total va en la raíz del envío, que es lo que espera la API
   *   cuando el operario declara un único peso.
   * @param string $observaciones
   *   Texto que se imprime en la etiqueta, máximo 80 caracteres.
   * @param bool $entregaSabado
   *   Si se pide entrega en sábado.
   * @param \Drupal\pronens_correos_express\Payload\DatosRecogida|null $recogida
   *   Recogida a crear junto con el alta. Es la única forma que da la API de
   *   crear una.
   */
  public function __construct(
    public ServicioCex $servicio,
    public int $numeroBultos = 1,
    public ?Weight $pesoTotal = NULL,
    public array $pesosPorBulto = [],
    public string $observaciones = '',
    public bool $entregaSabado = FALSE,
    public ?DatosRecogida $recogida = NULL,
  ) {}

}
