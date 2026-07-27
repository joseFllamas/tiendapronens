/**
 * @file
 * Categoría: chips de faceta desplegables, toggle Vista 2/4 y auto-envío del
 * orden. Vanilla JS, sin jQuery.
 *
 * Todo funciona sin JS: las facetas son enlaces, el orden tiene botón Aplicar
 * y el grid arranca con data-pro-cols="4" en el markup. El JS solo añade el
 * desplegable, la preferencia de columnas y el envío al cambiar el select.
 */

(function (Drupal, once) {
  'use strict';

  const CLAVE_VISTA = 'pronens.catalogo.columnas';
  const COLUMNAS_VALIDAS = ['2', '4'];

  /**
   * Chips de faceta: un desplegable por chip, uno abierto a la vez.
   *
   * @param {Element} barra - Contenedor [data-pro-filters].
   */
  function iniciaFacetas(barra) {
    const facetas = Array.from(barra.querySelectorAll('[data-pro-facet]'));

    function cierraTodas(excepto) {
      facetas.forEach((faceta) => {
        if (faceta === excepto) {
          return;
        }
        faceta.querySelector('[data-pro-facet-toggle]').setAttribute('aria-expanded', 'false');
        faceta.querySelector('[data-pro-facet-panel]').hidden = true;
      });
    }

    facetas.forEach((faceta) => {
      const boton = faceta.querySelector('[data-pro-facet-toggle]');
      const panel = faceta.querySelector('[data-pro-facet-panel]');
      if (!boton || !panel) {
        return;
      }
      boton.addEventListener('click', () => {
        const abierto = boton.getAttribute('aria-expanded') === 'true';
        cierraTodas(faceta);
        boton.setAttribute('aria-expanded', String(!abierto));
        panel.hidden = abierto;
      });
    });

    // Clic fuera y Escape cierran, como el mega menú.
    document.addEventListener('click', (e) => {
      if (!barra.contains(e.target)) {
        cierraTodas(null);
      }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') {
        return;
      }
      const abierta = facetas.find(
        (f) => f.querySelector('[data-pro-facet-toggle]').getAttribute('aria-expanded') === 'true',
      );
      if (abierta) {
        cierraTodas(null);
        abierta.querySelector('[data-pro-facet-toggle]').focus();
      }
    });
  }

  /**
   * Toggle Vista 2/4, recordado en localStorage.
   *
   * @param {Element} toggle - Contenedor [data-pro-view-toggle].
   */
  function iniciaVista(toggle) {
    const grid = document.querySelector('[data-pro-grid]');
    if (!grid) {
      return;
    }
    const botones = Array.from(toggle.querySelectorAll('[data-pro-view]'));

    function aplica(columnas) {
      grid.setAttribute('data-pro-cols', columnas);
      botones.forEach((boton) => {
        boton.setAttribute('aria-pressed', String(boton.dataset.proView === columnas));
      });
    }

    // Modo privado y cuotas llenas hacen que localStorage lance.
    let guardado = null;
    try {
      guardado = window.localStorage.getItem(CLAVE_VISTA);
    }
    catch {
      guardado = null;
    }
    if (COLUMNAS_VALIDAS.includes(guardado)) {
      aplica(guardado);
    }

    botones.forEach((boton) => {
      boton.addEventListener('click', () => {
        const columnas = boton.dataset.proView;
        aplica(columnas);
        try {
          window.localStorage.setItem(CLAVE_VISTA, columnas);
        }
        catch {
          // Sin persistencia; la vista de esta página ya ha cambiado.
        }
      });
    });
  }

  /**
   * El select de orden envía al cambiar; el botón Aplicar deja de hacer falta.
   *
   * @param {Element} contenedor - Contenedor [data-pro-sort].
   */
  function iniciaOrden(contenedor) {
    const select = contenedor.querySelector('select');
    // El contenedor vive DENTRO del form (views-exposed-form.html.twig pinta
    // solo su contenido), así que el formulario es un ancestro.
    const formulario = contenedor.closest('form');
    if (!select || !formulario) {
      return;
    }
    contenedor.classList.add('is-auto');
    select.addEventListener('change', () => {
      // requestSubmit y no submit(): respeta la validación y no choca con el
      // input llamado "submit" que mete Views.
      if (typeof formulario.requestSubmit === 'function') {
        formulario.requestSubmit();
      }
      else {
        formulario.submit();
      }
    });
  }

  Drupal.behaviors.pronensCatalogo = {
    attach(context) {
      once('pro-filters', '[data-pro-filters]', context).forEach(iniciaFacetas);
      once('pro-view-toggle', '[data-pro-view-toggle]', context).forEach(iniciaVista);
      once('pro-sort', '[data-pro-sort]', context).forEach(iniciaOrden);
    },
  };
})(Drupal, once);
