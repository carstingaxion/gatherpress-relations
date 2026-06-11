<?php
/**
 * Server-side render callback for the GatherPress Relations Roadmap block.
 *
 * HOW this file is invoked:
 * ─────────────────────────
 * block.json declares `"render": "file:./render.php"`. When WordPress
 * encounters the block delimiter in post_content, it `require`s this
 * file, making `$attributes`, `$content`, and `$block` available in
 * the local scope.
 *
 * WHY a Singleton renderer class?
 * The roadmap contains ~6 large sections with structured content
 * (HTML, code samples, tables, diagrams). Organising this as methods
 * on a class:
 * 1. Keeps the global scope clean.
 * 2. Allows each section to be toggled by checking `$attributes`.
 * 3. Makes the rendering logic testable in isolation.
 * 4. Uses the Singleton pattern so the class is instantiated exactly
 *    once even if the block appears multiple times on a page.
 *
 * AVAILABLE VARIABLES (provided by WordPress):
 * ```
 * $attributes = [
 *     'showDataModeling'    => bool,
 *     'showCastCrew'        => bool,
 *     'showEnsemble'        => bool,
 *     'showVisualizations'  => bool,
 *     'showScaling'         => bool,
 *     'showImplementation'  => bool,
 * ];
 * $content = '';            // Empty for dynamic blocks.
 * $block   = WP_Block;     // The parsed block instance.
 * ```
 *
 * ARCHITECTURAL CONTEXT — FIXED DECISIONS:
 * ────────────────────────────────────────
 * 1. Both "people" and "productions" are POST TYPES, not taxonomies.
 * 2. The production post type (`gatherpress_play`) is registered by
 *    gatherpress-productions plugin — we consume it, never register it.
 * 3. We register a new `gatherpress_person` post type with
 *    `gatherpress-shadow-source` support, which auto-creates a
 *    `_gatherpress_person` shadow taxonomy.
 * 4. All new functionality is gated behind the `gatherpress-relations`
 *    post_type_supports string. Nothing is hard-bound to any post type
 *    slug. Any CPT can opt in by declaring the support.
 * 5. Consumer post types tag themselves with shadow taxonomy terms to
 *    model relationships — same mechanism as venue ↔ event tagging.
 * 6. Roles are relationship metadata stored as structured post meta
 *    (`_gatherpress_relations`) on the consumer post.
 * 7. The pattern is agnostic — it works for any "special post type →
 *    person" connection: theater cast, event organizers, speakers, etc.
 * 8. Discovery of supporting post types uses WP core's
 *    `get_post_types_by_support( 'gatherpress-relations' )`.
 * 9. All wiring runs on `init` at priority 100 to ensure every CPT
 *    is already registered.
 *
 * @package GatherPressRelations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GatherPress_Relations_Renderer' ) ) {

	/**
	 * Class GatherPress_Relations_Renderer
	 *
	 * Singleton renderer that produces the complete roadmap HTML.
	 *
	 * WHY Singleton?
	 * If multiple roadmap blocks exist on the same page, each
	 * `require` of render.php would try to re-declare the class.
	 * The `class_exists` guard above prevents that. Internally,
	 * `get_instance()` ensures only one renderer object exists,
	 * avoiding redundant data structures in memory.
	 *
	 * @since 0.1.0
	 */
	class GatherPress_Relations_Renderer {

		/**
		 * Singleton instance.
		 *
		 * @since 0.1.0
		 * @var self|null
		 */
		private static ?self $instance = null;

		/**
		 * Returns the single renderer instance.
		 *
		 * @since  0.1.0
		 * @return self
		 */
		public static function get_instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor — enforces Singleton access.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {}

		/**
		 * Renders the complete roadmap block HTML.
		 *
		 * HOW the output is structured:
		 * ```
		 * <div {block-wrapper-attributes}>
		 *   <div class="gatherpress-relations">
		 *     <nav class="__toc">         <!-- Table of Contents -->
		 *     <div class="__content">     <!-- Scrollable sections -->
		 *       <header class="__header"> <!-- Title + subtitle -->
		 *       <div class="__section">   <!-- One per toggled section -->
		 *     </div>
		 *   </div>
		 * </div>
		 * ```
		 *
		 * @since  0.1.0
		 * @param  array<string, mixed> $attributes Block attributes from the editor.
		 * @return string Complete HTML for the roadmap.
		 */
		public function render( array $attributes ): string {
			$sections = $this->get_sections( $attributes );

			ob_start();
			?>
			<div <?php echo get_block_wrapper_attributes(); ?>>
				<div class="gatherpress-relations">
					<?php echo $this->render_toc( $sections ); ?>
					<div class="gatherpress-relations__content">
						<?php echo $this->render_header(); ?>
						<?php
						foreach ( $sections as $index => $section ) {
							echo $this->render_section( $section, $index + 1 );
						}
						?>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Builds the list of sections that should be rendered.
		 *
		 * HOW it works:
		 * Each section is an associative array with an `id`, `title`,
		 * and `content_method` (the name of the method that generates
		 * the section body HTML). Sections are included only if their
		 * corresponding attribute is truthy.
		 *
		 * @since  0.1.0
		 * @param  array<string, mixed> $attributes Block attributes.
		 * @return array<int, array{id: string, title: string, content_method: string}> Enabled sections.
		 */
		private function get_sections( array $attributes ): array {
			$all_sections = array(
				array(
					'attr_key'       => 'showDataModeling',
					'id'             => 'gpr-section-1',
					'title'          => __( 'Data Modeling & Architecture', 'gatherpress-relations' ),
					'content_method' => 'render_data_modeling',
				),
				array(
					'attr_key'       => 'showCastCrew',
					'id'             => 'gpr-section-2',
					'title'          => __( 'Cast & Crew List Block', 'gatherpress-relations' ),
					'content_method' => 'render_cast_crew',
				),
				array(
					'attr_key'       => 'showEnsemble',
					'id'             => 'gpr-section-3',
					'title'          => __( 'People / Ensemble Blocks', 'gatherpress-relations' ),
					'content_method' => 'render_ensemble',
				),
				array(
					'attr_key'       => 'showVisualizations',
					'id'             => 'gpr-section-4',
					'title'          => __( 'Smart Data-Relation Visualizations', 'gatherpress-relations' ),
					'content_method' => 'render_visualizations',
				),
				array(
					'attr_key'       => 'showScaling',
					'id'             => 'gpr-section-5',
					'title'          => __( 'Scaling & Performance', 'gatherpress-relations' ),
					'content_method' => 'render_scaling',
				),
				array(
					'attr_key'       => 'showImplementation',
					'id'             => 'gpr-section-6',
					'title'          => __( 'Implementation Roadmap', 'gatherpress-relations' ),
					'content_method' => 'render_implementation',
				),
			);

			return array_values(
				array_filter(
					$all_sections,
					function ( array $section ) use ( $attributes ): bool {
						return ! empty( $attributes[ $section['attr_key'] ] );
					}
				)
			);
		}

		/**
		 * Renders the roadmap header with title and subtitle.
		 *
		 * @since  0.1.0
		 * @return string Header HTML.
		 */
		private function render_header(): string {
			ob_start();
			?>
			<header class="gatherpress-relations__header">
				<h2 class="gatherpress-relations__main-title">
					<?php esc_html_e( 'Shadow Taxonomy Relations Roadmap', 'gatherpress-relations' ); ?>
				</h2>
				<p class="gatherpress-relations__subtitle">
					<?php esc_html_e( 'Architectural guide for connecting persons to productions (or any special post type) with roles in WordPress — powered by GatherPress shadow taxonomies, close to core, best editor experience, maximum flexibility, long-term scalability.', 'gatherpress-relations' ); ?>
				</p>
			</header>
			<?php
			return ob_get_clean();
		}

		/**
		 * Renders the sticky table of contents sidebar.
		 *
		 * @since  0.1.0
		 * @param  array<int, array{id: string, title: string}> $sections Enabled sections.
		 * @return string TOC HTML.
		 */
		private function render_toc( array $sections ): string {
			ob_start();
			?>
			<nav class="gatherpress-relations__toc" aria-label="<?php esc_attr_e( 'Roadmap table of contents', 'gatherpress-relations' ); ?>">
				<h3 class="gatherpress-relations__toc-title">
					<?php esc_html_e( 'Contents', 'gatherpress-relations' ); ?>
				</h3>
				<ul class="gatherpress-relations__toc-list">
					<?php foreach ( $sections as $section ) { ?>
						<li class="gatherpress-relations__toc-item">
							<a class="gatherpress-relations__toc-link" href="#<?php echo esc_attr( $section['id'] ); ?>">
								<?php echo esc_html( $section['title'] ); ?>
							</a>
						</li>
					<?php } ?>
				</ul>
			</nav>
			<?php
			return ob_get_clean();
		}

		/**
		 * Renders a single collapsible section.
		 *
		 * HOW collapsing works:
		 * view.js toggles `--collapsed` on the section wrapper and
		 * flips `aria-expanded` on the button. CSS handles the
		 * max-height/opacity transition.
		 *
		 * WHY a <button> for the header?
		 * Buttons are natively keyboard-focusable and activatable
		 * with Enter/Space, making the section toggle fully accessible
		 * without manual tabindex or keydown handling.
		 *
		 * @since  0.1.0
		 * @param  array{id: string, title: string, content_method: string} $section Section data.
		 * @param  int                                                      $number  1-based section index.
		 * @return string Section HTML.
		 */
		private function render_section( array $section, int $number ): string {
			$body_id = str_replace( 'section', 'body', $section['id'] );
			$content = $this->{$section['content_method']}();

			ob_start();
			?>
			<div class="gatherpress-relations__section" id="<?php echo esc_attr( $section['id'] ); ?>">
				<button
					class="gatherpress-relations__section-header"
					aria-expanded="true"
					aria-controls="<?php echo esc_attr( $body_id ); ?>"
					type="button"
				>
					<span class="gatherpress-relations__section-number"><?php echo esc_html( (string) $number ); ?></span>
					<h3 class="gatherpress-relations__section-title"><?php echo esc_html( $section['title'] ); ?></h3>
					<span class="gatherpress-relations__section-chevron" aria-hidden="true">&#9662;</span>
				</button>
				<div class="gatherpress-relations__section-body" id="<?php echo esc_attr( $body_id ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is escaped in individual render methods.
					echo $content;
					?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Section 1: Data Modeling & Architecture.
		 *
		 * CONTENT SUMMARY:
		 * - Both people and productions are post types with shadow-source support
		 * - Shadow taxonomies as the relationship primitive
		 * - Roles as relationship metadata (post meta on the consumer)
		 * - Agnostic pattern: works for any post type combination
		 * - Comparison table: shadow taxonomy vs manual taxonomy vs users
		 * - Schema diagrams showing the shadow taxonomy flow
		 *
		 * @since  0.1.0
		 * @return string Section body HTML.
		 */
		private function render_data_modeling(): string {
			ob_start();
			?>
			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'The Core Question', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'How do you connect "persons" (actors, technicians, directors, speakers, organizers) to "special posts" (productions, events, sessions) with "roles" (Romeo, Light Technician, Keynote Speaker) — in a way that feels native to WordPress, provides the best editor experience, and scales for years?', 'gatherpress-relations' ); ?>
			</p>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--key">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Fixed Architectural Decisions', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'People and productions are BOTH post types with gatherpress-shadow-source support. The production CPT (gatherpress_play) is provided by the gatherpress-productions plugin — we consume it, never register it. We register a new gatherpress_person post type. All new functionality (cast/crew assignments, ensemble features) is wrapped in the gatherpress-relations post_type_supports string — nothing is hard-bound to any post type slug. Discovery of supporting post types uses WP core\'s get_post_types_by_support( "gatherpress-relations" ). Consumer post types tag themselves with shadow taxonomy terms to model relationships. Roles are relationship metadata stored as structured post meta (_gatherpress_relations) on the consumer. Any post type can opt in by declaring the relevant support.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'What is gatherpress-shadow-source?', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The gatherpress-shadow-source post type support is a GatherPress primitive that creates a hidden taxonomy mirroring a post type. When a post type declares this support, the system:', 'gatherpress-relations' ); ?>
			</p>
			<ul class="gatherpress-relations__list">
				<li><?php esc_html_e( 'Registers a hidden taxonomy _<post_type> with show_ui => false, show_admin_column => true, publicly_queryable => true, show_in_rest => true, and rewrite => false.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'On first publish, inserts a shadow term whose slug is the post slug prefixed with an underscore (e.g. jane-doe becomes _jane-doe).', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'When the source post is renamed, the shadow term slug and name update automatically.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'When the source post is deleted, the shadow term is removed.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'The taxonomy appears in Query Loop block taxonomy controls (for filtering) but does not expose public archive URLs (rewrite => false).', 'gatherpress-relations' ); ?></li>
			</ul>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--info">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Same Primitive, Different Domain', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'This is exactly how GatherPress models venue ↔ event relationships. The gatherpress_venue post type declares gatherpress-shadow-source, producing a _gatherpress_venue taxonomy. Events tag themselves with venue shadow terms. The pattern is domain-agnostic — swap "venue" for "gatherpress_person" or "gatherpress_play" and the wiring is identical.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Why Both People and Productions Are Post Types', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'People need rich profile data — biography, headshot, contact information, external links — that is best modeled with post_content, featured images, and the full block editor. Productions similarly need rich editorial content. Making both post types gives each:', 'gatherpress-relations' ); ?>
			</p>
			<ul class="gatherpress-relations__list">
				<li><?php esc_html_e( 'Full block editor support for rich content (bios, descriptions, galleries).', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'Featured images for headshots and production posters — native thumbnail support.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'Dedicated single templates (single-gatherpress_person.html, single-gatherpress_play.html) with full template hierarchy.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'REST API endpoints (/wp/v2/gatherpress_person, /wp/v2/gatherpress_play) for decoupled consumers.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'Shadow taxonomies auto-generated by gatherpress-shadow-source — enabling the same tagging + Query Loop filtering that GatherPress uses for venues.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( 'Archive pages and pagination handled by WordPress core.', 'gatherpress-relations' ); ?></li>
			</ul>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'How Relationships Are Wired', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The relationship wiring is driven entirely by post_type_supports. Our plugin defines a gatherpress-relations support string. On init:100 (after all CPTs are registered), it uses WP core\'s get_post_types_by_support( "gatherpress-relations" ) to discover every consumer. Then it calls register_taxonomy_for_object_type() — the core WordPress API for attaching a taxonomy to a post type — to wire the _gatherpress_person shadow taxonomy onto each consumer. No post type slug is hard-coded in the wiring layer.', 'gatherpress-relations' ); ?>
			</p>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// STEP 1: Register the "gatherpress_person" post type (the ONLY CPT we register).
// The gatherpress_play CPT comes from gatherpress-productions — we never register it.
register_post_type( 'gatherpress_person', [
	'label'    =&gt; __( 'People', 'gatherpress-relations' ),
	'public'   =&gt; true,
	'supports' =&gt; [ 'title', 'editor', 'thumbnail', 'custom-fields', 'gatherpress-shadow-source' ],
	'show_in_rest' =&gt; true,
	'has_archive'  =&gt; true,
	'rewrite'      =&gt; [ 'slug' =&gt; 'person' ],
] );

// STEP 2: Bridge external CPTs into our system.
// The gatherpress_play CPT is owned by gatherpress-productions — we add our
// support flag to it, not the other way around.
add_action( 'init', function (): void {
	if ( post_type_exists( 'gatherpress_play' ) ) {
		add_post_type_support( 'gatherpress_play', 'gatherpress-relations' );
	}
	// Any other external CPT can be bridged the same way:
	// if ( post_type_exists( 'gatherpress_event' ) ) {
	//     add_post_type_support( 'gatherpress_event', 'gatherpress-relations' );
	// }
}, 15 );

// STEP 3: Dynamic wiring — use get_post_types_by_support() from WP core.
// No post type slug appears in this callback.
add_action( 'init', function (): void {
	// get_post_types_by_support() returns an array of post type names
	// that declare the given support string. This is a core function
	// (since WP 4.7) that reads from the global $wp_post_types registry.
	$consumers = get_post_types_by_support( 'gatherpress-relations' );

	foreach ( $consumers as $pt ) {
		// Register junction meta per consumer.
		register_post_meta( $pt, '_gatherpress_relations', [
			'type'          =&gt; 'string',
			'single'        =&gt; true,
			'show_in_rest'  =&gt; true,
			'description'   =&gt; 'JSON array of person-role assignments.',
			'auth_callback' =&gt; function (): bool {
				return current_user_can( 'edit_posts' );
			},
		] );
	}

	// Wire _gatherpress_person shadow taxonomy onto each consumer
	// using register_taxonomy_for_object_type() from WP core.
	// This is idempotent — safe to call even if already wired.
	if ( taxonomy_exists( '_gatherpress_person' ) ) {
		foreach ( $consumers as $pt ) {
			register_taxonomy_for_object_type( '_gatherpress_person', $pt );
		}
	}
}, 100 ); // Priority 100: safely after ALL CPTs are registered.

// The _gatherpress_play shadow taxonomy wiring onto events is handled by
// gatherpress-productions itself — not our responsibility.</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'The Role Problem: Relationship Metadata', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'Shadow taxonomy terms give you person ↔ production connections (via the _gatherpress_person taxonomy on the production post), but they do not natively store metadata about the connection itself — the "role". A person is not always "Romeo"; they are Romeo in Production X but the Light Technician in Production Y. Roles are contextual to a specific relationship, not to a person or a production.', 'gatherpress-relations' ); ?>
			</p>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The solution: store roles as structured post meta on the consumer post (the production, event, etc.), keyed by the shadow term. This is the same "junction metadata" pattern used across WordPress — the taxonomy handles the many-to-many join, and post meta carries per-link attributes.', 'gatherpress-relations' ); ?>
			</p>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Schema: Post Meta as Junction Metadata', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// Post meta key: _gatherpress_relations
// Stored on: gatherpress_play post (or any consumer with gatherpress-relations support)
// Value: JSON array of person-role assignments
//
// Each entry references a person by their shadow term slug
// (the underscore-prefixed post_name from the _gatherpress_person taxonomy).
//
// PAYLOAD EXAMPLE:
// post_id: 42 (gatherpress_play: "Hamlet")
// meta_key: "_gatherpress_relations"
// meta_value:
[
	{
	"person_shadow_slug": "_jane-doe",
	"person_post_id": 15,
	"role": "Hamlet",
	"department": "cast",
	"billing_order": 1
	},
	{
	"person_shadow_slug": "_alex-rivera",
	"person_post_id": 23,
	"role": "Ophelia",
	"department": "cast",
	"billing_order": 2
	},
	{
	"person_shadow_slug": "_sam-chen",
	"person_post_id": 8,
	"role": "Director",
	"department": "direction",
	"billing_order": 1
	},
	{
	"person_shadow_slug": "_taylor-wright",
	"person_post_id": 31,
	"role": "Lighting Designer",
	"department": "design",
	"billing_order": 1
	}
]

// WHY both shadow_slug and post_id?
// The shadow_slug is the canonical link (it survives re-imports),
// and the post_id is a fast denormalized lookup for rendering.
// If the person post is renamed, the shadow term slug updates
// automatically — a scheduled reconciliation job or a
// save_post hook can refresh the meta entries.</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Registration Code for Junction Meta', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// Junction meta is registered DYNAMICALLY for every post type that
// declares the 'gatherpress-relations' support.
// This happens in the init:100 callback (see "How Relationships Are Wired").
//
// No post type slug is hard-coded — the same meta key (_gatherpress_relations)
// is registered on every supporting post type discovered via
// get_post_types_by_support( 'gatherpress-relations' ):
//
//   $consumers = get_post_types_by_support( 'gatherpress-relations' );
//   foreach ( $consumers as $pt ) {
//       register_post_meta( $pt, '_gatherpress_relations', [
//           'type'          =&gt; 'string',   // JSON-encoded array
//           'single'        =&gt; true,
//           'show_in_rest'  =&gt; true,
//           'description'   =&gt; 'JSON array of {person_shadow_slug, person_post_id, role, department, billing_order}.',
//           'auth_callback' =&gt; function (): bool {
//               return current_user_can( 'edit_posts' );
//           },
//       ] );
//   }
//
// If a conference plugin adds:
//   add_post_type_support( 'session', 'gatherpress-relations' );
// then 'session' posts automatically get _gatherpress_relations meta, the
// _gatherpress_person shadow taxonomy wired onto them, and the Cast &amp; Crew
// block works on session pages — zero changes in our plugin code.</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Relationship Diagram', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__diagram">
				<span class="gatherpress-relations__diagram-content">┌────────────────────────────────┐      ┌────────────────────────────────┐
│  gatherpress_person (CPT)      │      │  gatherpress_play (CPT)        │
│  supports: shadow-source       │      │  supports: shadow-source       │
│                                │      │  (from gatherpress-productions)│
│  post_id: 15                   │      │  post_id: 42                   │
│  post_name: jane-doe           │      │  post_name: hamlet             │
│  post_title: Jane Doe          │      │  post_title: Hamlet            │
└──────────┬─────────────────────┘      └──────────┬─────────────────────┘
			│                                       │
	auto-generates                          auto-generates
			│                                       │
			▼                                       ▼
┌────────────────────────────────┐      ┌────────────────────────────────┐
│  _gatherpress_person           │      │  _gatherpress_play             │
│  (shadow taxonomy)             │      │  (shadow taxonomy)             │
│  hidden, show_in_rest          │      │  hidden, show_in_rest          │
│                                │      │                                │
│  term: _jane-doe               │      │  term: _hamlet                 │
│  term: _alex-rivera            │      │  term: _tempest                │
│  term: _sam-chen               │      │  term: _midsummer              │
└──────────┬─────────────────────┘      └──────────┬─────────────────────┘
			│                                       │
	wired onto via filter                    wired onto via filter
			│                                       │
			▼                                       ▼
┌──────────────────────────────────────────────────────────────────────┐
│  gatherpress_play (CPT)           │  gatherpress_event (CPT)         │
│                                   │                                  │
│  Tagged with _gatherpress_person: │  Tagged with _gatherpress_person:│
│    _jane-doe, _sam-chen           │    _jane-doe                     │
│                                   │  Tagged with _gatherpress_play:  │
│  post_meta: _gatherpress_relations│    _hamlet                       │
│  [{                               │                                  │
│    person_shadow_slug:            │  post_meta: _gatherpress_relations│
│      "_jane-doe",                 │  [{                              │
│    role: "Hamlet",                │    person_shadow_slug:            │
│    department: "cast"             │      "_jane-doe",                │
│  }]                               │    role: "Host"                  │
│                                   │  }]                              │
└───────────────────────────────────┴──────────────────────────────────┘</span>
				<span class="gatherpress-relations__diagram-caption"><?php esc_html_e( 'Fig 1: Both people (gatherpress_person) and productions (gatherpress_play) are post types with shadow-source support. Shadow taxonomy terms wire them onto consumer post types. Role metadata lives as _gatherpress_relations post meta on the consumer.', 'gatherpress-relations' ); ?></span>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Comparison: Shadow Taxonomy vs Manual Taxonomy vs Users', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__table-wrap">
				<table class="gatherpress-relations__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Criterion', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Shadow Taxonomy (post type + gatherpress-shadow-source)', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Manual Taxonomy (register_taxonomy)', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'WP Users', 'gatherpress-relations' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Rich profile data (bio, photo, editor)', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Full post editor + featured image', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '⚠️ Term meta + custom fields only', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '⚠️ User meta only', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Many-to-many with other post types', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Via shadow taxonomy terms', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Native term ↔ post', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ Needs custom meta', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Automatic lifecycle sync', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Term created/renamed/deleted with post', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ Manual management', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ N/A', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Block editor sidebar panel on consumer', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Automatic (show_in_rest + taxonomy panel)', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Automatic', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ No native panel', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Query Loop filtering', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Appears in taxonomy controls', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Native', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ Custom query needed', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Dedicated single pages', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Full template hierarchy (single-gatherpress_person.html)', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '⚠️ Term archive only', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '⚠️ Author archive only', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Cross-post-type reuse', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Wire via register_taxonomy_for_object_type()', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '⚠️ register_taxonomy_for_object_type', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ Manual per post type', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Login/auth capabilities', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ N/A (not real users)', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ N/A', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Native auth', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Reuse with gatherpress/venue block', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '✅ Set sourcePostType attribute', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ Not compatible', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '❌ Not compatible', 'gatherpress-relations' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Agnostic by Design', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'This pattern is not theater-specific. Replace "gatherpress_person" with "sponsor", "speaker", or "vendor" — the same gatherpress-shadow-source support on the person post type applies. Any consumer post type (gatherpress_play, gatherpress_event, session, conference) opts in by declaring gatherpress-relations support. The wiring layer discovers it automatically via get_post_types_by_support(). No code changes needed.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--warning">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Why not a separate "role" taxonomy?', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'Roles like "Romeo" or "Light Tech" are contextual to a specific relationship — they are not reusable categories. A separate taxonomy would create orphaned terms for every unique role ever assigned. Instead, roles live as metadata on the person ↔ consumer relationship, stored as post meta (_gatherpress_relations) on whichever post type declares gatherpress-relations support.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Leveraging the gatherpress/venue Block', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The gatherpress/venue block accepts a sourcePostType attribute (default gatherpress_venue). Set it to your shadow-source CPT and the block resolves its connected source post via your taxonomy — same block, same inner blocks (post-title, venue-detail, etc.), different source. This gives you a person-detail or production-detail surface on any event template for free:', 'gatherpress-relations' ); ?>
			</p>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">&lt;!-- Show the connected person's details on an event template --&gt;
&lt;!-- wp:gatherpress/venue {"sourcePostType":"gatherpress_person"} --&gt;
	&lt;!-- wp:post-title /--&gt;
	&lt;!-- wp:post-featured-image /--&gt;
	&lt;!-- wp:post-content /--&gt;
&lt;!-- /wp:gatherpress/venue --&gt;

&lt;!-- Show the connected production's details on an event template --&gt;
&lt;!-- wp:gatherpress/venue {"sourcePostType":"gatherpress_play"} --&gt;
	&lt;!-- wp:post-title /--&gt;
&lt;!-- /wp:gatherpress/venue --&gt;</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'JavaScript: Discovering Post Types via Core Data Store', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'In the editor, use the @wordpress/core-data store to dynamically discover post types and their supports. This ensures the block\'s UI adapts to the runtime environment without hard-coding any post type slug:', 'gatherpress-relations' ); ?>
			</p>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Hook that returns all post types supporting 'gatherpress-relations'.
 *
 * HOW this works:
 * ───────────────
 * The @wordpress/core-data store exposes getPostTypes() which fetches
 * post type objects from the REST API (/wp/v2/types). Each object
 * includes a `supports` property. We filter client-side to find all
 * post types that declare 'gatherpress-relations' support.
 *
 * WHY the core data store instead of a custom endpoint?
 * - Zero new REST routes needed.
 * - The data is already cached by the store across components.
 * - Stays in sync with the server's post type registry automatically.
 *
 * @returns {{ consumerTypes: Object[]|null, isResolving: boolean }}
 *
 * PAYLOAD example (consumerTypes):
 * [
 *   { slug: 'gatherpress_play', name: 'Productions', supports: { ... } },
 *   { slug: 'gatherpress_event', name: 'Events', supports: { ... } },
 * ]
 */
function useRelationConsumerTypes() {
	return useSelect( ( select ) =&gt; {
		const { getPostTypes, isResolving } = select( coreStore );
		const allTypes = getPostTypes( { per_page: -1 } );

		if ( ! allTypes ) {
			return { consumerTypes: null, isResolving: true };
		}

		// Filter for post types with gatherpress-relations support.
		// The REST API exposes supports as an object with boolean values.
		const consumers = allTypes.filter(
			( type ) =&gt; type.supports?.[ 'gatherpress-relations' ]
		);

		return {
			consumerTypes: consumers,
			isResolving: isResolving( 'getPostTypes', [{ per_page: -1 }] ),
		};
	}, [] );
}</code>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Section 2: Cast & Crew List Block.
		 *
		 * @since  0.1.0
		 * @return string Section body HTML.
		 */
		private function render_cast_crew(): string {
			ob_start();
			?>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The Cast & Crew List block is the primary interface for viewing the person ↔ production ↔ role relationships. It renders a grouped roster on the production page by reading the _gatherpress_person shadow taxonomy terms assigned to the production and enriching them with role metadata from the _gatherpress_relations post meta.', 'gatherpress-relations' ); ?>
			</p>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Block Architecture', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__callout gatherpress-relations__callout--info">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Dynamic Block', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'This must be a dynamic block (render.php) because it reads _gatherpress_relations post meta and resolves person post data at render time. If a person\'s name, headshot, or bio changes, the block output updates automatically without re-saving the production.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'How the Data Flows', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The block combines two data sources on the consumer post:', 'gatherpress-relations' ); ?>
			</p>
			<ul class="gatherpress-relations__list">
				<li><strong><?php esc_html_e( 'Shadow taxonomy terms', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'The _gatherpress_person shadow taxonomy terms assigned to this post via the editor\'s taxonomy panel (or the gatherpress_shadow_taxonomy_object_types wiring). These tell you WHICH persons are connected.', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Junction meta (_gatherpress_relations)', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'A JSON array stored as post meta on the consumer. Each entry references a person by their shadow term slug and adds the role, department, and billing order. This tells you HOW each person is connected.', 'gatherpress-relations' ); ?></li>
			</ul>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Frontend Output Structure', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">&lt;div class="wp-block-gatherpress-cast-crew"&gt;
	&lt;section class="cast-crew__department"&gt;
	&lt;h3 class="cast-crew__department-title"&gt;Cast&lt;/h3&gt;
	&lt;div class="cast-crew__grid"&gt;
		&lt;a href="/person/jane-doe/" class="cast-crew__card"&gt;
		&lt;img class="cast-crew__headshot"
			src="..."
			alt="Jane Doe" /&gt;
		&lt;span class="cast-crew__name"&gt;Jane Doe&lt;/span&gt;
		&lt;span class="cast-crew__role"&gt;Hamlet&lt;/span&gt;
		&lt;/a&gt;
	&lt;/div&gt;
	&lt;/section&gt;
	&lt;section class="cast-crew__department"&gt;
	&lt;h3&gt;Direction&lt;/h3&gt;
	&lt;/section&gt;
&lt;/div&gt;</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Render Logic (Simplified)', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// In render.php of the cast-crew block:
$post_id   = get_the_ID();
$relations = json_decode(
	get_post_meta( $post_id, '_gatherpress_relations', true ) ?: '[]',
	true
);

// Group by department.
$departments = [];
foreach ( $relations as $entry ) {
	$dept = $entry['department'] ?? 'other';
	$departments[ $dept ][] = $entry;
}

// Sort each department by billing_order.
foreach ( $departments as &amp;$members ) {
	usort(
		$members,
		fn( $a, $b ) =&gt; ( $a['billing_order'] ?? 99 ) &lt;=&gt; ( $b['billing_order'] ?? 99 )
	);
}

// Render each department section...
foreach ( $departments as $dept_name =&gt; $members ) {
	echo '&lt;section class="cast-crew__department"&gt;';
	echo '&lt;h3&gt;' . esc_html( ucfirst( $dept_name ) ) . '&lt;/h3&gt;';
	foreach ( $members as $member ) {
		$person_post_id = $member['person_post_id'];
		$person_post    = get_post( $person_post_id );
		$headshot       = get_the_post_thumbnail_url( $person_post_id, 'medium' );
		$permalink      = get_permalink( $person_post_id );
		// ... render card with link to person single page
	}
	echo '&lt;/section&gt;';
}</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Editor Experience', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'In the editor, the block reads the current post\'s _gatherpress_relations meta via useEntityProp and renders a live preview of the grouped roster. The shadow taxonomy panel (_gatherpress_person) already appears in the post sidebar — editors can add/remove person tags there. A custom InspectorControls panel in the block provides:', 'gatherpress-relations' ); ?>
			</p>
			<ul class="gatherpress-relations__list">
				<li><strong><?php esc_html_e( 'Person selector', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'ComboboxControl searching person posts via REST (/wp/v2/gatherpress_person?search=...), then resolving their shadow term slug.', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Role input', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'TextControl for the role name (free text — roles are unique per production).', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Department dropdown', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'SelectControl with options: Cast, Direction, Design, Stage Management, Other.', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Billing order', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'NumberControl or drag-and-drop reordering within each department.', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Add / Remove', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'Buttons to add a new person-role entry or remove an existing one.', 'gatherpress-relations' ); ?></li>
			</ul>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Pro Tip: useEntityProp + Core Data Store', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'Use useEntityProp( "postType", currentPostType, "meta._gatherpress_relations" ) to read and write the junction meta directly from the block. The currentPostType is resolved dynamically from the core data store — the block never hard-codes "gatherpress_play". When the editor adds a person-role entry via the block\'s sidebar, the block also programmatically adds the corresponding _gatherpress_person shadow term to the post\'s taxonomy terms — keeping the two data sources in sync. Changes are saved when the user clicks "Update".', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Department Configuration', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// Departments and their display order.
// Filterable so themes/plugins can customise for their domain.
$departments_config = apply_filters( 'gatherpress_relations_departments', [
	'cast'             =&gt; __( 'Cast',             'gatherpress-relations' ),
	'direction'        =&gt; __( 'Direction',         'gatherpress-relations' ),
	'design'           =&gt; __( 'Design',            'gatherpress-relations' ),
	'stage_management' =&gt; __( 'Stage Management',  'gatherpress-relations' ),
	'musicians'        =&gt; __( 'Musicians',         'gatherpress-relations' ),
	'production'       =&gt; __( 'Production',        'gatherpress-relations' ),
	'other'            =&gt; __( 'Other',             'gatherpress-relations' ),
] );</code>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Section 3: People / Ensemble Blocks.
		 *
		 * CONTENT SUMMARY:
		 * - 2.1 Person Profile Card — now a "Recent Roles" list block
		 *   showing only roles with linked productions (no headshot, no bio).
		 * - 2.2 Ensemble Grid — NOT a custom block; uses core/query with
		 *   gatherpress-seasons for season filtering and humanmade/query-filter
		 *   for the filter UI.
		 * - 2.3 Person Production Timeline — also a core/query pattern,
		 *   not a custom block.
		 *
		 * @since  0.1.0
		 * @return string Section body HTML.
		 */
		private function render_ensemble(): string {
			ob_start();
			?>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'These blocks and patterns render person-centric views — a recent roles list, a filterable ensemble grid, and a production timeline. Because persons are full post types (gatherpress_person), they integrate natively with the core Query Loop block. Only the "Recent Roles" list requires a custom block; the grid and timeline are achieved with core/query patterns and companion plugins.', 'gatherpress-relations' ); ?>
			</p>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( '2.1 Person Recent Roles (Custom Block)', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'A focused block that renders a list of recent roles a person has held, with each role linking to its production page. This is the only custom block in Phase 2 — it needs custom rendering because the role data lives as junction metadata (_gatherpress_relations) on the consumer posts, not on the person post itself.', 'gatherpress-relations' ); ?>
			</p>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--info">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Why a Custom Block?', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'A core/query block can list consumer posts tagged with a person\'s shadow term, but it cannot extract the per-relationship role name from _gatherpress_relations meta. The Person Recent Roles block resolves this by querying consumer posts via the shadow taxonomy and then parsing the junction meta to display each role alongside its linked production.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// block.json attributes for Person Recent Roles:
{
	"name": "gatherpress/person-recent-roles",
	"attributes": {
	"maxRoles": {
		"type": "number",
		"default": 10
	},
	"showDepartment": {
		"type": "boolean",
		"default": true
	},
	"groupByDepartment": {
		"type": "boolean",
		"default": false
	}
	},
	"usesContext": [ "postId", "postType" ]
}

// Render logic (render.php):
//
// 1. Resolve person from block context:
//    $person_id   = $block-&gt;context['postId'];
//    $person_slug = get_post_field( 'post_name', $person_id );
//    $shadow_slug = '_' . $person_slug;
//    $shadow_term = get_term_by( 'slug', $shadow_slug, '_gatherpress_person' );
//
// 2. Query all consumer posts tagged with this person's shadow term:
//    $consumer_types = get_post_types_by_support( 'gatherpress-relations' );
//    $productions = get_posts( [
//        'post_type'      =&gt; $consumer_types,
//        'posts_per_page' =&gt; $maxRoles,
//        'tax_query'      =&gt; [ [
//            'taxonomy' =&gt; '_gatherpress_person',
//            'terms'    =&gt; $shadow_term-&gt;term_id,
//        ] ],
//        'orderby' =&gt; 'date',
//        'order'   =&gt; 'DESC',
//    ] );
//
// 3. For each production, extract the role from junction meta:
//    $relations = json_decode(
//        get_post_meta( $prod-&gt;ID, '_gatherpress_relations', true ) ?: '[]',
//        true
//    );
//    $entries = array_filter(
//        $relations,
//        fn( $e ) =&gt; $e['person_shadow_slug'] === $shadow_slug
//    );
//
// 4. Render as a list:
//    &lt;ul class="person-recent-roles__list"&gt;
//      &lt;li class="person-recent-roles__item"&gt;
//        &lt;a href="/production/hamlet/"&gt;Hamlet&lt;/a&gt;
//        &lt;span class="person-recent-roles__role"&gt;as Hamlet&lt;/span&gt;
//        &lt;span class="person-recent-roles__dept"&gt;Cast&lt;/span&gt;
//      &lt;/li&gt;
//    &lt;/ul&gt;</code>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Shadow Taxonomy Query = Core-Optimised', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'The tax_query uses WordPress core\'s optimised term_relationships JOIN. No LIKE on JSON, no custom table. The shadow taxonomy gives you the same query performance as any native taxonomy — O(1) index lookup on the term_taxonomy_id.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( '2.2 Ensemble Grid (core/query + query-filter)', 'gatherpress-relations' ); ?></h4>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--key">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Not a Custom Block', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'The Ensemble Grid is built entirely with core/query block querying gatherpress_person posts. No custom block is needed — WordPress core handles the loop, pagination, and template. Seasons are provided by the gatherpress-seasons plugin, and filtering is handled by humanmade/query-filter.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'Since gatherpress_person is a full post type with show_in_rest and archive support, the core Query Loop block can query it directly. The editor\'s Query Loop controls let you set post type, taxonomy filters, ordering, and pagination — all without code.', 'gatherpress-relations' ); ?>
			</p>

			<h5 class="gatherpress-relations__heading"><?php esc_html_e( 'Filtering Architecture', 'gatherpress-relations' ); ?></h5>
			<ul class="gatherpress-relations__list">
				<li><strong><?php esc_html_e( 'Season filtering', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'The gatherpress-seasons plugin provides a season taxonomy (or similar mechanism). Persons connected to productions in a given season can be filtered via taxonomy queries on the shadow taxonomy. The core/query block supports taxonomy filtering natively in its editor controls.', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Department / status filtering', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'Use humanmade/query-filter (github.com/humanmade/query-filter) to add frontend filter UI to the Query Loop. This plugin provides filter blocks (dropdowns, checkboxes, search) that modify the query parameters client-side or via URL parameters — no custom JavaScript needed.', 'gatherpress-relations' ); ?></li>
				<li><strong><?php esc_html_e( 'Status metadata', 'gatherpress-relations' ); ?></strong> — <?php esc_html_e( 'Ensemble status (current member, alumni, guest artist) can be stored as a taxonomy on the person post type, making it filterable via both the Query Loop and query-filter blocks.', 'gatherpress-relations' ); ?></li>
			</ul>

			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">&lt;!-- Ensemble Grid: core/query block pattern --&gt;
&lt;!-- wp:query {"queryId":1,"query":{"postType":"gatherpress_person","perPage":24,"orderBy":"title","order":"asc"}} --&gt;
&lt;div class="wp-block-query"&gt;

	&lt;!-- Filter bar via humanmade/query-filter --&gt;
	&lt;!-- wp:query-filter/taxonomy {"taxonomy":"ensemble_status"} /--&gt;
	&lt;!-- wp:query-filter/taxonomy {"taxonomy":"_gatherpress_season"} /--&gt;

	&lt;!-- wp:post-template {"layout":{"type":"grid","columnCount":4}} --&gt;
	&lt;!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1","scale":"cover"} /--&gt;
	&lt;!-- wp:post-title {"isLink":true,"level":3,"fontSize":"small"} /--&gt;
	&lt;!-- wp:post-excerpt {"moreText":"","excerptLength":15} /--&gt;
	&lt;!-- /wp:post-template --&gt;

	&lt;!-- wp:query-pagination --&gt;
	&lt;!-- wp:query-pagination-previous /--&gt;
	&lt;!-- wp:query-pagination-numbers /--&gt;
	&lt;!-- wp:query-pagination-next /--&gt;
	&lt;!-- /wp:query-pagination --&gt;

	&lt;!-- wp:query-no-results --&gt;
	&lt;!-- wp:paragraph --&gt;
	&lt;p&gt;No ensemble members found matching your filters.&lt;/p&gt;
	&lt;!-- /wp:paragraph --&gt;
	&lt;!-- /wp:query-no-results --&gt;

&lt;/div&gt;
&lt;!-- /wp:query --&gt;</code>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Why humanmade/query-filter?', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'query-filter integrates directly with the core Query Loop block. It adds filter controls (taxonomy dropdowns, search, date ranges) that modify query parameters without custom JavaScript or REST endpoints. Filters work via URL parameters, making filtered views bookmarkable and SEO-friendly. No custom block development needed for the filtering layer.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( '2.3 Person Production Timeline (core/query Pattern)', 'gatherpress-relations' ); ?></h4>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--key">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Not a Custom Block', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'The Person Production Timeline can be built with a core/query block that filters consumer posts (gatherpress_play, gatherpress_event) by the current person\'s _gatherpress_person shadow term. On a single-gatherpress_person template, the Query Loop can inherit the query context to show all productions featuring this person, ordered by date.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'On a single-gatherpress_person.html template, a core/query block querying consumer post types and filtering by the _gatherpress_person shadow taxonomy produces a chronological list of all productions the person has been involved in. The shadow taxonomy term is resolved from the current post context automatically.', 'gatherpress-relations' ); ?>
			</p>

			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">&lt;!-- Person Production Timeline: core/query on single-gatherpress_person template --&gt;
&lt;!-- wp:query {"queryId":2,"query":{"postType":"gatherpress_play","perPage":50,"orderBy":"date","order":"desc","taxQuery":{"_gatherpress_person":[]}}} --&gt;
&lt;div class="wp-block-query"&gt;

	&lt;!-- wp:post-template {"layout":{"type":"default"}} --&gt;
	&lt;!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between"}} --&gt;
	&lt;div class="wp-block-group"&gt;
		&lt;!-- wp:post-title {"isLink":true,"level":4} /--&gt;
		&lt;!-- wp:post-date {"format":"Y"} /--&gt;
	&lt;/div&gt;
	&lt;!-- /wp:group --&gt;
	&lt;!-- /wp:post-template --&gt;

	&lt;!-- wp:query-no-results --&gt;
	&lt;!-- wp:paragraph --&gt;
	&lt;p&gt;No productions found for this person.&lt;/p&gt;
	&lt;!-- /wp:paragraph --&gt;
	&lt;!-- /wp:query-no-results --&gt;

&lt;/div&gt;
&lt;!-- /wp:query --&gt;

&lt;!-- NOTE: The role name per production is NOT available in the
	core/query context because it lives in _gatherpress_relations
	meta on the consumer post, not as a standard field. To display
	roles alongside production titles, use the Person Recent Roles
	custom block (2.1) instead of this core/query pattern.

	This core/query pattern is ideal when you need:
	- A simple linked list of productions without role names
	- Pagination and standard query controls
	- Zero custom block code
--&gt;</code>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--info">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'When to Use core/query vs. Person Recent Roles', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'Use the core/query pattern (2.3) when you need a simple list of productions linked to a person — titles, dates, featured images, excerpts — without per-relationship role names. Use the Person Recent Roles custom block (2.1) when you need to display the actual role name (e.g., "as Hamlet") alongside each production, because that data requires parsing the _gatherpress_relations junction meta.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Using gatherpress/event-query for Contextual Scoping', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'The gatherpress/event-query block\'s "Filter by Current Venue" toggle automatically scopes to whatever shadow-source CPT the queried page represents. On a gatherpress_person singular page, it scopes to that person\'s events; on a gatherpress_play singular, to that production\'s events.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Phase 2 Dependencies', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__table-wrap">
				<table class="gatherpress-relations__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Dependency', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Provides', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Required For', 'gatherpress-relations' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code class="gatherpress-relations__inline-code">gatherpress-seasons</code></td>
							<td><?php esc_html_e( 'Season taxonomy and management UI', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'Season filtering in the Ensemble Grid', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><code class="gatherpress-relations__inline-code">humanmade/query-filter</code></td>
							<td><?php esc_html_e( 'Filter blocks for core/query (taxonomy, search, date)', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'Frontend filtering UI for Ensemble Grid', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'WordPress core (Query Loop)', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'core/query, core/post-template, pagination', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'Ensemble Grid + Person Timeline patterns', 'gatherpress-relations' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Section 4: Smart Data-Relation Visualizations.
		 *
		 * @since  0.1.0
		 * @return string Section body HTML.
		 */
		private function render_visualizations(): string {
			ob_start();
			?>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'These blocks query relationships dynamically — they follow the shadow taxonomy links between persons, productions, and events to surface meaningful connections.', 'gatherpress-relations' ); ?>
			</p>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( '3.1 "Where Have You Seen Them?"', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'Given a person, show all productions/events they have appeared in with role names.', 'gatherpress-relations' ); ?>
			</p>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// Query: "Find all consumer posts featuring gatherpress_person post_id = 15"
$person_slug  = get_post_field( 'post_name', 15 );
$shadow_slug  = '_' . $person_slug;
$shadow_term  = get_term_by( 'slug', $shadow_slug, '_gatherpress_person' );

$consumer_types = get_post_types_by_support( 'gatherpress-relations' );

$results = get_posts( [
	'post_type'      =&gt; $consumer_types,
	'posts_per_page' =&gt; 50,
	'tax_query'      =&gt; [
		[
			'taxonomy' =&gt; '_gatherpress_person',
			'terms'    =&gt; $shadow_term-&gt;term_id,
		],
	],
	'orderby' =&gt; 'date',
	'order'   =&gt; 'DESC',
] );

// For each result, extract the role from _gatherpress_relations meta.
foreach ( $results as $post ) {
	$relations = json_decode(
		get_post_meta( $post-&gt;ID, '_gatherpress_relations', true ) ?: '[]',
		true
	);
	$person_entry = array_filter(
		$relations,
		fn( $e ) =&gt; $e['person_shadow_slug'] === $shadow_slug
	);
	$entry = reset( $person_entry );
	// $entry['role'] =&gt; "Hamlet"
}</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( '3.2 "Who\'s In This Show?"', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The inverse query: on a production page, list every cast and crew member with links to their person page.', 'gatherpress-relations' ); ?>
			</p>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// On gatherpress_play post_id = 42:
$relations = json_decode(
	get_post_meta( 42, '_gatherpress_relations', true ) ?: '[]',
	true
);

foreach ( $relations as $entry ) {
	$person_post_id = $entry['person_post_id'];
	$person_post    = get_post( $person_post_id );
	$permalink      = get_permalink( $person_post_id );

	$shadow_term = get_term_by( 'slug', $entry['person_shadow_slug'], '_gatherpress_person' );
	$total_appearances = $shadow_term ? $shadow_term-&gt;count : 0;

	// Render: "Jane Doe as Hamlet (12 productions)"
}</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( '3.3 "Upcoming for You" (with GatherPress)', 'gatherpress-relations' ); ?></h4>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'If integrated with GatherPress RSVP data, this block can recommend upcoming productions and events based on the visitor\'s attendance history.', 'gatherpress-relations' ); ?>
			</p>
			<ul class="gatherpress-relations__list">
				<li><?php esc_html_e( '1. Find events the user has RSVP\'d to (GatherPress attendance data).', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( '2. For each attended event, get the shadow terms assigned to it.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( '3. Find upcoming events that share any of those shadow terms.', 'gatherpress-relations' ); ?></li>
				<li><?php esc_html_e( '4. Render: "You attended Hamlet — the same director\'s next event is The Tempest Opening Night."', 'gatherpress-relations' ); ?></li>
			</ul>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--info">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'REST Endpoint for Recommendations', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'This recommendation query is too complex for render.php alone. Create a custom REST endpoint (e.g., gatherpress-relations/v1/recommendations) that accepts a list of attended event IDs and returns upcoming events with shared shadow terms.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'REST API Design for Relation Queries', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// Custom REST endpoints for relation queries:

// GET /gatherpress-relations/v1/person/15/appearances
// Response:
[
	{
	"consumer_id": 42,
	"consumer_type": "gatherpress_play",
	"title": "Hamlet",
	"role": "Hamlet",
	"department": "cast",
	"date": "2024-03-15",
	"permalink": "https://example.com/production/hamlet/"
	}
]

// GET /gatherpress-relations/v1/consumer/42/relations
// Response:
{
	"cast": [
	{
		"person_post_id": 15,
		"name": "Jane Doe",
		"role": "Hamlet",
		"headshot": "https://example.com/wp-content/uploads/...",
		"permalink": "https://example.com/person/jane-doe/"
	}
	]
}

// GET /gatherpress-relations/v1/recommendations?attended=78,92
// Response:
[
	{
	"event_id": 110,
	"title": "The Tempest Opening Night",
	"reason": "Same director: Sam Chen",
	"date": "2025-01-20",
	"permalink": "https://example.com/event/tempest-opening/"
	}
]</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Reusing Shadow_Source Query Primitives', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">use GatherPress\Core\Shadow_Source;

// Inside a pre_get_posts callback:
$shadow_source = Shadow_Source::get_instance();
$source_post   = $shadow_source-&gt;resolve_post_from_query_context( $query );

if ( $source_post instanceof WP_Post ) {
	$tax_query   = (array) ( $query-&gt;get( 'tax_query' ) ?: [] );
	$tax_query[] = $shadow_source-&gt;build_tax_query_clause( $source_post );
	$query-&gt;set( 'tax_query', $tax_query );
}</code>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Section 5: Scaling & Performance.
		 *
		 * @since  0.1.0
		 * @return string Section body HTML.
		 */
		private function render_scaling(): string {
			ob_start();
			?>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'The shadow taxonomy approach is inherently performant because it leverages WordPress core\'s indexed term_relationships table for the many-to-many join.', 'gatherpress-relations' ); ?>
			</p>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Scaling Tiers', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__table-wrap">
				<table class="gatherpress-relations__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Scale', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Consumer Posts', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Strategy', 'gatherpress-relations' ); ?></th>
							<th><?php esc_html_e( 'Read Performance', 'gatherpress-relations' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Small', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '< 100', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'Shadow taxonomy tax_query + JSON meta parse', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '< 50ms', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Medium', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '100–500', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'Same + transient cache for enriched results', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '< 100ms (first), < 5ms (cached)', 'gatherpress-relations' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Large', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '500+', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( 'Denormalized lookup table', 'gatherpress-relations' ); ?></td>
							<td><?php esc_html_e( '< 10ms', 'gatherpress-relations' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Transient Caching (Medium Scale)', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">function gatherpress_get_person_appearances( int $person_post_id ): array {
	$cache_key = 'gp_person_appearances_' . $person_post_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$shadow_slug = '_' . get_post_field( 'post_name', $person_post_id );
	$shadow_term = get_term_by( 'slug', $shadow_slug, '_gatherpress_person' );

	if ( ! $shadow_term ) {
		return [];
	}

	$consumer_types = get_post_types_by_support( 'gatherpress-relations' );

	$posts = get_posts( [
		'post_type'      =&gt; $consumer_types,
		'posts_per_page' =&gt; 100,
		'tax_query'      =&gt; [
			[ 'taxonomy' =&gt; '_gatherpress_person', 'terms' =&gt; $shadow_term-&gt;term_id ],
		],
	] );

	$result = [];
	foreach ( $posts as $post ) {
		$relations = json_decode( get_post_meta( $post-&gt;ID, '_gatherpress_relations', true ) ?: '[]', true );
		$entry     = array_filter( $relations, fn( $e ) =&gt; $e['person_shadow_slug'] === $shadow_slug );
		$entry     = reset( $entry );
		$result[]  = [
			'consumer_id'   =&gt; $post-&gt;ID,
			'consumer_type' =&gt; $post-&gt;post_type,
			'title'         =&gt; $post-&gt;post_title,
			'role'          =&gt; $entry['role'] ?? '',
			'department'    =&gt; $entry['department'] ?? '',
			'date'          =&gt; $post-&gt;post_date,
		];
	}

	set_transient( $cache_key, $result, HOUR_IN_SECONDS );
	return $result;
}</code>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Denormalized Lookup Table (Large Scale)', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">// Custom table schema:
// Table: {$wpdb-&gt;prefix}gatherpress_relations_lookup
//
// ┌────────┬─────────────────┬────────────────┬──────────────┬───────────┬───────────────┐
// │ id     │ consumer_id     │ person_id      │ role         │ department│ billing_order │
// │ BIGINT │ BIGINT (FK)     │ BIGINT (FK)    │ VARCHAR(255) │ VARCHAR   │ INT           │
// └────────┴─────────────────┴────────────────┴──────────────┴───────────┴───────────────┘
//
// Indices: PRIMARY KEY (id), INDEX (consumer_id), INDEX (person_id), INDEX (department)</code>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Migration Path', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'Start with shadow taxonomy + JSON meta (Phase 1). Add transient caching (Phase 2). If you exceed 500 consumer posts, create the lookup table with dbDelta and backfill from existing _gatherpress_relations meta.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Object Cache Integration', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">$cache_key   = 'person_appearances_' . $person_post_id;
$cache_group = 'gatherpress_relations';

$result = wp_cache_get( $cache_key, $cache_group );
if ( false === $result ) {
	$result = $wpdb-&gt;get_results( $wpdb-&gt;prepare(
		"SELECT * FROM {$wpdb-&gt;prefix}gatherpress_relations_lookup WHERE person_id = %d",
		$person_post_id
	) );
	wp_cache_set( $cache_key, $result, $cache_group, HOUR_IN_SECONDS );
}</code>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Section 6: Implementation Roadmap.
		 *
		 * @since  0.1.0
		 * @return string Section body HTML.
		 */
		private function render_implementation(): string {
			ob_start();
			?>
			<p class="gatherpress-relations__paragraph">
				<?php esc_html_e( 'A phased approach ensures each layer is stable before adding complexity. Each phase delivers a usable, testable increment. The architecture is agnostic from Phase 1 — nothing is hard-bound to any post type slug.', 'gatherpress-relations' ); ?>
			</p>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--key">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Fixed Decision: Production Is External', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'The gatherpress_play post type is registered and provided by the gatherpress-productions plugin. We never register it ourselves — we only consume its data.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<div class="gatherpress-relations__callout gatherpress-relations__callout--info">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'The post_type_supports Pattern', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( 'Every new capability in this plugin is registered as a post_type_supports string. A post type opts in by adding the support to its supports array. The plugin hooks into init at priority 100 and uses get_post_types_by_support( "gatherpress-relations" ) to discover all consumer post types.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<div class="gatherpress-relations__phase gatherpress-relations__phase--alpha">
				<h4 class="gatherpress-relations__phase-title"><?php esc_html_e( '✅ Phase 1 — Alpha: Foundation (Implemented)', 'gatherpress-relations' ); ?></h4>
				<p class="gatherpress-relations__phase-text"><?php esc_html_e( 'Phase 1 is implemented in gatherpress-relations.php and src/blocks/roadmap/index.js. It includes the gatherpress_person post type, the gatherpress-relations support system, bridge logic for gatherpress_play, dynamic wiring at init:100, dependency checking, and editor-side hooks via the core data store.', 'gatherpress-relations' ); ?></p>

				<div class="gatherpress-relations__callout gatherpress-relations__callout--tip">
					<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Developer Documentation', 'gatherpress-relations' ); ?></p>
					<p class="gatherpress-relations__callout-text">
						<?php esc_html_e( 'Full implementation details, class architecture, hook execution order, meta schema, and editor hook API are documented in docs/developer/README.md.', 'gatherpress-relations' ); ?>
					</p>
				</div>

				<h5 class="gatherpress-relations__heading"><?php esc_html_e( 'Implemented Components', 'gatherpress-relations' ); ?></h5>
				<ul class="gatherpress-relations__list">
					<li><code class="gatherpress-relations__inline-code">GatherPress_Relations_Person_CPT</code> <?php esc_html_e( '— registers gatherpress_person with gatherpress-shadow-source support (init:10)', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">GatherPress_Relations_Bridge</code> <?php esc_html_e( '— adds gatherpress-relations support to gatherpress_play if active (init:15)', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">GatherPress_Relations_Wiring</code> <?php esc_html_e( '— discovers consumers via get_post_types_by_support(), registers _gatherpress_relations meta, wires _gatherpress_person shadow taxonomy via register_taxonomy_for_object_type() (init:100)', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">GatherPress_Relations_Dependency</code> <?php esc_html_e( '— checks for GatherPress core, enables graceful degradation', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">GatherPress_Relations_Singleton_Trait</code> <?php esc_html_e( '— reusable Singleton pattern shared by all classes', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">useSupportsRelations()</code> <?php esc_html_e( '— editor hook checking current post type\'s gatherpress-relations support', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">useRelationConsumerTypes()</code> <?php esc_html_e( '— editor hook returning all post types with gatherpress-relations support', 'gatherpress-relations' ); ?></li>
				</ul>

				<h5 class="gatherpress-relations__heading"><?php esc_html_e( 'Cast & Crew List Block', 'gatherpress-relations' ); ?></h5>
				<p class="gatherpress-relations__paragraph">
					<?php esc_html_e( '✅ Implemented as gatherpress/cast-crew-list. A dynamic block that reads _gatherpress_relations meta from the current consumer post and renders a grouped, department-sorted roster with headshots, names, roles, and links to person pages. Includes a full editor sidebar for managing person-role entries (search, add, edit, reorder, remove) and a frontend department filter bar with staggered entrance animations.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<div class="gatherpress-relations__phase gatherpress-relations__phase--beta">
				<h4 class="gatherpress-relations__phase-title"><?php esc_html_e( 'Phase 2 — Beta: People Blocks & Patterns (In Progress)', 'gatherpress-relations' ); ?></h4>
				<p class="gatherpress-relations__phase-text"><?php esc_html_e( 'One custom block (Person Recent Roles) and two documented core/query patterns (Ensemble Grid, Person Timeline). Transient caching for cross-queries.', 'gatherpress-relations' ); ?></p>

				<h5 class="gatherpress-relations__heading"><?php esc_html_e( 'Person Recent Roles Block', 'gatherpress-relations' ); ?></h5>
				<p class="gatherpress-relations__paragraph">
					<?php esc_html_e( '✅ Implemented as gatherpress/person-recent-roles. A dynamic block that queries consumer posts tagged with the current person\'s _gatherpress_person shadow term, parses _gatherpress_relations junction meta to extract role data, and renders a linked list of roles with production titles, department badges, post type labels, and dates. Supports grouping by department, configurable max roles, and multiple date formats.', 'gatherpress-relations' ); ?>
				</p>

				<ul class="gatherpress-relations__list">
					<li><strong><?php esc_html_e( '✅ Custom block:', 'gatherpress-relations' ); ?></strong> <?php esc_html_e( 'Person Recent Roles — list of roles with linked productions', 'gatherpress-relations' ); ?></li>
					<li><strong><?php esc_html_e( 'core/query pattern:', 'gatherpress-relations' ); ?></strong> <?php esc_html_e( 'Ensemble Grid — filterable grid via gatherpress-seasons + humanmade/query-filter', 'gatherpress-relations' ); ?></li>
					<li><strong><?php esc_html_e( 'core/query pattern:', 'gatherpress-relations' ); ?></strong> <?php esc_html_e( 'Person Production Timeline — chronological list on person templates', 'gatherpress-relations' ); ?></li>
					<li><?php esc_html_e( 'Transient caching layer with save_post invalidation', 'gatherpress-relations' ); ?></li>
					<li><code class="gatherpress-relations__inline-code">single-gatherpress_person.html</code> <?php esc_html_e( 'template with Recent Roles block + core/query timeline', 'gatherpress-relations' ); ?></li>
				</ul>
			</div>

			<div class="gatherpress-relations__phase gatherpress-relations__phase--ga">
				<h4 class="gatherpress-relations__phase-title"><?php esc_html_e( 'Phase 3 — GA: Visualization Blocks & REST', 'gatherpress-relations' ); ?></h4>
				<p class="gatherpress-relations__phase-text"><?php esc_html_e( 'Build the "Where Have You Seen Them?" and enriched "Who\'s In This Show?" blocks. Create custom REST endpoints.', 'gatherpress-relations' ); ?></p>
				<ul class="gatherpress-relations__list">
					<li><code class="gatherpress-relations__inline-code">GET /gatherpress-relations/v1/person/{id}/appearances</code></li>
					<li><code class="gatherpress-relations__inline-code">GET /gatherpress-relations/v1/consumer/{id}/relations</code></li>
					<li><?php esc_html_e( '"Where Have You Seen Them?" block', 'gatherpress-relations' ); ?></li>
					<li><?php esc_html_e( 'Enriched "Who\'s In This Show?" block', 'gatherpress-relations' ); ?></li>
				</ul>
			</div>

			<div class="gatherpress-relations__phase gatherpress-relations__phase--future">
				<h4 class="gatherpress-relations__phase-title"><?php esc_html_e( 'Phase 4 — Future: Scale & Integrate', 'gatherpress-relations' ); ?></h4>
				<p class="gatherpress-relations__phase-text"><?php esc_html_e( 'If scale demands it, add the denormalized lookup table. Integrate with GatherPress RSVP data for the "Upcoming for You" recommendation block.', 'gatherpress-relations' ); ?></p>
				<ul class="gatherpress-relations__list">
					<li><?php esc_html_e( 'Denormalized gatherpress_relations_lookup table + migration script', 'gatherpress-relations' ); ?></li>
					<li><?php esc_html_e( 'GatherPress RSVP integration hooks', 'gatherpress-relations' ); ?></li>
					<li><?php esc_html_e( '"Upcoming for You" recommendation block', 'gatherpress-relations' ); ?></li>
				</ul>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'Key Principles Throughout', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__callout gatherpress-relations__callout--key">
				<p class="gatherpress-relations__callout-title"><?php esc_html_e( 'Guiding Principles', 'gatherpress-relations' ); ?></p>
				<p class="gatherpress-relations__callout-text">
					<?php esc_html_e( '1. post_type_supports first — every feature is a named support string. 2. Shadow-source first — use gatherpress-shadow-source for every entity that needs many-to-many relationships. 3. Consume, don\'t own — the gatherpress_play post type is provided by gatherpress-productions. 4. Agnostic by default — use filters and the sourcePostType attribute pattern. 5. REST-first — every data relationship should be queryable via the REST API. 6. Block-first — every UI element is a registered Gutenberg block. 7. Cache aggressively. 8. Degrade gracefully. 9. Wire late — all discovery and wiring runs on init:100.', 'gatherpress-relations' ); ?>
				</p>
			</div>

			<h4 class="gatherpress-relations__heading"><?php esc_html_e( 'File Structure', 'gatherpress-relations' ); ?></h4>
			<div class="gatherpress-relations__code-block">
				<code class="gatherpress-relations__code">gatherpress-relations/
├── gatherpress-relations.php      # Plugin bootstrap (Singleton)
├── webpack.config.js              # Custom webpack config for multi-block builds
├── src/
│   └── blocks/
│       ├── roadmap/               # Roadmap block source
│       │   ├── block.json
│       │   ├── index.js
│       │   ├── edit.js
│       │   ├── style.scss
│       │   ├── editor.scss
│       │   ├── view.js
│       │   └── render.php
│       ├── cast-crew-list/        # ✅ Cast &amp; Crew List block
│       │   ├── block.json
│       │   ├── index.js
│       │   ├── edit.js
│       │   ├── style.scss
│       │   ├── editor.scss
│       │   ├── view.js
│       │   └── render.php
│       └── recent-roles/          # ✅ Person Recent Roles block
│           ├── block.json
│           ├── index.js
│           ├── edit.js
│           ├── style.scss
│           ├── editor.scss
│           └── render.php
├── includes/
│   ├── class-post-types.php       # gatherpress_person CPT registration ONLY
│   ├── class-supports.php         # Defines &amp; discovers post_type_supports
│   ├── class-bridge.php           # add_post_type_support() calls
│   ├── class-meta.php             # Post meta registration
│   ├── class-rest-endpoints.php   # Custom REST routes
│   └── class-cache.php            # Transient &amp; object cache helpers
├── blocks/                        # (future: additional blocks)
│   ├── person-recent-roles/       # Phase 2 (custom block)
│   ├── where-seen/                # Phase 3
│   └── upcoming-for-you/          # Phase 4
├── patterns/                      # Block patterns (registered via PHP)
│   ├── ensemble-grid.php          # Phase 2 (core/query + query-filter)
│   └── person-timeline.php        # Phase 2 (core/query)
└── templates/
	└── single-gatherpress_person.html</code>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}

/*
 * Render the block.
 */
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All output is escaped within the renderer methods.
echo GatherPress_Relations_Renderer::get_instance()->render( $attributes );
