// ---- MENSAJES DE RESPUESTA ------------------------------------------------------------

/** Se ha sacado de customer data,
 * ya que esta funcion es igual en ps y wc
 */
function showResponseMessage(errorCode) {
    switch (errorCode) {
        case '200':
            var title = title200;
            var description = description200;
            showModalInfoWindow(title + ' ' + description);
            // location.reload();
            break;
        case '404':
            var title = title404;
            var description = description404;
            showModalErrorWindow(title + ' ' + description);
            break;
        case '401':
            var title = title401;
            var description = description401;
            showModalErrorWindow(title + ' ' + description);
            break;
        case '999':
            var title = title999;
            var description = description999;
            showModalErrorWindow(title + ' ' + description);
            break;
        default:
            alert(errorCode);
    }
}
