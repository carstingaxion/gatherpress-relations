<?php
/**
 * Plugin Name:       GatherPress Relations
 * Plugin URI:        https://github.com/carstingaxion/gatherpress-relations
 * Description:       Connects persons to events (or any post type) with individual roles per relation.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Requires plugins:  gatherpress
 * Author:            carstenbach
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gatherpress-relations
 * Domain Path:       /languages
 *
 * @package GatherPressRelations
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_RELATIONS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_RELATIONS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_RELATIONS_CORE_FILE', __FILE__ );


/**
 * Adds the GatherPressRelations namespace to the GatherPress autoloader.
 *
 * HOW this works:
 * ───────────────
 * GatherPress core provides a PSR-4-like autoloader that maps namespace
 * prefixes to directory paths. By hooking into `gatherpress_autoloader`,
 * this plugin registers its namespace so that any class reference like
 * `GatherPressRelations\Setup` resolves to
 * `includes/classes/class-setup.php` automatically.
 *
 * WHY use the GatherPress autoloader?
 * 1. Consistent with the GatherPress ecosystem — every companion plugin
 *    uses the same mechanism (gatherpress-seasons, gatherpress-productions).
 * 2. Zero custom autoloader code to maintain.
 * 3. The namespace-to-path mapping follows WordPress conventions
 *    (`class-<name>.php` file naming).
 *
 * @since 0.1.0
 *
 * @param array<string, string> $namespaces An associative array of namespaces and their paths.
 * @return array<string, string> Modified array of namespaces and their paths.
 */
if ( ! function_exists( 'gatherpress_relations_autoloader' ) ) {
	function gatherpress_relations_autoloader( array $namespaces ): array {
		$namespaces['GatherPressRelations'] = GATHERPRESS_RELATIONS_CORE_PATH;

		return $namespaces;
	}
}
add_filter( 'gatherpress_autoloader', 'gatherpress_relations_autoloader' );


/**
 * Initializes the plugin.
 *
 * Bootstrap function that starts the plugin by instantiating the main
 * Setup class. Hooks into `plugins_loaded` to ensure that GatherPress
 * core (and its autoloader) is available before we reference any of
 * our namespaced classes.
 *
 * @since 0.1.0
 * @return void
 */
if ( ! function_exists( 'gatherpress_relations_setup' ) ) {
	function gatherpress_relations_setup(): void {
		if ( defined( 'GATHERPRESS_VERSION' ) ) {
			\GatherPressRelations\Setup::get_instance();
		}
	}
}
add_action( 'plugins_loaded', 'gatherpress_relations_setup' );


// add_action( 'init', function() {
// register_post_type( 'gatherpress_sponsor', array(
// 	'label' => 'Sponsor',
// 	'public' => true,
// 	'show_in_rest' => true,
// 	'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'gatherpress-shadow-source', 'gatherpress-relations-to' ),
// ) );
// } );

// add_filter(
//     'gatherpress_relations_departments',
//     function ( array $departments, string $source_type ): array {
//         if ( 'gatherpress_sponsor' === $source_type ) {
//             return array(
//                 'gold'   => __( 'Gold',   'gatherpress-sponsors' ),
//                 'silver' => __( 'Silver', 'gatherpress-sponsors' ),
//                 'bronze' => __( 'Bronze', 'gatherpress-sponsors' ),
//             );
//         }
//         return $departments;
//     },
//     10,
//     2
// );
