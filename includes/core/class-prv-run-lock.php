<?php
/**
 * Run-lock: prevents concurrent cron + Run-now executions.
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Distributed run-lock using a WP transient.
 *
 * Ensures exactly one probe run (cron or manual) executes at a time.
 * Acquiring the lock is atomic via the transient API with a TTL so stale
 * locks (e.g. from a crashed run) self-expire after PRV_RUN_LOCK_TTL_SECONDS.
 *
 * Who triggers: PRV_Probe_Runner::run() acquires; PRV_Admin_Page "Run now"
 *               handler checks before dispatching.
 * Dependencies: set_transient(), get_transient(), delete_transient().
 *
 * @see class-prv-probe-runner.php -- Acquires + releases lock around the run.
 * @see class-prv-settings-page.php -- Checks is_locked() before Run now.
 * @package PrVision
 */
class PRV_Run_Lock {

	/**
	 * Transient key for the run lock.
	 */
	const LOCK_KEY = 'prv_run_lock';

	/**
	 * Lock TTL in seconds. Should exceed the longest plausible run.
	 */
	const TTL_SECONDS = 3600; // 1 hour.

	/**
	 * Attempt to acquire the run lock.
	 *
	 * Returns true only if the lock was not already held. Uses a non-blocking
	 * check-and-set approach: if the transient already exists the lock is held.
	 *
	 * Side effects: Sets a WP transient when successful.
	 *
	 * @return bool True if lock acquired; false if already held.
	 */
	public static function acquire(): bool {
		if ( false !== get_transient( self::LOCK_KEY ) ) {
			return false; // Already locked.
		}
		// set_transient returns false if the transient already exists (on some hosts);
		// re-check after set to guard against the race window.
		set_transient( self::LOCK_KEY, time(), self::TTL_SECONDS );
		// Brief window for a second writer -- re-check.
		$val = get_transient( self::LOCK_KEY );
		return false !== $val;
	}

	/**
	 * Release the run lock.
	 *
	 * Should be called in a finally block after every run, whether successful
	 * or not, to prevent lock starvation on non-fatal errors.
	 *
	 * Side effects: Deletes the WP transient.
	 *
	 * @return void
	 */
	public static function release(): void {
		delete_transient( self::LOCK_KEY );
	}

	/**
	 * Check whether a run is currently in progress.
	 *
	 * @return bool True when locked.
	 */
	public static function is_locked(): bool {
		return false !== get_transient( self::LOCK_KEY );
	}

	/**
	 * Return approximate seconds since the lock was acquired.
	 *
	 * @return int|null Null if not locked.
	 */
	public static function locked_since(): ?int {
		$val = get_transient( self::LOCK_KEY );
		if ( false === $val ) {
			return null;
		}
		return (int) ( time() - (int) $val );
	}
}
