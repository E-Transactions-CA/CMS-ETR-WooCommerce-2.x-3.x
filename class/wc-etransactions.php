<?php

/**
 * E-Transactions - Main class.
 *
 * @class   WC_Etransactions
 */
class WC_Etransactions
{
    private $_config;
    private $_currencyDecimals = array(
        '008' => 2,
        '012' => 2,
        '032' => 2,
        '036' => 2,
        '044' => 2,
        '048' => 3,
        '050' => 2,
        '051' => 2,
        '052' => 2,
        '060' => 2,
        '064' => 2,
        '068' => 2,
        '072' => 2,
        '084' => 2,
        '090' => 2,
        '096' => 2,
        '104' => 2,
        '108' => 0,
        '116' => 2,
        '124' => 2,
        '132' => 2,
        '136' => 2,
        '144' => 2,
        '152' => 0,
        '156' => 2,
        '170' => 2,
        '174' => 0,
        '188' => 2,
        '191' => 2,
        '192' => 2,
        '203' => 2,
        '208' => 2,
        '214' => 2,
        '222' => 2,
        '230' => 2,
        '232' => 2,
        '238' => 2,
        '242' => 2,
        '262' => 0,
        '270' => 2,
        '292' => 2,
        '320' => 2,
        '324' => 0,
        '328' => 2,
        '332' => 2,
        '340' => 2,
        '344' => 2,
        '348' => 2,
        '352' => 0,
        '356' => 2,
        '360' => 2,
        '364' => 2,
        '368' => 3,
        '376' => 2,
        '388' => 2,
        '392' => 0,
        '398' => 2,
        '400' => 3,
        '404' => 2,
        '408' => 2,
        '410' => 0,
        '414' => 3,
        '417' => 2,
        '418' => 2,
        '422' => 2,
        '426' => 2,
        '428' => 2,
        '430' => 2,
        '434' => 3,
        '440' => 2,
        '446' => 2,
        '454' => 2,
        '458' => 2,
        '462' => 2,
        '478' => 2,
        '480' => 2,
        '484' => 2,
        '496' => 2,
        '498' => 2,
        '504' => 2,
        '504' => 2,
        '512' => 3,
        '516' => 2,
        '524' => 2,
        '532' => 2,
        '532' => 2,
        '533' => 2,
        '548' => 0,
        '554' => 2,
        '558' => 2,
        '566' => 2,
        '578' => 2,
        '586' => 2,
        '590' => 2,
        '598' => 2,
        '600' => 0,
        '604' => 2,
        '608' => 2,
        '634' => 2,
        '643' => 2,
        '646' => 0,
        '654' => 2,
        '678' => 2,
        '682' => 2,
        '690' => 2,
        '694' => 2,
        '702' => 2,
        '704' => 0,
        '706' => 2,
        '710' => 2,
        '728' => 2,
        '748' => 2,
        '752' => 2,
        '756' => 2,
        '760' => 2,
        '764' => 2,
        '776' => 2,
        '780' => 2,
        '784' => 2,
        '788' => 3,
        '800' => 2,
        '807' => 2,
        '818' => 2,
        '826' => 2,
        '834' => 2,
        '840' => 2,
        '858' => 2,
        '860' => 2,
        '882' => 2,
        '886' => 2,
        '901' => 2,
        '931' => 2,
        '932' => 2,
        '934' => 2,
        '936' => 2,
        '937' => 2,
        '938' => 2,
        '940' => 0,
        '941' => 2,
        '943' => 2,
        '944' => 2,
        '946' => 2,
        '947' => 2,
        '948' => 2,
        '949' => 2,
        '950' => 0,
        '951' => 2,
        '952' => 0,
        '953' => 0,
        '967' => 2,
        '968' => 2,
        '969' => 2,
        '970' => 2,
        '971' => 2,
        '972' => 2,
        '973' => 2,
        '974' => 0,
        '975' => 2,
        '976' => 2,
        '977' => 2,
        '978' => 2,
        '979' => 2,
        '980' => 2,
        '981' => 2,
        '984' => 2,
        '985' => 2,
        '986' => 2,
        '990' => 0,
        '997' => 2,
        '998' => 2,
    );

    private $_errorCode = array(
        '00000' => 'Successful operation',
        '00001' => 'Payment system not available',
        '00003' => 'Paybor error',
        '00004' => 'Card number or invalid cryptogram',
        '00006' => 'Access denied or invalid identification',
        '00008' => 'Invalid validity date',
        '00009' => 'Subscription creation failed',
        '00010' => 'Unknown currency',
        '00011' => 'Invalid amount',
        '00015' => 'Payment already done',
        '00016' => 'Existing subscriber',
        '00021' => 'Unauthorized card',
        '00029' => 'Invalid card',
        '00030' => 'Timeout',
        '00033' => 'Unauthorized IP country',
        '00040' => 'No 3D Secure',
    );

    private $_resultMapping = array(
        'M' => 'amount',
        'R' => 'reference',
        'T' => 'call',
        'A' => 'authorization',
        'B' => 'subscription',
        'C' => 'cardType',
        'D' => 'validity',
        'E' => 'error',
        'F' => '3ds',
        'G' => '3dsWarranty',
        'H' => 'imprint',
        'I' => 'ip',
        'J' => 'lastNumbers',
        'K' => 'sign',
        'N' => 'firstNumbers',
        'O' => '3dsInlistment',
        'o' => 'celetemType',
        'P' => 'paymentType',
        'Q' => 'time',
        'S' => 'transaction',
        'U' => 'subscriptionData',
        'W' => 'date',
        'Y' => 'country',
        'Z' => 'paymentIndex',
        'v' => '3dsVersion',
    );

    public function __construct(WC_Etransactions_Config $config)
    {
        $this->_config = $config;
    }

    public function addCartErrorMessage($message)
    {
        wc_add_notice($message, 'error');
    }

    public function addOrderNote(WC_Order $order, $message)
    {
        $order->add_order_note($message);
    }

    public function addOrderPayment(WC_Order $order, $type, array $data)
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'wc_etransactions_payment', array(
            'order_id' => $order->get_id(),
            'type' => $type,
            'data' => serialize($data),
        ));
    }

    /**
     * Retrieve the language value for PBX_LANG parameter
     *
     * @return string
     */
    protected function getPbxLang()
    {
        // Choose correct language
        $lang = get_locale();
        if (!empty($lang)) {
            $lang = preg_replace('#_.*$#', '', $lang);
        }
        $languages = $this->getLanguages();
        if (!array_key_exists($lang, $languages)) {
            $lang = 'default';
        }

        return $languages[$lang];
    }

    /**
     * @params WC_Order $order Order
     * @params string $type Type of payment (standard or threetime)
     * @params array $additionalParams Additional parameters
     */
    public function buildSystemParams(WC_Order $order, $type, array $additionalParams = array())
    {
        global $wpdb;

        // Parameters
        $values = array();

        // Retrieve the current card that was forced on the order (if any)
        $card = $this->_config->getOrderCard($order);
        // Retrieve the tokenized card (if any)
        $tokenizedCard = $this->_config->getTokenizedCard($order);

        // Merchant information
        $values['PBX_SITE'] = $this->_config->getSite();
        $values['PBX_RANG'] = $this->_config->getRank();
        $values['PBX_IDENTIFIANT'] = $this->_config->getIdentifier();
        $values['PBX_VERSION'] = WC_ETRANSACTIONS_PLUGIN . "-" . WC_ETRANSACTIONS_VERSION . "_WP" . get_bloginfo('version') . "_WC" . WC()->version;

        // Order information
        $values['PBX_PORTEUR'] = $this->getBillingEmail($order);
        $values['PBX_DEVISE'] = $this->getCurrency();
        $values['PBX_CMD'] = $order->get_id().' - '.$this->getBillingName($order);

        // Amount
        $orderAmount = floatval($order->get_total());
        $amountScale = $this->_currencyDecimals[$values['PBX_DEVISE']];
        $amountScale = pow(10, $amountScale);
        switch ($type) {
            case 'standard':
                $delay = $this->_config->getDelay();

                // Debit on specific order status, force authorization only
                if ($this->_config->isPremium()
                && $delay === WC_Etransactions_Config::ORDER_STATE_DELAY) {
                    // Author only
                    $values['PBX_AUTOSEULE'] = 'O';
                }

                // Classic delay
                if ($delay != WC_Etransactions_Config::ORDER_STATE_DELAY) {
                    // The current card is not able to handle PBX_DIFF parameter
                    if (!empty($card->id_card) && empty($card->debit_differe)) {
                        // Reset the delay
                        $delay = 0;
                    }
                    // Delay must be between 0 & 7
                    $delay = max(0, min($delay, 7));
                    if ($delay > 0) {
                        $values['PBX_DIFF'] = sprintf('%02d', $delay);
                    }
                }

                $values['PBX_TOTAL'] = sprintf('%03d', round($orderAmount * $amountScale));
                break;

            case 'threetime':
                // Compute each payment amount
                $step = round($orderAmount * $amountScale / 3);
                $firstStep = ($orderAmount * $amountScale) - 2 * $step;
                $values['PBX_TOTAL'] = sprintf('%03d', $firstStep);
                $values['PBX_2MONT1'] = sprintf('%03d', $step);
                $values['PBX_2MONT2'] = sprintf('%03d', $step);

                // Payment dates
                $now = new DateTime();
                $now->modify('1 month');
                $values['PBX_DATE1'] = $now->format('d/m/Y');
                $now->modify('1 month');
                $values['PBX_DATE2'] = $now->format('d/m/Y');

                // Force validity date of card
                $values['PBX_DATEVALMAX'] = $now->format('ym');
                break;

            default:
                $message  = __('Unexpected type %s', WC_ETRANSACTIONS_PLUGIN);
                $message = sprintf($message, $type);
                throw new Exception($message);
        }

        // E-Transactions => Magento
        $values['PBX_RETOUR'] = 'M:M;R:R;T:T;A:A;B:B;C:C;D:D;E:E;F:F;G:G;I:I;J:J;N:N;O:O;P:P;Q:Q;S:S;W:W;Y:Y;v:v;K:K';
        $values['PBX_RUF1'] = 'POST';

        // Allow tokenization ?
        $allowTokenization = (bool)get_post_meta($order->get_id(), $order->get_payment_method() . '_allow_tokenization', true);
        if (empty($tokenizedCard) && $this->_config->allowOneClickPayment($card) && $allowTokenization) {
            $values['PBX_REFABONNE'] = wp_hash($order->get_id() . '-' . $order->get_customer_id());
            $values['PBX_RETOUR'] = 'U:U;' . $values['PBX_RETOUR'];
        }

        // Add tokenized card information
        if (!empty($tokenizedCard)) {
            $cardToken = explode('|', $tokenizedCard->get_token());
            $values['PBX_REFABONNE'] = $cardToken[0];
            $values['PBX_TOKEN'] = $cardToken[1];
            $values['PBX_DATEVAL'] = sprintf('%02d', $tokenizedCard->get_expiry_month()) . sprintf('%02d', substr($tokenizedCard->get_expiry_year(), 2, 2));
        }

        // 3DSv2 parameters
        $values['PBX_SHOPPINGCART'] = $this->getXmlShoppingCartInformation($order);
        $values['PBX_BILLING'] = $this->getXmlBillingInformation($order);

        // Choose correct language
        $values['PBX_LANGUE'] = $this->getPbxLang();
        // Prevent PBX_SOURCE to be sent when card type is LIMONETIK
        if (empty($card->type_payment) || $card->type_payment != 'LIMONETIK') {
            $values['PBX_SOURCE'] = 'RWD';
        }

        if ($this->_config->getPaymentUx($order) == WC_Etransactions_Config::PAYMENT_UX_SEAMLESS) {
            $values['PBX_THEME_CSS'] = 'frame-puma.css';
        }

        // Misc.
        $values['PBX_TIME'] = date('c');
        $values['PBX_HASH'] = strtoupper($this->_config->getHmacAlgo());

        // Specific parameter to set a specific payment method
        if (!empty($card->id_card)) {
            $values['PBX_TYPEPAIEMENT'] = $card->type_payment;
            $values['PBX_TYPECARTE'] = $card->type_card;
        }

        // Adding additionnal informations
        $values = array_merge($values, $additionalParams);

        // Sort parameters for simpler debug
        ksort($values);

        // Sign values
        $sign = $this->signValues($values);

        // Hash HMAC
        $values['PBX_HMAC'] = $sign;

        return $values;
    }

    /**
     * Build parameters in order to create a token for a card
     *
     * @param object $card
     * @param array $additionalParams
     * @return void
     */
    public function buildTokenizationSystemParams($card = null, array $additionalParams = array())
    {
        global $wpdb;

        // Parameters
        $values = array();

        // Merchant information
        $values['PBX_SITE'] = $this->_config->getSite();
        $values['PBX_RANG'] = $this->_config->getRank();
        $values['PBX_IDENTIFIANT'] = $this->_config->getIdentifier();
        $values['PBX_VERSION'] = WC_ETRANSACTIONS_PLUGIN . "-" . WC_ETRANSACTIONS_VERSION . "_WP" . get_bloginfo('version') . "_WC" . WC()->version;

        // "Order" information
        $apmId = uniqid();
        $values['PBX_PORTEUR'] = $this->getBillingEmail(WC()->customer);
        $values['PBX_REFABONNE'] = wp_hash($apmId . '-' . get_current_user_id());
        $values['PBX_DEVISE'] = $this->getCurrency();
        $values['PBX_CMD'] = 'APM-' . get_current_user_id() . '-' . $apmId;

        // Amount
        $orderAmount = floatval(1.0);
        $amountScale = pow(10, $this->_currencyDecimals[$values['PBX_DEVISE']]);
        // Author only
        $values['PBX_AUTOSEULE'] = 'O';
        $values['PBX_TOTAL'] = sprintf('%03d', round($orderAmount * $amountScale));
        $values['PBX_RETOUR'] = 'U:U;M:M;R:R;T:T;A:A;B:B;C:C;D:D;E:E;F:F;G:G;I:I;J:J;N:N;O:O;P:P;Q:Q;S:S;W:W;Y:Y;v:v;K:K';
        $values['PBX_RUF1'] = 'POST';

        // 3DSv2 parameters
        $values['PBX_SHOPPINGCART'] = $this->getXmlShoppingCartInformation();
        $values['PBX_BILLING'] = $this->getXmlBillingInformation(WC()->customer);

        // Choose correct language
        $values['PBX_LANGUE'] = $this->getPbxLang();
        // Prevent PBX_SOURCE to be sent when card type is LIMONETIK
        if (empty($card->type_payment) || $card->type_payment != 'LIMONETIK') {
            $values['PBX_SOURCE'] = 'RWD';
        }

        // Misc.
        $values['PBX_TIME'] = date('c');
        $values['PBX_HASH'] = strtoupper($this->_config->getHmacAlgo());

        // Specific parameter to set a specific payment method
        if (!empty($card->id_card)) {
            $values['PBX_TYPEPAIEMENT'] = $card->type_payment;
            $values['PBX_TYPECARTE'] = $card->type_card;
        }

        // Adding additionnal informations
        $values = array_merge($values, $additionalParams);

        // Sort parameters for simpler debug
        ksort($values);

        // Sign values
        $sign = $this->signValues($values);

        // Hash HMAC
        $values['PBX_HMAC'] = $sign;

        return $values;
    }

    /**
     * Retrieve keys used for the mapping
     *
     * @return array
     */
    public function getParametersKeys()
    {
        return array_keys($this->_resultMapping);
    }

    public function convertParams(array $params)
    {
        $result = array();
        foreach ($this->_resultMapping as $param => $key) {
            if (isset($params[$param])) {
                $result[$key] = utf8_encode($params[$param]);
            }
        }

        return $result;
    }

    public function getBillingEmail($object)
    {
        if (!is_a($object, 'WC_Order') && !is_a($object, 'WC_Customer')) {
            throw new Exception('Invalid object on getXmlBillingInformation');
        }

        return $object->get_billing_email();
    }

    public function getBillingName($object)
    {
        if (!is_a($object, 'WC_Order') && !is_a($object, 'WC_Customer')) {
            throw new Exception('Invalid object on getXmlBillingInformation');
        }

        $name = $object->get_billing_first_name().' '.$object->get_billing_last_name();
        $name = remove_accents($name);
        $name = trim(preg_replace('/[^-. a-zA-Z0-9]/', '', $name));
        return $name;
    }

    /**
     * Format a value to respect specific rules
     *
     * @param string $value
     * @param string $type
     * @param int $maxLength
     * @return string
     */
    protected function formatTextValue($value, $type, $maxLength = null)
    {
        /*
        AN : Alphanumerical without special characters
        ANP : Alphanumerical with spaces and special characters
        ANS : Alphanumerical with special characters
        N : Numerical only
        A : Alphabetic only
        */

        switch ($type) {
            default:
            case 'AN':
                $value = remove_accents($value);
                break;
            case 'ANP':
                $value = remove_accents($value);
                $value = preg_replace('/[^-. a-zA-Z0-9]/', '', $value);
                break;
            case 'ANS':
                break;
            case 'N':
                $value = preg_replace('/[^0-9.]/', '', $value);
                break;
            case 'A':
                $value = remove_accents($value);
                $value = preg_replace('/[^A-Za-z]/', '', $value);
                break;
        }
        // Remove carriage return characters
        $value = trim(preg_replace("/\r|\n/", '', $value));

        // Cut the string when needed
        if (!empty($maxLength) && is_numeric($maxLength) && $maxLength > 0) {
            if (function_exists('mb_strlen')) {
                if (mb_strlen($value) > $maxLength) {
                    $value = mb_substr($value, 0, $maxLength);
                }
            } elseif (strlen($value) > $maxLength) {
                $value = substr($value, 0, $maxLength);
            }
        }

        return $value;
    }

    /**
     * Import XML content as string and use DOMDocument / SimpleXML to validate, if available
     *
     * @param string $xml
     * @return string
     */
    protected function exportToXml($xml)
    {
        if (class_exists('DOMDocument')) {
            $doc = new DOMDocument();
            $doc->loadXML($xml);
            $xml = $doc->saveXML();
        } elseif (function_exists('simplexml_load_string')) {
            $xml = simplexml_load_string($xml)->asXml();
        }

        $xml = trim(preg_replace('/(\s*)(' . preg_quote('<?xml version="1.0" encoding="utf-8"?>') . ')(\s*)/', '$2', $xml));
        $xml = trim(preg_replace("/\r|\n/", '', $xml));

        return $xml;
    }

    /**
     * Generate XML value for PBX_BILLING parameter
     *
     * @param WC_Order|WC_Customer $object
     * @return string
     */
    public function getXmlBillingInformation($object)
    {
        if (!is_a($object, 'WC_Order') && !is_a($object, 'WC_Customer')) {
            throw new Exception('Invalid object on getXmlBillingInformation');
        }

        $firstName = $this->formatTextValue($object->get_billing_first_name(), 'ANP', 30);
        $lastName = $this->formatTextValue($object->get_billing_last_name(), 'ANP', 30);
        $addressLine1 = $this->formatTextValue($object->get_billing_address_1(), 'ANS', 50);
        $addressLine2 = $this->formatTextValue($object->get_billing_address_2(), 'ANS', 50);
        $zipCode = $this->formatTextValue($object->get_billing_postcode(), 'ANS', 16);
        $city = $this->formatTextValue($object->get_billing_city(), 'ANS', 50);
        $countryCode = (int)WC_Etransactions_Iso3166_Country::getNumericCode($object->get_billing_country());

        $xml = sprintf(
            '<?xml version="1.0" encoding="utf-8"?><Billing><Address><FirstName>%s</FirstName><LastName>%s</LastName><Address1>%s</Address1><Address2>%s</Address2><ZipCode>%s</ZipCode><City>%s</City><CountryCode>%d</CountryCode></Address></Billing>',
            $firstName,
            $lastName,
            $addressLine1,
            $addressLine2,
            $zipCode,
            $city,
            $countryCode
        );

        return $this->exportToXml($xml);
    }

    /**
     * Generate XML value for PBX_SHOPPINGCART parameter
     *
     * @param WC_Order $order
     * @return string
     */
    public function getXmlShoppingCartInformation(WC_Order $order = null)
    {
        $totalQuantity = 0;
        if (!empty($order)) {
            foreach ($order->get_items() as $item) {
                $totalQuantity += (int)$item->get_quantity();
            }
        } else {
            $totalQuantity = 1;
        }
        // totalQuantity must be less or equal than 99
        // totalQuantity must be greater or equal than 1
        $totalQuantity = max(1, min($totalQuantity, 99));

        return sprintf('<?xml version="1.0" encoding="utf-8"?><shoppingcart><total><totalQuantity>%d</totalQuantity></total></shoppingcart>', $totalQuantity);
    }


    public function getClientIp()
    {
        $ipaddress = '';
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_X_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif ($_SERVER['HTTP_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif ($_SERVER['HTTP_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif ($_SERVER['REMOTE_ADDR']) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    public function getCurrency()
    {
        return WC_Etransactions_Iso4217Currency::getIsoCode(get_woocommerce_currency());
    }

    public function getLanguages()
    {
        return array(
            'fr' => 'FRA',
            'es' => 'ESP',
            'it' => 'ITA',
            'de' => 'DEU',
            'nl' => 'NLD',
            'sv' => 'SWE',
            'pt' => 'PRT',
            'default' => 'GBR',
        );
    }

    public function getOrderPayments($orderId, $type)
    {
        global $wpdb;
        $sql = 'select * from '.$wpdb->prefix.'wc_etransactions_payment where order_id = %d and type = %s';
        $sql = $wpdb->prepare($sql, $orderId, $type);
        return $wpdb->get_row($sql);
    }

    /**
     * Check if the is an existing transaction for a specific order
     *
     * @param int $orderId
     * @param string $paymentType
     * @return boolean
     */
    public function hasOrderPayment($orderId, $paymentType = null)
    {
        global $wpdb;
        $sql = 'select COUNT(*) from '.$wpdb->prefix.'wc_etransactions_payment where order_id = %d';
        if (!empty($paymentType)) {
            $sql .= ' AND `type` = %s';
            $sql = $wpdb->prepare($sql, $orderId, $paymentType);
        } else {
            $sql = $wpdb->prepare($sql, $orderId);
        }

        return ((int)$wpdb->get_var($sql) > 0);
    }

    public function getParams()
    {
        // Retrieves data
        $data = file_get_contents('php://input');
        if (empty($data)) {
            $data = $_SERVER['QUERY_STRING'];
        }
        if (empty($data)) {
            $message = 'An unexpected error in E-Transactions call has occured: no parameters.';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        // Extract signature
        $matches = array();
        if (!preg_match('#^(.*)&K=(.*)$#', $data, $matches)) {
            $message = 'An unexpected error in E-Transactions call has occured: missing signature.';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        // Check signature
        $signature = base64_decode(urldecode($matches[2]));
        $pubkey = file_get_contents(dirname(__FILE__).'/pubkey.pem');
        $res = (boolean) openssl_verify($matches[1], $signature, $pubkey);

        if (!$res) {
            if (preg_match('#^s=i&(.*)&K=(.*)$#', $data, $matches)) {
                $signature = base64_decode(urldecode($matches[2]));
                $res = (boolean) openssl_verify($matches[1], $signature, $pubkey);
            }

            // IPN LIMONETIK case, we have to remove some args
            if (!$res) {
                // Remove any extra parameter that is not useful (prevent wrong signature too)
                $queryArgs = array();
                parse_str($data, $queryArgs);
                foreach (array_diff(array_keys($queryArgs), $this->getParametersKeys()) as $queryKey) {
                    unset($queryArgs[$queryKey]);
                }
                // Rebuild the data query string
                $data = http_build_query($queryArgs, '?', '&', PHP_QUERY_RFC3986);
                preg_match('#^(.*)&K=(.*)$#', $data, $matches);

                // Check signature
                $signature = base64_decode(urldecode($matches[2]));
                $pubkey = file_get_contents(dirname(__FILE__).'/pubkey.pem');
                $res = (boolean) openssl_verify($matches[1], $signature, $pubkey);
            }

            if (!$res) {
                $message = 'An unexpected error in E-Transactions call has occured: invalid signature.';
                throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
            }
        }

        $rawParams = array();
        parse_str($data, $rawParams);

        // Decrypt params
        $params = $this->convertParams($rawParams);
        if (empty($params)) {
            $message = 'An unexpected error in E-Transactions call has occured: no parameters.';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        return $params;
    }

    public function getSystemUrl(WC_Order $order = null)
    {
        $urls = $this->_config->getSystemUrls($order);
        if (empty($urls)) {
            $message = 'Missing URL for E-Transactions system in configuration';
            throw new Exception(__($message, WC_ETRANSACTIONS_PLUGIN));
        }

        // look for valid peer
        $error = null;
        foreach ($urls as $url) {
            $testUrl = preg_replace('#^([a-zA-Z0-9]+://[^/]+)(/.*)?$#', '\1/load.html', $url);

            $connectParams = array(
                'timeout' => 5,
                'redirection' => 0,
                'user-agent' => 'Woocommerce E-Transactions module',
                'httpversion' => '2',
            );
            try {
                $response = wp_remote_get($testUrl, $connectParams);
                if (is_array($response) && ($response['response']['code'] == 200)) {
                    if (preg_match('#<div id="server_status" style="text-align:center;">OK</div>#', $response['body']) == 1) {
                        return $url;
                    }
                }
            } catch (Exception $e) {
                $error = $e;
            }
        }

        // Here, there's a problem
        throw new Exception(__('E-Transactions not available. Please try again later.'));
    }

    public function isMobile()
    {
        // From http://detectmobilebrowsers.com/, regexp of 09/09/2013
        global $_SERVER;
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $userAgent)) {
            return true;
        }
        if (preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($userAgent, 0, 4))) {
            return true;
        }
        return false;
    }

    public function signValues(array $values)
    {
        // Serialize values
        $params = array();
        foreach ($values as $name => $value) {
            $params[] = $name.'='.$value;
        }
        $query = implode('&', $params);

        // Prepare key
        $key = pack('H*', $this->_config->getHmacKey());

        // Sign values
        $sign = hash_hmac($this->_config->getHmacAlgo(), $query, $key);
        if ($sign === false) {
            $errorMsg = 'Unable to create hmac signature. Maybe a wrong configuration.';
            throw new Exception(__($errorMsg, WC_ETRANSACTIONS_PLUGIN));
        }

        return strtoupper($sign);
    }

    public function toErrorMessage($code)
    {
        if (isset($this->_errorCode[$code])) {
            return $this->_errorCode[$code];
        }

        return 'Unknown error '.$code;
    }

    /**
     * Load order from the $token
     * @param string $token Token (@see tokenizeOrder)
     * @return Mage_Sales_Model_Order
     */
    public function untokenizeOrder($token)
    {
        $parts = explode(' - ', $token, 2);
        if (count($parts) < 2) {
            $message = 'Invalid decrypted token "%s"';
            throw new Exception(sprintf(__($message, WC_ETRANSACTIONS_PLUGIN), $token));
        }

        // Retrieves order
        $order = new WC_Order($parts[0]);
        $temp_id=$order->get_id();
        if (empty($temp_id)) {
            $message = 'Not existing order id from decrypted token "%s"';
            throw new Exception(sprintf(__($message, WC_ETRANSACTIONS_PLUGIN), $token));
        }

        $name = $this->getBillingName($order);
        if (($name != utf8_decode($parts[1])) && ($name != $parts[1])) {
            $message = 'Consistency error on descrypted token "%s"';
            throw new Exception(sprintf(__($message, WC_ETRANSACTIONS_PLUGIN), $token));
        }

        return $order;
    }

    /**
     * Retrieve the customer ID from the transaction reference
     * Use for the "Add payment method" action (APM)
     *
     * @param string $reference
     * @return int the customer ID
     */
    public function untokenizeCustomerId($reference)
    {
        $parts = explode('-', $reference);
        if (count($parts) < 3) {
            throw new Exception(sprintf(__('Invalid decrypted reference "%s"', WC_ETRANSACTIONS_PLUGIN), $reference));
        }

        return (int)$parts[1];
    }

    /**
     * Retrieve the APM unique ID from the transaction reference
     * Use for the "Add payment method" action (APM)
     *
     * @param string $reference
     * @return int the APM ID
     */
    public function untokenizeApmId($reference)
    {
        $parts = explode('-', $reference);
        if (count($parts) < 3) {
            throw new Exception(sprintf(__('Invalid decrypted reference "%s"', WC_ETRANSACTIONS_PLUGIN), $reference));
        }

        return (int)$parts[2];
    }
}
