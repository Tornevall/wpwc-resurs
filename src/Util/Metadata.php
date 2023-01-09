<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Woocommerce\Settings;
use WC_Order;

/**
 * Order metadata handler.
 */
class Metadata
{
    /**
     * Reported fix: Left operand cannot be mixed (see https://psalm.dev/059)
     * @return string
     * @consider Centralizing this differently.
     */
    private static function getPrefix(): string
    {
        return (string)Settings::PREFIX;
    }

    /**
     * Set meta data to WC Order
     * @param WC_Order $order
     * @param string $metaDataKey
     * @param string $metaDataValue
     * @return bool
     */
    public static function setOrderMeta(
        WC_Order $order,
        string $metaDataKey,
        string $metaDataValue
    ): bool {
        return (bool)add_post_meta(
            post_id: $order->get_id(),
            meta_key: self::getPrefix() . '_' . $metaDataKey,
            meta_value: $metaDataValue,
            unique: true
        );
    }

    /**
     * @param WC_Order $order
     * @param string $metaDataKey
     * @return string
     */
    public static function getOrderMeta(WC_Order $order, string $metaDataKey): string
    {
        return (string)get_post_meta(
            post_id: $order->get_id(),
            key: self::getPrefix() . '_' . $metaDataKey,
            single: true
        );
    }

    /**
     * Check if current order is a valid Resurs Payment.
     * @param WC_Order $order
     * @return bool
     */
    public static function isValidResursPayment(WC_Order $order): bool
    {
        return Metadata::getOrderMeta(order: $order, metaDataKey: 'payment_id') !== '';
    }
}
