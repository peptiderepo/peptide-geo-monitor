<?php
/**
 * Tests for PRV_Cron: scheduling, clearing, and registration.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Cron
 * @covers PRV_Activator
 * @covers PRV_Deactivator
 */
class CronTest extends TestCase {

	protected function setUp(): void {
		prv_test_reset();
	}

	public function test_schedule_weekly_adds_event(): void {
		$this->assertFalse( PRV_Cron::is_scheduled() );
		PRV_Cron::schedule_weekly();
		$this->assertTrue( PRV_Cron::is_scheduled() );
	}

	public function test_schedule_weekly_is_idempotent(): void {
		PRV_Cron::schedule_weekly();
		PRV_Cron::schedule_weekly();
		$events = $GLOBALS['prv_test_state']['cron_events'];
		$this->assertCount( 1, $events );
	}

	public function test_clear_schedule_removes_event(): void {
		PRV_Cron::schedule_weekly();
		PRV_Cron::clear_schedule();
		$this->assertFalse( PRV_Cron::is_scheduled() );
	}

	public function test_clear_schedule_safe_when_nothing_scheduled(): void {
		PRV_Cron::clear_schedule();
		$this->assertFalse( PRV_Cron::is_scheduled() );
	}

	public function test_register_hooks_registers_cron_hook_action(): void {
		$cron = new PRV_Cron();
		$cron->register_hooks();
		$hooks = array_column( $GLOBALS['prv_test_state']['actions'], 'hook' );
		$this->assertContains( PRV_CRON_HOOK, $hooks );
	}

	public function test_prv_cron_hook_constant_value(): void {
		$this->assertSame( 'prv_weekly_probe', PRV_CRON_HOOK );
	}

	public function test_activator_schedules_cron(): void {
		PRV_Activator::activate();
		$this->assertTrue( PRV_Cron::is_scheduled() );
	}

	public function test_deactivator_clears_cron(): void {
		PRV_Activator::activate();
		PRV_Deactivator::deactivate();
		$this->assertFalse( PRV_Cron::is_scheduled() );
	}
}
