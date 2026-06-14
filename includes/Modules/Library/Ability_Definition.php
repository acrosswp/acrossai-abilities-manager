<?php
/**
 * Abstract base class for ability definitions.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for ability definitions.
 *
 * Subclasses implement one abstract method (ability()) — the Library page
 * derives its grouping fields (category, slug, labels) automatically.
 * The constructor hooks acrossai_abilities_api_init automatically.
 *
 * Optional: args['sub_group'] adds a display-only sub-heading inside the
 * Library Specific panel. Does NOT affect saved config or execution.
 */
abstract class Ability_Definition {

	/**
	 * Register the push_definition filter callback.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_filter( 'acrossai_abilities_api_init', array( $this, 'push_definition' ) );
	}

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * Must return an array with:
	 *   - 'name'  (string) the unique ability name, e.g. 'plugin-slug/ability-slug'
	 *   - 'args'  (array)  the args passed to wp_register_ability:
	 *                      label, description, category, execute_callback,
	 *                      permission_callback, input_schema, output_schema, meta
	 *
	 * The Library page derives its display fields from this return value:
	 *   - Library card grouping: args['category']
	 *   - Per-row label:         args['label']
	 *   - Unique slug:           name
	 */
	abstract protected function ability(): array;

	/**
	 * Filter callback — wired automatically by the constructor.
	 *
	 * Derives Library grouping fields from ability() so subclasses only need
	 * to implement the single ability() method.
	 *
	 * @param array $definitions Existing definitions collected so far.
	 * @return array
	 */
	public function push_definition( array $definitions ): array {
		$spec = $this->ability();
		$name = $spec['name'] ?? '';
		$args = $spec['args'] ?? array();

		$category  = $args['category'] ?? '';
		$sub_group = isset( $args['sub_group'] ) ? (string) $args['sub_group'] : '';

		$row = array(
			'category'       => $category,
			'category_label' => ucwords( str_replace( '-', ' ', $category ) ),
			'slug'           => $name,
			'slug_label'     => $args['label'] ?? $name,
			'name'           => $name,
			'args'           => $args,
		);

		if ( '' !== $sub_group ) {
			$row['sub_group']       = $sub_group;
			$row['sub_group_label'] = isset( $args['sub_group_label'] ) && '' !== $args['sub_group_label']
				? (string) $args['sub_group_label']
				: ucwords( str_replace( '-', ' ', $sub_group ) );
		}

		$definitions[] = $row;

		return $definitions;
	}
}
