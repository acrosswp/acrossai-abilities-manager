<?php
/**
 * Database table definition for the ability execution logs table.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database;

use BerlinDB\Database\Table;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Manages database table creation and upgrades for ability execution logs.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logs_Table extends Table {

	/**
	 * Table name (without WordPress table prefix).
	 *
	 * @var string
	 */
	protected $name = 'acrossai_ability_logs';

	/**
	 * Table version used to trigger maybe_upgrade().
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * WordPress option key used to track the installed schema version.
	 *
	 * @var string
	 */
	protected $db_version_key = 'acrossai_ability_logs_db_version';

	/**
	 * Use per-site table prefix ($wpdb->prefix), not the network base prefix.
	 * Explicitly set to false so multisite intent is declared in code (SEC-03).
	 *
	 * @var bool
	 */
	protected $global = false;

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Logs_Table|null
	 */
	protected static $_instance = null;

	/**
	 * Get the singleton instance of this table.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Logs_Table
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Define the raw SQL column list for CREATE TABLE.
	 *
	 * BerlinDB interpolates $this->schema directly into:
	 *   CREATE TABLE {name} ( {schema} ) {charset_collation}
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "
			`id` bigint(20) unsigned NOT NULL auto_increment,
			`ability_slug` varchar(255) NOT NULL DEFAULT '',
			`source` varchar(20) NOT NULL DEFAULT '',
			`mcp_server_id` varchar(255) DEFAULT NULL,
			`user_id` bigint(20) unsigned DEFAULT NULL,
			`input` longtext DEFAULT NULL,
			`output` longtext DEFAULT NULL,
			`status` varchar(20) NOT NULL DEFAULT '',
			`duration_ms` int(11) NOT NULL DEFAULT 0,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `ability_slug_created` (`ability_slug`(191),`created_at`),
			KEY `source_created` (`source`,`created_at`),
			KEY `user_id_created` (`user_id`,`created_at`),
			KEY `status_created` (`status`,`created_at`)
		";
	}
}
