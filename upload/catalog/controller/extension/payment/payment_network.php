<?php

require_once DIR_SYSTEM . 'library/payment_network/Gateway.php';
require_once DIR_SYSTEM . 'library/payment_network/ClientInterface.php';
require_once DIR_SYSTEM . 'library/payment_network/Client.php';
require_once DIR_SYSTEM . 'library/payment_network/AmountHelper.php';

/**
 * NOTE: OpenCart uses camelCase for functions/variables and
 *       snake_case for database fields (e.g. order_status_id).
 *
 *       Retrieved model data simply returns field name and their
 *       value in snake_case rather than camelCase otherwise used
 *       in OpenCart's PHP coding standards
 *
 *       Loaded models also use snake_case
 */
class ControllerExtensionPaymentPaymentNetwork extends Controller
{
    static private $url;
    static private $curi;
    static private $token;

    /**
     * @var \P3\SDK\Gateway
     */
    private $gateway;

    public function __construct($registry) {
        parent::__construct($registry);
        $module = strtolower(basename(__FILE__, '.php'));
        self::$url = 'extension/payment/' . $module;
        self::$curi = 'payment_' . $module;
        self::$token = (isset($this->session->data['user_token']) ? '&user_token=' . $this->session->data['user_token'] : '');

        $merchant_secret = $this->config->get(self::$curi . '_merchantsecret');
        $merchant_id = $this->config->get(self::$curi . '_merchantid');
        $gateway_url = $this->config->get(self::$curi . '_gateway_url');

        $this->gateway = new \P3\SDK\Gateway($merchant_id, $merchant_secret, $gateway_url);
    }

    public function index() {
        // Only load where the confirm action is asking us to show the form!
        if ($_REQUEST['route'] == 'checkout/confirm') {
            $this->load->language(self::$url);

            $integrationType = $this->config->get(self::$curi . '_module_type');

            if (in_array($integrationType, ['hosted', 'hosted_v2', 'hosted_v3'], true)) {
                return $this->createHostedForm($integrationType);
            }

            if (in_array($integrationType, ['direct'], true)) {
                return $this->createDirectForm();
            }
        } else {
            return new \Exception('Unauthorized!');
        }
    }

    private function createHostedForm($type = 'hosted') {
        $parameters = $this->captureOrder();
        $callback = $this->url->link(self::$url . '/callback', '', true).'&XDEBUG_SESSION_START=session_name';;
        $parameters['redirectURL'] = $callback;
        $parameters['callbackURL'] = $callback;

        $data['button_confirm'] = $this->language->get('button_confirm');

        $data['includeDeviceData'] = true;
        $data['isHostedModal'] = $type === 'hosted_v3';

        $merchant_secret = $this->config->get(self::$curi . '_merchantsecret');

        $parameters['signature'] = $this->gateway->sign($parameters, $merchant_secret);

        $data['formdata'] = $parameters;
        
        if (in_array($type, ['hosted', 'hosted_v3'], true)) {
            
            if ($type === 'hosted_v3') {
                $data['form_hosted_modal_url'] =  $this->getGatewayURL('hosted_v3');
            } else {
                $data['form_hosted_url'] =  $this->getGatewayURL('hosted');
            }
            return $this->load->view(self::$url . '_hosted', $data);
        }

        if (in_array($type, ['hosted_v2'], true)) {
            $data['form_hosted_url'] = $this->getGatewayURL('hosted_v2');
            return $this->load->view(self::$url . '_iframe', $data);
        }
    }

    private function createDirectForm() {
        $data['cc_card_number'] = $this->language->get('cc_card_number');
        $data['cc_card_start_date'] = $this->language->get('cc_card_start_date');
        $data['cc_card_expiry_date'] = $this->language->get('cc_card_expiry_date');
        $data['cc_card_cvv'] = $this->language->get('cc_card_cvv');
        $data['text_credit_card'] = $this->language->get('text_credit_card');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['process_url'] = $this->url->link(self::$url . '/process_direct_request', '', true);

        $this->document->addScript('catalog/view/javascript/payform.js');

        $deviceData = [
            'deviceChannel'				=> 'browser',
            'deviceIdentity'			=> (isset($_SERVER['HTTP_USER_AGENT']) ? htmlentities($_SERVER['HTTP_USER_AGENT']) : null),
            'deviceTimeZone'			=> '0',
            'deviceCapabilities'		=> '',
            'deviceScreenResolution'	=> '1x1x1',
            'deviceAcceptContent'		=> (isset($_SERVER['HTTP_ACCEPT']) ? htmlentities($_SERVER['HTTP_ACCEPT']) : null),
            'deviceAcceptEncoding'		=> (isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? htmlentities($_SERVER['HTTP_ACCEPT_ENCODING']) : null),
            'deviceAcceptLanguage'		=> (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? htmlentities($_SERVER['HTTP_ACCEPT_LANGUAGE']) : null),
            'deviceAcceptCharset'		=> (isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? htmlentities($_SERVER['HTTP_ACCEPT_CHARSET']) : null),
        ];

        $monthOptions = [];
        foreach (range(1, 12) as $value) {
            $monthOptions[str_pad($value, 2, '0', STR_PAD_LEFT)] = $value;
        }


        $yearOptions = [];

        foreach (range(date("Y"), date("Y") + 12) as $value) {
            $yearOptions[substr($value, 2)] = $value;
        }

        $data['deviceData'] = $deviceData;
        $data['monthOptions'] = $monthOptions;
        $data['yearOptions'] = $yearOptions;

        // Add direct gateway URL to direct form
     //   $data['process_url'] =  $this->getGatewayURL('direct');        

        return $this->load->view(self::$url . '_direct', $data);
    }

    public function process_direct_request(): array
    {
        $redirect_url = $this->url->link(self::$url . '/callback', '', true).'&XDEBUG_SESSION_START=session_name';

        if ($_SERVER['REQUEST_METHOD'] == 'POST'
            && isset($_POST['cardNumber'], $_POST['cardExpiryMonth'], $_POST['cardExpiryYear'], $_POST['cardCVV'], $_POST['browserInfo'])
        ) {
            $args = array_merge(
                $this->captureOrder(),
                $_POST['browserInfo'],
                [
                    'cardNumber'           => str_replace(' ', '', $_POST['cardNumber']),
                    'cardExpiryMonth'      => $_POST['cardExpiryMonth'],
                    'cardExpiryYear'       => $_POST['cardExpiryYear'],
                    'cardCVV'              => $_POST['cardCVV'],
                    'threeDSRedirectURL'   => $redirect_url,
                    'threeDSVersion' => 2,
                ]
            );

            $response = $this->gateway->directRequest($args);
            setcookie('xref', $response['xref'], time()+315);

            $this->callback($response);
        }

        throw new InvalidArgumentException('Something went wrong with processing direct request, please check $_POST data');
    }

    public function callback($response = null)
    {
        $merchant_id = $this->config->get(self::$curi . '_merchantid');

        // v1
        if (isset($_REQUEST['MD'], $_REQUEST['PaRes'])) {
            $req = array(
                'action'	   => 'SALE',
                'merchantID'   => $merchant_id,
                'xref'         => $_COOKIE['xref'],
                'threeDSMD'    => $_REQUEST['MD'],
                'threeDSPaRes' => $_REQUEST['PaRes'],
                'threeDSPaReq' => ($_REQUEST['PaReq'] ?? null),
            );

            $response = $this->gateway->directRequest($req);
        }

        // v2
        if (isset($_POST['threeDSMethodData']) || isset($_POST['cres'])) {
            $req = array(
                'merchantID' => $merchant_id,
                'action' => 'SALE',
                // The following field must be passed to continue the 3DS request
                'threeDSRef' => $_COOKIE['threeDSRef'],
                'threeDSResponse' => $_POST,
            );

            $response = $this->gateway->directRequest($req);
        }

        $data = $response ?? $_POST;

        try {
            $this->createWallet($data);

            $this->gateway->verifyResponse($data, [$this, 'on_three_ds_required'], [$this, 'on_successful_transaction']);
        } catch (\Exception $exception) {

            if (isset($data)) {
                $this->on_failed_transaction($data);
            }
            $url = $this->url->link('checkout/failure', '', true) . self::$token;
            $this->redirect($url);
        }
    }

    /**
     * @return array
     */
    private function captureOrder()
    {
        $merchant_id = $this->config->get(self::$curi . '_merchantid');
        $form_responsive = $this->config->get(self::$curi . '_form_responsive');

        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $amount = $this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false);
        $amount = \P3\SDK\AmountHelper::calculateAmountByCurrency($amount, $order['currency_code']);

        $trans_id = $this->session->data['order_id'];
        $bill_name = str_replace('&amp;', '&', $order['payment_firstname'] . ' ' . $order['payment_lastname']);

        $bill_address = str_replace('&amp;', '&', $order['payment_address_1']);
        $addressFields = [
            'payment_address_2',
            'payment_city',
            'payment_zone',
            'payment_country'
        ];

        foreach ($addressFields as $item) {
            if (!empty($order[$item])) {
                $bill_address .= "\n". str_replace('&amp;', '&', $order[$item]);
            }
        }

        $params = array(
            "action" => "SALE",
            "merchantID" => $merchant_id,
            "amount" => $amount,
            'type' => 1,
            "countryCode" => $order['payment_iso_code_2'],
            "currencyCode" => $order['currency_code'],
            "transactionUnique" => $trans_id,
            "orderRef" => "Order " . $trans_id,
            "customerName" => $bill_name,
            "customerAddress" => $bill_address,
            "customerPostCode" => $order['payment_postcode'],
            "customerEmail" => $order['email'],
            "customerPhone" => @$order['telephone'],
            "item1Description" => "Order " . $trans_id,
            "item1Quantity" => 1,
            "item1GrossValue" => $amount,
            "formResponsive" => $form_responsive,
            'merchantCategoryCode' => 5411,
            'customerOCSESSID' => $this->session->getId()
        );

        if (filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $params['remoteAddress'] = $_SERVER['REMOTE_ADDR'];
        }

        /**
         * Wallets
         */
        if (
            $this->config->get(self::$curi . '_customer_wallets') === 'Y'
            && $this->config->get(self::$curi . '_module_type') === 'hosted_v3'
            && $this->customer->isLogged()
        ) {

            $table_name = DB_PREFIX . 'payment_network_wallets';
            $sql = <<<SQL
SELECT * FROM `$table_name` WHERE merchant_id = '{$this->db->escape($params['merchantID'])}' AND customer_email = '{$this->db->escape($params['customerEmail'])}'
SQL;

            $wallets = $this->db->query($sql);

            //If the customer wallet record exists.
            if ($wallets->num_rows > 0)
            {
                //Add walletID to request.
                $params['walletID'] = $wallets->rows[0]['wallet_id'];
            } else {
                //Create a new wallet.
                $params['walletStore'] = 'Y';
            }
            $params['walletEnabled'] = 'Y';
            $params['walletRequired'] = 'Y';
        }

        return $params;
    }

    /**
     * Wallet creation.
     *
     * A wallet will always be created if a walletID is returned. Even if payment fails.
     */
    protected function createWallet($response) {
        $this->session->start($response['customerOCSESSID']);
        $this->registry->set('customer', new Cart\Customer($this->registry));

        //when the wallets is enabled, the user is logged in and there is a wallet ID in the response.
        if ($this->config->get(self::$curi . '_customer_wallets') === 'Y'
            && $this->config->get(self::$curi . '_module_type') === 'hosted_v3'
            && $this->customer->isLogged()
            && isset($response['walletID'])) {

            $walletID = $this->db->escape($response['walletID']);
            $merchantID = $this->config->get(self::$curi . '_merchantid');
            $customerEmail = $this->db->escape($this->customer->getEmail());

            $table_name = DB_PREFIX . 'payment_network_wallets';

            $sql = <<<SQL
SELECT * FROM `$table_name` WHERE merchant_id = '$merchantID' AND customer_email = '$customerEmail'
SQL;

            $wallets = $this->db->query($sql);

            //If the customer wallet record does not exists.
            if ($wallets->num_rows == 0) {
                //Add walletID to request.
                $sql = <<<SQL
INSERT INTO `$table_name` (`merchant_id`, `customer_email`, `wallet_id`) VALUES ('$merchantID', '$customerEmail', '$walletID');
SQL;
                $this->db->query($sql);
            }
        }
    }

    public function on_failed_transaction($response) {
        if (isset($response['transactionUnique'], $response['responseCode'])) {
            $orderId = $response['transactionUnique'];
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder($orderId);

            $orderMessage = ($response['responseCode'] == "0" ? "Payment Successful" : "Payment Unsuccessful") . "<br/><br/>" .
                "Message: " . $response['responseMessage'] . "<br/>" .
                "xref: " . $response['xref'] . "<br/>";

            $status = $this->config->get(self::$curi . '_order_status_failed_id');
            if ($order['order_status_id'] != $status) {
                $this->model_checkout_order->addOrderHistory(
                    $orderId,
                    $this->config->get(self::$curi . '_order_status_failed_id'),
                    $orderMessage,
                    true
                );

                $this->registry->set('cart', new Cart\Cart($this->registry));
                $this->cart->clear();
            }
        }
    }

    public function on_three_ds_required($threeDSVersion, $res) {
        setcookie('threeDSRef', $res['threeDSRef'], time()+315);

        // check for version
        echo $this->gateway->silentPost($res['threeDSURL'], $res['threeDSRequest']);

        if ($threeDSVersion >= 200) {
            // Silently POST the 3DS request to the ACS in the IFRAME

            // Remember the threeDSRef as need it when the ACS responds
            $_SESSION['threeDSRef'] = $res['threeDSRef'];
        }

        exit();
    }

    public function on_successful_transaction($data)
    {
        $this->load->model('checkout/order');
        $orderId = $data['transactionUnique'];
        $order = $this->model_checkout_order->getOrder($orderId);

        if ($order['order_status_id'] == 0) {
            $msg = "Payment " . ($data['responseCode'] == 0 ? "Successful" : "Unsuccessful") . "<br/><br/>" .
                "Amount Received: " . (isset($data['amountReceived']) ? floatval($data['amountReceived']) / 100 : 'Unknown') . "<br/>" .
                "Message: \"" . ucfirst($data['responseMessage']) . "\"</br>" .
                "Xref: " . $data['xref'] . "<br/>" .
                (isset($data['cv2Check']) ? "CV2 Check: " . ucfirst($data['cv2Check']) . "</br>": '') .
                (isset($data['addressCheck']) ? "Address Check: " . ucfirst($data['addressCheck']) . "</br>": '') .
                (isset($data['postcodeCheck']) ? "Postcode Check: " . ucfirst($data['postcodeCheck']) . "</br>": '');

            if (isset($data['threeDSEnrolled'])) {
                switch ($data['threeDSEnrolled']) {
                    case "Y":
                        $enrolledtext = "Enrolled.";
                        break;
                    case "N":
                        $enrolledtext = "Not Enrolled.";
                        break;
                    case "U";
                        $enrolledtext = "Unable To Verify.";
                        break;
                    case "E":
                        $enrolledtext = "Error Verifying Enrolment.";
                        break;
                    default:
                        $enrolledtext = "Integration unable to determine enrolment status.";
                        break;
                }
                $msg .= "<br />3D Secure enrolled check outcome: \"" . $enrolledtext . "\"";
            }

            if (isset($data['threeDSAuthenticated'])) {
                switch ($data['threeDSAuthenticated']) {
                    case "Y":
                        $authenticatedtext = "Authentication Successful";
                        break;
                    case "N":
                        $authenticatedtext = "Not Authenticated";
                        break;
                    case "U";
                        $authenticatedtext = "Unable To Authenticate";
                        break;
                    case "A":
                        $authenticatedtext = "Attempted Authentication";
                        break;
                    case "E":
                        $authenticatedtext = "Error Checking Authentication";
                        break;
                    default:
                        $authenticatedtext = "Integration unable to determine authentication status.";
                        break;
                }
                $msg .= "<br />3D Secure authenticated check outcome: \"" . $authenticatedtext . "\"";
            }

            $this->model_checkout_order->addOrderHistory(
                $data['transactionUnique'],
                $this->config->get(self::$curi . '_order_status_success_id'),
                $msg,
                true
            );

            $this->registry->set('cart', new Cart\Cart($this->registry));
            $this->cart->clear();
        }
        $url = $this->url->link('checkout/success', '', true) . self::$token;

        $this->redirect($url);
    }

    /**
     * @param string $url
     */
    public function redirect(string $url): void
    {
        $isIframe = $this->config->get(self::$curi . '_module_type') == 'hosted_v2';
        if ($isIframe) {
            $this->response->setOutput("<script>top.location = '$url';</script>");
        } else {
            $this->response->redirect($url);
        }
    }

    /**
     * Get Gateway URL
     * 
     * This function get the correct getway URL based on the intergration type
     * 
     * @param string $intergrationType
     */
    protected function getGatewayURL(string $intergrationType) {

        // Get base URL and trim any ending slashes
        $baseURL = $this->config->get(self::$curi . '_gateway_url');
        $baseURL = trim($baseURL,'/\\');

        // Switch on the intergration type to retrieve the correct URL
        switch($intergrationType) {
            // Hosted form intergration names
            case 'Hosted' :
            case 'hosted' : 
            case 'hosted_v2' :
                // If paymentform/* is in the base URL just return
                // it as is. Other wise append /hosted/
                if(preg_match('/paymentform(\/.*)?/', $baseURL)) {
                    return $baseURL;
                } else {                
                    return $baseURL . '/hosted/';
                }
            break;
            // HFv2 intergration names
            case 'hfv2' :
            case 'Modal' :
            case 'hosted_v3' :
                $url = preg_replace('/paymentform(\/.*)?/', '', $baseURL, 1);
                $url = trim($url, '/\\');
                return $url . '/hosted/modal/';
            break;
            default :
                throw new \Exception('Intergration type unknown');
            break;
        }
    }
}
