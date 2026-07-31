<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

use Drupal\physical\Length;
use Drupal\physical\Weight;

/**
 * Un paquete físico dentro de una expedición.
 *
 * Las dimensiones son opcionales: la API acepta ceros y para los productos a
 * domicilio la propia integración oficial nunca las manda. Las que importan son
 * las del embalaje, que vienen del tipo de paquete de Commerce.
 */
final readonly class Bulto {

  public function __construct(
    public ?Weight $peso = NULL,
    public ?Length $largo = NULL,
    public ?Length $ancho = NULL,
    public ?Length $alto = NULL,
    public string $observaciones = '',
  ) {}

}
