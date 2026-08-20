<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Hook;

use Drupal\commerce_cart\Form\AddToCartFormInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\pronens_personalizacion\OrderProcessor\ExtrasOrderProcessor;
use Drupal\pronens_personalizacion\OrderProcessor\PersonalizacionOrderProcessor;
use Drupal\taxonomy\TermInterface;

/**
 * Adapta el formulario de añadir al carrito a la card de personalización.
 *
 * El cliente decide qué se borda con dos controles: el texto (una inicial o un
 * nombre, según el modo del producto) y el color del hilo. La tipografía dejó
 * de elegirse por decisión del cliente (2026-07-26): será siempre la misma, así
 * que el vocabulario fuente_bordado y field_fuente quedan en el modelo sin
 * exponerse, por si la decisión se revierte.
 */
final class PersonalizacionHooks {

  use StringTranslationTrait;

  /**
   * Campos de personalización expuestos en el add-to-cart.
   */
  private const CAMPOS = [
    PersonalizacionOrderProcessor::CAMPO_TEXTO,
    'field_color_bordado',
    self::CAMPO_FONDO,
  ];

  /**
   * Campo de la línea de pedido con el fondo sobre el que va el bordado.
   *
   * La nube de las mochilas y las bolsas: el nombre no se borda sobre la tela
   * sino dentro de una forma, que viene en varios colores. No cuesta nada, así
   * que no tiene procesador de pedido; solo hay que guardar cuál eligió el
   * cliente, que es lo que va a leer el taller.
   */
  private const CAMPO_FONDO = 'field_fondo_bordado';

  /**
   * Campo del producto con los fondos que ofrece.
   */
  private const CAMPO_FONDO_PRODUCTO = 'field_fondos_disponibles';

  /**
   * Campo de la línea de pedido con los extras elegidos.
   */
  private const CAMPO_EXTRAS = ExtrasOrderProcessor::CAMPO_EXTRAS;

  /**
   * Campo de la línea con el texto que pide un extra.
   */
  private const CAMPO_EXTRAS_TEXTO = ExtrasOrderProcessor::CAMPO_TEXTO;

  /**
   * Campo del producto con los extras que ofrece.
   */
  private const CAMPO_EXTRAS_PRODUCTO = 'field_extras_disponibles';

  public function __construct(
    private readonly CurrencyFormatterInterface $currencyFormatter,
  ) {
  }

  /**
   * Altera todos los formularios de añadir al carrito.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  #[Hook('form_commerce_order_item_add_to_cart_form_alter')]
  public function addToCartFormAlter(array &$form, FormStateInterface $form_state): void {
    $objeto = $form_state->getBuildInfo()['callback_object'] ?? NULL;
    if (!$objeto instanceof AddToCartFormInterface) {
      return;
    }
    $producto = $form_state->get('product');
    if (!$producto instanceof ProductInterface) {
      return;
    }

    // Los extras no dependen del bordado: un producto sin bordado puede ofrecer
    // llavero o envoltorio, así que se resuelven antes de nada.
    $this->preparaExtras($form, $producto);

    // En un producto sin bordado los campos sobran por completo: 81 de los 370
    // migrados no lo admiten.
    if (!$this->esPersonalizable($producto)) {
      foreach (self::CAMPOS as $campo) {
        $form[$campo]['#access'] = FALSE;
      }
      return;
    }

    $modo = $this->modo($producto);
    // El fondo del bordado: qué nubes ofrece este producto, si ofrece alguna.
    $this->preparaFondos($form, $producto);

    // La card del diseño: checkbox que pliega y despliega la personalización.
    // No es un campo, es UX; que haya bordado o no lo decide el texto.
    $form['personalizacion_activa'] = [
      '#type' => 'checkbox',
      '#title' => $modo === 'inicial'
        ? $this->t('Embroider your initial')
        : $this->t('Embroider your name'),
      // En modo inicial viene marcada: la inicial es el reclamo con el que se
      // vende el producto y no cuesta nada, así que obligar a activarla sobra;
      // quien quiera la prenda lisa la desmarca. En modo texto sigue apagada
      // porque marcarla cuesta 5 € y eso no se activa por nosotros.
      '#default_value' => $modo === 'inicial',
      '#weight' => 1,
    ];

    $estados = [
      'visible' => [
        ':input[name="personalizacion_activa"]' => ['checked' => TRUE],
      ],
    ];
    foreach (self::CAMPOS as $campo) {
      if (isset($form[$campo])) {
        $form[$campo]['#states'] = $estados;
        // Detrás del checkbox, delante del botón.
        $form[$campo]['#weight'] = 2;
      }
    }

    // El modo del producto decide qué se puede escribir: una sola letra o un
    // nombre. El widget es un string_textfield; se ajusta su elemento de valor.
    $texto = &$form[PersonalizacionOrderProcessor::CAMPO_TEXTO];
    if (isset($texto['widget'][0]['value']) && $modo === 'inicial') {
      // Con contexto: "Initial" a secas es una palabra genérica que cualquier
      // otro módulo puede registrar con otro significado y acabaría
      // compartiendo traducción. Aquí es la letra que se borda.
      $texto['widget'][0]['value']['#title'] = $this->t('Initial', [], ['context' => 'Embroidery']);
      $texto['widget'][0]['value']['#maxlength'] = 1;
      $texto['widget'][0]['value']['#attributes']['maxlength'] = 1;
      $texto['widget'][0]['value']['#placeholder'] = $this->t('One letter');
    }

    $form['#validate'][] = [self::class, 'validarPersonalizacion'];
  }

  /**
   * Comprueba que los extras que piden texto lo traigan.
   *
   * Se registra siempre que haya extras en el formulario, aunque el producto no
   * admita bordado: son cosas independientes.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  public static function validarExtras(array &$form, FormStateInterface $form_state): void {
    $elegidos = array_filter((array) $form_state->getValue(self::CAMPO_EXTRAS, []));
    $texto = trim((string) $form_state->getValue([self::CAMPO_EXTRAS_TEXTO, 0, 'value'], ''));

    if ($elegidos === []) {
      // Sin extras, el texto sobra: se vacía para que no quede colgado.
      $form_state->setValue(self::CAMPO_EXTRAS_TEXTO, []);
      return;
    }

    $tids = array_map(static fn ($valor) => is_array($valor) ? ($valor['target_id'] ?? $valor) : $valor, $elegidos);
    /** @var array<int, TermInterface> $extras */
    $extras = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
    foreach ($extras as $extra) {
      $pide = $extra->hasField('field_pide_texto')
        && !$extra->get('field_pide_texto')->isEmpty()
        && (bool) $extra->get('field_pide_texto')->value;
      if ($pide && $texto === '') {
        $form_state->setErrorByName(self::CAMPO_EXTRAS_TEXTO, new TranslatableMarkup(
          'Type the name for @extra.',
          ['@extra' => $extra->label()]
        ));
        return;
      }
    }

    $form_state->setValue([self::CAMPO_EXTRAS_TEXTO, 0, 'value'], $texto);
  }

  /**
   * Normaliza y valida el texto a bordar según el modo del producto.
   *
   * Los datos del D7 contienen bordados de solo espacios: se normaliza aquí
   * para que la línea de pedido guarde texto real o nada. El maxlength del
   * navegador no es una garantía, así que el modo inicial se reafirma en
   * servidor.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  public static function validarPersonalizacion(array &$form, FormStateInterface $form_state): void {
    $campo = PersonalizacionOrderProcessor::CAMPO_TEXTO;
    $valores = $form_state->getValue($campo);
    $texto = trim((string) ($valores[0]['value'] ?? ''));

    $producto = $form_state->get('product');
    $inicial = $producto instanceof ProductInterface
      && !$producto->get('field_modo_personalizacion')->isEmpty()
      && $producto->get('field_modo_personalizacion')->value === 'inicial';

    $activa = (bool) $form_state->getValue('personalizacion_activa');
    if (!$activa || $texto === '') {
      // La casilla del modo inicial viene marcada, así que aquí no se puede
      // pasar de largo: sin letra elegida el pedido saldría con la prenda lisa
      // sin que nadie lo haya decidido. Se pide la letra o que se desmarque.
      if ($activa && $inicial) {
        $form_state->setErrorByName($campo, new TranslatableMarkup('Choose the initial you want embroidered, or untick “Embroider your initial”.'));
        return;
      }
      // Sin casilla o sin texto no hay bordado: se vacía todo para que ni el
      // recargo ni el taller reciban restos.
      $form_state->setValue($campo, []);
      $form_state->setValue('field_color_bordado', []);
      $form_state->setValue(self::CAMPO_FONDO, []);
      return;
    }

    // El fondo elegido tiene que ser uno de los que ofrece el producto: el
    // selector ya solo enseña esos, pero el valor llega por POST y el campo
    // apunta a todo el vocabulario, así que aquí se reafirma. Sin fondos
    // declarados el campo ni se enseña y el nombre va sobre la tela.
    if ($producto instanceof ProductInterface) {
      $fondo = $form_state->getValue([self::CAMPO_FONDO, 0, 'target_id']);
      $ofrecidos = $inicial ? [] : array_map(
        static fn ($termino) => (string) $termino->id(),
        self::fondosDisponibles($producto)
      );
      if ($ofrecidos === []) {
        $form_state->setValue(self::CAMPO_FONDO, []);
      }
      elseif ($fondo === NULL || $fondo === '' || !in_array((string) $fondo, $ofrecidos, TRUE)) {
        $form_state->setErrorByName(self::CAMPO_FONDO, new TranslatableMarkup('Choose the background for the embroidery.'));
        return;
      }
    }

    if ($inicial && mb_strlen($texto) > 1) {
      $form_state->setErrorByName($campo, new TranslatableMarkup('This product is personalised with a single initial.'));
      return;
    }

    // Los productos que se bordan en caja alta lo dicen en su ficha
    // (field_bordado_mayusculas), y se aplica aquí y no solo en la vista previa:
    // lo que se guarda en la línea es lo que va a leer el taller, así que el
    // pedido, el correo y el albarán tienen que decir MÓNICA y no Mónica.
    if (!$inicial && $producto instanceof ProductInterface
      && $producto->hasField('field_bordado_mayusculas')
      && (bool) $producto->get('field_bordado_mayusculas')->value) {
      $texto = mb_strtoupper($texto);
    }

    $form_state->setValue([$campo, 0, 'value'], $texto);
  }

  /**
   * Deja en el formulario solo los fondos que ofrece este producto.
   *
   * El campo de la línea de pedido apunta a todo el vocabulario, así que aquí se
   * recorta a lo que declara el producto en field_fondos_disponibles. Un
   * producto que no declara ninguno no ve el selector y borda el nombre
   * directamente sobre la tela, que es lo que hacen los 279 de nombre migrados.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   * @param \Drupal\commerce_product\Entity\ProductInterface $producto
   *   El producto de la ficha.
   */
  private function preparaFondos(array &$form, ProductInterface $producto): void {
    if (!isset($form[self::CAMPO_FONDO]['widget']['#options'])) {
      return;
    }
    // El fondo es el de un NOMBRE bordado: una inicial es un parche que va
    // sobre la tela y no dentro de una nube, así que en modo inicial ni se
    // pregunta aunque el producto tuviera fondos declarados.
    $disponibles = $this->modo($producto) === 'inicial' ? [] : self::fondosDisponibles($producto);
    if ($disponibles === []) {
      $form[self::CAMPO_FONDO]['#access'] = FALSE;
      return;
    }

    $opciones = [];
    foreach ($disponibles as $fondo) {
      $opciones[$fondo->id()] = $fondo->label();
    }
    // Sin la opción vacía: estos productos se bordan siempre dentro del fondo,
    // y quien no quiera bordado desmarca la casilla, que ya vacía la línea
    // entera. Es la misma regla que el formato de la inicial.
    $form[self::CAMPO_FONDO]['widget']['#options'] = $opciones;

    // El primero viene elegido de entrada: el campo no es obligatorio, así que
    // sin esto se podía pedir el bordado sin decir sobre qué fondo va. Ojo: en
    // un campo de un solo valor, options_buttons espera el tid **suelto**; con
    // un array dentro no marca ningún radio.
    $actual = $form[self::CAMPO_FONDO]['widget']['#default_value'] ?? NULL;
    if (in_array($actual, [NULL, '', '_none', [], ['_none']], TRUE)) {
      $primero = array_key_first($opciones);
      $form[self::CAMPO_FONDO]['widget']['#default_value'] =
        ($form[self::CAMPO_FONDO]['widget']['#type'] ?? '') === 'checkboxes' ? [$primero] : $primero;
    }
  }

  /**
   * Fondos que declara el producto, en el orden en que los declara.
   *
   * @return array<int, TermInterface>
   *   Los términos de fondo publicados.
   */
  private static function fondosDisponibles(ProductInterface $producto): array {
    if (!$producto->hasField(self::CAMPO_FONDO_PRODUCTO)) {
      return [];
    }
    $campo = $producto->get(self::CAMPO_FONDO_PRODUCTO);
    if (!$campo instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    return array_values(array_filter(
      $campo->referencedEntities(),
      static fn ($fondo) => $fondo instanceof TermInterface && $fondo->isPublished()
    ));
  }

  /**
   * Deja en el formulario solo los extras que ofrece este producto.
   *
   * El campo de la línea de pedido apunta a todo el vocabulario, así que aquí se
   * recorta a lo que declara el producto en field_extras_disponibles. Un
   * producto que no declara ninguno no ve el bloque: es lo que hace que el
   * llavero no aparezca en un polo con inicial.
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  private function preparaExtras(array &$form, ProductInterface $producto): void {
    $disponibles = $this->extrasDisponibles($producto);
    if ($disponibles === [] || !isset($form[self::CAMPO_EXTRAS]['widget']['#options'])) {
      foreach ([self::CAMPO_EXTRAS, self::CAMPO_EXTRAS_TEXTO] as $campo) {
        if (isset($form[$campo])) {
          $form[$campo]['#access'] = FALSE;
        }
      }
      return;
    }

    $opciones = [];
    $piden_texto = [];
    foreach ($disponibles as $extra) {
      $opciones[$extra->id()] = $this->etiquetaDeExtra($extra);
      if ($this->extraPideTexto($extra)) {
        $piden_texto[] = $extra->id();
      }
    }
    $form[self::CAMPO_EXTRAS]['widget']['#options'] = $opciones;
    $form['#validate'][] = [self::class, 'validarExtras'];

    if ($piden_texto === []) {
      $form[self::CAMPO_EXTRAS_TEXTO]['#access'] = FALSE;
      return;
    }
    // El texto solo se pide si está marcado alguno de los extras que lo piden.
    $condiciones = [];
    foreach ($piden_texto as $tid) {
      $condiciones[] = [':input[name="' . self::CAMPO_EXTRAS . '[' . $tid . ']"]' => ['checked' => TRUE]];
    }
    $form[self::CAMPO_EXTRAS_TEXTO]['#states'] = ['visible' => count($condiciones) === 1 ? $condiciones[0] : [$condiciones]];
  }

  /**
   * Extras que declara el producto.
   *
   * @return array<int, TermInterface>
   *   Los términos de extra.
   */
  private function extrasDisponibles(ProductInterface $producto): array {
    if (!$producto->hasField(self::CAMPO_EXTRAS_PRODUCTO)) {
      return [];
    }
    $campo = $producto->get(self::CAMPO_EXTRAS_PRODUCTO);
    if (!$campo instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    return array_values(array_filter(
      $campo->referencedEntities(),
      static fn ($extra) => $extra instanceof TermInterface && $extra->isPublished()
    ));
  }

  /**
   * Etiqueta de la casilla de un extra, con su precio si lo tiene.
   */
  private function etiquetaDeExtra(TermInterface $extra): TranslatableMarkup|string {
    $precio = $extra->hasField('field_precio') && !$extra->get('field_precio')->isEmpty()
      ? $extra->get('field_precio')->first()
      : NULL;
    if (!$precio instanceof PriceItem) {
      return (string) $extra->label();
    }
    $importe = $precio->toPrice();
    if ($importe->isZero()) {
      return (string) $extra->label();
    }

    return new TranslatableMarkup('@extra +@precio', [
      '@extra' => $extra->label(),
      '@precio' => $this->currencyFormatter->format($importe->getNumber(), $importe->getCurrencyCode()),
    ]);
  }

  /**
   * Si un extra necesita un texto del cliente.
   */
  private function extraPideTexto(TermInterface $extra): bool {
    return $extra->hasField('field_pide_texto')
      && !$extra->get('field_pide_texto')->isEmpty()
      && (bool) $extra->get('field_pide_texto')->value;
  }

  /**
   * Si el producto admite bordado.
   */
  private function esPersonalizable(ProductInterface $producto): bool {
    return $producto->hasField('field_personalizable')
      && !$producto->get('field_personalizable')->isEmpty()
      && (bool) $producto->get('field_personalizable')->value;
  }

  /**
   * Modo de personalización del producto: inicial o texto.
   *
   * Sin valor se asume texto libre, que es lo que hacía el D7.
   */
  private function modo(ProductInterface $producto): string {
    if (!$producto->hasField('field_modo_personalizacion')
      || $producto->get('field_modo_personalizacion')->isEmpty()) {
      return 'texto';
    }
    return (string) $producto->get('field_modo_personalizacion')->value;
  }

}
