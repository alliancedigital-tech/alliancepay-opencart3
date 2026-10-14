<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class ModelExtensionPaymentRefund.
 */
class ModelExtensionPaymentRefund extends Model
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->library('AllianceConfig');
        $this->load->library('AllianceHelper');
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
     * @param $refundShipping
     * @return array|mixed
     * @throws Throwable
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
                    $eComResponse = $this->ecom->checkOperationStatus(
                        $allianceOrder['hpp_order_id']
                    );
                    $purchaseOperation = AllianceHelper::getOperationPurchaseData($eComResponse);
                } else {
                    $purchaseOperation = AllianceHelper::getOperationPurchaseData($allianceOrderCallBack);
                }

                if (empty($purchaseOperation)) {
                    return [
                        'message' => $this->language->get('text_alliance_operation_purchase_not_found'),
                        'error' => true,
                    ];
                }

                if ($isFullReturn) {
                    if ($allianceOrder['hpp_pay_type'] === AllianceConfig::OPERATION_TYPE_PREAUTH) {
                        $coinAmount = $this->getCompletionOperationCoinAmount(
                            $allianceOrderCallBack,
                            $allianceOrder
                        );
                    } else {
                        $coinAmount = (int)$purchaseOperation['coinAmount'];
                    }
                } else {
                    $coinAmount = $this->getPartialRefundCoinAmount(
                        $orderId,
                        $productIds,
                        $refundShipping,
                        (float)($allianceOrder['conversion_rate'] ?? 0)
                    );
                }
                $refundData = $this->prepareRefundData($purchaseOperation, $coinAmount);

                $data = $this->ecom->proceedRefund(
                    $this->url,
                    $refundData
                );

                if (!empty($data['msgType']) && $data['msgType'] == 'ERROR') {
                    $this->logger->log(
                        AllianceLogger::ERROR,
                        'Refund error: ' . ($data['msgText'] ?? '')
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

            if (empty($data['error'])) {
                $data['message'] = $this->language->get('text_refund_success_message');
                $data['success'] = true;
            }
        } else {
            $data['message'] = $this->language->get('error_something_went_wrong');
            $data['error'] = true;
        }

        return $data;
    }

    /**
     * @param array $operationData
     * @param int $coinAmount
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

    /**
     * @param int $orderId
     * @return bool
     */
    public function checkIfOrderRefunded(int $orderId): bool
    {
        $refoundAmont = 0;

        $allianceOrders = $this->model_extension_payment_history->getRefundOrder($orderId);

        foreach ($allianceOrders as $allianceOrder) {
            $refoundAmont += $allianceOrder['coin_amount'];
        }

        $ocOrder = $this->model_sale_order->getOrder($orderId);
        $ocOrderTotal = self::toCoinAmount((float)$ocOrder['total']);

        return $ocOrderTotal === $refoundAmont;
    }


    /**
     * @param array $callBackData
     * @param array $allianceOrder
     * @return int
     */
    private function getCompletionOperationCoinAmount(array $callBackData, array $allianceOrder): int
    {
        $operations = $callBackData['operations'] ?? [];
        if (!empty($callBackData['operation'])) {
            $operations[] = $callBackData['operation'];
        }

        $completionCoinAmount = null;
        foreach ($operations as $operation) {
            if (($operation['type'] ?? '') === AllianceConfig::OPERATION_TYPE_COMPLETION
                && ($operation['status'] ?? '') === AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS
            ) {
                $completionCoinAmount = (int)$operation['coinAmount'];
            }
        }

        if ($completionCoinAmount !== null) {
            return $completionCoinAmount;
        }

        return (int)$allianceOrder['coin_amount'];
    }

    /**
     * @param $orderId
     * @param $productIds
     * @param $refundShipping
     * @param float $conversionRate
     * @return int
     */
    private function getPartialRefundCoinAmount($orderId, $productIds, $refundShipping = 0, float $conversionRate = 0.0)
    {
        $amountInOrderCurrency = 0.0;
        $products = $this->model_sale_order->getOrderProducts($orderId);

        foreach ($products as $product) {
            if (in_array($product['order_product_id'], $productIds)) {
                $amountInOrderCurrency += (float)$product['total'];
            }
        }

        if ($refundShipping) {
            $order_totals = $this->model_sale_order->getOrderTotals($orderId);
            foreach ($order_totals as $total) {
                if ($total['code'] == 'shipping') {
                    $amountInOrderCurrency += (float)$total['value'];
                }
            }
        }

        if ($conversionRate > 0) {
            return (int)round($amountInOrderCurrency * $conversionRate * 100);
        }

        return self::toCoinAmount($amountInOrderCurrency);
    }

    /**
     * @param $orderId
     * @param $isFullReturn
     * @param $productIds
     * @return void
     */
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

    /**
     * @param float $itemAmount
     * @return int
     */
    public static function toCoinAmount(float $itemAmount): int
    {
        $amount = number_format($itemAmount, 4, '.', '');
        $amount = str_replace([' ', ','], ['', '.'], $amount);

        return (int)round((float)$amount * 100);
    }

    /** @deprecated Use toCoinAmount() instead */
    private function getCoinAmount($itemAmount): int
    {
        return self::toCoinAmount((float)$itemAmount);
    }
}
