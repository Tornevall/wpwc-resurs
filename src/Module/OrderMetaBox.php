<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\MissingKeyException;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Sanitize;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;
use WP_Post;

class OrderMetaBox
{
    /**
     * @throws ConfigException
     */
    public static function output_order(WP_Post $post): void
    {
        if (!$post instanceof WP_Post && $post->post_type !== 'shop_order') {
            return;
        }

        $order = new WC_Order(order: $post->ID);

        if (!Metadata::isValidResursPayment(order: $order)) {
            return;
        }

        $orderData = Metadata::getOrderInfo(order: $order);

        try {
            $paymentInformation = new PaymentInformation(
                paymentId: self::getResursBankPaymentId(orderData: $orderData)
            );
            echo Sanitize::sanitizeHtml(
                html: $paymentInformation->widget->content
            );
        } catch (Throwable $error) {
            echo '<b>' . Translator::translate(
                phraseId: 'failed-to-fetch-order-data-from-the-server'
            ) . ' ' .
                 Translator::translate(
                     phraseId: 'server-response'
                 ) . ':</b> ' . $error->getMessage();
            Config::getLogger()->error(message: $error);
        }
    }

    /**
     * Fetches payment id from orderData array generated by Data::getOrderInfo
     *
     * @param array $orderData
     * @throws MissingKeyException
     */
    private static function getResursBankPaymentId(array $orderData): string
    {
        if (!isset($orderData['meta']['resursbank_payment_id'][0])) {
            throw new MissingKeyException(
                message: 'Missing resursbank_payment_id in metadata'
            );
        }

        return $orderData['meta']['resursbank_payment_id'][0];
    }
}
