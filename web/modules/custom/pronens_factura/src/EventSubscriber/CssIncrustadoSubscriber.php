<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\EventSubscriber;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_print\Asset\AssetCollectorInterface;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\Event\PrintHtmlAlterEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Incrusta el CSS en el HTML del PDF para que dompdf no salga a la red.
 *
 * El módulo entity_print pinta las hojas de estilo como <link href> y dompdf
 * las DESCARGA por HTTP de la propia web, con la URL de la petición. Al generar
 * la factura desde la consola (drush, cron) el host es "default" y la
 * descarga se queda colgada hasta el timeout: la primera prueba estuvo cinco
 * minutos parada con la transacción abierta y bloqueando la caché de
 * configuración de las demás peticiones. Y en el servidor real dependería de
 * que PHP pueda llamar a su propio dominio, que no siempre puede.
 *
 * Aquí se resuelven las mismas libraries que entity_print (las del tema por
 * defecto más la suya de fábrica), SIN agregación (el agregado se genera bajo
 * demanda al pedir la URL, así que puede no existir aún en disco), se leen los
 * ficheros y se meten en un <style>. Va con prioridad alta para actuar antes
 * de que entity_print convierta los href en absolutos.
 */
final class CssIncrustadoSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'entity_print.asset_collector')]
    private readonly AssetCollectorInterface $assetCollector,
    private readonly AssetResolverInterface $assetResolver,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [PrintEvents::POST_RENDER => ['incrustar', 50]];
  }

  /**
   * Sustituye los <link rel="stylesheet"> por el CSS en línea.
   */
  public function incrustar(PrintHtmlAlterEvent $event): void {
    $entidades = $event->getEntities();
    $esFactura = FALSE;
    foreach ($entidades as $entidad) {
      if ($entidad->getEntityTypeId() === 'commerce_invoice') {
        $esFactura = TRUE;
      }
    }
    if (!$esFactura) {
      return;
    }

    // Misma normalización que AssetRenderer::render(): el evento CSS_ALTER de
    // entity_print admite todavía la forma antigua de render array
    // (['#attached' => ['library' => […]]]), y commerce_invoice la usa.
    $libraries = [];
    foreach ($this->assetCollector->getCssLibraries($entidades) as $clave => $library) {
      if ($clave === '#attached' && is_array($library)) {
        $libraries = array_merge($libraries, (array) ($library['library'] ?? []));
      }
      elseif (is_string($library)) {
        $libraries[] = $library;
      }
    }
    if ($this->configFactory->get('entity_print.settings')->get('default_css')) {
      $libraries[] = 'entity_print/default';
    }
    $attached = AttachedAssets::createFromRenderArray(['#attached' => ['library' => $libraries]]);
    $assets = $this->assetResolver->getCssAssets($attached, FALSE, $this->languageManager->getCurrentLanguage());

    $css = [];
    foreach ($assets as $asset) {
      if (($asset['type'] ?? '') !== 'file') {
        continue;
      }
      $fichero = DRUPAL_ROOT . '/' . ltrim((string) $asset['data'], '/');
      if (is_file($fichero)) {
        $css[] = (string) file_get_contents($fichero);
      }
    }
    if ($css === []) {
      return;
    }

    $html = &$event->getHtml();
    $html = (string) preg_replace('/<link\b[^>]*\brel="stylesheet"[^>]*>\s*/i', '', $html);
    $estilo = "<style>\n" . implode("\n", $css) . "\n</style>\n";
    $html = str_contains($html, '</head>')
      ? str_replace('</head>', $estilo . '</head>', $html)
      : $estilo . $html;
  }

}
