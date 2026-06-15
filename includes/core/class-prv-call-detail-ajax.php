<?php
/**
 * AJAX handler for the call-detail drawer content (v0.3.0).
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Handles the prv_call_detail AJAX action for the drawer content.
 *
 * Fetches metadata + I/O for a single call ID and returns the rendered
 * drawer HTML as JSON. Enforces nonce + capability before any DB read.
 *
 * Security: delegates rendering to PRV_Call_Drawer_Renderer which only
 * outputs allowlisted fields — never headers, keys, or raw HTTP bodies.
 *
 * Who triggers: wp_ajax_prv_call_detail hook (logged-in users only).
 * Dependencies: PRV_Call_Log_Query, PRV_Call_Drawer_Renderer.
 *
 * @see class-prv-call-drawer-renderer.php — HTML renderer for the drawer.
 * @see class-prv-call-log-query.php       — Fetches meta + io rows.
 * @see class-prv-plugin.php               — Registers the AJAX hook.
 * @package PrVision
 */
class PRV_Call_Detail_Ajax {

	/**
	 * Nonce action for the call-detail drawer.
	 */
	const NONCE_ACTION = 'prv_call_drawer';

	/**
	 * Register the AJAX hook.
	 *
	 * Side effects: Adds wp_ajax action.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_prv_call_detail', array( $this, 'handle' ) );
	}

	/**
	 * Handle the AJAX request and return drawer HTML.
	 *
	 * Side effects: Outputs JSON; terminates request.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'pr-vision' ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'pr-vision' ) );
		}

		$call_id = isset( $_POST['call_id'] ) ? absint( wp_unslash( $_POST['call_id'] ) ) : 0;
		if ( $call_id < 1 ) {
			wp_send_json_error( esc_html__( 'Invalid call ID.', 'pr-vision' ) );
		}

		$query = new PRV_Call_Log_Query();

		// Fetch from prv_call_meta.
		global $wpdb;
		$table = PRV_Call_Meta_Table::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$meta = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$call_id
			),
			ARRAY_A
		);

		if ( null === $meta || ! is_array( $meta ) ) {
			wp_send_json_error( esc_html__( 'Call not found.', 'pr-vision' ) );
		}

		$io = $query->get_call_io( $call_id );

		ob_start();
		$renderer = new PRV_Call_Drawer_Renderer();
		$renderer->render_content( $meta, $io );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
