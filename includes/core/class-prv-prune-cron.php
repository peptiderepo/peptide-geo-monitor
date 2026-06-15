<?php
/**
 * Daily prune cron: delete aged prv_call_io rows (v0.3.0).
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Manages the daily prune cron that deletes aged prv_call_io rows.
 *
 * Retention window is set in Settings (default 90 days). Only prv_call_io
 * rows are deleted — prv_call_meta (cost/metadata) is kept indefinitely.
 * The cron is idempotent: calling schedule() when already scheduled is safe.
 *
 * Cleared in the deactivator AND uninstall so no orphan cron events remain.
 *
 * Who triggers: PRV_Activator (schedule), PRV_Deactivator (clear),
 *               uninstall.php (clear), self::handle_prune_tick() (prune).
 * Dependencies: $wpdb, PRV_Call_Io_Table, PRV_Config.
 *
 * @see class-prv-call-io-table.php — Table from which aged rows are deleted.
 * @see class-prv-config.php        — get_io_retention_days() for the window.
 * @see class-prv-activator.php     — Calls schedule() on activation.
 * @see class-prv-deactivator.php   — Calls clear_schedule() on deactivation.
 * @see uninstall.php               — Calls clear_schedule() on uninstall.
 * @package PrVision
 */
class PRV_Prune_Cron {

	/**
	 * WP-Cron hook name for the daily prune event.
	 */
	const PRUNE_HOOK = 'prv_daily_prune';

	/**
	 * Register the cron action hook.
	 *
	 * Side effects: Adds action for PRV_PRUNE_HOOK.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::PRUNE_HOOK, array( $this, 'handle_prune_tick' ) );
	}

	/**
	 * Callback fired by WP-Cron daily.
	 *
	 * Deletes prv_call_io rows older than the configured retention window.
	 * Safe to re-run — idempotent on already-pruned data.
	 *
	 * Side effects: Database delete on prv_call_io.
	 *
	 * @return int Number of rows deleted.
	 */
	public function handle_prune_tick(): int {
		return self::prune_now();
	}

	/**
	 * Perform the prune immediately (used by handle_prune_tick + tests).
	 *
	 * Deletes only prv_call_io rows; prv_call_meta is never touched.
	 *
	 * Side effects: Database delete on prv_call_io.
	 *
	 * @return int Number of rows deleted (0 when nothing to prune).
	 */
	public static function prune_now(): int {
		global $wpdb;

		$days  = PRV_Config::get_io_retention_days();
		$table = PRV_Call_Io_Table::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE captured_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);

		return (int) $deleted;
	}

	/**
	 * Schedule the daily prune event.
	 *
	 * Safe to call on re-activation — no-op when already scheduled.
	 * Side effects: Adds a WP-Cron daily event.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Remove the prune cron schedule.
	 *
	 * Called on deactivation and uninstall.
	 * Side effects: Removes the WP-Cron event.
	 *
	 * @return void
	 */
	public static function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::PRUNE_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::PRUNE_HOOK );
		}
		wp_clear_scheduled_hook( self::PRUNE_HOOK );
	}
}
