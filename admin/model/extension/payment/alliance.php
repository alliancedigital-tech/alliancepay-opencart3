<?php

class ModelExtensionPaymentAlliance extends Model
{

    public function getAllianceOrderCallback($order_id)
    {
        $allianceOrder = $this->getAllianceOrder($order_id);

        return !empty($allianceOrder) ? json_decode($allianceOrder['callback_data'], true) : [];
    }

    public function getAllianceOrder($order_id)
    {
        $query = $this->db->query("SELECT * FROM `"
            . DB_PREFIX . "alliance_checkout_integration_order` WHERE `order_id` = '"
            . $order_id . "'"
        );

        return $query->row;
    }

    public function fillOperationStatusData(
        array $data,
        array $e_com_response,
        bool $is_error,
        bool $isRefunded = false
    ) {
        $data['is_error'] = $is_error;

        if ($e_com_response) {
            $amount = $e_com_response['coinAmount'] ?? $e_com_response['coin_amount'];

            if ($is_error) {
                $data['msg_type'] = $e_com_response['msgType'];
            } else {
                $data['is_refund'] = $isRefunded;
                $data['coin_amount'] = $this->convertCoinAmount($amount);
                $data['ecom_order_id'] = $e_com_response['ecomOrderId'] ?? $e_com_response['ecom_order_id'];
                $data['status_url'] = $e_com_response['statusUrl'] ?? $e_com_response['status_url'];
                $data['merchant_id'] = $e_com_response['merchantId'] ?? $e_com_response['merchant_id'];
                $data['hpp_order_id'] = $e_com_response['hppOrderId'] ?? $e_com_response['hpp_order_id'];
                $data['hpp_pay_type'] = $e_com_response['hppPayType'] ?? $e_com_response['hpp_pay_type'];
                $data['expired_order_date'] =
                    $e_com_response['expiredOrderDate'] ?? $e_com_response['expired_order_date'];
                $data['order_status'] = $e_com_response['orderStatus'] ?? $e_com_response['order_status'];
                $data['create_date'] = $e_com_response['createDate'] ?? $e_com_response['create_date'];
            }
        }

        return $data;
    }

    private function convertCoinAmount($amount)
    {
        return number_format($amount / 100, 2, ',', '');
    }
}
