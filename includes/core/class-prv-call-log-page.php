<?php
/**
 * PR Vision Call Log admin sub-page (v0.3.0).
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Call Log sub-page: paginated call list + per-call detail drawer.
 *
 * Registers "PR Vision > Call Log" sub-menu. Reads filter/page params from
 * the URL, queries PRV_Call_Log_Query, and delegates rendering to
 * PRV_Call_Log_Table_Renderer and PRV_Call_Drawer_Renderer.
 *
 * Who triggers: PRV_Plugin::init() — is_admin() guard.
 * Dependencies: PRV_Call_Log_Query, PRV_Call_Log_Table_Renderer,
 *               PRV_Call_Drawer_Renderer, PRV_Config.
 *
 * @see class-prv-call-log-table-renderer.php — Table + drawer rendering.
 * @see class-prv-call-drawer-renderer.php    — Detail drawer rendering.
 * @see class-prv-call-log-query.php          — Query layer.
 * @package PrVision
 */
class PRV_Call_Log_Page {

	/**
	 * Admin menu slug.
	 */
	const MENU_SLUG = 'pr-vision-calls';

	/**
	 * Register WordPress admin hooks.
	 *
	 * Side effects: Adds admin_menu action.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
	}

	/**
	 * Register the Call Log sub-menu under PR Vision.
	 *
	 * Side effects: Adds a WP admin sub-menu entry.
	 *
	 * @return void
	 */
	public function add_submenu(): void {
		add_submenu_page(
			PRV_Admin_Page::MENU_SLUG,
			__( 'PR Vision — Call Log', 'pr-vision' ),
			__( 'Call Log', 'pr-vision' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the full Call Log page.
	 *
	 * Side effects: Outputs HTML.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'pr-vision' ) );
		}

		// Read filter params (no nonce: read-only display only).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filters = array(
			'model'     => isset( $_GET['prv_filter_model'] ) ? sanitize_text_field( wp_unslash( $_GET['prv_filter_model'] ) ) : '',
			'peptide'   => isset( $_GET['prv_filter_peptide'] ) ? sanitize_text_field( wp_unslash( $_GET['prv_filter_peptide'] ) ) : '',
			'date_from' => isset( $_GET['prv_filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['prv_filter_from'] ) ) : '',
			'date_to'   => isset( $_GET['prv_filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['prv_filter_to'] ) ) : '',
			'status'    => isset( $_GET['prv_filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['prv_filter_status'] ) ) : '',
		);
		$page    = isset( $_GET['prv_page'] ) ? max( 1, absint( wp_unslash( $_GET['prv_page'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$query  = new PRV_Call_Log_Query();
		$result = $query->get_page( $filters, $page );

		$renderer = new PRV_Call_Log_Table_Renderer();
		$renderer->render(
			array(
				'rows'    => $result['rows'],
				'total'   => $result['total'],
				'pages'   => $result['pages'],
				'page'    => $page,
				'filters' => $filters,
			)
		);
	}
}
