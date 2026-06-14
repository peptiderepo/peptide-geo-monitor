<?php
/**
 * Upgrade runner: idempotent, version-gated migrations.
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Runs pending data migrations on every plugins_loaded call.
 *
 * Each migration is version-gated (cheap option check first) so it only
 * runs when truly needed. Safe to call repeatedly -- all migrations are
 * idempotent.  This fires on UPGRADE (not just activation), so the plugin
 * running live on prod without a fresh activation still gets migrated.
 *
 * Who triggers: PRV_Plugin::init() at plugins_loaded priority 1.
 * Dependencies: PRV_Model_Registry, PRV_Config_Version.
 *
 * @see class-prv-model-registry.php    -- Migration v2 implementation.
 * @see class-prv-config-version.php    -- Scoring-config versioning.
 * @see class-prv-plugin.php            -- Calls PRV_Upgrader::run().
 * @package PrVision
 */
class PRV_Upgrader {

	/**
	 * Run all pending migrations in version order.
	 *
	 * Side effects: May write to wp_options (migration state).
	 *
	 * @return void
	 */
	public static function run(): void {
		PRV_Table_Manager::create_table();
		PRV_Model_Registry::run_migration_v2();
		PRV_Config_Version::maybe_seed_initial_version();
	}
}
