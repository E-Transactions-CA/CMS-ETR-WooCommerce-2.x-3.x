<?php
/**
 * Plugin Name: E-Transactions
 * Description: E-Transactions gateway payment plugins for WooCommerce
 * Version: 1.0.1
 * Author: E-Transactions
 * Author URI: http://www.e-transactions.fr
 * Text Domain: wc-etransactions
 *
 * @package WordPress
 * @since 0.9.0
 */

// Ensure not called directly
if (!defined('ABSPATH')) {
    exit;
}

$previousET = (in_array('woocommerce-etransactions/woocommerce-etransactions.php', apply_filters('active_plugins', get_option('active_plugins'))));
if (is_multisite()) {
    // Si multisite
    $previousET = (array_key_exists('woocommerce-etransactions/woocommerce-etransactions.php', apply_filters('active_plugins', get_site_option('active_sitewide_plugins'))));
}
if ($previousET) {
    die("Une version pr&eacute;c&eacute;dente du plugin E-Transactions est d&eacute;j&agrave; install&eacute;e. veuillez la d&eacute;sactiver avant d'activer celle-ci.");
}

function wooCommerceActiveETwp()
{
    // Makes sure the plugin is defined before trying to use it
    if (!class_exists('WC_Payment_Gateway')) {
        return false;
    }
    return true;
}

// Ensure WooCommerce is active
if (defined('WC_ETRANSACTIONS_PLUGIN')) {
    _e('Previous plugin already installed. deactivate the previous one first.', WC_ETRANSACTIONS_PLUGIN);
    die(__('Previous plugin already installed. deactivate the previous one first.', WC_ETRANSACTIONS_PLUGIN));
}
defined('WC_ETRANSACTIONS_PLUGIN') or define('WC_ETRANSACTIONS_PLUGIN', 'wc-etransactions');
defined('WC_ETRANSACTIONS_VERSION') or define('WC_ETRANSACTIONS_VERSION', '1.0.1');
defined('WC_ETRANSACTIONS_KEY_PATH') or define('WC_ETRANSACTIONS_KEY_PATH', ABSPATH . '/kek.php');
defined('WC_ETRANSACTIONS_PLUGIN_URL') or define('WC_ETRANSACTIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

function wc_etransactions_installation()
{
    global $wpdb;
    $installed_ver = get_option("WC_ETRANSACTIONS_PLUGIN.'_version'");
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    if (!wooCommerceActiveETwp()) {
        _e('WooCommerce must be activated', WC_ETRANSACTIONS_PLUGIN);
        die();
    }
    if ($installed_ver != WC_ETRANSACTIONS_VERSION) {
        require_once(ABSPATH.'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE `{$wpdb->prefix}wc_etransactions_payment` (
             id int not null auto_increment,
             order_id bigint not null,
             type enum('capture', 'authorization', 'first_payment', 'second_payment', 'third_payment') not null,
             data varchar(2048) not null,
             KEY order_id (order_id),
             PRIMARY KEY (id));";

        $sql .= "CREATE TABLE `{$wpdb->prefix}wc_etransactions_cards` (
            `id_card` int(2) not null auto_increment PRIMARY KEY,
            `payment_method` varchar(30) not null,
            `env` enum('test', 'production') not null,
            `user_xp` enum('redirect', 'seamless') null,
            `type_payment` varchar(12) not null,
            `type_card` varchar(30) not null,
            `label` varchar(30) not null,
            `position` tinyint(1) unsigned default '0' not null,
            `force_display` tinyint(1) unsigned default '0' null,
            `allow_iframe` tinyint(1) unsigned default '1' null,
            `debit_differe` tinyint(1) unsigned null,
            `3ds` tinyint(1) unsigned null,
            UNIQUE KEY `cards_unique` (`env`, `payment_method`, `type_payment`, `type_card`));";
        dbDelta($sql);
        wc_etransactions_sql_initialization();
        update_option(WC_ETRANSACTIONS_PLUGIN.'_version', WC_ETRANSACTIONS_VERSION);
    }
}

function wc_etransactions_sql_initialization()
{
    global $wpdb;

    require_once(dirname(__FILE__).'/class/wc-etransactions-config.php');

    // Remove cards that aren't used anymore into default card list
    $existingCards = $wpdb->get_results("select distinct `type_payment`, `type_card` from `{$wpdb->prefix}wc_etransactions_cards`");
    foreach ($existingCards as $existingCard) {
        $cardExists = false;
        // Check if card already exists
        foreach (WC_Etransactions_Config::getDefaultCards() as $card) {
            if ($card['type_payment'] == $existingCard->type_payment
            && $card['type_card'] == $existingCard->type_card) {
                $cardExists = true;
                break;
            }
        }
        if (!$cardExists) {
            // The card is not managed anymore, delete it
            $wpdb->delete($wpdb->prefix . 'wc_etransactions_cards', array(
                'type_payment' => $existingCard->type_payment,
                'type_card' => $existingCard->type_card,
            ));
        }
    }

    // Create the cards
    foreach (array('test', 'production') as $env) {
        foreach (array('etransactions_std') as $paymentMethod) {
            foreach (WC_Etransactions_Config::getDefaultCards() as $card) {
                $card['env'] = $env;
                $card['payment_method'] = $paymentMethod;
                // Check if card already exists
                $sql = $wpdb->prepare("select `id_card` from `{$wpdb->prefix}wc_etransactions_cards`
                where `env` = %s
                and `payment_method` = %s
                and `type_payment` = %s
                and `type_card` = %s", $card['env'], $paymentMethod, $card['type_payment'], $card['type_card']);
                $idCard = $wpdb->get_col($sql);
                if (!empty($idCard)) {
                    continue;
                }
                // Create the card
                $wpdb->insert($wpdb->prefix . 'wc_etransactions_cards', $card);
            }
        }
    }
}

function wc_etransactions_initialization()
{
    if (!wooCommerceActiveETwp()) {
        return ("Woocommerce not Active") ;
    }
    $class = 'WC_Etransactions_Abstract_Gateway';

    if (!class_exists($class)) {
        require_once(dirname(__FILE__).'/class/wc-etransactions-config.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-iso4217currency.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-iso3166-country.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-curl-helper.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-abstract-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-standard-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-threetime-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-encrypt.php');
    }

    load_plugin_textdomain(WC_ETRANSACTIONS_PLUGIN, false, dirname(plugin_basename(__FILE__)).'/lang/');

    $crypto = new ETransactionsEncrypt();
    if (!file_exists(WC_ETRANSACTIONS_KEY_PATH)) {
        $crypto->generateKey();
    }

    if (get_site_option(WC_ETRANSACTIONS_PLUGIN.'_version') != WC_ETRANSACTIONS_VERSION) {
        wc_etransactions_installation();
    }

    // Init hooks & filters
    wc_etransactions_register_hooks();
}

function wc_etransactions_register_hooks()
{
    // Register hooks & filters for each instance
    foreach (wc_get_etransactions_classes() as $gatewayClass) {
        $gatewayClass::getInstance($gatewayClass)->initHooksAndFilters();
    }

    add_filter('woocommerce_payment_gateways', 'wc_etransactions_register');
    add_action('woocommerce_admin_order_data_after_billing_address', 'wc_etransactions_show_details');
}

function wc_get_etransactions_classes()
{
    return array(
        'WC_EStdGw',
        'WC_E3Gw',
    );
}

function wc_etransactions_register(array $methods)
{
    return array_merge($methods, wc_get_etransactions_classes());
}

function wc_etransactions_show_details(WC_Order $order)
{
    $method = get_post_meta($order->get_id(), '_payment_method', true);
    switch ($method) {
        case 'etransactions_std':
            $method = new WC_EStdGw();
            $method->showDetails($order);
            break;
        case 'etransactions_3x':
            $method = new WC_E3Gw();
            $method->showDetails($order);
            break;
    }
}

register_activation_hook(__FILE__, 'wc_etransactions_installation');
add_action('plugins_loaded', 'wc_etransactions_initialization');
