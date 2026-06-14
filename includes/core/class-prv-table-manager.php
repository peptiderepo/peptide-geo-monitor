<?php
/**
 * Database table creation and schema versioning for PR Vision.
 *
 * @package PrVision
 */

declare(strict_types=1);

/**
 * Database table creation and schema versioning for PR Vision.
 *
 * Creates {prefix}prv_ai_visibility via dbDelta (idempotent). Tracks the
 * schema version in the prv_schema_version option so future migrations can
 * detect when an upgrade is needed without destructive ALTER TABLE.
 *
 * v0.2.0 adds config_version INT column (nullable; existing rows get NULL
 * which is handled cleanly by scoring queries that filter by version).
 *
 * Who triggers: PRV_Upgrader::run() on every plugins_loaded call (idempotent).
 * Dependencies: $wpdb global, dbDelta() (requires upgrade.php).
 *
 * @see class-prv-upgrader.php -- Calls create_table() on upgrade.
 * @see ARCHITECTURE.md        -- Section Storage.
 * @package PrVision
 */
class PRV_Table_Manager {

	/**
	 * Name of the custom table (without $wpdb->prefix).
	 */
	const TABLE_BASE = 'prv_ai_visibility';

	/**
	 * Current DB schema version.
	 */
	const SCHEMA_VERSION = 2;

	/**
	 * Create or upgrade the AI-visibility table.
	 *
	 * Uses dbDelta to create or non-destructively alter the table.
	 * Records the schema version in an option afterwards.
	 * Safe to call on every plugins_loaded -- dbDelta is idempotent.
	 *
	 * Side effects: Writes to the database; sets prv_schema_version option.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_BASE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id         VARCHAR(36)         NOT NULL,
			captured_at    DATETIME            NOT NULL,
			peptide_slug   VARCHAR(200)        NOT NULL,
			peptide_label  VARCHAR(200)        NOT NULL,
			model          VARCHAR(200)        NOT NULL,
			prompt_intent  VARCHAR(200)        NOT NULL,
			cited          TINYINT(1)          NOT NULL DEFAULT 0,
			our_position   INT(11)             NULL     DEFAULT NULL,
			source_domains LONGTEXT            NOT NULL DEFAULT '',
			raw_excerpt    LONGTEXT            NOT NULL DEFAULT '',
			cost_usd       DECIMAL(12,8)       NOT NULL DEFAULT 0.00000000,
			config_version INT(11)             NULL     DEFAULT NULL,
			PRIMARY KEY (id),
			KEY run_id (run_id),
			KEY peptide_slug (peptide_slug),
			KEY captured_at (captured_at),
			KEY config_version (config_version)
		) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );

		update_option( 'prv_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Get the full table name including the WP prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_BASE;
	}

	/**
	 * Drop the AI-visibility table entirely.
	 *
	 * Called only from uninstall.php -- never during normal deactivation.
	 *
	 * Side effects: Permanently destroys all probe data.
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
