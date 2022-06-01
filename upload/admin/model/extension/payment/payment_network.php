<?php

class ModelExtensionPaymentPaymentNetwork extends Model {
    public function install() {
        $this->db->query("
			CREATE TABLE `" . DB_PREFIX . "payment_network_wallets` (
				`merchant_id` int(11) NOT NULL,
				`customer_email` varchar(255) NOT NULL,
				`wallet_id` int(11) NOT NULL,
				PRIMARY KEY(`merchant_id`, `customer_email`, `wallet_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "payment_network_wallets`;");
    }
}
