<?php

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

    public function index()
    {
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/payment/alliance', $data);
    }

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

        if ($request_data['orderStatus'] === 'SUCCESS') {
            $this->model_checkout_order->addOrderHistory(
                $alliance_order['order_id'],
                $this->config->get('payment_alliance_paid_status')
            );
            $this->model_account_customer->addTransaction(
                $order['customer_id'],
                $alliance_order['order_id'],
                sprintf($this->language->get('text_transaction_success'), $alliance_order['order_id']),
                $order['total']
            );
        }

        if ($request_data['orderStatus'] === 'PENDING' || $request_data['orderStatus'] === 'REQUIRED_3DS') {
            $this->model_checkout_order->addOrderHistory(
                $alliance_order['order_id'],
                $this->config->get('payment_alliance_pending_status')
            );
        }

        if ($request_data['orderStatus'] === 'FAIL') {
            $this->model_checkout_order->addOrderHistory(
                $alliance_order['order_id'],
                $this->config->get('payment_alliance_error_status')
            );
            $this->model_account_customer->addTransaction(
                $order['customer_id'],
                $alliance_order['order_id'],
                sprintf($this->language->get('text_transaction_fail'), $alliance_order['order_id']),
                $order['total']
            );
        }

        $this->model_extension_payment_alliance->saveCallBack(
            $request_data,
            $alliance_order['order_id']
        );

        return true;

    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function payment()
    {
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        try {
            $jwe_token = $this->ecom->authorizeByVirtualDevice(
                $this->config->get('payment_alliance_service_code_id')
            );
            $decrypt_auth = $this->ecom->decryptResponse($jwe_token);
            $data = $this->ecom->createCardHppOrder(
                $decrypt_auth,
                $this->session,
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
}
