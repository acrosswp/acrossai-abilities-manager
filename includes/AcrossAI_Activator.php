<?php
/**
 * Fired during plugin activation.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table;
use WPBoilerplate\AccessControl\Database\Rule\RuleTable;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.0.1
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @author     AcrossWP <deepak@acrosswp.com>
 */
class AcrossAI_Activator {

	/**
	 * Run activation tasks.
	 *
	 * Creates or upgrades the {prefix}acrossai_abilities,
	 * {prefix}acrossai_ability_logs, and {prefix}wpb_access_control tables.
	 *
	 * @since  0.0.1
	 * @return void
	 */
	public static function activate(): void {
		( new AcrossAI_Abilities_Table() )->maybe_upgrade();
		( new AcrossAI_Ability_Logs_Table() )->maybe_upgrade();
		( new RuleTable() )->maybe_upgrade();
	}
}
