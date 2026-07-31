<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

/**
 * Quién recibe el paquete.
 */
final readonly class DatosDestinatario {

  public function __construct(
    public string $nombre,
    public string $direccion,
    public string $poblacion,
    public string $codigoPostal,
    public string $paisIso,
    public string $apellidos = '',
    public string $empresa = '',
    public string $documento = '',
    public string $telefono = '',
    public string $correo = '',
  ) {}

}
