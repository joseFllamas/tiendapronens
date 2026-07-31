/* **********************************************************************************************************
 *                                         ReferenceCode.js
 *   Librería de uso común para backend y front end de gestión de CityPaqs y Oficinas
 ********************************************************************************************************* */

var cod_homepaq;
var cod_office;
var data;

/* **********************************************************************************************************
 *                                         CityPaqs
 ********************************************************************************************************* */

/**
 * Inserta Citypaq
 */
function insertCityPaq() {
    var data = {
        ajax: true,
        token: static_token,
        action: 'insertCityPaq',
        data: dataCityPaq,
        citypaq: cod_homepaq,
    };
    insertReferenceCode(data);
}

/* **********************************************************************************************************
 *                                         Oficinas
 ********************************************************************************************************* */

/**
 * Inserta Citypaq
 */
function insertOffice() {
    var data = {
        ajax: true,
        token: static_token,
        action: 'insertOffice',
        data: dataOffice,
        office: cod_office,
    };
    insertReferenceCode(data);
}

/**
 *
 * @param {*} data información recibida del controlador
 */
function insertReferenceCode(data) {
    /**
     * ***********************************************************************************
     *                                    Llamada AJAX
     * ***********************************************************************************
     */
    $.ajax({
        url: ReferenceCodeUrl + rand + ajaxtrue,
        type: 'POST',
        data: data,
        cache: false,
        processData: true,
        /**
         * ***********************************************************************************
         *                                    Llamada AJAX SUCCESS
         * ***********************************************************************************
         */
        success: function (data) {},
        /**
         * ***********************************************************************************
         *                                    Llamada AJAX ERROR
         * ***********************************************************************************
         */
        error: function (e) {
            alert('ERROR 18034: ' + ajaxError);
        },
    });
}
