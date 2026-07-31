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

use Smarty\Template;

/**
 * Prefiltro Smarty del plugin (versión WordPress-only).
 *
 * Sustituye a la clase global \Prefilter de vendor/ecommerce_common_lib/Prefilter.php.
 * Transforma los literales {l s='...' mod='correosoficial'} de los TPLs en variables
 * Smarty {$...} y persiste las cadenas en los ficheros langs/*Lang.php.
 */
class CorreosOficialPrefilter
{
    /**
     * Convierte {l s='settings' mod='correosoficial'} en {$settings} y registra las
     * traducciones detectadas en el fichero de idioma de la página actual.
     */
    public static function preFilterConstants($tpl_output, Template $template)
    {
        global $co_page;
        global $post;

        $input = $tpl_output;

        $tpl_output = preg_replace_callback(
            "/{l s=\'(.*?)\'.*?mod=\'correosoficial\'}/s",
            function ($m) {
                $str = str_replace('/', '_', $m[1]);
                $str = str_replace("'", '', $str);
                $str = str_replace(array("\r", "\n"), '', $str);
                $id = '{' . '$' . preg_replace('/[^a-zA-Z\r\n]/', '_', $str) . '}';
                return $id;
            },
            $tpl_output
        );

        preg_match_all("/{l s='(.*?)'}/s", $input, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            if (!strstr($matches[0][$i], '{l s=')) {
                unset($matches[0][$i]);
            }
        }

        $assign = '';
        foreach ($matches[1] as $match) {
            $match = str_replace(" mod='correosoficial", '', $match);

            $str = str_replace('/', '_', $match);
            $str = str_replace("'", '', $str);
            $str = str_replace(array("\r", "\n"), '', $str);
            $id = preg_replace('/[^a-zA-Z\r\n]/', '_', $str);

            $assign .= "\n" . '$this->smarty->assign("' . $id . '", __("' . $str . '","correosoficial"));' . "\n";
        }

        $page = isset($_GET['page']) ? $_GET['page'] : null;

        $langsDir = dirname(__DIR__) . '/langs/';

        if ($page == 'home') {
            $fileLang = $langsDir . 'homeLang.php';
        } elseif ($page == 'settings' || $page == 'correosoficial') {
            $fileLang = $langsDir . 'settingsLang.php';
        } elseif ($page == 'utilities') {
            $fileLang = $langsDir . 'utilitysLang.php';
        } elseif ($co_page == 'checkout') {
            $fileLang = $langsDir . 'checkoutLang.php';
        } elseif (preg_match('/admin.*\.tpl/', $template->getCompiled()->filepath ?? '')) {
            $fileLang = $langsDir . 'orderLang.php';
        } elseif ($co_page == 'my_account') {
            $fileLang = $langsDir . 'orderDetailLang.php';
        } elseif ($page == 'notifications') {
            $fileLang = $langsDir . 'notificationsLang.php';
        } else {
            return $tpl_output;
        }

        $fp = fopen($fileLang, 'a+');

        if (!strstr(file_get_contents($fileLang), '<?php')) {
            fwrite($fp, "<?php\r\n");
        }

        fwrite($fp, $assign);
        fclose($fp);

        self::cleanAssignFile($fileLang);

        return $tpl_output;
    }

    /**
     * Elimina las líneas duplicadas en los ficheros langs/*Lang.php.
     */
    private static function cleanAssignFile($file)
    {
        $lines = file($file);
        $lines = array_unique($lines);
        file_put_contents($file, implode($lines));
    }
}
