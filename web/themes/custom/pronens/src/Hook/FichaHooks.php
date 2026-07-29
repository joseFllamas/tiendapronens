<?php

namespace Drupal\pronens\Hook;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens\CamposTrait;

/**
 * Hooks de la ficha de producto.
 *
 * Aparte de PronensHooks porque la ficha es la pantalla más grande y mezcla
 * dos cosas distintas: los datos del producto (galería, acordeones,
 * relacionados) y la reordenación del formulario de añadir al carrito, que es
 * del módulo pronens_personalizacion y aquí solo se agrupa y se viste.
 */
class FichaHooks {

  use CamposTrait;
  use StringTranslationTrait;

  /**
   * Cuántas fotos entran en la cuadrícula de la galería.
   *
   * El prototipo dibuja seis huecos; con menos fotos la cuadrícula se ajusta.
   */
  protected const MAX_FOTOS = 6;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrencyFormatterInterface $currencyFormatter,
    protected ConfigFactoryInterface $configFactory,
    protected RendererInterface $renderer,
  ) {
  }

  /**
   * Monta la ficha completa.
   *
   * Galería, cabecera, desglose de precio, acordeones y relacionados. Lo llama
   * PronensHooks::preprocessCommerceProduct() porque un tema solo puede
   * implementar cada preprocess una vez. El add-to-cart viene del display.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de producto.
   */
  public function buildFicha(array &$variables, ProductInterface $producto): void {
    $variacion = $producto->getDefaultVariation();
    $precio = $variacion?->getPrice();
    $recargo = $this->recargo($producto);

    $variables['ficha'] = [
      'eyebrow' => $this->eyebrow($producto),
      'titulo' => $producto->label(),
      'fotos' => $this->fotos($variables, $producto),
      'precio' => $precio !== NULL
        ? $this->currencyFormatter->format($precio->getNumber(), $precio->getCurrencyCode())
        : NULL,
      // El desglose "14,52 € + 5,00 € bordado" del prototipo: el precio
      // unitario no cambia, el bordado es un ajuste aparte.
      'precio_base' => $precio !== NULL ? (float) $precio->getNumber() : NULL,
      'moneda' => $precio?->getCurrencyCode() ?? 'EUR',
      'recargo' => $recargo,
      'recargo_texto' => $recargo > 0
        ? $this->currencyFormatter->format((string) $recargo, $precio?->getCurrencyCode() ?? 'EUR')
        : NULL,
      'personalizable' => $this->esPersonalizable($producto),
      'guia_tallas' => $this->guiaTallas($producto),
      'guia_bordado' => $this->esModoInicial($producto) ? $this->guiaBordado($variables) : NULL,
      'relacionados' => $this->relacionados($variables, $producto),
    ];
    $variables['#attached']['library'][] = 'pronens/ficha';
    $variables['#attached']['library'][] = 'pronens/caveat';
    $variables['#attached']['drupalSettings']['pronens']['ficha'] = [
      'precioBase' => $variables['ficha']['precio_base'],
      'recargo' => $recargo,
      'moneda' => $variables['ficha']['moneda'],
      // Precio de cada extra, para que el CTA sume en vivo lo que se marca.
      'extras' => $this->preciosDeExtras($producto),
    ];
  }

  /**
   * Implements hook_form_alter().
   *
   * Reordena y agrupa el add-to-cart para el orden del diseño: atributos,
   * card de personalización, y por último cantidad junto al CTA. El módulo
   * pone los campos de bordado con peso 2; aquí solo se agrupan y se visten,
   * sin tocar validación ni lógica de negocio.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!str_starts_with($form_id, 'commerce_order_item_add_to_cart_form_commerce_product')) {
      return;
    }

    $form['#attributes']['class'][] = 'pro-buy-form';

    // Atributos (talla, medida…): pastillas.
    if (isset($form['purchased_entity'])) {
      $form['purchased_entity']['#attributes']['class'][] = 'pro-attrs';
      $form['purchased_entity']['#weight'] = 0;
    }

    // Card del personalizador: el checkbox y los dos campos van juntos.
    if (isset($form['personalizacion_activa'])) {
      $form['pro_perso'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['pro-perso'],
          'data-pro-perso' => TRUE,
        ],
        '#weight' => 5,
      ];
      foreach (['personalizacion_activa', 'field_texto_bordado', 'field_color_bordado'] as $clave) {
        if (isset($form[$clave])) {
          $form['pro_perso'][$clave] = $form[$clave];
          unset($form[$clave]);
        }
      }
      $form['pro_perso']['personalizacion_activa']['#attributes']['data-pro-perso-toggle'] = TRUE;
      // El prototipo canta el recargo junto al texto del checkbox.
      $producto = $form_state->get('product');
      $recargo = $producto instanceof ProductInterface ? $this->recargo($producto) : 0.0;
      if ($recargo > 0) {
        $titulo = (string) $form['pro_perso']['personalizacion_activa']['#title'];
        $form['pro_perso']['personalizacion_activa']['#title'] = Markup::create(
          $titulo . ' <span class="pro-perso__fee">+'
          . $this->currencyFormatter->format((string) $recargo, 'EUR') . '</span>'
        );
      }
      if (isset($form['pro_perso']['field_texto_bordado']['widget'][0]['value'])) {
        $valor = &$form['pro_perso']['field_texto_bordado']['widget'][0]['value'];
        $valor['#attributes']['data-pro-perso-texto'] = TRUE;
        // La descripción del campo es una nota interna de la migración ("máximo
        // real observado en el Drupal 7…"): en la tienda va un placeholder con
        // el límite real, que es lo que dice el diseño.
        $limite = (int) ($valor['#maxlength'] ?? 0);
        $valor['#description'] = NULL;
        if (($valor['#placeholder'] ?? '') === '' && $limite > 1) {
          $valor['#placeholder'] = $this->t('Escribe el nombre (máx. @n)', ['@n' => $limite]);
        }
        unset($valor);
      }
      if (isset($form['pro_perso']['field_color_bordado'])) {
        // El formato solo se elige en los productos de inicial: en el D7 el
        // campo existía únicamente en el tipo de línea `custom_color_product`
        // de un solo producto, y el cliente confirma que la elección de
        // combinación es cosa de la inicial, no del nombre completo.
        if ($producto instanceof ProductInterface && $this->esModoInicial($producto)) {
          $this->vistealosFormatos($form['pro_perso']['field_color_bordado']);
        }
        else {
          $form['pro_perso']['field_color_bordado']['#access'] = FALSE;
        }
      }
    }

    // Extras (llavero y compañía) en su propia card: no dependen del bordado.
    if (isset($form['field_extras']) && ($form['field_extras']['#access'] ?? TRUE) !== FALSE) {
      $form['pro_extras'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['pro-extras'], 'data-pro-extras' => TRUE],
        '#weight' => 7,
      ];
      foreach (['field_extras', 'field_extras_texto'] as $clave) {
        if (isset($form[$clave])) {
          $form['pro_extras'][$clave] = $form[$clave];
          unset($form[$clave]);
        }
      }
      $this->vistealosExtras($form['pro_extras']['field_extras']);
    }

    // Cantidad y CTA en la misma fila, después de la card.
    $form['pro_compra'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pro-ficha__row']],
      '#weight' => 10,
    ];
    if (isset($form['quantity'])) {
      $form['quantity']['#attributes']['class'][] = 'pro-qty';
      if (isset($form['quantity']['widget'][0]['value'])) {
        $form['quantity']['widget'][0]['value']['#attributes']['data-pro-qty-input'] = TRUE;
      }
      $form['pro_compra']['quantity'] = $form['quantity'];
      unset($form['quantity']);
    }
    if (isset($form['actions'])) {
      $form['actions']['#attributes']['class'][] = 'pro-buy';
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#attributes']['class'][] = 'pro-btn';
        $form['actions']['submit']['#attributes']['class'][] = 'pro-btn--cta';
        $form['actions']['submit']['#attributes']['data-pro-cta'] = TRUE;
      }
      $form['pro_compra']['actions'] = $form['actions'];
      unset($form['actions']);
    }
  }

  /**
   * Fotos de la galería: principal primero, sin repetidas.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<int, array<string, mixed>>
   *   Lista de imágenes renderizables.
   */
  protected function fotos(array &$variables, ProductInterface $producto): array {
    $fotos = [];
    foreach ($this->mediasFromFields($producto, ['field_imagen_principal', 'field_galeria']) as $media) {
      $principal = $fotos === [];
      $imagen = $this->buildStyledImage(
        $media,
        $principal ? 'pronens_ficha_principal' : 'pronens_ficha_miniatura',
        $principal
      );
      if ($imagen === NULL) {
        continue;
      }
      $fotos[] = $imagen;
      $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $media->getCacheTags());
      if (\count($fotos) === self::MAX_FOTOS) {
        break;
      }
    }

    return $fotos;
  }

  /**
   * Eyebrow del diseño: categoría y composición separadas por punto medio.
   */
  protected function eyebrow(ProductInterface $producto): ?string {
    $partes = [];
    $termino = $this->termFromField($producto, 'field_tipo_de_producto');
    if ($termino !== NULL) {
      $partes[] = $termino->label();
    }
    if ($producto->hasField('field_composicion') && !$producto->get('field_composicion')->isEmpty()) {
      $partes[] = (string) $producto->get('field_composicion')->value;
    }

    return $partes === [] ? NULL : implode(' · ', $partes);
  }

  /**
   * Recargo por bordado de este producto, en euros.
   *
   * Por producto manda field_recargo; si está vacío, el ajuste global de
   * /admin/commerce/config/personalizacion.
   */
  protected function recargo(ProductInterface $producto): float {
    if ($producto->hasField('field_recargo') && !$producto->get('field_recargo')->isEmpty()) {
      return (float) $producto->get('field_recargo')->number;
    }

    return (float) $this->configFactory->get('pronens_personalizacion.settings')->get('recargo.number');
  }

  /**
   * TRUE si el producto admite bordado.
   */
  protected function esPersonalizable(ProductInterface $producto): bool {
    return $producto->hasField('field_personalizable')
      && (bool) $producto->get('field_personalizable')->value;
  }

  /**
   * Guía de tallas: solo 57 productos la tienen.
   *
   * @return array<string, mixed>|null
   *   Render array del término de la guía.
   */
  protected function guiaTallas(ProductInterface $producto): ?array {
    $termino = $this->termFromField($producto, 'field_guia_tallas');
    if ($termino === NULL) {
      return NULL;
    }

    return [
      'titulo' => $termino->label(),
      'contenido' => $termino->get('description')->view(['label' => 'hidden', 'type' => 'text_default']),
    ];
  }

  /**
   * Productos para "Combínalo con".
   *
   * field_relacionados está vacío en los 370 productos migrados, así que se
   * cae a los del mismo término, que es lo que el cliente esperaría ver.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<int, array<string, mixed>>
   *   Render arrays de tarjetas.
   */
  protected function relacionados(array &$variables, ProductInterface $producto): array {
    $ids = [];
    if ($producto->hasField('field_relacionados')) {
      $lista = $producto->get('field_relacionados');
      if ($lista instanceof EntityReferenceFieldItemListInterface) {
        foreach ($lista->referencedEntities() as $relacionado) {
          $ids[] = (int) $relacionado->id();
        }
      }
    }

    if ($ids === []) {
      $termino = $this->termFromField($producto, 'field_tipo_de_producto');
      if ($termino === NULL) {
        return [];
      }
      $consulta = $this->entityTypeManager->getStorage('commerce_product')->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('field_tipo_de_producto', $termino->id())
        ->condition('product_id', $producto->id(), '<>')
        ->sort('created', 'DESC')
        ->range(0, 4);
      $ids = array_map('intval', array_values($consulta->execute()));
      $variables['#cache']['tags'] = Cache::mergeTags(
        $variables['#cache']['tags'] ?? [],
        ['commerce_product_list']
      );
    }

    if ($ids === []) {
      return [];
    }

    $constructor = $this->entityTypeManager->getViewBuilder('commerce_product');
    $tarjetas = [];
    foreach ($this->entityTypeManager->getStorage('commerce_product')->loadMultiple(array_slice($ids, 0, 4)) as $relacionado) {
      $tarjetas[] = $constructor->view($relacionado, 'tarjeta');
    }

    return $tarjetas;
  }


  /**
   * Convierte las opciones del formato del bordado en fichas visuales.
   *
   * Cada término de color_letra es una combinación cerrada de la fuente única
   * y los colores del hilo, y el diseño la representa con la foto de una letra
   * bordada (el "choose your initial colour" de la referencia). El widget de
   * opciones pinta etiquetas de texto, así que aquí se sustituyen por la foto
   * del término; si no tiene, por una muestra de color de field_color; y si
   * tampoco, se deja el nombre.
   *
   * Ojo con los datos: las 9 fotos que trajo el D7 son miniaturas de 82x93 de
   * camisetas dobladas, no letras bordadas, así que hoy la ficha cae a la
   * muestra de color. En cuanto el taller suba las fotos reales a field_imagen
   * la ficha las usa sin tocar código.
   *
   * @param array<string, mixed> $elemento
   *   El elemento de formulario del campo, por referencia.
   */
  protected function vistealosFormatos(array &$elemento): void {
    if (!isset($elemento['widget']['#options'])) {
      return;
    }
    // En #attributes del widget la clase acabaría también en cada radio; en el
    // elemento va solo al fieldset, que es lo que estiliza el tema.
    $elemento['#attributes']['class'][] = 'pro-formatos';
    // Fuera la opción vacía: el diseño no ofrece "N/D" y el módulo ya vacía el
    // bordado entero cuando la casilla está desmarcada.
    unset($elemento['widget']['#options']['_none']);
    if (($elemento['widget']['#default_value'] ?? NULL) === NULL || $elemento['widget']['#default_value'] === ['_none']) {
      $elemento['widget']['#default_value'] = [array_key_first($elemento['widget']['#options'])];
    }
    // Nota de desarrollo, no texto de tienda.
    $elemento['widget']['#description'] = NULL;
    $elemento['#description'] = NULL;
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple(array_keys($elemento['widget']['#options']));

    // Los términos vienen ordenados por peso, que es el orden de la foto guía:
    // el número de la pastilla y el de la foto tienen que coincidir.
    $numero = 0;
    foreach ($elemento['widget']['#options'] as $tid => $etiqueta) {
      $termino = $terminos[$tid] ?? NULL;
      if ($termino === NULL) {
        continue;
      }
      $numero++;
      $media = $this->mediaFromField($termino, 'field_imagen');
      $foto = $media !== NULL ? $this->buildStyledImage($media, 'pronens_formato') : NULL;
      $color = $this->colorDeFormato($termino);

      if ($foto !== NULL && $this->fotoUtil($media)) {
        $muestra = '<span class="pro-formato__foto">' . $this->renderer->render($foto) . '</span>';
      }
      elseif ($color !== NULL) {
        $muestra = '<span class="pro-formato__color" style="--pro-formato-color:' . $color . '"></span>';
      }
      else {
        $muestra = '<span class="pro-formato__num">' . $numero . '</span>';
      }
      $elemento['widget']['#options'][$tid] = Markup::create(
        $muestra . '<span class="pro-formato__nombre">' . $etiqueta . '</span>'
      );
    }
  }

  /**
   * Color del hilo de un formato, en hexadecimal, o NULL.
   */
  protected function colorDeFormato(object $termino): ?string {
    if (!$termino instanceof \Drupal\Core\Entity\FieldableEntityInterface) {
      return NULL;
    }
    if (!$termino->hasField('field_color') || $termino->get('field_color')->isEmpty()) {
      return NULL;
    }
    // color_field_type guarda el hexadecimal en la propiedad "color", no en
    // "value": leerlo por ->value devuelve NULL.
    $valor = (string) ($termino->get('field_color')->first()?->get('color')->getValue() ?? '');

    // Solo hexadecimales: el valor va directo a un style inline.
    return preg_match('/^#?[0-9a-fA-F]{6}$/', $valor) === 1
      ? (str_starts_with($valor, '#') ? $valor : '#' . $valor)
      : NULL;
  }

  /**
   * TRUE si la foto del formato sirve como ficha de letra bordada.
   *
   * Las del D7 miden 82x93: por debajo de 200px de ancho no dan ni para el
   * tamaño de la ficha en pantallas normales, y de hecho no son letras.
   */
  protected function fotoUtil(?object $media): bool {
    if (!$media instanceof \Drupal\media\MediaInterface || !$media->hasField('field_media_image')) {
      return FALSE;
    }
    $item = $media->get('field_media_image')->first();

    return $item !== NULL && (int) ($item->getValue()['width'] ?? 0) >= 200;
  }


  /**
   * Precio unitario de cada extra que ofrece el producto.
   *
   * @return array<int, float>
   *   Precios indexados por id de término.
   */
  protected function preciosDeExtras(ProductInterface $producto): array {
    if (!$producto->hasField('field_extras_disponibles')) {
      return [];
    }
    $lista = $producto->get('field_extras_disponibles');
    if (!$lista instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }
    $precios = [];
    foreach ($lista->referencedEntities() as $extra) {
      if (!$extra instanceof \Drupal\Core\Entity\FieldableEntityInterface
        || !$extra->hasField('field_precio')
        || $extra->get('field_precio')->isEmpty()) {
        continue;
      }
      $precios[(int) $extra->id()] = (float) ($extra->get('field_precio')->first()?->get('number')->getValue() ?? 0);
    }

    return $precios;
  }

  /**
   * TRUE si el producto se personaliza con una inicial y no con un nombre.
   */
  protected function esModoInicial(ProductInterface $producto): bool {
    return $producto->hasField('field_modo_personalizacion')
      && (string) $producto->get('field_modo_personalizacion')->value === 'inicial';
  }

  /**
   * Guía visual del formato del bordado, para el diálogo de ayuda.
   *
   * Es un bloque de contenido del tipo guia_bordado, así que el cliente cambia
   * la foto y el texto desde /admin/content/block sin tocar código.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags).
   *
   * @return array<string, mixed>|null
   *   Render array del bloque.
   */
  protected function guiaBordado(array &$variables): ?array {
    $almacen = $this->entityTypeManager->getStorage('block_content');
    $bloques = $almacen->loadByProperties(['type' => 'guia_bordado']);
    $bloque = reset($bloques);
    if ($bloque === FALSE) {
      return NULL;
    }
    // Lista del bundle además del bloque: si el cliente crea otro, se recalcula.
    $variables['#cache']['tags'] = Cache::mergeTags(
      $variables['#cache']['tags'] ?? [],
      Cache::mergeTags($bloque->getCacheTags(), ['block_content_list'])
    );

    return $this->entityTypeManager->getViewBuilder('block_content')->view($bloque, 'default');
  }


  /**
   * Pone la foto del extra junto a su casilla.
   *
   * El widget de opciones pinta etiquetas de texto; el diseño de la referencia
   * enseña el llavero, así que la foto del término se cuela en la etiqueta.
   *
   * @param array<string, mixed> $elemento
   *   El elemento de formulario del campo, por referencia.
   */
  protected function vistealosExtras(array &$elemento): void {
    if (!isset($elemento['widget']['#options'])) {
      return;
    }
    $elemento['#attributes']['class'][] = 'pro-extras__lista';
    /** @var array<int, \Drupal\taxonomy\TermInterface> $terminos */
    $terminos = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple(array_keys($elemento['widget']['#options']));

    foreach ($elemento['widget']['#options'] as $tid => $etiqueta) {
      $termino = $terminos[$tid] ?? NULL;
      if ($termino === NULL) {
        continue;
      }
      $media = $this->mediaFromField($termino, 'field_imagen');
      $foto = $media !== NULL ? $this->buildStyledImage($media, 'pronens_formato') : NULL;
      $muestra = $foto !== NULL
        ? '<span class="pro-extra__foto">' . $this->renderer->render($foto) . '</span>'
        : '';
      $elemento['widget']['#options'][$tid] = Markup::create(
        $muestra . '<span class="pro-extra__nombre">' . $etiqueta . '</span>'
      );
    }
  }

}
