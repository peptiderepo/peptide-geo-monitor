<?php
/**
 * Tests for PRV_Cost_Ledger: MTD cost retrieval, budget pre-check,
 * and the budget-cap abort behaviour.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Cost_Ledger
 */
class CostLedgerTest extends TestCase {

	protected function setUp(): void {
		prv_test_reset();
	}

	public function test_can_afford_below_cap(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['wpdb_var']                          = '2.50';

		$ledger = new PRV_Cost_Ledger();
		$this->assertTrue( $ledger->can_afford( 1.0 ), 'can_afford: 2.50 spent + 1.00 = 3.50 < 5.00 → true' );
	}

	public function test_can_afford_at_cap_returns_false(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['wpdb_var']                          = '5.00';

		$ledger = new PRV_Cost_Ledger();
		$this->assertFalse( $ledger->can_afford( 0.01 ), 'can_afford: 5.00 spent + 0.01 > 5.00 → false' );
	}

	public function test_can_afford_exactly_at_cap_is_allowed(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['wpdb_var']                          = '4.99';

		$ledger = new PRV_Cost_Ledger();
		$this->assertTrue( $ledger->can_afford( 0.01 ), 'can_afford: 4.99 + 0.01 = 5.00 exactly → true' );
	}

	public function test_get_remaining_budget_usd_correct_value(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['wpdb_var']                          = '3.25';

		$ledger = new PRV_Cost_Ledger();
		$this->assertSame( 1.75, round( $ledger->get_remaining_budget_usd(), 2 ), 'get_remaining_budget_usd: 5.00 - 3.25 = 1.75' );
	}

	public function test_get_remaining_budget_never_negative(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['wpdb_var']                          = '10.00';

		$ledger = new PRV_Cost_Ledger();
		$this->assertSame( 0.0, $ledger->get_remaining_budget_usd(), 'get_remaining_budget_usd: clamps to 0.0 when over cap' );
	}

	public function test_get_month_to_date_usd_null_returns_zero(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['wpdb_var']                          = null;

		$ledger = new PRV_Cost_Ledger();
		$this->assertSame( 0.0, $ledger->get_month_to_date_usd(), 'get_month_to_date_usd: null DB result → 0.0' );
	}

	public function test_update_row_cost_returns_true(): void {
		$ledger = new PRV_Cost_Ledger();
		$this->assertTrue( $ledger->update_row_cost( 42, 0.00123456 ), 'update_row_cost: returns true on success' );
	}

	public function test_budget_abort_can_afford_false_at_cap(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 0.001;
		$GLOBALS['prv_test_state']['wpdb_var']                          = '0.001';

		$ledger = new PRV_Cost_Ledger();
		$this->assertFalse( $ledger->can_afford( 0.000001 ), 'budget abort: can_afford returns false at cap → runner would stop' );
	}
}
