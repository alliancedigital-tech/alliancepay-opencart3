<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class Alliance.
 */
class ModelExtensionPaymentAlliance extends Model
{
    /**
     * @param array $address
     * @return array
     */
    public function getMethod(array $address = [])
    {
        $this->load->language('extension/payment/alliance');
        $this->load->library('AllianceConfig');

        $this->load->library('AllianceSubscriptionEligibility');
        if (AllianceSubscriptionEligibility::resolveCartSubscriptionPlan($this->cart->getProducts(), $this->registry)) {
            return [];
        }

        $option_data['alliance'] = [
            'code' => AllianceConfig::ALLIANCE_PAYMENT_CODE,
            'title' => $this->language->get('text_title'),
        ];

        return [
            'code' => AllianceConfig::ALLIANCE_PAYMENT_CODE,
            'title' => $this->language->get('heading_title'),
            'option' => $option_data,
            'sort_order' => $this->config->get('payment_alliance_sort_order'),
        ];
    }

    /**
     * @param string $hpp_order_id
     * @return array
     */
    public function getAllianceOrder(string $hpp_order_id): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_checkout_integration_order` WHERE `hpp_order_id` = '"
            . $this->db->escape($hpp_order_id) . "'"
        );

        return $query->row;
    }

    /**
     * @param array $data
     * @param int $order_id
     * @return void
     */
    public function saveAllianceOrder(array $data, int $order_id)
    {
        $this->load->model('checkout/order');

        if (($data['hppPayType'] ?? '') === 'PREAUTH') {
            $this->load->language('extension/payment/alliance');
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                (int)$this->config->get('payment_alliance_preauth_status'),
                $this->language->get('text_preauth_success')
            );
        } elseif ($data['orderStatus'] == 'PENDING' || $data['orderStatus'] == 'REQUIRED_3DS') {
            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_alliance_pending_status'));
        }

        if ($data['orderStatus'] == 'FAIL') {
            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_alliance_error_status'));
        }

        $sql = "INSERT INTO `" . DB_PREFIX . "alliance_checkout_integration_order` SET";

        $implode = [];

        if (!empty($order_id)) {
            $implode[] = "`order_id` = '" . $order_id . "'";
        }

        if (!empty($data['merchantRequestId'])) {
            $implode[] = "`merchant_request_id` = '" . $this->db->escape($data['merchantRequestId']) . "'";
        }

        if (!empty($data['hppOrderId'])) {
            $implode[] = "`hpp_order_id` = '" . $this->db->escape($data['hppOrderId']) . "'";
        }

        if (!empty($data['merchantId'])) {
            $implode[] = "`merchant_id` = '" . $this->db->escape($data['merchantId']) . "'";
        }

        if (!empty($data['coinAmount'])) {
            $implode[] = "`coin_amount` = '" . (int) $data['coinAmount'] . "'";
        }

        if (isset($data['originalAuthorizedAmount'])) {
            $implode[] = "`original_authorized_amount` = '" . (int) $data['originalAuthorizedAmount'] . "'";
        }

        if (isset($data['currencyCode'])) {
            $implode[] = "`currency_code` = '" . (int) $data['currencyCode'] . "'";
        }

        if (!empty($data['hppPayType'])) {
            $implode[] = "`hpp_pay_type` = '" . $this->db->escape($data['hppPayType']) . "'";
        }

        if (!empty($data['orderStatus'])) {
            $implode[] = "`order_status` = '" . $this->db->escape($data['orderStatus']) . "'";
        }

        if (!empty($data['paymentMethods'])) {
            $paymentMethods = json_encode($data['paymentMethods']);
            $implode[] = "`payment_methods` = '" . $this->db->escape($paymentMethods) . "'";
        }

        if (!empty($data['updatedAt'])) {
            $implode[] = "`updated_at` = '" . $this->db->escape($data['updatedAt']) . "'";
        }

        if (!empty($data['ecomOrderId'])) {
            $implode[] = "`ecom_order_id` = '" . $this->db->escape($data['ecomOrderId']) . "'";
        }

        if (!empty($data['createDate'])) {
            $implode[] = "`create_date` = '" . $this->db->escape($data['createDate']) . "'";
        }

        if (!empty($data['expiredOrderDate'])) {
            $implode[] = "`expired_order_date` = '" . $this->db->escape($data['expiredOrderDate']) . "'";
        }

        if ($implode) {
            $sql .= implode(', ', $implode);
        }

        $this->db->query($sql);
    }

    /**
     * @param array $data
     * @param int $order_id
     * @param string $operationId
     * @return void
     */
    public function saveCallBack(array $data, int $order_id, string $operationId = '')
    {
        $this->load->model('checkout/order');

        $sql = "UPDATE `" . DB_PREFIX . "alliance_checkout_integration_order` SET";

        $implode = [];

        if (!empty($data['orderStatus'])) {
            $implode[] = "`order_status` = '" . $data['orderStatus'] . "'";
        }

        if (!empty($operationId)) {
            $implode[] = "`operation_id` = '" . $operationId . "'";
        }

        $conversionRate = $data['operation']['conversionRate'] ?? null;
        if ($conversionRate !== null && (float)$conversionRate > 0) {
            $implode[] = "`conversion_rate` = '" . $this->db->escape((string)(float)$conversionRate) . "'";
        }

        $implode[] = "`updated_at` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'";
        $implode[] = "`is_callback_returned` = '" . $this->db->escape(1) . "'";

        $data = $this->prepareOperations($data);

        $implode[] = "`callback_data` = '" . $this->db->escape(json_encode($data)) . "'";

        if ($implode) {
            $sql .= implode(', ', $implode);
        }

        $sql .= " WHERE `order_id` = '" . $order_id . "'";

        $this->db->query($sql);
    }

    /**
     * @param array $data
     * @return array
     */
    private function prepareOperations(array $data)
    {
        $operations = [];
        $hppOrderId = $data['hppOrderId'] ?? $data['hpp_order_id'];
        $allainceOrder = $this->getAllianceOrder($hppOrderId);

        if (!empty($allainceOrder) && !empty($allainceOrder['callback_data'])) {
            $callbackData = json_decode($allainceOrder['callback_data'], true);
            if (!empty($callbackData['operations'])) {
                $operations = array_column($callbackData['operations'], null, 'operationId');
            }
        }

        if (!isset($data['operations']) && isset($data['operation'])) {
            if (!isset($operations[$data['operation']['operationId']])) {
                $operations[] = $data['operation'];
                unset($data['operation']);
            }
        } elseif (isset($data['operations'])) {
            foreach ($data['operations'] as $callbackOperation) {
                $operationId = $callbackOperation['operationId'] ?? $callbackOperation['operation_id'];
                if (!isset($operations[$operationId])) {
                    $operations[] = $callbackOperation;
                }
            }
        }

        $data['operations'] = $operations;

        return $data;
    }

    /**
     * @param object $session
     * @return void
     */
    public function clearCart(object $session)
    {
        $this->cart->clear();

        unset($session->data['order_id']);
        unset($session->data['payment_method']);
        unset($session->data['payment_methods']);
        unset($session->data['shipping_method']);
        unset($session->data['shipping_methods']);
        unset($session->data['comment']);
        unset($session->data['agree']);
        unset($session->data['coupon']);
        unset($session->data['reward']);
    }

    /**
     * @return mixed
     */
    public function cartTotalPrice()
    {
        $this->load->model('checkout/cart');

        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        ($this->model_checkout_cart->getTotals)($totals, $taxes, $total);

        return $totals[key(array_slice($totals, -1, 1, true))]['value'];
    }
}
