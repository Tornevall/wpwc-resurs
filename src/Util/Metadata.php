<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use WC_Order;

use function is_array;

/**
 * Order metadata handler.
 *
 * @psalm-suppress MissingDependency
 */
class Metadata
{
    public const KEY_PAYMENT_ID = RESURSBANK_MODULE_PREFIX . '_payment_id';

    /**
     * Store UUID of Resurs Bank payment on order.
     */
    public static function setPaymentId(
        WC_Order $order,
        string $id
    ): void {
        self::setOrderMeta(
            order: $order,
            metaDataKey: self::KEY_PAYMENT_ID,
            metaDataValue: $id
        );
    }

    /**
     * Get UUID of Resurs Bank payment attached to order.
     */
    public static function getPaymentId(WC_Order $order): string
    {
        return self::getOrderMeta(
            order: $order,
            metaDataKey: self::KEY_PAYMENT_ID
        );
    }

    /**
     * Set metadata to an order.
     * Metadata is stored uniquely (meaning the returned data from getOrderMeta can be returned as $single=true).
     */
    public static function setOrderMeta(
        WC_Order $order,
        string $metaDataKey,
        string $metaDataValue
    ): bool {
        $existingMeta = get_post_meta(
            post_id: $order->get_id(),
            key: $metaDataKey,
            single: true
        );

        if ($existingMeta) {
            return (bool)update_post_meta(
                post_id: $order->get_id(),
                meta_key: $metaDataKey,
                meta_value: $metaDataValue
            );
        }

        return (bool)add_post_meta(
            post_id: $order->get_id(),
            meta_key: $metaDataKey,
            meta_value: $metaDataValue,
            unique: true
        );
    }

    /**
     * Return metadata from an order, as a single variable.
     * Normally metadata is returned as array, but currently we usually only save values once.
     */
    public static function getOrderMeta(
        WC_Order $order,
        string $metaDataKey
    ): string {
        return (string)get_post_meta(
            post_id: $order->get_id(),
            key: $metaDataKey,
            single: true
        );
    }

    /**
     * Check if current order is a valid Resurs Payment.
     */
    public static function isValidResursPayment(WC_Order $order): bool
    {
        return self::getPaymentId(order: $order) !== '';
    }

    /**
     * Fetch order information and metadata.
     *
     * @return array
     */
    public static function getOrderInfo(WC_Order $order): array
    {
        $meta = get_post_custom(post_id: $order->get_id());
        return [
            'order' => $order,
            'meta' => is_array(value: $meta) ? $meta : [],
        ];
    }

    /**
     * Retrieve order associated with payment id.
     */
    public static function getOrderByPaymentId(string $paymentId): ?WC_Order
    {
        $result = null;

        $orders = wc_get_orders(args: [
            'meta_key' => self::KEY_PAYMENT_ID,
            'meta_value' => $paymentId,
            'meta_compare' => '=',
        ]);

        if (
            is_array(value: $orders) &&
            count($orders) === 1 &&
            $orders[0] instanceof WC_Order
        ) {
            $result = $orders[0];
        }

        return $result;
    }
}
