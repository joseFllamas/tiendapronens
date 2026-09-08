<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\EventSubscriber;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\pronens_factura\RegistroDescargas;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Apunta cada descarga de factura cuando el PDF sale de verdad.
 *
 * Se engancha a la respuesta y no al controlador porque el controlador es de
 * contrib (InvoiceController::download) y no tiene ningún gancho. Y tiene que
 * ser en la RESPUESTA y no en la petición porque esa ruta puede acabar en 404:
 * si el PDF no se pudo generar, el módulo lanza NotFoundHttpException, y una
 * factura que no se ha llevado nadie no se puede marcar como descargada.
 *
 * Por eso se comprueban las dos cosas: que la respuesta es un fichero
 * (BinaryFileResponse, que es lo que devuelve la descarga) y que es correcta.
 */
final class DescargaFacturaSubscriber implements EventSubscriberInterface {

  /**
   * La ruta de descarga de commerce_invoice.
   */
  private const RUTA = 'entity.commerce_invoice.download';

  public function __construct(
    private readonly RouteMatchInterface $rutaActual,
    private readonly RegistroDescargas $registroDescargas,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['apuntarDescarga', -100]];
  }

  /**
   * Anota la descarga si la respuesta es el PDF.
   */
  public function apuntarDescarga(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    if ($this->rutaActual->getRouteName() !== self::RUTA) {
      return;
    }
    $respuesta = $event->getResponse();
    if (!$respuesta instanceof BinaryFileResponse || !$respuesta->isSuccessful()) {
      return;
    }
    $factura = $this->rutaActual->getParameter('commerce_invoice');
    if (!$factura instanceof InvoiceInterface) {
      return;
    }

    $this->registroDescargas->apuntar($factura);
  }

}
