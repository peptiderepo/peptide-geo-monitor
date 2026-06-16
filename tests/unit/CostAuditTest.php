<?php
/**
 * Tests: cost audit trail — rollup reconciliation, prune, and
 * probe-runner best-effort capture.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Mock capture writer that throws in write_io. */
class PRV_Capture_Writer_Throwing extends PRV_Capture_Writer {
	/**
	 * @inheritDoc
	 */
	public function write_io( int $call_id, string $prompt_text, string $response_text ): bool {
		throw new \RuntimeException( 'Simulated capture_io failure' );
	}
}

/**
 * @covers PRV_Cost_Rollup_Query
 * @covers PRV_Cost_Ledger
 * @covers PRV_Prune_Cron
 * @covers PRV_Table_Manager
 */
class CostAuditTest extends TestCase {

	protected function setUp(): void {
		prv_test_reset();
	}

	public function test_get_mtd_summary_returns_positive_cost(): void {
		$GLOBALS['prv_test_state']['wpdb_row'] = array( 'total_cost' => '1.23456789', 'total_calls' => '5' );
		$GLOBALS['prv_test_state']['wpdb_var'] = '1.23456789';

		$rollup  = new PRV_Cost_Rollup_Query();
		$summary = $rollup->get_mtd_summary();

		$this->assertGreaterThan( 0, $summary['total_cost'], 'get_mtd_summary returns positive cost when rows present' );
	}

	public function test_ledger_mtd_matches_mock(): void {
		$GLOBALS['prv_test_state']['wpdb_var'] = '1.23456789';

		$ledger         = new PRV_Cost_Ledger();
		$mtd_via_ledger = $ledger->get_month_to_date_usd();

		$this->assertSame( (float) '1.23456789', $mtd_via_ledger, 'PRV_Cost_Ledger MTD matches mock value' );
	}

	public function test_project_month_end_returns_at_least_mtd(): void {
		$GLOBALS['prv_test_state']['wpdb_row'] = array( 'total_cost' => '1.23456789', 'total_calls' => '5' );

		$rollup    = new PRV_Cost_Rollup_Query();
		$projected = $rollup->project_month_end( 1.23 );

		$this->assertGreaterThanOrEqual( 1.23, $projected, 'project_month_end returns at least MTD value' );
		$this->assertIsFloat( $projected, 'project_month_end returns a float' );
	}

	public function test_prune_now_returns_int_and_does_not_drop_call_meta(): void {
		$deleted = PRV_Prune_Cron::prune_now();

		$this->assertIsInt( $deleted, 'prune_now returns an integer' );
		$this->assertNotContains(
			'prv_call_meta',
			$GLOBALS['prv_test_state']['wpdb_dropped_tables'],
			'prune_now does not drop prv_call_meta'
		);
	}

	public function test_capture_io_exception_does_not_propagate_to_runner(): void {
		$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
		$GLOBALS['prv_test_state']['options']['prv_peptides']           = array( array( 'slug' => 'bpc-157', 'label' => 'BPC-157' ) );
		$GLOBALS['prv_test_state']['options']['prv_prompt_intents']     = array( 'what is {peptide}' );
		$GLOBALS['prv_test_state']['options']['prv_models_v2']          = array(
			array( 'slug' => 'perplexity/sonar', 'enabled' => true ),
		);
		$GLOBALS['prv_test_state']['options']['prv_active_config_version'] = 3;
		$GLOBALS['prv_test_state']['wpdb_var']                             = '0.0';

		$throwing_capture  = new PRV_Capture_Writer_Throwing();
		$ledger            = new PRV_Cost_Ledger();
		$runner            = new PRV_Probe_Runner( $ledger, $throwing_capture );
		$exception_escaped = false;
		$counts            = null;

		try {
			$counts = $runner->run();
		} catch ( \Throwable $e ) {
			$exception_escaped = true;
		}

		$this->assertFalse( $exception_escaped, 'forced capture_io exception does not propagate to probe runner caller' );
		$this->assertIsArray( $counts, 'runner returns counts array even when capture_io throws' );
	}

	public function test_drop_table_methods_work_correctly(): void {
		PRV_Table_Manager::drop_table();
		PRV_Call_Io_Table::drop_table();
		PRV_Call_Meta_Table::drop_table();

		$dropped = $GLOBALS['prv_test_state']['wpdb_dropped_tables'];
		$this->assertContains( 'prv_call_io', $dropped, 'drop_table() drops prv_call_io' );
		$this->assertContains( 'prv_call_meta', $dropped, 'drop_table() drops prv_call_meta' );
		$this->assertContains( 'prv_ai_visibility', $dropped, 'drop_table() drops prv_ai_visibility (existing table)' );
	}
}
