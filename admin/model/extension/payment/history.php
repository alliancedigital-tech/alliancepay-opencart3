<?php

/**
 * Class ModelExtensionPaymentHistory.
 */
class ModelExtensionPaymentHistory extends Model
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->library('RefundHistoryMapper');
    }

    /**
     * @param array $data
     * @param int $orderId
     * @return void
     */
    public function updateRefundHistory(array $data, int $orderId)
    {
        if (!empty($data) && !empty($orderId)) {
            $sql = "INSERT INTO `" . DB_PREFIX . "alliance_integration_order_refund` SET";

            $implode = [];
            $implode[] = "`order_id` = '" . $orderId . "'";

            foreach (RefundHistoryMapper::REFUND_DATA_HISTORY_FIELDS_MAPPING as $key => $dbField) {
                if (empty($data[$key])) {
                    continue;
                }
                $value = in_array($key, RefundHistoryMapper::JSON_ENCODED_FIELDS_LIST)
                    ? json_encode($data[$key])
                    : (string) $data[$key];
                $implode[] = sprintf("`%s` = '%s'", $dbField, $this->db->escape($value));
            }

            if ($implode) {
                $sql .= implode(", ", $implode);
            }

            $this->db->query($sql);
        }
    }

    /**
     * @param int $orderId
     * @return array|null
     */
    public function getRefundOrder(int $orderId): ?array
    {
        $query = $this->db->query("SELECT * FROM `"
            . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '"
            . $orderId . "'"
        );

        return $query->rows;
    }

    public function getRefundOperationsTotal(int $orderId): int
    {
        $refundTotal = 0;

        $query = $this->db->query("SELECT coin_amount FROM `"
            . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '"
            . $orderId . "'"
        );

        foreach ($query->rows as $row) {
            $refundTotal += $row['coin_amount'];
        }

        return $refundTotal;
    }

    /**
     * @param int $orderId
     * @param int $orderStatusId
     * @param string $comment
     * @param int $orderNotify
     * @return void
     */
    public function updateOrderHistoryAndStatus(
        int $orderId,
        int $orderStatusId,
        string $comment = '',
        int $orderNotify = 0
    ) {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . $orderStatusId
            . "', date_modified = NOW() WHERE order_id = '" . (int)$orderId . "'");

        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET order_id = '" . $orderId
            . "',order_status_id = '" . $orderStatusId . "',notify = '" . $orderNotify
            . "',comment = '" . $this->db->escape($comment)
            . "',date_added = NOW()");
    }
}
