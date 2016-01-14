<?php
/*
Plugin Name: WooCommerce - Extended Payment Gateway
Plugin URI: http://oromedialab.com/
Description: Extended Payment Gateway for WooCommerce (http://paytronicks.com)
Author: Ibrahim Azhar Armar
Author URI: http://www.iarmar.com/
Version: 0.1
License: GPL-2.0+
*/

// Initialize plugin class
add_action('plugins_loaded', 'woocommerce_paytronicks_init', 0);

function woocommerce_paytronicks_init()
{
	// If WooCommerce is not available exit function
	if (!class_exists( 'WC_Payment_Gateway')) return;

	// Include our payment gateway class
	include_once('paytronicks.php');
}

// Add custom action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'woocommerce_paytronicks_action_links');
function woocommerce_paytronicks_action_links($links)
{
	$plugin_links = array(
		'<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout').'">'.__( 'Settings', 'paytronicks' ).'</a>',
	);
	return array_merge($plugin_links, $links);
}

// Register payment gatway with WooCommerce
add_filter('woocommerce_payment_gateways', 'register_payment_gateway');

function register_payment_gateway($methods)
{
	$methods[] = 'WC_Paytronicks';
	return $methods;
}
