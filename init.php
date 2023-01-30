<?php
/**
 * Plugin Name: Resurs Bank Payments for WooCommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway.
 * WC Tested up to: 6.9.1
 * Requires PHP: 8.1
 * Version: 1.0.0
 * Author:
 * Plugin URI:
 * Author URI:
 * Text Domain: resurs-bank-payments-for-woocommerce
 * Domain Path: /language
 *
 * @noinspection PhpCSValidationInspection
 * @noinspection PhpDefineCanBeReplacedWithConstInspection
 */

declare(strict_types=1);

use ResursBank\Service\WooCommerce;
use Resursbank\Woocommerce\Modules\Api\Connection;
use Resursbank\Woocommerce\Modules\Cache\Cache;
use Resursbank\Woocommerce\Settings\Settings;

define(
    constant_name: 'RESURSBANK_MODULE_DIR_NAME',
    value: substr(__DIR__, strrpos(__DIR__, '/') + 1)
);
if (!defined(constant_name: 'ABSPATH')) {
    exit;
}
require_once(__DIR__ . '/autoload.php');

// Using same path identifier as the rest of the plugin-verse.
define(constant_name: 'RESURSBANK_GATEWAY_PATH', value: plugin_dir_path(__FILE__));
define(constant_name: 'RESURSBANK_MODULE_PREFIX', value: 'resursbank');

// Do not touch this just yet. Converting filters to something else than snake_cases has to be done
// in one sweep - if necessary.
define(constant_name: 'RESURSBANK_SNAKE_CASE_FILTERS', value: true);

// Early initiation. If this request catches an exception, it is mainly caused by unset credentials.
try {
    Connection::setup();
} catch (Throwable) {
    return;
}

// Translation domain is used for all phrases that is not relying on ecom2.
load_plugin_textdomain(
    domain: 'resurs-bank-payments-for-woocommerce',
    plugin_rel_path: dirname(path: plugin_basename(file: __FILE__)) . '/language/'
);

// Make sure there is an instance of WooCommerce among active plugins.
if (!WooCommerce::getActiveState()) {
    return;
}

if (is_admin()) {
    add_action(
        hook_name: 'woocommerce_loaded',
        callback: static fn() => Settings::setup()
    );
}

// This is the part where we usually initialized the plugin by a "plugins loaded"-hook,
// or checking that we're in "WordPress mode" with if (function_exists('add_action')) {}.
add_action(hook_name: 'plugins_loaded', callback: 'ResursBank\Service\WordPress::initializeWooCommerce');
