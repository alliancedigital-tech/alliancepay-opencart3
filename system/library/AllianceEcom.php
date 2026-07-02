<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleJWT\JWE;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;

/**
 * Class AllianceEcom.
 */
class AllianceEcom
{
    const AUTHORIZE_VIRTUAL_DEVICE = 'api-gateway/authorize_virtual_device';
    const CREATE_ORDER = 'ecom/execute_request/hpp/v1/create-order';
    const CREATE_REFUND = 'ecom/execute_request/payments/v3/refund';

    const CHECK_OPERATIONS = 'ecom/execute_request/hpp/v1/operations';

    const NOTIFICATION_URL_ROUTE = 'extension/payment/alliance/notifyCallback';
    const ALGORITHM = 'ECDH-ES+A256KW';
    const ENCRYPTION = 'A256GCM';

    const HPP_PAY_TYPE_PURCHASE = 'PURCHASE';
    const HPP_PAY_TYPE_A2A = 'A2A';
    const A2A_DIRECT_TYPE = 'BANK_LINK';
    const A2A_PRIORITY_BANK_CODE = 'ALL_BANKS';
    const A2A_OPERATION_TYPE = 'ACCOUNT_2_ACCOUNT';

    private $dateTime;
    private $client;
    private $config;

    private $logger;

    private $validationRules = [
        'senderCustomerId' => [
            'type'     => 'string',
            'max_len'  => 255,
            'required' => true,
        ],
        'senderFirstName' => [
            'type'           => 'string',
            'max_len'        => 30,
            'required'       => false,
            'no_only_digits' => true,
            'pattern'        => '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
            'stop_words'     => ['NULL', '3D SECURE', 'SURNAME', 'CARDHOLDER', 'UNKNOWN']
        ],
        'senderLastName' => [
            'type'           => 'string',
            'max_len'        => 30,
            'required'       => false,
            'no_only_digits' => true,
            'pattern'        => '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u'
        ],
        'senderEmail' => [
            'type'     => 'string',
            'max_len'  => 256,
            'required' => false
        ],
        'senderCountry' => [
            'type'     => 'string',
            'max_len'  => 3,
            'required' => false
        ],
        'senderRegion' => [
            'type'     => 'string',
            'max_len'  => 255,
            'required' => false
        ],
        'senderCity' => [
            'type'     => 'string',
            'max_len'  => 25,
            'required' => false
        ],
        'senderStreet' => [
            'type'     => 'string',
            'max_len'  => 35,
            'required' => false
        ],
        'senderAdditionalAddress' => [
            'type'     => 'string',
            'max_len'  => 255,
            'required' => false
        ],
        'senderIp' => [
            'type'     => 'string',
            'max_len'  => 50,
            'required' => false
        ],
        'senderPhone' => [
            'type'     => 'numeric_string',
            'max_len'  => 20,
            'required' => false
        ],
        'senderZipCode' => [
            'type'     => 'string',
            'max_len'  => 50,
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
        $clientFacade = $registry->get('AllianceGuzzleFacade');
        $clientFacade->init($this->config->get('payment_alliance_url'));
        $this->client = $clientFacade;
    }

    /**
     * @param string $payment_alliance_service_code_id
     * @return mixed
     */
    public function authorizeByVirtualDevice(string $payment_alliance_service_code_id)
    {
        try {
            $response = $this->client->request(self::AUTHORIZE_VIRTUAL_DEVICE, [
                'headers' => $this->getHeaders(),
                'json' => [
                    'serviceCode' =>  $payment_alliance_service_code_id,
                ]
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'authorize error: ' . $e->getMessage());
            throw $e;
        }

        $contents = $response->getBody()->getContents();
        $contents = json_decode($contents, true);

        if (!isset($contents['jwe'])) {
            $this->logger->log(AllianceLogger::ERROR, 'No token received from authorize endpoint.');
        }

        return $contents['jwe'];
    }

    /**
     * @param string $jwe_token
     * @return array
     */
    public function decryptResponse(string $jwe_token)
    {
        $set = new KeySet();
        $keyFactory = new KeyFactory();

        $json = [
            "kty" => "EC",
            "d" => $this->config->get("payment_alliance_jwt_param_d"),
            "use" => "enc",
            "crv" => "P-384",
            "x" => $this->config->get("payment_alliance_jwt_param_x"),
            "y" => $this->config->get("payment_alliance_jwt_param_y"),
            "alg" => self::ALGORITHM,
        ];

        $keys = $keyFactory->create($json, null, null, self::ALGORITHM);
        $set->add($keys);

        $jwe_decrypted = JWE::decrypt($jwe_token, $set, self::ALGORITHM);

        return (array) json_decode($jwe_decrypted->getPlaintext());
    }

    /**
     * @param array $publicServerKey
     * @param string $payload
     * @return string
     * @throws Exception
     */
    public function encryptRequest(array $publicServerKey, string $payload)
    {
        $headers = [
            'alg' => self::ALGORITHM,
            'enc' => self::ENCRYPTION
        ];

        $key = KeyFactory::create($publicServerKey, null, null, self::ALGORITHM);
        $keySet = new KeySet();
        $keySet->add($key);
        $jwe = new JWE($headers, $payload);

        try {
            $token = $jwe->encrypt($keySet);
        } catch (Exception $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can not encrypt JWE: ' . $e->getMessage());
            throw $e;
        }

        return $token;
    }

    /**
     * @param array $decrypt_auth
     * @param array $orderInfo
     * @param object $config
     * @param $url
     * @param $total_price
     * @return mixed
     * @throws Throwable
     */
    public function createCardHppOrder(array $decrypt_auth, array $orderInfo, object $config, $url, $total_price)
    {
        try {
            $notificationUrl = $this->getNotificationUrl($url);
            $response = $this->client->request(self::CREATE_ORDER, [
                'headers' => $this->getHeaders($decrypt_auth),
                'json' => $this->collectPaymentData($orderInfo, $config, $notificationUrl, $total_price),
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t create order: ' . $e->getMessage());
            throw $e;
        }

        $contents = $response->getBody()->getContents();

        return json_decode($contents, true);
    }

    /**
     * @param array $decrypt_auth
     * @param $url
     * @param $callBackData
     * @return mixed
     * @throws Exception
     */
    public function proceedRefund(array $decrypt_auth, $url, $callBackData)
    {
        $refundResult = [];
        try {
            $notificationUrl = $this->getNotificationUrl($url);
            $serverPublicKey = json_decode(json_encode($this->getServerPublicKey($decrypt_auth)), true);
            $headers = $this->getHeaders($decrypt_auth, 'text/plain');

            if (!empty($serverPublicKey)) {
                $payload = json_encode($this->collectRefundData(
                    $notificationUrl,
                    $callBackData
                ), JSON_PRETTY_PRINT);
                $refundEncryptBody = $this->encryptRequest(
                    $serverPublicKey,
                    $payload
                );
                $response = $this->client->request(self::CREATE_REFUND, [
                    'headers' => $headers,
                    'body' => $refundEncryptBody,
                ]);

                $contents = json_decode($response->getBody()->getContents(), true);

                if (isset($contents['jwe'])) {
                    $refundResult = $this->decryptResponse($contents['jwe']);
                }
            }
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Can\'t proceed refund: ' . $e->getMessage());
            throw new \RuntimeException('Cannot process refund. Check alliance.log for details.');
        }

        return $refundResult;
    }

    /**
     * @param array $decrypt_auth
     * @param string $hpp_order_id
     * @return array|mixed
     */
    public function checkOperationStatus(array $decrypt_auth, string $hpp_order_id)
    {
        try {
            $response = $this->client->request(self::CHECK_OPERATIONS, [
                'headers' => $this->getHeaders($decrypt_auth),
                'json' => [
                    'hppOrderId' => $hpp_order_id,
                ]
            ]);
        } catch (Throwable $e) {
            $this->logger->log(AllianceLogger::ERROR, 'Check operation error: ' . $e->getMessage());
        }

        if (isset($response)) {
            $contents = $response->getBody()->getContents();

            return json_decode($contents, true) ?? [];
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

        $paymentData = [
            'merchantId' => $config->get('payment_alliance_merchant_id'),
            'hppPayType' => $config->get('payment_alliance_hpp_type') ?? self::HPP_PAY_TYPE_PURCHASE,
            'failUrl' => $config->get('payment_alliance_fail_url'),
            'successUrl' => $config->get('payment_alliance_success_url'),
            'merchantRequestId' => uniqid(),
            'statusPageType' => 'STATUS_TIMER_PAGE',
            'paymentMethods' => ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'],
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

        return $paymentData;
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
     * @param array $decrypt_auth
     * @param $contentType
     * @return array
     */
    private function getHeaders(array $decrypt_auth = [], $contentType = 'application/json')
    {
        $headers['Content-Type'] = $contentType;
        $headers['x-api_version'] = 'v1';
        $headers['x-request_id'] = uniqid();

        if ($decrypt_auth) {
            $headers['x-device_id'] = $decrypt_auth['deviceId'];
        }

        if ($decrypt_auth) {
            $headers['x-refresh_token'] = $decrypt_auth['refreshToken'];
        }

        return $headers;
    }

    /**
     * @param Url $url
     * @return string
     */
    private function getNotificationUrl(Url $url)
    {
        return $url->link(self::NOTIFICATION_URL_ROUTE);
    }

    /**
     * @param array $decrypt_auth
     * @return mixed|null
     */
    private function getServerPublicKey(array $decrypt_auth = [])
    {
        return $decrypt_auth['serverPublic'] ?? null;
    }

    /**
     * @param $data
     * @param $rules
     * @return array
     */
    public function validateCustomerData($data, $rules) {
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
