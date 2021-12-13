<?php

namespace ResursBank\Service;

use Exception;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use ResursBank\Module\PluginHooks;
use ResursBank\Module\ResursBankAPI;
use TorneLIB\IO\Data\Strings;

/**
 * Class WordPress WordPress related actions.
 *
 * @package ResursBank
 * @since 0.0.1.0
 */
class WordPress
{
    /**
     * @since 0.0.1.0
     */
    public static function initializePlugin()
    {
        // Do not actively work where WooCommerce isn't live.
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        // Initialize plugin functions.
        new PluginHooks();
        // Always initialize defaults once on plugin loaded (performance saver).
        Data::getDefaultsInit();
        self::setupAjaxActions();
        self::setupFilters();
        self::setupScripts();
        self::setupActions();
        self::setupWoocommerceAdminActions();
        self::setupWoocommerceCheckoutActions();
    }

    /**
     * Preparing for ajax actions.
     *
     * @since 0.0.1.0
     */
    private static function setupAjaxActions()
    {
        // Take a note on checkout_create_order, which is breaking some kind of internal standard here.
        // The intentions from the beginning was to just use an rco-naming here.
        $actionList = [
            'test_credentials',
            'import_credentials',
            'get_payment_methods',
            'get_new_callbacks',
            'get_trigger_test',
            'get_trigger_response',
            'get_address',
            'checkout_create_order',
            'purchase_reject',
            'callback_unregister',
            'get_callback_matches',
            'get_callback_matches',
            'get_internal_resynch',
            'set_new_annuity',
            'get_new_annuity_calculation',
            'get_cost_of_purchase',
        ];

        foreach ($actionList as $action) {
            $camelCaseAction = sprintf('ResursBank\Module\PluginApi::%s', Strings::returnCamelCase($action));
            add_action(
                sprintf('rbwc_%s', $action),
                $camelCaseAction
            );
        }
    }

    /**
     * Internal filter setup.
     *
     * @since 0.0.1.0
     */
    private static function setupFilters()
    {
        // Generic calls.
        add_filter('plugin_action_links', 'ResursBank\Service\WooCommerce::getPluginAdminUrl', 10, 2);
        // Other calls.
        add_filter('rbwc_admin_dynamic_content', 'ResursBank\Gateway\AdminPage::getAdminDynamicContent', 10, 2);
        // Data calls.
        add_filter('rbwc_get_plugin_information', 'ResursBank\Module\Data::getPluginInformation');
        // Localization.
        add_filter('rbwc_localizations_generic', 'ResursBank\Service\WooCommerce::getGenericLocalization', 10, 2);
        // Helper calls.
        add_filter('woocommerce_get_settings_pages', 'ResursBank\Service\WooCommerce::getSettingsPages');
        add_filter('is_protected_meta', 'ResursBank\Service\WooCommerce::getProtectedMetaData', 10, 3);
        add_filter('rbwc_get_part_payment_page', 'ResursBank\Service\WordPress::getPartPaymentPage');

        if (Data::isEnabled()) {
            add_filter('woocommerce_payment_gateways', 'ResursBank\Service\WooCommerce::getGateways');
            add_filter(
                'woocommerce_available_payment_gateways',
                'ResursBank\Service\WooCommerce::getAvailableGateways'
            );
            add_filter('rbwc_get_address_field_controller', 'ResursBank\Service\WordPress::getAddressFieldController');
            add_filter('allow_resurs_run', 'ResursBank\Service\WooCommerce::getAllowResursRun');
        }
    }

    /**
     * Script preparation.
     *
     * @since 0.0.1.0
     */
    private static function setupScripts()
    {
        add_action('wp_enqueue_scripts', 'ResursBank\Service\WordPress::setResursBankScripts');
        add_action('admin_enqueue_scripts', 'ResursBank\Service\WordPress::setResursBankScriptsAdmin');
    }

    /**
     * Basic actions.
     *
     * @since 0.0.1.0
     */
    private static function setupActions()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        add_action('admin_notices', '\ResursBank\Service\WordPress::getAdminNotices');
        add_action('rbwc_get_localized_scripts', '\ResursBank\Service\WordPress::getLocalizedScripts', 10, 3);
        add_action('rbwc_localizations_admin', '\ResursBank\Service\WordPress::getLocalizedScriptsDeprecated', 10, 2);
        add_action('wp_ajax_' . $action, '\ResursBank\Module\PluginApi::execApi');
        add_action('wp_ajax_nopriv_' . $action, '\ResursBank\Module\PluginApi::execApiNoPriv');
        add_action('woocommerce_admin_field_button', '\ResursBank\Module\FormFields::getFieldButton', 10, 2);
        add_action('woocommerce_admin_field_decimal_warning', '\ResursBank\Module\FormFields::getFieldDecimals', 10, 2);
        add_action('woocommerce_admin_field_methodlist', '\ResursBank\Module\FormFields::getFieldMethodList', 10, 2);
        add_action(
            'woocommerce_admin_field_callbacklist',
            '\ResursBank\Module\FormFields::getFieldCallbackList',
            10,
            2
        );
        add_filter('woocommerce_get_settings_general', '\ResursBank\Module\Data::getGeneralSettings');
        add_action('woocommerce_single_product_summary', 'ResursBank\Module\Data::getAnnuityFactors');
        add_action('updated_option', '\ResursBank\Module\PluginApi::getOptionsControl', 10, 3);
        // Checkout Actions.
        if (Data::isEnabled()) {
            add_filter('woocommerce_before_checkout_billing_form', 'ResursBank\Module\FormFields::getGetAddressForm');
        }
    }

    /**
     * Admin events.
     *
     * @since 0.0.1.0
     */
    private static function setupWoocommerceAdminActions()
    {
        if (Data::isEnabled()) {
            add_action(
                'woocommerce_admin_order_data_after_order_details',
                'ResursBank\Service\WooCommerce::getAdminAfterOrderDetails'
            );
            add_action(
                'woocommerce_admin_order_data_after_billing_address',
                'ResursBank\Service\WooCommerce::getAdminAfterBilling'
            );
            add_action(
                'woocommerce_admin_order_data_after_shipping_address',
                'ResursBank\Service\WooCommerce::getAdminAfterShipping'
            );
        }
    }

    /**
     * Customer based events (checkout, etc).
     *
     * @since 0.0.1.0
     */
    private static function setupWoocommerceCheckoutActions()
    {
        // Customer is in checkout.
        add_action(
            'woocommerce_before_checkout_form',
            'ResursBank\Service\WooCommerce::setIsInCheckout'
        );
        add_action(
            'woocommerce_is_checkout',
            'ResursBank\Service\WooCommerce::setIsInCheckout'
        );
        // Customer is not in checkout.
        add_action(
            'woocommerce_add_to_cart',
            'ResursBank\Service\WooCommerce::setAddToCart'
        );
        // Customer is not in checkout. RCO helper, updating order rows on fly in this section.
        add_action(
            'woocommerce_cart_updated',
            'ResursBank\Service\WooCommerce::setUpdatedCart'
        );
        // Customer is not in checkout.
        add_filter(
            'woocommerce_update_order_review_fragments',
            'ResursBank\Service\WooCommerce::getReviewFragments'
        );
        add_action(
            'woocommerce_checkout_update_order_review',
            'ResursBank\Service\WooCommerce::getOrderReviewSettings'
        );
    }

    /**
     * Render an options list array for custom part payment widgets.
     *
     * @return array
     * @since 0.0.1.0
     */
    public static function getPartPaymentPage()
    {
        $pagelist = get_pages();
        $widgetPages = [
            '0' => '',
        ];
        /** @var WP_Post $pages */
        foreach ($pagelist as $page) {
            $widgetPages[$page->ID] = $page->post_title;
        }

        return $widgetPages;
    }

    /**
     * Look for admin notices.
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminNotices()
    {
        global $current_tab, $parent_file;

        // See if there is a credential error for Resurs Bank.
        self::getCredentialError();

        if (isset($_SESSION[Data::getPrefix()]['exception'])) {
            $class = 'notice notice-error is-dismissible';
            foreach ($_SESSION[Data::getPrefix()]['exception'] as $index => $item) {
                printf(
                    '<div class="%1$s"><p>[%3$s] %2$s</p></div>',
                    esc_attr($class),
                    esc_html($item->getMessage()),
                    Data::getPrefix()
                );
            }
            unset($_SESSION[Data::getPrefix()]['exception']);
        }

        $requiredVersionNotice = sprintf(
            __(
                'The current plugin "%s" requires at least version %s - for the moment, you are running ' .
                'on version %s. You should consider upgrading as soon as possible.',
                'trbwc'
            ),
            Data::getPluginTitle(true),
            WooCommerce::getRequiredVersion(),
            WooCommerce::getWooCommerceVersion()
        );

        try {
            if ($parent_file === 'woocommerce' || $current_tab === Data::getPrefix('admin')) {
                WooCommerce::testRequiredVersion(false);
            }
        } catch (Exception $e) {
            Data::setLogException($e);
            echo Data::getGenericClass()->getTemplate(
                'adminpage_woocommerce_requirement',
                [
                    'requiredVersionNotice' => $requiredVersionNotice,
                ]
            );
        }

        $selfAwareness = self::applyFiltersDeprecated('v22_woo_appearance', false);
        if ($selfAwareness) {
            self::getOldSelfAwareness();
        }
    }

    /**
     * Generate admin notices the ugly way since there is no proper front end script to push
     * out such notices.
     * @since 0.0.1.0
     */
    private static function getCredentialError()
    {
        $frontCredentialCheck = Data::getResursOption('front_callbacks_credential_error');
        try {
            if (!empty($frontCredentialCheck)) {
                $credentialMessage = json_decode($frontCredentialCheck);
                // Generate an exeception the ugly way.
                if (isset($credentialMessage->message)) {
                    throw new Exception(
                        sprintf(
                            'Resurs Bank %s (%s): %s',
                            isset($credentialMessage->function) ? $credentialMessage->function : __FUNCTION__,
                            $credentialMessage->code,
                            $credentialMessage->message
                        ),
                        $credentialMessage->code
                    );
                }
            }
        } catch (Exception $e) {
            if (!isset($_SESSION[Data::getPrefix()]['exception'])) {
                $_SESSION[Data::getPrefix()]['exception'] = [];
            }
            $_SESSION[Data::getPrefix()]['exception'][] = $e;
        }
    }

    /**
     * Run deprecated filter features, use the new definitions instead. This handler should not even be necessary.
     *
     * @param $filterName
     * @param $value
     * @return mixed|void
     * @since 0.0.1.0
     */
    public static function applyFiltersDeprecated($filterName, $value)
    {
        $return = apply_filters(
            sprintf('%s_%s', 'resurs_bank', self::getFilterName($filterName)),
            $value,
            self::getFilterArgs(func_get_args())
        );

        // This dual filter solutions isn't very clever.
        if ($return === null) {
            $return = apply_filters(
                sprintf('%s_%s', 'resursbank', self::getFilterName($filterName)),
                $value,
                self::getFilterArgs(func_get_args())
            );
        }

        return $return;
    }

    /**
     * @param $filterName
     * @return string
     * @since 0.0.1.0
     */
    public static function getFilterName($filterName)
    {
        $return = $filterName;
        if (defined('RESURSBANK_SNAKECASE_FILTERS')) {
            $return = (new Strings())->getSnakeCase($filterName);
        }

        return $return;
    }

    /**
     * Clean up arguments and return the real ones.
     *
     * @param $args
     * @return array
     * @since 0.0.1.0
     */
    public static function getFilterArgs($args)
    {
        if (is_array($args) && count($args) > 2) {
            array_shift($args);
            array_shift($args);
        }

        return $args;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getOldSelfAwareness()
    {
        echo Data::getGenericClass()->getTemplate(
            'adminpage_woocommerce_version22',
            [
                'wooPlug22VersionInfo' => sprintf(
                    __(
                        'It seems that you still have another plugin enabled (%s %s) in this platform that works ' .
                        'as Resurs Bank Payment Gateway. If this is intended, you can ignore this message.',
                        'trbwc'
                    ),
                    defined('RB_WOO_CLIENTNAME') ? RB_WOO_CLIENTNAME : 'Resurs Bank for WooCommerce',
                    defined('RB_WOO_VERSION') ? RB_WOO_VERSION : 'v2.x'
                ),
            ]
        );
    }

    /**
     * Check whether older plugins should be disabled at this point.
     * @return bool
     * @since 0.0.1.0
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public static function getPriorVersionsDisabled()
    {
        $isAjax = is_ajax();
        //if (is_admin() && current_user_can('administrator')) {$return = false;}

        // True means that the old plugin will be disabled at this moment
        $return = self::getPrioVersionsDisabledLocations();

        if ($isAjax) {
            // Allow life in ajax calls.
            $return = false;
        } elseif ($return && self::getRequest('post')) {
            $return = false;
        } elseif ($return && self::getRequest('wc-api') === 'WC_Resurs_Bank') {
            $return = false;
        } elseif ($return) {
            // Find more places that could be necessary to enable the plugin.
            $return = WordPress::applyFilters('getPriorVersionsDisabled', $return);
        }

        return $return;
    }

    /**
     * Defaults disable for old plugin.
     * @return bool|string|null
     * @since 0.0.1.0
     */
    private static function getPrioVersionsDisabledLocations()
    {
        $return = Data::getResursOption('priorVersionsDisabled');
        $appearance = self::getPriorVersionDisabledAppearances();
        $section = self::getRequest('section');
        $page = self::getRequest('page');
        $tab = self::getRequest('tab');
        $action = self::getRequest('action');
        $isInPageSection = in_array($page, $appearance['in'], true) ||
            in_array($section, $appearance['in'], true) ||
            in_array($action, $appearance['in'], true) ||
            in_array($tab, $appearance['in'], true) ||
            (int)$page > 0;

        $isInSelf = (
            in_array($page, $appearance['notIn'], true) ||
            in_array($section, $appearance['notIn'], true) ||
            in_array($tab, $appearance['notIn'], true)
        );
        // Some sections are still allowed, for example wc-settings so that the old plugin can be configured.
        if ($isInPageSection && !$isInSelf) {
            $return = false;
        }

        return $return;
    }

    /**
     * @return array
     * @since 0.0.1.0
     */
    private static function getPriorVersionDisabledAppearances()
    {
        return [
            'in' => [
                'wc-settings',
                'editpost',
            ],
            'notIn' => [
                sprintf('%s_admin', Data::getPrefix()),
            ],
        ];
    }

    /**
     * @param $request
     * @return mixed|string
     * @since 0.0.1.0
     */
    private static function getRequest($request)
    {
        return isset($_REQUEST[$request]) ? $_REQUEST[$request] : '';
    }

    /**
     * WordPress equivalent for apply_filters, but properly prefixed with plugin name tag.
     *
     * @param $filterName
     * @param $value
     * @return mixed
     * @since 0.0.1.0
     */
    public static function applyFilters($filterName, $value)
    {
        $applyArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                self::getFilterName($filterName)
            ),
            $value,
        ];

        return apply_filters(...array_merge($applyArray, self::getFilterArgs(func_get_args())));
    }

    /**
     * @since 0.0.1.0
     */
    public static function setResursBankScriptsAdmin()
    {
        self::setResursBankScripts(true);
    }

    /**
     * @param bool $isAdmin
     * @since 0.0.1.0
     */
    public static function setResursBankScripts($isAdmin = null)
    {
        foreach (Data::getPluginStyles($isAdmin) as $styleName => $styleFile) {
            wp_enqueue_style(
                sprintf('%s_%s', Data::getPrefix(), $styleName),
                sprintf(
                    '%s/css/%s?%s',
                    Data::getGatewayUrl(),
                    $styleFile,
                    Data::getTestMode() ? time() : 'static'
                ),
                [],
                Data::getCurrentVersion()
            );
        }

        foreach (Data::getPluginScripts($isAdmin) as $scriptName => $scriptFile) {
            $realScriptName = sprintf('%s_%s', Data::getPrefix(), $scriptName);
            self::setEnqueue($realScriptName, $scriptFile, $isAdmin);
        }
    }

    /**
     * @param $scriptName
     * @param $scriptFile
     * @param $isAdmin
     * @param array $localizeArray
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function setEnqueue($scriptName, $scriptFile, $isAdmin, $localizeArray = [])
    {
        wp_enqueue_script(
            $scriptName,
            sprintf(
                '%s/js/%s?%s',
                Data::getGatewayUrl(),
                $scriptFile,
                Data::getTestMode() ? Data::getPrefix() . '-' . time() : 'static'
            ),
            Data::getJsDependencies($scriptName, $isAdmin)
        );
        self::doAction('getLocalizedScripts', $scriptName, $isAdmin, $localizeArray);
    }

    /**
     * @param $actionName
     * @param $value
     * @since 0.0.1.0
     */
    public static function doAction($actionName, $value)
    {
        $actionArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                self::getFilterName($actionName)
            ),
            $value,
        ];

        do_action(...array_merge($actionArray, self::getFilterArgs(func_get_args())));
    }

    /**
     * @param $scriptName
     * @param null $isAdmin
     * @param null $extraLocalizationData
     * @since 0.0.1.0
     */
    public static function getLocalizedScripts($scriptName, $isAdmin = null, $extraLocalizationData = null)
    {
        if (($localizationData = self::getLocalizationData($scriptName, (bool)$isAdmin))) {
            if (is_array($extraLocalizationData) && count($extraLocalizationData)) {
                $localizationData = array_merge($localizationData, $extraLocalizationData);
            }
            wp_localize_script(
                $scriptName,
                sprintf('l_%s', $scriptName),
                $localizationData
            );
        }
    }

    /**
     * @param $scriptName
     * @param $isAdmin
     * @return array
     * @since 0.0.1.0
     */
    private static function getLocalizationData($scriptName, $isAdmin)
    {
        $return = [];

        if ((bool)$isAdmin && preg_match('/_admin$/', $scriptName)) {
            $return = self::getLocalizationDataAdmin($return);
        } elseif (preg_match('/_all$/', $scriptName)) {
            $return = self::getLocalizationDataGlobal($return);
        } else {
            $return = self::getLocalizationDataGeneric($return, $scriptName);
        }

        return $return;
    }

    /**
     * Localized variables shown in admin only.
     *
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getLocalizationDataAdmin($return)
    {
        global $current_tab;
        $return['noncify'] = self::getNonce('admin');
        $return['environment'] = ResursBankAPI::getEnvironment();
        $return['wsdl'] = ResursBankAPI::getWsdlMode();
        $return['translate_checkout_rco'] = __(
            'Resurs Checkout (RCO) is a one page stand-alone checkout, embedded as an iframe on the checkout ' .
            'page. It is intended to give you a full scale payment solution with all payment methods collected ' .
            'at the endpoint of Resurs Bank.',
            'trbwc'
        );
        $return['translate_checkout_simplified'] = __(
            'The integrated checkout (also known as the "simplified shop flow") is a direct integration with ' .
            'WooCommerce which uses intended APIs to interact with your customers while finishing the orders.',
            'trbwc'
        );
        $return['translate_checkout_hosted'] = __(
            '"Resurs Hosted Checkout" works similarly as the integrated simplified checkout, but on the ' .
            'checkout itself the customer are redirected to a hosted website to fulfill their payments. ' .
            'It can be quite easily compared with a Paypal solution.',
            'trbwc'
        );
        $return['resurs_test_credentials'] = __(
            'Validate and save credentials',
            'trbwc'
        );
        $return['credential_failure_notice'] = __(
            'The credential check failed. If you save the current data we can not guarantee ' .
            'that your store will properly work.',
            'trbwc'
        );
        $return['credential_success_notice'] = __(
            'The credential check was successful. You may now save the rest of your data.',
            'trbwc'
        );
        $return['requireFraudControl'] = __(
            'This setting requires you to enable the fraud control.',
            'trbwc'
        );
        $return['waiting_for_callback'] = __(
            'Waiting for test callback to arrive.',
            'trbwc'
        );
        $return['trigger_test_fail'] = __(
            'Callback trigger is currently not working.',
            'trbwc'
        );
        $return['callback_test_timeout'] = __(
            'Callback trigger timeout. Aborted.',
            'trbwc'
        );
        $return['remove_callback_confirm'] = __(
            'Are you sure you want to remove callback',
            'trbwc'
        );
        $return['update_callbacks_required'] = __(
            'Callback URLs for Resurs Bank may be outdated. Do you want to refresh the current data?',
            'trbwc'
        );
        $return['update_callbacks_refresh'] = __(
            'Refresh has finished. Please check your new settings to confirm the update.',
            'trbwc'
        );
        $return['current_tab'] = $current_tab;
        $return['enable'] = __('Enable', 'trbwc');
        $return['disable'] = __('Disable', 'trbwc');

        return self::applyFilters('localizationsAdmin', $return);
    }

    /**
     * Makes nonces strict based on client ip address.
     *
     * @param $tag
     * @param bool $strictify
     * @return string
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    private static function getNonce($tag, $strictify = true)
    {
        return (string)wp_create_nonce(self::getNonceTag($tag, $strictify));
    }

    /**
     * @param $tag
     * @param bool $strictify
     * @return string
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getNonceTag($tag, $strictify = true)
    {
        return Data::getPrefix($tag) . '|' . ($strictify ? $_SERVER['REMOTE_ADDR'] : '');
    }

    /**
     * Localized variables shown in all views.
     *
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getLocalizationDataGlobal($return)
    {
        $defaultTimeout = 8000;
        $setAjaxifyTimeout = WordPress::applyFilters('ajaxifyTimeout', $defaultTimeout);
        $return['noncify'] = self::getNonce('all');
        $return['ajaxify'] = admin_url('admin-ajax.php');
        $return['ajaxifyTimeout'] = (int)$setAjaxifyTimeout ? $setAjaxifyTimeout : $defaultTimeout;
        $return['spin'] = Data::getImage('spin.gif');
        $return['success'] = __('Successful.', 'trbwc');
        $return['failed'] = __('Failed.', 'trbwc');
        $return['reloading'] = __('Please wait while reloading...', 'trbwc');
        $return['nonce_error'] = __(
            'The page security (nonce) is reportedly expired or is wrong. This can also be caused by the ' .
            'fact that you have already interacted with the page you are trying to update information on. ' .
            'You may want to reload your browser before proceeding.',
            'trbwc'
        );
        $return['checkout_fields'] = FormFields::getFieldString();
        $return['getAddressFieldController'] = self::applyFilters('getAddressFieldController', []);
        $return['checkoutType'] = Data::getCheckoutType();

        return self::applyFilters('localizationsGlobal', $return);
    }

    /**
     * Localized variables shown in front (customer) view only.
     *
     * @param $return
     * @param null $scriptName
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getLocalizationDataGeneric($return, $scriptName = null)
    {
        $return['noncify'] = self::getNonce('simple');

        return self::applyFilters('localizationsGeneric', $return, $scriptName);
    }

    /**
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getAddressFieldController()
    {
        $return = [
            'billing_first_name' => 'firstName',
            'billing_last_name' => 'lastName',
            'applicant-full-name' => 'firstName:lastName',
            'billing_address_1' => 'addressRow1',
            'billing_address_2' => 'addressRow2',
            'billing_postcode' => 'postalCode',
            'billing_city' => 'postalArea',
        ];

        if (Data::getCustomerType() === 'LEGAL') {
            $return['billing_company'] = 'fullName';
        }

        return $return;
    }

    /**
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getLocalizedScriptsDeprecated($return)
    {
        $importDeprecated = get_option('resursImportCredentials');

        if (!$importDeprecated) {
            $return['deprecated_login'] = !empty(Data::getResursOptionDeprecated('login')) ? true : false;
            $return['resurs_deprecated_credentials'] = __(
                'Import credentials from Resurs v2.x',
                'trbwc'
            );
            $return['credential_import_success'] = __(
                'Import successful.',
                'trbwc'
            );
            $return['credential_import_failed'] = __(
                'Import failed.',
                'trbwc'
            );
        }
        return $return;
    }
}
