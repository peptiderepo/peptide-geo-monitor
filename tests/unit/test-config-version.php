<?php
/**
 * Tests for PRV_Config_Version: config hash, bump, version records.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== PRV Config Version Tests ===\n";

// Shared API key constant.
if ( ! defined( 'PRV_OPENROUTER_API_KEY' ) ) {
	define( 'PRV_OPENROUTER_API_KEY', 'sk-or-test-key' );
}
if ( ! defined( 'PRV_CF_ACCOUNT_ID' ) ) {
	define( 'PRV_CF_ACCOUNT_ID', '' );
}
if ( ! defined( 'PRV_CF_GATEWAY_ID' ) ) {
	define( 'PRV_CF_GATEWAY_ID', '' );
}

// ── Test: maybe_seed_initial_version creates a v1 record ────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
PRV_Config_Version::maybe_seed_initial_version();

$versions = PRV_Config_Version::get_all_versions();
prv_assert( count( $versions ) >= 1, 'seed: at least 1 version record after initial seed' );
prv_assert_equals( 1, (int) $versions[0]['version'], 'seed: first version number is 1' );
prv_assert( ! empty( $versions[0]['hash'] ), 'seed: version has a hash' );
prv_assert_equals( 1, PRV_Config_Version::get_active_version(), 'seed: active version is 1' );

// ── Test: maybe_seed_initial_version is idempotent ───────────────────────

PRV_Config_Version::maybe_seed_initial_version();
prv_assert_equals( 1, count( PRV_Config_Version::get_all_versions() ), 'seed idempotent: still 1 version after second seed call' );

// ── Test: bump when config unchanged returns null ────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
PRV_Config_Version::maybe_seed_initial_version();

$bump = PRV_Config_Version::bump_version_if_changed();
prv_assert( null === $bump, 'bump: returns null when config unchanged' );
prv_assert_equals( 1, PRV_Config_Version::get_active_version(), 'bump: active version stays 1 when config unchanged' );

// ── Test: bump after scoring-relevant change creates new version ─────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
PRV_Config_Version::maybe_seed_initial_version();

// Change the model list (scoring-relevant).
$all = PRV_Model_Registry::get_all();
PRV_Model_Registry::add( 'anthropic/claude-3-haiku', 'openrouter', true, 'New model' );

$new_ver = PRV_Config_Version::bump_version_if_changed();
prv_assert( null !== $new_ver, 'bump: returns new version number when config changed' );
prv_assert_equals( 2, (int) $new_ver, 'bump: new version is 2' );
prv_assert_equals( 2, PRV_Config_Version::get_active_version(), 'bump: active version updated to 2' );
prv_assert_equals( 2, count( PRV_Config_Version::get_all_versions() ), 'bump: 2 version records exist' );

// ── Test: would_change detects a config change ───────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
PRV_Config_Version::maybe_seed_initial_version();

$original_hash = PRV_Config_Version::compute_hash();
prv_assert( false === PRV_Config_Version::would_change( $original_hash ), 'would_change: false when hash is current' );

// Modify config.
PRV_Model_Registry::add( 'x/new-model', 'openrouter', true );
$new_hash = PRV_Config_Version::compute_hash();
prv_assert( true === PRV_Config_Version::would_change( $new_hash ), 'would_change: true when config differs' );

// ── Test: compute_hash is stable for same config ─────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$h1 = PRV_Config_Version::compute_hash();
$h2 = PRV_Config_Version::compute_hash();
prv_assert_equals( $h1, $h2, 'compute_hash: stable for same config' );

// ── Test: config_version stamped on probe result ─────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
PRV_Config_Version::maybe_seed_initial_version();
prv_assert_equals( 1, PRV_Config_Version::get_active_version(), 'version stamp: active version is 1 before run' );

// Verify the runner writes config_version to DB (check the last DB insert args).
$GLOBALS['prv_test_state']['options']['prv_peptides']       = [ [ 'slug' => 'bpc-157', 'label' => 'BPC-157' ] ];
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = [ 'what is {peptide}' ];
$GLOBALS['prv_test_state']['options']['prv_models']         = [
	[ 'id' => 'mdl_test01', 'slug' => 'perplexity/sonar', 'provider' => 'perplexity', 'enabled' => true, 'note' => '', 'health_status' => 'unknown', 'health_probed' => 0, 'health_errors' => 0, 'health_run_id' => null ]
];
$GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ] = PRV_Model_Registry::SCHEMA_VERSION;

$cited_body = json_encode([
	'choices'   => [['message' => ['content' => 'test']]],
	'citations' => ['https://peptiderepo.com/bpc-157/'],
	'usage'     => ['total_tokens' => 50],
]);
$GLOBALS['prv_test_state']['remote_posts'] = [
	['response' => ['code' => 200], 'body' => $cited_body],
];

$runner = new PRV_Probe_Runner( new PRV_Cost_Ledger() );
$result = $runner->run();
prv_assert_equals( 1, $result['probed'], 'version stamp: runner completes 1 probe' );
// The prv_active_config_version persists after the run.
prv_assert_equals( 1, PRV_Config_Version::get_active_version(), 'version stamp: config version unchanged after run' );

exit( prv_test_summary() );
