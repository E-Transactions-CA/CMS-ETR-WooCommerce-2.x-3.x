<?php

/**
 * E-Transactions - Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class   WC_Etransactions_Abstract_Gateway
 * @extends WC_Payment_Gateway
 */
abstract class WC_Etransactions_Abstract_Gateway extends WC_Payment_Gateway
{
    protected $_config;
    protected $_etransactions;
    private $logger;

    /**
     * @var WC_Etransactions_Abstract_Gateway
     */
    private static $pluginInstance = array();

    /**
     * Returns payment gateway instance
     *
     * @return WC_Etransactions_Abstract_Gateway
     */
    public static function getInstance($class)
    {
        if (empty(self::$pluginInstance[$class])) {
            self::$pluginInstance[$class] = new static();
        }

        return self::$pluginInstance[$class];
    }

    public function __construct()
    {
        // Logger for debug if needed
        if (WC()->debug === 'yes') {
            $this->logger = WC()->logger();
        }

        $this->method_description = '<center><img src="' . plugins_url('images/logo.png', plugin_basename(dirname(__FILE__))) . '"/></center>';

        // Load the settings
        $this->defaultConfig = new WC_Etransactions_Config(array(), $this->defaultTitle, $this->defaultDesc, $this->type);
        $this->encryption = new ETransactionsEncrypt();
        $this->init_settings();
        $this->_config = new WC_Etransactions_Config($this->settings, $this->defaultTitle, $this->defaultDesc, $this->type);
        $this->_etransactions = new WC_Etransactions($this->_config);

        $this->title = apply_filters('title', $this->_config->getTitle());
        $this->description = apply_filters('description', $this->_config->getDescription());
        $this->commonDescription = '';
        $this->icon = apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'images/') . apply_filters('icon', $this->_config->getIcon());

        // Change title & description depending on the context
        if (!is_admin() && $this->getCurrentEnvMode() == 'test') {
            $this->title = apply_filters('title', $this->_config->getTitle() . ' (' . __('TEST MODE', WC_ETRANSACTIONS_PLUGIN) . ')');
            $this->description = apply_filters('description', '<strong>' . __('Test mode enabled - No debit will be made', WC_ETRANSACTIONS_PLUGIN) . '</strong><br /><br />' . $this->_config->getDescription());
            $this->commonDescription = apply_filters('description', '<strong>' . __('Test mode enabled - No debit will be made', WC_ETRANSACTIONS_PLUGIN) . '</strong><br /><br />');
        }

        if (is_admin()) {
            $this->title = apply_filters('title', $this->originalTitle);
        }

        // Handle specific payment gateway features for Premium subscription
        if ($this->_config->isPremium()) {
            $this->supports = array(
                'tokenization',
                'add_payment_method',
            );
            // Set has fields to true, allow display of checkbox even if description is empty
            $this->has_fields = true;
        }
    }

    /**
     * Register some hooks
     *
     * @return void
     */
    public function initHooksAndFilters()
    {
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'api_call'));
        add_action('admin_notices', array($this, 'display_custom_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'load_custom_admin_assets'));

        if ($this->_config->isPremium()) {
            // Hide payment gateway in some specific cases
            add_filter('woocommerce_available_payment_gateways', array($this, 'hide_payment_gateway'), 10);
            add_filter('woocommerce_before_account_payment_methods', array($this, 'load_custom_front_assets'));

            // Capture on a specific order state
            if ($this->_config->getDelay() == WC_Etransactions_Config::ORDER_STATE_DELAY) {
                $orderStatus = str_replace('wc-', '', $this->_config->getCaptureOrderStatus());
                add_action('woocommerce_order_status_' . $orderStatus, array($this, 'process_order_status_changed'));
            }

            // Call to detect change on order state (seamless transactions)
            add_action('wc_ajax_' . $this->id . '_order_poll', array($this, 'ajax_poll_order'));

            // Cards managements
            add_filter('woocommerce_available_payment_gateways', array($this, 'display_tokens_as_payment_gateways'));
        }

        // Hide main payment method if enabled into plugin configuration
        add_filter('woocommerce_available_payment_gateways', array($this, 'hide_main_payment_gateway'), 99999);

        // Handle display of forced cards
        if (!empty($this->_config->getCards($this->getCurrentEnvMode(), $this->id, true))) {
            add_filter('woocommerce_available_payment_gateways', array($this, 'display_custom_cards_as_payment_gateways'));
        }
    }

    /**
     * Display the save payment method checkbox
     *
     * @see WC_Payment_Gateway::payment_fields
     * @return void
     */
    public function payment_fields()
    {
        parent::payment_fields();

        // Do not display "save the card" card checkbox on add payment method page
        if (is_add_payment_method_page()) {
            return;
        }

        // Retrieve current card
        $card = $this->getCurrentCard();

        // Display checkbox if enabled into configuration
        if ($this->_config->allowOneClickPayment($card) && empty($this->tokenized_card_id)) {
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Allow retrieving of order status & URL to follow depending on the order state
     *
     * @return json
     */
    public function ajax_poll_order()
    {
        // Check submitted parameters & nonce
        if (empty($_POST['order_id'])) {
            wp_send_json_error();
        }
        $orderId = (int)$_POST['order_id'];
        check_ajax_referer($this->id . '-order-poll-' . $orderId, 'nonce');

        // Retrieve the order and check the payment method
        $order = wc_get_order($orderId);
        $orderData = $order->get_data();
        if (empty($orderData['payment_method']) || $orderData['payment_method'] != $this->id) {
            wp_send_json_error();
        }

        $redirectUrl = null;
        $paymentExists = (bool)$this->_etransactions->hasOrderPayment($orderId);
        if (in_array($orderData['status'], array('failed', 'cancelled'))) {
            // Try to pay again
            $redirectUrl = $order->get_checkout_payment_url();
        } elseif ($paymentExists) {
            // Success page
            $redirectUrl = $order->get_checkout_order_received_url();
        }

        wp_send_json_success([
            'payment_exists' => $paymentExists,
            'order_status' => $orderData['status'],
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Build the parameters and redirect the customer to the payment page
     * to proceed the "Add payment method" action
     *
     * @return void
     */
    public function add_payment_method()
    {
        if (empty($_POST['payment_method'])) {
            return;
        }

        // Payment identifier
        $paymentMethod = !empty($this->original_id) ? $this->original_id : $this->id;

        // Retrieve card id
        $card = null;
        if (!empty($this->card_id)) {
            $card = $this->_config->getCard($paymentMethod, $this->card_id);
        }

        $urls = $this->getReturnUrls('-tokenization');
        $params = $this->_etransactions->buildTokenizationSystemParams($card, $urls);

        try {
            $url = $this->_etransactions->getSystemUrl();
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return;
        }

        wp_redirect(esc_url($url) . '?' . http_build_query($params));
    }

    /**
     * Insert a new array item at a specific position
     *
     * @param array $input
     * @param int $pos
     * @param array $item
     * @return array
     */
    private function array_insert_at_position(&$input, $pos, $item)
    {
        return array_merge(array_splice($input, 0, $pos), $item, $input);
    }

    /**
     * Hide 3x payment gateway, it will not be used for tokenization process
     * on add payment method page
     *
     * @param array $params
     * @return array
     */
    public function hide_payment_gateway($params)
    {
        if (is_add_payment_method_page() && isset($params['etransactions_3x'])) {
            unset($params['etransactions_3x']);
        }

        return $params;
    }

    /**
     * Hide main payment method if enabled into plugin configuration
     *
     * @param array $params
     * @return array
     */
    public function hide_main_payment_gateway($params)
    {
        if (empty($this->original_id) && !$this->_config->allowDisplayGenericMethod()) {
            unset($params[$this->id]);
        }

        return $params;
    }


    /**
     * Fake payment gateways list and add the saved cards
     *
     * @param array $params
     * @return array
     */
    public function display_tokens_as_payment_gateways($params)
    {
        if (!isset($params[$this->id]) || !get_current_user_id() || is_add_payment_method_page()) {
            return $params;
        }

        // If tokenization is available, create the tokenized card
        // First, check if the token already exists on our side
        $exitingTokens = WC_Payment_Tokens::get_tokens(array(
            'user_id' => get_current_user_id(),
            'gateway_id' => $this->id,
        ));

        foreach ($exitingTokens as $idToken => $token) {
            // Clone the payment gateway, set a new id (temp), title & icon
            $paymentMethodKey = $this->id . '-token-' . $idToken;
            $newPaymentGateway = clone($params[$this->id]);
            $newPaymentGateway->id = $paymentMethodKey;
            $newPaymentGateway->tokenized_card_id = $idToken;
            $newPaymentGateway->original_id = $this->id;
            $newPaymentGateway->description = $this->commonDescription;

            $cardTitle = sprintf(
                __('Pay with my stored card - **%02d - %02d/%02d', WC_ETRANSACTIONS_PLUGIN),
                // $token->get_card_type(),
                $token->get_last4(),
                $token->get_expiry_month(),
                $token->get_expiry_year()
            );
            $newPaymentGateway->title = apply_filters('title', $cardTitle);
            if ($this->getCurrentEnvMode() == 'test') {
                $newPaymentGateway->title .= ' (' . __('TEST MODE', WC_ETRANSACTIONS_PLUGIN) . ')';
            }
            $newPaymentGateway->icon = apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . apply_filters('icon', strtoupper($token->get_card_type()) . '.svg');

            $params = $this->array_insert_at_position($params, array_search($this->id, array_keys($params)), array(
                $paymentMethodKey => $newPaymentGateway
            ));
        }

        return $params;
    }

    /**
     * Fake payment gateways list and add the forced cards
     *
     * @param array $params
     * @return array
     */
    public function display_custom_cards_as_payment_gateways($params)
    {
        if (!isset($params[$this->id])) {
            return $params;
        }

        foreach ($this->_config->getCards($this->getCurrentEnvMode(), $this->id, true) as $card) {
            $paymentMethodKey = $this->id . '_card_' . (int)$card->id_card;
            if (isset($params[$paymentMethodKey])) {
                continue;
            }

            // Clone the payment gateway, set a new id (temp), title & icon
            $newPaymentGateway = clone($params[$this->id]);
            $newPaymentGateway->id = $paymentMethodKey;
            $newPaymentGateway->original_id = $this->id;
            $newPaymentGateway->card_id = $card->id_card;
            $newPaymentGateway->title = apply_filters('title', $card->label);
            if ($this->getCurrentEnvMode() == 'test') {
                $newPaymentGateway->title .= ' (' . __('TEST MODE', WC_ETRANSACTIONS_PLUGIN) . ')';
            }
            $newPaymentGateway->description = $this->commonDescription;
            $newPaymentGateway->icon = apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'cards/') . apply_filters('icon', $card->type_card . '.svg');

            $params = $this->array_insert_at_position($params, array_search($this->id, array_keys($params)), array(
                $paymentMethodKey => $newPaymentGateway
            ));
        }

        return $params;
    }

    /**
     * Save the specific card/token id to use while creating the order
     * The meta key is saved as <payment_method>_card_id / <payment_method>_token_id
     *
     * @param int $orderId
     * @return void
     */
    protected function savePaymentMethodCardOrTokenToForce($orderId)
    {
        if (empty($_POST['payment_method'])) {
            return;
        }

        // Payment identifier
        $paymentMethod = !empty($this->original_id) ? $this->original_id : $this->id;

        $order = wc_get_order($orderId);
        // Reset payment method to the original one
        $order->set_payment_method($paymentMethod);
        $order->save();

        // Reset any previous values
        update_post_meta($orderId, $paymentMethod . '_card_id', null);
        update_post_meta($orderId, $paymentMethod . '_token_id', null);

        // Retrieve card id
        if (!empty($this->card_id)) {
            $card = $this->_config->getCard($paymentMethod, $this->card_id);
            if (!empty($card->id_card)) {
                // Add or reset the specific meta for the card id
                update_post_meta($orderId, $paymentMethod . '_card_id', $card->id_card);
            }
        }

        // Retrieve tokenized card id
        if (!empty($this->tokenized_card_id)) {
            $token = WC_Payment_Tokens::get($this->tokenized_card_id);
            if ($token !== null && $token->get_user_id() == get_current_user_id()) {
                // Add or reset the specific meta for the token card id
                update_post_meta($orderId, $paymentMethod . '_token_id', $this->tokenized_card_id);
            }
        }
    }

    /**
     * If the "Save the card" option is checked
     * Add the info to a meta key is saved as <payment_method>_allow_tokenization
     *
     * @param int $orderId
     * @return void
     */
    protected function saveAllowTokenInformation($orderId)
    {
        // Retrieve card
        $card = $this->getCurrentCard();

        if (!$this->_config->allowOneClickPayment($card) || empty($_POST['payment_method'])) {
            return;
        }

        // Retrieve "save the card" checkbox value
        $allowTokenization = !empty($_POST['wc-' . $this->id . '-new-payment-method']);
        // Payment identifier
        $paymentMethod = !empty($this->original_id) ? $this->original_id : $this->id;

        // Add or reset the specific meta for the card id
        update_post_meta($orderId, $paymentMethod . '_allow_tokenization', $allowTokenization);
    }

    /**
     * Save the token to the database if not already exists
     *
     * @param array $params
     * @param int $customerId
     * @param WC_Order $order
     * @return bool
     */
    protected function saveTokenToDatabase($params, $customerId, $order = null)
    {
        // Retrieve original order
        if (empty($order)) {
            // APM case
            if (preg_match('/APM-.*/', $params['reference'])) {
                $referenceId = $this->_etransactions->untokenizeApmId($params['reference']);
            } else {
                $order = $this->_etransactions->untokenizeOrder($params['reference']);
                $referenceId = $order->get_id();
            }
        } else {
            $referenceId = $order->get_id();
        }

        // Retrieve token information & card expiry date from subscriptionData
        $subscriptionData = explode('  ', $params['subscriptionData']);
        // Build token using order id too, so we can duplicate the cards
        // Token content : PBX_REFABONNE|PBX_TOKEN
        $token = wp_hash($referenceId . '-' . $customerId) . '|' . trim($subscriptionData[0]);

        $expiryDate = trim($subscriptionData[1]);
        $expiryYear = '20' . substr($expiryDate, 0, 2);
        $expiryMonth = substr($expiryDate, 2, 2);

        // If tokenization is available, create the tokenized card
        // First, check if the token already exists on our side
        $exitingTokens = WC_Payment_Tokens::get_tokens(array(
            'user_id' => $customerId,
            'gateway_id' => $this->id,
        ));

        // Check if the token already exists
        $tokenAlreadyExists = false;
        foreach ($exitingTokens as $existingToken) {
            if ($existingToken->get_token() == $token
                && $existingToken->get_expiry_month() == $expiryMonth
                && $existingToken->get_expiry_year() == $expiryYear
            ) {
                $tokenAlreadyExists = true;
                break;
            }
        }

        // The token already exists
        if ($tokenAlreadyExists) {
            return;
        }

        // Create the payment token
        $paymentToken = new WC_Payment_Token_CC();
        $paymentToken->set_token($token);
        $paymentToken->set_gateway_id($this->id);
        $paymentToken->set_card_type($params['cardType']);
        $paymentToken->set_last4($params['lastNumbers']);
        $paymentToken->set_expiry_month($expiryMonth);
        $paymentToken->set_expiry_year($expiryYear);
        $paymentToken->set_user_id($customerId);

        return $paymentToken->save();
    }

    /**
     * After the payment, if subscriptionData is available,
     * create the token if not already exists
     *
     * @param WC_Order $order
     * @param array $params
     * @return int the token id
     */
    protected function saveCardTokenAfterPayment(WC_Order $order, $params)
    {
        // Check for Premium subscription & subscriptionData information
        if (!$this->_config->isPremium() || empty($params['subscriptionData'])) {
            return;
        }

        // Allow tokenization ?
        $allowTokenization = (bool)get_post_meta($order->get_id(), $order->get_payment_method() . '_allow_tokenization', true);
        if (!$allowTokenization) {
            return;
        }

        return $this->saveTokenToDatabase($params, $order->get_customer_id(), $order);
    }

    public function process_order_status_changed($orderId)
    {
        $order = wc_get_order($orderId);
        $orderData = $order->get_data();
        if (empty($orderData['payment_method']) || $orderData['payment_method'] != $this->id) {
            return;
        }
        // Check if the order has already been captured
        $orderPayment = $this->_etransactions->getOrderPayments($orderId, 'capture');
        if (!empty($orderPayment->data)) {
            return;
        }

        // Retrieve the current authorization infos
        $orderPayment = $this->_etransactions->getOrderPayments($orderId, 'authorization');
        if (empty($orderPayment->data)) {
            return;
        }

        $orderPaymentData = unserialize($orderPayment->data);
        $httpClient = new WC_Etransactions_Curl_Helper($this->_config);

        $params = $httpClient->makeCapture($order, $orderPaymentData['transaction'], $orderPaymentData['call'], $orderPaymentData['amount'], $orderPaymentData['cardType']);
        if (isset($params['CODEREPONSE']) && $params['CODEREPONSE'] == '00000') {
            // Capture done
            $this->_etransactions->addOrderNote($order, __('Payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
            // Backup the capture operation timestamp
            $params['CAPTURE_DATE_ADD'] = time();
            $this->_etransactions->addOrderPayment($order, 'capture', $params);
        } else {
            // Payment refused
            $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
            $error = $this->_etransactions->toErrorMessage($params['CODEREPONSE']);
            $message = sprintf($message, $error);
            $this->_etransactions->addOrderNote($order, $message);
        }
    }

    /**
     * Retrieve current card to be used
     *
     * @return object|null
     */
    protected function getCurrentCard()
    {
        // Payment identifier
        $paymentMethod = !empty($this->original_id) ? $this->original_id : $this->id;

        // Retrieve card id
        $card = null;
        if (!empty($this->card_id)) {
            $card = $this->_config->getCard($paymentMethod, $this->card_id);
        }

        return $card;
    }

    /**
     * Retrieve form fields for the gateway plugin
     *
     * @return array
     */
    public function get_form_fields()
    {
        $fields = parent::get_form_fields();

        $fields += $this->getGlobalConfigurationFields();
        $fields += $this->getAccountConfigurationFields();
        $fields += $this->getCardsConfigurationFields();

        return $fields;
    }

    /**
     * Init payment gateway settings + separately handle environment
     *
     * @return void
     */
    public function init_settings()
    {
        parent::init_settings();

        // Set default env if not exists (upgrade / new install cases for example)
        if (empty($this->settings['environment'])) {
            $defaults = $this->defaultConfig->getDefaults();
            $this->settings['environment'] = $defaults['environment'];
        }

        // Set custom setting for environment (global to any env)
        if (get_option($this->plugin_id . $this->id . '_env') === false && !empty($this->settings['environment'])) {
            update_option($this->plugin_id . $this->id . '_env', $this->settings['environment']);
            unset($this->settings['environment']);
            update_option($this->get_option_key(), $this->settings);
        }

        // Module upgrade case, copy same settings on test env
        if (get_option($this->plugin_id . $this->id . '_settings') !== false && get_option($this->plugin_id . $this->id . '_test_settings') === false) {
            // Apply the same configuration on test vs production
            $testConfiguration = get_option($this->plugin_id . $this->id . '_settings');
            $testConfiguration['environment'] = 'TEST';
            update_option($this->plugin_id . $this->id . '_test_settings', $testConfiguration);
        }

        // Define the current environment
        $this->settings['environment'] = get_option($this->plugin_id . $this->id . '_env');

        $this->_config = new WC_Etransactions_Config($this->settings, $this->defaultTitle, $this->defaultDesc, $this->type);
        $this->settings = $this->_config->getFields();
    }

    /**
     * Handle custom config key for test / production settings
     *
     * @return string
     */
    public function get_option_key()
    {
        // Inherit settings from the previous version
        if ($this->getCurrentConfigMode() != 'production') {
            return $this->plugin_id . $this->id . '_' .  $this->getCurrentConfigMode() . '_settings';
        }

        return parent::get_option_key();
    }

    /**
     * save_hmackey
     * Used to save the settings field of the custom type HSK
     * @param  array $field
     * @return void
     */
    public function process_admin_options()
    {
        // Handle encrypted fields
        foreach (array('hmackey', 'pass') as $field) {
            $_POST[$this->plugin_id . $this->id . '_' . $field] = $this->encryption->encrypt($_POST[$this->plugin_id . $this->id . '_' . $field]);
        }

        // Handle environment config data separately
        if (isset($_POST[$this->plugin_id . $this->id . '_environment'])
        && in_array($_POST[$this->plugin_id . $this->id . '_environment'], array('TEST', 'PRODUCTION'))) {
            update_option($this->plugin_id . $this->id . '_env', $_POST[$this->plugin_id . $this->id . '_environment']);
            unset($_POST[$this->plugin_id . $this->id . '_environment']);
        }

        // Handle cards update
        if ($this->type != 'threetime') {
            foreach ($this->_config->getCards($this->getCurrentConfigMode(), $this->id, false) as $card) {
                if (!isset($_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-ux'])) {
                    continue;
                }
                $cardUpdateData = array(
                    'user_xp' => !empty($_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-ux']) ? $_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-ux'] : null,
                    'force_display' => (int)(!empty($_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-force-display']) && $_POST[$this->plugin_id . $this->id . '_card-' . (int)$card->id_card . '-force-display'] == 'on'),
                );
                $this->_config->updateCard($card, $cardUpdateData);
            }
        }

        parent::process_admin_options();
    }

    /**
     * Check the current context so allow/disallow a specific display action
     *
     * @return bool
     */
    protected function allowDisplay()
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        // Prevent display on others pages than setting, and if the current id isn't the one we are trying to configure
        if (
            !is_object($screen)
            || empty($screen->id)
            || $screen->id != 'woocommerce_page_wc-settings'
            || empty($_GET['section'])
            || $this->id != $_GET['section']
        ) {
            return false;
        }

        return true;
    }

    /**
     * Load the needed assets for the plugin configuration
     *
     * @return void
     */
    public function load_custom_admin_assets()
    {
        if (!$this->allowDisplay()) {
            return;
        }

        // Register JS & CSS files
        wp_register_style('admin.css', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/css/admin.css', array(), WC_ETRANSACTIONS_VERSION);
        wp_enqueue_style('admin.css');
        wp_register_script('admin.js', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/js/admin.js', array(), WC_ETRANSACTIONS_VERSION);
        wp_enqueue_script('admin.js');
    }

    /**
     * Load the needed assets for seamless iframe integration
     *
     * @return void
     */
    public function load_custom_front_assets()
    {
        if (!is_order_received_page() && !is_account_page()) {
            return;
        }

        // Register JS & CSS files
        wp_register_style('pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/css/front.css', array(), WC_ETRANSACTIONS_VERSION);
        wp_enqueue_style('pbx_fo');
        wp_register_script('pbx_fo', WC_ETRANSACTIONS_PLUGIN_URL . 'assets/js/front.js', array(), WC_ETRANSACTIONS_VERSION);
        wp_enqueue_script('pbx_fo');
        wp_localize_script('pbx_fo', 'pbx_fo', array(
            'homeUrl' => home_url(),
            'orderPollUrl' => home_url() . \WC_Ajax::get_endpoint($this->id . '_order_poll'),
        ));
    }

    /**
     * Used to display some specific notices regarding the current gateway env
     *
     * @return void
     */
    public function display_custom_admin_notices()
    {
        static $displayed = false;

        // HMAC or WooCommerce alerts
        if (wooCommerceActiveETwp()) {
            if ($this->allowDisplay() && !$this->checkCrypto()) {
                echo "<div class='notice notice-error is-dismissible'>
                <p><strong>/!\ Attention ! plugin " . $this->get_title() . " (" . $this->getCurrentConfigMode() . ") : </strong>" . __('HMAC key cannot be decrypted please re-enter or reinitialise it.', WC_ETRANSACTIONS_PLUGIN) . "</p>
                </div>";
            }
        } else {
            echo "<div class='notice notice-error is-dismissible'>
            <p><strong>/!\ Attention ! plugin E-Transactions : </strong>" . __('Woocommerce is not active !', 'wc-etransactions') . "</p>
            </div>";
        }

        if (!$this->allowDisplay() || $displayed) {
            return;
        }

        // Display alert banner if the extension is into TEST mode
        if (get_option($this->plugin_id . $this->id . '_env') == 'TEST') {
            $displayed = true; ?>
            <div id="pbx-alert-mode" class="pbx-alert-box notice notice-warning notice-alt">
                <div class="dashicons dashicons-warning"></div>
                <div class="pbx-alert-box-content">
                    <strong class="pbx-alert-title"><?= __('Test mode enabled', WC_ETRANSACTIONS_PLUGIN); ?></strong>
                    <?= __('No debit will be made', WC_ETRANSACTIONS_PLUGIN); ?>
                </div>
                <div class="dashicons dashicons-warning"></div>
            </div>
            <?php
        }
    }

    /**
     * Retrieve current environment mode (production / test)
     *
     * @return string
     */
    protected function getCurrentEnvMode()
    {
        // Use current defined mode into the global configuration
        if (!empty(get_option($this->plugin_id . $this->id . '_env')) && in_array(get_option($this->plugin_id . $this->id . '_env'), array('TEST', 'PRODUCTION'))) {
            return strtolower(get_option($this->plugin_id . $this->id . '_env'));
        }

        // Use the default mode from WC_Etransactions_Config
        $defaults = $this->defaultConfig->getDefaults();

        return strtolower($defaults['environment']);
    }

    /**
     * Retrieve current configuration mode (production / test)
     *
     * @return string
     */
    protected function getCurrentConfigMode()
    {
        // Use current defined mode into the URL (only if request is from admin)
        if (is_admin() && !empty($_GET['config_mode']) && in_array($_GET['config_mode'], array('test', 'production'))) {
            return $_GET['config_mode'];
        }

        // Use current defined mode into the global configuration
        if (!empty(get_option($this->plugin_id . $this->id . '_env')) && in_array(get_option($this->plugin_id . $this->id . '_env'), array('TEST', 'PRODUCTION'))) {
            return strtolower(get_option($this->plugin_id . $this->id . '_env'));
        }

        // Use the default mode from WC_Etransactions_Config
        $defaults = $this->defaultConfig->getDefaults();

        return $defaults['environment'];
    }

    public function admin_options()
    {
        $this->settings['hmackey'] = $this->_config->getHmacKey();
        $this->settings['pass'] = $this->_config->getPassword();

        ?>
        <script>
            var pbxUrl = <?= json_encode(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id)) ?>;
            var pbxConfigModeMessage = <?= json_encode(__('Do you really want to change the current shop environment mode?', WC_ETRANSACTIONS_PLUGIN)) ?>;
            var pbxGatewayId = <?= json_encode($this->id) ?>;
            var pbxOrderStateDelay = <?= json_encode(WC_Etransactions_Config::ORDER_STATE_DELAY) ?>;
            var pbxCurrentSubscription = <?= json_encode($this->_config->getSubscription()) ?>;
            var pbxPremiumSubscriptionId = <?= json_encode(WC_Etransactions_Config::PREMIUM_SUBSCRIPTION) ?>;
            var pbxPremiumSubscriptionFields = <?= json_encode(array(
                'capture_order_status',
                'pass',
                'allow_one_click_payment',
            )) ?>;
        </script>

        <div id="pbx-plugin-configuration">
            <div class="pbx-flex-container">
                <div>
                    <div id="pbx-plugin-image"></div>
                </div>
                <div id="pbx-current-mode-selector" class="pbx-current-mode-<?= $this->getCurrentEnvMode(); ?>">
                    <table class="form-table">
                        <?= $this->generate_settings_html($this->get_payment_mode_fields()); ?>
                    </table>
                </div>
            </div>
            <div class="clear"></div>

            <div class="pbx-current-config-mode pbx-current-config-mode-<?= $this->getCurrentConfigMode() ?>">
                <span class="dashicons dashicons-<?= ($this->getCurrentConfigMode() == 'test' ? 'warning' : 'yes-alt') ?>"></span>
                <?= sprintf(__('You are currently editing the <strong><u>%s</u></strong> configuration', WC_ETRANSACTIONS_PLUGIN), $this->getCurrentConfigMode()); ?>
                <span class="dashicons dashicons-<?= ($this->getCurrentConfigMode() == 'test' ? 'warning' : 'yes-alt') ?>"></span>
                <br /><br />
                <a href="<?= admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) ?>&config_mode=<?= ($this->getCurrentConfigMode() == 'production' ? 'test' : 'production') ?>">
                    <?= sprintf(__('=> Click here to switch to the <strong>%s</strong> configuration', WC_ETRANSACTIONS_PLUGIN), ($this->getCurrentConfigMode() == 'production' ? 'test' : 'production')); ?>
                </a>
            </div>

            <h2 id="pbx-tabs" class="nav-tab-wrapper">
                <a href="#pbx-pbx-account-configuration" class="nav-tab nav-tab-active">
                    <?= __('My account', WC_ETRANSACTIONS_PLUGIN); ?>
                </a>
                <a href="#pbx-global-configuration" class="nav-tab">
                    <?= __('Global configuration', WC_ETRANSACTIONS_PLUGIN); ?>
                </a>
                <?php if ($this->type != 'threetime') { ?>
                <a href="#pbx-cards-configuration" class="nav-tab">
                    <?= __('Means of payment configuration', WC_ETRANSACTIONS_PLUGIN); ?>
                </a>
                <?php } ?>
            </h2>
            <div id="pbx-pbx-account-configuration" class="tab-content tab-active">
                <table class="form-table">
                <?= $this->generate_account_configuration_html(); ?>
                </table>
            </div>
            <div id="pbx-global-configuration" class="tab-content">
                <table class="form-table">
                <?= $this->generate_global_configuration_html(); ?>
                </table>
            </div>
            <?php if ($this->type != 'threetime') { ?>
            <div id="pbx-cards-configuration" class="tab-content">
                <?= $this->generate_cards_configuration_html(); ?>
            </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Generate configuration form for the global configuration
     *
     * @return void
     */
    protected function generate_global_configuration_html()
    {
        $this->generate_settings_html($this->getGlobalConfigurationFields());
    }

    /**
     * Generate configuration form for the account configuration
     *
     * @return void
     */
    protected function generate_account_configuration_html()
    {
        $this->generate_settings_html($this->getAccountConfigurationFields());
    }

    /**
     * Generate configuration form for the cards configuration
     *
     * @return void
     */
    protected function generate_cards_configuration_html()
    {
        ?>
        <table class="form-table">
        <?php
        $this->generate_settings_html($this->getCardsConfigurationFields());
        ?>
        </table>

        <div id="pbx-cards-container" class="row">
        <?php
        foreach ($this->_config->getCards($this->getCurrentConfigMode(), $this->id, false) as $card) {
            ?>
            <div class="pbx-card col-lg-3 col-md-4 col-sm-5">
                <div class="card pbx-card-body" style="background-image: url('<?= plugins_url('cards/' . $card->type_card . '.svg', plugin_basename(dirname(__FILE__))) ?>')">
                    <div class="pbx-card-label">
                        <?= $card->label ?>
                    </div>
                    <div class="pbx-card-force-display-state">
                        <input id="card-<?= (int)$card->id_card ?>-force-display" name="<?= $this->plugin_id . $this->id . '_card-' . (int)$card->id_card ?>-force-display" type="checkbox" <?= !empty($card->force_display) ? 'checked' : '' ?> />
                        <label for="card-<?= (int)$card->id_card ?>-force-display"><?= __('Display on your payment page', WC_ETRANSACTIONS_PLUGIN) ?></label>
                    </div>
                    <div class="pbx-card-ux">
                        <label for="card-<?= (int)$card->id_card ?>-ux"><?= __('Display method', WC_ETRANSACTIONS_PLUGIN) ?></label>
                        <select class="select" id="card-<?= (int)$card->id_card ?>-ux" name="<?= $this->plugin_id . $this->id . '_card-' . (int)$card->id_card ?>-ux">
                            <option
                                value=""
                                <?= (!empty($card->allow_iframe) && empty($card->user_xp) ? ' selected="selected"' : '') ?>
                                <?= (empty($card->allow_iframe) ? ' disabled="disabled"' : '') ?>
                            >
                                <?= __('Same as global configuration', WC_ETRANSACTIONS_PLUGIN) ?>
                            </option>
                            <option
                                value="<?= WC_Etransactions_Config::PAYMENT_UX_REDIRECT ?>"
                                <?= (empty($card->allow_iframe) ? ' selected="selected"' : '') ?>
                                <?= ($card->user_xp == WC_Etransactions_Config::PAYMENT_UX_REDIRECT ? ' selected="selected"' : '') ?>
                            >
                                <?= __('Redirect method', WC_ETRANSACTIONS_PLUGIN) ?>
                            </option>
                            <option
                                value="<?= WC_Etransactions_Config::PAYMENT_UX_SEAMLESS ?>"
                                <?= (empty($card->allow_iframe) ? ' disabled="disabled"' : '') ?>
                                <?= (!empty($card->allow_iframe) && $card->user_xp == WC_Etransactions_Config::PAYMENT_UX_SEAMLESS ? ' selected="selected"' : '') ?>
                            >
                                <?= __('Seamless (iframe)', WC_ETRANSACTIONS_PLUGIN) ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            <?php
        } ?>
        </div>
        <?php
    }

    /**
     * Retrieve specific fields, dedicated to environment
     *
     * @return array
     */
    protected function get_payment_mode_fields()
    {
        $defaults = $this->defaultConfig->getDefaults();

        return array(
            'environment' => array(
                'title' => __('Current shop environment mode', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'select',
                // 'description' => __('In test mode your payments will not be sent to the bank.', WC_ETRANSACTIONS_PLUGIN),
                'options' => array(
                    'PRODUCTION' => __('Production', WC_ETRANSACTIONS_PLUGIN),
                    'TEST' => __('Test (no debit)', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default' => $defaults['environment'],
            ),
        );
    }

    /**
     * Retrieve the fields for the global configuration
     *
     * @return array
     */
    protected function getGlobalConfigurationFields()
    {
        if (!isset($this->_config)) {
            $this->_config = $this->defaultConfig;
        }
        $defaults = $this->defaultConfig->getDefaults();

        $formFields = array();
        $formFields['enabled'] = array(
            'title' => __('Enable/Disable', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('Enable E-Transactions Payment', WC_ETRANSACTIONS_PLUGIN),
            'default' => 'yes'
        );
        $formFields['generic_method_settings'] = array(
            'title' => __('Grouped payment configuration', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
            'default' => null,
        );
        if ($this->type != 'threetime') {
            $formFields['display_generic_method'] = array(
                'title' => __('Activate', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'checkbox',
                'label' => __('Display one payment option for all means of payment available on payment page after redirection', WC_ETRANSACTIONS_PLUGIN),
                'default' => $defaults['display_generic_method'],
            );
        }
        $formFields['title'] = array(
            'title' => __('Title displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Title of generic payment option displayed on your page with means of payment choices', WC_ETRANSACTIONS_PLUGIN),
            'default' => __($defaults['title'], WC_ETRANSACTIONS_PLUGIN),
        );
        $allFiles = scandir(plugin_dir_path(__DIR__) . 'images/');
        $fileList = array();
        foreach ($allFiles as $id => $file) {
            if (in_array(explode(".", $file)[1], array('png','jpg','gif','svg'))) {
                $fileList[$file]=$file;
            }
        }
        $formFields['icon'] = array(
            'title' => __('Logo displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'select',
            'description' => __('Title of generic payment option displayed on your page with means of payment choices. Files are available on directory: ', WC_ETRANSACTIONS_PLUGIN) . apply_filters(WC_ETRANSACTIONS_PLUGIN, '' . plugin_dir_url(__DIR__) . 'images/'),
            'default' => __($defaults['icon'], WC_ETRANSACTIONS_PLUGIN),
            'options' => $fileList,
        );
        $formFields['description'] = array(
            'title' => __('Description displayed on your payment page', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'textarea',
            'description' => __('Description of generic payment option displayed on your page with means of payment choices.', WC_ETRANSACTIONS_PLUGIN),
            'default' => __($defaults['description'], WC_ETRANSACTIONS_PLUGIN),
        );
        $formFields['global_settings'] = array(
            'title' => __('Cards default settings', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
            'default' => null,
        );
        if ($this->type == 'standard') {
            $formFields['delay'] = array(
                'title' => __('Debit type', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'select',
                'options' => array(
                    '0' => __('Immediate', WC_ETRANSACTIONS_PLUGIN),
                    WC_Etransactions_Config::ORDER_STATE_DELAY => __('On order event', WC_ETRANSACTIONS_PLUGIN),
                    '1' => __('1 day', WC_ETRANSACTIONS_PLUGIN),
                    '2' => __('2 days', WC_ETRANSACTIONS_PLUGIN),
                    '3' => __('3 days', WC_ETRANSACTIONS_PLUGIN),
                    '4' => __('4 days', WC_ETRANSACTIONS_PLUGIN),
                    '5' => __('5 days', WC_ETRANSACTIONS_PLUGIN),
                    '6' => __('6 days', WC_ETRANSACTIONS_PLUGIN),
                    '7' => __('7 days', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default' => $defaults['delay'],
            );
            $formFields['capture_order_status'] = array(
                'title' => __('Order status that trigger capture', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'select',
                'options' => wc_get_order_statuses(),
                'default' => $defaults['capture_order_status'],
                'class' => (!$this->_config->isPremium() || $this->_config->getDelay() != WC_Etransactions_Config::ORDER_STATE_DELAY ? 'hidden' : ''),
            );
        }
        if ($this->type != 'threetime') {
            $formFields['payment_ux'] = array(
                'title' => __('Display of payment method', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'select',
                'label' => __('This setting does not apply on the generic method (redirect method is forced)', WC_ETRANSACTIONS_PLUGIN),
                'options' => array(
                    'redirect' => __('Redirect method (default)', WC_ETRANSACTIONS_PLUGIN),
                    'seamless' => __('Seamless (iframe)', WC_ETRANSACTIONS_PLUGIN),
                ),
                'default' => $defaults['payment_ux'],
            );
            $formFields['allow_one_click_payment'] = array(
                'title' => __('1-click payment', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'checkbox',
                'label' => __('Allow your customer to pay without entering his card number for every order (only for payment with CB, VISA and Mastercard)', WC_ETRANSACTIONS_PLUGIN),
                'default' => $defaults['allow_one_click_payment'],
                'class' => (!$this->_config->isPremium() ? 'hidden' : ''),
            );
        }
        $formFields['amount'] = array(
            'title' => __('Minimal amount', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Enable this means of payment only for orders with amount equal or greater than the amount configured (let it empty for no condition)', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['amount']
        );

        return $formFields;
    }

    /**
     * Retrieve the fields for the cards configuration
     *
     * @return array
     */
    protected function getCardsConfigurationFields()
    {
        if (!isset($this->_config)) {
            $this->_config = $defaults;
        }
        $defaults = $this->defaultConfig->getDefaults();

        $formFields = array();
        $formFields['title_cards_configuration'] = array(
            'title' => __('Means of payment configuration', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
            'default' => null,
        );

        return $formFields;
    }

    /**
     * Retrieve the fields for the account configuration
     *
     * @return array
     */
    protected function getAccountConfigurationFields()
    {
        if (!isset($this->_config)) {
            $this->_config = $defaults;
        }
        $defaults = $this->defaultConfig->getDefaults();

        $formFields = array();
        $formFields['subscription'] = array(
            'title' => __('Up2pay e-Transactions offer subscribed', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'select',
            'default' => $defaults['subscription'],
            'options' => array(
                '1' => __('e-Transactions Access', WC_ETRANSACTIONS_PLUGIN),
                '2' => __('e-Transactions Premium', WC_ETRANSACTIONS_PLUGIN),
            ),
        );
        $formFields['site'] = array(
            'title' => __('Site number', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Site number provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['site'],
        );
        $formFields['rank'] = array(
            'title' => __('Rank number', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Rank number provided by E-Transactions (two last digits).', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['rank'],
        );
        $formFields['identifier'] = array(
            'title' => __('Login', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Internal login provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['identifier'],
        );
        $formFields['pass'] = array(
            'title' => __('Backoffice Password', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Internal backoffice password provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['pass'],
            'class' => (!$this->_config->isPremium() ? 'hidden' : ''),
        );
        $formFields['hmackey'] = array(
            'title' => __('HMAC', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Secrete HMAC key to create using the E-Transactions interface.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['hmackey'],
        );
        $formFields['technical'] = array(
            'title' => __('Technical settings', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
            'default' => null,
        );
        $formFields['ips'] = array(
            'title' => __('Allowed IPs', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('A coma separated list of E-Transactions IPs.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['ips'],
        );
        $formFields['debug'] = array(
            'title' => __('Debug', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'checkbox',
            'label' => __('Enable some debugging information', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['debug'],
        );

        return $formFields;
    }

    /**
     * Check If The Gateway Is Available For Use
     *
     * @access public
     * @return bool
     */
    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }
        $minimal = $this->_config->getAmount();
        if (empty($minimal)) {
            return true;
        }

        // Retrieve total from cart, or order
        $total = null;
        if (is_checkout_pay_page() && get_query_var('order-pay')) {
            $order = wc_get_order((int)get_query_var('order-pay'));
            if (!empty($order)) {
                $total = $order->get_total();
            }
        } elseif (WC()->cart) {
            $total = WC()->cart->total;
        }

        if ($total === null) {
            // Unable to retrieve order/cart total
            return false;
        }

        return $total >= $minimal;
    }

    /**
     * Process the payment, redirecting user to E-Transactions.
     *
     * @param int $order_id The order ID
     * @return array TODO
     */
    public function process_payment($orderId)
    {
        $order = wc_get_order($orderId);

        // Save the specific card/token id to use while creating the order
        $this->savePaymentMethodCardOrTokenToForce($orderId);

        // Save the checkbox state for "Save payment method"
        $this->saveAllowTokenInformation($orderId);

        $message = __('Customer is redirected to E-Transactions payment page', WC_ETRANSACTIONS_PLUGIN);
        $this->_etransactions->addOrderNote($order, $message);

        return array(
            'result' => 'success',
            'redirect' => add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->order_key, $order->get_checkout_order_received_url())),
        );
    }

    public function receipt_page($orderId)
    {
        $order = wc_get_order($orderId);
        $urls = $this->getReturnUrls('', $order);

        $params = $this->_etransactions->buildSystemParams($order, $this->type, $urls);

        try {
            $url = $this->_etransactions->getSystemUrl($order);
        } catch (Exception $e) {
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<form><center><button onClick='history.go(-1);return true;'>" . __('Back...', WC_ETRANSACTIONS_PLUGIN) . "</center></button></form>";
            exit;
        }

        // Output the payment form or iframe if seemsless is enabled
        $this->outputPaymentForm($order, $url, $params);
    }

    /**
     * Retrieve all return URL
     *
     * @param string $suffix
     * @param WC_Order $order
     * @return array
     */
    protected function getReturnUrls($suffix = '', $order = null)
    {
        $pbxAnnule = null;
        if (!empty($order)) {
            $pbxAnnule = $order->get_checkout_payment_url();
        }

        if (!is_multisite()) {
            return array(
                'PBX_ANNULE' => (!empty($pbxAnnule) ? $pbxAnnule : add_query_arg('status', 'cancel' . $suffix, add_query_arg('wc-api', get_class($this), get_permalink()))),
                'PBX_EFFECTUE' => add_query_arg('status', 'success' . $suffix, add_query_arg('wc-api', get_class($this), get_permalink())),
                'PBX_REFUSE' => add_query_arg('status', 'failed' . $suffix, add_query_arg('wc-api', get_class($this), get_permalink())),
                'PBX_REPONDRE_A' => add_query_arg('status', 'ipn' . $suffix, add_query_arg('wc-api', get_class($this), get_permalink())),
            );
        }

        return array(
            'PBX_ANNULE' => (!empty($pbxAnnule) ? $pbxAnnule : site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'cancel' . $suffix)))),
            'PBX_EFFECTUE' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'success' . $suffix))),
            'PBX_REFUSE' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'failed' . $suffix))),
            'PBX_REPONDRE_A' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'ipn' . $suffix))),
        );
    }

    protected function outputPaymentForm($order, $url, $params)
    {
        $debugMode = $this->_config->isDebug();
        if ($this->_config->getPaymentUx($order) == WC_Etransactions_Config::PAYMENT_UX_REDIRECT) {
            ?>
            <form id="pbxep_form" method="post" action="<?php echo esc_url($url); ?>" enctype="application/x-www-form-urlencoded">
                <?php if ($debugMode) : ?>
                    <p>
                        <?php echo __('This is a debug view. Click continue to be redirected to E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN); ?>
                    </p>
                <?php else : ?>
                    <p>
                        <?php echo __('You will be redirected to the E-Transactions payment page. If not, please use the button bellow.', WC_ETRANSACTIONS_PLUGIN); ?>
                    </p>
                    <script type="text/javascript">
                        window.setTimeout(function () {
                            document.getElementById('pbxep_form').submit();
                        }, 1);
                    </script>
                <?php endif; ?>
                <center><button><?php echo __('Continue...', WC_ETRANSACTIONS_PLUGIN); ?></button></center>
                <?php
                $type = $debugMode ? 'text' : 'hidden';
            foreach ($params as $name => $value) {
                $name = esc_attr($name);
                $value = esc_attr($value);
                if ($debugMode) {
                    echo '<p><label for="' . $name . '">' . $name . '</label>';
                }
                echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . $value . '" />';
                if ($debugMode) {
                    echo '</p>';
                }
            } ?>
            </form>
            <?php
        } else {
            $this->load_custom_front_assets(); ?>
            <input id="pbx-nonce" type="hidden" value="<?= wp_create_nonce($this->id . '-order-poll-' . $order->get_id()); ?>" />
            <input id="pbx-id-order" type="hidden" value="<?= (int)$order->get_id(); ?>" />
            <iframe
                id="pbx-seamless-iframe"
                src="<?php echo esc_url($url) . '?' . http_build_query($params); ?>"
                scrolling="no"
                frameborder="0"
            >
            </iframe>
            <script>
            if (window.history && window.history.pushState) {
                window.history.pushState('pbx-forward', null, '');
                window.addEventListener('popstate', function() {
                    window.location = <?php echo json_encode($params['PBX_ANNULE']); ?>;
                });
            }
            </script>
            <?php
            if ($debugMode) {
                echo '<p>' . __('This is a debug view.', WC_ETRANSACTIONS_PLUGIN) . '</p>';
                echo '<form>';
                foreach ($params as $name => $value) {
                    $name = esc_attr($name);
                    $value = esc_attr($value);
                    echo '<p>';
                    echo '<label for="' . $name . '">' . $name . '</label>';
                    echo '<input type="text" id="' . $name . '" name="' . $name . '" value="' . $value . '" />';
                    echo '</p>';
                }
                echo '</form>';
            }
        }
    }

    public function api_call()
    {
        if (!isset($_GET['status'])) {
            header('Status: 404 Not found', true, 404);
            die();
        }

        switch ($_GET['status']) {
            case 'cancel':
                return $this->on_payment_canceled();
                break;

            case 'failed':
                return $this->on_payment_failed();
                break;

            case 'ipn':
                return $this->on_ipn();
                break;

            case 'success':
                return $this->on_payment_succeed();
                break;

            // Tokenization
            case 'success-tokenization':
                return $this->onTokenizationSucceed();

            case 'ipn-tokenization':
                return $this->onTokenizationIpn();

            case 'cancel-tokenization':
                return wp_redirect(wc_get_endpoint_url('add-payment-method', '', wc_get_page_permalink('myaccount')));

            case 'failed-tokenization':
                return $this->onTokenizationFailed();

            default:
                header('Status: 404 Not found', true, 404);
                die();
        }
    }

    /**
     * Redirect the customer to the "Add payment method" page in case of failure
     *
     * @return void
     */
    protected function onTokenizationFailed()
    {
        try {
            $params = $this->_etransactions->getParams();
            $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
            $error = $this->_etransactions->toErrorMessage($params['error']);
            wc_add_notice(sprintf($message, $error), 'error');
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
        }

        wp_redirect(wc_get_endpoint_url('add-payment-method', '', wc_get_page_permalink('myaccount')));
    }

    /**
     * Retrieve parameters & customer id, backup the tokenized card (IPN case)
     *
     * @return void
     */
    protected function onTokenizationIpn()
    {
        try {
            $params = $this->_etransactions->getParams();
            $customerId = $this->_etransactions->untokenizeCustomerId($params['reference']);

            if ($params['error'] != '00000') {
                // Payment refused
                $error = $this->_etransactions->toErrorMessage($params['error']);
                if (WC()->debug === 'yes') {
                    $this->logger->add("etransactions", sprintf(__('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN), $error));
                }
                return;
            }

            $this->saveTokenToDatabase($params, $customerId);
        } catch (Exception $e) {
            if (WC()->debug === 'yes') {
                $this->logger->add("etransactions", $e->getMessage());
            }
        }
    }

    /**
     * Retrieve parameters & customer id, backup the tokenized card if not already exists
     * Redirect the customer to the payments methods list
     *
     * @return void
     */
    protected function onTokenizationSucceed()
    {
        try {
            $params = $this->_etransactions->getParams();
            $customerId = $this->_etransactions->untokenizeCustomerId($params['reference']);

            $this->saveTokenToDatabase($params, $customerId);
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            wp_redirect(wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
        }

        wc_add_notice(__('Your card has been added as a new payment method.', WC_ETRANSACTIONS_PLUGIN));
        wp_redirect(wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
    }

    public function on_payment_failed()
    {
        $order = null;
        try {
            $params = $this->_etransactions->getParams();

            if ($params !== false) {
                $order = $this->_etransactions->untokenizeOrder($params['reference']);
                $message = __('Customer is back from E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
                $message .= ' ' . __('Payment refused by E-Transactions', WC_ETRANSACTIONS_PLUGIN);
                $this->_etransactions->addCartErrorMessage($message);
            }
        } catch (Exception $e) {
            // Ignore
        }

        $this->redirectToCheckout($order);
    }

    public function on_payment_canceled()
    {
        $order = null;
        try {
            $params = $this->_etransactions->getParams();

            if ($params !== false) {
                $order = $this->_etransactions->untokenizeOrder($params['reference']);
                $message = __('Payment canceled', WC_ETRANSACTIONS_PLUGIN);
                $this->_etransactions->addCartErrorMessage($message);
            }
        } catch (Exception $e) {
            // Ignore
        }

        $this->redirectToCheckout($order);
    }

    public function on_payment_succeed()
    {
        $order = null;
        try {
            $params = $this->_etransactions->getParams();
            if ($params === false) {
                return;
            }

            // Retrieve order
            $order = $this->_etransactions->untokenizeOrder($params['reference']);

            // Check required parameters
            $this->checkRequiredParameters($order, $params);

            $message = __('Customer is back from E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
            $this->_etransactions->addOrderNote($order, $message);
            WC()->cart->empty_cart();

            // Payment success
            $this->addPaymentInfosAndChangeOrderStatus($order, $params, 'customer');

            // Save card token information
            $this->saveCardTokenAfterPayment($order, $params);

            wp_redirect($order->get_checkout_order_received_url());
            die();
        } catch (Exception $e) {
            if (WC()->debug === 'yes') {
                $this->logger->add("etransactions", $e->getMessage());
            }
        }

        $this->redirectToCheckout($order);
    }

    /**
     * Check required parameters on IPN / Customer back on shop
     *
     * @param WC_Order $order
     * @param array $params
     * @return void
     */
    protected function checkRequiredParameters(WC_Order $order, $params)
    {
        $requiredParams = array('amount', 'transaction', 'error', 'reference', 'sign', 'date', 'time');
        foreach ($requiredParams as $requiredParam) {
            if (!isset($params[$requiredParam])) {
                $message = sprintf(__('Missing %s parameter in E-Transactions call', WC_ETRANSACTIONS_PLUGIN), $requiredParam);
                $this->_etransactions->addOrderNote($order, $message);
                throw new Exception($message);
            }
        }
    }

    /**
     * Save payment infos, add note on order and change its status
     *
     * @param WC_Order $order
     * @param array $params
     * @param string $context (ipn or customer)
     * @return void
     */
    protected function addPaymentInfosAndChangeOrderStatus(WC_Order $order, $params, $context)
    {
        global $wpdb;

        // Check if the order has already been captured
        // Manage specific LIMONETIK case
        if ($this->_etransactions->hasOrderPayment($order->get_id()) && $params['paymentType'] != 'LIMONETIK') {
            return;
        }

        if ($params['error'] != '00000') {
            // Payment refused
            $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
            $error = $this->_etransactions->toErrorMessage($params['error']);
            $message = sprintf($message, $error);
            $this->_etransactions->addOrderNote($order, $message);
            return;
        }

        // Payment accepted / author OK
        switch ($this->type) {
            case 'standard':
                switch ($params['cardType']) {
                    case 'CVCONNECT':
                        $paymentType = 'first_payment';
                        if ($context == 'customer') {
                            $paymentType = 'capture';
                        }
                        if ($this->_etransactions->hasOrderPayment($order->get_id(), $paymentType)) {
                            break;
                        }
                        $this->_etransactions->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                        $this->_etransactions->addOrderPayment($order, $paymentType, $params);
                        $order->payment_complete($params['transaction']);
                        break;
                    case 'LIMOCB':
                        if ($this->_etransactions->hasOrderPayment($order->get_id(), 'second_payment')) {
                            break;
                        }

                        $this->_etransactions->addOrderNote($order, __('Second payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                        $this->_etransactions->addOrderPayment($order, 'second_payment', $params);
                        $order->payment_complete($params['transaction']);
                        break;
                    default:
                        if ($this->_config->getDelay() == WC_Etransactions_Config::ORDER_STATE_DELAY) {
                            $this->_etransactions->addOrderPayment($order, 'authorization', $params);
                            $this->_etransactions->addOrderNote($order, __('Payment was authorized by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                            $order->update_status('on-hold');
                        } else {
                            $this->_etransactions->addOrderPayment($order, 'capture', $params);
                            $this->_etransactions->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                            $order->payment_complete($params['transaction']);
                        }
                        break;
                }
                break;

            case 'threetime':
                $sql = 'select distinct type from ' . $wpdb->prefix . 'wc_etransactions_payment where order_id = ' . $order->get_id();
                $done = $wpdb->get_col($sql);
                if (!in_array('first_payment', $done)) {
                    $this->_etransactions->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                    $order->payment_complete($params['transaction']);
                    $this->_etransactions->addOrderPayment($order, 'first_payment', $params);
                } elseif (!in_array('second_payment', $done)) {
                    $this->_etransactions->addOrderNote($order, __('Second payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                    $this->_etransactions->addOrderPayment($order, 'second_payment', $params);
                } elseif (!in_array('third_payment', $done)) {
                    $this->_etransactions->addOrderNote($order, __('Third payment was captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                    $this->_etransactions->addOrderPayment($order, 'third_payment', $params);
                } else {
                    $message = __('Invalid three-time payment status', WC_ETRANSACTIONS_PLUGIN);
                    $this->_etransactions->addOrderNote($order, $message);
                    throw new Exception($message);
                }
                break;

            default:
                $message = __('Unexpected type %s', WC_ETRANSACTIONS_PLUGIN);
                $message = sprintf($message, $type);
                $this->_etransactions->addOrderNote($order, $message);
                throw new Exception($message);
        }
    }

    public function on_ipn()
    {
        try {
            $params = $this->_etransactions->getParams();

            if ($params === false) {
                return;
            }

            $order = $this->_etransactions->untokenizeOrder($params['reference']);

            // IP not allowed
            /*
            $allowedIps = $this->_config->getAllowedIps();
            $currentIp = $this->_etransactions->getClientIp();
            if (!in_array($currentIp, $allowedIps)) {
                $message = __('IPN call from %s not allowed.', WC_ETRANSACTIONS_PLUGIN);
                $message = sprintf($message, $currentIp);
                $this->_etransactions->addOrderNote($order, $message);
                throw new Exception($message);
            }
            // removed by JC: no need for IP checking anymore.
            */

            // Check required parameters
            $this->checkRequiredParameters($order, $params);

            // Payment success
            $this->addPaymentInfosAndChangeOrderStatus($order, $params, 'ipn');

            // Save card token information
            $this->saveCardTokenAfterPayment($order, $params);
        } catch (Exception $e) {
            if (WC()->debug === 'yes') {
                $this->logger->add("etransactions", $e->getMessage());
            }
        }
    }

    public function redirectToCheckout($order)
    {
        if ($order !== null) {
            // Try to pay again, redirect to checkout page
            wp_redirect($order->get_checkout_payment_url());
        } else {
            // Unable to retrieve the order, redirect to shopping cart
            wp_redirect(WC()->cart->get_cart_url());
        }
        die();
    }

    public function checkCrypto()
    {
        return $this->encryption->decrypt($this->settings['hmackey']);
    }

    abstract public function showDetails($orderId);
}
