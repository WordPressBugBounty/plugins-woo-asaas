<?php
/**
 * Plugin Name:          Asaas Gateway for WooCommerce
 * Plugin URI:           https://www.asaas.com
 * Description:          Take transparent credit card and bank ticket payment checkouts on your store using Asaas.
 * Author:               Asaas
 * Author URI:           https://www.asaas.com
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          woo-asaas
 * Domain Path:          /languages
 * Version:              2.7.5
 * Requires PHP:         7.4
 * Requires at least:    6.2
 * WC requires at least: 8.2
 * WC tested up to:      10.6
 *
 * @package WooAsaas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'plugins_loaded', function() {
	woo_asaas_check_dependencies();

	require_once __DIR__ . '/autoload.php';

	\WC_Asaas\WC_Asaas::get_instance();
});

/**
 * Check if required dependencies are met.
 *
 * @return void
 */
function woo_asaas_check_dependencies() {
	require_once __DIR__ . '/includes/environment/class-environment-requirements.php';

	$asaas_environment_requirements = new WC_Asaas\Environment\Environment_Requirements();

	if ( ! $asaas_environment_requirements->has_valid_dependencies() ) {
		add_action( 'admin_notices', array( $asaas_environment_requirements, 'display_dependency_admin_notice' ) );
	}
}
