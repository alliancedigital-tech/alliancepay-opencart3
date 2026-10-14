<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\ECKey;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\InvalidTokenException;

/**
 * Class AllianceEcom.
 */
class AllianceEcom
{
    const CREATE_ORDER = 'ecom/jws/payments/hpp/create_order_v1';

    const CREATE_REFUND = 'ecom/jws/payments/refund_v1';

    const CHECK_OPERATIONS = 'ecom/jws/payments/hpp/operations_v1';

    const COMPLETE_PAYMENT = 'ecom/jws/payments/preauth/completion_v1';

    const GET_PUBLIC_KEY = 'ecom/keys/public_key/get_v1';

    const NOTIFICATION_URL_ROUTE = 'extension/payment/alliance/notifyCallback';

    const HPP_PAY_TYPE_PURCHASE = 'PURCHASE';

    const HPP_PAY_TYPE_A2A = 'A2A';

    const HPP_PAY_TYPE_PREAUTH = 'PREAUTH';

    const A2A_DIRECT_TYPE = 'BANK_LINK';

    const A2A_PRIORITY_BANK_CODE = 'ALL_BANKS';

    const A2A_OPERATION_TYPE = 'ACCOUNT_2_ACCOUNT';

    const BANK_PUBLIC_KEY_CACHE_TTL = 3600;

    const PLAN_CREATE = 'ecom/jws/scheduled_payments/plan/create_v1';

    const PLAN_UPDATE = 'ecom/jws/scheduled_payments/plan/update_v1';

    const PLAN_STATUS_UPDATE = 'ecom/jws/scheduled_payments/plan/status_update_v1';

    const PLAN_VERSION_UPDATE = 'ecom/jws/scheduled_payments/plan/version/update_v1';

    const PLAN_VERSION_DELETE = 'ecom/jws/scheduled_payments/version/delete_v1';

    const SUBSCRIPTION_ORDER_CREATE = 'ecom/jws/scheduled_payments/subscription-orders/create_v1';

    const SUBSCRIPTION_ORDER_CANCEL = 'ecom/jws/scheduled_payments/subscription-orders/cancel_v1';

    const SUBSCRIPTION_PAYMENT_RECURRING = 'ecom/jws/scheduled_payments/payments/recurring_subscription_v1';

    const NOTIFICATION_URL_ROUTE_SUBSCRIPTION = 'extension/payment/alliance_subscription/notifyCallback';

    const SUBSCRIPTION_STATUS_PAGE_TYPE = 'STATUS_TIMER_PAGE';

    const SUBSCRIPTION_CONTENT_TYPE = 'application/jwt';

    private $dateTime;

    private $client;

    private $config;

    private $logger;

    private $cache;

    private $validationRules = [
        'senderCustomerId' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => true,
        ],
        'senderFirstName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' => '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
            'stop_words' => ['NULL', '3D SECURE', 'SURNAME', 'CARDHOLDER', 'UNKNOWN']
        ],
        'senderLastName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' => '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u'
        ],
        'senderEmail' => [
            'type' => 'string',
            'max_len' => 256,
            'required' => false
        ],
        'senderCountry' => [
            'type' => 'string',
            'max_len' => 3,
            'required' => false
        ],
        'senderRegion' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false
        ],
        'senderCity' => [
            'type' => 'string',
            'max_len' => 25,
            'required' => false
        ],
        'senderStreet' => [
            'type' => 'string',
            'max_len' => 35,
            'required' => false
        ],
        'senderAdditionalAddress' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false
        ],
        'senderIp' => [
            'type' => 'string',
            'max_len' => 50,
            'required' => false
        ],
        'senderPhone' => [
            'type' => 'numeric_string',
            'max_len' => 20,
            'required' => false
        ],
        'senderZipCode' => [
            'type' => 'string',
            'max_len' => 50,
            'required' => false
        ],
    ];

    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->config = $registry->get('config');
        $loader = $registry->get('load');
        $loader->library('AllianceDatetime');
        $loader->library('AllianceLogger');
        $loader->library('AllianceGuzzleFacade');
        $loader->library('AllianceCountryCodeProvider');
        $loader->library('AllianceConfig');
        $loader->library('AllianceRequestIdentification');

        $autoloadPaths = [
            DIR_SYSTEM . 'library/alliancepay/vendor/autoload.php',
            DIR_SYSTEM . '../vendor/autoload.php',
        ];

        $autoloadLoaded = false;
        foreach ($autoloadPaths as $autoloadPath) {
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
                $autoloadLoaded = true;
                break;
            }
        }

        if (!$autoloadLoaded) {
            throw new \RuntimeException(
                'AlliancePay: Composer autoloader not found. '
                . 'Checked paths: ' . implode(', ', $autoloadPaths)
            );
        }

        $this->logger = new AllianceLogger($registry);
        $this->dateTime = new AllianceDatetime();
        $this->countryCodeProvider = new AllianceCountryCodeProvider();
        $this->cache = $registry->get('cache');
        $clientFacade = $registry->get('AllianceGuzzleFacade');
        $clientFacade->init($this->config->get('payment_alliance_url'));
        $this->client = $clientFacade;
    }

    /**
     * @param string $endpoint
     * @param array $body
     * @return string
     * @throws Throwable
     */
    private function signJwsRequest(string $endpoint, array $body): string
    {
        $privateKeyPem = $this->config->get('payment_alliance_jws_private_key');
        $kid = $this->config->get('payment_alliance_jws_key_id');

        if (empty($privateKeyPem)) {
            throw new \RuntimeException('JWS Private Key is not configured.');
        }

        if (empty($kid)) {
            throw new \RuntimeException('JWS Key ID is not configured.');
        }

        try {
            $key = KeyFactory::create($privateKeyPem, 'pem');
            $key->setKeyId($kid);
            $keySet = new KeySet();
            $keySet->add($key);

            $jwt = new JWT(
                [
                    'alg' => 'RS256',
                    'ts' => (string)(int)(microtime(true) * 1000),
                    'targetUrl' => '/' . ltrim($endpoint, '/'),
                    'kid' => $kid,
                    'jti' => AllianceRequestIdentification::generateRequestId(),
                ],
                $body
            );

            return $jwt->encode($keySet, null, false);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'JWS signing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array
     */
    private function getJwsRequestHeaders(): array
    {
        return [
            'Content-Type' => 'application/jws',
            'Accept' => 'application/json',
            'x-api_version' => 'v1',
            'x-request_id' => AllianceRequestIdentification::generateRequestId(),
        ];
    }

    private function getJwsSubscriptionRequestHeaders(): array
    {
        return [
            'Content-Type' => self::SUBSCRIPTION_CONTENT_TYPE,
            'Accept' => 'application/json',
            'x-request_id' => AllianceRequestIdentification::generateRequestId(),
        ];
    }

    /**
     * @param string $jws
     * @return array
     */
    public function verifyJwsToken(string $jws): array
    {
        try {
            $deserialised = JWT::deserialise($jws);
            $headers = $deserialised['signatures'][0]['headers'];
            $kid = $headers['kid'] ?? null;
            $alg = $headers['alg'] ?? null;
        } catch (Throwable $e) {
            throw new \RuntimeException('JWS parse error: ' . $e->getMessage());
        }

        if (empty($kid) || empty($alg)) {
            throw new \RuntimeException('JWS: missing kid or alg in header.');
        }

        $jwk = $this->fetchBankPublicKey($kid);

        $key = new ECKey($jwk, 'php');
        $key->setKeyId($kid);
        $keySet = new KeySet();
        $keySet->add($key);

        try {
            $jwt = JWT::decode($jws, $keySet, $alg);
        } catch (InvalidTokenException $e) {
            throw new \RuntimeException('JWS verification failed: ' . $e->getMessage());
        }

        return $jwt->getClaims();
    }

    /**
     * @param string $kid
     * @return array
     */
    private function fetchBankPublicKey(string $kid): array
    {
        $cacheKey = 'alb_bank_pubkey_' . md5($kid);
        $cached = $this->cache ? $this->cache->get($cacheKey) : null;

        if ($cached) {
            return json_decode($cached, true);
        }

        $response = $this->client->request(self::GET_PUBLIC_KEY, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => ['kid' => $kid],
        ]);
        $jwk = json_decode($response->getBody()->getContents(), true);

        if (empty($jwk['kty']) || empty($jwk['crv']) || empty($jwk['x']) || empty($jwk['y'])) {
            throw new \RuntimeException('Invalid public key response from bank for kid=' . $kid);
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($jwk), self::BANK_PUBLIC_KEY_CACHE_TTL);
        }

        return $jwk;
    }

    /**
     * @param $response
     * @return array
     */
    private function parseResponse($response): array
    {
        $body = trim($response->getBody()->getContents());
        $dotCount = substr_count($body, '.');
        $isToken = $body !== '' && strpos($body, ' ') === false;

        if ($isToken && $dotCount === 2) {
            return $this->verifyJwsToken($body);
        }

        return json_decode($body, true) ?? [];
    }

    /**
     * @param array $orderInfo
     * @param object $config
     * @param $url
     * @param $total_price
     * @return mixed
     * @throws Throwable
     */
    public function createCardHppOrder(array $orderInfo, object $config, $url, $total_price)
    {
        try {
            $notificationUrl = $this->getNotificationUrl($url);
            $body = $this->collectPaymentData($orderInfo, $config, $notificationUrl, $total_price);
            $response = $this->client->request(self::CREATE_ORDER, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(self::CREATE_ORDER, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t create order: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param $url
     * @param $callBackData
     * @return mixed
     * @throws Exception
     */
    public function proceedRefund($url, $callBackData)
    {
        $refundResult = [];
        try {
            $notificationUrl = $this->getNotificationUrl($url);
            $body = $this->collectRefundData($notificationUrl, $callBackData);
            $response = $this->client->request(self::CREATE_REFUND, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(self::CREATE_REFUND, $body),
            ]);

            $refundResult = $this->parseResponse($response);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t proceed refund: ' . $e->getMessage());
            throw new \RuntimeException('Cannot process refund. Check alliance.log for details.');
        }

        return $refundResult;
    }

    /**
     * @param string $hpp_order_id
     * @return array|mixed
     */
    public function checkOperationStatus(string $hpp_order_id)
    {
        try {
            $body = ['hppOrderId' => $hpp_order_id];
            $response = $this->client->request(self::CHECK_OPERATIONS, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(self::CHECK_OPERATIONS, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Check operation error: ' . $e->getMessage());
        }

        if (isset($response)) {
            return $this->parseResponse($response);
        }

        return [];
    }

    /**
     * @param array $orderInfo
     * @param object $config
     * @param $url
     * @param $total_price
     * @return array
     */
    private function collectPaymentData(array $orderInfo, object $config, $url, $total_price)
    {
        $customerData = [];

        if ($orderInfo) {
            $customerData = [
                'senderFirstName' => $orderInfo['firstname'] ?? '',
                'senderLastName' => $orderInfo['lastname'] ?? '',
                'senderEmail' => $orderInfo['email'] ?? '',
                'senderCountry' => !empty($orderInfo['payment_iso_code_2'])
                    ? $this->countryCodeProvider->getCountryNumericCodeByAlpha2($orderInfo['payment_iso_code_2']) : '',
                'senderRegion' => $orderInfo['payment_zone'] ?? '',
                'senderCity' => $orderInfo['payment_city'] ?? '',
                'senderStreet' => $orderInfo['payment_address_1'] ?? '',
                'senderAdditionalAddress' => $orderInfo['payment_address_2'] ?? '',
                'senderIp' => $orderInfo['ip'] ?? '',
                'senderPhone' => $orderInfo['telephone'] ?? '',
                'senderZipCode' => $orderInfo['payment_postcode'] ?? '',
            ];
        }

        if (!empty($orderInfo['customer_id'])) {
            $customerData['senderCustomerId'] = $orderInfo['customer_id'];
        } else {
            $customerData['senderCustomerId'] = 'not_authorized_' . uniqid();
        }

        $hppMethods = json_decode((string)$config->get('payment_alliance_hpp_payment_methods'), true);

        $paymentData = [
            'merchantId' => $config->get('payment_alliance_merchant_id'),
            'hppPayType' => $config->get('payment_alliance_hpp_type') ?? self::HPP_PAY_TYPE_PURCHASE,
            'failUrl' => $config->get('payment_alliance_fail_url'),
            'successUrl' => $config->get('payment_alliance_success_url'),
            'merchantRequestId' => uniqid(),
            'statusPageType' => 'STATUS_TIMER_PAGE',
            'paymentMethods' => (is_array($hppMethods) && $hppMethods) ? $hppMethods : ['CARD'],
            'customerData' => $this->validateCustomerData($customerData, $this->validationRules),
            'coinAmount' => (int)($total_price * 100),
            'notificationUrl' => $url
        ];

        if ($config->get('payment_alliance_hpp_type') === self::HPP_PAY_TYPE_A2A) {
            $paymentData['hppPaymentType'] = self::HPP_PAY_TYPE_A2A;
            $paymentData['directType'] = self::A2A_DIRECT_TYPE;
            $paymentData['priorityBankCode'] = self::A2A_PRIORITY_BANK_CODE;
            $paymentData['merchantComment'] = 'Payment for order #' . $orderInfo['order_id'];
        }

        if ($config->get('payment_alliance_hpp_type') === self::HPP_PAY_TYPE_PREAUTH) {
            $paymentData['preAuthExpDate'] = $this->calculatePreAuthExpDate(
                $config->get('payment_alliance_preauth_exp_date')
            );
        }

        $hppPayType = $config->get('payment_alliance_hpp_type') ?? self::HPP_PAY_TYPE_PURCHASE;
        if (in_array($hppPayType, [self::HPP_PAY_TYPE_PURCHASE, self::HPP_PAY_TYPE_PREAUTH], true)) {
            $currencyAlpha = !empty($orderInfo['currency_code']) ? $orderInfo['currency_code'] : 'UAH';
            try {
                $paymentData['currencyCode'] = AllianceConfig::getCurrencyCode($currencyAlpha);
            } catch (\InvalidArgumentException $e) {
                $this->logger->log(
                    AllianceLogger::ERROR,
                    'Unsupported currency "' . $currencyAlpha . '" for order #'
                    . ($orderInfo['order_id'] ?? '') . ': ' . $e->getMessage()
                    . ' Falling back to UAH (980).'
                );
                $paymentData['currencyCode'] = AllianceConfig::CURRENCY_CODE_MAP['UAH'];
            }
        }

        return $paymentData;
    }

    /**
     * @param array $allianceOrder
     * @param $url
     * @return bool
     * @throws Throwable
     */
    public function executeCompletion(array $allianceOrder, $url): bool
    {
        $notificationUrl = $this->getNotificationUrl($url);
        $body = [
            'merchantRequestId' => uniqid(),
            'merchantId' => $allianceOrder['merchant_id'],
            'originalOperationId' => $allianceOrder['operation_id'],
            'coinAmount' => (string)$allianceOrder['coin_amount'],
            'date' => $this->dateTime::getSiteDateTime($this->config),
            'notificationUrl' => $notificationUrl,
        ];

        try {
            $response = $this->client->request(self::COMPLETE_PAYMENT, [
                'headers' => $this->getJwsRequestHeaders(),
                'body' => $this->signJwsRequest(self::COMPLETE_PAYMENT, $body),
            ]);

            $completionResult = $this->parseResponse($response);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t execute completion: ' . $e->getMessage());
            throw $e;
        }

        return !empty($completionResult['status'])
            && $completionResult['status'] === AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS;
    }

    /**
     * @param string $url
     * @param array $callBackData
     * @return array
     */
    private function collectRefundData(string $url, array $callBackData)
    {
        return [
            'merchantRequestId' => (string)$callBackData['merchant_request_id'] ?? '',
            'operationId' => (string)$callBackData['operation_id'] ?? '',
            'merchantId' => (string)$callBackData['merchant_id'] ?? '',
            'coinAmount' => (string)$callBackData['coin_amount'] ?? '',
            'notificationUrl' => $url,
            'date' => $this->dateTime::getSiteDateTime($this->config)
        ];
    }

    /**
     * @param string $setting
     * @return string
     */
    private function calculatePreAuthExpDate(string $setting): string
    {
        $map = [
            '2h' => '+2 hours',
            '4h' => '+4 hours',
            '6h' => '+6 hours',
            '12h' => '+12 hours',
            '1d' => '+1 day',
            '2d' => '+2 days',
            '7d' => '+7 days',
            '14d' => '+14 days',
            '28d' => '+28 days',
        ];
        $modifier = $map[$setting] ?? '+2 hours';
        $timezone = $this->config->get('config_timezone') ?: 'UTC';
        $dt = new \DateTime('now', new \DateTimeZone($timezone));
        $dt->modify('+30 sec');
        $dt->modify($modifier);

        return preg_replace('/(\.\d{2})\d/', '$1', $dt->format('Y-m-d H:i:s.vP'));
    }

    /**
     * @param Url $url
     * @param string $route Never hardcode the webhook path - always build it via this route builder.
     */
    private function getNotificationUrl(Url $url, string $route = self::NOTIFICATION_URL_ROUTE)
    {
        return $url->link($route);
    }

    /**
     * @param array $planData
     */
    public function createSubscriptionPlan(array $planData): array
    {
        try {
            $response = $this->client->request(self::PLAN_CREATE, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::PLAN_CREATE, $planData),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t create subscription plan: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $planId
     * @param string $versionId
     */
    public function setInfiniteSchedule(string $planId, string $versionId): array
    {
        $body = [
            'planId' => $planId,
            'versionId' => $versionId,
            'infiniteVersion' => true,
        ];

        try {
            $response = $this->client->request(self::PLAN_VERSION_UPDATE, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::PLAN_VERSION_UPDATE, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t set infinite schedule: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param array $planData
     */
    public function updateSubscriptionPlan(array $planData): array
    {
        try {
            $response = $this->client->request(self::PLAN_UPDATE, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::PLAN_UPDATE, $planData),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t update subscription plan: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $planId
     * @param string $status
     */
    public function updatePlanStatus(string $planId, string $status): array
    {
        $body = [
            'planId' => $planId,
            'status' => $status,
        ];

        try {
            $response = $this->client->request(self::PLAN_STATUS_UPDATE, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::PLAN_STATUS_UPDATE, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t update plan status: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $planId
     * @param string $versionId
     */
    public function deletePlanVersion(string $planId, string $versionId): array
    {
        $body = [
            'planId' => $planId,
            'versionId' => $versionId,
        ];

        try {
            $response = $this->client->request(self::PLAN_VERSION_DELETE, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::PLAN_VERSION_DELETE, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t delete plan version: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param array $body
     */
    public function createSubscriptionOrder(array $body): array
    {
        try {
            $response = $this->client->request(self::SUBSCRIPTION_ORDER_CREATE, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::SUBSCRIPTION_ORDER_CREATE, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t create subscription order: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $hppOrderId
     * @param string $merchantId
     * @param string $reason
     */
    public function cancelSubscriptionOrder(string $hppOrderId, string $merchantId, string $reason = ''): array
    {
        $body = [
            'hppOrderId' => $hppOrderId,
            'merchantId' => $merchantId,
        ];

        if ($reason !== '') {
            $body['reason'] = $reason;
        }

        try {
            $response = $this->client->request(self::SUBSCRIPTION_ORDER_CANCEL, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::SUBSCRIPTION_ORDER_CANCEL, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t cancel subscription order: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $hppOrderId
     * @param string $scheduledPaymentId
     * @param string $merchantId
     */
    public function triggerRecurringPayment(string $hppOrderId, string $scheduledPaymentId, string $merchantId): array
    {
        $body = [
            'hppOrderId' => $hppOrderId,
            'scheduledPaymentId' => $scheduledPaymentId,
            'merchantId' => $merchantId,
        ];

        try {
            $response = $this->client->request(self::SUBSCRIPTION_PAYMENT_RECURRING, [
                'headers' => $this->getJwsSubscriptionRequestHeaders(),
                'body' => $this->signJwsRequest(self::SUBSCRIPTION_PAYMENT_RECURRING, $body),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t trigger recurring payment: ' . $e->getMessage());
            throw $e;
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $hppOrderId
     */
    public function checkSubscriptionOperationStatus(string $hppOrderId): array
    {
        $this->logger->log(
            AllianceLogger::WARNING,
            'Reusing hpp/operations_v1 for subscription order ' . $hppOrderId . ' - no dedicated '
            . 'scheduled_payments status endpoint documented in spec §10.2; confirm with bank.'
        );

        return $this->checkOperationStatus($hppOrderId);
    }

    /**
     * @param $data
     * @param $rules
     * @return array
     */
    public function validateCustomerData($data, $rules)
    {
        $validated = [];

        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? trim($data[$field]) : '';
            $is_valid = true;
            $error_reason = '';

            if ($rule['required'] && $value === '') {
                $is_valid = false;
                $error_reason = 'поле є обов’язковим, але воно порожнє';
            }

            if ($value !== '' && $is_valid) {
                if (isset($rule['type']) && $rule['type'] === 'numeric_string') {
                    $value = preg_replace('/\D/', '', $value);
                }

                if (isset($rule['no_only_digits']) && preg_match('/^\d+$/', $value)) {
                    $is_valid = false;
                    $error_reason = 'значення не може містити виключно цифри';
                }

                if ($is_valid && isset($rule['pattern'])) {
                    if (!preg_match($rule['pattern'], $value)) {
                        $is_valid = false;
                        $error_reason = 'значення містить заборонені символи або невірний формат (пробіли/дефіси по краях)';
                    }
                }

                if ($is_valid && isset($rule['stop_words'])) {
                    if (in_array(mb_strtoupper($value), $rule['stop_words'])) {
                        $is_valid = false;
                        $error_reason = 'виявлено заборонене стоп-слово: ' . $value;
                    }
                }

                if ($is_valid && isset($rule['max_len'])) {
                    if (mb_strlen($value) > $rule['max_len']) {
                        $is_valid = false;
                        $error_reason = 'перевищено ліміт символів (max: ' . $rule['max_len'] . ')';
                    }
                }
            }

            if ($is_valid && $value !== '') {
                $validated[$field] = $value;
            } else {
                if ($value !== '' || $rule['required']) {
                    $this->log->write(sprintf(
                        'Payment Aggregation Error: Field [%s] removed. Reason: %s. Value was: [%s]',
                        $field,
                        $error_reason,
                        $value
                    ));
                }
            }
        }

        return $validated;
    }
}
