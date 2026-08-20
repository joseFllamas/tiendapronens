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
    const previewFondo = document.querySelector('[data-pro-preview-fondo]');
    const previewCaja = document.querySelector('[data-pro-preview-caja]');
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

    // El fondo del bordado (la nube de las mochilas y las bolsas): al cambiar
    // de color cambia la foto, la caja donde cabe el nombre y, si el término lo
    // dice, el hilo con el que se borda encima. Sin fondos, nada de esto existe
    // y el nombre se borda sobre la tela, como en el resto del catálogo.
    function pintaFondo() {
      if (!preview || !previewFondo) {
        return;
      }
      const marcado = form.querySelector('.pro-fondos input[type="radio"]:checked');
      const fondo = marcado ? (ajustes.fondos || {})[marcado.value] : null;
      if (!fondo || !fondo.foto) {
        return;
      }
      if (previewFondo.getAttribute('src') !== fondo.foto) {
        previewFondo.src = fondo.foto;
      }
      preview.style.setProperty('--pro-caja-ancho', `${fondo.ancho}%`);
      preview.style.setProperty('--pro-caja-alto', `${fondo.alto}%`);
      // Sin color propio manda el del producto, que es el que ya trae el
      // atributo style de la plantilla: se quita el de aquí y reaparece.
      if (fondo.color) {
        preview.style.setProperty('--pro-bordado-color', fondo.color);
      }
      else {
        preview.style.removeProperty('--pro-bordado-color');
      }
    }

    // La máquina borda a la altura configurada, pero un nombre largo no cabe
    // en su caja y el taller lo baja hasta que quepa: la ficha tiene que
    // enseñar eso mismo. La caja es la de dentro de la nube cuando hay fondo, y
    // sin fondo una de 8,6ch (unas 8 letras al tamaño configurado, ver
    // ficha.css): la zona de bordado de la prenda tampoco es infinita. Se mide
    // con offsetWidth y no con getBoundingClientRect(), que llegaría girado por
    // la rotación del montaje.
    function encoge() {
      if (!preview || !previewTexto || !previewCaja
        || !preview.classList.contains('pro-ficha__preview--nombre')) {
        return;
      }
      preview.style.setProperty('--pro-bordado-encoge', '1');
      const anchoCaja = previewCaja.offsetWidth;
      const ancho = previewTexto.offsetWidth;
      // Con la foto del fondo aún sin cargar, la caja mide cero: se deja el
      // factor en 1 y se vuelve a medir en cuanto la foto llega.
      if (!ancho || !anchoCaja) {
        return;
      }
      // El 0,98 es margen: offsetWidth viene redondeado a entero y sin él un
      // nombre justo se sale un píxel por la costura de la nube.
      let factor = (anchoCaja / ancho) * 0.98;
      // La altura solo limita dentro de la nube: sin fondo la caja no tiene
      // alto propio (mide lo que mida el texto) y el tope sería circular.
      if (preview.classList.contains('pro-ficha__preview--con-fondo')) {
        const altoCaja = previewCaja.offsetHeight;
        const alto = previewTexto.offsetHeight;
        if (!alto || !altoCaja) {
          return;
        }
        factor = Math.min(factor, (altoCaja / alto) * 0.98);
      }
      preview.style.setProperty('--pro-bordado-encoge', Math.min(1, factor).toFixed(3));
    }

    // La foto donde vive la vista previa, que puede no ser la primera
    // (field_bordado_foto: el bordado va en la espalda y el dibujo delante).
    // Al activarse el bordado se enciende un resplandor y, si la foto está
    // fuera de pantalla, se trae a la vista: sin señal, el cliente teclearía
    // mirando una foto y el nombre aparecería en otra.
    const shotPreview = preview ? preview.closest('.pro-ficha__shot') : null;
    let bordadoPrevio = false;

    function senalaFoto(activo) {
      if (!shotPreview) {
        return;
      }
      shotPreview.classList.toggle('pro-ficha__shot--brilla', activo);
      // Solo en el flanco de apagado a encendido: desplazarse en cada tecla
      // pelearía con el usuario por el scroll.
      if (activo && !bordadoPrevio) {
        const caja = shotPreview.getBoundingClientRect();
        const fuera = caja.top < 0 || caja.bottom > window.innerHeight
          || caja.left < 0 || caja.right > window.innerWidth;
        if (fuera) {
          shotPreview.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'nearest',
            inline: 'nearest',
          });
        }
      }
      bordadoPrevio = activo;
    }

    function pinta() {
      const activo = bordadoActivo();
      const unidades = Math.max(1, parseInt(cantidad && cantidad.value, 10) || 1);
      const unitario = base + (activo ? recargo : 0) + totalExtras();

      pintaLetras();
      if (preview && previewTexto) {
        preview.hidden = !activo;
        previewTexto.textContent = activo ? valorTexto() : '';
        pintaFondo();
        // Después de escribir el texto: se mide lo que hay, no lo que había.
        encoge();
        senalaFoto(activo);
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
    form.querySelectorAll('.pro-formatos input[type="radio"], .pro-fondos input[type="radio"]').forEach((radio) => {
      radio.addEventListener('change', pinta);
    });
    // La caja del texto se mide en porcentaje del fondo, así que hasta que la
    // foto no está no se sabe cuánto mide de alto. Con `once` porque la vista
    // previa vive en la plantilla del producto y no se reemplaza: sin él, cada
    // refresco AJAX del formulario apilaría otro handler sobre la misma foto.
    if (previewFondo) {
      once('pro-fondo-carga', previewFondo).forEach((foto) => {
        foto.addEventListener('load', encoge);
      });
    }
    // Y lo mismo con la tipografía del bordado: medir "MÓNICA" en la fuente de
    // reserva da un ancho que no es el que se va a ver.
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(encoge);
    }
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
   * Lupa del lightbox: acerca la foto y la recorre siguiendo al cursor.
   *
   * El acercamiento no es un número fijo: sale de dividir los píxeles que tiene
   * la foto entre los que ocupa en pantalla, porque ni pronens_lightbox ni
   * pronens_zoom amplían y más de la mitad del catálogo no llega a 1000px de
   * ancho. Donde no hay píxeles que enseñar no se ofrece la lupa, que es
   * preferible a emborronar la foto.
   *
   * La escala va en una clase y el punto de origen en una propiedad en línea:
   * así la transición del CSS suaviza el acercar y el alejar sin arrastrar el
   * recorrido, que tiene que ir pegado al cursor.
   *
   * @param {Element} dialogo - El <dialog> del lightbox.
   * @param {HTMLImageElement} img - La foto grande.
   * @param {Function} foto - Devuelve los datos de la foto que se está viendo.
   *
   * @return {?object} API de la lupa, o null si el diálogo no la trae.
   */
  function iniciaLupa(dialogo, img, foto) {
    const lienzo = dialogo.querySelector('[data-pro-zoom-lienzo]');
    if (!lienzo) {
      return null;
    }
    const aviso = dialogo.querySelector('[data-pro-zoom-hint]');
    // Por debajo de este acercamiento la lupa no aporta nada, y por encima del
    // tope se ven los píxeles del original por muchos que tenga.
    const MINIMO = 1.25;
    const TOPE = 3;
    let factor = 1;
    let puede = false;
    let activa = false;
    let movido = false;
    let partida = null;
    // Solo cambia el texto del aviso: lo que decide el comportamiento es el
    // pointerType de cada evento, no esta consulta, porque hay portátiles con
    // ratón y pantalla táctil a la vez.
    let dedo = window.matchMedia('(hover: none)').matches;

    /** Mide la foto y decide si hay píxeles de sobra para acercarla. */
    function calibra() {
      // offsetWidth y no getBoundingClientRect(): el rect ya viene multiplicado
      // por la escala cuando la foto está acercada, y recalibrar en ese momento
      // (al llegar la versión de detalle) daba factor 1 y apagaba la lupa.
      const ancho = img.offsetWidth;
      // El dato del servidor es el de la versión que se va a acabar sirviendo,
      // que puede ser mayor que la cargada; el de la foto cargada es el que
      // seguro que existe. Manda el mayor de los dos.
      const pixeles = Math.max(foto().ancho || 0, img.naturalWidth || 0);
      factor = ancho > 0 && pixeles > 0
        ? Math.min(pixeles / ancho, TOPE)
        : 1;
      puede = factor >= MINIMO;
      lienzo.classList.toggle('pro-zoom__lienzo--puede', puede);
      lienzo.style.setProperty('--pro-lupa', factor.toFixed(2));
      if (aviso) {
        aviso.hidden = !puede;
        aviso.textContent = dedo
          ? Drupal.t('Tap the photo to zoom')
          : Drupal.t('Hover over the photo to zoom');
      }
    }

    /** El punto de la foto que se queda quieto es el que está bajo el cursor. */
    function apunta(e) {
      const caja = lienzo.getBoundingClientRect();
      if (!caja.width || !caja.height) {
        return;
      }
      const x = Math.min(Math.max((e.clientX - caja.left) / caja.width, 0), 1);
      const y = Math.min(Math.max((e.clientY - caja.top) / caja.height, 0), 1);
      img.style.transformOrigin = `${(x * 100).toFixed(2)}% ${(y * 100).toFixed(2)}%`;
    }

    /**
     * Pide la versión de más resolución, si la foto la tiene.
     *
     * Se pide al acercar y no al abrir el lightbox: son unos cientos de KB que
     * solo hacen falta cuando alguien mira de cerca. El tamaño en pantalla no
     * cambia (los dos estilos escalan sin recortar, misma proporción), así que
     * el cambio no mueve nada.
     */
    function pideDetalle() {
      const datos = foto();
      if (!datos.lupa || datos.pedida) {
        return;
      }
      datos.pedida = true;
      const previa = new Image();
      previa.addEventListener('load', () => {
        // Puede haberse pasado a otra foto mientras cargaba.
        if (foto() === datos) {
          img.src = datos.lupa;
        }
      });
      previa.src = datos.lupa;
    }

    function enciende(e) {
      if (!puede) {
        return;
      }
      apunta(e);
      activa = true;
      lienzo.classList.add('pro-zoom__lienzo--activa');
      pideDetalle();
    }

    function apaga() {
      activa = false;
      lienzo.classList.remove('pro-zoom__lienzo--activa');
    }

    // Con ratón basta pasar por encima; el dedo necesita un toque, y otro para
    // volver a la foto entera.
    lienzo.addEventListener('pointermove', (e) => {
      if (e.pointerType !== 'mouse') {
        // Un arrastre no es un toque, y hay que descartarlo también con la foto
        // entera: si no, el gesto de pasar de foto acabaría además en un
        // pointerup que la acerca. El umbral es el de un dedo que no quiso
        // moverse.
        if (partida && Math.hypot(e.clientX - partida.x, e.clientY - partida.y) > 10) {
          movido = true;
        }
        if (activa) {
          apunta(e);
        }
        return;
      }
      if (activa) {
        apunta(e);
      }
      else {
        enciende(e);
      }
    });
    lienzo.addEventListener('pointerleave', (e) => {
      if (e.pointerType === 'mouse') {
        apaga();
      }
    });
    lienzo.addEventListener('pointerdown', (e) => {
      if (e.pointerType === 'mouse') {
        return;
      }
      movido = false;
      partida = { x: e.clientX, y: e.clientY };
      // El aviso se escribe pensando en el ratón hasta que llega un dedo.
      if (!dedo) {
        dedo = true;
        calibra();
      }
    });
    lienzo.addEventListener('pointerup', (e) => {
      const arrastre = movido;
      partida = null;
      if (e.pointerType === 'mouse' || arrastre) {
        return;
      }
      if (activa) {
        apaga();
      }
      else {
        enciende(e);
      }
    });
    // La foto nueva puede tener otra proporción, así que otro tamaño en
    // pantalla y otro acercamiento posible.
    img.addEventListener('load', calibra);
    window.addEventListener('resize', () => {
      if (dialogo.open) {
        apaga();
        calibra();
      }
    });

    return {
      calibra,
      apaga,
      activa: () => activa,
    };
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
      // Píxeles reales de la mejor versión disponible y, si existe, la URL de
      // esa versión: con ellos la lupa sabe cuánto puede acercar.
      ancho: parseInt(enlace.dataset.proAncho, 10) || 0,
      lupa: enlace.dataset.proLupa || '',
    }));
    let actual = 0;
    let origen = null;
    const lupa = iniciaLupa(dialogo, img, () => fotos[actual]);

    function muestra(indice) {
      actual = (indice + fotos.length) % fotos.length;
      if (lupa) {
        lupa.apaga();
      }
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
      // El lienzo no mide nada hasta que el diálogo está abierto.
      if (lupa) {
        lupa.calibra();
      }
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
      if (lupa) {
        lupa.apaga();
      }
      if (origen) {
        origen.focus();
      }
    });

    // Arrastrar de lado en táctil. Con la foto acercada el dedo la recorre, así
    // que ahí no se pasa de foto.
    let inicioX = null;
    dialogo.addEventListener('touchstart', (e) => {
      inicioX = lupa && lupa.activa() ? null : e.changedTouches[0].clientX;
    }, { passive: true });
    dialogo.addEventListener('touchend', (e) => {
      if (inicioX === null || fotos.length < 2 || (lupa && lupa.activa())) {
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
