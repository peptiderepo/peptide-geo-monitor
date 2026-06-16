<?php
/**
 * Tests for PRV_Cron cadence reschedule on config change.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Cron
 * @covers PRV_Config
 */
class CronRescheduleTest extends TestCase {

	protected function setUp(): void {
		prv_test_reset();
	}

	public function test_schedule_weekly_with_configured_cadence(): void {
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
		PRV_Cron::schedule_weekly();

		$this->assertTrue( PRV_Cron::is_scheduled(), 'schedule: event scheduled' );
		$next = PRV_Cron::next_run_timestamp();
		$this->assertNotFalse( $next, 'schedule: next_run_timestamp is not false' );
		$this->assertGreaterThan( 0, (int) $next, 'schedule: next_run_timestamp is positive' );
	}

	public function test_reschedule_clears_old_and_adds_new(): void {
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
		PRV_Cron::schedule();
		$this->assertTrue( PRV_Cron::is_scheduled(), 'reschedule pre: event is scheduled' );

		PRV_Cron::reschedule( 'daily' );
		$this->assertTrue( PRV_Cron::is_scheduled(), 'reschedule post: event is still scheduled after reschedule' );

		$events       = _get_cron_array();
		$found_daily  = false;
		foreach ( $events as $hooks ) {
			if ( isset( $hooks[ PRV_CRON_HOOK ] ) ) {
				foreach ( $hooks[ PRV_CRON_HOOK ] as $evt ) {
					if ( 'daily' === ( $evt['schedule'] ?? '' ) ) {
						$found_daily = true;
					}
				}
			}
		}
		$this->assertTrue( $found_daily, 'reschedule: new cron has daily recurrence' );
	}

	public function test_clear_schedule_removes_event(): void {
		PRV_Cron::schedule();
		$this->assertTrue( PRV_Cron::is_scheduled(), 'clear pre: event is scheduled' );

		PRV_Cron::clear_schedule();
		$this->assertFalse( PRV_Cron::is_scheduled(), 'clear: event removed after clear_schedule' );
	}

	public function test_schedule_is_idempotent(): void {
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
		PRV_Cron::schedule();
		$ts1 = PRV_Cron::next_run_timestamp();
		PRV_Cron::schedule();
		$ts2 = PRV_Cron::next_run_timestamp();

		$this->assertSame( $ts1, $ts2, 'schedule idempotent: same timestamp after two schedule() calls at same cadence' );
	}

	public function test_get_cadence_defaults_to_weekly(): void {
		$cadence = PRV_Config::get_cadence();
		$this->assertSame( 'weekly', $cadence, 'get_cadence: defaults to weekly' );
	}

	public function test_invalid_cadence_falls_back_to_weekly(): void {
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'invalid_cadence';
		$cadence = PRV_Config::get_cadence();
		$this->assertSame( 'weekly', $cadence, 'get_cadence: invalid value falls back to weekly' );
	}

	public function test_cron_tick_skipped_when_run_lock_held(): void {
		PRV_Run_Lock::acquire();
		$cron = new PRV_Cron();
		$cron->handle_cron_tick();

		$this->assertTrue( PRV_Run_Lock::is_locked(), 'cron tick: lock still held (run was skipped)' );
		PRV_Run_Lock::release();
		$this->assertFalse( PRV_Run_Lock::is_locked(), 'cron tick cleanup: lock released' );
	}
}
