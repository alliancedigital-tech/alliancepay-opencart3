<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class ModelExtensionPaymentAlliance.
 */
class ModelExtensionPaymentAlliance extends Model
{

    /**
     * @param int $order_id
     * @return array
     */
    public function getAllianceOrderCallback(int $order_id): array
    {
        $allianceOrder = $this->getAllianceOrder($order_id);

        return !empty($allianceOrder['callback_data']) ? json_decode($allianceOrder['callback_data'], true) : [];
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function getAllianceOrder(int $order_id): array
    {
        $query = $this->db->query("SELECT * FROM `"
            . DB_PREFIX . "alliance_checkout_integration_order` WHERE `order_id` = '"
            . (int)$order_id . "'"
        );

        return $query->row;
    }

    /**
     * @param array $data
     * @param array $e_com_response
     * @param bool $is_error
     * @param bool $isRefunded
     * @param array $allianceOrder
     * @return array
     */
    public function fillOperationStatusData(
        array $data,
        array $e_com_response,
        bool  $is_error,
        bool  $isRefunded = false,
        array $allianceOrder = []
    ): array
    {
        $data['is_error'] = $is_error;

        if ($e_com_response) {
            $amount = $e_com_response['coinAmount'] ?? $e_com_response['coin_amount'];

            if ($is_error) {
                $data['msg_type'] = $e_com_response['msgType'];
            } else {
                $data['is_refund'] = $isRefunded;
                $data['ecom_order_id'] = $e_com_response['ecomOrderId'] ?? $e_com_response['ecom_order_id'] ?? '';
                $data['status_url'] = $e_com_response['statusUrl'] ?? $e_com_response['status_url'] ?? '';
                $data['merchant_id'] = $e_com_response['merchantId'] ?? $e_com_response['merchant_id'] ?? '';
                $data['hpp_order_id'] = $e_com_response['hppOrderId'] ?? $e_com_response['hpp_order_id'] ?? '';
                $data['hpp_pay_type'] = $e_com_response['hppPayType'] ?? $e_com_response['hpp_pay_type'] ?? '';
                $data['expired_order_date'] =
                    $e_com_response['expiredOrderDate'] ?? $e_com_response['expired_order_date'] ?? '';
                $data['order_status'] = $e_com_response['orderStatus'] ?? $e_com_response['order_status'] ?? '';
                $data['create_date'] = $e_com_response['createDate'] ?? $e_com_response['create_date'] ?? '';
                $data['amount_currency_alpha'] = '';
                $data['amount_in_currency'] = '';

                $data['coin_amount'] = $this->convertCoinAmount($amount);

                if (!empty($allianceOrder)) {
                    $conversionRate = (float)($allianceOrder['conversion_rate'] ?? 0);
                    $currencyCode   = (int)($allianceOrder['currency_code'] ?? 0);

                    if ($conversionRate > 0 && $currencyCode > 0) {
                        $alphaMap = array_flip(\AllianceConfig::CURRENCY_CODE_MAP);
                        $currencyAlpha = $alphaMap[$currencyCode] ?? '';

                        if ($currencyAlpha !== '' && $currencyAlpha !== 'UAH') {
                            $foreignAmount = (float)$amount / 100;
                            $data['amount_in_currency'] = number_format($foreignAmount, 2, '.', ' ');
                            $data['amount_currency_alpha'] = $currencyAlpha;

                            $uahAmount = $foreignAmount * $conversionRate;
                            $data['coin_amount'] = number_format($uahAmount, 2, ',', '');
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param int $order_id
     * @return bool
     */
    public function checkIfA2AOrderCanBeRefunded(int $order_id): bool
    {
        $allianceOrder = $this->getAllianceOrder($order_id);

        if (empty($allianceOrder)) {
            return true;
        }

        $decodedCallbackData = !empty($allianceOrder['callback_data'])
            ? json_decode($allianceOrder['callback_data'], true) : [];

        if (!isset($decodedCallbackData['operations']) || !is_array($decodedCallbackData['operations'])) {
            return true;
        }

        foreach ($decodedCallbackData['operations'] as $operation) {
            if ($operation['type'] === 'ACCOUNT_2_ACCOUNT'
                && $operation['status'] === 'SUCCESS'
                && $operation['transactionType'] === 102
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $order_id
     * @param string $shippingMatchText
     * @return bool
     */
    public function isShippingReturnedForOrder(int $order_id, string $shippingMatchText): bool
    {
        $query = $this->db->query(
            "SELECT return_id FROM `" . DB_PREFIX . "return`"
            . " WHERE `order_id` = '" . (int)$order_id . "'"
            . " AND `comment` LIKE '%" . $this->db->escape($shippingMatchText) . "%'"
        );

        return $query->num_rows > 0;
    }

    /**
     * @param int|float $amount
     */
    private function convertCoinAmount($amount): string
    {
        return number_format($amount / 100, 2, ',', '');
    }
}
