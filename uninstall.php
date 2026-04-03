<?php
/**
 * Uninstall routine.
 *
 * @package Abilities_Editor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'abilities-editor.php';

Abilities_Editor\Database\Schema::drop_table();
