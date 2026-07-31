/* **************************************************************************************************
*                                  NIF - CIF - NIE Validation
*****************************************************************************************************
validate_nif_cif_nie. Returns the type of document and checks its validity.
* Usage:
*     validate_nif_cif_nie( str );
* 
*     > validate_nif_cif_nie( '12345678Z' );
*     // { type: 'dni', valid: true }
*     
*     > validate_nif_cif_nie( 'B83375575' );
*     // { type: 'cif', valid: false }
*****************************************************************************************************/
validate_nif_cif_nie = (function () {
    'use strict';

    var DNI_REGEX = /^(\d{8})([A-Z])$/;
    var CIF_REGEX = /^([ABCDEFGHJKLMNPQRSUVW])(\d{7})([0-9A-J])$/;
    var NIE_REGEX = /^[XYZ]\d{7,8}[A-Z]$/;

    var validate_nif_cif_nie = function (str) {
        // Ensure upcase and remove whitespace
        str = str.toUpperCase().replace(/\s/, '');

        var valid = false;
        var type = spainIdType(str);

        switch (type) {
            case 'dni':
                valid = validDNI(str);
                break;
            case 'nie':
                valid = validNIE(str);
                break;
            case 'cif':
                valid = validCIF(str);
                break;
        }

        return {
            type: type,
            valid: valid,
        };
    };

    var spainIdType = function (str) {
        if (str.match(DNI_REGEX)) {
            return 'dni';
        }
        if (str.match(CIF_REGEX)) {
            return 'cif';
        }
        if (str.match(NIE_REGEX)) {
            return 'nie';
        }
    };

    var validDNI = function (dni) {
        var dni_letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
        var letter = dni_letters.charAt(parseInt(dni, 10) % 23);

        return letter == dni.charAt(8);
    };

    var validNIE = function (nie) {
        // Change the initial letter for the corresponding number and validate as DNI
        var nie_prefix = nie.charAt(0);

        switch (nie_prefix) {
            case 'X':
                nie_prefix = 0;
                break;
            case 'Y':
                nie_prefix = 1;
                break;
            case 'Z':
                nie_prefix = 2;
                break;
        }

        return validDNI(nie_prefix + nie.substr(1));
    };

    var validCIF = function (cif) {
        var match = cif.match(CIF_REGEX);
        var letter = match[1],
            number = match[2],
            control = match[3];

        var even_sum = 0;
        var odd_sum = 0;
        var n;

        for (var i = 0; i < number.length; i++) {
            n = parseInt(number[i], 10);

            // Odd positions (Even index equals to odd position. i=0 equals first position)
            if (i % 2 === 0) {
                // Odd positions are multiplied first.
                n *= 2;

                // If the multiplication is bigger than 10 we need to adjust
                odd_sum += n < 10 ? n : n - 9;

                // Even positions
                // Just sum them
            } else {
                even_sum += n;
            }
        }

        var control_digit = 10 - (even_sum + odd_sum).toString().substr(-1);
        var control_letter = 'JABCDEFGHI'.substr(control_digit, 1);

        // Control must be a digit
        if (letter.match(/[ABEH]/)) {
            return control == control_digit;

            // Control must be a letter
        } else if (letter.match(/[KPQS]/)) {
            return control == control_letter;

            // Can be either
        } else {
            return control == control_digit || control == control_letter;
        }
    };

    return validate_nif_cif_nie;
})();

/* **************************************************************************************************
 *                                  Account number and IBAN validation
 *****************************************************************************************************/
function validate_acc_iban(value) {
    // IBAN Validation
    if (value.length == 24) {
        // Remove spaces and to upper case
        var iban = value.replace(/ /g, '').toUpperCase(),
            ibancheckdigits = '',
            leadingZeroes = true,
            cRest = '',
            cOperator = '',
            countrycode,
            ibancheck,
            charAt,
            cChar,
            bbanpattern,
            bbancountrypatterns,
            ibanregexp,
            i,
            p;

        // Check for IBAN code length.
        // It contains:
        // country code ISO 3166-1 - two letters,
        // two check digits,
        // Basic Bank Account Number (BBAN) - up to 30 chars
        var minimalIBANlength = 5;

        if (iban.length < minimalIBANlength) {
            return false;
        }

        // Check the country code and find the country specific format
        countrycode = iban.substring(0, 2);
        bbancountrypatterns = {
            AL: '\\d{8}[\\dA-Z]{16}',
            AD: '\\d{8}[\\dA-Z]{12}',
            AT: '\\d{16}',
            AZ: '[\\dA-Z]{4}\\d{20}',
            BE: '\\d{12}',
            BH: '[A-Z]{4}[\\dA-Z]{14}',
            BA: '\\d{16}',
            BR: '\\d{23}[A-Z][\\dA-Z]',
            BG: '[A-Z]{4}\\d{6}[\\dA-Z]{8}',
            CR: '\\d{17}',
            HR: '\\d{17}',
            CY: '\\d{8}[\\dA-Z]{16}',
            CZ: '\\d{20}',
            DK: '\\d{14}',
            DO: '[A-Z]{4}\\d{20}',
            EE: '\\d{16}',
            FO: '\\d{14}',
            FI: '\\d{14}',
            FR: '\\d{10}[\\dA-Z]{11}\\d{2}',
            GE: '[\\dA-Z]{2}\\d{16}',
            DE: '\\d{18}',
            GI: '[A-Z]{4}[\\dA-Z]{15}',
            GR: '\\d{7}[\\dA-Z]{16}',
            GL: '\\d{14}',
            GT: '[\\dA-Z]{4}[\\dA-Z]{20}',
            HU: '\\d{24}',
            IS: '\\d{22}',
            IE: '[\\dA-Z]{4}\\d{14}',
            IL: '\\d{19}',
            IT: '[A-Z]\\d{10}[\\dA-Z]{12}',
            KZ: '\\d{3}[\\dA-Z]{13}',
            KW: '[A-Z]{4}[\\dA-Z]{22}',
            LV: '[A-Z]{4}[\\dA-Z]{13}',
            LB: '\\d{4}[\\dA-Z]{20}',
            LI: '\\d{5}[\\dA-Z]{12}',
            LT: '\\d{16}',
            LU: '\\d{3}[\\dA-Z]{13}',
            MK: '\\d{3}[\\dA-Z]{10}\\d{2}',
            MT: '[A-Z]{4}\\d{5}[\\dA-Z]{18}',
            MR: '\\d{23}',
            MU: '[A-Z]{4}\\d{19}[A-Z]{3}',
            MC: '\\d{10}[\\dA-Z]{11}\\d{2}',
            MD: '[\\dA-Z]{2}\\d{18}',
            ME: '\\d{18}',
            NL: '[A-Z]{4}\\d{10}',
            NO: '\\d{11}',
            PK: '[\\dA-Z]{4}\\d{16}',
            PS: '[\\dA-Z]{4}\\d{21}',
            PL: '\\d{24}',
            PT: '\\d{21}',
            RO: '[A-Z]{4}[\\dA-Z]{16}',
            SM: '[A-Z]\\d{10}[\\dA-Z]{12}',
            SA: '\\d{2}[\\dA-Z]{18}',
            RS: '\\d{18}',
            SK: '\\d{20}',
            SI: '\\d{15}',
            ES: '\\d{20}',
            SE: '\\d{20}',
            CH: '\\d{5}[\\dA-Z]{12}',
            TN: '\\d{20}',
            TR: '\\d{5}[\\dA-Z]{17}',
            AE: '\\d{3}\\d{16}',
            GB: '[A-Z]{4}\\d{14}',
            VG: '[\\dA-Z]{4}\\d{16}',
        };

        bbanpattern = bbancountrypatterns[countrycode];

        // As new countries will start using IBAN in the
        // future, we only check if the countrycode is known.
        // This prevents false negatives, while almost all
        // false positives introduced by this, will be caught
        // by the checksum validation below anyway.
        // Strict checking should return FALSE for unknown
        // countries.

        if (typeof bbanpattern !== 'undefined') {
            ibanregexp = new RegExp('^[A-Z]{2}\\d{2}' + bbanpattern + '$', '');
            if (!ibanregexp.test(iban)) {
                return false; // Invalid country specific format
            }
        }

        // Now check the checksum, first convert to digits
        ibancheck = iban.substring(4, iban.length) + iban.substring(0, 4);
        for (i = 0; i < ibancheck.length; i++) {
            charAt = ibancheck.charAt(i);
            if (charAt !== '0') {
                leadingZeroes = false;
            }
            if (!leadingZeroes) {
                ibancheckdigits += '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'.indexOf(charAt);
            }
        }

        // Calculate the result of: ibancheckdigits % 97
        for (p = 0; p < ibancheckdigits.length; p++) {
            cChar = ibancheckdigits.charAt(p);
            cOperator = '' + cRest + '' + cChar;
            cRest = cOperator % 97;
        }
        return cRest === 1;
        // Fin IBAN Validation
        // Account number number validation
    } else if (value.length == 20) {
        var banco = value.substring(0, 4);
        var sucursal = value.substring(4, 8);
        var dc = value.substring(8, 10);
        var cuenta = value.substring(10, 20);

        if (!/^[0-9]{20}$/.test(banco + sucursal + dc + cuenta)) {
            return false;
        } else {
            valores = new Array(1, 2, 4, 8, 5, 10, 9, 7, 3, 6);
            control = 0;
            for (i = 0; i <= 9; i++) control += parseInt(cuenta.charAt(i)) * valores[i];
            control = 11 - (control % 11);

            if (control == 11) control = 0;
            else if (control == 10) control = 1;

            if (control != parseInt(dc.charAt(1))) {
                return false;
            }
            control = 0;
            var zbs = '00' + banco + sucursal;

            for (i = 0; i <= 9; i++) control += parseInt(zbs.charAt(i)) * valores[i];
            control = 11 - (control % 11);
            if (control == 11) control = 0;
            else if (control == 10) control = 1;
            if (control != parseInt(dc.charAt(0))) {
                return false;
            }
            return true;
        }
    } // Account number number validation
}

function validateCorreosUser() {
    jQuery.validator.addMethod(
        'accountTypeMethod',
        function (value) {
            const lowercasePattern = /^w\d{7,8}$/;
            const uppercasePattern = /^W[\w\d]{12}$/;

            return lowercasePattern.test(value) || uppercasePattern.test(value);
        }
        // "Error message: passed in index 'messages' in customer-data.js".
    );
}
