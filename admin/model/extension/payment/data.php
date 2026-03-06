<?php

class ModelExtensionPaymentData extends Model
{
    private $error = [];
    public function fillSettingData(object $session, object $config, object $request)
    {
        $this->load->language('extension/payment/alliance');

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_general'] = $this->language->get('text_general');
        $data['text_statuses'] = $this->language->get('text_statuses');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_paid_status'] = $this->language->get('entry_paid_status');
        $data['entry_confirmed_status'] = $this->language->get('entry_confirmed_status');
        $data['entry_complete_status'] = $this->language->get('entry_complete_status');
        $data['help_paid_status'] = $this->language->get('help_paid_status');
        $data['button_save'] = $this->language->get('button_save');

        $data['url_action'] = $this->url->link(
            'extension/payment/alliance',
            'user_token=' . $session->data['user_token'],
            'SSL'
        );

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link(
                'common/dashboard',
                'user_token=' . $session->data['user_token'],
                'SSL'
            ),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link(
                'marketplace/extension',
                'user_token=' .
                $session->data['user_token'] .
                '&type=payment',
                'SSL'
            ),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/payment/alliance',
                'user_token=' . $session->data['user_token'],
                'SSL'
            ),
        ];

        $data['payment_alliance_url'] =
            $request->post['payment_alliance_url']
            ?? $config->get('payment_alliance_url');

        $data['payment_alliance_service_code_id'] = isset(
            $request->post['payment_alliance_service_code_id']
        )
            ? $request->post['payment_alliance_service_code_id']
            : $config->get('payment_alliance_service_code_id');

        $data['payment_alliance_merchant_id'] = isset(
            $request->post['payment_alliance_merchant_id']
        )
            ? $request->post['payment_alliance_merchant_id']
            : $config->get('payment_alliance_merchant_id');

        $catalog_url = $this->config->get('config_url') ?: HTTP_CATALOG;

        if (empty($request->post['payment_alliance_fail_url'])
            && empty($config->get('payment_alliance_fail_url'))
        ) {
            $failUrl = $catalog_url . 'index.php?route=checkout/failure';
            $data['payment_alliance_fail_url'] = $failUrl;
            $this->config->set('payment_alliance_fail_url', $failUrl);
        } elseif (!empty($request->post['payment_alliance_fail_url'])) {
            $data['payment_alliance_fail_url'] = $request->post['payment_alliance_fail_url'];
        } else {
            $data['payment_alliance_fail_url'] = $config->get('payment_alliance_fail_url');
        }

        if (empty($request->post['payment_alliance_success_url'])
            && empty($config->get('payment_alliance_success_url'))
        ) {
            $successUrl = $catalog_url . 'index.php?route=checkout/success';
            $data['payment_alliance_success_url'] = $successUrl;
            $this->config->set('payment_alliance_success_url', $successUrl);
        } elseif (!empty($request->post['payment_alliance_success_url'])) {
            $data['payment_alliance_success_url'] = $request->post['payment_alliance_success_url'];
        } else {
            $data['payment_alliance_success_url'] = $config->get('payment_alliance_success_url');
        }

        $data['payment_alliance_status'] = isset(
            $request->post['payment_alliance_status']
        )
            ? $request->post['payment_alliance_status']
            : $config->get('payment_alliance_status');

        $data['payment_alliance_jwt_param_x'] = isset(
            $request->post['payment_alliance_jwt_param_x']
        )
            ? $request->post['payment_alliance_jwt_param_x']
            : $config->get('payment_alliance_jwt_param_x');

        $data['payment_alliance_jwt_param_y'] = isset(
            $request->post['payment_alliance_jwt_param_y']
        )
            ? $request->post['payment_alliance_jwt_param_y']
            : $config->get('payment_alliance_jwt_param_y');

        $data['payment_alliance_jwt_param_d'] = isset(
            $request->post['payment_alliance_jwt_param_d']
        )
            ? $request->post['payment_alliance_jwt_param_d']
            : $config->get('payment_alliance_jwt_param_d');

        // #ORDER STATUSES
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['payment_alliance_paid_status'] = isset(
            $request->post['payment_alliance_paid_status']
        )
            ? $request->post['payment_alliance_paid_status']
            : $config->get('payment_alliance_paid_status');

        $data['payment_alliance_pending_status'] = isset(
            $request->post['payment_alliance_pending_status']
        )
            ? $request->post['payment_alliance_pending_status']
            : $config->get('payment_alliance_pending_status');

        $data['payment_alliance_error_status'] = isset(
            $request->post['payment_alliance_error_status']
        )
            ? $request->post['payment_alliance_error_status']
            : $config->get('payment_alliance_error_status');
        
        $data['payment_alliance_refunded_status'] =
            $request->post['payment_alliance_refunded_status']
            ?? $config->get('payment_alliance_refunded_status');

        $data['payment_alliance_refund_fail_status'] =
            $request->post['payment_alliance_refund_fail_status']
            ?? $config->get('payment_alliance_refund_fail_status');


        $data['url_clear'] = $this->url->link(
            'extension/payment/alliance/clear',
            'user_token=' . $session->data['user_token'],
            'SSL'
        );

        // #LAYOUT
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['back'] = $this->url->link(
            'marketplace/extension',
            'user_token=' .
            $session->data['user_token'] .
            '&type=payment',
            'SSL'
        );

        // #NOTIFICATIONS
        $data['error_warning'] = '';
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } elseif (isset($session->data['warning'])) {
            $data['error_warning'] = $session->data['warning'];
            unset($session->data['warning']);
        } else {
            $data['error_warning'] = '';
        }

        $data['success'] = '';
        if (isset($session->data['success'])) {
            $data['success'] = $session->data['success'];
            unset($session->data['success']);
        }

        $data['error_status'] = '';
        if (isset($this->error['status'])) {
            $data['error_status'] = $this->error['status'];
        }

        $data['error_return_url'] = '';
        if (isset($this->error['return_url'])) {
            $data['error_return_url'] = $this->error['return_url'];
        }

        return $data;
    }
}
