/**
 * @file
 * Colocación del bordado arrastrándolo sobre la foto del producto.
 *
 * Los tres campos numéricos siguen siendo la fuente de verdad: aquí solo se
 * escriben al arrastrar y se leen para colocar la marca. Sin JS el formulario
 * sigue funcionando escribiendo los porcentajes a mano.
 *
 * En modo nombre la marca no es un parche cuadrado: el tamaño es la ALTURA de
 * la letra y el ancho lo pone el nombre, igual que en el bordado real. Y se
 * repinta con la fuente, el color y las mayúsculas que se elijan debajo, sin
 * guardar ni recargar.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Inicializa el lienzo de colocación.
   *
   * @param {Element} lienzo - Contenedor [data-pro-montaje-lienzo].
   */
  function init(lienzo) {
    const grupo = lienzo.closest('.pro-montaje');
    const marca = lienzo.querySelector('[data-pro-montaje-marca]');
    const barra = grupo ? grupo.querySelector('[data-pro-montaje-barra]') : null;
    const barraGiro = grupo ? grupo.querySelector('[data-pro-montaje-barra-rotacion]') : null;
    const nombre = lienzo.dataset.proMontajeModo !== 'inicial';
    const campos = {};
    if (!grupo || !marca) {
      return;
    }
    grupo.querySelectorAll('[data-pro-montaje-campo]').forEach((envoltorio) => {
      const input = envoltorio.matches('input') ? envoltorio : envoltorio.querySelector('input');
      if (input) {
        campos[envoltorio.dataset.proMontajeCampo] = input;
      }
    });
    if (!campos.x || !campos.y || !campos.tamano) {
      return;
    }

    const lee = (input, defecto) => {
      const valor = parseFloat(input.value);
      return Number.isFinite(valor) ? valor : defecto;
    };

    function coloca() {
      const x = lee(campos.x, 50);
      const y = lee(campos.y, 50);
      const tamano = lee(campos.tamano, nombre ? 5 : 12);
      const giro = campos.rotacion ? lee(campos.rotacion, 0) : 0;
      marca.style.left = `${x}%`;
      marca.style.top = `${y}%`;
      // El centrado va aquí y no solo en el CSS: la rotación se compone con él,
      // y una transform inline sustituye la de la hoja de estilos entera.
      marca.style.transform = `translate(-50%, -50%) rotate(${giro}deg)`;
      if (nombre) {
        // La altura de la letra, en % del ancho de la foto: el CSS la resuelve
        // con cqw sobre el lienzo, así que sigue valiendo si cambia de tamaño.
        marca.style.setProperty('--pro-montaje-alto', String(tamano));
      }
      else {
        marca.style.width = `${tamano}%`;
      }
    }

    // Escribe con dos decimales: más precisión no la aprecia nadie y ensucia.
    function guarda(input, valor) {
      input.value = String(Math.round(valor * 100) / 100);
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function mueve(evento) {
      const caja = lienzo.getBoundingClientRect();
      const x = ((evento.clientX - caja.left) / caja.width) * 100;
      const y = ((evento.clientY - caja.top) / caja.height) * 100;
      guarda(campos.x, Math.min(100, Math.max(0, x)));
      guarda(campos.y, Math.min(100, Math.max(0, y)));
      coloca();
    }

    let arrastrando = false;
    // Pointer events: vale igual para ratón, lápiz y dedo.
    lienzo.addEventListener('pointerdown', (e) => {
      arrastrando = true;
      lienzo.setPointerCapture(e.pointerId);
      mueve(e);
    });
    lienzo.addEventListener('pointermove', (e) => {
      if (arrastrando) {
        mueve(e);
      }
    });
    lienzo.addEventListener('pointerup', () => {
      arrastrando = false;
    });
    lienzo.addEventListener('pointercancel', () => {
      arrastrando = false;
    });

    if (barra) {
      barra.addEventListener('input', () => {
        guarda(campos.tamano, parseFloat(barra.value));
        coloca();
      });
    }
    if (barraGiro && campos.rotacion) {
      barraGiro.addEventListener('input', () => {
        guarda(campos.rotacion, parseFloat(barraGiro.value));
        coloca();
      });
    }
    // Escribir a mano en los números también recoloca la marca.
    Object.values(campos).forEach((input) => {
      input.addEventListener('input', coloca);
    });

    // --- Fuente, color y mayúsculas: se ven al elegirlos ---
    const opciones = {};
    grupo.querySelectorAll('[data-pro-montaje-opcion]').forEach((envoltorio) => {
      opciones[envoltorio.dataset.proMontajeOpcion] = envoltorio;
    });

    function viste() {
      const fuente = opciones.fuente
        ? opciones.fuente.querySelector('select, input:checked')
        : null;
      if (fuente) {
        ['unicase', 'script', 'letra'].forEach((clave) => {
          marca.classList.toggle(
            `pro-montaje__marca--fuente-${clave}`,
            (fuente.value || 'unicase') === clave
          );
        });
      }
      // El widget de color_field esconde su input y pinta unos botones que lo
      // rellenan con jQuery, así que el valor se lee del input después del clic
      // (que sí burbujea) y no de un evento propio del widget.
      const color = opciones.color
        ? opciones.color.querySelector('input[name*="[color]"]')
        : null;
      if (color) {
        marca.style.setProperty('--pro-montaje-color', color.value || '');
      }
      const caja = opciones.mayusculas
        ? opciones.mayusculas.querySelector('input[type="checkbox"]')
        : null;
      if (caja) {
        marca.classList.toggle('pro-montaje__marca--caps', caja.checked);
      }
    }

    Object.values(opciones).forEach((envoltorio) => {
      ['input', 'change', 'click'].forEach((evento) => {
        envoltorio.addEventListener(evento, viste);
      });
    });

    coloca();
    if (nombre) {
      viste();
    }
  }

  Drupal.behaviors.pronensMontaje = {
    attach(context) {
      once('pro-montaje', '[data-pro-montaje-lienzo]', context).forEach(init);
    },
  };
})(Drupal, once);
