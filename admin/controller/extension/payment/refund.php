<?php

/**
 * Class Refund.
 */
class ControllerExtensionPaymentRefund extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->model('sale/order');
        $this->load->model('extension/payment/refund');
        $this->load->library('AllianceConfig');
    }

    /**
     * @param $route
     * @param $args
     * @param $output
     * @return void
     */
    public function injectRefundButton(&$route, &$args, &$output)
    {
        $this->load->language('extension/payment/alliance');

        $buttonHtml = '';
        $order_id = (int)$this->request->get['order_id'];
        $order_info = $this->model_sale_order->getOrder($order_id);
        $isRefunded = $this->model_extension_payment_refund->checkIfOrderRefunded((int) $order_id);

        if ($order_info['payment_code'] === AllianceConfig::ALLIANCE_PAYMENT_CODE) {
            $disabled = !$isRefunded ? 0 : 1;
            $buttonHtml = '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    let isDisabled = Boolean(' . $disabled . ');
                    const container = document.querySelector(".page-header .container-fluid .pull-right");
                    if (container) {
                        const btnPartial = document.createElement("button");
                        btnPartial.id = "button-partial-return";
                        btnPartial.setAttribute("data-toggle", "tooltip");
                        btnPartial.setAttribute("title", "' . $this->language->get('text_partial_refund_title') . '");
                        btnPartial.className = "btn btn-warning";
                        btnPartial.style = "margin-right:5px;";
                        btnPartial.innerHTML = \'' . $this->language->get('button_partial_refund') . '\';
                        const formData = new FormData();
                        formData.append("is_full_refund", "1");
                        formData.append("product_ids", []);
                        const btn = document.createElement("button");
                        btn.className = "btn btn-danger";
                        btn.style = "margin-right:5px;";
                        if (isDisabled) {
                            btn.disabled = true;
                            btnPartial.disabled = true;
                        }
                        btn.innerHTML = \'' . $this->language->get('button_full_refund') . '\';
                        btn.addEventListener("click", function() {
                            const orderId = new URLSearchParams(window.location.search).get("order_id");
                            fetch("index.php?route=extension/payment/refund/refund&user_token='
                                . $this->getUserToken() . '&order_id=" + orderId, {
                                    method: "POST",
                                    body: formData
                                 })
                                .then(r => r.json())
                                .then(res => {
                                    btn.disabled = true;
                                    alert(res.message || "' . $this->language->get('text_refund_complete_js') . '");
                                })
                                .catch(err => alert(res.message || "' . $this->language->get('text_refund_failed_js') . '"));
                        });
                        btnPartial.addEventListener("click", function() {
                            proceedPartialReturn();
                        });
                        container.prepend(btnPartial);
                        container.prepend(btn);
                    }
                });
            </script>';
        }

        $output = str_replace('</body>', $buttonHtml . '</body>', $output);
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    public function refund()
    {
        $orderId = $this->request->get['order_id'] ?? 0;
        $isFullRefund = $this->request->post['is_full_refund'] == 1;
        $productIds = $this->request->post['product_ids'] ?? [];
        $refundShipping = $this->request->post['refund_shipping'] ?? 0;
        $data = $this->model_extension_payment_refund->refundOrder($orderId, $isFullRefund, $productIds, $refundShipping);
        $this->response->addHeader("Content-Type: application/json");
        $this->response->setOutput(json_encode($data));
    }

    /**
     * @return string
     */
    private function getUserToken()
    {
        $session = $this->session;

        return $session->data['user_token'];
    }
}
