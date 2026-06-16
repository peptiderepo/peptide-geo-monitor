<?php
/**
 * Tests for PRV_Cron_Guard: self-healing cron scheduling.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Cron_Guard
 */
class CronGuardTest extends TestCase {

	protected function setUp(): void {
		prv_test_reset();
	}

	public function test_ensure_scheduled_schedules_weekly_hook(): void {
		$this->assertFalse( wp_next_scheduled( PRV_CRON_HOOK ), 'guard/weekly: not scheduled before call' );

		$guard = new PRV_Cron_Guard();
		$guard->ensure_scheduled();

		$this->assertNotFalse( wp_next_scheduled( PRV_CRON_HOOK ), 'guard/weekly: prv_weekly_probe scheduled after ensure_scheduled()' );
	}

	public function test_ensure_scheduled_schedules_prune_hook(): void {
		$this->assertFalse( wp_next_scheduled( PRV_Prune_Cron::PRUNE_HOOK ), 'guard/prune: not scheduled before call' );

		$guard = new PRV_Cron_Guard();
		$guard->ensure_scheduled();

		$this->assertNotFalse( wp_next_scheduled( PRV_Prune_Cron::PRUNE_HOOK ), 'guard/prune: prv_daily_prune scheduled after ensure_scheduled()' );
	}

	public function test_ensure_scheduled_is_idempotent(): void {
		$guard = new PRV_Cron_Guard();
		$guard->ensure_scheduled();

		$ts_weekly_1 = wp_next_scheduled( PRV_CRON_HOOK );
		$ts_prune_1  = wp_next_scheduled( PRV_Prune_Cron::PRUNE_HOOK );

		$guard->ensure_scheduled(); // Second call.

		$ts_weekly_2 = wp_next_scheduled( PRV_CRON_HOOK );
		$ts_prune_2  = wp_next_scheduled( PRV_Prune_Cron::PRUNE_HOOK );

		$this->assertSame( $ts_weekly_1, $ts_weekly_2, 'guard/idempotent: weekly event timestamp unchanged after second call' );
		$this->assertSame( $ts_prune_1, $ts_prune_2, 'guard/idempotent: prune event timestamp unchanged after second call' );

		$cron_events = $GLOBALS['prv_test_state']['cron_events'];
		$this->assertArrayHasKey( PRV_CRON_HOOK, $cron_events, 'guard/idempotent: exactly one weekly event entry' );
		$this->assertArrayHasKey( PRV_Prune_Cron::PRUNE_HOOK, $cron_events, 'guard/idempotent: exactly one prune event entry' );
		$this->assertCount( 2, $cron_events, 'guard/idempotent: only two cron event entries total' );
	}

	public function test_guard_uses_same_recurrence_as_canonical_schedulers(): void {
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
		PRV_Cron::schedule();
		$canonical_weekly = $GLOBALS['prv_test_state']['cron_events'][ PRV_CRON_HOOK ]['schedule'] ?? null;

		prv_test_reset();
		PRV_Prune_Cron::schedule();
		$canonical_prune = $GLOBALS['prv_test_state']['cron_events'][ PRV_Prune_Cron::PRUNE_HOOK ]['schedule'] ?? null;

		prv_test_reset();
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
		$guard2 = new PRV_Cron_Guard();
		$guard2->ensure_scheduled();

		$guard_weekly = $GLOBALS['prv_test_state']['cron_events'][ PRV_CRON_HOOK ]['schedule'] ?? null;
		$guard_prune  = $GLOBALS['prv_test_state']['cron_events'][ PRV_Prune_Cron::PRUNE_HOOK ]['schedule'] ?? null;

		$this->assertSame( $canonical_weekly, $guard_weekly, 'guard/recurrence: weekly matches canonical PRV_Cron::schedule()' );
		$this->assertSame( $canonical_prune, $guard_prune, 'guard/recurrence: prune matches canonical PRV_Prune_Cron::schedule()' );
	}

	public function test_ensure_scheduled_noop_when_both_already_scheduled(): void {
		$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';
		PRV_Cron::schedule();
		PRV_Prune_Cron::schedule();
		$before = $GLOBALS['prv_test_state']['cron_events'];

		$guard3 = new PRV_Cron_Guard();
		$guard3->ensure_scheduled();

		$after = $GLOBALS['prv_test_state']['cron_events'];
		$this->assertSame( $before, $after, 'guard/noop: cron_events unchanged when all events already scheduled' );
	}

	public function test_register_hooks_adds_init_action(): void {
		$guard4 = new PRV_Cron_Guard();
		$guard4->register_hooks();

		$hooks = array_column( $GLOBALS['prv_test_state']['actions'], 'hook' );
		$this->assertContains( 'init', $hooks, 'guard/register_hooks: init action registered' );
	}
}
