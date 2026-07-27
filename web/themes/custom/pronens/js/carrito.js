/**
 * @file
 * Carrito flyout: abrir, cerrar y devolver el foco. Vanilla JS, sin jQuery.
 *
 * Sin JS el icono del carrito es un enlace a /cart y el panel no se muestra
 * nunca: el contenido ya viaja en el HTML (lo pinta el lazy builder del bloque
 * de Commerce), así que abrir no pide ninguna petición.
 */

(function (Drupal, once) {
  'use strict';

  const FOCO = 'a[href], button:not([disabled]), input:not([type="hidden"]), select, textarea';

  /**
   * Inicializa un carrito con panel.
   *
   * @param {Element} cart - Contenedor [data-pro-cart].
   */
  function init(cart) {
    const toggle = cart.querySelector('[data-pro-cart-toggle]');
    const panel = cart.querySelector('[data-pro-cart-panel]');
    if (!toggle || !panel) {
      return;
    }

    // El bloque del carrito vive dentro del header sticky, que crea contexto de
    // apilamiento: dejando el panel ahí, su z-index se resuelve dentro del
    // header y el overlay (que cuelga del body) se le pone por encima y come
    // los clics. Al mover el panel al body, panel y overlay comparten contexto
    // y además el oscurecido cubre también la cabecera.
    document.body.appendChild(panel);

    const overlay = document.createElement('div');
    overlay.className = 'pro-cart-overlay';
    document.body.appendChild(overlay);
    let abierto = false;

    function abre() {
      abierto = true;
      // Destapar antes de animar: con hidden no hay transición posible.
      panel.hidden = false;
      // Un frame de margen para que el navegador vea el estado inicial.
      requestAnimationFrame(() => panel.classList.add('is-open'));
      overlay.classList.add('is-visible');
      toggle.setAttribute('aria-expanded', 'true');
      const primero = panel.querySelector(FOCO);
      if (primero) {
        primero.focus();
      }
    }

    function cierra() {
      if (!abierto) {
        return;
      }
      abierto = false;
      panel.classList.remove('is-open');
      overlay.classList.remove('is-visible');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
      // Se vuelve a tapar al acabar la transición, no antes.
      const tapar = (e) => {
        if (e.target === panel && !abierto) {
          panel.hidden = true;
        }
      };
      panel.addEventListener('transitionend', tapar, { once: true });
      // Con prefers-reduced-motion no hay transición y el evento no llega.
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        panel.hidden = true;
      }
    }

    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      if (abierto) {
        cierra();
      }
      else {
        abre();
      }
    });
    panel.querySelectorAll('[data-pro-cart-close]').forEach((boton) => {
      boton.addEventListener('click', cierra);
    });
    overlay.addEventListener('click', cierra);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && abierto) {
        cierra();
        return;
      }
      // Foco atrapado dentro del panel mientras está abierto.
      if (e.key !== 'Tab' || !abierto) {
        return;
      }
      const focusables = Array.from(panel.querySelectorAll(FOCO)).filter((el) => el.offsetParent !== null);
      if (focusables.length === 0) {
        return;
      }
      const primero = focusables[0];
      const ultimo = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === primero) {
        e.preventDefault();
        ultimo.focus();
      }
      else if (!e.shiftKey && document.activeElement === ultimo) {
        e.preventDefault();
        primero.focus();
      }
    });
  }

  Drupal.behaviors.pronensCarrito = {
    attach(context) {
      once('pro-cart', '[data-pro-cart]', context).forEach(init);
    },
  };
})(Drupal, once);
