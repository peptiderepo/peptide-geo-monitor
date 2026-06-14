<?php
/**
 * Tests for PRV_Model_Registry: v1→v2 migration, CRUD, run-health.
 *
 * @package PrVision
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== PRV Model Registry Tests ===\n";

// ── Test: migration from v1 flat strings preserves live model set ─────────

prv_test_reset();
$GLOBALS['prv_test_state']['options']['prv_models'] = [
	'perplexity/sonar',
	'openai/gpt-4o-search-preview',
	'google/gemini-2.0-flash-001',
];
// No schema version set -- this is the v0.1.x state.

$migrated = PRV_Model_Registry::run_migration_v2();
prv_assert( $migrated, 'v1->v2 migration: run_migration_v2 returns true when migration performed' );

$models = PRV_Model_Registry::get_all();
prv_assert_equals( 3, count( $models ), 'v1->v2 migration: 3 models preserved' );

$slugs = array_column( $models, 'slug' );
prv_assert( in_array( 'perplexity/sonar', $slugs, true ), 'v1->v2 migration: perplexity/sonar preserved' );
prv_assert( in_array( 'openai/gpt-4o-search-preview', $slugs, true ), 'v1->v2 migration: gpt-4o preserved' );
prv_assert( in_array( 'google/gemini-2.0-flash-001', $slugs, true ), 'v1->v2 migration: gemini preserved' );

// Each migrated model has the required v2 fields.
foreach ( $models as $m ) {
	prv_assert( isset( $m['id'] ), "v1->v2 migration: model {$m['slug']} has id" );
	prv_assert( isset( $m['provider'] ), "v1->v2 migration: model {$m['slug']} has provider" );
	prv_assert( isset( $m['health_status'] ), "v1->v2 migration: model {$m['slug']} has health_status" );
	prv_assert( true === $m['enabled'], "v1->v2 migration: model {$m['slug']} is enabled by default" );
}

// Schema version updated.
prv_assert_equals(
	PRV_Model_Registry::SCHEMA_VERSION,
	(int) $GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ],
	'v1->v2 migration: schema version option updated'
);

// ── Test: migration is idempotent (runs twice, second is no-op) ──────────

$before_count = count( PRV_Model_Registry::get_all() );
$second_run   = PRV_Model_Registry::run_migration_v2();
prv_assert( false === $second_run, 'idempotent: second call to run_migration_v2 returns false' );
prv_assert_equals( $before_count, count( PRV_Model_Registry::get_all() ), 'idempotent: model count unchanged after second migration' );

// ── Test: upgrade-from-nothing (no prv_models option) seeds defaults ─────

prv_test_reset();
$migrated2 = PRV_Model_Registry::run_migration_v2();
prv_assert( $migrated2, 'default seed: migration returns true when no models exist' );
$models2 = PRV_Model_Registry::get_all();
prv_assert( count( $models2 ) >= 3, 'default seed: at least 3 default models seeded' );

// ── Test: add model ──────────────────────────────────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$base_count = count( PRV_Model_Registry::get_all() );
$new_id     = PRV_Model_Registry::add( 'anthropic/claude-3-haiku', 'openrouter', true, 'Test model' );
prv_assert( '' !== $new_id, 'add: returns non-empty id' );
prv_assert( str_starts_with( $new_id, 'mdl_' ), 'add: id has mdl_ prefix' );
prv_assert_equals( $base_count + 1, count( PRV_Model_Registry::get_all() ), 'add: model count increments' );

$found = PRV_Model_Registry::find_by_id( $new_id );
prv_assert( null !== $found, 'add: find_by_id returns the new model' );
prv_assert_equals( 'anthropic/claude-3-haiku', $found['slug'], 'add: slug stored correctly' );
prv_assert_equals( 'Test model', $found['note'], 'add: note stored correctly' );

// ── Test: update model ───────────────────────────────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$all    = PRV_Model_Registry::get_all();
$target = $all[0];
$result = PRV_Model_Registry::update( $target['id'], [ 'enabled' => false, 'note' => 'Disabled for test' ] );
prv_assert( $result, 'update: returns true' );
$updated = PRV_Model_Registry::find_by_id( $target['id'] );
prv_assert( null !== $updated, 'update: model still exists' );
prv_assert( false === $updated['enabled'], 'update: enabled flag updated' );
prv_assert_equals( 'Disabled for test', $updated['note'], 'update: note updated' );

// ── Test: remove model ───────────────────────────────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$id_to_remove = PRV_Model_Registry::get_all()[0]['id'];
$count_before = count( PRV_Model_Registry::get_all() );
$removed      = PRV_Model_Registry::remove( $id_to_remove );
prv_assert( $removed, 'remove: returns true' );
prv_assert_equals( $count_before - 1, count( PRV_Model_Registry::get_all() ), 'remove: count decrements' );
prv_assert( null === PRV_Model_Registry::find_by_id( $id_to_remove ), 'remove: model gone from get_all' );

// ── Test: remove non-existent id returns false ───────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
prv_assert( false === PRV_Model_Registry::remove( 'mdl_doesnotexist' ), 'remove: returns false for unknown id' );

// ── Test: get_enabled_slugs only returns enabled ─────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$all   = PRV_Model_Registry::get_all();
// Disable first model.
PRV_Model_Registry::update( $all[0]['id'], [ 'enabled' => false ] );
$slugs = PRV_Model_Registry::get_enabled_slugs();
prv_assert( ! in_array( $all[0]['slug'], $slugs, true ), 'get_enabled_slugs: disabled model excluded' );
prv_assert_equals( count( $all ) - 1, count( $slugs ), 'get_enabled_slugs: correct count' );

// ── Test: update_health sets correct status ──────────────────────────────

prv_test_reset();
PRV_Model_Registry::run_migration_v2();
$all     = PRV_Model_Registry::get_all();
$slug0   = $all[0]['slug'];
$slug1   = isset( $all[1] ) ? $all[1]['slug'] : null;
$run_id  = 'test-run-' . uniqid( '', true );

$outcomes = [ $slug0 => [ 'probed' => 5, 'errors' => 0 ] ];
if ( $slug1 ) {
	$outcomes[ $slug1 ] = [ 'probed' => 0, 'errors' => 3 ];
}

PRV_Model_Registry::update_health( $run_id, $outcomes );

$updated_all = PRV_Model_Registry::get_all();
foreach ( $updated_all as $m ) {
	if ( $m['slug'] === $slug0 ) {
		prv_assert_equals( 'healthy', $m['health_status'], 'update_health: probed>0,errors=0 => healthy' );
		prv_assert_equals( 5, $m['health_probed'], 'update_health: health_probed set correctly' );
		prv_assert_equals( $run_id, $m['health_run_id'], 'update_health: run_id stored' );
	}
	if ( $slug1 && $m['slug'] === $slug1 ) {
		prv_assert_equals( 'retired', $m['health_status'], 'update_health: probed=0,errors>0 => retired' );
		prv_assert_equals( 3, $m['health_errors'], 'update_health: health_errors set correctly' );
	}
}

exit( prv_test_summary() );
