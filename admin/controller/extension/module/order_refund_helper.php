<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

class ControllerExtensionModuleOrderRefundHelper extends Controller
{
    /**
     * @param $route
     * @param $data
     * @return void
     */
    public function addReturnedProducts(&$route, &$data)
    {
        $this->load->language('extension/payment/alliance');
        $this->load->model('extension/payment/alliance');

        if (isset($this->request->get['order_id'])) {
            $orderId = (int)$this->request->get['order_id'];
            $isA2ACanBeRefund = $this->model_extension_payment_alliance->checkIfA2AOrderCanBeRefunded($orderId);

            if ($isA2ACanBeRefund) {
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

                $data['is_allow_refund'] = $isA2ACanBeRefund;
                $data['is_alliance_refunded'] = $this->isRefunded($orderId);
                $data['shipping_returned'] = $this->model_extension_payment_alliance->isShippingReturnedForOrder(
                    $orderId,
                    $this->language->get('text_shipping_match')
                );
            } else {
                $data['is_allow_refund'] = $isA2ACanBeRefund;
            }

        }
    }

    /**
     * @param int $orderId
     * @return bool
     */
    private function isRefunded(int $orderId): bool
    {
        $this->load->model('extension/payment/refund');

        return $this->model_extension_payment_refund->checkIfOrderRefunded($orderId);
    }
}
