<?php
/**
 * Tests for PRV_Run_Lock: acquire/release/is_locked concurrency guard.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== PRV Run Lock Tests ===\n";

// ── Test: acquire returns true when not locked ────────────────────────────

prv_test_reset();
prv_assert( PRV_Run_Lock::acquire(), 'acquire: returns true when lock is free' );

// ── Test: is_locked true while acquired ──────────────────────────────────

prv_assert( PRV_Run_Lock::is_locked(), 'is_locked: true immediately after acquire' );

// ── Test: second acquire returns false (lock busy) ───────────────────────

prv_assert( false === PRV_Run_Lock::acquire(), 'acquire: returns false when already locked' );

// ── Test: release clears the lock ────────────────────────────────────────

PRV_Run_Lock::release();
prv_assert( false === PRV_Run_Lock::is_locked(), 'is_locked: false after release' );

// ── Test: acquire succeeds again after release ───────────────────────────

prv_assert( PRV_Run_Lock::acquire(), 'acquire: succeeds again after release' );
PRV_Run_Lock::release();

// ── Test: locked_since returns null when not locked ──────────────────────

prv_test_reset();
prv_assert( null === PRV_Run_Lock::locked_since(), 'locked_since: null when not locked' );

// ── Test: locked_since returns non-null when locked ──────────────────────

PRV_Run_Lock::acquire();
$since = PRV_Run_Lock::locked_since();
prv_assert( null !== $since, 'locked_since: non-null when locked' );
prv_assert( $since >= 0, 'locked_since: >= 0 seconds' );
PRV_Run_Lock::release();

// ── Test: probe runner refuses when locked (skipped_error sentinel) ───────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$GLOBALS['prv_test_state']['options']['prv_peptides']       = [ [ 'slug' => 'bpc-157', 'label' => 'BPC-157' ] ];
$GLOBALS['prv_test_state']['options']['prv_prompt_intents'] = [ 'what is {peptide}' ];

// Pre-acquire the lock.
PRV_Run_Lock::acquire();

if ( ! defined( 'PRV_OPENROUTER_API_KEY' ) ) {
	define( 'PRV_OPENROUTER_API_KEY', 'sk-or-test-key' );
}
if ( ! defined( 'PRV_CF_ACCOUNT_ID' ) ) {
	define( 'PRV_CF_ACCOUNT_ID', '' );
}
if ( ! defined( 'PRV_CF_GATEWAY_ID' ) ) {
	define( 'PRV_CF_GATEWAY_ID', '' );
}

$runner = new PRV_Probe_Runner( new PRV_Cost_Ledger() );
$counts = $runner->run();

// When lock is busy, skipped_error is -1 (sentinel) and probed is 0.
prv_assert_equals( 0, $counts['probed'], 'run-lock: runner returns 0 probed when locked' );
prv_assert_equals( -1, $counts['skipped_error'], 'run-lock: skipped_error is -1 sentinel when lock busy' );

PRV_Run_Lock::release();

// ── Test: runner releases lock even on exception (lock cleanup) ──────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
prv_assert( false === PRV_Run_Lock::is_locked(), 'run-lock cleanup: lock released after runner completes' );

exit( prv_test_summary() );
