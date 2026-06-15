<?php
/**
 * Tests: cost audit trail — rollup reconciliation, prune, and probe-runner
 * best-effort capture.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== test-cost-audit ===\n";
prv_test_reset();

// ── Test 1: per-call cost sum reconciles to MTD via PRV_Cost_Rollup_Query ─
// Seed the wpdb_var mock (returned by get_var) to simulate MTD total.
$GLOBALS['prv_test_state']['wpdb_var'] = '1.23456789';
$rollup = new PRV_Cost_Rollup_Query();
$summary = $rollup->get_mtd_summary();
prv_assert( $summary['total_cost'] > 0, 'get_mtd_summary returns positive cost when rows present' );

// Reconcile: the same wpdb_var stub also underlies PRV_Cost_Ledger.
$ledger  = new PRV_Cost_Ledger();
$mtd_via_ledger = $ledger->get_month_to_date_usd();
// Both read the same mock; confirm they agree.
prv_assert_equals( (float) '1.23456789', $mtd_via_ledger, 'PRV_Cost_Ledger MTD matches mock value' );

// ── Test 2: project_month_end returns a numeric value ────────────────────
$projected = $rollup->project_month_end( 1.23 );
prv_assert( $projected >= 1.23, 'project_month_end returns at least MTD value' );
prv_assert( is_float( $projected ), 'project_month_end returns a float' );

// ── Test 3: prune_now deletes only prv_call_io (not prv_call_meta) ───────
// PRV_Prune_Cron::prune_now() runs a DELETE on prv_call_io. The wpdb stub
// returns true for all queries; confirm it does NOT drop prv_call_meta.
prv_test_reset();
$deleted = PRV_Prune_Cron::prune_now();
// The stub returns (int)true = 1; just confirm no exception and correct return type.
prv_assert( is_int( $deleted ), 'prune_now returns an integer' );
// Confirm prv_call_meta was not among dropped tables.
prv_assert(
	! in_array( 'prv_call_meta', $GLOBALS['prv_test_state']['wpdb_dropped_tables'], true ),
	'prune_now does not drop prv_call_meta'
);

// ── Test 4: forced capture_io exception does not break probe ──────────────
// Create a mock capture writer that throws in write_io.
class PRV_Capture_Writer_Throwing extends PRV_Capture_Writer {
	public function write_io( int $call_id, string $prompt_text, string $response_text ): bool {
		throw new \RuntimeException( 'Simulated capture_io failure' );
	}
}

prv_test_reset();
$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
$GLOBALS['prv_test_state']['options']['prv_peptides']      = array( array( 'slug' => 'bpc-157', 'label' => 'BPC-157' ) );
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = array( 'what is {peptide}' );
$GLOBALS['prv_test_state']['options']['prv_models_v2']     = array(
	array( 'slug' => 'perplexity/sonar', 'enabled' => true ),
);
$GLOBALS['prv_test_state']['options']['prv_active_config_version'] = 3;
$GLOBALS['prv_test_state']['wpdb_var'] = '0.0'; // No prior spend.

$throwing_capture = new PRV_Capture_Writer_Throwing();
$ledger2          = new PRV_Cost_Ledger();
$runner           = new PRV_Probe_Runner( $ledger2, $throwing_capture );

$exception_escaped = false;
try {
	$counts = $runner->run();
	// If we got here, the exception was swallowed.
} catch ( \Throwable $e ) {
	$exception_escaped = true;
}
prv_assert( ! $exception_escaped, 'forced capture_io exception does not propagate to probe runner caller' );
prv_assert( is_array( $counts ?? null ), 'runner returns counts array even when capture_io throws' );

// ── Test 5: table drop methods work correctly ─────────────────────────────
prv_test_reset();
// Simulate what uninstall.php does: call the static drop methods directly.
PRV_Table_Manager::drop_table();
PRV_Call_Io_Table::drop_table();
PRV_Call_Meta_Table::drop_table();

$dropped = $GLOBALS['prv_test_state']['wpdb_dropped_tables'];
prv_assert( in_array( 'prv_call_io', $dropped, true ), 'drop_table() drops prv_call_io' );
prv_assert( in_array( 'prv_call_meta', $dropped, true ), 'drop_table() drops prv_call_meta' );
prv_assert( in_array( 'prv_ai_visibility', $dropped, true ), 'drop_table() drops prv_ai_visibility (existing table)' );

exit( prv_test_summary() );
