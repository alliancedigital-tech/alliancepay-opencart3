<?php

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

    const NOTIFICATION_URL_ROUTE = 'index.php?route=extension/payment/alliance/notifyCallback';
    const ALGORITHM = 'ECDH-ES+A256KW';

    const ENCRYPTION = 'A256GCM';

    private $dateTime;
    private $client;
    private $config;

    private $logger;

    public function __construct(Registry $registry)
    {
        $this->config = $registry->get('config');
        $loader = $registry->get('load');
        $loader->library('AllianceDatetime');
        $loader->library('AllianceLogger');
        $loader->library('AllianceGuzzleFacade');

        $composerAutoload = DIR_SYSTEM . 'library/alliancepay/vendor/autoload.php';

        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } else {
            $rootAutoload = DIR_SYSTEM . '../vendor/autoload.php';
            if (file_exists($rootAutoload)) {
                require_once $rootAutoload;
            } else {
                throw new Exception('AlliancePay: Autoloader not found.');
            }
        }

        $this->logger = new AllianceLogger($registry);
        $this->dateTime = new AllianceDatetime();
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
            $this->logger->log('authorize error: ' . $e->getMessage(), AllianceLogger::ERROR);
        }

        $contents = $response->getBody()->getContents();
        $contents = json_decode($contents, true);

        if (!isset($contents['jwe'])) {
            $this->logger->log('No token: ' . $e->getMessage(), AllianceLogger::ERROR);
        }

        return $contents['jwe'];
    }

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
            $this->logger->log('Can not encrypt JWE' . $e->getMessage(), AllianceLogger::ERROR);
        }

        return $token;
    }

    /**
     * @param array $decrypt_auth
     * @param object $session
     * @param object $config
     * @param $url
     * @param $total_price
     * @return mixed
     */
    public function createCardHppOrder(array $decrypt_auth, object $session, object $config, $url, $total_price)
    {
        try {
            $notificationUrl = $this->getNotificationUrl($url);
            $response = $this->client->request(self::CREATE_ORDER, [
                'headers' => $this->getHeaders($decrypt_auth),
                'json' => $this->collectPaymentData($session, $config, $notificationUrl, $total_price),
            ]);
        } catch (Throwable $e) {
            $this->logger->log('can\'t create order: ' . $e->getMessage(), AllianceLogger::ERROR);
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
            $this->logger->log('Can\'t refound: ' . $e->getMessage(), AllianceLogger::ERROR);
            throw new Exception('Cannot process refund! Check alliance.log for errors.');
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
                    "hppOrderId" => $hpp_order_id,
                ]
            ]);
        } catch (Throwable $e) {
            $this->logger->log('Check operation error: ' . $e->getMessage(), AllianceLogger::ERROR);
        }

        if (isset($response)) {
            $contents = $response->getBody()->getContents();

            return json_decode($contents, true) ?? [];
        }

        return [];
    }

    /**
     * @param object $session
     * @param object $config
     * @param $url
     * @param $total_price
     * @return array
     */
    private function collectPaymentData(object $session, object $config, $url, $total_price)
    {
        if (!isset($session->data['customer']['customer_id'])){
            $customer_id = 'not_authorized_' . uniqid();
        } else {
            $customer_id = 'id_' . $session->data['customer']['customer_id'];
        }

        return [
            "merchantId" => $config->get("payment_alliance_merchant_id"),
            "hppPayType" => "PURCHASE",
            "failUrl" => $config->get("payment_alliance_fail_url"),
            "successUrl" => $config->get("payment_alliance_success_url"),
            "merchantRequestId" => uniqid(),
            "statusPageType" => "STATUS_TIMER_PAGE",
            "paymentMethods" => ["CARD", "APPLE_PAY", "GOOGLE_PAY"],
            "customerData" => [
                "senderCustomerId" => $customer_id,
            ],
            "coinAmount" => (int)($total_price * 100),
            "notificationUrl" => $url
        ];
    }

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

    private function getHeaders(array $decrypt_auth = [], $contentType = 'application/json')
    {
        //$headers["Accept"] = $contentType;
        $headers["Content-Type"] = $contentType;
        $headers["x-api_version"] = "v1";
        $headers["x-request_id"] = uniqid();

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

    private function getServerPublicKey(array $decrypt_auth = [])
    {
        return $decrypt_auth['serverPublic'] ?? null;
    }
}
