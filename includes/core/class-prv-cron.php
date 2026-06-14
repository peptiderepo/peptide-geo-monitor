<?php
/**
 * WP-Cron management for the weekly AI-visibility probe.
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * WP-Cron management for the AI-visibility probe.
 *
 * Registers the prv_weekly_probe hook, schedules/clears the event, and
 * dispatches to PRV_Probe_Runner on each tick.  Cadence changes clear the
 * existing schedule and reschedule at the new recurrence so next-run
 * timestamp reflects the actual cadence, not just the saved option.
 *
 * Who triggers: PRV_Plugin::init() (hook registration), PRV_Activator
 *               (schedule), PRV_Deactivator (clear), PRV_Settings_Page
 *               (reschedule on cadence change).
 * Dependencies: PRV_Probe_Runner, PRV_Run_Lock.
 *
 * @see class-prv-probe-runner.php  -- Runner invoked on each cron tick.
 * @see class-prv-activator.php     -- Calls schedule() on activation.
 * @see class-prv-settings-page.php -- Calls reschedule() on cadence change.
 * @package PrVision
 */
class PRV_Cron {

	/**
	 * Register WordPress action hooks.
	 *
	 * Side effects: Adds action for PRV_CRON_HOOK.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( PRV_CRON_HOOK, array( $this, 'handle_cron_tick' ) );
	}

	/**
	 * Callback fired by WP-Cron on the scheduled cadence.
	 *
	 * Respects the run-lock; if already running (e.g. "Run now" is active)
	 * the cron tick is skipped silently without double-spending.
	 *
	 * Side effects: Instantiates and runs PRV_Probe_Runner.
	 *
	 * @return void
	 */
	public function handle_cron_tick(): void {
		if ( PRV_Run_Lock::is_locked() ) {
			// Another run is in progress -- skip this tick.
			return;
		}
		$runner = new PRV_Probe_Runner();
		$runner->run();
	}

	/**
	 * Schedule the probe cron event using the configured cadence.
	 *
	 * Safe to call on re-activation -- no-op when already scheduled at the
	 * correct recurrence; clears and reschedules when recurrence differs.
	 *
	 * Side effects: Adds (or replaces) a WP-Cron event.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		$cadence = PRV_Config::get_cadence();
		$next    = wp_next_scheduled( PRV_CRON_HOOK );

		if ( false !== $next ) {
			// Check if the current recurrence matches.
			$events = _get_cron_array();
			foreach ( $events as $timestamp => $hooks ) {
				if ( isset( $hooks[ PRV_CRON_HOOK ] ) ) {
					foreach ( $hooks[ PRV_CRON_HOOK ] as $event ) {
						if ( isset( $event['schedule'] ) && $event['schedule'] === $cadence ) {
							return; // Already scheduled at the right cadence.
						}
					}
				}
			}
			// Wrong cadence -- clear and reschedule.
			self::clear_schedule();
		}

		wp_schedule_event( time(), $cadence, PRV_CRON_HOOK );
	}

	/**
	 * Reschedule the cron at a new cadence.
	 *
	 * Clears the existing event and creates a new one immediately.
	 * Called by PRV_Settings_Page when the cadence option changes.
	 *
	 * Side effects: Removes existing cron event; adds new one.
	 *
	 * @param string $cadence New WP-Cron recurrence identifier.
	 *
	 * @return void
	 */
	public static function reschedule( string $cadence ): void {
		self::clear_schedule();
		wp_schedule_event( time(), $cadence, PRV_CRON_HOOK );
	}

	/**
	 * Schedule using the legacy 'weekly' recurrence (activation compat).
	 *
	 * @return void
	 */
	public static function schedule_weekly(): void {
		self::schedule();
	}

	/**
	 * Remove the cron schedule.
	 *
	 * Called on deactivation. Does not purge data.
	 *
	 * Side effects: Removes the WP-Cron event.
	 *
	 * @return void
	 */
	public static function clear_schedule(): void {
		$timestamp = wp_next_scheduled( PRV_CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, PRV_CRON_HOOK );
		}
	}

	/**
	 * Check whether the probe event is currently scheduled.
	 *
	 * @return bool
	 */
	public static function is_scheduled(): bool {
		return false !== wp_next_scheduled( PRV_CRON_HOOK );
	}

	/**
	 * Return the timestamp of the next scheduled run.
	 *
	 * @return int|false Timestamp or false if not scheduled.
	 */
	public static function next_run_timestamp() {
		return wp_next_scheduled( PRV_CRON_HOOK );
	}
}
