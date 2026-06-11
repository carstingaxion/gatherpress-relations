<?php
/**
 * Class Wiring
 *
 * Singleton that performs dynamic discovery and wiring at init:100.
 *
 * HOW it works:
 * ─────────────
 * 1. Uses `get_post_types_by_support( 'gatherpress-relations' )` from
 *    WP core to discover every post type that has opted into the
 *    relations system. No post type slug is hard-coded in this class.
 *
 * 2. For each discovered consumer post type:
 *    a. Registers `_gatherpress_relations` post meta (JSON string,
 *       single, shown in REST, auth-gated).
 *
 * 3. Calls `register_taxonomy_for_object_type()` to wire the
 *    `_gatherpress_person` shadow taxonomy onto all discovered
 *    consumer post types.
 *
 * WHY init:100?
 * At priority 100 every plugin and theme has registered their CPTs
 * (typically at priority 10) and the Bridge class has added support
 * flags (at priority 50). Scanning at 100 is a safe belt-and-suspenders
 * approach that guarantees complete discovery.
 *
 * WHY get_post_types_by_support()?
 * This WP core function (since 4.7) reads from the global
 * `$wp_post_types` registry and returns an array of post type slugs
 * declaring the given support. It is the canonical, fastest way to
 * discover supporting CPTs.
 *
 * Signature: get_post_types_by_support( array|string $feature, string $operator = 'and' ): string[]
 *
 * @since   0.1.0
 * @package GatherPressRelations
 */

namespace GatherPressRelations;

use GatherPress\Core\Traits\Singleton;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

class Wiring {
	use Singleton;

	/**
	 * The post meta key for junction metadata.
	 *
	 * WHY a constant?
	 * Ensures the same key is used everywhere — registration,
	 * render callbacks, REST schema, editor JS.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const META_KEY = '_gatherpress_relations';

	/**
	 * The post type support string that consumer post types declare.
	 *
	 * Consumer ("from") post types are the ones that carry the
	 * `_gatherpress_relations` junction meta and tag themselves with
	 * shadow taxonomy terms from source post types.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const SUPPORT_KEY_FROM = 'gatherpress-relations-from';

	/**
	 * The post type support string that connectable source post types declare.
	 *
	 * Source ("to") post types are the ones whose shadow taxonomies
	 * get wired onto consumer post types. Each source must also
	 * declare `gatherpress-shadow-source` so a hidden taxonomy
	 * `_<post_type>` exists.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const SUPPORT_KEY_TO = 'gatherpress-relations-to';

	/**
	 * Discovered consumer post type slugs.
	 *
	 * Populated once during `wire()` and available to other methods
	 * and external code via `get_consumers()`.
	 *
	 * @since 0.1.0
	 * @var string[]
	 */
	private array $consumers = array();

	/**
	 * Discovered source post type slugs.
	 *
	 * Populated once during `wire()` and available to other methods
	 * and external code via `get_sources()`.
	 *
	 * @since 0.1.0
	 * @var string[]
	 */
	private array $sources = array();

	/**
	 * Constructor for the Wiring class.
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
	 * Hooks the wiring onto `init:100`.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'wire' ), 100 );
	}

	/**
	 * Discovers consumer and source post types, then wires meta and
	 * shadow taxonomies.
	 *
	 * HOW this method executes:
	 * 1. Discovers consumer post types via `gatherpress-relations-from`.
	 * 2. Discovers source post types via `gatherpress-relations-to`.
	 * 3. Registers `_gatherpress_relations` meta on each consumer.
	 * 4. For each source type, wires its shadow taxonomy onto all consumers
	 *    via `register_taxonomy_for_object_type()`.
	 *
	 * PAYLOAD (example after discovery):
	 * ```php
	 * $consumers = [ 'gatherpress_play', 'gatherpress_event' ];
	 * $sources   = [ 'gatherpress_person' ];
	 * ```
	 *
	 * WHY discover sources dynamically?
	 * Any plugin can register a post type with `gatherpress-relations-to`
	 * support (e.g., `gatherpress_sponsor`, `gatherpress_venue`) and its
	 * shadow taxonomy will be automatically wired onto all consumers.
	 * No post type slug is hard-coded in the wiring layer.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function wire(): void {
		$this->consumers = get_post_types_by_support( self::SUPPORT_KEY_FROM );
		$this->sources   = get_post_types_by_support( self::SUPPORT_KEY_TO );

		if ( empty( $this->consumers ) ) {
			return;
		}

		$this->register_meta_for_consumers();
		$this->wire_shadow_taxonomies();
	}

	/**
	 * Returns the discovered consumer post type slugs.
	 *
	 * WHY public?
	 * Other classes (e.g., REST endpoints, block render callbacks)
	 * may need the list of consumer types at runtime.
	 *
	 * @since  0.1.0
	 * @return string[] Array of post type slugs with gatherpress-relations-from support.
	 */
	public function get_consumers(): array {
		return $this->consumers;
	}

	/**
	 * Returns the discovered source post type slugs.
	 *
	 * WHY public?
	 * The editor JS needs to know which post types are connectable
	 * sources so it can offer them in search dropdowns and resolve
	 * their shadow taxonomy names dynamically.
	 *
	 * @since  0.1.0
	 * @return string[] Array of post type slugs with gatherpress-relations-to support.
	 */
	public function get_sources(): array {
		return $this->sources;
	}

	/**
	 * Registers _gatherpress_relations post meta on each consumer.
	 *
	 * HOW the meta is structured:
	 * A JSON-encoded array of person-role assignments. Each entry:
	 * ```json
	 * {
	 *   "slug": "_jane-doe",
	 *   "id": 15,
	 *   "type": "gatherpress_person",
	 *   "role": "Hamlet",
	 *   "department": "cast",
	 *   "order": 1
	 * }
	 * ```
	 *
	 * WHY 'string' type with JSON?
	 * WordPress post meta does not natively support complex arrays
	 * in REST. Using a JSON string with `show_in_rest => true` and
	 * `single => true` gives us a clean REST payload that the editor
	 * can read/write via `useEntityProp`.
	 *
	 * WHY auth_callback?
	 * Gates meta writing to users who can edit posts. Without this,
	 * the REST API would reject meta updates.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	private function register_meta_for_consumers(): void {
		foreach ( $this->consumers as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'description'   => __( 'JSON array of {slug, id, type, role, department, order} entries for the gatherpress-relations system.', 'gatherpress-relations' ),
					'auth_callback' => function (): bool {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Wires shadow taxonomies from ALL source types onto ALL consumers.
	 *
	 * HOW it works:
	 * ─────────────
	 * Iterates every discovered source post type (those with
	 * `gatherpress-relations-to` support), derives its shadow
	 * taxonomy slug (`_<source_post_type>`), and calls
	 * `register_taxonomy_for_object_type()` for each consumer.
	 *
	 * WHY iterate sources dynamically?
	 * Any plugin can register a post type with both
	 * `gatherpress-shadow-source` and `gatherpress-relations-to`
	 * support. Its shadow taxonomy is then automatically wired
	 * onto all consumers — no hard-coded slug needed.
	 *
	 * EXAMPLE:
	 * If `gatherpress_person` and `gatherpress_sponsor` both declare
	 * `gatherpress-relations-to`, the resulting wiring is:
	 * ```php
	 * register_taxonomy_for_object_type( '_gatherpress_person',  'gatherpress_event' );
	 * register_taxonomy_for_object_type( '_gatherpress_person',  'gatherpress_play' );
	 * register_taxonomy_for_object_type( '_gatherpress_sponsor', 'gatherpress_event' );
	 * register_taxonomy_for_object_type( '_gatherpress_sponsor', 'gatherpress_play' );
	 * ```
	 *
	 * @since  0.1.0
	 * @return void
	 */
	private function wire_shadow_taxonomies(): void {
		foreach ( $this->sources as $source_type ) {
			$shadow_taxonomy = '_' . $source_type;

			/*
			 * Guard: if the shadow taxonomy does not exist, skip.
			 *
			 * WHY this guard?
			 * The source type may declare `gatherpress-relations-to`
			 * without `gatherpress-shadow-source` (a misconfiguration),
			 * or GatherPress core may not be active. In either case,
			 * the shadow taxonomy won't exist.
			 */
			if ( ! taxonomy_exists( $shadow_taxonomy ) ) {
				continue;
			}

			foreach ( $this->consumers as $consumer_type ) {
				register_taxonomy_for_object_type( $shadow_taxonomy, $consumer_type );
			}
		}
	}
}
