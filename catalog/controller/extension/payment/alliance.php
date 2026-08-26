<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class ControllerExtensionPaymentAlliance.
 */
class ControllerExtensionPaymentAlliance extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->load->language('extension/payment/alliance');
        $this->load->model('extension/payment/alliance');
        $this->load->model('checkout/order');
        $this->load->model('account/transaction');
        $this->load->library('AllianceEcom');
        $this->load->library('AllianceLogger');
        $this->load->library('AllianceHelper');
        $this->load->library('AllianceConfig');
        $this->logger = new AllianceLogger($registry);
        $this->ecom = new AllianceEcom($registry);
    }

    /**
     * @return mixed
     */
    public function index()
    {
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/payment/alliance', $data);
    }

    /**
     * @return bool
     */
    public function notifyCallback(): bool
    {
        $request = file_get_contents('php://input');
        $request_data = json_decode($request, true);
        $this->logger->info('Callback request', $request_data);
        $alliance_order = $this->model_extension_payment_alliance->getAllianceOrder($request_data['hppOrderId']);

        if (empty($request_data) || empty($request_data['operation'])) {
            return false;
        }

        if (empty($alliance_order)) {
            return false;
        }

        $order = $this->model_checkout_order->getOrder($alliance_order['order_id']);

        if (empty($order)) {
            return false;
        }

        $operationId = '';

        if (!empty($request_data['hppPayType'])
            && $request_data['operation']['type'] !== AllianceConfig::OPERATION_TYPE_REFUND
        ) {
            $purchaseOperation = AllianceHelper::getOperationPurchaseData($request_data);

            if (!empty($purchaseOperation)) {
                $operationId = AllianceHelper::getPurchaseOperationId($purchaseOperation);
                $purchaseStatus = AllianceHelper::getPurchaseOperationStatus($purchaseOperation);

                $this->handleOrderStatusChange(
                    $purchaseStatus,
                    (int) $alliance_order['order_id'],
                    $order,
                    $purchaseOperation['type'] === AllianceConfig::OPERATION_TYPE_COMPLETION
                );
            } else {
                $preAuthOperation = AllianceHelper::getPreAuthOperationData($request_data);

                if (!empty($preAuthOperation)) {
                    $operationId = $preAuthOperation['operationId'] ?? '';
                }
            }
        }

        $this->model_extension_payment_alliance->saveCallBack(
            $request_data,
            $alliance_order['order_id'],
            $operationId
        );

        return true;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function payment()
    {
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data = [];

        try {
            if ($this->config->get('payment_alliance_hpp_type') === AllianceEcom::HPP_PAY_TYPE_A2A
                && ($order['currency_code'] ?? '') !== 'UAH'
            ) {
                throw new \RuntimeException($this->language->get('error_a2a_non_uah'));
            }

            $jwe_token = $this->ecom->authorizeByVirtualDevice(
                $this->config->get('payment_alliance_service_code_id')
            );
            $decrypt_auth = $this->ecom->decryptResponse($jwe_token);
            $data = $this->ecom->createCardHppOrder(
                $decrypt_auth,
                $order,
                $this->config,
                $this->url,
                $order['total']
            );
            if ($this->config->get('payment_alliance_hpp_type') === 'PREAUTH') {
                $data['originalAuthorizedAmount'] = (int)round($order['total'] * 100);
            }

            if (!empty($order['currency_code'])) {
                $data['currencyCode'] = AllianceConfig::getCurrencyCode((string)$order['currency_code']);
            }

            $this->model_extension_payment_alliance->saveAllianceOrder(
                $data,
                $this->session->data['order_id']
            );
            $this->model_extension_payment_alliance->clearCart($this->session);
        } catch (Exception $e) {
            $json['error'] = $this->language->get('text_payment_something_wrong');
        }

        $json['redirect'] = $data['redirectUrl'] ?? '';

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @param string $status
     * @param int $orderId
     * @param array $order
     * @param bool $isPreauth
     * @return void
     */
    private function handleOrderStatusChange(string $status, int $orderId, array $order, bool $isPreauth = false): void
    {
        if ($status === AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS) {

            $message = !$isPreauth
                ? sprintf($this->language->get('text_transaction_success'), $orderId)
                : $this->language->get('text_completion_success');
            $statusSuccess = !$isPreauth ? $this->config->get('payment_alliance_paid_status')
                : $this->config->get('payment_alliance_completion_status');
            $this->model_checkout_order->addOrderHistory(
                $orderId,
                $statusSuccess,
                $message
            );
            $this->model_account_customer->addTransaction(
                $order['customer_id'],
                $orderId,
                sprintf($this->language->get('text_transaction_success'), $orderId),
                $order['total']
            );
        } elseif ($status === AllianceConfig::ALLIANCE_ORDER_PENDING_STATUS
            || $status === AllianceConfig::ALLIANCE_ORDER_REQUIRED_3DS_STATUS
        ) {
            $this->model_checkout_order->addOrderHistory(
                $orderId,
                $this->config->get('payment_alliance_pending_status')
            );
        } elseif ($status === AllianceConfig::ALLIANCE_ORDER_FAIL_STATUS) {
            $this->model_checkout_order->addOrderHistory(
                $orderId,
                $this->config->get('payment_alliance_error_status')
            );
            $this->model_account_customer->addTransaction(
                $order['customer_id'],
                $orderId,
                sprintf($this->language->get('text_transaction_fail'), $orderId),
                $order['total']
            );
        }
    }
}
