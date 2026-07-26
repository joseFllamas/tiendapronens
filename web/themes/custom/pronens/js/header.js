/**
 * @file
 * Header: mega menú (hover con intención, click/teclado, Escape) y
 * off-canvas mobile con acordeones. Vanilla JS, sin jQuery.
 */

(function (Drupal, once) {
  'use strict';

  const HOVER_DELAY = 100;
  const MOBILE_QUERY = '(max-width: 1024px)';

  /**
   * Cierra todos los paneles mega abiertos.
   *
   * @param {Element} header - Raíz del header.
   */
  function closeAllMegas(header) {
    header.querySelectorAll('[data-pro-mega].is-open').forEach((item) => {
      item.classList.remove('is-open');
      const toggle = item.querySelector('[data-pro-mega-toggle]');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /**
   * Inicializa un header.
   *
   * @param {Element} header - Elemento [data-pro-header].
   */
  function init(header) {
    const nav = header.querySelector('[data-pro-nav]');
    const burger = header.querySelector('[data-pro-burger]');
    const isMobile = () => window.matchMedia(MOBILE_QUERY).matches;
    let hoverTimer = null;

    // Overlay del off-canvas.
    const overlay = document.createElement('div');
    overlay.className = 'pro-nav-overlay';
    document.body.appendChild(overlay);

    function closeOffcanvas() {
      if (!nav) {
        return;
      }
      nav.classList.remove('is-open');
      overlay.classList.remove('is-visible');
      if (burger) {
        burger.setAttribute('aria-expanded', 'false');
      }
    }

    header.querySelectorAll('[data-pro-mega]').forEach((item) => {
      const toggle = item.querySelector('[data-pro-mega-toggle]');
      if (!toggle) {
        return;
      }

      // Hover con intención (solo desktop).
      item.addEventListener('mouseenter', () => {
        if (isMobile()) {
          return;
        }
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(() => {
          closeAllMegas(header);
          item.classList.add('is-open');
          toggle.setAttribute('aria-expanded', 'true');
        }, HOVER_DELAY);
      });

      item.addEventListener('mouseleave', () => {
        if (isMobile()) {
          return;
        }
        clearTimeout(hoverTimer);
      });

      // Click / Enter / Space: alterna (teclado, touch y acordeón mobile).
      toggle.addEventListener('click', () => {
        const open = item.classList.contains('is-open');
        if (!isMobile()) {
          closeAllMegas(header);
        }
        item.classList.toggle('is-open', !open);
        toggle.setAttribute('aria-expanded', String(!open));
      });
    });

    // Al salir del header (que incluye el panel), cerrar (desktop).
    header.addEventListener('mouseleave', () => {
      if (!isMobile()) {
        clearTimeout(hoverTimer);
        closeAllMegas(header);
      }
    });

    // Escape cierra mega y off-canvas, devolviendo el foco.
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') {
        return;
      }
      const openItem = header.querySelector('[data-pro-mega].is-open');
      if (openItem && !isMobile()) {
        closeAllMegas(header);
        const toggle = openItem.querySelector('[data-pro-mega-toggle]');
        if (toggle) {
          toggle.focus();
        }
      }
      if (nav && nav.classList.contains('is-open')) {
        closeOffcanvas();
        if (burger) {
          burger.focus();
        }
      }
    });

    // Cerrar mega si el foco sale del header (navegación por teclado).
    header.addEventListener('focusout', (e) => {
      if (!isMobile() && !header.contains(e.relatedTarget)) {
        closeAllMegas(header);
      }
    });

    // Burger: abre/cierra el off-canvas.
    if (burger && nav) {
      burger.addEventListener('click', () => {
        const open = nav.classList.toggle('is-open');
        overlay.classList.toggle('is-visible', open);
        burger.setAttribute('aria-expanded', String(open));
      });
      overlay.addEventListener('click', closeOffcanvas);
    }
  }

  Drupal.behaviors.pronensHeader = {
    attach(context) {
      once('pro-header', '[data-pro-header]', context).forEach(init);
    },
  };
})(Drupal, once);
