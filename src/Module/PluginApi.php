<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecommerce\Types\Callback;
use ResursBank\Gateway\ResursCheckout;
use ResursBank\Gateway\ResursDefault;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use RuntimeException;
use TorneLIB\Data\Password;
use TorneLIB\IO\Data\Strings;
use WC_Checkout;
use WC_Order;

/**
 * Class PluginApi
 *
 * @package ResursBank\Module
 */
class PluginApi
{
    /**
     * @var ResursDefault
     * @since 0.0.1.0
     */
    private static $resursCheckout;
    /**
     * @var ResursDefault
     * @since 0.0.1.0
     */
    private static $resursDefault;

    /**
     * List of callbacks required for this plugin to handle payments properly.
     * @var array
     * @since 0.0.1.0
     */
    private static $callbacks = [
        Callback::UNFREEZE,
        Callback::TEST,
        Callback::UPDATE,
        Callback::BOOKED,
    ];

    /**
     * @since 0.0.1.0
     */
    public static function execApi()
    {
        $returnedValue = WordPress::applyFilters(self::getAction(), null, $_REQUEST);
        if (!empty($returnedValue)) {
            self::reply($returnedValue);
        }
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private static function getAction()
    {
        $action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

        return (new Strings())->getCamelCase(self::getTrimmedActionString($action));
    }

    /**
     * @param $action
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTrimmedActionString($action)
    {
        $action = preg_replace('/^resursbank_/i', '', $action);

        return $action;
    }

    /**
     * @param $out
     * @param bool $dieInstantly Set to exit after reply if true.
     * @since 0.0.1.0
     */
    private static function reply($out = null, $dieInstantly = true)
    {
        $success = true;

        if (!isset($out['error'])) {
            $out['error'] = null;
        }
        if (!isset($out['ajax_success'])) {
            if (!empty($out['error'])) {
                $success = false;
            }
            $out['ajax_success'] = $success;
        }

        header('Content-type: application/json; charset=utf-8', true, 200);
        echo json_encode($out);
        if ($dieInstantly) {
            die();
        }
    }

    /**
     * @since 0.0.1.0
     */
    public static function execApiNoPriv()
    {
        WordPress::doAction(self::getAction(), null);
    }

    /**
     * @since 0.0.1.0
     */
    public static function importCredentials()
    {
        update_option('resursImportCredentials', time());
        self::getValidatedNonce();
        self::reply([
            'login' => Data::getResursOptionDeprecated('login'),
            'pass' => Data::getResursOptionDeprecated('password'),
        ]);
    }

    /**
     * @param null $expire
     * @param null $noReply Boolean that returns the answer instead of replying live.
     * @return bool
     * @since 0.0.1.0
     */
    public static function getValidatedNonce($expire = null, $noReply = null)
    {
        $return = false;
        $expired = false;
        $preExpired = self::expireNonce(__FUNCTION__);
        $defaultNonceError = 'nonce_validation';

        $isSafe = (is_admin() && is_ajax());
        $nonceArray = [
            'admin',
            'all',
            'simple',
        ];

        // Not recommended as this expires immediately and stays expired.
        if ((bool)$expire && $preExpired) {
            $expired = $preExpired;
            $defaultNonceError = 'nonce_expire';
        }

        foreach ($nonceArray as $nonceType) {
            if (wp_verify_nonce(self::getParam('n'), WordPress::getNonceTag($nonceType))) {
                $return = true;
                break;
            }
        }
        if (!$return && $isSafe && (bool)Data::getResursOption('nonce_trust_admin_session')) {
            // If request is based on ajax and admin.
            $return = true;
            $expired = false;
        }

        if (!$return || $expired) {
            if (!(bool)$noReply) {
                self::reply(
                    [
                        'error' => $defaultNonceError,
                    ]
                );
            }
        }

        return $return;
    }

    /**
     * Make sure the used nonce can only be used once.
     *
     * @param $nonceTag
     * @return bool
     * @since 0.0.1.0
     */
    public static function expireNonce($nonceTag)
    {
        $optionTag = 'resurs_nonce_' . $nonceTag;
        $return = false;
        $lastNonce = get_option($optionTag);
        if (self::getParam('n') === $lastNonce) {
            $return = true;
        } else {
            // Only update if different.
            update_option($optionTag, self::getParam('n'));
        }
        return $return;
    }

    /**
     * @param $key
     * @return mixed|string
     * @since 0.0.1.0
     */
    private static function getParam($key)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
    }

    /**
     * @since 0.0.1.0
     */
    public static function getCostOfPurchase()
    {
        $wooCommerceStyleSheet = get_stylesheet_directory_uri() . '/css/woocommerce.css';
        $resursStyleSheet = Data::getGatewayUrl() . '/css/costofpurchase.css';

        $method = WooCommerce::getRequest('method');
        $total = WooCommerce::getRequest('total');
        if (Data::getCustomerCountry() !== 'DK') {
            $priceInfoHtml = Api::getResurs()->getCostOfPriceInformation($method, $total, true, true);
        } else {
            $priceInfoHtml = Api::getResurs()->getCostOfPriceInformation(Api::getPaymentMethods(), $total, false, true);
        }

        echo Data::getGenericClass()
            ->getTemplate(
                'checkout_costofpurchase_default.phtml',
                [
                    'wooCommerceStyleSheet' => $wooCommerceStyleSheet,
                    'resursStyleSheet' => $resursStyleSheet,
                    'priceInfoHtml' => $priceInfoHtml,
                ]
            );

        die;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function checkoutCreateOrder()
    {
        WooCommerce::setSessionValue('rco_customer_session_request', $_REQUEST['rco_customer']);

        $finalCartTotal = WC()->cart->total;
        $lastSeenCartTotal = WooCommerce::getSessionValue('customerCartTotal');
        if ($finalCartTotal === $lastSeenCartTotal) {
            self::getPreparedRcoOrder();
            self::getCreatedOrder();
        } else {
            $elseWhereMessage = __(
                'While you were in the checkout, the cart has been updated somewhere else. If you have more tabs ' .
                'open in your browser, make sure you only use one of them during the payment. You may want to ' .
                'reload this page to make it right.',
                'trbwc'
            );
            $return = [
                'success' => false,
                'errorString' => $elseWhereMessage,
                'messages' => $elseWhereMessage,
                'errorCode' => 400,
                'orderId' => 0,
            ];
        }

        return $return;
    }

    /**
     * What will be created on success.
     * @return WC_Checkout
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getPreparedRcoOrder()
    {
        // Remove this, when it's safe. As it seems, this class is never used when RCO is initiated.
        //self::$resursDefault = new ResursDefault();
        self::$resursCheckout = new ResursCheckout();
        $billingAddress = self::$resursCheckout->getCustomerFieldsByApiVersion();
        $deliveryAddress = self::$resursCheckout->getCustomerFieldsByApiVersion('deliveryAddress');

        foreach ($billingAddress as $billingDataKey => $billingDataValue) {
            $deliveryAddress[$billingDataKey] = self::getDeliveryFrom(
                $billingDataKey,
                $deliveryAddress,
                $billingAddress
            );
        }

        self::setCustomerAddressRequest($billingAddress);
        self::setCustomerAddressRequest($deliveryAddress, 'shipping');
    }

    /**
     * Get delivery address by delivery address with fallback on billing.
     * This has also been used in the old plugin to match data correctly.
     *
     * @param $key
     * @param $deliveryArray
     * @param $billingArray
     * @return mixed|string
     * @since 0.0.1.0
     */
    private static function getDeliveryFrom($key, $deliveryArray, $billingArray)
    {
        if (isset($deliveryArray[$key]) && !empty($deliveryArray[$key])) {
            $return = $deliveryArray[$key];
        } elseif (isset($billingArray[$key]) && !empty($billingArray[$key])) {
            $return = $billingArray[$key];
        } else {
            $return = '';
        }

        return $return;
    }

    /**
     * Rewrite post and request data on fly so the woocommerce data fields matches those that come from RCO.
     *
     * @param $checkoutCustomer
     * @since 0.0.1.0
     */
    private static function setCustomerAddressRequest($checkoutCustomer, $type = 'billing')
    {
        foreach ($checkoutCustomer as $item => $value) {
            $itemVar = sprintf('%s_%s', $type, $item);
            $_REQUEST[$itemVar] = $value;
            $_POST[$itemVar] = $value;
        }
    }

    /**
     * @throws Exception
     */
    private static function getCreatedOrder()
    {
        $return = [
            'success' => false,
            'errorString' => '',
            'errorCode' => 0,
            'orderId' => 0,
        ];
        try {
            $order = new WC_Checkout();

            // This value is needed during process_payment, since that's where we get.
            WooCommerce::setSessionValue('resursCheckoutType', Data::getCheckoutType());

            // Note handing over responsibily makes it most plausably that the return section here
            // will only fire up if something goes terribly wrong as the process_checkout is handling the
            // return section individually from somewhere else. Take a look at ResursDefault.php for the
            // rest of this process. At this moment we therefore don't need to worry about metadata and
            // stored apiData since it is entirely unavailable.
            $order->process_checkout();
        } catch (Exception $e) {
            $return['errorCode'] = $e->getCode();
            $return['errorString'] = $e->getMessage();
        }

        // This return point only occurs on severe errors.
        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function testCredentials()
    {
        $isValid = self::getValidatedNonce(null, true);
        $validationResponse = false;

        /**
         * Defines whether this is a live change or just a test. This occurs when the user is switching
         * environment without saving the data. This allows us - for example - to validate production
         * before switching over.
         *
         * @var bool $isLiveChange
         */
        $isLiveChange = Data::getResursOption('environment') === self::getParam('e') ? false : true;

        if ($isValid) {
            try {
                $validationResponse = (new Api())->getConnection()->validateCredentials(
                    (self::getParam('e') !== 'live') ? 1 : 0,
                    self::getParam('u'),
                    self::getParam('p')
                );
                Data::delResursOption('front_credential_error');
            } catch (RuntimeException $e) {
                $validationResponse = $e->getMessage();
                Data::setLogNotice(
                    sprintf(
                        __(
                            'An error occured during credential checking: %s (%d).',
                            'trbwc'
                        ),
                        $e->getMessage(),
                        $e->getCode()
                    )
                );
            }
        }

        // Save when validating.
        if ($validationResponse) {
            // Since credentials was verified, we can set the environment first to ensure credentials are stored
            // on the proper options.
            if (!$isLiveChange) {
                Data::setResursOption('environment', self::getParam('e'));
            }

            switch (self::getParam('e')) {
                case 'live':
                    $getUserFrom = 'login_production';
                    $getPasswordFrom = 'password_production';
                    break;
                default:
                    $getUserFrom = 'login';
                    $getPasswordFrom = 'password';
            }
            Data::setResursOption($getUserFrom, self::getParam('u'));
            Data::setResursOption($getPasswordFrom, self::getParam('p'));

            if ($isLiveChange) {
                Api::getPaymentMethods(false);
                Api::getAnnuityFactors(false);
            }
        }

        Data::setLogNotice(
            sprintf(
                __(
                    'Resurs Bank credential validation for environment %s executed, response was %s.',
                    'trbwc'
                ),
                self::getParam('e'),
                is_bool($validationResponse) && (bool)$validationResponse ? 'true' : 'false'
            )
        );

        if ($validationResponse && $isLiveChange) {
            self::getPaymentMethods(false);
            self::getNewCallbacks();
        }

        self::reply(
            [
                'validation' => $validationResponse,
            ]
        );
    }

    /**
     * @param bool $reply
     * @param bool $validate
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPaymentMethods($reply = true, $validate = true)
    {
        if ($validate) {
            self::getValidatedNonce();
        }

        // Re-fetch payment methods.
        Api::getPaymentMethods(false);
        Api::getAnnuityFactors(false);
        if (self::canReply($reply)) {
            self::reply([
                'reload' => true,
            ]);
        }
    }

    /**
     * @param $reply
     * @return bool
     * @since 0.0.1.0
     */
    private static function canReply($reply)
    {
        return is_null($reply) || (bool)$reply === true;
    }

    /**
     * @return bool[]
     * @throws Exception
     * @since 0.0.1.0
     * @link https://docs.tornevall.net/display/TORNEVALL/Callback+URLs+explained
     */
    public static function getNewCallbacks($validate = true)
    {
        if ($validate) {
            self::getValidatedNonce();
        }
        try {
            Api::getResurs()->unregisterEventCallback(
                Callback::FINALIZATION & Callback::ANNULMENT & Callback::AUTOMATIC_FRAUD_CONTROL,
                true
            );
        } catch (Exception $e) {
            if ($e->getCode() === 401) {
                Data::getCredentialNotice();
                throw $e;
            }
        }
        foreach (self::$callbacks as $ecomCallbackId) {
            $callbackUrl = self::getCallbackUrl(self::getCallbackParams($ecomCallbackId));
            try {
                Data::setLogNotice(sprintf('Callback Registration: %s.', $callbackUrl));
                Api::getResurs()->setRegisterCallback(
                    $ecomCallbackId,
                    $callbackUrl,
                    self::getCallbackDigestData()
                );
            } catch (Exception $e) {
                Data::setLogException($e);
            }
        }
        Api::getCallbackList(false);

        return ['reload' => true];
    }

    /**
     * @param $callbackParams
     * @return string
     * @since 0.0.1.0
     * @link https://docs.tornevall.net/display/TORNEVALL/Callback+URLs+explained
     */
    private static function getCallbackUrl($callbackParams)
    {
        $return = WooCommerce::getWcApiUrl();
        if (is_array($callbackParams)) {
            foreach ($callbackParams as $cbKey => $cbValue) {
                // Selective carefulness.
                if (preg_match('/{|}/', $cbValue)) {
                    // Variables specifically sent to Resurs Bank that need to be left as is.
                    $return = rawurldecode(add_query_arg($cbKey, $cbValue, $return));
                } else {
                    $return = add_query_arg($cbKey, $cbValue, $return);
                }
            }
        }
        return $return;
    }

    /**
     * @param $ecomCallbackId
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     * @link https://docs.tornevall.net/display/TORNEVALL/Callback+URLs+explained
     */
    private static function getCallbackParams($ecomCallbackId)
    {
        $params = [
            'c' => Api::getResurs()->getCallbackTypeString($ecomCallbackId),
            't' => time(),
        ];
        switch ($ecomCallbackId) {
            case Callback::TEST:
                $params += [
                    'ignore1' => '{param1}',
                    'ignore2' => '{param2}',
                    'ignore3' => '{param3}',
                    'ignore4' => '{param4}',
                    'ignore5' => '{param5}',
                ];
                unset($params['t']);
                break;
            default:
                // UNFREEZE, ANNULMENT, FINALIZATION, UPDATE, BOOKED
                $params['p'] = '{paymentId}';
                $params['d'] = '{digest}';
        }

        return $params;
    }

    /**
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getCallbackDigestData()
    {
        $return = [
            'digestSalt' => self::getTheSalt(),
        ];

        return $return;
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getTheSalt()
    {
        $currentSaltData = (int)Data::getResursOption('saltdate');
        $saltDateControl = time() - $currentSaltData;
        $currentSalt = ($return = Data::getResursOption('salt'));

        if (empty($currentSalt) || $saltDateControl >= 86400) {
            $return = (new Password())->mkpass();
            Data::setResursOption('salt', $return);
            Data::setResursOption('saltdate', time());
        }

        return $return;
    }

    /**
     * @since 0.0.1.0
     */
    public static function setNewAnnuity()
    {
        //self::getValidatedNonce();

        $mode = WooCommerce::getRequest('mode');

        switch ($mode) {
            case 'e':
                Data::setResursOption(
                    'currentAnnuityFactor',
                    WooCommerce::getRequest('id')
                );
                Data::setResursOption(
                    'currentAnnuityDuration',
                    (int)WooCommerce::getRequest('duration')
                );
                break;
            case 'd':
                Data::delResursOption('currentAnnuityFactor');
                Data::delResursOption('currentAnnuityDuration');
                break;
            default:
        }

        // Confirm Request.
        self::reply(
            [
                'id' => WooCommerce::getRequest('id'),
                'duration' => Data::getResursOption('currentAnnuityDuration'),
                'mode' => WooCommerce::getRequest('mode'),
            ]
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getNewAnnuityCalculation()
    {
        $currencyRequest = empty(Data::getResursOption('part_payment_template')) ? [] : ['currency' => ' '];

        self::reply(
            [
                'price' => wc_price(
                    Api::getResurs()->getAnnuityPriceByDuration(
                        WooCommerce::getRequest('price'),
                        Data::getResursOption('currentAnnuityFactor'),
                        (int)Data::getResursOption('currentAnnuityDuration')
                    ),
                    $currencyRequest
                ),
            ]
        );
    }

    /**
     * @since 0.0.1.0
     */
    public static function getCallbackMatches()
    {
        $callbackConstant = 0;
        $return['requireRefresh'] = false;
        $return['similarity'] = 0;

        foreach (self::$callbacks as $callback) {
            $callbackConstant += $callback;
        }

        $current_tab = WooCommerce::getRequest('t');
        $hasErrors = false;
        $freshCallbackList = [];
        $e = null;

        try {
            $freshCallbackList = Api::getResurs()->getRegisteredEventCallback($callbackConstant);
            Data::clearCredentialNotice();
        } catch (Exception $e) {
            $hasErrors = true;

            if ($e->getCode() === 401) {
                Data::getCredentialNotice();
            } else {
                Data::setResursOption(
                    'front_callbacks_credential_error',
                    json_encode(['code' => $e->getCode(), 'message' => $e->getMessage(), 'function' => __FUNCTION__])
                );
            }
        }

        if ($current_tab === sprintf('%s_admin', Data::getPrefix()) &&
            (time() - (int)Data::getResursOption('lastCallbackCheck')) > 60) {
            $storedCallbacks = Data::getResursOption('callbacks');

            if (!$hasErrors) {
                if (empty($storedCallbacks)) {
                    $return['requireRefresh'] = true;
                } else {
                    foreach (self::$callbacks as $callback) {
                        $expectedUrl = self::getCallbackUrl(self::getCallbackParams($callback));
                        similar_text(
                            $expectedUrl,
                            $freshCallbackList[Api::getResurs()->getCallbackTypeString($callback)],
                            $percentualValue
                        );
                        if ($percentualValue < 90) {
                            $return['requireRefresh'] = true;
                        }
                        $return['similarity'] = $percentualValue;
                    }
                }
            }
        }
        $return['errors'] = [
            'code' => isset($e) ? $e->getCode() : 0,
            'message' => isset($e) ? $e->getMessage() : null,
        ];

        self::reply($return);
    }

    /**
     * @since 0.0.1.0
     */
    public static function getInternalResynch()
    {
        $return = [
            'reload' => false,
            'errorstring' => '',
            'errorcode' => 0,
        ];
        if (is_admin()) {
            try {
                self::getNewCallbacks(false);
                Api::getPaymentMethods(false);
                Api::getAnnuityFactors(false);
            } catch (\Exception $e) {
                $return['errorstring'] = $e->getMessage();
                $return['errorcode'] = $e->getCode();
            }
        } else {
            $return['errorstring'] = 'Not admin';
            $return['errorcode'] = 401;
        }

        self::reply(
            $return
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function callbackUnregister()
    {
        $successRemoval = false;
        $callback = WooCommerce::getRequest('callback');
        $message = '';
        if ((bool)Data::getResursOption('show_developer')) {
            $successRemoval = Api::getResurs()->unregisterEventCallback(
                Api::getResurs()->getCallbackTypeByString($callback)
            );
        } else {
            $message = __('Advanced mode is disabled. You can not make this change.', 'trbwc');
        }

        self::reply(
            [
                'unreg' => $successRemoval,
                'callback' => $callback,
                'message' => $message,
            ]
        );
    }

    /**
     * Monitor credential changes.
     * @param $option
     * @param $old
     * @param $new
     * @since 0.0.1.0
     */
    public static function getOptionsControl($option, $old, $new)
    {
        $actOn = [
            sprintf('%s_admin_environment', Data::getPrefix()) => ['getNewCallbacks', 'getPaymentMethods'],
            sprintf('%s_admin_login', Data::getPrefix()) => ['getNewCallbacks'],
            sprintf('%s_admin_password', Data::getPrefix()) => ['getPaymentMethods'],
            sprintf('%s_admin_login_production', Data::getPrefix()) => ['getNewCallbacks'],
            sprintf('%s_admin_password_production', Data::getPrefix()) => ['getPaymentMethods'],
        ];
        if ($old !== $new && isset($actOn[$option]) && !is_ajax()) {
            foreach ($actOn[$option] as $execFunction) {
                try {
                    switch ($execFunction) {
                        case 'getNewCallbacks':
                            // This function is called from front-end too and in such cases it does nonce
                            // checks. When saving from admin, nonce checks are not needed - it rather breaks
                            // the saving itself. So in this particular case, nonce checks are disabled.
                            self::{$execFunction}(false);
                            break;
                        case 'getPaymentMethods':
                            self::{$execFunction}(false, false);
                            break;
                        default:
                            self::{$execFunction}();
                    }
                    Data::clearCredentialNotice();
                } catch (Exception $e) {
                    if (is_admin() && $e->getCode() === 401) {
                        // @todo RWC-234 github #32
                        Data::getCredentialNotice();
                    }
                }
            }
        }
    }

    /**
     * Trigger TEST callback at Resurs Bank.
     *
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getTriggerTest()
    {
        Data::setResursOption('resurs_callback_test_response', null);
        $return = WordPress::applyFiltersDeprecated('resurs_trigger_test_callback', null);
        $return['api'] = (bool)Api::getResurs()->triggerCallback();
        $return['html'] = sprintf(
            '<div>%s</div><div id="resursWaitingForTest"></div>',
            sprintf(
                __('Activated test trigger. Response "%s" received.', 'trbwc'),
                $return['api'] ? 'success' : 'fail'
            )
        );
        $return = WordPress::applyFilters('triggerCallback', $return);
        return $return;
    }

    /**
     * @return array
     * @since 0.0.1.0
     */
    public static function getTriggerResponse()
    {
        $runTime = 0;
        $success = false;
        if (isset($_REQUEST['runTime'])) {
            $runTime = (int)$_REQUEST['runTime'];
        }
        if ((int)Data::getResursOption('resurs_callback_test_response') > 0) {
            $lastResponse = sprintf(
                '%s %s',
                __('Received', 'trbwc'),
                strftime('%Y-%m-%d %H:%M:%S', Data::getResursOption('resurs_callback_test_response'))
            );
            $success = true;
        } else {
            $lastResponse =
                sprintf(
                    __(
                        'Waiting for callback TEST (%d seconds).'
                    ),
                    $runTime
                );
        }
        $return = [
            'lastResponse' => $lastResponse,
            'runTime' => $runTime,
            'success' => $success,
        ];

        return $return;
    }

    /**
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAddress()
    {
        $apiRequest = Api::getResurs();
        $addressResponse = [];
        $identification = WooCommerce::getRequest('identification');
        $customerType = Data::getCustomerType();
        $customerCountry = Data::getCustomerCountry();

        if (Data::isTest() && Data::isProductionAvailable() && Data::getResursOption('simulate_real_getaddress')) {
            $apiRequest->setEnvironment(RESURS_ENVIRONMENTS::PRODUCTION);
            $apiRequest->setAuthentication(
                Data::getResursOption('login_production'),
                Data::getResursOption('password_production')
            );
        }

        $return = [
            'api_error' => '',
            'code' => 0,
            'identificationResponse' => [],
        ];

        WooCommerce::setSessionValue('identification', WooCommerce::getRequest('identification'));

        switch ($customerCountry) {
            case 'NO':
                // This request works only on norwegian accounts.
                try {
                    $addressResponse = (array)$apiRequest->getAddressByPhone($identification, $customerType);
                    self::getAddressLog($customerCountry, $customerType, $identification, __(
                        'By phone request.',
                        'trbwc'
                    ));
                } catch (Exception $e) {
                    // If we get an error here, it might be cause by credential errors.
                    // In that case lets fall back to the default lookup.
                    $addressResponse = (array)$apiRequest->getAddress($identification, $customerType);
                    self::getAddressLog($customerCountry, $customerType, $identification, __(
                        'By phone request failed, executed failover by government id.',
                        'trbwc'
                    ));
                }
                break;
            case 'SE':
                try {
                    $addressResponse = (array)$apiRequest->getAddress($identification, $customerType);
                    self::getAddressLog($customerCountry, $customerType, $identification, __(
                        'By government id/company id (See customer type).',
                        'trbwc'
                    ));
                } catch (Exception $e) {
                    self::getAddressLog(
                        $customerCountry,
                        $customerType,
                        $identification,
                        sprintf(
                            __(
                                'By government id/company id (See customer type), but failed: (%d) %s.',
                                'trbwc'
                            ),
                            $e->getCode(),
                            $e->getMessage()
                        )
                    );
                    $return['api_error'] = $e->getMessage();
                    $return['code'] = $e->getCode();
                }
                break;
            default:
        }
        $return['identificationResponse'] = $addressResponse;
        $return['billing_country'] = $customerCountry;

        if (is_array($addressResponse) && count($addressResponse)) {
            $return = self::getTransformedAddressResponse($return, $addressResponse);
        }

        self::reply($return);
    }

    /**
     * Log an event of getAddress.
     *
     * @param $customerCountry
     * @param $customerType
     * @param $identification
     * @param $runFunctionInfo
     * @since 0.0.1.0
     */
    private static function getAddressLog($customerCountry, $customerType, $identification, $runFunctionInfo)
    {
        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                __('getAddress request (country %s, type %s) for %s: %s', 'trbwc'),
                $customerCountry,
                $customerType,
                $identification,
                $runFunctionInfo
            )
        );
    }

    /**
     * Transform getAddress responses into WooCommerce friendly data fields so that they
     * can be easily pushed out to the default forms.
     * @param $return
     * @param $addressResponse
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTransformedAddressResponse($return, $addressResponse)
    {
        $compileKeys = [];
        $compileInfo = [];
        $addressFields = WordPress::applyFilters('getAddressFieldController', []);
        foreach ($addressFields as $addressField => $addressTransform) {
            // Check if the session is currently holding something that we want to put up in some fields.
            $wooSessionData = trim(WooCommerce::getRequest($addressTransform));
            if (!empty($wooSessionData)) {
                $addressResponse[$addressTransform] = $wooSessionData;
            }
            if (!preg_match('/:/', $addressTransform)) {
                $return[$addressField] = isset($addressResponse[$addressTransform]) ?
                    $addressResponse[$addressTransform] : '';
            } else {
                $splitInfo = explode(':', $addressTransform);
                foreach ($splitInfo as $splitKey) {
                    $compileInfo[] = '%s';
                    $compileKeys[] = isset($addressResponse[$splitKey]) ? $addressResponse[$splitKey] : '';
                }
                $return[$addressField] = vsprintf(implode(' ', $compileInfo), $compileKeys);
            }
        }

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function purchaseReject()
    {
        $return = [
            'error' => false,
            'rejectUpdate' => false,
            'message' => '',
        ];
        $rejectType = WooCommerce::getRequest('type');

        // Presumably this is available for us to handle the order with.
        $wooOrderId = WooCommerce::getSessionValue('order_awaiting_payment');

        $transReject = [
            'fail' => __('Failed', 'trbwc'),
            'deny' => __('Denied', 'trbwc'),
        ];

        // Fallback to the standard reject reason if nothing is found.
        $transRejectMessage = isset($transReject[$rejectType]) ? $transReject[$rejectType] : $rejectType;

        if ($wooOrderId) {
            $currentOrder = new WC_Order($wooOrderId);
            $failNote = sprintf(
                __('Order was rejected with status "%s".', 'trbwc'),
                $transRejectMessage
            );
            $updateStatus = WooCommerce::setOrderStatusUpdate(
                $currentOrder,
                'failed',
                $failNote
            );
            Data::setLogNotice(
                $failNote
            );

            // Insert new row each time this section is triggered. That makes the try count traceable.
            Data::setOrderMeta($currentOrder, 'rco_rejected_once', true);
            Data::setOrderMeta(
                $currentOrder,
                'rco_rejected',
                sprintf(
                    '%s (%s)',
                    $rejectType,
                    strftime('%Y-%m-%d %H:%M:%S', time())
                ),
                true,
                true
            );

            // When status update is finished, add more information since it goes out to customer front too.
            $failNote .= ' ' .
                WordPress::applyFilters(
                    'purchaseRejectCustomerMessage',
                    __('Please contact customer service for more information.', 'trbwc')
                );

            Data::setOrderMeta($currentOrder, 'rco_reject_message', $failNote);

            $return['rejectUpdate'] = $updateStatus;
            $return['message'] = $failNote;
            return $return;
        }

        return $return;
    }
}
