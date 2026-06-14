<?php
/**
 * Tests for projected cost calculation and budget cap logic.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== PRV Projected Cost Tests ===\n";

if ( ! defined( 'PRV_OPENROUTER_API_KEY' ) ) {
	define( 'PRV_OPENROUTER_API_KEY', 'sk-or-test-key' );
}
if ( ! defined( 'PRV_CF_ACCOUNT_ID' ) ) {
	define( 'PRV_CF_ACCOUNT_ID', '' );
}
if ( ! defined( 'PRV_CF_GATEWAY_ID' ) ) {
	define( 'PRV_CF_GATEWAY_ID', '' );
}

// ── Test: projected cost reflects enabled models × peptides × intents ─────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();

// Set up a known config: 2 enabled models, 2 peptides, 2 intents.
$GLOBALS['prv_test_state']['options']['prv_models'] = [
	[ 'id' => 'mdl_a1', 'slug' => 'perplexity/sonar', 'provider' => 'perplexity', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
	[ 'id' => 'mdl_a2', 'slug' => 'openai/gpt-4o-search-preview', 'provider' => 'openrouter', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
];
$GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ] = PRV_Model_Registry::SCHEMA_VERSION;
$GLOBALS['prv_test_state']['options']['prv_peptides']       = [ [ 'slug' => 'bpc-157', 'label' => 'BPC-157' ], [ 'slug' => 'tb-500', 'label' => 'TB-500' ] ];
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = [ 'what is {peptide}', '{peptide} dosage' ];
$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';

$cost = PRV_Config::get_projected_cost();

prv_assert_equals( 8, $cost['probe_count'], 'projected cost: 2 models × 2 peptides × 2 intents = 8 probes' );
prv_assert( $cost['per_run_usd'] > 0.0, 'projected cost: per_run_usd > 0' );
prv_assert( $cost['per_month_usd'] > 0.0, 'projected cost: per_month_usd > 0' );
prv_assert( $cost['per_month_usd'] >= $cost['per_run_usd'], 'projected cost: monthly >= per-run' );
prv_assert( false === $cost['over_cap'], 'projected cost: 8-probe config not over $5 cap' );

// ── Test: over_cap flag fires when projected monthly > cap ────────────────

prv_test_reset();
// 1000 peptides × 3 intents × 3 models at $0.005/probe = $45/run (way over $5).
$big_peptides = [];
for ( $i = 0; $i < 100; $i++ ) {
	$big_peptides[] = [ 'slug' => "pep-{$i}", 'label' => "Peptide {$i}" ];
}
$GLOBALS['prv_test_state']['options']['prv_models'] = [
	[ 'id' => 'mdl_b1', 'slug' => 'perplexity/sonar', 'provider' => 'perplexity', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
	[ 'id' => 'mdl_b2', 'slug' => 'openai/gpt-4o-search-preview', 'provider' => 'openrouter', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
	[ 'id' => 'mdl_b3', 'slug' => 'google/gemini-2.0-flash-001', 'provider' => 'openrouter', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
];
$GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ] = PRV_Model_Registry::SCHEMA_VERSION;
$GLOBALS['prv_test_state']['options']['prv_peptides']       = $big_peptides;
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = [ 'what is {peptide}', '{peptide} dosage', '{peptide} guide' ];
$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';

$cost_big = PRV_Config::get_projected_cost();
prv_assert( $cost_big['over_cap'], 'projected cost: over_cap=true for large config over $5 cap' );

// ── Test: disabled models excluded from probe_count ───────────────────────

prv_test_reset();
$GLOBALS['prv_test_state']['options']['prv_models'] = [
	[ 'id' => 'mdl_c1', 'slug' => 'perplexity/sonar', 'provider' => 'perplexity', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
	[ 'id' => 'mdl_c2', 'slug' => 'openai/gpt-4o-search-preview', 'provider' => 'openrouter', 'enabled' => false, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ],
];
$GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ] = PRV_Model_Registry::SCHEMA_VERSION;
$GLOBALS['prv_test_state']['options']['prv_peptides']       = [ [ 'slug' => 'bpc-157', 'label' => 'BPC-157' ] ];
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = [ 'what is {peptide}' ];
$GLOBALS['prv_test_state']['options']['prv_monthly_budget_usd'] = 5.0;
$GLOBALS['prv_test_state']['options'][ PRV_Config::CADENCE_KEY ] = 'weekly';

$cost_disabled = PRV_Config::get_projected_cost();
// 1 enabled model × 1 peptide × 1 intent = 1 probe.
prv_assert_equals( 1, $cost_disabled['probe_count'], 'projected cost: disabled models excluded from probe_count' );

// ── Test: truncation flag set when budget hit mid-run ────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
PRV_Config_Version::maybe_seed_initial_version();

$GLOBALS['prv_test_state']['options']['prv_peptides'] = [
	[ 'slug' => 'bpc-157', 'label' => 'BPC-157' ],
	[ 'slug' => 'tb-500',  'label' => 'TB-500' ],
	[ 'slug' => 'mk-677',  'label' => 'MK-677' ],
];
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = [ 'what is {peptide}' ];

class PRV_Exhausting_Ledger_Cost extends PRV_Cost_Ledger {
	private int $count = 0;
	public function can_afford( float $e ): bool { return $this->count++ < 1; }
	public function update_row_cost( int $id, float $c ): bool { return true; }
	public function get_month_to_date_usd(): float { return 4.99; }
}

$cited_body = json_encode([
	'choices'   => [['message' => ['content' => 'test']]],
	'citations' => ['https://peptiderepo.com/bpc-157/'],
	'usage'     => ['total_tokens' => 50],
]);
// Queue enough responses.
$GLOBALS['prv_test_state']['remote_posts'] = array_fill( 0, 3, ['response' => ['code' => 200], 'body' => $cited_body] );

$runner = new PRV_Probe_Runner( new PRV_Exhausting_Ledger_Cost() );
$counts = $runner->run();

prv_assert( $counts['truncated'], 'truncation flag: set when budget hit mid-run' );
prv_assert_equals( 1, (int) get_option( 'prv_last_run_truncated', 0 ), 'truncation flag: prv_last_run_truncated option set to 1' );

exit( prv_test_summary() );
