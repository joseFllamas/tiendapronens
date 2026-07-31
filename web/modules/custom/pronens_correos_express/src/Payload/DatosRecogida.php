<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

/**
 * Recogida solicitada junto con el alta de la expedición.
 *
 * Es la única forma de crear una recogida en esta API: el endpoint específico
 * existe pero la integración oficial dejó de usarlo, y lo que funciona es la
 * marca dentro del propio alta.
 */
final readonly class DatosRecogida {

  public function __construct(
    public \DateTimeImmutable $fecha,
    public \DateTimeImmutable $desde,
    public \DateTimeImmutable $hasta,
    public string $referencia = '',
  ) {}

}
