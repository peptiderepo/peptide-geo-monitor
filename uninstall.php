<?php
/**
 * PR Vision -- full data teardown on uninstall.
 *
 * Drops the custom prv_ai_visibility table and deletes every wp_options
 * row whose name starts with "prv_". WP-Cron events are already cleared
 * by PRV_Deactivator on deactivation; this runs after that.
 *
 * v0.2.0 additions: prv_models_schema_version, prv_config_versions,
 * prv_active_config_version, prv_api_key_status, prv_api_key_last_check,
 * prv_last_run_at, prv_last_run_counts, prv_last_run_truncated,
 * prv_last_run_truncated_at -- all purged by the prv_ wildcard DELETE below.
 *
 * v0.2.3 addition: prv_provider_key_enc (encrypted API key) -- also purged
 *
 * v0.3.0 additions: prv_call_meta + prv_call_io tables (both DROPped);
 *   prv_io_retention_days option (purged by wildcard DELETE);
 *   prv_daily_prune cron hook (cleared in step 4).
 * by the prv_ wildcard DELETE below. No plaintext is stored.
 *
 * @see ARCHITECTURE.md          -- Section Uninstall specification.
 * @see class-prv-deactivator.php -- Clears cron on deactivation.
 * @see class-prv-key-store.php   -- Encrypted key storage (prv_provider_key_enc).
 * @package PrVision
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/* ── 0. Bootstrap minimum classes for DROP calls ─────────────────── */

require_once plugin_dir_path( __FILE__ ) . 'includes/core/class-prv-autoloader.php';
PRV_Autoloader::register();

/* ── 1. Drop the custom tables (including v0.3.0 audit tables) ────── */

$table = $wpdb->prefix . 'prv_ai_visibility';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// v0.3.0: per-call audit tables (drop io before meta to respect FK intent).
$io_table = $wpdb->prefix . 'prv_call_io';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$io_table}" );

$meta_table = $wpdb->prefix . 'prv_call_meta';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$meta_table}" );

/* ── 2. Delete all prv_ prefixed options (v0.1, v0.2, v0.2.3) ───── */

// Covers prv_provider_key_enc (v0.2.3) along with all prior option keys.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'prv\_%'"
);

/* ── 3. Delete prv_ transients ────────────────────────────────────── */

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_prv\_%' OR option_name LIKE '_transient_timeout_prv\_%'"
);

/* ── 4. Clear any remaining scheduled cron events ─────────────────── */

wp_clear_scheduled_hook( 'prv_weekly_probe' );
// v0.3.0: daily prune cron.
wp_clear_scheduled_hook( 'prv_daily_prune' );
