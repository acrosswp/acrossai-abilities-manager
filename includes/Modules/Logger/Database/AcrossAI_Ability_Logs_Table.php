<?php
/**
 * Database table registration for ability execution logs.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database;

use BerlinDB\Database\Table;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Manages database table creation and upgrades for ability execution logs.
 *
 * Registered at plugins_loaded P20 to create the table schema if it doesn't exist.
 * Per-site isolation is enforced via $global = false (SEC-03).
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
	protected $version = '1';

	/**
	 * WordPress option key used to track the installed schema version.
	 *
	 * @var string
	 */
	protected $db_version_key = 'acrossai_ability_logs_db_version';

	/**
	 * Schema class for this table (BerlinDB v3: property, not overridden method).
	 *
	 * @var string
	 */
	protected $schema = AcrossAI_Ability_Logs_Schema::class;

	/**
	 * Use per-site table prefix ($wpdb->prefix), not the network base prefix.
	 * Explicitly set to false for per-site isolation (multisite compliance — SEC-03).
	 *
	 * @var bool
	 */
	protected $global = false;

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Logs_Table|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance of this table.
	 *
	 * Note: constructor is intentionally NOT private. BerlinDB\Database\Table
	 * performs table-registration side-effects in parent::__construct().
	 * A private constructor would prevent those from running and break table
	 * registration. Justified exception to the Module Contract.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Logs_Table
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
