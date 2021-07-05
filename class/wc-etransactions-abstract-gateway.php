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

    public function __construct()
    {
        // Logger for debug if needed
        if (WC()->debug === 'yes') {
            $this->logger = WC()->logger();
        }

        $this->method_description = '<center><img src="' . plugins_url('images/logo.png', plugin_basename(dirname(__FILE__))) . '"/></center>';

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->_config = new WC_Etransactions_Config($this->settings, $this->defaultTitle, $this->defaultDesc);
        $this->_etransactions = new WC_Etransactions($this->_config);

        // $this->title = $this->_config->getTitle();
        // $this->description = $this->_config->getDescription();

        $this->title = apply_filters('title', $this->_config->getTitle());
        $this->description = apply_filters('description', $this->_config->getDescription());
        $this->icon = apply_filters(WC_ETRANSACTIONS_PLUGIN, plugin_dir_url(__DIR__) . 'images/') . apply_filters('icon', $this->_config->getIcon());

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'api_call'));
    }

    /**
     * save_hmackey
     * Used to save the settings field of the custom type HSK
     * @param  array $field
     * @return void
     */
    public function process_admin_options()
    {
        $crypto = new ETransactionsEncrypt();
        if (!isset($_POST['crypted'])) {
            if (isset($_POST["woocommerce_etransactions_std_hmackey"])) {
                $_POST["woocommerce_etransactions_std_hmackey"] = $crypto->encrypt($_POST["woocommerce_etransactions_std_hmackey"]);
            } else {
                $_POST["woocommerce_etransactions_3x_hmackey"] = $crypto->encrypt($_POST["woocommerce_etransactions_3x_hmackey"]);
            }
            $_POST['crypted'] = true;
        }

        parent::process_admin_options();
    }

    public function admin_options()
    {
        $crypt = new ETransactionsEncrypt();
        $this->settings['hmackey'] = $crypt->decrypt($this->settings['hmackey']);

        parent::admin_options();
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $defaults = new WC_Etransactions_Config(array(), $this->defaultTitle, $this->defaultDesc);
        $defaults = $defaults->getDefaults();
        $this->form_fields = array();
        $this->form_fields['enabled'] = array(
            'title' => __('Enable/Disable', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('Enable E-Transactions Payment', WC_ETRANSACTIONS_PLUGIN),
            'default' => 'yes'
        );
        $this->form_fields['title'] = array(
            'title' => __('Title', 'woocommerce'),
            'type' => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
            'default' => __($defaults['title'], WC_ETRANSACTIONS_PLUGIN),
        );
        $allFiles = scandir(plugin_dir_path(__DIR__) . 'images/');
        $fileList = array();
        foreach ($allFiles as $id => $file) {
            if (in_array(explode(".", $file)[1], array('png','jpg','gif','svg'))) {
                $fileList[$file]=$file;
            }
        }
        $this->form_fields['icon'] = array(
            'title' => __('Icon file', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'select',
            'description' => __('Icon file to be displayed to customers. file are located in: ', WC_ETRANSACTIONS_PLUGIN) . apply_filters(WC_ETRANSACTIONS_PLUGIN, '' . plugin_dir_url(__DIR__) . 'images/'),
            'default' => __($defaults['icon'], WC_ETRANSACTIONS_PLUGIN),
            'options' => $fileList,
        );
        $this->form_fields['description'] = array(
            'title' => __('Description', 'woocommerce'),
            'type' => 'textarea',
            'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
            'default' => __($defaults['description'], WC_ETRANSACTIONS_PLUGIN),
        );
        if ($this->type == 'standard') {
            $this->form_fields['delay'] = array(
                'title' => __('Delay', WC_ETRANSACTIONS_PLUGIN),
                'type' => 'select',
                'options' => array(
                    '0' => __('Immediate', WC_ETRANSACTIONS_PLUGIN),
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
        }
        $this->form_fields['amount'] = array(
            'title' => __('Minimal amount', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Enable this payment method for order with amount greater or equals to this amount (empty to ignore this condition)', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['amount']
        );
        $this->form_fields['etransactions_account'] = array(
            'title' => __('E-Transactions account', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
        );
        $this->form_fields['site'] = array(
            'title' => __('Site number', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Site number provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['site'],
        );
        $this->form_fields['rank'] = array(
            'title' => __('Rank number', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Rank number provided by E-Transactions (two last digits).', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['rank'],
        );
        $this->form_fields['identifier'] = array(
            'title' => __('Login', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Internal login provided by E-Transactions.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['identifier'],
        );
        $this->form_fields['hmackey'] = array(
            'title' => __('HMAC', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('Secrete HMAC key to create using the E-Transactions interface.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['hmackey'],
        );
        $this->form_fields['environment'] = array(
            'title' => __('Environment', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'select',
            'description' => __('In test mode your payments will not be sent to the bank.', WC_ETRANSACTIONS_PLUGIN),
            'options' => array(
                'PRODUCTION' => __('Production', WC_ETRANSACTIONS_PLUGIN),
                'TEST' => __('Test', WC_ETRANSACTIONS_PLUGIN),
            ),
            'default' => $defaults['environment'],
        );
        $this->form_fields['technical'] = array(
            'title' => __('Technical settings', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'title',
        );
        $this->form_fields['ips'] = array(
            'title' => __('Allowed IPs', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'text',
            'description' => __('A coma separated list of E-Transactions IPs.', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['ips'],
        );
        $this->form_fields['debug'] = array(
            'title' => __('Debug', WC_ETRANSACTIONS_PLUGIN),
            'type' => 'checkbox',
            'label' => __('Enable some debugging information', WC_ETRANSACTIONS_PLUGIN),
            'default' => $defaults['debug'],
        );
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
        $total = WC()->cart->total;

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

        if (!is_multisite()) {
            $urls = array(
                'PBX_ANNULE' => add_query_arg('status', 'cancel', add_query_arg('wc-api', get_class($this), get_permalink())),
                'PBX_EFFECTUE' => add_query_arg('status', 'success', add_query_arg('wc-api', get_class($this), get_permalink())),
                'PBX_REFUSE' => add_query_arg('status', 'failed', add_query_arg('wc-api', get_class($this), get_permalink())),
                'PBX_REPONDRE_A' => add_query_arg('status', 'ipn', add_query_arg('wc-api', get_class($this), get_permalink())),
            );
        } else {
            $urls = array(
                'PBX_ANNULE' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'cancel'))),
                'PBX_EFFECTUE' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'success'))),
                'PBX_REFUSE' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'failed'))),
                'PBX_REPONDRE_A' => site_url(add_query_arg('wc-api', get_class($this), add_query_arg('status', 'ipn'))),
            );
        }

        $params = $this->_etransactions->buildSystemParams($order, $this->type, $urls);

        try {
            $url = $this->_etransactions->getSystemUrl();
        } catch (Exception $e) {
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<form><center><button onClick='history.go(-1);return true;'>" . __('Back...', WC_ETRANSACTIONS_PLUGIN) . "</center></button></form>";
            exit;
        }
        $debug = $this->_config->isDebug();
        ?>
        <form id="pbxep_form" method="post" action="<?php echo esc_url($url); ?>" enctype="application/x-www-form-urlencoded">
            <?php if ($debug) : ?>
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
            $type = $debug ? 'text' : 'hidden';
            foreach ($params as $name => $value) {
                $name = esc_attr($name);
                $value = esc_attr($value);
                if ($debug) {
                    echo '<p><label for="' . $name . '">' . $name . '</label>';
                }
                echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . $value . '" />';
                if ($debug) {
                    echo '</p>';
                }
            }
            ?>
        </form>
        <?php
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

            default:
                header('Status: 404 Not found', true, 404);
                die();
        }
    }

    public function on_payment_failed()
    {
        try {
            $params = $this->_etransactions->getParams();

            if ($params !== false) {
                $order = $this->_etransactions->untokenizeOrder($params['reference']);
                $message = __('Customer is back from E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
                $message .= ' ' . __('Payment refused by E-Transactions', WC_ETRANSACTIONS_PLUGIN);
                $order->cancel_order($message);
                $message = __('Payment refused by E-Transactions', WC_ETRANSACTIONS_PLUGIN);
                $this->_etransactions->addCartErrorMessage($message);
                $order->update_status('failed', $message);
            }
        } catch (Exception $e) {
            // Ignore
        }

        $this->redirectToCheckout();
    }

    public function on_payment_canceled()
    {
        try {
            $params = $this->_etransactions->getParams();

            if ($params !== false) {
                $order = $this->_etransactions->untokenizeOrder($params['reference']);
                $message = __('Payment was canceled by user on E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
                $order->cancel_order($message);
                $message = __('Payment canceled', WC_ETRANSACTIONS_PLUGIN);
                $this->_etransactions->addCartErrorMessage($message);
                $order->update_status('failed', $message);
            }
        } catch (Exception $e) {
            // Ignore
        }

        $this->redirectToCheckout();
    }

    public function on_payment_succeed()
    {
        try {
            $params = $this->_etransactions->getParams();
            if ($params !== false) {
                $order = $this->_etransactions->untokenizeOrder($params['reference']);
                $message = __('Customer is back from E-Transactions payment page.', WC_ETRANSACTIONS_PLUGIN);
                $this->_etransactions->addOrderNote($order, $message);
                WC()->cart->empty_cart();

                wp_redirect($order->get_checkout_order_received_url());
                die();
            }
        } catch (Exception $e) {
            // Ignore
        }

        $this->redirectToCheckout();
    }

    public function on_ipn()
    {
        global $wpdb;

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
            $requiredParams = array('amount', 'transaction', 'error', 'reference', 'sign', 'date', 'time');
            foreach ($requiredParams as $requiredParam) {
                if (!isset($params[$requiredParam])) {
                    $message = sprintf(__('Missing %s parameter in E-Transactions call', WC_ETRANSACTIONS_PLUGIN), $requiredParam);
                    $this->_etransactions->addOrderNote($order, $message);
                    throw new Exception($message);
                }
            }

            // Payment success
            if ($params['error'] == '00000') {
                switch ($this->type) {
                    case 'standard':
                        $this->_etransactions->addOrderNote($order, __('Payment was authorized and captured by E-Transactions.', WC_ETRANSACTIONS_PLUGIN));
                        $order->payment_complete($params['transaction']);
                        $this->_etransactions->addOrderPayment($order, 'capture', $params);
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
            } else {
                // Payment refused
                $message = __('Payment was refused by E-Transactions (%s).', WC_ETRANSACTIONS_PLUGIN);
                $error = $this->_etransactions->toErrorMessage($params['error']);
                $message = sprintf($message, $error);
                $this->_etransactions->addOrderPayment($order, 'failed_payment', $params);
                $this->_etransactions->addOrderNote($order, $message);
            }
        } catch (Exception $e) {
            if (WC()->debug === 'yes') {
                $this->logger->add("etransactions", $e->getMessage());
            }
        }
    }

    public function redirectToCheckout()
    {
        wp_redirect(WC()->cart->get_cart_url());
        die();
    }

    public function checkCrypto()
    {
        $crypt = new ETransactionsEncrypt();
        return $crypt->decrypt($this->settings['hmackey']);
    }

    abstract public function showDetails($orderId);
}
