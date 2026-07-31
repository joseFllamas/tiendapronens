<?php

/**
 * Plantilla de email de devolución para el cliente.
 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting">
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Solicitud de contacto</title>
        <style type="text/css">
            @font-face {
                font-family: 'cartero';
                src: url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Light.woff2) format('woff2'),
                    url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Light.woff) format('woff'),
                    url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Light.ttf) format('truetype');
                font-weight: 100;
                font-style: normal;
            }

            @font-face {
                font-family: 'cartero-regular';
                src: url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Regular.woff2) format('woff2'),
                    url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Regular.woff) format('woff'),
                    url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Regular.ttf) format('truetype');
                font-weight: normal;
                font-style: normal;
            }

            @font-face {
                font-family: 'cartero-bold';
                src: url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Bold.woff2) format('woff2'),
                    url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Bold.woff) format('woff'),
                    url(https://www.market.correos.es/static/shop/front/fonts/cartero/cartero-Bold.ttf) format('truetype');
                font-weight: bold;
                font-style: normal;
            }

            body {
                margin: 0 auto;
                padding: 0;
                min-width: 280px;
                max-width: 600px;
                font-family: 'cartero-regular', Arial, sans-serif;
                background-color: #ffffff;
                color: #333333;
                line-height: 1.5;
            }

            a {
                color: #152E6D;
                text-decoration: none;
                font-weight: bold;
            }

            .title {
                font-family: 'cartero-bold', Arial, sans-serif;
                font-size: 30px;
                padding: 40px 0 20px 0;
                line-height: 150%;
            }

            .content-text {
                font-size: 20px;
                padding-bottom: 20px;
                line-height: 150%;
            }

            ul {
                padding-left: 20px;
            }

            li {
                font-size: 20px;
                line-height: 150%;
                margin-bottom: 10px;
            }
        </style>
    </head>

<body>
    <!-- Header -->
    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
        <tr>
            <td style="display:none !important; font-size:1px;color: transparent; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
                Recibida solicitud de contacto comercial desde el Módulo Ecommerce de Prestashop.
            </td>
        </tr>
    </table>

    <!-- CONTENT -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0.5" style="max-width:600px; margin:auto;">
        <tr>
            <td style="padding:20px;">
                <h1 class="title"><?= esc_html($shop_name) ?></h1>

                <p class="content-text">
                    <?= nl2br(esc_html($recipient_return_hello)) ?>,<br><br>

                    <?php if ($company === 'Correos') : ?>
                        <?= esc_html($recipient_return_text1) ?> (<b><?= esc_html($shipping_number) ?></b>) <?= esc_html($recipient_return_text2) ?>
                        <b><?= esc_html($recipient_return_doc_cn23) ?></b> <?= esc_html($recipient_return_text3) ?>.
                    <?php elseif ($company === 'CEX') : ?>
                        <?= esc_html($recipient_return_text1_cex) ?> <b><?= esc_html($recipient_return_pickup_date) ?></b> <?= esc_html($recipient_return_text2_cex) ?>
                        <?= esc_html($recipient_return_pickup_time) ?>H <?= esc_html($recipient_return_text3_cex) ?> <b><?= esc_html($recipient_return_shop_name) ?></b>,
                        <?= esc_html($recipient_return_text4_cex) ?>.<br><br>

                        <?= esc_html($recipient_return_text5_cex) ?> <?= esc_html($recipient_return_text6_cex) ?>
                        <a href="https://s.correosexpress.com/"><?= esc_html($recipient_return_text7_cex) ?></a><br><br>

                        <b><?= esc_html($return_code_cex) ?></b><br><br>

                        <u><?= esc_html($recipient_return_recommendations) ?></u><br><br>

                        <?= esc_html($recipient_return_recommendation_info) ?>:<br><br>

                <ul>
                    <li><?= esc_html($recipient_return_recommendation1) ?>.</li>
                    <li><?= esc_html($recipient_return_recommendation2) ?>.</li>
                    <li><?= esc_html($recipient_return_recommendation3) ?>.</li>
                </ul>
            <?php endif; ?>

            <br><?= esc_html($recipient_return_thanks) ?>
            </p>

            <p class="content-text">
                <?= esc_html($recipient_return_bye) ?>,<br>
                <b><?= esc_html($recipient_return_footer) ?></b>
            </p>

            <p class="content-text">
                <a href="mailto:<?= esc_attr($sender_email) ?>"><b><?= esc_html($sender_email) ?></b></a>
            </p>

            <hr style="border:none; height:3px; background-color:#333;">
            </td>
        </tr>
    </table>
</body>

</html>