<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\PartPayment\PartPayment;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Settings\Filter\InvalidateCacheButton;
use Resursbank\Woocommerce\Settings\Filter\PartPaymentPeriod;
use Resursbank\Woocommerce\Settings\Settings;

/**
 * Module initialization class for functionality used by wp-admin.
 */
class Admin
{
    /**
     * Init various modules.
     */
    public static function init(): void
    {
        Gateway::initAdmin();
        OrderManagement::init();
        InvalidateCacheButton::init();
        PartPayment::initAdmin();
        PartPaymentPeriod::init();
        Settings::init();
        PaymentInformation::init();

        add_action(
            hook_name: 'updated_option',
            callback: 'Resursbank\Woocommerce\Settings\PartPayment::validateLimit',
            priority: 10,
            accepted_args: 3
        );
    }
}
