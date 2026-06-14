<?php
/**
 * Tests for PRV_Probe_Runner: budget-cap abort, run-lock, model health.
 *
 * Uses queued mock HTTP responses (via $GLOBALS['prv_test_state']['remote_posts'])
 * and a mock ledger to verify runner behaviour without real API calls.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== PRV Probe Runner Tests ===\n";

// ── Define API key constants (required by providers) ──────────────────────

if ( ! defined( 'PRV_OPENROUTER_API_KEY' ) ) {
	define( 'PRV_OPENROUTER_API_KEY', 'sk-or-test-key' );
}
if ( ! defined( 'PRV_CF_ACCOUNT_ID' ) ) {
	define( 'PRV_CF_ACCOUNT_ID', '' );
}
if ( ! defined( 'PRV_CF_GATEWAY_ID' ) ) {
	define( 'PRV_CF_GATEWAY_ID', '' );
}

// ── Mock ledger: tracks afford calls, simulates exhaustion ────────────────

class PRV_Mock_Ledger extends PRV_Cost_Ledger {
	public int  $afford_count    = 0;
	public int  $no_afford_count = 0;
	public int  $exhaust_after   = PHP_INT_MAX;

	public function can_afford( float $estimated ): bool {
		$total = $this->afford_count + $this->no_afford_count;
		if ( $total >= $this->exhaust_after ) {
			$this->no_afford_count++;
			return false;
		}
		$this->afford_count++;
		return true;
	}

	public function update_row_cost( int $row_id, float $cost ): bool { return true; }
	public function get_month_to_date_usd(): float { return 0.0; }
}

// ── Shared helpers ────────────────────────────────────────────────────────

function setup_simple_run( array $model_rows, array $peptides = null, array $intents = null ): void {
	PRV_Model_Registry::run_migration_v2();
	PRV_Config_Version::maybe_seed_initial_version();
	$GLOBALS['prv_test_state']['options']['prv_models']         = $model_rows;
	$GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ] = PRV_Model_Registry::SCHEMA_VERSION;
	$GLOBALS['prv_test_state']['options']['prv_peptides']       = $peptides ?? [['slug'=>'bpc-157','label'=>'BPC-157']];
	$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = $intents  ?? ['what is {peptide}'];
}

$sonar_row = [ 'id' => 'mdl_t1', 'slug' => 'perplexity/sonar', 'provider' => 'perplexity', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ];

$cited_body = json_encode([
	'choices'   => [['message' => ['content' => 'BPC-157 info.']]],
	'citations' => ['https://peptiderepo.com/bpc-157/', 'https://examine.com'],
	'usage'     => ['total_tokens' => 100],
]);

// ── Test: basic run completes -- 1×1×1 ───────────────────────────────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
];

$ledger1 = new PRV_Mock_Ledger();
$runner1 = new PRV_Probe_Runner( $ledger1 );
$counts1 = $runner1->run();

prv_assert_equals( 1, $counts1['probed'],         'basic run: 1 probed' );
prv_assert_equals( 0, $counts1['skipped_budget'], 'basic run: 0 budget skips' );
prv_assert_equals( 0, $counts1['skipped_error'],  'basic run: 0 error skips' );
prv_assert_equals( 1, $ledger1->afford_count,     'basic run: 1 can_afford check' );
prv_assert( '' !== $counts1['run_id'],             'basic run: run_id is non-empty' );
prv_assert( false === $counts1['truncated'],       'basic run: not truncated' );

// ── Test: 2×1×1 run completes all probes when budget sufficient ───────────

prv_test_reset();
setup_simple_run(
	[$sonar_row],
	[['slug'=>'bpc-157','label'=>'BPC-157'],['slug'=>'tb-500','label'=>'TB-500']],
	['what is {peptide}']
);
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
	['response' => ['code' => 200], 'body' => $cited_body],
];

$ledger2 = new PRV_Mock_Ledger();
$runner2 = new PRV_Probe_Runner( $ledger2 );
$counts2 = $runner2->run();

prv_assert_equals( 2, $counts2['probed'],      '2×1×1 run: 2 probes completed' );
prv_assert_equals( 2, $ledger2->afford_count,  '2×1×1 run: 2 can_afford checks' );

// ── Test: budget cap abort after N probes ────────────────────────────────

prv_test_reset();
setup_simple_run(
	[$sonar_row],
	[['slug'=>'bpc-157','label'=>'BPC-157'],['slug'=>'tb-500','label'=>'TB-500'],['slug'=>'mk-677','label'=>'MK-677']],
	['what is {peptide}']
);
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
	['response' => ['code' => 200], 'body' => $cited_body],
	['response' => ['code' => 200], 'body' => $cited_body],
];

$exh = new PRV_Mock_Ledger();
$exh->exhaust_after = 1; // Budget exhausted after 1st check.

$runner3 = new PRV_Probe_Runner( $exh );
$counts3 = $runner3->run();

prv_assert_equals( 1, $counts3['probed'],        'budget abort: 1 probe completed before cap' );
prv_assert( $counts3['skipped_budget'] >= 2,     'budget abort: >=2 probes skipped (budget)' );
prv_assert_equals( 0, $counts3['skipped_error'], 'budget abort: 0 error skips' );
prv_assert( $counts3['truncated'],               'budget abort: truncated flag set' );

// ── Test: HTTP error counts as skipped_error ─────────────────────────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 500], 'body' => 'Error'],
	['response' => ['code' => 500], 'body' => 'Error'],
	['response' => ['code' => 500], 'body' => 'Error'],
];

$ledger4 = new PRV_Mock_Ledger();
$runner4 = new PRV_Probe_Runner( $ledger4 );
$counts4 = $runner4->run();

prv_assert_equals( 0, $counts4['probed'],        'HTTP 500: 0 probes' );
prv_assert( $counts4['skipped_error'] >= 1,      'HTTP 500: >=1 error skip recorded' );

// ── Test: WP_Error from wp_remote_post counts as skipped_error ──────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
$wp_error = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
$GLOBALS['prv_test_state']['remote_posts'] = [ $wp_error, $wp_error, $wp_error ];

$ledger5 = new PRV_Mock_Ledger();
$runner5 = new PRV_Probe_Runner( $ledger5 );
$counts5 = $runner5->run();

prv_assert_equals( 0, $counts5['probed'],   'WP_Error: 0 probes completed' );
prv_assert( $counts5['skipped_error'] >= 1, 'WP_Error: >=1 error skip recorded' );

// ── Test: run returns correct keys in counts array ───────────────────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
];

$ledger6 = new PRV_Mock_Ledger();
$runner6 = new PRV_Probe_Runner( $ledger6 );
$counts6 = $runner6->run();

prv_assert( array_key_exists( 'probed',         $counts6 ), 'run: returns probed key' );
prv_assert( array_key_exists( 'skipped_budget', $counts6 ), 'run: returns skipped_budget key' );
prv_assert( array_key_exists( 'skipped_error',  $counts6 ), 'run: returns skipped_error key' );
prv_assert( array_key_exists( 'truncated',      $counts6 ), 'run: returns truncated key' );
prv_assert( array_key_exists( 'run_id',         $counts6 ), 'run: returns run_id key' );

// ── Test: run-lock refused when already locked ────────────────────────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
PRV_Run_Lock::acquire();
$ledger7 = new PRV_Mock_Ledger();
$runner7 = new PRV_Probe_Runner( $ledger7 );
$counts7 = $runner7->run();
prv_assert_equals( 0, $counts7['probed'],    'run-lock: 0 probed when locked' );
prv_assert_equals( -1, $counts7['skipped_error'], 'run-lock: -1 sentinel when lock busy' );
prv_assert_equals( 0, $ledger7->afford_count, 'run-lock: no can_afford checks when locked' );
PRV_Run_Lock::release();

// ── Test: per-model health updated after run ──────────────────────────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
];

$runner8 = new PRV_Probe_Runner( new PRV_Mock_Ledger() );
$runner8->run();

$models8 = PRV_Model_Registry::get_all();
prv_assert( count( $models8 ) > 0, 'health update: at least 1 model' );
// The sonar model should have health_status = healthy (probed=1, errors=0).
foreach ( $models8 as $m ) {
	if ( 'perplexity/sonar' === $m['slug'] ) {
		prv_assert_equals( 'healthy', $m['health_status'], 'health update: sonar healthy after successful run' );
		prv_assert_equals( 1, $m['health_probed'], 'health update: health_probed = 1' );
		prv_assert_equals( 0, $m['health_errors'], 'health update: health_errors = 0' );
	}
}

// ── Test: lock is released after a successful run ─────────────────────────

prv_test_reset();
setup_simple_run( [$sonar_row] );
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
];
$runner9 = new PRV_Probe_Runner( new PRV_Mock_Ledger() );
$runner9->run();
prv_assert( false === PRV_Run_Lock::is_locked(), 'run-lock cleanup: lock released after successful run' );

exit( prv_test_summary() );
