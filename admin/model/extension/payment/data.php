<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class ModelExtensionPaymentData.
 */
class ModelExtensionPaymentData extends Model
{
    private array $error = [];

    /**
     * @param object $session
     * @param object $config
     * @param object $request
     * @return array
     */
    public function fillSettingData(object $session, object $config, object $request): array
    {
        $this->load->language('extension/payment/alliance');

        return array_merge(
            $this->getLanguageData(),
            $this->getBreadcrumbs($session),
            $this->getFormFields($config, $request),
            $this->getOrderStatuses($config, $request),
            $this->getLayoutBlocks($session),
            $this->getNotifications($session)
        );
    }

    /**
     * @return array
     */
    private function getLanguageData(): array
    {
        return [
            'heading_title' => $this->language->get('heading_title'),
            'text_general' => $this->language->get('text_general'),
            'text_statuses' => $this->language->get('text_statuses'),
            'text_edit' => $this->language->get('text_edit'),
            'text_enabled' => $this->language->get('text_enabled'),
            'text_disabled' => $this->language->get('text_disabled'),
            'entry_status' => $this->language->get('entry_status'),
            'entry_paid_status' => $this->language->get('entry_paid_status'),
            'entry_confirmed_status' => $this->language->get('entry_confirmed_status'),
            'entry_complete_status' => $this->language->get('entry_complete_status'),
            'help_paid_status' => $this->language->get('help_paid_status'),
            'button_save' => $this->language->get('button_save'),
            'entry_preauth_exp_date' => $this->language->get('entry_preauth_exp_date'),
            'help_preauth_exp_date' => $this->language->get('help_preauth_exp_date'),
            'entry_preauth_status' => $this->language->get('entry_preauth_status'),
            'help_preauth_status' => $this->language->get('help_preauth_status'),
            'entry_completion_status' => $this->language->get('entry_completion_status'),
            'help_completion_status' => $this->language->get('help_completion_status'),
        ];
    }

    /**
     * @param object $session
     * @return array
     */
    private function getBreadcrumbs(object $session): array
    {
        $token = $session->data['user_token'];

        return [
            'url_action' => $this->url->link(
                'extension/payment/alliance',
                'user_token=' . $token,
                'SSL'
            ),
            'breadcrumbs' => [
                [
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link('common/dashboard', 'user_token=' . $token, 'SSL'),
                ],
                [
                    'text' => $this->language->get('text_payment'),
                    'href' => $this->url->link(
                        'marketplace/extension',
                        'user_token=' . $token . '&type=payment',
                        'SSL'
                    ),
                ],
                [
                    'text' => $this->language->get('heading_title'),
                    'href' => $this->url->link('extension/payment/alliance', 'user_token=' . $token, 'SSL'),
                ],
            ],
        ];
    }

    /**
     * @param object $config
     * @param object $request
     * @return array
     */
    private function getFormFields(object $config, object $request): array
    {
        $post = $request->post;
        $catalog_url = $this->config->get('config_url') ?: HTTP_CATALOG;

        return [
            'payment_alliance_url' => $post['payment_alliance_url']
                ?? $config->get('payment_alliance_url'),
            'payment_alliance_merchant_id' => $post['payment_alliance_merchant_id']
                ?? $config->get('payment_alliance_merchant_id'),
            'payment_alliance_hpp_type' => $post['payment_alliance_hpp_type']
                ?? $config->get('payment_alliance_hpp_type'),
            'config_currency' => $config->get('config_currency') ?: 'UAH',
            'payment_alliance_preauth_exp_date' => $post['payment_alliance_preauth_exp_date']
                ?? $config->get('payment_alliance_preauth_exp_date'),
            'payment_alliance_status' => $post['payment_alliance_status']
                ?? $config->get('payment_alliance_status'),
            'payment_alliance_jws_private_key' => $post['payment_alliance_jws_private_key']
                ?? $config->get('payment_alliance_jws_private_key'),
            'payment_alliance_jws_key_id' => $post['payment_alliance_jws_key_id']
                ?? $config->get('payment_alliance_jws_key_id'),
            'payment_alliance_hpp_payment_methods' => $post['payment_alliance_hpp_payment_methods']
                ?? (json_decode((string)$config->get('payment_alliance_hpp_payment_methods'), true) ?: ['CARD']),
            'payment_alliance_fail_url' => $this->resolveUrl(
                'payment_alliance_fail_url',
                $catalog_url . 'index.php?route=checkout/failure',
                $config,
                $request
            ),
            'payment_alliance_success_url' => $this->resolveUrl(
                'payment_alliance_success_url',
                $catalog_url . 'index.php?route=checkout/success',
                $config,
                $request
            ),
        ];
    }

    /**
     * @param string $key
     * @param string $default
     * @param object $config
     * @param object $request
     * @return string
     */
    private function resolveUrl(string $key, string $default, object $config, object $request): string
    {
        if (!empty($request->post[$key])) {
            return $request->post[$key];
        }

        $stored = $config->get($key);

        if (!empty($stored)) {
            return $stored;
        }

        $this->config->set($key, $default);

        return $default;
    }

    /**
     * @param object $config
     * @param object $request
     * @return array
     */
    private function getOrderStatuses(object $config, object $request): array
    {
        $this->load->model('localisation/order_status');
        $post = $request->post;

        return [
            'order_statuses' => $this->model_localisation_order_status->getOrderStatuses(),
            'payment_alliance_paid_status' => $post['payment_alliance_paid_status']
                ?? $config->get('payment_alliance_paid_status'),
            'payment_alliance_pending_status' => $post['payment_alliance_pending_status']
                ?? $config->get('payment_alliance_pending_status'),
            'payment_alliance_error_status' => $post['payment_alliance_error_status']
                ?? $config->get('payment_alliance_error_status'),
            'payment_alliance_refunded_status' => $post['payment_alliance_refunded_status']
                ?? $config->get('payment_alliance_refunded_status'),
            'payment_alliance_refund_fail_status' => $post['payment_alliance_refund_fail_status']
                ?? $config->get('payment_alliance_refund_fail_status'),
            'payment_alliance_preauth_status' => $post['payment_alliance_preauth_status']
                ?? $config->get('payment_alliance_preauth_status'),
            'payment_alliance_completion_status' => $post['payment_alliance_completion_status']
                ?? $config->get('payment_alliance_completion_status'),
        ];
    }

    /**
     * @param object $session
     * @return array
     */
    private function getLayoutBlocks(object $session): array
    {
        $token = $session->data['user_token'];

        return [
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
            'back' => $this->url->link(
                'marketplace/extension',
                'user_token=' . $token . '&type=payment',
                'SSL'
            ),
            'url_clear' => $this->url->link(
                'extension/payment/alliance/clear',
                'user_token=' . $token,
                'SSL'
            ),
        ];
    }

    /**
     * @param object $session
     * @return array
     */
    private function getNotifications(object $session): array
    {
        $errorWarning = $this->error['warning'] ?? $session->data['warning'] ?? '';
        if (isset($session->data['warning'])) {
            unset($session->data['warning']);
        }

        $success = '';
        if (isset($session->data['success'])) {
            $success = $session->data['success'];
            unset($session->data['success']);
        }

        return [
            'error_warning' => $errorWarning,
            'success' => $success,
            'error_status' => $this->error['status'] ?? '',
            'error_return_url' => $this->error['return_url'] ?? '',
        ];
    }
}
