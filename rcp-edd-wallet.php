<?php
/**
 * Plugin Name: Restrict Content Pro - Easy Digital Downloads Wallet
 * Description: Deposit funds into a customer's Easy Digital Downloads Wallet when they subscribe to a Restrict Content Pro membership.
 * Version: 1.0.1
 * Author: iThemes, LLC
 * Author URI: https://ithemes.com
 * Contributors: jthillithemes, layotte, ithemes
 * Text Domain: rcp-edd-wallet
 * iThemes Package: rcp-edd-wallet
 */

/**
 * Loads the plugin textdomain.
 */
function rcp_edd_wallet_textdomain() {
        load_plugin_textdomain( 'rcp-edd-wallet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'rcp_edd_wallet_textdomain' );

/**
 * Adds the plugin settings form fields to the subscription level form.
 */
function rcp_edd_wallet_level_fields( $level ) {

	if ( ! class_exists( 'EDD_Wallet' ) ) {
		return;
	}

	$funds = ( ! empty( $level ) ? get_option( 'rcp_subscription_edd_wallet_funds_' . $level->id, 0 ) : 0 );
?>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="rcp-edd-wallet-funds"><?php _e( 'EDD Wallet Deposit', 'rcp-edd-wallet' ); ?></label>
		</th>
		<td>
			<input type="number" min="0" step="1" id="rcp-edd-wallet-funds" name="rcp-edd-wallet-funds" value="<?php echo esc_attr( $funds ); ?>" style="width: 60px;"/>
			<p class="description"><?php _e( 'The amount to deposit into the member\'s EDD Wallet each subscription period.', 'rcp-edd-wallet' ); ?></p>
		</td>
	</tr>

<?php
}
add_action( 'rcp_add_subscription_form', 'rcp_edd_wallet_level_fields' );
add_action( 'rcp_edit_subscription_form', 'rcp_edd_wallet_level_fields' );


/**
 * Saves the subscription level funds settings.
 */
function rcp_edd_wallet_save_level_funds( $level_id = 0, $args = array() ) {

	if ( ! class_exists( 'EDD_Wallet' ) ) {
		return;
	}

	if ( empty( $_POST['rcp-edd-wallet-funds'] ) ) {
		return;
	}
	update_option( 'rcp_subscription_edd_wallet_funds_' . $level_id, absint( $_POST['rcp-edd-wallet-funds'] ) );
}
add_action( 'rcp_add_subscription', 'rcp_edd_wallet_save_level_funds', 10, 2 );
add_action( 'rcp_edit_subscription_level', 'rcp_edd_wallet_save_level_funds', 10, 2 );


/**
 * Adds Wallet funds when making a new payment.
 */
function rcp_edd_wallet_add_funds( $payment_id, $args = array(), $amount ) {

	if ( ! class_exists( 'EDD_Wallet' ) || ! function_exists( 'EDD' ) || empty( $args['user_id'] ) ) {
		return;
	}

	$sub_id = rcp_get_subscription_id( $args['user_id'] );

	if ( ! $sub_id ) {
		return;
	}

	$funds  = get_option( 'rcp_subscription_edd_wallet_funds_' . $sub_id, 0 );

	if ( $funds == 0 ) {
		return;
	}

	$user = get_user_by( 'id', $args['user_id'] );

	if ( ! $user ) {
		return;
	}

	$customer = EDD()->customers->get_customer_by( 'email', $user->user_email );

	if ( ! $customer ) {
		EDD()->customers->add( array(
			'user_id' => $user->ID,
			'email'   => $user->user_email,
			'name'    => ( ! empty( $user->display_name ) ? $user->display_name : $user->user_nicename )
		) );
	}

	edd_wallet()->wallet->deposit( $user->ID, $funds, 'deposit' );
}
add_action( 'rcp_insert_payment', 'rcp_edd_wallet_add_funds', 10, 3 );


/**
 * Removes Wallet funds when a subscription is refunded.
 */
function rcp_edd_wallet_remove_funds( $payment_id, $payment_data = array() ) {

	if ( ! class_exists( 'EDD_Wallet' ) || ! function_exists( 'EDD' ) ) {
		return;
	}

	if ( empty( $payment_data['status'] ) || 'refunded' !== $payment_data['status'] ) {
		return;
	}

	if ( empty( $payment_data['user_id'] ) || empty( $payment_data['transaction_id'] ) ) {
		return;
	}

	// See if the wallet funds have already been refunded
	$chk = get_user_meta( $payment_data['user_id'], 'rcp_edd_wallet_txid_' . strtolower( $payment_data['transaction_id'] ), true );
	if ( ! empty( $chk ) && 'refunded' === $chk ) {
		return;
	}

	$sub_id = rcp_get_subscription_id( $payment_data['user_id'] );

	if ( ! $sub_id ) {
		return;
	}

	$funds  = get_option( 'rcp_subscription_edd_wallet_funds_' . $sub_id, 0 );

	if ( $funds == 0 ) {
		return;
	}

	$user = get_user_by( 'id', $payment_data['user_id'] );

	if ( ! $user ) {
		return;
	}

	$balance = edd_wallet()->wallet->balance( $user->ID );

	if ( $funds > $balance ) {
		$funds = $balance;
	}

	edd_wallet()->wallet->withdraw( $user->ID, $funds, 'withdrawal' );

	update_user_meta( $user->ID, 'rcp_edd_wallet_txid_' . sanitize_key( $payment_data['transaction_id'] ), 'refunded' );

}
add_action( 'rcp_update_payment', 'rcp_edd_wallet_remove_funds', 10, 2 );

if ( ! function_exists( 'ithemes_rcp_edd_wallet_updater_register' ) ) {
	function ithemes_rcp_edd_wallet_updater_register( $updater ) {
		$updater->register( 'REPO', __FILE__ );
	}
	add_action( 'ithemes_updater_register', 'ithemes_rcp_edd_wallet_updater_register' );

	require( __DIR__ . '/lib/updater/load.php' );
}