<?php

declare(strict_types=1);

class ControllerExtensionPaymentAllianceSubscription extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->load->language('extension/payment/alliance_subscription');
        $this->load->model('extension/payment/alliance_subscription');
        $this->load->model('checkout/order');
        $this->load->library('AllianceEcom');
        $this->load->library('AllianceLogger');
        $this->load->library('AllianceSubscriptionHelper');
        $this->load->library('AllianceSubscriptionConfig');
        $this->load->library('AllianceSubscriptionEligibility');
        $this->load->library('AllianceCountryCodeProvider');
        $this->logger = new AllianceLogger($registry);
        $this->ecom = new AllianceEcom($registry);
    }

    public function index()
    {
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/payment/alliance_subscription', $data);
    }

    public function payment()
    {
        $json = [];

        try {

            $plan = AllianceSubscriptionEligibility::resolveCartSubscriptionPlan(
                $this->cart->getProducts(),
                $this->registry
            );

            if (empty($plan)) {
                throw new \RuntimeException($this->language->get('error_subscription_not_eligible'));
            }

            $orderId = (int)($this->session->data['order_id'] ?? 0);
            $order = $this->model_checkout_order->getOrder($orderId);

            if (empty($order)) {
                throw new \RuntimeException($this->language->get('error_order_not_found'));
            }

            $rulesSnapshot = json_decode((string)$plan['rules'], true) ?: [];

            $merchantRequestId = $this->model_extension_payment_alliance_subscription->reserveMerchantRequestId($orderId);

            $body = $this->collectSubscriptionOrderData($plan, $order, $merchantRequestId);

            $response = $this->ecom->createSubscriptionOrder($body);

            if (empty($response['hppOrderId']) || empty($response['redirectUrl'])) {
                throw new \RuntimeException($this->language->get('error_subscription_provider_failed'));
            }

            $this->model_extension_payment_alliance_subscription->saveSubscription(
                $response,
                $orderId,
                (int)$plan['plan_id'],
                $rulesSnapshot,
                $merchantRequestId,
                (int)$this->customer->getId()
            );

            $json['redirect'] = $response['redirectUrl'];
        } catch (\Throwable $e) {
            $this->logger->error('Subscription payment() error: ' . $e->getMessage());
            $json['error'] = $e->getMessage() ?: $this->language->get('error_something_went_wrong');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @param array $plan
     * @param array $order
     * @param string $merchantRequestId
     */
    private function collectSubscriptionOrderData(array $plan, array $order, string $merchantRequestId): array
    {
        $countryCodeProvider = new AllianceCountryCodeProvider();
        $customerId = $this->customer->getId();

        $customerData = [
            'senderCustomerId' => $customerId ? $customerId : 'not_authorized_' . uniqid(),
            'senderFirstName' => $order['firstname'] ?? '',
            'senderLastName' => $order['lastname'] ?? '',
            'senderEmail' => $order['email'] ?? '',
            'senderCountry' => !empty($order['payment_iso_code_2'])
                ? $countryCodeProvider->getCountryNumericCodeByAlpha2($order['payment_iso_code_2']) : '',
            'senderRegion' => $order['payment_zone'] ?? '',
            'senderCity' => $order['payment_city'] ?? '',
            'senderStreet' => $order['payment_address_1'] ?? '',
            'senderAdditionalAddress' => $order['payment_address_2'] ?? '',
            'senderIp' => $order['ip'] ?? '',
            'senderPhone' => $order['telephone'] ?? '',
            'senderZipCode' => $order['payment_postcode'] ?? '',
        ];

        $hppMethods = json_decode((string)$this->config->get('payment_alliance_hpp_payment_methods'), true);

        return [
            'planId' => $plan['provider_plan_id'],
            'merchantId' => (string)$this->config->get('payment_alliance_merchant_id'),
            'merchantRequestId' => $merchantRequestId,
            'purpose' => 'Subscription for order #' . (int)($order['order_id'] ?? 0),
            'merchantComment' => 'Subscription for order #' . (int)($order['order_id'] ?? 0),
            'notificationUrl' => $this->url->link(AllianceEcom::NOTIFICATION_URL_ROUTE_SUBSCRIPTION),
            'notificationEncryption' => (string)$this->config->get('payment_alliance_subscription_notification_encryption'),
            'statusPageType' => AllianceEcom::SUBSCRIPTION_STATUS_PAGE_TYPE,
            'successUrl' => $this->config->get('payment_alliance_success_url'),
            'failUrl' => $this->config->get('payment_alliance_fail_url'),
            'hppPaymentMethods' => is_array($hppMethods) ? $hppMethods : ['CARD'],
            'directType' => 'REDIRECT',
            'customerData' => $this->ecom->validateCustomerData($customerData, $this->getCustomerDataValidationRules()),
        ];
    }

    private function getCustomerDataValidationRules(): array
    {
        return [
            'senderCustomerId' => ['type' => 'string', 'max_len' => 255, 'required' => true],
            'senderFirstName' => ['type' => 'string', 'max_len' => 30, 'required' => false],
            'senderLastName' => ['type' => 'string', 'max_len' => 30, 'required' => false],
            'senderEmail' => ['type' => 'string', 'max_len' => 256, 'required' => false],
            'senderCountry' => ['type' => 'string', 'max_len' => 3, 'required' => false],
            'senderRegion' => ['type' => 'string', 'max_len' => 255, 'required' => false],
            'senderCity' => ['type' => 'string', 'max_len' => 25, 'required' => false],
            'senderStreet' => ['type' => 'string', 'max_len' => 35, 'required' => false],
            'senderAdditionalAddress' => ['type' => 'string', 'max_len' => 255, 'required' => false],
            'senderIp' => ['type' => 'string', 'max_len' => 50, 'required' => false],
            'senderPhone' => ['type' => 'numeric_string', 'max_len' => 20, 'required' => false],
            'senderZipCode' => ['type' => 'string', 'max_len' => 50, 'required' => false],
        ];
    }

    public function notifyCallback(): bool
    {
        $request = file_get_contents('php://input');
        $trimmed = trim($request);
        $dotCount = substr_count($trimmed, '.');
        $isToken = $trimmed !== '' && strpos($trimmed, ' ') === false;

        if ($isToken && $dotCount === 2) {
            try {
                $requestData = $this->ecom->verifyJwsToken($trimmed);
            } catch (\Throwable $e) {
                $this->logger->error('Subscription JWS callback verification failed: ' . $e->getMessage());
                $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');

                return false;
            }
        } else {
            $requestData = json_decode($trimmed, true);
        }

        if (!is_array($requestData)) {
            $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');

            return false;
        }

        $this->logger->info('Subscription callback request', $requestData);

        $this->model_extension_payment_alliance_subscription->processCallback($requestData);

        return true;
    }

    /**
     * @param string $route
     * @param array $args
     */
    public function cartGuardEvent(&$route, &$args)
    {
        $productId = (int)($args[0] ?? $this->request->post['product_id'] ?? 0);

        if (!$productId) {
            return null;
        }

        $plan = AllianceSubscriptionEligibility::resolveProductSubscriptionPlan($productId, $this->registry);
        $wantsSubscription = ($this->request->post['alliance_purchase_type'] ?? 'once') === 'subscription';
        $existingCart = $this->cart->getProducts();

        if (!$plan || !$wantsSubscription) {
            AllianceSubscriptionEligibility::clearSubscriptionIntent($this->registry, $productId);

            if (AllianceSubscriptionEligibility::cartHasSubscriptionItem($existingCart, $this->registry)) {
                return $this->cartGuardJsonError($this->language->get('error_subscription_mixed_cart'));
            }

            return null;
        }

        if (!empty($existingCart)) {
            return $this->cartGuardJsonError($this->language->get('error_subscription_mixed_cart'));
        }

        AllianceSubscriptionEligibility::markSubscriptionIntent($productId, $this->registry);

        return null;
    }

    /**
     * @param string $message
     */
    private function cartGuardJsonError(string $message): bool
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['error' => $message]));

        return true;
    }

    /**
     * @param string $route
     * @param array $args
     * @param string $output
     */
    public function injectSubscribeBlockEvent(&$route, &$args, &$output)
    {
        $productId = (int)($this->request->get['product_id'] ?? 0);

        if (!$productId) {
            return;
        }

        $plan = AllianceSubscriptionEligibility::resolveProductSubscriptionPlan($productId, $this->registry);

        if (empty($plan)) {
            return;
        }

        $rules = json_decode((string)$plan['rules'], true);
        $firstRule = is_array($rules) ? reset($rules) : null;
        $subscribeLabel = htmlspecialchars($this->language->get('text_subscribe_price_prefix'), ENT_QUOTES);

        if (!empty($firstRule['coinAmount'])) {
            $price = number_format(((int)$firstRule['coinAmount']) / 100, 2);
            $interval = trim(($firstRule['intervalValue'] ?? '') . ' ' . ($firstRule['intervalUnit'] ?? ''));
            $subscribeLabel .= ' ' . $price . ($interval !== '' ? ' / ' . htmlspecialchars($interval, ENT_QUOTES) : '');
        }

        $addToCartUrl = $this->url->link('checkout/cart/add', '', true);
        $cartUrl = $this->url->link('checkout/cart', '', true);
        $buyOnceLabel = htmlspecialchars($this->language->get('text_buy_once'), ENT_QUOTES);
        $buttonLabel = htmlspecialchars($this->language->get('button_add_to_cart_choice'), ENT_QUOTES);

        $html = '
        <div id="alliance-subscription-block" class="alliance-subscription-block panel panel-default" style="margin-top:15px;padding:15px;">
            <div class="form-group">
                <select id="alliance-purchase-type" class="form-control">
                    <option value="once">' . $buyOnceLabel . '</option>
                    <option value="subscription">' . $subscribeLabel . '</option>
                </select>
            </div>
            <button type="button" id="alliance-add-to-cart-button" class="btn btn-primary" data-product-id="' . $productId . '">
                ' . $buttonLabel . '
            </button>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                var block = document.getElementById("alliance-subscription-block");
                var nativeButton = document.getElementById("button-cart");

                if (block && nativeButton && nativeButton.parentNode) {
                    nativeButton.parentNode.insertBefore(block, nativeButton);
                    nativeButton.style.display = "none";
                } else if (block) {
                    document.body.insertBefore(block, document.body.firstChild);
                }

                var select = document.getElementById("alliance-purchase-type");
                var btn = document.getElementById("alliance-add-to-cart-button");
                if (!btn || !select) { return; }

                btn.addEventListener("click", function () {
                    var purchaseType = select.value;
                    var quantityInput = document.getElementById("input-quantity");
                    var quantity = purchaseType === "subscription"
                        ? 1
                        : (quantityInput ? (parseInt(quantityInput.value, 10) || 1) : 1);

                    fetch("' . $addToCartUrl . '", {
                        method: "POST",
                        headers: {"Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"},
                        body: "product_id=' . $productId . '&quantity=" + quantity + "&alliance_purchase_type=" + purchaseType
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        if (json.error) {
                            alert(typeof json.error === "string" ? json.error : Object.values(json.error)[0]);
                        } else {
                            window.location = "' . $cartUrl . '";
                        }
                    });
                });
            });
        </script>';

        $output = str_replace('</body>', $html . '</body>', $output);
    }

    /**
     * @param string $route
     * @param array $args
     * @param string $output
     */
    public function injectAccountMenuLinkEvent(&$route, &$args, &$output)
    {
        if (!$this->customer->isLogged()) {
            return;
        }

        $url = html_entity_decode($this->url->link(
            'extension/payment/alliance_subscription/myList',
            'customer_token=' . ($this->session->data['customer_token'] ?? ''),
            true
        ));

        $html = '
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                var list = document.querySelector(".list-group.list-group-flush") || document.querySelector(".list-group");
                if (!list) { return; }
                var a = document.createElement("a");
                a.className = "list-group-item";
                a.href = "' . $url . '";
                a.innerHTML = \'' . htmlspecialchars($this->language->get('text_my_subscriptions'), ENT_QUOTES) . '\';
                list.appendChild(a);
            });
        </script>';

        $output = str_replace('</body>', $html . '</body>', $output);
    }

    public function myList()
    {
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('extension/payment/alliance_subscription/myList', '', true);
            $this->response->redirect($this->url->link('account/login', '', true));
            return;
        }

        $this->document->setTitle($this->language->get('heading_title_my_list'));

        $data['heading_title'] = $this->language->get('heading_title_my_list');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home'),
            ],
            [
                'text' => $this->language->get('text_account'),
                'href' => $this->url->link('account/account', '', true),
            ],
            [
                'text' => $this->language->get('heading_title_my_list'),
                'href' => $this->url->link('extension/payment/alliance_subscription/myList', '', true),
            ],
        ];

        $subscriptions = $this->model_extension_payment_alliance_subscription->getCustomerSubscriptions(
            (int)$this->customer->getId()
        );

        $data['subscriptions'] = [];
        foreach ($subscriptions as $subscription) {
            $data['subscriptions'][] = [
                'subscription_id' => $subscription['subscription_id'],
                'plan_name' => $subscription['plan_name'] ?? '',
                'product_name' => $subscription['product_name'] ?? '',
                'status' => $subscription['status'],
                'created_at' => $subscription['created_at'],
                'can_cancel' => in_array($subscription['status'], ['ACTIVE', 'PENDING'], true),
            ];
        }

        $data['cancel_action'] = html_entity_decode(
            $this->url->link('extension/payment/alliance_subscription/cancelOwn', '', true)
        );
        $data['continue'] = $this->url->link('account/account', '', true);

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');

        $this->response->setOutput($this->load->view('extension/payment/alliance_subscription_my_list', $data));
    }

    public function cancelOwn()
    {
        $json = [];

        if (!$this->customer->isLogged()) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_not_logged_in');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $subscriptionId = (int)($this->request->post['subscription_id'] ?? 0);
        $result = $this->model_extension_payment_alliance_subscription->cancelOwnSubscription(
            (int)$this->customer->getId(),
            $subscriptionId
        );

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }
}
