<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

class ControllerExtensionModuleOrderRefundHelper extends Controller
{
    public function addReturnedProducts(&$route, &$data) {
        $this->load->language('extension/payment/alliance');

        if (isset($this->request->get['order_id'])) {
            $orderId = (int)$this->request->get['order_id'];
            $this->load->model('sale/return');
            $orderReturnedProducts = $this->model_sale_return->getReturns(['filter_order_id' => $orderId]);
            $orderProducts = $this->model_sale_order->getOrderProducts($orderId);

            if (!empty($orderReturnedProducts)) {
                $orderReturnedProducts = array_column($orderReturnedProducts, null, 'product_id');
            }

            foreach ($orderProducts as $orderProduct) {
                if (isset($orderReturnedProducts[$orderProduct['product_id']])) {
                    $data['returned_products'][] = $orderProduct['order_product_id'];
                }
            }

            $data['is_alliance_refunded'] = $this->isRefunded($orderId);

            $res_shipping = $this->db->query("SELECT return_id FROM "
                . DB_PREFIX . "return WHERE order_id = '" . $orderId . "' AND comment LIKE '%" . $this->db->escape($this->language->get('text_shipping_match')) . "%'");
            $data['shipping_returned'] = ($res_shipping->num_rows > 0);
        }
    }

    private function getCoinAmount($itemAmount)
    {
        $amount = number_format($itemAmount, 4, '.', '');
        $amount = str_replace([' ', ','], ['', '.'], $amount);

        return (int)round((float)$amount * 100);
    }

    private function isRefunded($orderId)
    {
        $this->load->model('extension/payment/history');
        $ocOrder = $this->model_sale_order->getOrder($orderId);
        $ocOrderTotal = $this->getCoinAmount((float) $ocOrder['total']);
        $refundOperationsTotal = $this->model_extension_payment_history->getRefundOperationsTotal($orderId);

        return $refundOperationsTotal == $ocOrderTotal;
    }
}
