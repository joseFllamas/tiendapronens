/**
 * @file
 * Guarda de dónde vino el visitante, si el partner lo dice en la URL.
 *
 * Todo esto vive en el navegador a propósito:
 *
 * - La cookie NO se puede escribir desde PHP. Una página normal de la tienda se
 *   sirve desde la caché de página, así que una cabecera `Set-Cookie` puesta al
 *   renderizarla se guardaría con ella y se la llevaría el siguiente visitante,
 *   que no ha venido del partner.
 * - No se escribe nada sin consentimiento. Estas funciones no se llaman solas:
 *   las llama el servicio «educoland» de Klaro (su «callback_code»), que corre
 *   al cargar la página con el consentimiento ya resuelto y cada vez que
 *   cambia. Sin consentimiento se llama a borrar(), y la atribución del pedido
 *   se queda en el cupón.
 *
 * Lo que se guarda son los cuatro datos del contrato de educoland: partner,
 * colocación, creatividad y momento del clic. Ni identificadores ni nada
 * personal; ese es el motivo de que la atribución vaya por UTM.
 */
((Drupal, drupalSettings) => {
  'use strict';

  const MAX = 64;

  /**
   * Deja un valor de UTM en [a-z0-9_-] y 64 caracteres.
   *
   * Mismo saneado que en servidor (Atribucion::sanear): lo que no encaja se
   * borra en vez de sustituirse, para no inventar una colocación que no existe.
   */
  const sanear = (valor) =>
    (valor || '').toString().toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, MAX);

  const ajustes = () => drupalSettings.pronensReferral || {};

  Drupal.pronensReferral = {

    /**
     * Escribe la cookie si esta página es una llegada desde el partner.
     *
     * Last-click: cada llegada nueva pisa la anterior y reinicia los 90 días.
     * En una página sin los parámetros no se toca nada, así que navegar por la
     * tienda no borra ni acorta lo que ya había.
     */
    capturar() {
      const config = ajustes();
      if (!config.activo || !config.fuente) {
        return;
      }
      let parametros;
      try {
        parametros = new URLSearchParams(window.location.search);
      }
      catch (error) {
        return;
      }
      if (sanear(parametros.get('utm_source')) !== sanear(config.fuente)) {
        return;
      }
      const valor = JSON.stringify({
        s: sanear(config.fuente),
        c: sanear(parametros.get('utm_campaign')),
        ct: sanear(parametros.get('utm_content')) || null,
        t: Math.floor(Date.now() / 1000),
      });
      const dias = parseInt(config.dias, 10) || 90;
      document.cookie = [
        `${config.cookie}=${encodeURIComponent(valor)}`,
        `Max-Age=${dias * 86400}`,
        'Path=/',
        'SameSite=Lax',
        window.location.protocol === 'https:' ? 'Secure' : '',
      ].filter(Boolean).join('; ');
    },

    /**
     * Borra la cookie: se ha retirado el consentimiento.
     *
     * Klaro borra además por su cuenta las cookies declaradas en el servicio,
     * pero eso solo ocurre cuando el visitante cambia de opinión estando en la
     * página; esto cubre también el primer «rechazar».
     */
    borrar() {
      const config = ajustes();
      if (!config.cookie) {
        return;
      }
      document.cookie = `${config.cookie}=; Max-Age=0; Path=/; SameSite=Lax`;
    },

  };

})(Drupal, drupalSettings);
