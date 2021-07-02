<?php

/**
 * E-Transactions - Configuration class.
 *
 * @class   WC_Etransactions_Config
 */
class WC_Etransactions_Config
{
    private $_values;
    private $_defaults = array(
        'icon' => 'logo.png',
        'amount' => '',
        'debug' => 'no',
        'delay' => 0,
        'capture_order_status' => 'wc-processing',
        'payment_ux' => 'redirect',
        'allow_one_click_payment' => 'no',
        'display_generic_method' => 'no',
        'environment' => 'TEST',
        'hmackey' => '4642EDBBDFF9790734E673A9974FC9DD4EF40AA2929925C40B3A95170FF5A578E7D2579D6074E28A78BD07D633C0E72A378AD83D4428B0F3741102B69AD1DBB0',
        'pass' => 'ETRANSACTIONS',
        'subscription' => 1,
        'identifier' => 3262411,
        'ips' => '194.2.122.158,195.25.7.166,195.101.99.76',
        'rank' => 95,
        'site' => 9999999,
    );

    /**
     * Custom delay value to capture on a specific order status
     */
    const ORDER_STATE_DELAY = 9999;

    /**
     * Identifier for an Access subscription (default)
     */
    const ACCESS_SUBSCRIPTION = 1;

    /**
     * Identifier for a Premium subscription
     */
    const PREMIUM_SUBSCRIPTION = 2;

    /**
     * Identifier for default payment UX (redirect)
     */
    const PAYMENT_UX_REDIRECT = 'redirect';

    /**
     * Identifier for Seamless payment UX (iframe)
     */
    const PAYMENT_UX_SEAMLESS = 'seamless';

    public function __construct(array $values, $defaultTitle, $defaultDesc, $paymentType)
    {
        $this->_values = $values;
        $this->_defaults['title'] = $defaultTitle;
        $this->_defaults['description'] = $defaultDesc;
        $this->encryption = new ETransactionsEncrypt();
        $this->paymentType = $paymentType;
    }

    protected function _getOption($name)
    {
        if (isset($this->_values[$name])) {
            return $this->_values[$name];
        }

        return $this->getDefaultOption($name);
    }

    /**
     * Retrieve the default value for a specific configuration key
     *
     * @param string $name
     * @return mixed
     */
    protected function getDefaultOption($name)
    {
        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }
        return null;
    }

    /**
     * Retrieve all settings by using defined or default value
     *
     * @return array
     */
    public function getFields()
    {
        $settings = array();
        foreach (array_keys($this->_defaults) as $configKey) {
            $settings[$configKey] = $this->_getOption($configKey);
        }

        return $settings;
    }

    public function getAmount()
    {
        $value = $this->_getOption('amount');
        return empty($value) ? null : floatval($value);
    }

    public function getAllowedIps()
    {
        return explode(',', $this->_getOption('ips'));
    }

    public function getDefaults()
    {
        return $this->_defaults;
    }

    public function getDelay()
    {
        return (int)$this->_getOption('delay');
    }

    public function getCaptureOrderStatus()
    {
        return $this->_getOption('capture_order_status');
    }

    public function getDescription()
    {
        return $this->_getOption('description');
    }

    public function getHmacAlgo()
    {
        return 'SHA512';
    }

    public function getHmacKey()
    {
        if (isset($this->_values['hmackey']) && $this->_values['hmackey'] != $this->_defaults['hmackey']) {
            return $this->encryption->decrypt($this->_values['hmackey']);
        }

        return $this->_defaults['hmackey'];
    }

    public function getPassword()
    {
        if (isset($this->_values['pass']) && $this->_values['pass'] != $this->_defaults['pass']) {
            return $this->encryption->decrypt($this->_values['pass']);
        }

        return $this->_defaults['pass'];
    }

    public function getIdentifier()
    {
        return $this->_getOption('identifier');
    }

    public function getRank()
    {
        return $this->_getOption('rank');
    }

    public function getSite()
    {
        return $this->_getOption('site');
    }

    public function getSubscription()
    {
        return $this->_getOption('subscription');
    }

    /**
     * Retrieve the payment UX information from the global configuration or forced card
     *
     * @param WC_Order $order
     * @return string
     */
    public function getPaymentUx(WC_Order $order = null)
    {
        // Force redirect method for 3x payment method
        if ($this->isThreeTimePayment()) {
            return self::PAYMENT_UX_REDIRECT;
        }

        // Default behaviour for "add payment method" page
        if (is_add_payment_method_page()) {
            return $this->getDefaultOption('payment_ux');
        }

        if (empty($order)) {
            return $this->_getOption('payment_ux');
        }

        // If a specific card type is used, check the payment UX on the card
        $card = $this->getOrderCard($order);

        // Check if we have a tokenized card for this order
        if (empty($card)) {
            $tokenizedCard = $this->getTokenizedCard($order);
            if (!empty($tokenizedCard)) {
                // Look for an existing card using card_type
                $ccList = array(
                    'CB',
                    'VISA',
                    'E_CARD',
                    'EUROCARD_MASTERCARD',
                    'MASTERCARD',
                    'MAESTRO',
                );
                $cardType = strtoupper($tokenizedCard->get_card_type());
                if (in_array($cardType, $ccList)) {
                    $cardType = 'CB';
                }
                // Retrieve the card, if any
                $card = $this->getCardByType($tokenizedCard->get_gateway_id(), $cardType);
            }
        }

        if (empty($card)) {
            // Force redirect method for generic payment
            return self::PAYMENT_UX_REDIRECT;
        }

        if (!empty($card->user_xp)) {
            return $card->user_xp;
        }

        // The card itself does not allow iframe, force redirect in this case
        if (!empty($card->id_card) && empty($card->allow_iframe)) {
            return self::PAYMENT_UX_REDIRECT;
        }

        return $this->_getOption('payment_ux');
    }

    /**
     * Retrieve the "allow one-click" payments
     *
     * @param object|null $card
     * @return bool
     */
    public function allowOneClickPayment($card = null)
    {
        // Disable one click payment for 3x payment method
        if ($this->isThreeTimePayment()) {
            return false;
        }

        // Disable one-click payment for all cards that aren't managing tokenization
        // Disable for the generic method too
        if (empty($card) || !in_array($card->type_card, $this->getTokenizableCards())) {
            return false;
        }

        return $this->isPremium() && in_array($this->_getOption('allow_one_click_payment'), array('yes', '1'));
    }

    public function getSystemProductionUrls(WC_Order $order = null)
    {
        return array(
            'https://tpeweb.e-transactions.fr/php/',
            'https://tpeweb1.e-transactions.fr/php/',
        );
    }

    public function getSystemTestUrls(WC_Order $order = null)
    {
        return array(
            'https://preprod-tpeweb.e-transactions.fr/php/',
        );
    }

    public function getSystemUrls(WC_Order $order = null)
    {
        if ($this->isProduction()) {
            return $this->getSystemProductionUrls($order);
        }
        return $this->getSystemTestUrls($order);
    }

    public function getDirectProductionUrls()
    {
        return array(
            'https://ppps.e-transactions.fr/PPPS.php',
            'https://ppps1.e-transactions.fr/PPPS.php',
        );
    }

    public function getDirectTestUrls()
    {
        return array(
            'https://preprod-ppps.e-transactions.fr/PPPS.php',
        );
    }

    public function getDirectUrls()
    {
        if ($this->isProduction()) {
            return $this->getDirectProductionUrls();
        }
        return $this->getDirectTestUrls();
    }

    public function getTitle()
    {
        return $this->_getOption('title');
    }

    public function getIcon()
    {
        return $this->_getOption('icon');
    }

    public function isDebug()
    {
        return $this->_getOption('debug') === 'yes';
    }

    /**
     * Getter for display_generic_method option
     *
     * @return bool
     */
    public function allowDisplayGenericMethod()
    {
        // Force generic payment for 3x payment method
        if ($this->isThreeTimePayment()) {
            return true;
        }

        return $this->_getOption('display_generic_method') === 'yes';
    }

    public function isProduction()
    {
        return $this->_getOption('environment') === 'PRODUCTION';
    }

    public function isPremium()
    {
        return ($this->getSubscription() == WC_Etransactions_Config::PREMIUM_SUBSCRIPTION);
    }

    /**
     * Retrieve cards for the current env & payment method
     *
     * @param string $env
     * @param string $paymentMethod
     * @param bool $forceDisplayOnly
     * @return array
     */
    public function getCards($env, $paymentMethod, $forceDisplayOnly = true)
    {
        global $wpdb;

        // Do not return anyt card for 3x payment method
        if ($this->isThreeTimePayment()) {
            return array();
        }

        return $wpdb->get_results($wpdb->prepare("select * from `{$wpdb->prefix}wc_etransactions_cards`
        WHERE `env` = %s
        AND `payment_method` = %s" .
        ($forceDisplayOnly ? " AND `force_display`=1 " : "") . "
        ORDER BY `position` ASC, `type_payment`, `type_card`", $env, $paymentMethod));
    }

    /**
     * Retrieve a specific card on the current env & payment method
     *
     * @param string $paymentMethod
     * @param int $cardId
     * @return array
     */
    public function getCard($paymentMethod, $cardId)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("select * from `{$wpdb->prefix}wc_etransactions_cards`
        WHERE `env` = %s
        AND `payment_method` = %s
        AND `id_card` = %d", ($this->isProduction() ? 'production' : 'test'), $paymentMethod, $cardId));
    }

    /**
     * Retrieve a specific card (by its type) on the current env & payment method
     *
     * @param string $paymentMethod
     * @param string $cardType
     * @return array
     */
    public function getCardByType($paymentMethod, $cardType)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("select * from `{$wpdb->prefix}wc_etransactions_cards`
        WHERE `env` = %s
        AND `payment_method` = %s
        AND `type_card` = %s", ($this->isProduction() ? 'production' : 'test'), $paymentMethod, $cardType));
    }

    /**
     * Get the prefered payment card associated to the current order
     *
     * @param WC_Order $order
     * @return object|null
     */
    public function getOrderCard(WC_Order $order)
    {
        // If a specific card type is used, check the payment UX on the card
        $cardId = (int)get_post_meta($order->get_id(), $order->get_payment_method() . '_card_id', true);
        if (empty($cardId)) {
            return null;
        }
        $card = $this->getCard($order->get_payment_method(), $cardId);
        if (empty($card)) {
            return null;
        }

        return $card;
    }

    /**
     * Get the associated tokenized card to the current order
     *
     * @param WC_Order $order
     * @return WC_Payment_Token_CC|null
     */
    public function getTokenizedCard(WC_Order $order)
    {
        // Check if a specific saved card type is used
        $tokenId = (int)get_post_meta($order->get_id(), $order->get_payment_method() . '_token_id', true);
        if (empty($tokenId)) {
            return null;
        }
        $token = WC_Payment_Tokens::get($tokenId);
        if (empty($token)) {
            return null;
        }

        return $token;
    }

    /**
     * Update card information
     *
     * @param object $card
     * @param array $data
     * @return bool
     */
    public function updateCard($card, $data)
    {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'wc_etransactions_cards',
            $data,
            array(
                'id_card' => $card->id_card,
            )
        );
    }

    /**
     * Retrieve all "type_card" that are allowing tokenization
     *
     * @return void
     */
    private function getTokenizableCards()
    {
        return array(
            'CB',
            'VISA',
            'EUROCARD_MASTERCARD',
            'E_CARD',
            'MAESTRO',
        );
    }

    /**
     * Retrieve all cards managed by the payment gateway
     *
     * @return array
     */
    public static function getDefaultCards()
    {
        return array(
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'CB',
                'label' => 'Carte bancaire',
                'debit_differe' => 1,
                '3ds' => 2,
                'position' => 0,
                'force_display' => 1,
            ),
            array(
                'type_payment' => 'WALLET',
                'type_card' => 'PAYLIB',
                'label' => 'Paylib',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 1,
                'force_display' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'AMEX',
                'label' => 'Carte American Express',
                'debit_differe' => 1,
                '3ds' => 2,
                'position' => 2,
            ),
            array(
                'type_payment' => 'PAYPAL',
                'type_card' => 'PAYPAL',
                'label' => 'PayPal',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 3,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'JCB',
                'label' => 'JCB',
                'debit_differe' => 1,
                '3ds' => 2,
                'position' => 4,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'DINERS',
                'label' => 'Diner\'s',
                'debit_differe' => 1,
                '3ds' => 0,
                'position' => 5,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'APETIZ',
                'label' => 'Apetiz',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 6,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'SODEXO',
                'label' => 'Sodexo',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 6,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'UPCHEQUDEJ',
                'label' => 'Up Chèque Déjeuner',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 7,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'CVCONNECT',
                'label' => 'Chèque-Vacances Connect',
                'debit_differe' => 0,
                '3ds' => 0,
                'allow_iframe' => 0,
                'position' => 8,
            ),
        );
    }

    /**
     * Check if the current config is related to threetime method
     *
     * @return boolean
     */
    protected function isThreeTimePayment()
    {
        return $this->paymentType == 'threetime';
    }
}
