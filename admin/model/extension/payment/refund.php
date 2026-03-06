<?php

use GuzzleHttp\Exception\GuzzleException;

class ModelExtensionPaymentRefund extends Model
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->library('AllianceConfig');
        $this->load->library('AllianceEcom');
        $this->load->library('AllianceLogger');
        $this->load->model('extension/payment/alliance');
        $this->load->model('extension/payment/history');
        $this->load->model('sale/return');
        $this->load->model('sale/order');
        $this->load->language('extension/payment/alliance');
        $this->ecom = new AllianceEcom($registry);
        $this->logger = new AllianceLogger($registry);
    }

    /**
     * @param int $orderId
     * @param $isFullReturn
     * @param $productIds
     * @return array|mixed
     * @throws GuzzleException
     */
    public function refundOrder(int $orderId, $isFullReturn = true, $productIds = [], $refundShipping = 0)
    {
        $data = [];
        if (!empty($orderId)) {
            $allianceOrderCallBack = $this->model_extension_payment_alliance
                ->getAllianceOrderCallback($orderId);
            $allianceOrder = $this->model_extension_payment_alliance->getAllianceOrder($orderId);
            $refundData = [];
            $purchaseOperation = [];

            try {

                if (empty($allianceOrderCallBack)) {
                    $jwe_token = $this->ecom->authorizeByVirtualDevice(
                        $this->config->get('payment_alliance_service_code_id')
                    );
                    $decrypt_auth = $this->ecom->decryptResponse($jwe_token);
                    $eComResponse = $this->ecom->checkOperationStatus(
                        $decrypt_auth,
                        $allianceOrder['hpp_order_id']
                    );
                    $purchaseOperation = $this->getOperationPurchaseData($eComResponse);
                } else {
                    $purchaseOperation = $this->getOperationPurchaseData($allianceOrderCallBack);
                }
                $coinAmount = $isFullReturn
                    ? $purchaseOperation['coinAmount']
                    : $this->getPartialRefundCoinAmount($orderId, $productIds, $refundShipping);
                $refundData = $this->prepareRefundData($purchaseOperation, $coinAmount);

                $jwe_token = $this->ecom->authorizeByVirtualDevice(
                    $this->config->get(
                        'payment_alliance_service_code_id'
                    )
                );
                $data = [];
                $decrypt_auth = $this->ecom->decryptResponse($jwe_token);
                $data = $this->ecom->proceedRefund(
                    $decrypt_auth,
                    $this->url,
                    $refundData
                );

                if (!empty($data['msgType']) && $data['msgType'] == 'ERROR') {
                    $this->logger->log(
                        'Refound error: ' . $data['msgText'] ?? '',
                        LogLevel::ERROR
                    );
                }

                $this->model_extension_payment_history->updateRefundHistory(
                    $data,
                    $orderId
                );
                $totalAmountRefunds = $this->model_extension_payment_history->getRefundOperationsTotal((int) $orderId);
                if ($isFullReturn || $allianceOrder['coin_amount'] == $totalAmountRefunds) {
                    $this->model_extension_payment_history->updateOrderHistoryAndStatus(
                        (int) $orderId,
                        (int) $this->config->get('payment_alliance_refunded_status'),
                        $this->language->get('text_refund_history_comment')
                    );
                }
                $this->proceedReturn($orderId, $isFullReturn, $productIds);
            } catch (Exception $e) {
                $data['message'] = $e->getMessage();
                $data['error'] = true;
                $this->model_extension_payment_history->updateOrderHistoryAndStatus(
                    (int) $orderId,
                    (int) $this->config->get('payment_alliance_refund_fail_status'),
                    $e->getMessage()
                );
            }
        } else {
            $data['message'] = $this->language->get('error_something_went_wrong');
            $data['error'] = true;
        }

        $data['message'] = $this->language->get('text_refund_success_message');
        $data['success'] = true;

        return $data;
    }

    /**
     * @param array $operationStatusData
     * @return array
     */
    public function prepareRefundData(array $operationData, int $coinAmount = 0)
    {
        $data = [];

        $data['operation_id'] = $operationData['operationId'];
        $data['merchant_id'] = $operationData['merchantId'] ?? '';
        $data['merchant_request_id'] = uniqid();
        $data['coin_amount'] = $coinAmount;

        return $data;
    }

    public function checkIfOrderRefunded(int $orderId): bool
    {
        $refoundAmont = 0;

        $allianceOrders = $this->model_extension_payment_history->getRefundOrder($orderId);

        foreach ($allianceOrders as $allianceOrder) {
            $refoundAmont += $allianceOrder['coin_amount'];
        }

        $ocOrder = $this->model_sale_order->getOrder($orderId);
        $ocOrderTotal = $this->getCoinAmount($ocOrder['total']);

        return $ocOrderTotal == $refoundAmont;
    }

    /**
     * @param array $orderCallBackData
     * @return array
     */
    private function getOperationPurchaseData(array $orderCallBackData): array
    {
        $purchaseOperation = [];

        if (!empty($orderCallBackData['operation'])) {
            $purchaseOperation = $orderCallBackData['operation'];
        } elseif ($orderCallBackData['operations']) {
            foreach ($orderCallBackData['operations'] as $operation) {
                if (!empty($operation['type']) && $operation['type'] == 'PURCHASE') {
                    $purchaseOperation = $operation;
                }
            }
        }

        return $purchaseOperation;
    }

    private function getPartialRefundCoinAmount($orderId, $productIds, $refundShipping = 0)
    {
        $coinAmount = 0;
        $products = $this->model_sale_order->getOrderProducts($orderId);

        foreach ($products as $product) {
            if (in_array($product['order_product_id'], $productIds)) {
                $coinAmount += $this->getCoinAmount($product['total']);
            }
        }

        if ($refundShipping) {
            $order_totals = $this->model_sale_order->getOrderTotals($orderId);
            foreach ($order_totals as $total) {
                if ($total['code'] == 'shipping') {
                    $coinAmount += $this->getCoinAmount($total['value']);
                }
            }
        }

        return $coinAmount;
    }

    private function proceedReturn($orderId ,$isFullReturn = true, $productIds = []): void
    {
        $this->load->model('localisation/return_reason');
        $orderInfo = $this->model_sale_order->getOrder($orderId);
        $returnProducts = [];

        if ($isFullReturn) {
            $returnProducts = $this->model_sale_order->getOrderProducts($orderId);
        } else {
            $orderProducts = $this->model_sale_order->getOrderProducts($orderId);

            foreach ($orderProducts as $orderProduct) {
                if (in_array($orderProduct['order_product_id'], $productIds)) {
                    $returnProducts[] = $orderProduct;
                }
            }
        }

        foreach ($returnProducts as $product) {
            $return_data = [
                'order_id'   => $orderId,
                'customer_id'=> $orderInfo['customer_id'],
                'firstname'  => $orderInfo['firstname'],
                'lastname'   => $orderInfo['lastname'],
                'email'      => $orderInfo['email'],
                'telephone'  => $orderInfo['telephone'],
                'product'    => $product['name'],
                'product_id' => $product['product_id'],
                'model'      => $product['model'],
                'quantity'   => $product['quantity'],
                'opened'     => 0,
                'date_ordered' => $orderInfo['date_added'],
                'return_reason_id' => 1,
                'return_action_id' => 2,
                'return_status_id' => 1,
                'comment'    => $this->language->get('text_auto_return_comment'),
            ];
            $this->model_sale_return->addReturn($return_data);
        }
    }

    private function getCoinAmount($itemAmount)
    {
        $amount = number_format($itemAmount, 4, '.', '');
        $amount = str_replace([' ', ','], ['', '.'], $amount);

        return (int)round((float)$amount * 100);
    }
}
