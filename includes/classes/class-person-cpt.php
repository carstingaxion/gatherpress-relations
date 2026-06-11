<?php
/**
 * Class Person_CPT
 *
 * Singleton that registers the gatherpress_person post type.
 *
 * HOW it works:
 * ─────────────
 * On `init` (default priority 10), registers gatherpress_person with:
 * - `gatherpress-shadow-source` support: auto-creates a hidden
 *   `_gatherpress_person` taxonomy whose terms mirror published
 *   person posts (e.g., post "jane-doe" → term "_jane-doe").
 * - Standard supports: title, editor, thumbnail, excerpt, custom-fields.
 * - REST API enabled.
 *
 * WHY a dedicated class?
 * Isolates CPT registration from wiring logic. If the person CPT
 * needs additional customisation (e.g., custom capabilities, extra
 * labels), it happens here without touching the relationship layer.
 *
 * WHY this is the ONLY CPT we register:
 * The production post type (`gatherpress_play`) is owned by the
 * `gatherpress-productions` plugin. We consume it via
 * `add_post_type_support()` in the Bridge class — never via
 * `register_post_type()`.
 *
 * @since   0.1.0
 * @package GatherPressRelations
 */

namespace GatherPressRelations;

use GatherPress\Core\Traits\Singleton;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

class Person_CPT {
	use Singleton;

	/**
	 * The post type slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const POST_TYPE = 'gatherpress_person';

	/**
	 * Constructor for the Person_CPT class.
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
	 * Hooks the CPT registration onto `init`.
	 *
	 * WHY default priority (10)?
	 * The CPT must exist before:
	 * - init:50 where the Bridge adds support flags to external CPTs.
	 * - init:100 where the Wiring class discovers consumers.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the gatherpress_person post type.
	 *
	 * PAYLOAD (abbreviated register_post_type args):
	 * ```php
	 * [
	 *     'label'        => 'People',
	 *     'public'       => true,
	 *     'show_in_rest' => true,
	 *     'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt',
	 *                         'custom-fields', 'gatherpress-shadow-source' ],
	 * ]
	 * ```
	 *
	 * WHY 'gatherpress-shadow-source' in supports?
	 * This triggers GatherPress core's Shadow_Source lifecycle:
	 * - Registers `_gatherpress_person` hidden taxonomy.
	 * - Creates a shadow term per published person post.
	 * - Keeps term slug/name in sync with post slug/title.
	 * - Removes term when post is deleted.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register(): void {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		add_filter( 'gatherpress_shadow_taxonomy_args', function ( array $args, string $post_type ): array {
			if ( self::POST_TYPE === $post_type ) {
				$args['show_in_quick_edit'] = false;
			}
			return $args;
		}, 10, 2 );

		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'People', 'gatherpress-relations' ),
				'labels'       => array(
					'name'               => __( 'People', 'gatherpress-relations' ),
					'singular_name'      => __( 'Person', 'gatherpress-relations' ),
					'add_new_item'       => __( 'Add New Person', 'gatherpress-relations' ),
					'edit_item'          => __( 'Edit Person', 'gatherpress-relations' ),
					'new_item'           => __( 'New Person', 'gatherpress-relations' ),
					'view_item'          => __( 'View Person', 'gatherpress-relations' ),
					'search_items'       => __( 'Search People', 'gatherpress-relations' ),
					'not_found'          => __( 'No people found', 'gatherpress-relations' ),
					'not_found_in_trash' => __( 'No people found in Trash', 'gatherpress-relations' ),
					'all_items'          => __( 'All People', 'gatherpress-relations' ),
					'archives'           => __( 'People Archives', 'gatherpress-relations' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array(
					'title',
					'editor',
					'thumbnail',
					'excerpt',
					'custom-fields',
					'gatherpress-shadow-source',
				),
				'rewrite'      => array( 'slug' => 'person' ),
			)
		);

		/*
		 * Declare that gatherpress_person is a connectable "source"
		 * post type. The Wiring class at init:100 discovers all post
		 * types with this support and wires their shadow taxonomies
		 * onto every consumer post type.
		 *
		 * WHY a separate add_post_type_support call?
		 * `gatherpress-relations-to` is our own support string, not
		 * a core WordPress one. Declaring it explicitly after CPT
		 * registration makes the intent clear and keeps the supports
		 * array in register_post_type focused on core/GatherPress
		 * primitives.
		 */
		add_post_type_support( self::POST_TYPE, 'gatherpress-relations-to' );
	}
}
