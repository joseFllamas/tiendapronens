<?php
/**
 * This program is free software: you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see https://www.gnu.org/licenses/.
 */

namespace CorreosOficial\Classes;

use Exception;
use LogicException;

/**
 * Sustituye a la clase global \SendMail de
 * vendor/ecommerce_common_lib/SendEmail.inc.php.
 *
 * Versión WordPress-only: envía emails con la función nativa mail().
 */
class CorreosOficialSendMail
{
    protected $email;
    protected $subject;
    protected $body;
    protected $headers;

    /**
     * @param string|array $email   Destinatario único o array de destinatarios.
     * @param string       $subject Asunto del email.
     * @param string       $body    Cuerpo del mensaje.
     * @param string       $from    Email del remitente.
     * @param array|null   $cc_array Array opcional con direcciones CC.
     * @param string       $type    'html' o 'multipart'.
     */
    public function __construct($email, $subject, $body, $from, $cc_array = null, $type = 'html')
    {
        if (is_array($email)) {
            $this->email = '';
            foreach ($email as $e) {
                $this->email .= "<$e>,";
            }
            $this->email[strlen($this->email) - 1] = ' ';
        } else {
            $this->email = $email;
        }
        $this->subject = $subject;
        $this->body = $body;

        $headers  = "MIME-Version: 1.0" . "\r\n";

        if ($type == 'html') {
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        } elseif ($type == 'multipart') {
            $headers .= "Content-Type: multipart/mixed; boundary=\"00000000000066858605e5b8dcc4\"; charset=UTF-8" . "\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit" . "\r\n";
        } else {
            throw new LogicException('Error 22010 : Error técnico: No se indicado el tipo de cabecera');
        }

        $headers .= "From: <$from>" . "\r\n";
        $headers .= "Reply-To: <no-reply@correos.com>" . "\r\n";

        if (is_array($cc_array)) {
            $cc = '';
            foreach ($cc_array as $c) {
                $cc .= "<$c>,";
            }
            $cc[strlen($cc) - 1] = ' ';
            $headers .= "Cc: $cc" . "\r\n";
        }
        $this->headers = $headers;

        CorreosOficialUtils::varDump('EMAIL: ', $this->email);
        CorreosOficialUtils::varDump('HEADERS: ', $headers);
        CorreosOficialUtils::varDump('SEPARATOR: ', '~~~~~~~~~~~~~~~~~~~~~~~~');
    }

    /**
     * Envía el email. Devuelve "Enviado" en caso de éxito o el mensaje de error.
     */
    public function sendEmail()
    {
        $result = false;
        try {
            if ($this->email) {
                @$result = mail($this->email, $this->subject, $this->body, $this->headers);

                $error = error_get_last();

                if (empty($result)) {
                    return $error['message'];
                } else {
                    return 'Enviado';
                }
            }
        } catch (Exception $e) {
            $result = "No se pudo enviar el email. Por favor, revise su php_error.log";
            $result .= "Error en línea: " . __LINE__ . " en Fichero: " . __FILE__;
            return $result;
        }
    }
}
