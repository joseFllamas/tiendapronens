<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

/**
 * Quién envía el paquete.
 *
 * La tienda no tiene todos estos datos: commerce_store guarda nombre, dirección
 * y correo, pero no teléfono ni NIF, así que esos dos vienen de la
 * configuración del módulo.
 */
final readonly class DatosRemitente {

  public function __construct(
    public string $nombre,
    public string $direccion,
    public string $poblacion,
    public string $codigoPostal,
    public string $paisIso,
    public string $documento = '',
    public string $contacto = '',
    public string $telefono = '',
    public string $correo = '',
  ) {}

}
