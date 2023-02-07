<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Ordermanagement;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Payment\Converter\Refund;
use Throwable;
use WC_Order;

/**
 * Contains code for handling order status change to "Refunded"
 */
class Refunded extends Status
{
    /**
     * Performs full refund of Resurs payment.
     *
     * @throws ConfigException
     */
    public static function refund(int $orderId, string $old): void
    {
        try {
            $order = self::getWooCommerceOrder(orderId: $orderId);
        } catch (Throwable) {
            return;
        }

        $resursBankId = $order->get_meta(key: 'resursbank_payment_id');

        if (empty($resursBankId)) {
            return;
        }

        try {
            $resursPayment = self::getResursPayment(
                paymentId: $resursBankId,
                order: $order,
                oldStatus: $old
            );
        } catch (Throwable) {
            MessageBag::addError(
                msg: 'Unable to load Resurs payment information for refund. Reverting to previous order status.'
            );
            return;
        }

        if (!$resursPayment->canRefund()) {
            MessageBag::addError(
                msg: 'Resurs order can not be refunded. Reverting to previous order status.'
            );
            $order->update_status(new_status: $old);
            return;
        }

        self::performFullRefund(
            resursBankId: $resursBankId,
            order: $order,
            oldStatus: $old
        );
    }

    public static function partialRefund(int $orderId, int $refundId): void
    {
        $refundOrder = wc_get_order($refundId);
        $order = wc_get_order($orderId);
        $resursBankId = $order->get_meta(key: 'resursbank_payment_id');

        // Does not work as refundOrder is of the wrong type, need to handle conversion in another way
        // Have changed the allowed types. For Monday: Test and move on (hopefully)

        $items = Refund::getOrderLines(order: $refundOrder);

        if (empty($items)) {
            Repository::refund(paymentId: $resursBankId);
        } else {
            Repository::refund(paymentId: $resursBankId, orderLines: $items);
        }
    }

    /**
     * Performs the actual refund operation.
     *
     * @throws ConfigException
     */
    private static function performFullRefund(string $resursBankId, WC_Order $order, string $oldStatus): void
    {
        try {
            Repository::refund(paymentId: $resursBankId);
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            MessageBag::addError(
                msg: 'Unable to perform refund: ' . $error->getMessage() . '. Reverting to previous order status'
            );
            $order->update_status(new_status: $oldStatus);
        }
    }
}
