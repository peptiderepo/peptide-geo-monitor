<?php
/**
 * Plugin activation handler.
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Plugin activation handler.
 *
 * Creates the custom database table via dbDelta and registers the weekly
 * WP-Cron probe schedule. Safe to call on re-activation — dbDelta is
 * idempotent and the cron schedule is only added when absent.
 *
 * Who triggers: register_activation_hook() in pr-vision.php.
 * Dependencies: PRV_Table_Manager, PRV_Cron, PRV_Config.
 *
 * @see class-prv-table-manager.php — Database schema creation.
 * @see class-prv-cron.php          — Cron scheduling.
 * @see class-prv-prune-cron.php    — Daily prune cron (v0.3.0).
 * @package PrVision
 */
class PRV_Activator {

	/**
	 * Run all activation tasks.
	 *
	 * Side effects: Creates DB table, writes default options, schedules cron.
	 *
	 * @return void
	 */
	public static function activate(): void {
		PRV_Table_Manager::create_table();
		// v0.3.0: per-call audit trail tables.
		PRV_Call_Meta_Table::create_table();
		PRV_Call_Io_Table::create_table();
		PRV_Config::seed_defaults();
		PRV_Cron::schedule_weekly();
		// v0.3.0: daily prune cron for prv_call_io.
		PRV_Prune_Cron::schedule();
	}
}
