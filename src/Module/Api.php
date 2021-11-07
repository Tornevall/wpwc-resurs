<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WordPress;
use ResursException;
use RuntimeException;
use function in_array;
use function is_array;

/**
 * Class Api EComPHP Translator. Also a guide for what could be made better in the real API.
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class Api
{
    /**
     * @var ResursBank $resursBank
     */
    public static $resursBank;

    /**
     * @var array $paymentMethods
     * @since 0.0.1.0
     */
    private static $paymentMethods;

    /**
     * @var array $callbacks
     * @since 0.0.1.0
     */
    private static $callbacks;

    /**
     * @var array $annuityFactors
     * @since 0.0.1.0
     */
    private static $annuityFactors;

    /**
     * @var ResursBank $ecom
     * @since 0.0.1.0
     */
    private $ecom;

    /**
     * @var array $credentials
     * @since 0.0.1.0
     */
    private $credentials = [
        'username' => '',
        'password' => '',
    ];

    /**
     * @return bool|string
     * @since 0.0.1.0
     */
    public static function getWsdlMode()
    {
        return Data::getResursOption('api_wsdl');
    }

    /**
     * Get payment properly by testing two API's before giving up.
     * @param $orderId
     * @param null $failover
     * @param null $orderInfo
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPayment($orderId, $failover = null, $orderInfo = null)
    {
        if ((bool)$failover) {
            self::getResurs()->setFlag('GET_PAYMENT_BY_REST');
        }
        try {
            if (($credentialMeta = self::getMatchingCredentials($orderInfo)) !== null) {
                $resurs = self::getEcomBySecondaryCredentials($credentialMeta, $orderId);
                $return = $resurs->getPayment($orderId);
                $return->isCurrentCredentials = false;
                $return->username = isset($credentialMeta->l) ? $credentialMeta->l : '';
                $return->environment = isset($credentialMeta->e) ? $credentialMeta->e : 1;
            } else {
                $return = self::getResurs()->getPayment($orderId);
                $return->isCurrentCredentials = true;
                $return->username = null;
                $return->environment = null;
            }
        } catch (Exception $e) {
            if (!(bool)$failover && $e->getCode() === 2) {
                self::getPaymentByRestNotice($e);
                return self::getPayment($orderId, true);
            }
            Data::setLogException($e);
            throw $e;
        }
        // Restore failovers.
        self::getResurs()->deleteFlag('GET_PAYMENT_BY_REST');

        return $return;
    }

    /**
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getResurs()
    {
        if (empty(self::$resursBank)) {
            // Instantiation.
            self::$resursBank = new self();
        }

        return self::$resursBank->getConnection();
    }

    /**
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getConnection()
    {
        if (empty($this->ecom)) {
            $this->getResolvedCredentials();
            $this->ecom = new ResursBank(
                $this->credentials['username'],
                $this->credentials['password'],
                self::getEnvironment()
            );
            $this->setWsdlCache();
            $this->setEcomConfiguration();
            $this->setEcomTimeout();
            $this->setEcomAutoDebitMethods();
        }

        return $this->ecom;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getResolvedCredentials()
    {
        $environment = Data::getResursOption('environment');

        switch ($environment) {
            case 'live':
                $getUserFrom = 'login_production';
                $getPasswordFrom = 'password_production';
                break;
            default:
                $getUserFrom = 'login';
                $getPasswordFrom = 'password';
        }

        $this->credentials['username'] = Data::getResursOption($getUserFrom);
        $this->credentials['password'] = Data::getResursOption($getPasswordFrom);

        if (empty($this->credentials['username']) || empty($this->credentials['password'])) {
            throw new RuntimeException('ECom credentials are not fully set.', 404);
        }

        return true;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getEnvironment()
    {
        return in_array(
            Data::getResursOption('environment'),
            [
                'live',
                'production',
            ],
            true
        ) ? RESURS_ENVIRONMENTS::PRODUCTION : RESURS_ENVIRONMENTS::TEST;
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setWsdlCache()
    {
        $wsdlMode = Data::getResursOption('api_wsdl');

        // If production is set, we'll go cached wsdl to speed up in default view.
        switch ($wsdlMode) {
            case 'none':
                $this->ecom->setWsdlCache(false);
                break;
            case 'both':
                $this->ecom->setWsdlCache(true);
                break;
            default:
                // Default: Is production?
                if (!Data::getTestMode()) {
                    $this->ecom->setWsdlCache(true);
                }
                break;
        }

        return $wsdlMode;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setEcomConfiguration()
    {
        $this->ecom->setUserAgent(
            sprintf(
                '%s_%s_%s',
                Data::getPluginTitle(true),
                Data::getCurrentVersion(),
                Data::getCheckoutType()
            )
        );
    }

    /**
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setEcomTimeout()
    {
        $currentTimeout = (int)WordPress::applyFilters('setCurlTimeout', 12);
        $this->ecom->setFlag('CURL_TIMEOUT', $currentTimeout > 0 ? $currentTimeout : 12);

        return $this;
    }

    /**
     * @return $this
     * @since 0.0.1.0
     */
    private function setEcomAutoDebitMethods()
    {
        $finalizationMethodTypes = (array)Data::getResursOption('order_instant_finalization_methods');

        foreach ($finalizationMethodTypes as $methodType) {
            if ($methodType !== 'default') {
                $this->ecom->setAutoDebitableType($methodType);
            }
        }
        return $this;
    }

    /**
     * Compare current credential setup with order meta credentials.
     *
     * Inspection says not all necessary throws tags are here. But they are.
     * @param $orderInfo
     * @return bool
     * @throws ResursException
     * @since 0.0.1.0
     */
    private static function getMatchingCredentials($orderInfo)
    {
        $credentialMeta = json_decode(self::getApiMeta($orderInfo), false);
        $return = null;

        $intersected = array_intersect(
            (array)$credentialMeta,
            [
                'l' => Data::getResursOption('login'),
                'p' => Data::getResursOption('password'),
                'e' => Data::getResursOption('environment'),
            ]
        );

        if (count($intersected) !== count((array)$credentialMeta)) {
            $return = $credentialMeta;
        }

        return $return;
    }

    /**
     * @param $orderData
     * @return false|string
     * @throws ResursException
     * @since 0.0.1.0
     */
    private static function getApiMeta($orderData)
    {
        $return = Data::getDecryptData(
            Data::getOrderMeta('orderapi', $orderData)
        );

        if (empty($return)) {
            // Encryption may have failed.
            $return = Data::getDecryptData(Data::getOrderMeta('orderapi', $orderData), true);
        }

        return $return;
    }

    /**
     * @param $credentialMeta
     * @param $orderId
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getEcomBySecondaryCredentials($credentialMeta, $orderId)
    {
        Data::setLogNotice(
            sprintf(
                __(
                    'Ecom request %s for %s with different credentials (%s, in environment %s).',
                    'trbwc'
                ),
                __FUNCTION__,
                $orderId,
                $credentialMeta->l,
                in_array($credentialMeta->e, ['test', 'staging']) ? 1 : 0
            )
        );
        return self::getTemporaryEcom(
            isset($credentialMeta->l) ? $credentialMeta->l : '',
            isset($credentialMeta->p) ? $credentialMeta->p : '',
            in_array($credentialMeta->e, ['test', 'staging']) ? 1 : 0
        );
    }

    /**
     * @param $username
     * @param $password
     * @param $environment
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getTemporaryEcom($username, $password, $environment)
    {
        // Creating a simple connection.
        return new ResursBank(
            $username,
            $password,
            $environment
        );
    }

    /**
     * @param Exception $e
     * @since 0.0.1.0
     */
    private static function getPaymentByRestNotice($e)
    {
        Data::setLogNotice(
            sprintf(
                __(
                    'Got exception %d in %s, will retry with REST.',
                    'trbwc'
                ),
                $e->getCode(),
                __FUNCTION__
            )
        );
    }

    /**
     * Fetch annuity factors from storage or new data.
     * @param $fromStorage
     * @return array|int|mixed|null
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getAnnuityFactors($fromStorage = true)
    {
        $return = self::$annuityFactors;
        if ($fromStorage && is_array($stored = json_decode(Data::getResursOption('annuityFactors'), false))) {
            $return = $stored;
        }

        if (!$fromStorage || empty($return)) {
            try {
                $annuityArray = [];
                // fromStorage can be called externally.
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $paymentMethods = is_array($fromStorage) ? $fromStorage : (array)self::getPaymentMethods();
                if (count($paymentMethods)) {
                    foreach ($paymentMethods as $paymentMethod) {
                        $annuityResponse = self::getResurs()->getAnnuityFactors($paymentMethod->id);
                        if (is_array($annuityResponse) || is_object($annuityResponse)) {
                            // Are we running side by side with v2.x?
                            // And is that side running ECom 1.3.41 or higher?
                            $annuityArray[$paymentMethod->id] = is_array($annuityResponse) ?
                                $annuityResponse : [$annuityResponse];
                        }
                    }
                    self::$annuityFactors = $annuityArray;
                }
            } catch (Exception $e) {
                // Reset.
                Data::setResursOption('annuityFactors', null);
                throw $e;
            }
            $return = self::$annuityFactors;
            Data::setResursOption('annuityFactors', json_encode(self::$annuityFactors));
        }

        return $return;
    }

    /**
     * Fetch stored or new payment methods.
     * @param bool $fromStorage
     * @return array|mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getPaymentMethods($fromStorage = true)
    {
        $return = self::$paymentMethods;
        if ($fromStorage) {
            $stored = json_decode(Data::getResursOption('paymentMethods'));
            if (is_array($stored)) {
                $return = $stored;
            }
        }

        if (!$fromStorage || empty($return)) {
            try {
                self::$paymentMethods = self::getResurs()->getPaymentMethods([], true);
            } catch (Exception $e) {
                // Reset.
                Data::setResursOption('paymentMethods', null);
                throw $e;
            }
            $return = self::$paymentMethods;
            Data::setResursOption('paymentMethods', json_encode(self::$paymentMethods));
        }

        return $return;
    }

    /**
     * @param bool $fromStorage
     * @return array|mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getCallbackList($fromStorage = true)
    {
        $return = self::$callbacks;
        if ($fromStorage && is_array($stored = json_decode(Data::getResursOption('callbacks'), false))) {
            $return = $stored;
        }

        if (!$fromStorage || empty($return)) {
            try {
                self::$paymentMethods = self::getResurs()->getRegisteredEventCallback(255);
            } catch (Exception $e) {
                // Reset.
                Data::setResursOption('callbacks', null);
                throw $e;
            }
            $return = self::$paymentMethods;
            Data::setResursOption('callbacks', json_encode(self::$paymentMethods));
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public function getCredentialsPresent()
    {
        $return = true;
        try {
            $this->getResolvedCredentials();
        } catch (Exception $e) {
            $return = false;
            Data::setLogException($e);
        }
        return $return;
    }

    /**
     * @param $checkoutType
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setCheckoutType($checkoutType)
    {
        $this->getConnection()->setPreferredPaymentFlowService($checkoutType);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setFraudFlags()
    {
        $this->getConnection()->setWaitForFraudControl(Data::getResursOption('waitForFraudControl'));
        $this->getConnection()->setAnnulIfFrozen(Data::getResursOption('waitForFraudControl'));
        $this->getConnection()->setFinalizeIfBooked(Data::getResursOption('waitForFraudControl'));
    }
}
