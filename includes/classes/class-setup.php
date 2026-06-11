<?php
/**
 * Class Setup
 *
 * Singleton entry-point for the GatherPress Relations plugin.
 *
 * HOW it works:
 * ─────────────
 * 1. `get_instance()` is called from the bootstrap function in
 *    `gatherpress-relations.php` on `plugins_loaded`.
 * 2. The constructor initialises all sub-systems (Dependency, Person CPT,
 *    Bridge, Wiring) and hooks block registration onto `init`.
 * 3. Each sub-system's Singleton is instantiated, which triggers its
 *    own `setup_hooks()` to register the appropriate `init` callbacks.
 * 4. GatherPress core is a hard dependency (`Requires plugins: gatherpress`),
 *    so WordPress prevents activation without it — no admin notice needed.
 *
 * @since   0.1.0
 * @package GatherPressRelations
 */

namespace GatherPressRelations;

use GatherPress\Core\Traits\Singleton;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

class Setup {
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * Initializes and sets up various components of the plugin.
	 * Follows the GatherPress core pattern where each Singleton's
	 * constructor calls `setup_hooks()` to register actions/filters.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Initialises all plugin sub-systems and hooks.
	 *
	 * WHY are sub-systems instantiated here?
	 * Each `get_instance()` call triggers the sub-system's
	 * constructor → `setup_hooks()`, registering its `init` callbacks.
	 * The priority ordering ensures correct execution:
	 * - init:10 — Person CPT registration + block registration.
	 * - init:50 — Bridge adds support to external CPTs.
	 * - init:100 — Wiring discovers consumers and connects everything.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Register the gatherpress_person post type at init:10.
		Person_CPT::get_instance();

		// Bridge external CPTs at init:50.
		Bridge::get_instance();

		// Wire shadow taxonomy + meta at init:100.
		Wiring::get_instance();

		// Register blocks at init:10.
		add_action( 'init', array( $this, 'register_block' ) );

		// Localise the filtered department config for the editor JS.
		add_action( 'enqueue_block_editor_assets', array( $this, 'localize_department_config' ) );

		// Enqueue sidebar styles in the parent document (outside the iframe).
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_sidebar_styles' ) );
	}

	/**
	 * Registers all plugin blocks from the compiled build directory.
	 *
	 * HOW this works:
	 * ────────────────
	 * Each block under `build/blocks/<slug>/` contains a `block.json`
	 * that `register_block_type()` reads to discover the block name,
	 * attributes, scripts, styles, and the `render` callback.
	 *
	 * WHY register all blocks here?
	 * All block registrations are centralised in the Setup singleton
	 * to ensure a single, predictable init:10 callback. Adding a new
	 * block requires only one line here and a webpack entry.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_block(): void {
		$plugin_dir = GATHERPRESS_RELATIONS_CORE_PATH;

		register_block_type( $plugin_dir . '/build/blocks/roadmap/' );
		register_block_type( $plugin_dir . '/build/blocks/cast-crew-list/' );
		register_block_type( $plugin_dir . '/build/blocks/recent-roles/' );

		$this->register_cast_crew_block_styles();
	}

	/**
	 * Registers additional block styles for the Cast & Crew List block.
	 *
	 * HOW this works:
	 * ────────────────
	 * Uses `register_block_style()` from WP core to add named style
	 * variations to the Cast & Crew List block. Each style adds a CSS
	 * class (`is-style-<slug>`) to the block wrapper.
	 *
	 * STYLES REGISTERED:
	 * - `simple-list` (default) — minimal vertical list.
	 * - `compact-table` — dense, tabular layout.
	 * - `cards` — visual card grid with shadows and hover elevation.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	private function register_cast_crew_block_styles(): void {
		register_block_style(
			'gatherpress/cast-crew-list',
			array(
				'name'       => 'simple-list',
				'label'      => __( 'Simple List', 'gatherpress-relations' ),
				'is_default' => true,
			)
		);

		register_block_style(
			'gatherpress/cast-crew-list',
			array(
				'name'  => 'compact-table',
				'label' => __( 'Compact Table', 'gatherpress-relations' ),
			)
		);

		register_block_style(
			'gatherpress/cast-crew-list',
			array(
				'name'  => 'cards',
				'label' => __( 'Cards', 'gatherpress-relations' ),
			)
		);
	}

	/**
	 * Enqueues sidebar-specific styles for the Cast & Crew List block.
	 *
	 * HOW this works:
	 * ────────────────
	 * Since WordPress 6.0+ the block editor canvas runs inside an
	 * iframe. Styles declared via block.json's `editorStyle` are
	 * injected into the iframe and cannot reach the InspectorControls
	 * sidebar, which lives in the parent document. This method
	 * enqueues a dedicated stylesheet in the parent document so
	 * sidebar UI styles render correctly.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function enqueue_sidebar_styles(): void {
		$css = $this->get_sidebar_css();
		wp_register_style(
			'gatherpress-cast-crew-sidebar',
			false,
			array(),
			GATHERPRESS_RELATIONS_VERSION
		);
		wp_enqueue_style( 'gatherpress-cast-crew-sidebar' );
		wp_add_inline_style( 'gatherpress-cast-crew-sidebar', $css );
	}

	/**
	 * Returns the CSS for the Cast & Crew sidebar panel.
	 *
	 * WHY inline instead of a separate file?
	 * The sidebar styles are small (~2 KB) and only loaded in the
	 * editor context. Inline avoids an extra HTTP request.
	 *
	 * @since  0.1.0
	 * @return string The sidebar CSS.
	 */
	private function get_sidebar_css(): string {
		return '
/* Cast & Crew sidebar — search results dropdown */
.cast-crew-editor__search-results {
	list-style: none;
	margin: 0.5rem 0;
	padding: 0;
	max-height: 160px;
	overflow-y: auto;
	border: 1px solid #e0e0e0;
	border-radius: 4px;
	background: #fff;
}
.cast-crew-editor__search-results li {
	padding: 0;
	margin: 0;
}
.cast-crew-editor__search-results li .components-button {
	width: 100%;
	justify-content: flex-start;
	border-radius: 0;
	padding: 6px 12px;
	font-size: 13px;
	height: auto;
	min-height: 36px;
}
.cast-crew-editor__search-results li .components-button:hover {
	background: #f0f0f0;
}

/* Add section spacing */
.cast-crew-editor__add-section {
	margin-bottom: 12px;
	padding-bottom: 12px;
	border-bottom: 1px solid #e0e0e0;
}

/* Entry list title with count badge */
.cast-crew-editor__entries-title {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 11px;
	font-weight: 500;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	margin: 0 0 8px;
	color: #757575;
}
.cast-crew-editor__entries-count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 18px;
	height: 18px;
	padding: 0 5px;
	border-radius: 9px;
	background: #1e1e1e;
	color: #fff;
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0;
	text-transform: none;
}

/* Entry list container */
.cast-crew-editor__entries {
	display: flex;
	flex-direction: column;
	gap: 1px;
	background: #e0e0e0;
	border: 1px solid #e0e0e0;
	border-radius: 4px;
	overflow: hidden;
}

/* Individual entry card */
.cast-crew-editor__entry {
	background: #fff;
	margin: 0;
	transition: opacity 0.15s ease;
	border: none;
	border-radius: 0;
}
.cast-crew-editor__entry--dragging {
	opacity: 0.3;
}
.cast-crew-editor__entry--drop-above {
	box-shadow: inset 0 2px 0 0 var(--wp-admin-theme-color, #3858e9);
}

/* Entry row layout */
.cast-crew-editor__entry-row {
	display: grid;
	grid-template-columns: 28px 1fr auto;
	align-items: center;
	gap: 0;
	padding: 0;
	min-height: 40px;
}

/* Drag handle */
.cast-crew-editor__drag-handle {
	display: flex;
	align-items: center;
	justify-content: center;
	align-self: stretch;
	cursor: grab;
	color: #c0c0c0;
	transition: color 0.1s ease, background 0.1s ease;
	padding: 0;
}
.cast-crew-editor__drag-handle:hover {
	color: #1e1e1e;
	background: #f0f0f0;
}
.cast-crew-editor__drag-handle:active {
	cursor: grabbing;
}
.cast-crew-editor__drag-handle svg {
	fill: currentColor;
	width: 18px;
	height: 18px;
}

/* Entry info */
.cast-crew-editor__entry-info {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 0;
	padding: 6px 4px 6px 0;
	min-width: 0;
	line-height: 1.3;
}
.cast-crew-editor__entry-name {
	font-size: 13px;
	font-weight: 600;
	color: #1e1e1e;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.cast-crew-editor__entry-detail {
	font-size: 11px;
	color: #757575;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.cast-crew-editor__entry-detail em {
	font-style: italic;
}

/* Entry action buttons */
.cast-crew-editor__entry-actions {
	display: flex;
	align-items: center;
	gap: 0;
	padding-right: 4px;
}
.cast-crew-editor__entry-actions .components-button {
	min-width: 0;
	width: 24px;
	height: 24px;
	padding: 0;
	color: #757575;
}
.cast-crew-editor__entry-actions .components-button:hover {
	color: #1e1e1e;
}
.cast-crew-editor__entry-actions .components-button.is-destructive {
	color: #c0c0c0;
}
.cast-crew-editor__entry-actions .components-button.is-destructive:hover {
	color: #cc1818;
}
.cast-crew-editor__entry-actions .components-button.is-pressed {
	background: #1e1e1e;
	color: #fff;
	border-radius: 2px;
}
.cast-crew-editor__entry-actions .components-button svg {
	width: 20px;
	height: 20px;
}

/* Inline edit panel */
.cast-crew-editor__entry-edit {
	padding: 8px 8px 10px 28px;
	border-top: 1px solid #f0f0f0;
	background: #fafafa;
}
.cast-crew-editor__entry-edit .components-base-control {
	margin-bottom: 8px;
}
.cast-crew-editor__entry-edit .components-base-control:last-child {
	margin-bottom: 0;
}
';
	}

	/**
	 * Localises the filtered department configuration for the
	 * Cast & Crew sidebar plugin and all relation blocks.
	 *
	 * HOW this works:
	 * ────────────────
	 * Hooks into `enqueue_block_editor_assets` and enqueues the
	 * sidebar plugin script, then calls `wp_localize_script()` on it.
	 * The department config is available to all blocks on the page
	 * via the global `gatherPressRelationsConfig` object.
	 *
	 * PAYLOAD localised:
	 * ```js
	 * window.gatherPressRelationsConfig = {
	 *   departments: [
	 *     { value: 'cast', label: 'Cast' },
	 *     { value: 'direction', label: 'Direction' },
	 *     // ... filtered via gatherpress_relations_departments
	 *   ]
	 * };
	 * ```
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function localize_department_config(): void {
		$plugin_dir = GATHERPRESS_RELATIONS_CORE_PATH;
		$plugin_url = plugins_url( '', GATHERPRESS_RELATIONS_CORE_FILE );

		$asset_file = $plugin_dir . '/build/plugins/cast-crew-sidebar/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => GATHERPRESS_RELATIONS_VERSION,
			);

		wp_enqueue_script(
			'gatherpress-relations-cast-crew-sidebar',
			$plugin_url . '/build/plugins/cast-crew-sidebar/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		/**
		 * Filters the department configuration for the Cast & Crew block.
		 *
		 * This is the same filter used in the server-side renderers,
		 * ensuring editor and frontend stay in sync.
		 *
		 * @since 0.1.0
		 * @param array<string, string> $departments Slug => label pairs.
		 */
		/**
		 * Default departments — used as the base for all post types
		 * and as the global fallback when no per-type override exists.
		 *
		 * @var array<string, string>
		 */
		$default_departments = array(
			'speaker'    => __( 'Speaker', 'gatherpress-relations' ),
			'moderation' => __( 'Moderation', 'gatherpress-relations' ),
			'host'       => __( 'Host', 'gatherpress-relations' ),
		);

		/**
		 * Filters the department configuration for the Cast & Crew block.
		 *
		 * The second parameter `$post_type` is an empty string when
		 * called for the global/default context. When called for a
		 * specific consumer post type, it contains the post type slug.
		 *
		 * EXAMPLE — different departments for sponsors vs persons:
		 * ```php
		 * add_filter( 'gatherpress_relations_departments', function ( array $departments, string $source_type ): array {
		 *     if ( 'gatherpress_sponsor' === $source_type ) {
		 *         return [
		 *             'gold'   => __( 'Gold', 'my-plugin' ),
		 *             'silver' => __( 'Silver', 'my-plugin' ),
		 *             'bronze' => __( 'Bronze', 'my-plugin' ),
		 *         ];
		 *     }
		 *     return $departments;
		 * }, 10, 2 );
		 * ```
		 *
		 * @since 0.1.0
		 * @param array<string, string> $departments Slug => label pairs.
		 * @param string                $source_type The source post type slug (e.g., 'gatherpress_person'), or '' for the global default.
		 */
		$departments = apply_filters( 'gatherpress_relations_departments', $default_departments, '' );

		$options = array();
		foreach ( $departments as $slug => $label ) {
			$options[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}

		/*
		 * Build a per-source-type department map.
		 *
		 * HOW this works:
		 * For each source post type that supports gatherpress-relations-to,
		 * we call the filter with the source type slug. If the filtered
		 * result differs from the global default, we include it in the map.
		 * Otherwise we omit it — the JS side falls back to the global
		 * departments.
		 *
		 * WHY per-source-type (not per-consumer)?
		 * Departments describe the connectable entity, not the consumer.
		 * A `gatherpress_person` always uses Speaker/Moderation/Host (or
		 * Cast/Direction/Design for a theater context). A
		 * `gatherpress_sponsor` always uses Gold/Silver/Bronze. This
		 * holds regardless of whether the consumer is an event, a
		 * production, or any other post type.
		 */
		$source_slugs_for_depts    = get_post_types_by_support( Wiring::SUPPORT_KEY_TO );
		$departments_by_post_type  = array();

		foreach ( $source_slugs_for_depts as $source_slug_dept ) {
			$type_departments = apply_filters( 'gatherpress_relations_departments', $default_departments, $source_slug_dept );

			// Only include in the map if it differs from the global default.
			if ( $type_departments !== $departments ) {
				$type_options = array();
				foreach ( $type_departments as $slug => $label ) {
					$type_options[] = array(
						'value' => $slug,
						'label' => $label,
					);
				}
				$departments_by_post_type[ $source_slug_dept ] = $type_options;
			}
		}

		/*
		 * Discover all source post types (those with
		 * gatherpress-relations-to support) and pass them to the
		 * editor JS so it can build search controls, resolve shadow
		 * taxonomy names, and handle term sync for any source type
		 * — not just gatherpress_person.
		 *
		 * PAYLOAD example:
		 * ```js
		 * window.gatherPressRelationsConfig.sourceTypes = [
		 *   { slug: 'gatherpress_person', shadowTaxonomy: '_gatherpress_person',
		 *     label: 'People', singularLabel: 'Person' }
		 * ]
		 * ```
		 */
		$source_slugs = get_post_types_by_support( Wiring::SUPPORT_KEY_TO );
		$source_types = array();
		foreach ( $source_slugs as $source_slug ) {
			$pt_obj = get_post_type_object( $source_slug );
			if ( ! $pt_obj ) {
				continue;
			}
			$source_types[] = array(
				'slug'            => $source_slug,
				'shadowTaxonomy'  => '_' . $source_slug,
				'label'           => $pt_obj->labels->name ?? $source_slug,
				'singularLabel'   => $pt_obj->labels->singular_name ?? $source_slug,
			);
		}

		wp_localize_script(
			'gatherpress-relations-cast-crew-sidebar',
			'gatherPressRelationsConfig',
			array(
				'departments'          => $options,
				'departmentsByPostType' => $departments_by_post_type,
				'sourceTypes'          => $source_types,
			)
		);
	}


}
