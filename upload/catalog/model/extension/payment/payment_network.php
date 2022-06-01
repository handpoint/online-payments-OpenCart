<?php
class ModelExtensionPaymentPaymentNetwork extends Model {

	static private $url;
	static private $curi;

	public function __construct($params) {
		parent::__construct($params);
		$module = strtolower(basename(__FILE__, '.php'));
		self::$url = 'extension/payment/' . $module;
		self::$curi = 'payment_' . $module;
	}

	public function getMethod( $address, $total ) {

		$this->load->language(self::$url);

		$query = $this->db->query( "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" .
			(int)$this->config->get(self::$curi . '_geo_zone_id') . "' AND country_id = '" .
			(int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] .
			"' OR zone_id = '0')" );

		if ($this->config->get( self::$curi . '_total' ) > $total) {

			$status = false;

		} elseif (!$this->config->get(self::$curi . '_geo_zone_id')) {

			$status = true;

		} elseif ($query->num_rows) {

			$status = true;

		} else {

			$status = false;

		}

		$method_data = array();

		if ( $status ) {

			$method_data = array(
				'code'       => 'payment_network',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get(self::$curi . '_sort_order')
			);

		}

		return $method_data;

	}

}

?>
