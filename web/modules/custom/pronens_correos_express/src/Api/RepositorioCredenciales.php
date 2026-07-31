<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

use Drupal\Core\State\StateInterface;

/**
 * Guarda y lee las credenciales de Correos Express en State.
 *
 * En State y no en configuración a propósito: config/sync está versionado en
 * git, así que un "drush cex" después de guardar el formulario comitearía la
 * contraseña de Correos Express en texto plano, y no hay forma de excluir una
 * clave concreta de una exportación. State no se exporta, sobrevive a un "drush
 * cim" y permite tener preproducción en local y producción en el servidor sin
 * que aparezca ni un diff de configuración.
 *
 * Se descartó el módulo key: añade una dependencia contrib con entidad de
 * configuración y plugins de proveedor para guardar tres cadenas, y su
 * proveedor por defecto vuelve a ser configuración, con el mismo problema.
 *
 * El precio de State es que no está tipado, y phpstan corre a nivel 8. Por eso
 * este repositorio existe: fuera de aquí las credenciales son siempre un objeto
 * Credenciales.
 */
final class RepositorioCredenciales {

  private const CLAVE = 'pronens_correos_express.credenciales';

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * Lee las credenciales guardadas.
   */
  public function cargar(): Credenciales {
    $guardado = $this->state->get(self::CLAVE);
    if (!is_array($guardado)) {
      return Credenciales::vacias();
    }

    return new Credenciales(
      is_string($guardado['codigo_cliente'] ?? NULL) ? $guardado['codigo_cliente'] : '',
      is_string($guardado['usuario'] ?? NULL) ? $guardado['usuario'] : '',
      is_string($guardado['contrasena'] ?? NULL) ? $guardado['contrasena'] : '',
    );
  }

  /**
   * Guarda las credenciales.
   */
  public function guardar(Credenciales $credenciales): void {
    $this->state->set(self::CLAVE, [
      'codigo_cliente' => $credenciales->codigoCliente,
      'usuario' => $credenciales->usuario,
      'contrasena' => $credenciales->contrasena,
    ]);
  }

  /**
   * Borra las credenciales.
   */
  public function borrar(): void {
    $this->state->delete(self::CLAVE);
  }

}
