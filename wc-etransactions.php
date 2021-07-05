<?php
/**
 * Plugin Name: E-Transactions
 * Description: E-Transactions gateway payment plugins for WooCommerce
 * Version: 0.9.9.9.3
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
defined('WC_ETRANSACTIONS_VERSION') or define('WC_ETRANSACTIONS_VERSION', '0.9.9.9.3');
defined('WC_ETRANSACTIONS_KEY_PATH') or define('WC_ETRANSACTIONS_KEY_PATH', ABSPATH . '/kek.php');

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
        $tableName = $wpdb->prefix.'wc_etransactions_payment';
        $sql = "CREATE TABLE $tableName (
             id int not null auto_increment,
             order_id bigint not null,
             type enum('capture', 'first_payment', 'second_payment', 'third_payment') not null,
             data varchar(2048) not null,
             KEY order_id (order_id),
             PRIMARY KEY  (id))";

        require_once(ABSPATH.'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option(WC_ETRANSACTIONS_PLUGIN.'_version', WC_ETRANSACTIONS_VERSION);
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
}

function wc_etransactions_register(array $methods)
{
    $methods[] = 'WC_EStdGw';
    $methods[] = 'WC_E3Gw';
    return $methods;
}

register_activation_hook(__FILE__, 'wc_etransactions_installation');
add_action('plugins_loaded', 'wc_etransactions_initialization');
add_filter('woocommerce_payment_gateways', 'wc_etransactions_register');

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

add_action('woocommerce_admin_order_data_after_billing_address', 'wc_etransactions_show_details');

function hmac_admin_notice()
{
    if (wooCommerceActiveETwp()) {
        $temp = new WC_EStdGw();
        $plugin_data = get_plugin_data(__FILE__);
        $plugin_name = $plugin_data['Name'];
        if (!$temp->checkCrypto()) {
            echo "<div class='notice notice-error  is-dismissible'>
                <p><strong>/!\ Attention ! plugin ".$plugin_name." : </strong>".__('HMAC key cannot be decrypted please re-enter or reinitialise it.', WC_ETRANSACTIONS_PLUGIN)."</p>
                </div>";
        }
    } else {
        echo "<div class='notice notice-error  is-dismissible'>
              <p><strong>/!\ Attention ! plugin E-Transactions : </strong>".__('Woocommerce is not active !', 'wc-etransactions')."</p>
             </div>";
    }
}
add_action('admin_notices', 'hmac_admin_notice');
