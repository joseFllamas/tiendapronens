<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\EventSubscriber;

use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\Core\File\FileSystemInterface;
use Drupal\entity_print\Event\PreSendPrintEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Crea la carpeta privada de las facturas antes de guardar el PDF.
 *
 * El módulo entity_print guarda el fichero con FileSystem::saveData() sobre
 * `private://<subcarpeta>/…` y NO crea la subcarpeta del tipo de factura: en un
 * entorno recién montado (o en producción tras declarar file_private_path) la
 * primera factura fallaba con "destination directory is not properly
 * configured" y el correo salía sin adjunto. Aquí se prepara la carpeta justo
 * antes, en PRE_SEND, que es el último evento antes de escribir.
 */
final class CarpetaPdfSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PrintEvents::PRE_SEND => 'prepararCarpeta'];
  }

  /**
   * Asegura `private://<subcarpeta del tipo de factura>`.
   */
  public function prepararCarpeta(PreSendPrintEvent $event): void {
    foreach ($event->getEntities() as $entidad) {
      if ($entidad->getEntityTypeId() !== 'commerce_invoice') {
        continue;
      }
      $tipo = InvoiceType::load($entidad->bundle());
      $carpeta = 'private://' . ($tipo?->getPrivateSubdirectory() ?: '');
      $this->fileSystem->prepareDirectory($carpeta, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }
  }

}
