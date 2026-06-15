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
 * @see class-prv-call-meta-table.php   -- [v0.3.0] Per-call metadata table.
 * @see class-prv-call-io-table.php     -- [v0.3.0] Per-call I/O table (prunable).
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
		// v0.3.0: per-call cost/metadata + I/O tables.
		PRV_Call_Meta_Table::create_table();
		PRV_Call_Io_Table::create_table();
		PRV_Model_Registry::run_migration_v2();
		PRV_Config_Version::maybe_seed_initial_version();
		// v0.3.0: seed retention default if not set.
		add_option( 'prv_io_retention_days', PRV_IO_RETENTION_DEFAULT_DAYS );
	}
}
