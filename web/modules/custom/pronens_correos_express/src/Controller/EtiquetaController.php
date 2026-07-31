<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\pronens_correos_express\Api\CorreosExpressException;
use Drupal\pronens_correos_express\GestorExpediciones;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega la etiqueta de una expedición.
 *
 * El PDF no se guarda en disco. Este sitio no tiene configurado el sistema de
 * ficheros privado, así que la etiqueta acabaría en el directorio público con
 * el nombre, la dirección y el teléfono de un cliente accesibles por URL. Y no
 * hace falta: Correos Express la regenera cuando se le pide.
 */
final class EtiquetaController extends ControllerBase {

  public function __construct(
    private readonly GestorExpediciones $gestorExpediciones,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(GestorExpediciones::class),
    );
  }

  /**
   * Devuelve la etiqueta en PDF.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   Pedido al que pertenece el envío.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   Envío del que se quiere la etiqueta.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   El PDF, o una redirección al pedido si no se pudo obtener.
   */
  public function descargar(OrderInterface $commerce_order, ShipmentInterface $commerce_shipment): Response {
    try {
      $etiquetas = $this->gestorExpediciones->etiquetas($commerce_shipment);
    }
    catch (CorreosExpressException $e) {
      $this->messenger()->addError($this->t('No se pudo obtener la etiqueta: @mensaje', [
        '@mensaje' => $e->getMessage(),
      ]));

      return new RedirectResponse(Url::fromRoute('entity.commerce_order.canonical', [
        'commerce_order' => $commerce_order->id(),
      ])->toString());
    }

    $expedicion = (string) $this->gestorExpediciones->expedicion($commerce_shipment);

    // La API devuelve un PDF por bulto. Concatenar los ficheros no produce un
    // PDF válido de varias páginas, así que con más de uno se entregan en un
    // ZIP en lugar de un documento que la mitad de los visores no abriría.
    // Fusionar de verdad necesitaría una librería de PDF, y eso solo se
    // justifica si el taller pide imprimir de una tirada.
    if (count($etiquetas->pdfs) > 1) {
      return $this->zip($etiquetas->pdfs, $expedicion);
    }

    return $this->pdf($etiquetas->pdfs[0], sprintf('cex-%s.pdf', $expedicion));
  }

  /**
   * Respuesta con un único PDF.
   */
  private function pdf(string $contenido, string $nombre): Response {
    return new Response($contenido, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('inline; filename="%s"', $nombre),
      'Content-Length' => (string) strlen($contenido),
      // Es un documento con datos personales: no se guarda en ninguna caché.
      'Cache-Control' => 'private, no-store, max-age=0',
    ]);
  }

  /**
   * Respuesta con un ZIP que lleva una etiqueta por bulto.
   *
   * @param list<string> $pdfs
   *   Contenido de cada etiqueta.
   * @param string $expedicion
   *   Número de expedición, para nombrar los ficheros.
   */
  private function zip(array $pdfs, string $expedicion): Response {
    $temporal = (string) tempnam(sys_get_temp_dir(), 'cex');
    $zip = new \ZipArchive();
    $zip->open($temporal, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    foreach ($pdfs as $indice => $pdf) {
      $zip->addFromString(sprintf('cex-%s-bulto-%d.pdf', $expedicion, $indice + 1), $pdf);
    }
    $zip->close();

    $contenido = (string) file_get_contents($temporal);
    unlink($temporal);

    return new Response($contenido, Response::HTTP_OK, [
      'Content-Type' => 'application/zip',
      'Content-Disposition' => sprintf('attachment; filename="cex-%s.zip"', $expedicion),
      'Content-Length' => (string) strlen($contenido),
      'Cache-Control' => 'private, no-store, max-age=0',
    ]);
  }

}
