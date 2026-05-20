<?php
/**
 * BerlinDB Table manager for custom abilities
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Database
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use BerlinDB\Database\Table;

/**
 * Class AcrossAI_Custom_Ability_Table
 *
 * Manages the custom_abilities BerlinDB table.
 * Singleton pattern enforces single instance (Memory SEC-PLAN-002).
 * Per-site table ($global = false) for multisite isolation (Memory SEC-03).
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Table extends Table {

	/**
	 * Singleton instance.
	 *
	 * @since 0.0.1
	 * @var self
	 */
	protected static $_instance = null;

	/**
	 * Name of the table.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $name = 'acrossai_custom_abilities';

	/**
	 * Schema class.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $schema = AcrossAI_Custom_Ability_Schema::class;

	/**
	 * Row class.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $row = AcrossAI_Custom_Ability_Row::class;

	/**
	 * Query class.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $query = AcrossAI_Custom_Ability_Query::class;

	/**
	 * Whether table is multisite-global.
	 *
	 * @since 0.0.1
	 * @var bool
	 */
	public $global = false; // Per-site table for multisite isolation (Memory SEC-03)

	/**
	 * Get singleton instance.
	 *
	 * @since 0.0.1
	 * @return self Singleton instance.
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Private constructor (singleton pattern).
	 *
	 * @since 0.0.1
	 */
	private function __construct() {
		parent::__construct();
	}

	/**
	 * Insert a new custom ability record.
	 *
	 * @since 0.0.1
	 * @param array $data Ability data.
	 * @return int|false Record ID or false on failure.
	 */
	public function insert( $data = array() ) {
		return parent::insert( $data );
	}

	/**
	 * Update a custom ability record.
	 *
	 * @since 0.0.1
	 * @param int   $id   Record ID.
	 * @param array $data Fields to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( $id = 0, $data = array() ) {
		return parent::update( $id, $data );
	}

	/**
	 * Delete a custom ability record.
	 *
	 * @since 0.0.1
	 * @param int $id Record ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id = 0 ) {
		return parent::delete( $id );
	}

	/**
	 * Get a single custom ability by ID.
	 *
	 * @since 0.0.1
	 * @param int $id Record ID.
	 * @return object|null AcrossAI_Custom_Ability_Row object or null.
	 */
	public function get( $id = 0 ) {
		return parent::get( $id );
	}

	/**
	 * Get custom abilities via query builder.
	 *
	 * @since 0.0.1
	 * @param array $args Query arguments.
	 * @return array Array of AcrossAI_Custom_Ability_Row objects.
	 */
	public function query( $args = array() ) {
		return $this->new_query( $args )->get();
	}

	/**
	 * Create a new query instance.
	 *
	 * @since 0.0.1
	 * @param array $args Query arguments.
	 * @return AcrossAI_Custom_Ability_Query Query instance.
	 */
	public function new_query( $args = array() ) {
		return new $this->query( $this, $args );
	}

	/**
	 * Get all enabled custom abilities.
	 *
	 * Used for WordPress Abilities API registration at wp_abilities_api_init.
	 *
	 * @since 0.0.1
	 * @return array Array of AcrossAI_Custom_Ability_Row objects (enabled only).
	 */
	public function get_enabled() {
		return $this->new_query()
			->enabled_only()
			->order_by( 'ability_slug', 'ASC' )
			->get();
	}

	/**
	 * Get a single ability by slug.
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug.
	 * @return object|null AcrossAI_Custom_Ability_Row object or null.
	 */
	public function get_by_slug( $slug = '' ) {
		$results = $this->new_query()
			->by_slug( $slug )
			->get();

		return ! empty( $results ) ? $results[0] : null;
	}

	/**
	 * Check if a slug exists.
	 *
	 * Used for uniqueness validation.
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug.
	 * @return bool True if slug exists, false otherwise.
	 */
	public function slug_exists( $slug = '' ) {
		return null !== $this->get_by_slug( $slug );
	}

	/**
	 * Get count of all custom abilities.
	 *
	 * @since 0.0.1
	 * @return int Total count.
	 */
	public function count() {
		return $this->new_query()->count();
	}
}
