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
        $alliance_order = $this->model_extension_payment_alliance->getAllianceOrder($request_data['hppOrderId']);

        if (empty($alliance_order)) {
            return false;
        }

        $order = $this->model_checkout_order->getOrder($alliance_order['order_id']);

        if (empty($order)) {
            return false;
        }

        if (!empty($request_data['hppPayType'])
            && !empty($request_data['operation'])
            && $request_data['operation']['type'] !== 'REFUND'
        ) {
            $payType = $request_data['hppPayType'];

            if ($payType === 'PURCHASE') {
                $status = $request_data['orderStatus'] ?? '';
            } elseif ($payType === 'A2A') {
                $status = $this->getA2AOperationStatus($request_data);
                if ($status === '' && isset($request_data['orderStatus'])) {
                    $status = $request_data['orderStatus'];
                }
            } else {
                $status = '';
            }

            $this->handleOrderStatusChange($status, $alliance_order, $order);
        }

        $this->model_extension_payment_alliance->saveCallBack(
            $request_data,
            $alliance_order['order_id']
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
     * @param array $allianceOrder
     * @param array $order
     * @return void
     */
    private function handleOrderStatusChange(string $status, array $allianceOrder, array $order): void
    {
        $orderId = $allianceOrder['order_id'];

        if ($status === 'SUCCESS') {
            $this->model_checkout_order->addOrderHistory(
                $orderId,
                $this->config->get('payment_alliance_paid_status')
            );
            $this->model_account_customer->addTransaction(
                $order['customer_id'],
                $orderId,
                sprintf($this->language->get('text_transaction_success'), $orderId),
                $order['total']
            );
        } elseif ($status === 'PENDING' || $status === 'REQUIRED_3DS') {
            $this->model_checkout_order->addOrderHistory(
                $orderId,
                $this->config->get('payment_alliance_pending_status')
            );
        } elseif ($status === 'FAIL') {
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

    /**
     * @param array $data
     * @return string
     */
    private function getA2AOperationStatus(array $data): string
    {
        if (!empty($data['operation'])
            && !empty($data['operation']['status'])
            && $data['operation']['type'] === 'ACCOUNT_2_ACCOUNT'
        ) {
            return $data['operation']['status'];
        }

        return '';
    }
}
