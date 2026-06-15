<?php
/**
 * Database schema for the per-call I/O table (v0.3.0) — prunable.
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Manages the prv_call_io table: rendered prompt + raw response, PRUNED.
 *
 * Only the body-level content is stored here — never Authorization headers,
 * api keys, or raw HTTP request objects. The prune cron deletes rows older
 * than the retention window; the companion prv_call_meta table is kept.
 *
 * Who triggers: PRV_Upgrader::run() on every plugins_loaded (idempotent).
 * Dependencies: $wpdb, dbDelta(), PRV_Call_Meta_Table (FK source).
 *
 * @see class-prv-call-meta-table.php — FK source: prv_call_meta.id.
 * @see class-prv-capture-writer.php  — Writes rows to this table.
 * @see class-prv-prune-cron.php      — Deletes aged rows from this table only.
 * @see ARCHITECTURE.md               — §Storage v0.3.0.
 * @package PrVision
 */
class PRV_Call_Io_Table {

	/**
	 * Table base name (without $wpdb->prefix).
	 */
	const TABLE_BASE = 'prv_call_io';

	/**
	 * Create or upgrade the call-io table via dbDelta.
	 *
	 * Safe to call on every plugins_loaded — dbDelta is idempotent.
	 * Side effects: Database write.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table           = $wpdb->prefix . self::TABLE_BASE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			call_id     BIGINT(20) UNSIGNED NOT NULL,
			prompt_text LONGTEXT            NOT NULL DEFAULT '',
			response_text LONGTEXT          NOT NULL DEFAULT '',
			captured_at DATETIME            NOT NULL,
			PRIMARY KEY (id),
			KEY call_id (call_id),
			KEY captured_at (captured_at)
		) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );
	}

	/**
	 * Get the full table name including WP prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_BASE;
	}

	/**
	 * Drop the table. Called from uninstall.php only.
	 *
	 * Side effects: Permanently destroys all I/O records.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_BASE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
