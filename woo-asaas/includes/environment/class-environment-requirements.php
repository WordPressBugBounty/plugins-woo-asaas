<?php
/**
 * Environment Requirements
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * Environment requirements checker
 */
class Environment_Requirements {

	private const MIN_PHP_VERSION = '7.4';
	private const MIN_WP_VERSION  = '6.2';
	private const MIN_WC_VERSION  = '8.2';

	/**
	 * PHP version
	 *
	 * @var string
	 */
	private $php_version;

	/**
	 * WordPress version
	 *
	 * @var string
	 */
	private $wp_version;

	/**
	 * WooCommerce version
	 *
	 * @var string
	 */
	private $wc_version;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->php_version = PHP_VERSION;
		$this->wp_version  = $GLOBALS['wp_version'] ?? null;
		$this->wc_version  = defined( 'WC_VERSION' ) ? WC_VERSION : null;
	}

	/**
	 * Check if all dependencies are valid
	 *
	 * @return bool
	 */
	public function has_valid_dependencies() {
		return $this->is_php_version_supported()
			&& $this->is_wp_version_supported()
			&& $this->is_wc_version_supported();
	}

	/**
	 * Check if PHP version is supported
	 *
	 * @return bool
	 */
	public function is_php_version_supported() {
		return version_compare( $this->php_version, self::MIN_PHP_VERSION, '>=' );
	}

	/**
	 * Check if WordPress version is supported
	 *
	 * @return bool
	 */
	public function is_wp_version_supported() {
		return $this->wp_version && version_compare( $this->wp_version, self::MIN_WP_VERSION, '>=' );
	}

	/**
	 * Check if WooCommerce version is supported
	 *
	 * @return bool
	 */
	public function is_wc_version_supported() {
		return $this->wc_version && version_compare( $this->wc_version, self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Display dependency admin notice
	 *
	 * @return void
	 */
	public function display_dependency_admin_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$notices = array();

		if ( ! $this->is_php_version_supported() ) {
			/* translators: %1$s: Minimum PHP version, %2$s: Current PHP version */
			$message   = esc_html__(
				'Asaas Gateway requires PHP %1$s or higher. You are running PHP %2$s.',
				'woo-asaas'
			);
			$notices[] = sprintf( $message, self::MIN_PHP_VERSION, $this->php_version );
		}

		if ( ! $this->is_wp_version_supported() ) {
			/* translators: %1$s: Minimum WordPress version, %2$s: Current WordPress version */
			$message   = esc_html__(
				'Asaas Gateway requires WordPress %1$s or higher. You are running WordPress %2$s.',
				'woo-asaas'
			);
			$notices[] = sprintf( $message, self::MIN_WP_VERSION, $this->wp_version );
		}

		if ( ! $this->is_wc_version_supported() ) {
			/* translators: %1$s: Minimum WooCommerce version, %2$s: Current WooCommerce version */
			$message    = esc_html__(
				'Asaas Gateway requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.',
				'woo-asaas'
			);
			$wc_version = $this->wc_version ?: esc_html__( 'not installed', 'woo-asaas' );
			$notices[]  = sprintf( $message, self::MIN_WC_VERSION, $wc_version );
		}

		foreach ( $notices as $notice ) {
			echo '<div class="error"><p><strong>' . esc_html( $notice ) . '</strong></p></div>';
		}
	}
}
