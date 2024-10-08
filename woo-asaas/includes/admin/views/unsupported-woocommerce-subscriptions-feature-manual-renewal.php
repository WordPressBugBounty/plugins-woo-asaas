<?php
/**
 * Unsupported WooCommerce Subscriptions plugin feature: manual renewal.
 *
 * @package WooAsaas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS ) ?: '';
$tab  = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS ) ?: '';
?>

<div class="notice notice-error">
	<p>
		<span class="dashicons-before dashicons-dismiss components-dropdown"></span><strong><?php esc_html_e( 'The Asaas WooCommerce plugin does not support the manual renewal feature.', 'woo-asaas' ); ?></strong>
	<?php
	if ( 'wc-settings' === $page && 'subscriptions' === $tab ) {
		?>
		<?php esc_html_e( 'Please, review this setting.', 'woo-asaas' ); ?>
		<?php
	} else {
		$button_action = self_admin_url( 'admin.php?page=wc-settings&tab=subscriptions' );
		$button_label  = __( 'Click here to review WooCommerce Subscriptions settings', 'woo-asaas' );
		?>
		<a href="<?php echo esc_url( $button_action ); ?>" class="button button-primary"><?php echo esc_html( $button_label ); ?></a>
		<?php
	}
	?>
	</p>
</div>
