<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */

/** @noinspection CompactCanBeUsedInspection */

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Lib\Widget\Widget;
use Resursbank\Ecom\Module\Customer\Enum\CustomerType;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use stdClass;
use TorneLIB\IO\Data\Arrays;
use WC_Checkout;
use WC_Settings_API;
use function count;
use function in_array;
use function is_array;

/**
 * Class FormFields Self contained settings.
 *
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class FormFields extends WC_Settings_API
{
    /**
     * @var bool
     * @since 0.0.1.0
     */
    private static $allowMocking;

    /**
     * @var bool
     * @since 0.0.1.0
     */
    private static $showDeveloper;

    /**
     * @param string $section
     * @param null $id
     * @return array
     * @throws Exception
     * @noinspection ParameterDefaultValueIsNotNullInspection
     * @since 0.0.1.0
     */
    public static function getFormFields($section = 'basic', $id = null): array
    {
        if (empty($section)) {
            $section = 'basic';
        }

        // Basic settings. Returned to ResursDefault configuration.
        // Note: Some types below is not WooCommerce standard and is catched by woocommerce "type actions".
        /** @noinspection HtmlUnknownTarget */
        $formFields = [
            'basic' => [
                'title' => __(
                    'Basic Resurs Bank API Settings',
                    'resurs-bank-payments-for-woocommerce'
                ),
                'enabled' => [
                    'id' => 'enabled',
                    'title' => __(
                        'Enable plugin checkout functions',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Resurs Bank', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'This enables core functions of Resurs Bank, like the payment gateway, etc. ' .
                        'When disabled, after shop functions (i.e. debiting, annulling, etc) will still work.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'yes',
                ],
                'checkout_type' => [
                    'id' => 'checkout_type',
                    'title' => __('Checkout Type', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'rco' => __(
                            'Resurs Checkout (embedded checkout by iframe)',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'simplified' => __(
                            'Integrated Checkout (simplified shopFlow)',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 1,
                        'onchange' => 'resursUpdateFlowDescription(this)',
                    ],
                    'default' => 'rco',
                    'desc' => __(
                        'Chosen checkout type.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'internal_tax' => [
                    'id' => 'internal_tax',
                    'title' => __('Internal Tax Class', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'select',
                    'options' => WordPress::applyFilters('getTaxClasses', []),
                    'default' => 'standard',
                    'desc' => __(
                        'Tax class to use when nothing else is specified (for example, on payment fees).',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'environment' => [
                    'id' => 'environment',
                    'title' => __('Environment', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'test' => __(
                            'Test',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'live' => __(
                            'Production',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 1,
                    ],
                    'default' => 'test',
                    'desc' => __(
                        'Defines if you are are live or just in test/staging. Default: test.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'jwt_client_id' => [
                    'id' => 'jwt_client_id',
                    'title' => __('JWT Client ID', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'text',
                    'desc' => __(
                        'Web services username, received from Resurs Bank.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'jwt_client_id_production' => [
                    'id' => 'jwt_client_id_production',
                    'title' => __('JWT Client ID (Production).',
                        'resurs-bank-payments-for-woocommerce'),
                    'type' => 'text',
                    'desc' => __(
                        'JWT Client ID (username), received from Resurs Bank.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'jwt_client_secret' => [
                    'id' => 'jwt_client_secret',
                    'title' => __('JWT Client Secret', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'password',
                    'default' => '',
                    'desc' => __(
                        'API password/secret, received from Resurs Bank. If your credentials are saved within the same ' .
                        'environment as the chosen one and you decide to validate them before saving, payment ' .
                        'methods and necessary data will update the same time. Otherwise, only credentials will be ' .
                        'saved.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'custom_attributes' => [
                        'onload' => 'resursAppendCredentialCheck()',
                    ],
                ],
                'jwt_client_secret_production' => [
                    'id' => 'jwt_client_secret_production',
                    'title' => __(
                        'Resurs Bank API Password (Production).',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'password',
                    'default' => '',
                    'desc' => __(
                        'API password, received from Resurs Bank. To validate and store the credentials ' .
                        'make sure you use the validation button. If you choose to not validate your credentials ' .
                        'here, and instead just save, you have to update the methods manually in the payment ' .
                        'methods section.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'custom_attributes' => [
                        'onload' => 'resursAppendCredentialCheck()',
                    ],
                ],
                'mapi_store_id' => [],
                'country' => [
                    'id' => 'country',
                    'title' => __('Chosen merchant country', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'text',
                    'default' => get_option('woocommerce_default_country'),
                    'css' => 'width: 100px',
                    'custom_attributes' => [
                        'readonly' => 'readonly',
                    ],
                    'desc' => __(
                        'Defines which country this plugin operates from. Credentials given by Resurs Bank are ' .
                        'limited to a specific country. Default: Store address country.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'extended_test_mode' => [
                    'id' => 'extended_test_mode',
                    'title' => __(
                        'Enable extended test mode',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __(
                        'Extended help when test is enabled',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'Activates helping functions that makes life easier when testing.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'yes',
                ],
            ],
            'payment_methods' => [
                'title' => __(
                    'Payment methods and order handling',
                    'resurs-bank-payments-for-woocommerce'
                ),
                'payment_methods_settings_section_start' => [
                    'type' => 'title',
                    'title' => __(
                        'Payment methods, products and checkout',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'This section covers information for your current payment methods that is linked with your ' .
                        'API settings. You can not edit titles or descriptions at this page so if you need to ' .
                        'change such data you have to contact Resurs Bank support.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'payment_method_icons' => [
                    'type' => 'select',
                    'id' => 'payment_method_icons',
                    'title' => __(
                        'Checkout method logotypes',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'none',
                    'options' => [
                        'none' => __(
                            'Prefer to not display logotypes',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'woocommerce_icon' => __(
                            'Display logotypes as WooCommerce default',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'only_specifics' => __(
                            'Display icons only if they are of customized',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'specifics_and_resurs' => __(
                            'Display Resurs branded and customized icons',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 1,
                    ],
                    'desc' => __(
                        'If there are branded payment methods in your checkout, that you prefer to display, choose ' .
                        'your best option here. Observe that this option is entirely dependent on your theme and ' .
                        'no layout are rendered through this as we use the default icon setup in WooCommerce to ' .
                        'show the icons.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'streamline_payment_fields' => [
                    'type' => 'checkbox',
                    'id' => 'streamline_payment_fields',
                    'title' => __(
                        'Applicant fields are always visible',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'default' => 'no',
                    'desc_tip' => __(
                        'The applicant fields that Resurs Bank is using to handle payments is normally, inherited ' .
                        'from WooCommerce standard billing fields in the checkout. You can however enable them ' .
                        'here, if you want your customers to see them anyway.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'get_address_form' => [
                    'type' => 'checkbox',
                    'id' => 'get_address_form',
                    'title' => __(
                        'Use address information lookup service',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'default' => 'yes',
                    'desc_tip' => __(
                        'This enables address lookup forms (getAddress) in checkout, when available. ' .
                        'Countries currently supported is SE (government id) and NO (phone number).',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'get_address_form_always' => [
                    'type' => 'checkbox',
                    'id' => 'get_address_form_always',
                    'title' => __(
                        'Always show government id field from address service',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'default' => 'no',
                    'desc_tip' => __(
                        'With this setting enabled, the getAddress form will always be shown, regardless of country ' .
                        'compatibility.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'rco_method_titling' => [
                    'type' => 'select',
                    'id' => 'rco_method_titling',
                    'title' => __(
                        'How to display payment method titles',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'options' => [
                        'default' => __('Default title.', 'resurs-bank-payments-for-woocommerce'),
                        'id' => __(
                            'Use the ID of the chosen payment method.',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'description' => __(
                            'Use the description of the chosen payment method.',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'default' => 'default',
                    'custom_attributes' => [
                        'size' => 1,
                    ],
                    'desc' => __(
                        'When payment methods are mentioned in order data and Resurs Checkout payments, you can ' .
                        'choose how it should be displayed. Selecting anything but the default value will display ' .
                        'the ID or description of a chosen payment method instead of "Resurs Bank AB".',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'rco_iframe_position' => [
                    'type' => 'select',
                    'id' => 'rco_iframe_position',
                    'title' => 'Resurs Checkout Position',
                    'desc' => __(
                        'Defines where in the checkout the iframe should be placed. Preferred position is after ' .
                        'the checkout form and also default. This setting is also configurable with filters.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'options' => [
                        'after_checkout_form' => __(
                            'After checkout form (Default).',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'checkout_before_order_review' => __(
                            'Before order review.',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'default' => 'after_checkout_form',
                    'custom_attributes' => [
                        'size' => 1,
                    ],
                ],
                'part_payment_template' => [
                    'type' => 'select',
                    'id' => 'part_payment_template',
                    'title' => __('Part payment template', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => __(
                        'When you enable the part payment options for products, you can choose your own ' .
                        'template to display. Templates are built on WordPress pages. If you want to show a custom ' .
                        'page, you may choose which page you want to show here. Shortcodes that can be use: ' .
                        '[currency], [monthlyPrice], [monthlyDuration], [methodId], [methodDescription]. If you use ' .
                        'a custom text [monthlyPrice] will be delivered without the currency, so you have to add ' .
                        'that part yourself.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'options' => WordPress::applyFilters('getPartPaymentPage', []),
                ],
                'part_payment_sums' => [
                    'type' => 'checkbox',
                    'id' => 'part_payment_sums',
                    'title' => __(
                        'Allow part payment information in checkout',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'default' => 'no',
                    'desc_tip' => __(
                        'If part payment information is enabled with annuity factors, you can choose to activate ' .
                        'this template in the cart review and the order review.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'part_payment_threshold' => [
                    'id' => 'part_payment_threshold',
                    'title' => __('Part payment threshold', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'text',
                    'desc' => __(
                        'Minimum installment amount per month for when part payment information should be displayed. ' .
                        'Default is 150. When set to 150, this value will be automatically adjusted to ' .
                        '15 if country is set to FI, since EUR is used there. If any other sum is set here, that ' .
                        'value will always be used instead of the defaults. ',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '150',
                ],
                'payment_methods_extended' => [
                    'id' => 'payment_methods_extended',
                    'type' => 'checkbox',
                    'title' => __(
                        'Extended Payment Methods View',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Show more information about the payment methods in the payment method listview.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'payment_methods_settings_end' => [
                    'id' => 'payment_methods_settings_end',
                    'type' => 'sectionend',
                ],
                'order_status_section' => [
                    'id' => 'order_status_section',
                    'type' => 'title',
                    'title' => 'Order Status Mapping',
                ],
                'order_instant_finalization_status' => [
                    'id' => 'order_instant_finalization_status',
                    'title' => 'Automatically debited order status',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        'default' => __('Use default (Completed)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'processing' => __('Processing', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'credited' => __('Credited (refunded)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'completed' => __('Completed', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'pending' => __('Pending (on-hold)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'annulled' => __('Annulled (cancelled)', 'resurs-bank-payment-gateway-for-woocommerce'),
                    ],
                ],
                /*'order_instant_finalization_methods' => [
                    'id' => 'order_instant_finalization_methods',
                    'title' => 'Payment methods defined as automatically debited',
                    'type' => 'select',
                    'options' => WordPress::applyFilters('getAvailableAutoDebitMethods', []),
                    'default' => 'default',
                    'custom_attributes' => [
                        'size' => count(WordPress::applyFilters('getAvailableAutoDebitMethods', [])),
                        'multiple' => 'multiple',
                    ],
                ],*/
                'order_status_mapping_section_end' => [
                    'id' => 'order_status_mapping_section_end',
                    'type' => 'sectionend',
                ],
                'payment_methods_list' => [
                    'type' => 'methodlist',
                    'id' => 'payment_methods_list_section',
                ],
                'payment_methods_button' => [
                    'type' => 'button',
                    'action' => 'button',
                    'title' => __(
                        'Update payment methods and annuity factors',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'custom_attributes' => [
                        'onclick' => 'getResursPaymentMethods()',
                    ],
                ],
                'payment_methods_list_end' => [
                    'type' => 'sectionend',
                ],
            ],
            'fraud_control' => [
                'title' => __('Fraud control', 'resurs-bank-payments-for-woocommerce'),
                'fraud_finalization_section' => [
                    'type' => 'title',
                    'title' => __(
                        'How to handle fraud and debiting',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => sprintf(
                        __(
                            'This section configures how fraud and finalizations should be handled in the ' .
                            'integrated (simplified flow) and hosted checkout (not Resurs Checkout!). ' .
                            'It is strongly recommended to keep the settings disabled and let callbacks handle ' .
                            'the rest, unless you are a travel company that rely on non fraudulent behaviours. ' .
                            'The settings below makes sure that orders that is frozen when the order has been ' .
                            'handled are automatically annulled. If the orders in other hands are healthy and booked ' .
                            'you can also set the process to automatically debit/finalize the order with the setup ' .
                            'below. For more information, see <a href="%s" target="_blank">%s</a>.',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'https://test.resurs.com/docs/display/ecom/paymentData',
                        'https://test.resurs.com/docs/display/ecom/paymentData'
                    ),
                ],
                'waitForFraudControl' => [
                    'id' => 'waitForFraudControl',
                    'type' => 'checkbox',
                    'title' => __('Wait for fraud control', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'The checkout process waits until the fraud control is finished at Resurs Bank ' .
                        'and the order is handled synchronously. If this setting is disabled, Resurs Bank must be ' .
                        'able to reach your system with callbacks to be able to deliver the result.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                    'custom_attributes' => [
                        'onchange' => 'getResursFraudFlags(this)',
                    ],
                ],
                'annulIfFrozen' => [
                    'id' => 'annulIfFrozen',
                    'type' => 'checkbox',
                    'title' => __('Annul frozen orders', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'If Resurs Bank freezes a payment due to fraud, the order will automatically be annulled. ' .
                        'By default, the best practice is to handle all annulments asynchronously with callbacks. ' .
                        'Callback event name is ANNUL.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                    'custom_attributes' => [
                        'onchange' => 'getResursFraudFlags(this)',
                    ],
                ],
                'finalizeIfBooked' => [
                    'id' => 'finalizeIfBooked',
                    'type' => 'checkbox',
                    'title' => __(
                        'Automatically debit if booked',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Orders are automatically debited (finalized) if the fraud control passes. ' .
                        'By default, the best practice is to handle all finalizations asynchronously with callbacks. ' .
                        'Callback event name is FINALIZATION.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                    'custom_attributes' => [
                        'onchange' => 'getResursFraudFlags(this)',
                    ],
                ],
                'fraud_finalization_section_end' => [
                    'type' => 'sectionend',
                ],
            ],
            'advanced' => [
                'title' => __('Advanced Merchant', 'resurs-bank-payments-for-woocommerce'),
                'complex_api_section' => [
                    'type' => 'title',
                    'title' => __('Advanced API', 'resurs-bank-payments-for-woocommerce'),
                ],
                'rco_paymentid_age' => [
                    'id' => 'rco_paymentid_age',
                    'title' => __(
                        'Resurs Checkout paymentId maximum age.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'text',
                    'desc' => __(
                        'Defined in seconds, how long a preferred payment id can live before it is renewed in a ' .
                        'current session. This setting is necessary as we use the id to track cart updates ' .
                        'which very much prevents malicious cart manipulation. It also allows customers to reload ' .
                        'the checkout page and still use the same payment id. When a payment is successful, the ' .
                        'preferred payment id will also be reset.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '3600',
                ],
                'discard_coupon_vat' => [
                    'id' => 'discard_coupon_vat',
                    'title' => __(
                        'Do not add VAT to discounts',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'When order rows are added to Resurs Bank API, the VAT is applied on the coupon amount ' .
                        'excluding tax. To handle the discount without vat and instead use the full including tax ' .
                        'amount as a discount, you can enable this feature.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'prevent_rounding_panic' => [
                    'id' => 'prevent_rounding_panic',
                    'title' => __('Prevent rounding errors', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'WooCommerce are able to show prices rounded with 0 decimals. It is however widely known ' .
                        'and confirmed that payment gateways may have problems with tax calculation, when the ' .
                        'decimals are fewer than two. With this setting enabled, the plugin will try to override the ' .
                        'decimal setup as long as it is set to lower than 2. If you use this feature, you also ' .
                        'confirm that you are willingly using a, for the platform, unsupported feature. If you\'ve ' .
                        'not already done it, it is recommended to instead increase the number of decimals to 2 or ' .
                        'higher.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'deprecated_interference' => [
                    'id' => 'deprecated_interference',
                    'title' => __(
                        'Can interact with old-plugin orders',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Enabling this feature allows the plugin to enter orders created with the old plugin.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ],
                'store_api_history' => [
                    'id' => 'store_api_history',
                    'title' => __(
                        'Store API history in orders',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Enabled', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'If this setting is active, the first time you view a specific order API data will be stored ' .
                        'for it. This means that it will be possible to go back to prior orders and view them even ' .
                        'after you change the user credentials.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ],
                'api_wsdl' => [
                    'id' => 'api_wsdl',
                    'title' => __('WSDL requests are cached', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'select',
                    'options' => [
                        'default' => __(
                            'Default: Only for production/live environment',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'both' => __(
                            'Both for production/live and test/staging',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        'none' => __(
                            'Do not cache WSDL',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'desc' => __(
                        'This setting defines how SOAP requests are being made to Resurs Bank. It is ' .
                        'usually recommended to keep requests cached (meaning wsdl and data required for a SOAP ' .
                        'call to work, are stored locally on your server). During development it sometimes ' .
                        'better to run tests uncached, however, it is not recommended in a production since ' .
                        'this directly affects network performance (since each SOAP call will include an extra ' .
                        'network request to the API first).'
                    ),
                    'default' => 'default',
                ],
                'complex_api_section_end' => [
                    'type' => 'sectionend',
                ],
                'complex_developer_section' => [
                    'type' => 'title',
                    'title' => __('Developer Section', 'resurs-bank-payments-for-woocommerce'),
                ],
                'logging' => [
                    'title' => __('Logging', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'title',
                    'desc' => __(
                        'Default for this plugin is to log a fair amount of data for you. However, there is also ' .
                        'also much debug data for developers available, that you normally not need. In this section ' .
                        'you can choose the extras you want to see in your logs.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'log_dir' => [
                    'id' => 'log_dir',
                    'type' => 'text',
                    'title' => __(
                        'Log path',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'To enable logging, you actively have to fill in the path for where you want to keep them. ' .
                        'To avoid data leaks we suggest that this path not be accessible from the internet.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'must_obfuscate_logged_personal_data' => [
                    'id' => 'must_obfuscate_logged_personal_data',
                    'type' => 'checkbox',
                    'title' => __(
                        'Protect personal data in logs',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Yes', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'To protect customers, customer data will be obfuscated in logs during debugging. If ' .
                        'you really must change this to see debugged customers, this setting is the one to ' .
                        'disable. However, as this is an alternative to be GDPR compliant in debug logs you ' .
                        'should probably consider avoiding this.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'yes',
                ],
                'can_log_order_events' => [
                    'id' => 'can_log_order_events',
                    'type' => 'checkbox',
                    'title' => __(
                        'Log merchant details (CAN_LOG_ORDER_EVENTS)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Yes', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Detailed order events are data that normally passes without any sound. ' .
                        'Things like initial order creations and clicks could show up in your logs.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'can_log_info' => [
                    'id' => 'can_log_info',
                    'type' => 'checkbox',
                    'title' => __(
                        'Log informative (CAN_LOG_INFO)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Yes', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Log events that flows under severity INFO. Logs affected is for example mocking events.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'can_log_order_developer' => [
                    'id' => 'can_log_order_developer',
                    'type' => 'checkbox',
                    'title' => __(
                        'Log developer order events (CAN_LOG_ORDER_DEVELOPER)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Yes', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Works like details for merchants, but this adds debugging information that may only be ' .
                        'relevant for developers.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'can_log_junk' => [
                    'id' => 'can_log_junk',
                    'type' => 'checkbox',
                    'title' => __(
                        'Deep details (CAN_LOG_JUNK)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Yes', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Things that only developers would have interest in. Logs may be noisy with this ' .
                        'option enabled.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'can_log_backend' => [
                    'id' => 'can_log_backend',
                    'type' => 'checkbox',
                    'title' => __(
                        'Log backend requests (CAN_LOG_BACKEND)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __('Yes', 'resurs-bank-payments-for-woocommerce'),
                    'desc_tip' => __(
                        'Log backend events triggered by the the javascript method that handles all AJAX ' .
                        'requests (ajaxify-js).',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'yes',
                ],
                'complex_developer_section_end' => [
                    'type' => 'sectionend',
                ],
                'tweaks' => [
                    'type' => 'title',
                    'title' => __('Special Tweaks Section', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => __(
                        'If you have no idea what this is for, do not touch it.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ],
                'show_developer' => [
                    'title' => __(
                        'Activate Advanced Tweaking Mode (Developer)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'Activate Advanced Tweaking Mode (you might need an extra reload after save)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'The developer section is normally nothing you will need, unless you are a very advanced ' .
                        'administrator/developer/merchant that likes to configure a little bit over the limits. ' .
                        'If you know what you are doing, feel free to activate this section.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'the_reset_button' => [
                    'type' => 'button',
                    'action' => 'button',
                    'title' => __(
                        'Reset all settings',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'Resets the entire database settings storage of the plugin to its absolute defaults, ' .
                        'except encryption keys.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'custom_attributes' => [
                        'onclick' => 'rbwcResetThisPlugin()',
                    ],
                ],
                'tweaks_end' => [
                    'type' => 'sectionend',
                ],
            ],
            'information' => [
                'title' => __('Support', 'resurs-bank-payments-for-woocommerce'),
            ],
        ];

        // Filter based on merchant requested feature.
        if (Data::getCheckoutType() !== ResursDefault::TYPE_SIMPLIFIED &&
            WordPress::applyFilters('canHideFraudControl', true)
        ) {
            unset($formFields['fraud_control']);
        }

        if (Data::hasCredentials()) {
            try {
                $defaultStore = ['0'=>''];
                $renderedStores = $defaultStore + (new ResursBankAPI())->getRenderedStores();

                // This section is now reserved in the configuration set.
                $formFields['basic']['mapi_store_id'] = [
                    'type' => 'select',
                    'id' => 'mapi_store_id',
                    'title' => __('Store', 'rs-bank-merchant-api-addon-for-woocommerce'),
                    'options' => $renderedStores,
                    'default' => '',
                ];
            } catch (Exception $e) {
                $formFields['basic']['mapi_store_id'] = [
                    'type' => 'title',
                    'desc' => sprintf('<b>Can not fetch stores: %s</b>', $e->getMessage()),
                ];
            }
        }

        $hasOldSettings = get_option('woocommerce_resurs-bank_settings');
        if (is_array($hasOldSettings) && isset($hasOldSettings['enabled'])) {
            $array = new Arrays();
            $formFields['advanced'] = $array->moveArrayAfter(
                $formFields['advanced'],
                [
                    'type' => 'button',
                    'action' => 'button',
                    'title' => __(
                        'Clean up prior version settings',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc' => __(
                        'Removes all traces of resurs-plugin v2.2 from the database.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'custom_attributes' => [
                        'onclick' => 'rbwcResetVersion22()',
                    ],
                ],
                'the_reset_button',
                'reset_former_plugin_settings'
            );
        }

        $formFields = WordPress::applyFilters('getCustomFormFields', $formFields, $section);

        if ($section === 'all') {
            $return = $formFields;
        } else {
            $return = isset($formFields[$section]) ? self::getTransformedIdArray($formFields[$section], $id) : [];
        }

        return (array)$return;
    }

    /**
     * Transform options into something that fits in a WC_Settings_Page-block.
     *
     * @param $array
     * @param $add
     * @return array
     * @since 0.0.1.0
     */
    public static function getTransformedIdArray($array, $add): array
    {
        $return = $array;

        if (!empty($add)) {
            foreach ($array as $itemKey => $item) {
                if (is_array($item)) {
                    if (isset($item['id'])) {
                        $item['id'] = sprintf('%s_%s', $add, $item['id']);
                    } else {
                        $item['id'] = sprintf('%s_%s', $add, $itemKey);
                    }
                    $return[$itemKey] = $item;
                } else {
                    unset($return[$itemKey]);
                }
            }
        }

        return $return;
    }

    /**
     * @param $formData
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldButton($formData)
    {
        (new self())->getFieldButtonApi($formData);
    }

    /**
     * @param $formData
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getFieldButtonApi($formData)
    {
        $action = isset($formData['action']) && !empty($formData['action']) ? $formData['action'] : 'button';

        $allowedFormData = [
            Data::getPrefix('admin_payment_methods_button'),
            Data::getPrefix('admin_callbacks_button'),
            Data::getPrefix('admin_trigger_callback_button'),
            Data::getPrefix('admin_the_reset_button'),
            Data::getPrefix('admin_reset_former_plugin_settings'),
        ];

        if (isset($formData['id']) && in_array($formData['id'], $allowedFormData, true)) {
            $formArray = $formData;
            $formArray['action'] = $action; // Our action
            $formArray['custom_attributes'] = $this->get_custom_attribute_html($formData);
            $formArray['columns'] = [
                'the_reset_button' => true,
                'reset_former_plugin_settings' => true
            ];
            $formArray['short_id'] = preg_replace(sprintf('/%s_admin_/', Data::getPrefix()), '', $formArray['id']);
            echo Data::getEscapedHtml(
                Data::getGenericClass()->getTemplate('adminpage_button', $formArray)
            );
        }
    }

    /**
     * Filter based addon.
     * Do not use getResursOption in this request as this may cause infinite loops.
     *
     * @param $currentArray
     * @param $section
     * @return array
     * @since 0.0.1.0
     */
    public static function getDeveloperTweaks($currentArray, $section): array
    {
        $return = $currentArray;

        $developerArray = [
            'developer' => [
                'dev_section' => [
                    'type' => 'title',
                    'title' => __('Developers Section', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => sprintf(
                        __(
                            'This section is for very advanced tweaking only. It is not enabled and visible by ' .
                            'default for security reasons. Proceed at your own risk!',
                            'resurs-bank-payments-for-woocommerce'
                        )
                    ),
                ],
                'title' => __('Developer Settings', 'resurs-bank-payments-for-woocommerce'),
                'plugin_section' => [
                    'type' => 'title',
                    'title' => 'Plugin Settings',
                ],
                'dev_section_end' => [
                    'type' => 'sectionend',
                ],
                'admin_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Administration Tweaking',
                ],
                'nonce_trust_admin_session' => [
                    'id' => 'nonce_trust_admin_session',
                    'title' => __(
                        'Trust is_admin before frontend nonces.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Yes, do trust them please.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'For some places in the admin panel, we use nonces as an extra security layer when it comes ' .
                        'to requests like updating callbacks, payment methods, etc. Sometimes nonces expires too ' .
                        'quickly and breaks requests in wp_admin. Enable this feature to start trusting is_admin() ' .
                        'during ajax request primarily and nonces secondarily. is_admin is normally a security layer ' .
                        'that prevents unknonwn requests to be executed.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'admin_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
                'customer_checkout_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Customer & Checkout Tweaking',
                ],
                'allow_mocking' => [
                    'id' => 'allow_mocking',
                    'title' => __('Allow mocked behaviours', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'This setting enables mocked behaviours and data on fly, during tests.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'customer_checkout_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
                'order_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Order Tweaking',
                ],
                'order_note_prefix' => [
                    'id' => 'order_note_prefix',
                    'title' => __(
                        'Prefix for order and status notes',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'text',
                    'desc' => __(
                        'When orders are updated with new statuses, or gets new notifications this is how we are ' .
                        'prefixing the notes. Default (empty) is "trbwc".',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'order_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
                'api_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Order Tweaking',
                ],
                'api_soap_url' => [
                    'id' => 'api_soap_url',
                    'title' => __('API SOAP Url', 'resurs-bank-payments-for-woocommerce'),
                    'type' => 'text',
                    'desc' => __(
                        'Use another URL for the SOAP-API. Currently only for test environment!',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => '',
                ],
                'api_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
            ],
        ];

        $mockingTweaks = self::getMockingTweaks();

        // Dev section should be the last one, in the wp-admin.
        if ((isset($section) && $section === 'all') || self::getShowDeveloper()) {
            $return = array_merge($return, $mockingTweaks, $developerArray);
        }

        return $return;
    }

    /**
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getMockingTweaks()
    {
        $return = [];
        if (!isset(self::$allowMocking)) {
            self::$allowMocking = Data::getResursOption('allow_mocking', null, false);
        }

        if (self::$allowMocking && Data::getResursOption('environment', null, false) === 'test') {
            $return['mocking'] = [
                'title' => __('Mocking & Testing', 'resurs-bank-payments-for-woocommerce'),
                'mocking_section' => [
                    'type' => 'title',
                    'title' => __('Mocking Section', 'resurs-bank-payments-for-woocommerce'),
                    'desc' => sprintf(
                        __(
                            'Section of Mocking & Tests. Are you not developing this plugin? Then you probably do ' .
                            'not need it either. The section is specifically placed hiddenly here, since the options ' .
                            'here are used to recreate events under very specific circumstances. For example, you ' .
                            'can mock errors from here, that you otherwise had to hardcode into the plugin. Options ' .
                            'here are normally enabled until the feature has been trigged once. After first ' .
                            'execution it will instantly become disabled automatically. The mocking section can only ' .
                            'be enabled when your environment is set to test and you explicitly allowed mocking on ' .
                            'your site.',
                            'resurs-bank-payments-for-woocommerce'
                        )
                    ),
                ],
                'mock_update_payment_reference_failure' => [
                    'id' => 'mock_update_payment_reference_failure',
                    'title' => __(
                        'Fail on updatePaymentReference',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'This setting enables a fictive error on front-to-back calls during order creations where ' .
                        'updatePaymentReference occurs.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_create_iframe_exception' => [
                    'id' => 'mock_create_iframe_exception',
                    'title' => __(
                        'Fail on iframe creation',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'This setting enables a fictive error in the checkout where the iframe fails to render. This ' .
                        'has happened during development, where the current payment id used by the plugin ' .
                        'collided with an already existing order id at Resurs Bank.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_update_callback_exception' => [
                    'id' => 'mock_update_callback_exception',
                    'title' => __(
                        'Fail on callback update in wp-admin',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'This setting enables a fictive callback problem.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_empty_price_info_html' => [
                    'id' => 'mock_empty_price_info_html',
                    'title' => __(
                        'Fail retrieval of priceinfo',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'Ensure that the priceinfo box still shows data when no data has ' .
                        'been retrieved from priceinfo.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_annuity_factor_config_exception' => [
                    'id' => 'mock_annuity_factor_config_exception',
                    'title' => __(
                        'Fail fetching annuityFactor values',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'Ensure that the priceinfo box still shows data when no data has been ' .
                        'retrieved from priceinfo.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_callback_update_exception' => [
                    'id' => 'mock_callback_update_exception',
                    'title' => __(
                        'Fail update callbacks from Resurs Bank',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'Ensure that the priceinfo box still shows data when no data has been ' .
                        'retrieved from priceinfo.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_refund_exception' => [
                    'id' => 'mock_refund_exception',
                    'title' => __(
                        'Emulate refunding error from Resurs Bank (annul/credit)',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'Emulating exceptions from SoapService in the annulPayment/creditPayment services.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mock_get_payment_method_namespace_exception' => [
                    'id' => 'mock_get_payment_method_namespace_exception',
                    'title' => __(
                        'Throw getPaymentMethodNamespace exception',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'desc_tip' => __(
                        'Throw an exception on getPaymentMethodNamespace checks (used when checking whether a ' .
                        'payment method is enabled or not) and is based on environment and username.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'default' => 'no',
                ],
                'mocking_section_end' => [
                    'type' => 'sectionend',
                ],
            ];
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getShowDeveloper(): bool
    {
        if (!isset(self::$showDeveloper)) {
            self::$showDeveloper = (bool)Data::getResursOption('show_developer', null, false);
        }
        return self::$showDeveloper;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldDecimals()
    {
        if (wc_get_price_decimals() < 2 && Data::getResursOption('prevent_rounding_panic')) {
            echo Data::getEscapedHtml(
                Data::getGenericClass()->getTemplate('adminpage_general_decimals.phtml', [
                    'pluginTitle' => Data::getPluginTitle(),
                ])
            );
        }
    }

    /**
     * @param WC_Checkout|null $wcCheckout
     * @param bool $returnAsHtml
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getGetAddressForm(null|WC_Checkout$wcCheckout, bool $returnAsHtml = false): mixed
    {
        $getAddressFormAlways = (bool)Data::getResursOption('get_address_form_always');
        $customerTypeByConditions = Data::getCustomerType();

        if (Data::isTest() && file_exists(Data::getGatewayPath() . '/testdata.json')) {
            try {
                $testDataFile = Data::getGatewayPath() . '/testdata.json';
                $liveTestData = json_decode(@file_get_contents($testDataFile), true, 512);
            } catch (Exception $e) {
                Data::writeLogException($e, __FUNCTION__);
            }
        }

        Data::getSafeStyle();

        $template = sprintf('%s/checkout_getaddress.phtml', Data::getGatewayPath('templates'));

        global $legacyVariables;
        $legacyVariables = [
            'customer_private' => __('Private person', 'resurs-bank-payments-for-woocommerce'),
            'customer_company' => __('Company', 'resurs-bank-payments-for-woocommerce'),
            'customer_type' => $customerTypeByConditions,
            'customer_button_text' => WordPress::applyFilters(
                'getAddressButtonText',
                __('Get address', 'resurs-bank-payments-for-woocommerce')
            ),
            'supported_country' => Data::isGetAddressSupported(),
            'get_address_form' => Data::canUseGetAddressForm(),
            'get_address_form_always' => $getAddressFormAlways,
            'liveTestData' => $liveTestData ?? [],
        ];

        $return = (new Widget())->render(file: $template);

        if ($returnAsHtml) {
            return $return;
        } else {
            echo Data::getEscapedHtml($return);
        }
        return $wcCheckout instanceof WC_Checkout ? $wcCheckout : '';
    }

    /**
     * Fetch payment methods list. formData is not necessary here since this is a very specific field.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldMethodList(): void
    {
        // Considering this place as a safe place to apply display in styles.
        Data::getSafeStyle();

        $exception = null;
        $annuityException = null;
        $paymentMethods = new stdClass();
        $theFactor = Data::getResursOption('currentAnnuityFactor');
        $theDuration = (int)Data::getResursOption('currentAnnuityDuration');
        $annuityFactors = [];

        try {
            $paymentMethods = ResursBankAPI::getPaymentMethods();
        } catch (Exception $e) {
            $exception = $e;
        }
        $silentGetPaymentMethodsException = WooCommerce::getSessionValue('silentGetPaymentMethodsException');

        try {
            $annuityFactors = self::getAnnuityDropDown(ResursBankAPI::getAnnuityFactors(), $theFactor, $theDuration);
        } catch (Exception $e) {
            $annuityException = $e;
        }
        $silentAnnuityException = WooCommerce::getSessionValue('silentAnnuityException');

        if (is_array($paymentMethods)) {
            $annuityEnabled = Data::getResursOption('currentAnnuityFactor');
            $paymentMethods = self::getInternalMethodDescriptions($paymentMethods);

            $paymentMethodTemplate = Data::getGenericClass()->getTemplate(
                'adminpage_paymentmethods.phtml',
                [
                    'paymentMethods' => $paymentMethods,
                    'annuityFactors' => $annuityFactors,
                    'exception' => $exception,
                    'annuityException' => $annuityException,
                    'annuityEnabled' => $annuityEnabled,
                    'silentGetPaymentMethodsException' => $silentGetPaymentMethodsException,
                    'silentAnnuityException' => $silentAnnuityException,
                    'environment' => Data::getResursOption('environment'),
                    'lastMethodUpdate' => self::getLastPaymentMethodUpdate(),
                    'canUseFee' => Data::getCheckoutType() !== 'rco' && Data::isPaymentFeeAllowed(),
                ]
            );
            echo Data::getEscapedHtml($paymentMethodTemplate);
        }
    }

    /**
     * @param $annuityFactors
     * @param $theFactor
     * @param $theDuration
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getAnnuityDropDown($annuityFactors, $theFactor, $theDuration): array
    {
        $return = [];

        foreach ($annuityFactors as $id => $factorArray) {
            if (count($factorArray)) {
                $return[$id] = self::getRenderedFactors($id, $factorArray, $theFactor, $theDuration);
            } else {
                $return[$id] = '';
            }
        }

        return $return;
    }

    /**
     * @param $id
     * @param $factorArray
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getRenderedFactors($id, $factorArray, $theFactor, $theDuration): string
    {
        $options = null;
        foreach ($factorArray as $item) {
            $selected = '';
            if (($id === $theFactor) && (int)$theDuration === (int)$item->duration && empty($selected)) {
                $selected = 'selected';
            }
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                $item->duration,
                $selected,
                $item->paymentPlanName
            );
        }

        $isFactorEnabled = Data::getResursOption('currentAnnuityFactor');
        $enabled = $isFactorEnabled === $id;

        return Data::getGenericClass()->getTemplate('adminpage_annuity_selector.phtml', [
            'id' => $id,
            'options' => $options,
            'enabled' => $enabled,
        ]);
    }

    /**
     * @param array $paymentMethods
     * @return array
     * @since 0.0.1.5
     */
    private static function getInternalMethodDescriptions(array $paymentMethods): array
    {
        if (is_array($paymentMethods)) {
            foreach ($paymentMethods as $methodIdx => $methodItem) {
                if (is_object($methodItem)) {
                    $description = Data::getResursOption(sprintf('method_custom_description_%s', $methodItem->id));
                    $internalFee = Data::getResursOption(sprintf('method_custom_fee_%s', $methodItem->id));
                    $methodItem->internalDescription = $description;
                    $methodItem->internalFee = $internalFee;
                    $paymentMethods[$methodIdx] = $methodItem;
                }
            }
        }
        return $paymentMethods;
    }

    /**
     * @return string
     * @since 0.0.1.4
     */
    private static function getLastPaymentMethodUpdate(): string
    {
        $lastMethodUpdateStored = (int)Data::getResursOption('lastMethodUpdate');
        return $lastMethodUpdateStored ? date('Y-m-d H:i', $lastMethodUpdateStored) : __(
            'Never.',
            'resurs-bank-payments-for-woocommerce'
        );
    }

    /**
     * @return string
     * @since 0.0.1.4
     */
    private static function getLastCallbackUpdate(): string
    {
        $lastMethodUpdateStored = (int)Data::getResursOption('lastCallbackUpdate');
        return $lastMethodUpdateStored ? date('Y-m-d H:i', $lastMethodUpdateStored) : __(
            'Never.',
            'resurs-bank-payments-for-woocommerce'
        );
    }

    /**
     * @return string
     * @since 0.0.1.4
     */
    private static function getLastCallbackTrigger(): string
    {
        $lastMethodUpdateStored = (int)Data::getResursOption('resurs_callback_test_response');
        return $lastMethodUpdateStored ? date('Y-m-d H:i:s', $lastMethodUpdateStored) : __(
            'Never.',
            'resurs-bank-payments-for-woocommerce'
        );
    }

    /**
     * @param null $key
     * @return array|mixed
     * @since 0.0.1.0
     */
    public static function getFieldString($key = null)
    {
        $fields = [
            'government_id' => __('Social security number', 'resurs-bank-payments-for-woocommerce'),
            'phone' => __('Phone number', 'resurs-bank-payments-for-woocommerce'),
            'mobile' => __('Mobile number', 'resurs-bank-payments-for-woocommerce'),
            'email' => __('E-mail address', 'resurs-bank-payments-for-woocommerce'),
            'government_id_contact' => __(
                'Applicant government id',
                'resurs-bank-payments-for-woocommerce'
            ),
            'contact_government_id' => __(
                'Contact government id',
                'resurs-bank-payments-for-woocommerce'
            ),
            'card_number' => __('Card number', 'resurs-bank-payments-for-woocommerce'),
            'applicant_government_id' => __(
                'Applicant Government ID',
                'resurs-bank-payments-for-woocommerce'
            ),
            'applicant_telephone_number' => __(
                'Applicant Telephone Number',
                'resurs-bank-payments-for-woocommerce'
            ),
            'applicant_mobile_number' => __(
                'Applicant Mobile Number',
                'resurs-bank-payments-for-woocommerce'
            ),
            'applicant_email_address' => __(
                'Applicant E-Mail Address',
                'resurs-bank-payments-for-woocommerce'
            ),
            'applicant_full_name' => __(
                'Applicant Full Name',
                'resurs-bank-payments-for-woocommerce'
            ),
        ];

        // If no key are sent here, it is probably a localization request.
        $return = $fields;

        if (!empty($key) && isset($fields[$key])) {
            $return = $fields[$key];
        }

        return $return;
    }

    /**
     * @param $key
     * @return bool
     * @since 0.0.1.0
     */
    public static function canDisplayField($key): bool
    {
        return in_array($key, [
            'government_id',
            'government_id_contact',
            'card_number',
        ]);
    }

    /**
     * @param string $key
     * @param CustomerType $customerType
     * @return array
     * @since 0.0.1.0
     */
    public static function getSpecificTypeFields(string $key, CustomerType $customerType): array
    {
        $return = [
            'NATURAL' => [
                'INVOICE' => [
                    'government_id',
                    'phone',
                    'mobile',
                    'email',
                ],
                'CARD' => [
                    'government_id',
                ],
                'DEBIT_CARD' => [
                    'government_id',
                ],
                'CREDIT_CARD' => [
                    'government_id',
                ],
                'REVOLVING_CREDIT' => [
                    'government_id',
                    'mobile',
                    'email',
                ],
                'PART_PAYMENT' => [
                    'government_id',
                    'phone',
                    'mobile',
                    'email',
                ],
                'undefined' => [
                    'government_id',
                    'phone',
                    'mobile',
                    'email',
                ],
            ],
            'LEGAL' => [
                'COMPINVOICE' => [
                    'applicant_government_id',
                    'applicant_telephone_number',
                    'applicant_mobile_number',
                    'applicant_email_address',
                    'applicant_full_name',
                    'contact_government_id',
                ],
                'INVOICE' => [
                    'applicant_government_id',
                    'applicant_telephone_number',
                    'applicant_mobile_number',
                    'applicant_email_address',
                    'applicant_full_name',
                    'contact_government_id',
                ],
                'undefined' => [
                    'phone',
                    'mobile',
                    'email',
                ],
            ],
        ];

        $return = $return[$customerType->value][$key] ?? $return[$customerType->value]['undefined'];

        // Hand over the result to the filter on your way back.
        return WordPress::applyFilters('getSpecificTypeFields', $return, $key);
    }
}
