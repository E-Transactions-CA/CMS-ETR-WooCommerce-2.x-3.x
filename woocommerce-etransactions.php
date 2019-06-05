<?php
/**
 * Plugin Name: WooCommerce E-Transactions Payment plugin
 * Description: E-Transactions gateway payment plugins for WooCommerce
 * Version: 0.9.8.5
 * Author: E-Transactions
 * Author URI: http://www.e-transactions.fr
 * 
 * @package WordPress
 * @since 0.9.0
 */
// Ensure not called directly
if (!defined('ABSPATH')) {
	exit;
}

function wooCommerceActiveET(){
	$wooCommerceActiveET = (in_array('woocommerce/woocommerce.php',apply_filters('active_plugins', get_option('active_plugins'))));
	if(is_multisite()){
		//Si multisite
		$wooCommerceActiveET = (array_key_exists('woocommerce/woocommerce.php', 
														apply_filters('active_plugins', get_site_option('active_sitewide_plugins'))));
	}
	return $wooCommerceActiveET;
}

// Ensure WooCommerce is active
if (!wooCommerceActiveET()) {
	return;
}

define('WC_ETRANSACTIONS_PLUGIN', 'woocommerce-etransactions');
define('WC_ETRANSACTIONS_VERSION', '0.9.8.5');
define('WC_ETRANSACTIONS_KEY_PATH', ABSPATH . '/kek.php');


function woocommerce_etransactions_installation() {
	global $wpdb;
	$installed_ver = get_option( "WC_ETRANSACTIONS_PLUGIN.'_version'" );
	
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if(!wooCommerceActiveET()) {
		_e('WooCommerce must be activated', WC_ETRANSACTIONS_PLUGIN);
		die();
	}

	if ( $installed_ver != WC_ETRANSACTIONS_VERSION ) {
		$tableName = $wpdb->prefix.'wc_etransactions_payment';
		$sql = "CREATE TABLE $tableName (
			 id int not null auto_increment,
			 order_id bigint not null,
			 type enum('capture', 'first_payment', 'second_payment', 'third_payment') not null,
			 data varchar(2048) not null,
			 KEY order_id (order_id),
			 PRIMARY KEY  (id))";

		require_once(ABSPATH.'wp-admin/includes/upgrade.php');

		dbDelta( $sql );

		update_option(WC_ETRANSACTIONS_PLUGIN.'_version', WC_ETRANSACTIONS_VERSION);
	}
	
}
function woocommerce_etransactions_initialization() {
	$class = 'WC_Etransactions_Abstract_Gateway';

	if (!class_exists($class)) {
		require_once(dirname(__FILE__).'/class/wc-etransactions-config.php');
		require_once(dirname(__FILE__).'/class/wc-etransactions-iso4217currency.php');
		require_once(dirname(__FILE__).'/class/wc-etransactions.php');
		require_once(dirname(__FILE__).'/class/wc-etransactions-abstract-gateway.php');
		require_once(dirname(__FILE__).'/class/wc-etransactions-standard-gateway.php');
        require_once(dirname(__FILE__).'/class/wc-etransactions-twotime-gateway.php');
		require_once(dirname(__FILE__).'/class/wc-etransactions-threetime-gateway.php');
		require_once(dirname(__FILE__).'/class/wc-etransactions-encrypt.php');
	}

	load_plugin_textdomain(WC_ETRANSACTIONS_PLUGIN, false, dirname(plugin_basename(__FILE__)).'/lang/');

	$crypto = new ETransactionsEncrypt();
	if(!file_exists(WC_ETRANSACTIONS_KEY_PATH))$crypto->generateKey();
	
    if ( get_site_option( WC_ETRANSACTIONS_PLUGIN.'_version' ) != WC_ETRANSACTIONS_VERSION ) {
        woocommerce_etransactions_installation();
    }
}

function woocommerce_etransactions_register(array $methods) {
	$methods[] = 'WC_Etransactions_Standard_Gateway';
    $methods[] = 'WC_Etransactions_Twotime_Gateway';
	$methods[] = 'WC_Etransactions_Threetime_Gateway';
	return $methods;
}

register_activation_hook(__FILE__, 'woocommerce_etransactions_installation');
add_action('plugins_loaded', 'woocommerce_etransactions_initialization');
add_filter('woocommerce_payment_gateways', 'woocommerce_etransactions_register');

function woocommerce_etransactions_show_details(WC_Order $order) {
	$method = get_post_meta($order->get_id(), '_payment_method', true);
	switch ($method) {
		case 'etransactions_std':
			$method = new WC_Etransactions_Standard_Gateway();
			$method->showDetails($order);
			break;
		case 'etransactions_3x':
			$method = new WC_Etransactions_Threetime_Gateway();
			$method->showDetails($order);
			break;
	}
}

add_action('woocommerce_admin_order_data_after_billing_address', 'woocommerce_etransactions_show_details');
