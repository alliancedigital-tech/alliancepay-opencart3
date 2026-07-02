<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class FixSessionOrderId.
 */
class ControllerExtensionPaymentEventFixSessionOrderId extends Controller
{
    /**
     * @param $route
     * @param $args
     * @param $output
     * @return void
     */
    public function afterAddOrder(&$route, &$args, &$output)
    {
        if (!empty($output) && is_numeric($output) && empty($this->session->data['order_id'])) {
            $this->session->data['order_id'] = (int)$output;
        }
    }
}
