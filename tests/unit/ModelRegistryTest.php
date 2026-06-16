<?php
/**
 * Tests for PRV_Model_Registry: v1->v2 migration, CRUD, run-health.
 *
 * @package PrVision
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers PRV_Model_Registry
 */
class ModelRegistryTest extends TestCase {

	protected function setUp(): void {
		prv_test_reset();
	}

	public function test_migration_v1_to_v2_preserves_live_models(): void {
		$GLOBALS['prv_test_state']['options']['prv_models'] = [
			'perplexity/sonar',
			'openai/gpt-4o-search-preview',
			'google/gemini-2.0-flash-001',
		];

		$migrated = PRV_Model_Registry::run_migration_v2();
		$this->assertTrue( $migrated );

		$models = PRV_Model_Registry::get_all();
		$this->assertCount( 3, $models );

		$slugs = array_column( $models, 'slug' );
		$this->assertContains( 'perplexity/sonar', $slugs );
		$this->assertContains( 'openai/gpt-4o-search-preview', $slugs );
		$this->assertContains( 'google/gemini-2.0-flash-001', $slugs );

		foreach ( $models as $m ) {
			$this->assertArrayHasKey( 'id', $m );
			$this->assertArrayHasKey( 'provider', $m );
			$this->assertArrayHasKey( 'health_status', $m );
			$this->assertTrue( $m['enabled'] );
		}

		$this->assertSame(
			PRV_Model_Registry::SCHEMA_VERSION,
			(int) $GLOBALS['prv_test_state']['options'][ PRV_Model_Registry::VERSION_KEY ]
		);
	}

	public function test_migration_is_idempotent(): void {
		$GLOBALS['prv_test_state']['options']['prv_models'] = [ 'perplexity/sonar' ];
		PRV_Model_Registry::run_migration_v2();
		$before_count = count( PRV_Model_Registry::get_all() );
		$second_run   = PRV_Model_Registry::run_migration_v2();

		$this->assertFalse( $second_run );
		$this->assertSame( $before_count, count( PRV_Model_Registry::get_all() ) );
	}

	public function test_migration_seeds_defaults_when_no_models(): void {
		$migrated = PRV_Model_Registry::run_migration_v2();
		$this->assertTrue( $migrated );
		$this->assertGreaterThanOrEqual( 3, count( PRV_Model_Registry::get_all() ) );
	}

	public function test_add_model(): void {
		PRV_Model_Registry::run_migration_v2();
		$base_count = count( PRV_Model_Registry::get_all() );
		$new_id     = PRV_Model_Registry::add( 'anthropic/claude-3-haiku', 'openrouter', true, 'Test model' );

		$this->assertNotEmpty( $new_id );
		$this->assertStringStartsWith( 'mdl_', $new_id );
		$this->assertCount( $base_count + 1, PRV_Model_Registry::get_all() );

		$found = PRV_Model_Registry::find_by_id( $new_id );
		$this->assertNotNull( $found );
		$this->assertSame( 'anthropic/claude-3-haiku', $found['slug'] );
		$this->assertSame( 'Test model', $found['note'] );
	}

	public function test_update_model(): void {
		PRV_Model_Registry::run_migration_v2();
		$target = PRV_Model_Registry::get_all()[0];
		$result = PRV_Model_Registry::update( $target['id'], [ 'enabled' => false, 'note' => 'Disabled for test' ] );

		$this->assertTrue( $result );
		$updated = PRV_Model_Registry::find_by_id( $target['id'] );
		$this->assertFalse( $updated['enabled'] );
		$this->assertSame( 'Disabled for test', $updated['note'] );
	}

	public function test_remove_model(): void {
		PRV_Model_Registry::run_migration_v2();
		$all          = PRV_Model_Registry::get_all();
		$id           = $all[0]['id'];
		$count_before = count( $all );

		$removed = PRV_Model_Registry::remove( $id );
		$this->assertTrue( $removed );
		$this->assertCount( $count_before - 1, PRV_Model_Registry::get_all() );
		$this->assertNull( PRV_Model_Registry::find_by_id( $id ) );
	}

	public function test_remove_nonexistent_returns_false(): void {
		PRV_Model_Registry::run_migration_v2();
		$this->assertFalse( PRV_Model_Registry::remove( 'mdl_doesnotexist' ) );
	}

	public function test_get_enabled_slugs_excludes_disabled(): void {
		PRV_Model_Registry::run_migration_v2();
		$all = PRV_Model_Registry::get_all();
		PRV_Model_Registry::update( $all[0]['id'], [ 'enabled' => false ] );
		$slugs = PRV_Model_Registry::get_enabled_slugs();

		$this->assertNotContains( $all[0]['slug'], $slugs );
		$this->assertCount( count( $all ) - 1, $slugs );
	}

	public function test_update_health_sets_correct_status(): void {
		PRV_Model_Registry::run_migration_v2();
		$all    = PRV_Model_Registry::get_all();
		$slug0  = $all[0]['slug'];
		$slug1  = $all[1]['slug'] ?? null;
		$run_id = 'test-run-' . uniqid( '', true );

		$outcomes = [ $slug0 => [ 'probed' => 5, 'errors' => 0 ] ];
		if ( $slug1 ) {
			$outcomes[ $slug1 ] = [ 'probed' => 0, 'errors' => 3 ];
		}
		PRV_Model_Registry::update_health( $run_id, $outcomes );

		foreach ( PRV_Model_Registry::get_all() as $m ) {
			if ( $m['slug'] === $slug0 ) {
				$this->assertSame( 'healthy', $m['health_status'] );
				$this->assertSame( 5, $m['health_probed'] );
				$this->assertSame( $run_id, $m['health_run_id'] );
			}
			if ( $slug1 && $m['slug'] === $slug1 ) {
				$this->assertSame( 'retired', $m['health_status'] );
				$this->assertSame( 3, $m['health_errors'] );
			}
		}
	}
}
