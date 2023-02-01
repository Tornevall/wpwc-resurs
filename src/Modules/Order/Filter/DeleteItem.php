<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\Order\Order as OrderModule;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Throwable;

/**
 * Event executed when order item is deleted.
 */
class DeleteItem
{
//    private static WC_Order|null $order;

    /**
     * Register action filter which executed when order item gets deleted.
     *
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws FilesystemException
     * @throws TranslationException
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'woocommerce_before_delete_order_item',
            callback: static function (mixed $itemId): void {
                try {
                    self::exec(itemId: (int)$itemId);
                } catch (Throwable $e) {
                    Config::getLogger()->error(message: $e);
                    wp_send_json_error(
                        data: ['error' => Translator::translate(
                            phraseId: 'cancel-article-row-fail'
                        ) . ' ' . $e->getMessage()]
                    );
                }
            }
        );
    }

    /**
     * Cancel item on Resurs Bank payment.
     *
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws Exception
     */
    private static function exec(int $itemId): void
    {
        $order = OrderModule::getOrder(itemId: $itemId);

        if ($order === null) {
            return;
        }

        Repository::cancel(
            paymentId: OrderModule::getPaymentId(order: $order),
            orderLines: Order::getOrderLines(
                order: $order,
                filter: [$itemId],
                includeShipping: false
            )
        );
    }
}
