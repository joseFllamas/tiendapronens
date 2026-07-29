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
    const base = Number(ajustes.precioBase) || 0;
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

    function pinta() {
      const activo = bordadoActivo();
      const unidades = Math.max(1, parseInt(cantidad && cantidad.value, 10) || 1);
      const unitario = base + (activo ? recargo : 0) + totalExtras();

      pintaLetras();
      if (preview && previewTexto) {
        preview.hidden = !activo;
        previewTexto.textContent = activo ? valorTexto() : '';
      }
      if (desglose) {
        desglose.hidden = !activo;
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
   * Diálogo con la guía del formato, y el enlace que lo abre.
   *
   * El enlace se pone aquí, junto al selector de formato, en lugar de en la
   * plantilla, porque el selector lo pinta el formulario de Commerce. Sin JS el
   * diálogo no se abre, así que el enlace tampoco aparece.
   *
   * @param {Element} dialogo - El elemento [data-pro-guia].
   */
  function iniciaGuia(dialogo) {
    const selector = document.querySelector('.pro-formatos');
    if (!selector || typeof dialogo.showModal !== 'function') {
      return;
    }

    const enlace = document.createElement('button');
    enlace.type = 'button';
    enlace.className = 'pro-formato-ayuda';
    enlace.setAttribute('aria-haspopup', 'dialog');
    enlace.textContent = Drupal.t('How does it look?');
    selector.appendChild(enlace);

    enlace.addEventListener('click', () => dialogo.showModal());
    dialogo.querySelectorAll('[data-pro-guia-close]').forEach((boton) => {
      boton.addEventListener('click', () => dialogo.close());
    });
    // Clic en el fondo: el backdrop no es un elemento, así que se compara con
    // el propio dialog, que es lo que recibe el evento fuera del contenido.
    dialogo.addEventListener('click', (e) => {
      if (e.target === dialogo) {
        dialogo.close();
      }
    });
    dialogo.addEventListener('close', () => enlace.focus());
  }

  Drupal.behaviors.pronensFicha = {
    attach(context) {
      once('pro-qty', '[data-pro-qty-input]', context).forEach(iniciaStepper);
      once('pro-buy-form', '.pro-buy-form', context).forEach(iniciaPersonalizacion);
      once('pro-guia', '[data-pro-guia]', context).forEach(iniciaGuia);
    },
  };
})(Drupal, once, drupalSettings);
