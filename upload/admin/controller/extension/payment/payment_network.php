<?php

class ControllerExtensionPaymentPaymentNetwork extends Controller {

	private $error = array();

	static private $url;
	static private $curi;
	static private $token;

	public function __construct($registry) {
		parent::__construct($registry);
		$module = strtolower(basename(__FILE__, '.php'));
		self::$url = 'extension/payment/' . $module;
		self::$curi = 'payment_' . $module;
		self::$token = (isset($this->session->data['user_token']) ? '&user_token=' . $this->session->data['user_token'] : '');
	}

	public function index() {

		$this->load->language(self::$url);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			$this->model_setting_setting->editSetting(self::$curi, $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link(
				'marketplace/extension',
				self::$token . '&type=payment',
				true
			));

		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');

		// place holder texts
		$data['text_merchantid'] = $this->language->get('text_merchantid');
		$data['text_merchantsecret'] = $this->language->get('text_merchantsecret');
		$data['text_gateway_url'] = $this->language->get('text_gateway_url');

		$data['text_enabled']    = $this->language->get('text_enabled');
		$data['text_disabled']   = $this->language->get('text_disabled');
		$data['text_all_zones']  = $this->language->get('text_all_zones');
		$data['text_yes']        = $this->language->get('text_yes');
		$data['text_no']         = $this->language->get('text_no');
		$data['text_live']       = $this->language->get('text_live');
		$data['text_successful'] = $this->language->get('text_successful');
		$data['text_fail']       = $this->language->get('text_fail');

		$data['entry_merchantid']      = $this->language->get('entry_merchantid');
		$data['entry_merchantsecret']  = $this->language->get('entry_merchantsecret');
		$data['entry_gateway_url']     = $this->language->get('entry_gateway_url');
		$data['entry_order_status']    = $this->language->get('entry_order_status');
		$data['entry_geo_zone']        = $this->language->get('entry_geo_zone');
		$data['entry_form_responsive'] = $this->language->get('entry_form_responsive');
		$data['entry_status']          = $this->language->get('entry_status');
		$data['entry_sort_order']      = $this->language->get('entry_sort_order');

		$data['button_save']   = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		// module type selection
		$data['entry_module_type']   = $this->language->get('entry_module_type');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', self::$token, true),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_payment'),
			'href'      => $this->url->link('marketplace/extension', self::$token, true),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link(self::$url, self::$token, true),
			'separator' => ' :: '
		);


		$data['action'] =
			$this->url->link(self::$url, self::$token, true);

		$data['cancel'] =
			$this->url->link('marketplace/extension', self::$token, true);

		$fields = array(
			'permissions',
			'merchantid',
			'merchantsecret',
			'gateway_url',
			'status_id',
			'form_responsive',
			'sort_order',
			'module_type',
			'customer_wallets',
			'status',
			'order_status_success_id',
			'order_status_failed_id',
			'geo_zone_id'
		);

		foreach ($fields as $i=>$field) {
			if (isset($this->error[$field]) && !empty($field)) {
				$data['error_' . $field] = $this->error[$field];
			}

			$config = self::$curi . '_' . $field;

			if (isset($this->request->post[$config])) {
				$data['inputvalue_' . $field] = $this->request->post[$config];
			} else if ($this->config->has($config)) {
				$data['inputvalue_' . $field] = $this->config->get($config);
			} else {
				$data['inputvalue_' . $field] = '';
			}

			$data['inputname_' . $field] = self::$curi . '_' . $field;
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$this->template = self::$url;

		$this->children = array(
			'common/header',
			'common/footer'
		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view($this->template, $data), $data);

	}

    public function install() {
        $this->load->model('extension/payment/payment_network');

        $this->model_extension_payment_payment_network->install();
    }

    public function uninstall() {
        $this->load->model('extension/payment/payment_network');

        $this->model_extension_payment_payment_network->uninstall();
    }

	private function validate() {
		if (!$this->user->hasPermission('modify', self::$url)) {
			$this->error['permissions'] = true;
		}

		if (
			!(isset($this->request->post[self::$curi . '_merchantid']) &&
			!empty($this->request->post[self::$curi . '_merchantid']))
		) {
			$this->error['merchantid'] = true;
		}

		if (
			!(isset($this->request->post[self::$curi . '_merchantsecret']) &&
			!empty($this->request->post[self::$curi . '_merchantsecret']))
		) {
			$this->error['merchantsecret'] = true;
		}

		if (
			!(isset($this->request->post[self::$curi . '_gateway_url']) &&
			!empty($this->request->post[self::$curi . '_gateway_url']))
		) {
			$this->error['gateway_url'] = true;
		}

//		if (
//			!(isset($this->request->post[self::$curi . '_currencycode']) &&
//			is_numeric($this->request->post[self::$curi . '_currencycode']))
//		) {
//			$this->error['currencycode'] = true;
//		}
//
//		if (
//			!(isset($this->request->post[self::$curi . '_countrycode']) &&
//			is_numeric($this->request->post[self::$curi . '_countrycode']))
//		) {
//			$this->error['countrycode'] = true;
//		}

		if ($this->error) {
			$this->error['warning'] = true;
		}

		return !$this->error;
	}
}
