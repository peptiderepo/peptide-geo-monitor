<?php
/**
 * Tests for PRV_Cron cadence reschedule on config change.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== PRV Cron Reschedule Tests ===\n";

// ── Test: schedule_weekly schedules with the configured cadence ───────────

prv_test_reset();
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
PRV_Cron::schedule_weekly();
prv_assert( PRV_Cron::is_scheduled(), 'schedule: event scheduled' );
$next = PRV_Cron::next_run_timestamp();
prv_assert( false !== $next, 'schedule: next_run_timestamp is not false' );
prv_assert( (int) $next > 0, 'schedule: next_run_timestamp is positive' );

// ── Test: reschedule clears old and adds new event ────────────────────────

prv_test_reset();
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
PRV_Cron::schedule();
prv_assert( PRV_Cron::is_scheduled(), 'reschedule pre: event is scheduled' );

PRV_Cron::reschedule( 'daily' );
prv_assert( PRV_Cron::is_scheduled(), 'reschedule post: event is still scheduled after reschedule' );

// Verify the cron array stores the new recurrence.
$events = _get_cron_array();
$found_daily = false;
foreach ( $events as $hooks ) {
	if ( isset( $hooks[ PRV_CRON_HOOK ] ) ) {
		foreach ( $hooks[ PRV_CRON_HOOK ] as $evt ) {
			if ( 'daily' === ( $evt['schedule'] ?? '' ) ) {
				$found_daily = true;
			}
		}
	}
}
prv_assert( $found_daily, 'reschedule: new cron has daily recurrence' );

// ── Test: clear_schedule removes the event ────────────────────────────────

prv_test_reset();
PRV_Cron::schedule();
prv_assert( PRV_Cron::is_scheduled(), 'clear pre: event is scheduled' );
PRV_Cron::clear_schedule();
prv_assert( false === PRV_Cron::is_scheduled(), 'clear: event removed after clear_schedule' );

// ── Test: schedule is idempotent (called twice) ───────────────────────────

prv_test_reset();
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
PRV_Cron::schedule();
$ts1 = PRV_Cron::next_run_timestamp();
PRV_Cron::schedule(); // Second call.
$ts2 = PRV_Cron::next_run_timestamp();
prv_assert( $ts1 === $ts2, 'schedule idempotent: same timestamp after two schedule() calls at same cadence' );

// ── Test: get_cadence default is weekly ──────────────────────────────────

prv_test_reset();
$cadence = PRV_Config::get_cadence();
prv_assert_equals( 'weekly', $cadence, 'get_cadence: defaults to weekly' );

// ── Test: invalid cadence falls back to weekly ────────────────────────────

prv_test_reset();
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'invalid_cadence';
$cadence = PRV_Config::get_cadence();
prv_assert_equals( 'weekly', $cadence, 'get_cadence: invalid value falls back to weekly' );

// ── Test: cron tick skipped when run-lock held ───────────────────────────

prv_test_reset();
PRV_Run_Lock::acquire();
$cron = new PRV_Cron();
// handle_cron_tick should return without running -- no exception.
$cron->handle_cron_tick();
prv_assert( PRV_Run_Lock::is_locked(), 'cron tick: lock still held (run was skipped)' );
PRV_Run_Lock::release();
prv_assert( false === PRV_Run_Lock::is_locked(), 'cron tick cleanup: lock released' );

exit( prv_test_summary() );
