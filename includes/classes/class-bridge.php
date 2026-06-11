<?php
/**
 * Class Bridge
 *
 * Singleton that bridges external post types into the relations system.
 *
 * HOW it works:
 * ─────────────
 * On `init:50` (after CPTs are registered at priority 10, before the
 * wiring scan at priority 100), this class calls `add_post_type_support()`
 * on external CPTs that should participate in the relations system.
 *
 * WHY add_post_type_support instead of editing the CPT registration?
 * The `gatherpress_play` CPT is owned by `gatherpress-productions`.
 * `add_post_type_support()` is the idiomatic WordPress way for one
 * plugin to extend another plugin's post type without modifying its
 * registration call.
 *
 * WHY priority 50?
 * `gatherpress-productions` registers `gatherpress_play` at default
 * priority (10). We add support at 50 — safely after the CPT exists
 * but well before the Wiring class scans at 100.
 *
 * EXTENSIBILITY:
 * Third-party plugins can bridge their own CPTs the same way:
 * ```php
 * add_action( 'init', function() {
 *     add_post_type_support( 'my_custom_type', 'gatherpress-relations-from' );
 * }, 50 );
 * ```
 *
 * @since   0.1.0
 * @package GatherPressRelations
 */

namespace GatherPressRelations;

use GatherPress\Core\Traits\Singleton;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

class Bridge {
	use Singleton;

	/**
	 * External post types to bridge into the relations system.
	 *
	 * WHY an array?
	 * Makes it trivial to add more external CPTs in future
	 * (e.g., 'gatherpress_event') without changing the hook logic.
	 *
	 * Each entry is a post type slug. The bridge checks
	 * `post_type_exists()` before adding support, so missing
	 * CPTs are silently skipped.
	 *
	 * @since 0.1.0
	 * @var string[]
	 */
	private array $external_types = array(
		'gatherpress_event',
		'gatherpress_play',
	);

	/**
	 * Constructor for the Bridge class.
	 *
	 * Follows the GatherPress core Singleton pattern where each
	 * constructor calls `setup_hooks()` to register its init callbacks.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Hooks the bridge onto `init:50`.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'add_support_to_external_types' ), 50 );
	}

	/**
	 * Adds gatherpress-relations support to external post types.
	 *
	 * HOW it works:
	 * Iterates `$external_types`, checks if each exists, then calls
	 * `add_post_type_support()`. The Wiring class at init:100 will
	 * discover these CPTs via `get_post_types_by_support()`.
	 *
	 * WHY guard with post_type_exists()?
	 * If `gatherpress-productions` is not active, `gatherpress_play`
	 * does not exist. The guard prevents a PHP notice and ensures
	 * graceful degradation.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function add_support_to_external_types(): void {
		foreach ( $this->external_types as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				add_post_type_support( $post_type, 'gatherpress-relations-from' );
			}
		}
	}
}
