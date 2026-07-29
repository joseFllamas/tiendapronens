/**
 * @file
 * Ficha: stepper de cantidad, vista previa del bordado sobre la primera foto y
 * total del CTA. Vanilla JS, sin jQuery.
 *
 * Sin JS la ficha sigue completa: el input de cantidad es un number nativo, el
 * CTA lleva su texto de Commerce y la card de personalización la pliegan
 * y despliega el sistema de estados de core. Lo que añade el JS es el stepper,
 * la vista previa y el precio total en vivo.
 */

(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Formatea un importe en euros al estilo español (1.234,56 €).
   *
   * @param {number} valor - Importe.
   * @param {string} moneda - Código ISO de moneda.
   *
   * @return {string} Importe formateado.
   */
  function formatea(valor, moneda) {
    return new Intl.NumberFormat(document.documentElement.lang || 'es', {
      style: 'currency',
      currency: moneda || 'EUR',
    }).format(valor);
  }

  /**
   * Stepper de cantidad alrededor del input number de Commerce.
   *
   * @param {Element} input - El input [data-pro-qty-input].
   */
  function iniciaStepper(input) {
    const envoltorio = document.createElement('div');
    envoltorio.className = 'pro-stepper';
    input.parentNode.insertBefore(envoltorio, input);

    const menos = document.createElement('button');
    menos.type = 'button';
    menos.className = 'pro-stepper__btn';
    menos.textContent = '−';
    menos.setAttribute('aria-label', Drupal.t('Decrease quantity'));

    const mas = document.createElement('button');
    mas.type = 'button';
    mas.className = 'pro-stepper__btn';
    mas.textContent = '+';
    mas.setAttribute('aria-label', Drupal.t('Increase quantity'));

    envoltorio.appendChild(menos);
    envoltorio.appendChild(input);
    envoltorio.appendChild(mas);

    const minimo = parseInt(input.getAttribute('min'), 10) || 1;

    function sincroniza() {
      menos.disabled = (parseInt(input.value, 10) || minimo) <= minimo;
    }

    function cambia(delta) {
      const actual = parseInt(input.value, 10) || minimo;
      input.value = String(Math.max(minimo, actual + delta));
      // El input es la fuente de verdad: el resto escucha su change.
      input.dispatchEvent(new Event('change', { bubbles: true }));
      sincroniza();
    }

    menos.addEventListener('click', () => cambia(-1));
    mas.addEventListener('click', () => cambia(1));
    input.addEventListener('change', sincroniza);
    sincroniza();
  }

  /**
   * Vista previa del bordado y precio total en vivo.
   *
   * @param {Element} form - El formulario [class~="pro-buy-form"].
   */
  function iniciaPersonalizacion(form) {
    const ajustes = (drupalSettings.pronens && drupalSettings.pronens.ficha) || {};
    // El precio sale del formulario, no de drupalSettings: los settings se
    // fijan al renderizar la página y no cambian al elegir otra variación,
    // mientras que el formulario sí lo vuelve a traer en cada refresco AJAX.
    const base = Number(form.dataset.proPrecio) || Number(ajustes.precioBase) || 0;
    const baseTexto = form.dataset.proPrecioTexto || '';
    const recargo = Number(ajustes.recargo) || 0;
    const moneda = ajustes.moneda || 'EUR';
    const preciosExtra = ajustes.extras || {};

    const casilla = form.querySelector('[data-pro-perso-toggle]');
    const texto = form.querySelector('[data-pro-perso-texto]');
    const cantidad = form.querySelector('[data-pro-qty-input]');
    const cta = form.querySelector('[data-pro-cta]');
    const preview = document.querySelector('[data-pro-preview]');
    const previewTexto = document.querySelector('[data-pro-preview-text]');
    const total = document.querySelector('[data-pro-total]');
    const fotoPrincipal = document.querySelector('.pro-ficha__shot--main img');
    const desglose = document.querySelector('[data-pro-breakdown]');
    const ctaBase = cta ? cta.value : '';

    // En modo inicial el texto es una rejilla de radios y el marcador cae en su
    // envoltorio, no en cada input; en modo nombre es un input y lo lleva él.
    function valorTexto() {
      if (!texto) {
        return '';
      }
      if (texto.tagName === 'INPUT') {
        return (texto.value || '').trim();
      }
      const marcado = texto.querySelector('input:checked');
      return marcado ? marcado.value : '';
    }

    function bordadoActivo() {
      return Boolean(casilla && casilla.checked && valorTexto() !== '');
    }

    // Suma de los extras marcados. El valor de cada casilla es el id del
    // término, que es la clave con la que llegan los precios.
    function totalExtras() {
      let suma = 0;
      form.querySelectorAll('[data-pro-extras] input[type="checkbox"]:checked').forEach((casillaExtra) => {
        suma += Number(preciosExtra[casillaExtra.value]) || 0;
      });
      return suma;
    }

    // Perfil e interior del formato marcado, para dibujar letra y vista previa.
    function coloresFormato() {
      const marcado = form.querySelector('.pro-formatos input[type="radio"]:checked');
      const colores = marcado ? (ajustes.formatos || {})[marcado.value] : null;
      return colores || null;
    }

    // Las letras de la rejilla y la vista previa se dibujan con esos colores:
    // el relleno en color y el contorno con text-stroke, que es lo más parecido
    // al parche bordado sin necesitar una foto por combinación.
    function pintaLetras() {
      const colores = coloresFormato();
      const rejilla = form.querySelector('.pro-letras');
      const destinos = [];
      if (rejilla) {
        destinos.push(rejilla);
      }
      if (preview) {
        destinos.push(preview);
      }
      destinos.forEach((destino) => {
        destino.style.setProperty('--pro-letra-interior', colores ? colores.interior : '');
        destino.style.setProperty('--pro-letra-perfil', colores ? colores.perfil : '');
      });
    }

    // La foto de la variación elegida es la base sin letra del montaje, así que
    // al cambiar de color la foto principal cambia con ella.
    function sincronizaFoto() {
      const url = form.dataset.proMontaje;
      if (fotoPrincipal && url && fotoPrincipal.getAttribute('src') !== url) {
        fotoPrincipal.setAttribute('src', url);
        fotoPrincipal.removeAttribute('srcset');
      }
    }

    function pinta() {
      const activo = bordadoActivo();
      const unidades = Math.max(1, parseInt(cantidad && cantidad.value, 10) || 1);
      const unitario = base + (activo ? recargo : 0) + totalExtras();

      pintaLetras();
      sincronizaFoto();
      if (preview && previewTexto) {
        preview.hidden = !activo;
        previewTexto.textContent = activo ? valorTexto() : '';
      }
      if (desglose) {
        desglose.hidden = !activo;
        // El desglose lo pinta PHP con la variación por defecto; al cambiar de
        // talla hay que rehacerlo con el precio de la elegida.
        if (activo && baseTexto && recargo > 0) {
          desglose.textContent = `${baseTexto} + ${formatea(recargo, moneda)} ${ajustes.etiquetaBordado || ''}`.trim();
        }
      }
      if (total && base > 0) {
        total.textContent = formatea(unitario, moneda);
      }
      if (cta && base > 0) {
        cta.value = `${ctaBase} · ${formatea(unitario * unidades, moneda)}`;
      }
    }

    [casilla, texto, cantidad].forEach((campo) => {
      if (!campo) {
        return;
      }
      campo.addEventListener('input', pinta);
      campo.addEventListener('change', pinta);
    });
    form.querySelectorAll('.pro-formatos input[type="radio"]').forEach((radio) => {
      radio.addEventListener('change', pinta);
    });
    form.querySelectorAll('[data-pro-extras] input[type="checkbox"]').forEach((casillaExtra) => {
      casillaExtra.addEventListener('change', pinta);
    });
    // Con la rejilla, el change burbujea desde cada radio hasta el envoltorio.
    if (texto && texto.tagName !== 'INPUT') {
      texto.addEventListener('change', pinta);
    }
    pinta();
  }

  /**
   * Handlers del diálogo de la guía: cerrar, fondo y foco.
   *
   * Va aparte del enlace porque el diálogo vive en la plantilla del producto y
   * no se reemplaza nunca, mientras que el formulario sí.
   *
   * @param {Element} dialogo - El elemento [data-pro-guia].
   */
  function iniciaGuiaDialogo(dialogo) {
    dialogo.querySelectorAll('[data-pro-guia-close]').forEach((boton) => {
      boton.addEventListener('click', () => dialogo.close());
    });
    // Clic en el fondo: el backdrop no es un elemento, así que el evento llega
    // al propio dialog cuando se pulsa fuera del contenido.
    dialogo.addEventListener('click', (e) => {
      if (e.target === dialogo) {
        dialogo.close();
      }
    });
  }

  /**
   * Enlace que abre la guía, junto al selector de formato.
   *
   * El enlace se pone aquí y no en la plantilla porque el selector lo pinta el
   * formulario de Commerce. Y el `once` va sobre el selector, no sobre el
   * diálogo: al cambiar de talla o color, Commerce reemplaza el formulario por
   * AJAX y hay que volver a poner el enlace en el selector nuevo.
   *
   * @param {Element} selector - El contenedor .pro-formatos.
   */
  function iniciaGuiaEnlace(selector) {
    const dialogo = document.querySelector('[data-pro-guia]');
    if (!dialogo || typeof dialogo.showModal !== 'function') {
      return;
    }

    const enlace = document.createElement('button');
    enlace.type = 'button';
    enlace.className = 'pro-formato-ayuda';
    enlace.setAttribute('aria-haspopup', 'dialog');
    enlace.textContent = Drupal.t('How does it look?');
    selector.appendChild(enlace);

    enlace.addEventListener('click', () => dialogo.showModal());
    // Al cerrar, el foco vuelve al enlace que lo abrió, que puede ser uno nuevo
    // si el formulario se ha reemplazado entre medias.
    dialogo.addEventListener('close', () => {
      if (enlace.isConnected) {
        enlace.focus();
      }
    });
  }

  /**
   * Lightbox de la galería: la foto entera, sin el recorte 3:4 de la cuadrícula.
   *
   * Sin JS cada foto es un enlace a su versión grande, así que la galería sigue
   * siendo útil; con JS el clic abre el diálogo y se pasan las fotos con las
   * flechas, arrastrando o con los botones. Escape y el foco los gestiona el
   * propio <dialog>.
   *
   * @param {Element} galeria - Contenedor [data-pro-galeria].
   */
  function iniciaZoom(galeria) {
    const dialogo = document.querySelector('[data-pro-zoom-dialog]');
    const enlaces = Array.from(galeria.querySelectorAll('[data-pro-zoom]'));
    if (!dialogo || enlaces.length === 0 || typeof dialogo.showModal !== 'function') {
      return;
    }
    const img = dialogo.querySelector('[data-pro-zoom-img]');
    const pie = dialogo.querySelector('[data-pro-zoom-caption]');
    const contador = dialogo.querySelector('[data-pro-zoom-count]');
    // Título e imagen de cada foto salen del propio enlace y su <img>.
    const fotos = enlaces.map((enlace) => ({
      url: enlace.getAttribute('href'),
      alt: (enlace.querySelector('img') || {}).alt || '',
      // El pie llega vacío cuando el alt es un nombre de fichero heredado.
      pie: enlace.dataset.proPie || '',
    }));
    let actual = 0;
    let origen = null;

    function muestra(indice) {
      actual = (indice + fotos.length) % fotos.length;
      img.src = fotos[actual].url;
      img.alt = fotos[actual].alt;
      if (pie) {
        pie.textContent = fotos[actual].pie;
      }
      if (contador) {
        contador.textContent = `${actual + 1} / ${fotos.length}`;
      }
    }

    function abre(indice, disparador) {
      origen = disparador || null;
      muestra(indice);
      dialogo.showModal();
    }

    enlaces.forEach((enlace, indice) => {
      enlace.addEventListener('click', (e) => {
        e.preventDefault();
        abre(indice, enlace);
      });
    });

    const prev = dialogo.querySelector('[data-pro-zoom-prev]');
    const next = dialogo.querySelector('[data-pro-zoom-next]');
    if (prev) {
      prev.addEventListener('click', () => muestra(actual - 1));
    }
    if (next) {
      next.addEventListener('click', () => muestra(actual + 1));
    }
    dialogo.querySelectorAll('[data-pro-zoom-close]').forEach((boton) => {
      boton.addEventListener('click', () => dialogo.close());
    });
    // Clic en el fondo: el evento llega al propio dialog, no al contenido.
    dialogo.addEventListener('click', (e) => {
      if (e.target === dialogo) {
        dialogo.close();
      }
    });
    dialogo.addEventListener('keydown', (e) => {
      if (fotos.length < 2) {
        return;
      }
      if (e.key === 'ArrowRight') {
        e.preventDefault();
        muestra(actual + 1);
      }
      else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        muestra(actual - 1);
      }
    });
    dialogo.addEventListener('close', () => {
      if (origen) {
        origen.focus();
      }
    });

    // Arrastrar de lado en táctil.
    let inicioX = null;
    dialogo.addEventListener('touchstart', (e) => {
      inicioX = e.changedTouches[0].clientX;
    }, { passive: true });
    dialogo.addEventListener('touchend', (e) => {
      if (inicioX === null || fotos.length < 2) {
        return;
      }
      const avance = e.changedTouches[0].clientX - inicioX;
      if (Math.abs(avance) > 45) {
        muestra(actual + (avance < 0 ? 1 : -1));
      }
      inicioX = null;
    }, { passive: true });
  }

  Drupal.behaviors.pronensFicha = {
    attach(context) {
      once('pro-qty', '[data-pro-qty-input]', context).forEach(iniciaStepper);
      once('pro-buy-form', '.pro-buy-form', context).forEach(iniciaPersonalizacion);
      once('pro-guia-dialogo', '[data-pro-guia]', context).forEach(iniciaGuiaDialogo);
      once('pro-guia-enlace', '.pro-formatos', context).forEach(iniciaGuiaEnlace);
      once('pro-galeria', '[data-pro-galeria]', context).forEach(iniciaZoom);
    },
  };
})(Drupal, once, drupalSettings);
