<?php
/**
 * BerlinDB Row class for a single ability override record.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database;

use BerlinDB\Database\Row;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Represents a single row from the acrossai_abilities_overwrite table.
 *
 * Tinyint → PHP bool/null casting is delegated to AcrossAI_Sanitizer::cast_tri_state()
 * and MUST NOT be duplicated here (RF-02).
 *
 * @since 0.1.0
 *
 * @property int         $id
 * @property string      $ability_slug
 * @property string|null $provider
 * @property string|null $source
 * @property bool|null   $site_allowed
 * @property bool|null   $readonly
 * @property bool|null   $destructive
 * @property bool|null   $idempotent
 * @property bool|null   $show_in_rest
 * @property bool|null   $show_in_mcp
 * @property string|null $mcp_type
 * @property string|null $mcp_servers
 * @property string      $created_at
 * @property string|null $updated_at
 * @property int|null    $created_by
 * @property int|null    $updated_by
 */
class AcrossAI_Sitewide_Row extends Row {

	/**
	 * Primary key.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Ability slug identifier.
	 *
	 * @var string
	 */
	public $ability_slug = '';

	/**
	 * Provider string.
	 *
	 * @var string|null
	 */
	public $provider = null;

	/**
	 * Source enum: plugin|theme|core|db.
	 *
	 * @var string|null
	 */
	public $source = null;

	/**
	 * Whether the ability is allowed site-wide. NULL = use registry default.
	 *
	 * @var bool|null
	 */
	public $site_allowed = null;

	/**
	 * Whether the ability is read-only. NULL = use registry default.
	 *
	 * @var bool|null
	 */
	public $readonly = null;

	/**
	 * Whether the ability is destructive. NULL = use registry default.
	 *
	 * @var bool|null
	 */
	public $destructive = null;

	/**
	 * Whether the ability is idempotent. NULL = use registry default.
	 *
	 * @var bool|null
	 */
	public $idempotent = null;

	/**
	 * Whether the ability is shown in the REST API. NULL = use registry default.
	 *
	 * @var bool|null
	 */
	public $show_in_rest = null;

	/**
	 * Whether the ability is shown in MCP. NULL = use registry default.
	 *
	 * @var bool|null
	 */
	public $show_in_mcp = null;

	/**
	 * MCP type override. NULL = use registry default.
	 *
	 * @var string|null
	 */
	public $mcp_type = null;

	/**
	 * JSON-encoded MCP server IDs. NULL = all servers.
	 *
	 * @var string|null
	 */
	public $mcp_servers = null;

	/**
	 * Creation timestamp.
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Last update timestamp.
	 *
	 * @var string|null
	 */
	public $updated_at = null;

	/**
	 * User ID who created the record.
	 *
	 * @var int|null
	 */
	public $created_by = null;

	/**
	 * User ID who last updated the record.
	 *
	 * @var int|null
	 */
	public $updated_by = null;

	/**
	 * Constructor — casts tinyint fields to PHP bool/null via shared utility.
	 *
	 * @since  0.1.0
	 * @param  object|array $item Raw DB row.
	 */
	public function __construct( $item ) {
		parent::__construct( $item );

		// Cast tinyint columns using the shared sanitizer utility (RF-02).
		$tri_state_fields = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'show_in_mcp' );
		foreach ( $tri_state_fields as $field ) {
			$this->{$field} = AcrossAI_Sanitizer::cast_tri_state( $this->{$field} );
		}

		// JSON-decode mcp_servers — stored as JSON longtext, must return as PHP array.
		// Without this, the REST response would return a raw JSON string instead of an array,
		// breaking the JS client which expects string[].
		if ( null !== $this->mcp_servers ) {
			$decoded           = json_decode( $this->mcp_servers, true );
			$this->mcp_servers = is_array( $decoded ) ? $decoded : null;
		}

		// Cast integer fields.
		$this->id         = (int) $this->id;
		$this->created_by = null !== $this->created_by ? (int) $this->created_by : null;
		$this->updated_by = null !== $this->updated_by ? (int) $this->updated_by : null;
	}
}
