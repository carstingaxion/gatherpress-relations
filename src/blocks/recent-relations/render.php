<?php
/**
 * Server-side render callback for the Recent Relations block.
 *
 * HOW this file is invoked:
 * ─────────────────────────
 * block.json declares `"render": "file:./render.php"`. WordPress
 * requires this file when the block delimiter is encountered,
 * providing $attributes, $content, and $block in local scope.
 *
 * HOW the data flows:
 * ───────────────────
 * 1. Resolves the current person post from block context.
 * 2. Derives the shadow term slug (_<person_slug>).
 * 3. Looks up the shadow term in the _gatherpress_person taxonomy.
 * 4. Queries all consumer posts (post types with gatherpress-relations-from
 *    support) tagged with that shadow term.
 * 5. For each consumer post, parses _gatherpress_relations meta to
 *    extract entries matching this person's shadow slug.
 * 6. Sorts results by date descending.
 * 7. Renders a semantic list with linked production titles, role
 *    names, department badges, and dates.
 *
 * WHY a Singleton renderer?
 * Prevents class redeclaration if the block appears multiple times
 * on the same page (e.g., in a Query Loop with gatherpress_person
 * post type).
 *
 * AVAILABLE VARIABLES:
 * ```
 * $attributes = [
 *     'maxRoles'          => int,
 *     'showDepartment'    => bool,
 *     'groupByDepartment' => bool,
 *     'showPostType'      => bool,
 *     'showDate'          => bool,
 *     'dateFormat'        => string ('Y'|'M Y'|'full'),
 * ];
 * $content = '';
 * $block   = WP_Block;
 * ```
 *
 * @package GatherPressRelations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GatherPress_Relations_Recent_Roles_Renderer' ) ) {

	/**
	 * Class GatherPress_Relations_Recent_Roles_Renderer
	 *
	 * Singleton that produces the Recent Relations block HTML.
	 *
	 * @since 0.1.0
	 */
	class GatherPress_Relations_Recent_Roles_Renderer {

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
		 * Returns the department configuration via the shared filter.
		 *
		 * WHY the same filter as the Cast & Crew block?
		 * Departments are a domain concept shared across all relation
		 * blocks. Using the same filter ensures consistency — if a
		 * theme adds "Orchestra" via the filter, it appears in both
		 * blocks.
		 *
		 * @since  0.1.0
		 * @return array<string, string> Department slug => label map.
		 */
		/**
		 * Returns the department configuration for a given source post type.
		 *
		 * @since  0.1.0
		 * @param  string $source_type The source post type slug (default: '').
		 * @return array<string, string> Department slug => label map.
		 */
		private function get_departments( string $source_type = '' ): array {
			/** This filter is documented in includes/classes/class-setup.php */
			return apply_filters(
				'gatherpress_relations_departments',
				array(
					'speaker'    => __( 'Speaker', 'gatherpress-relations' ),
					'moderation' => __( 'Moderation', 'gatherpress-relations' ),
					'host'       => __( 'Host', 'gatherpress-relations' ),
				),
				$source_type
			);
		}

		/**
		 * Formats a post date according to the block's dateFormat attribute.
		 *
		 * @since  0.1.0
		 * @param  string $date_string The post's date string (Y-m-d H:i:s).
		 * @param  string $format      The format attribute ('Y', 'M Y', 'full').
		 * @return string The formatted date string.
		 */
		private function format_date( string $date_string, string $format ): string {
			$timestamp = strtotime( $date_string );
			if ( false === $timestamp ) {
				return '';
			}

			switch ( $format ) {
				case 'Y':
					return gmdate( 'Y', $timestamp );
				case 'M Y':
					return gmdate( 'M Y', $timestamp );
				case 'full':
					return wp_date( get_option( 'date_format' ), $timestamp );
				default:
					return gmdate( 'Y', $timestamp );
			}
		}

		/**
		 * Resolves the display date for a consumer post.
		 *
		 * HOW this works:
		 * ────────────────
		 * If the consumer post type declares `gatherpress-event-date`
		 * support, reads the GatherPress event start datetime meta
		 * (`gatherpress_datetime_start`) instead of the post's
		 * published date. Falls back to `post_date` when:
		 * - The post type does not support `gatherpress-event-date`.
		 * - The meta value is empty or not a valid datetime string.
		 *
		 * WHY check post_type_supports instead of the meta directly?
		 * A post might have leftover datetime meta from a previous
		 * configuration. The `gatherpress-event-date` support flag is
		 * the authoritative signal that the post type uses event dates
		 * as its canonical temporal reference.
		 *
		 * WHY `gatherpress_datetime_start`?
		 * GatherPress stores the event start datetime in this meta key
		 * as a plain datetime string (e.g., "2023-06-05 18:00:00").
		 *
		 * @since  0.1.0
		 * @param  WP_Post $consumer_post The consumer post object.
		 * @return string  The date string (Y-m-d H:i:s format) to use for display.
		 */
		private function resolve_consumer_date( WP_Post $consumer_post ): string {
			if ( ! post_type_supports( $consumer_post->post_type, 'gatherpress-event-date' ) ) {
				return $consumer_post->post_date;
			}

			$event_start = get_post_meta(
				$consumer_post->ID,
				'gatherpress_datetime_start',
				true
			);

			if ( ! empty( $event_start ) && is_string( $event_start ) && false !== strtotime( $event_start ) ) {
				return $event_start;
			}

			return $consumer_post->post_date;
		}

		/**
		 * Renders the Person Recent Roles block.
		 *
		 * @since  0.1.0
		 * @param  array<string, mixed> $attributes Block attributes.
		 * @param  WP_Block             $block      The parsed block instance.
		 * @return string Complete HTML for the block.
		 */
		public function render( array $attributes, WP_Block $block ): string {
			/*
			 * Step 1: Resolve the person post from block context.
			 *
			 * WHY block context instead of get_the_ID()?
			 * Inside a Query Loop, get_the_ID() returns the main query's
			 * post, not the loop iteration's post. Block context provides
			 * the correct post ID for the current iteration.
			 */
			$person_id = $block->context['postId'] ?? get_the_ID();

			if ( ! $person_id ) {
				return '';
			}

			$person_post = get_post( $person_id );

			if ( ! $person_post ) {
				return '';
			}

			/*
			 * Step 2: Derive the shadow term slug.
			 *
			 * WHY underscore prefix?
			 * The gatherpress-shadow-source convention prefixes all
			 * shadow term slugs with an underscore to distinguish them
			 * from sentinel terms.
			 */
			$person_slug = $person_post->post_name;
			$shadow_slug = '_' . $person_slug;

			/*
			 * Step 3: Look up the shadow term.
			 */
			/*
			 * Resolve the shadow taxonomy for this person's post type.
			 *
			 * WHY dynamic instead of hardcoding '_gatherpress_person'?
			 * The person post type is not the only possible source type.
			 * Any post type declaring `gatherpress-relations-to` support
			 * produces a shadow taxonomy named `_<post_type>`. We derive
			 * the taxonomy from the block context's postType, falling
			 * back to `_gatherpress_person` for backward compatibility.
			 */
			$source_post_type    = $person_post->post_type;
			$shadow_taxonomy     = '_' . $source_post_type;

			if ( ! taxonomy_exists( $shadow_taxonomy ) ) {
				return '';
			}

			$shadow_term = get_term_by( 'slug', $shadow_slug, $shadow_taxonomy );

			if ( ! $shadow_term || is_wp_error( $shadow_term ) ) {
				return '';
			}

			/*
			 * Step 4: Discover consumer post types.
			 *
			 		 * WHY get_post_types_by_support?
		 * This is the canonical, slug-agnostic way to find all
		 * post types that have opted into the relations system.
		 * No post type slug is hard-coded.
		 *
		 * If the `filterPostType` attribute is set to a non-empty
		 * string, only that specific post type is queried — provided
		 * it actually supports `gatherpress-relations-from`. This gives
			 * editors a way to scope the roles list to a single consumer
			 * type (e.g., only productions, only events).
			 */
			$all_consumer_types = get_post_types_by_support( 'gatherpress-relations-from' );

			if ( empty( $all_consumer_types ) ) {
				return '';
			}

			$filter_post_type = $attributes['filterPostType'] ?? '';

			if ( $filter_post_type && in_array( $filter_post_type, $all_consumer_types, true ) ) {
				$consumer_types = array( $filter_post_type );
			} else {
				$consumer_types = $all_consumer_types;
			}

			/*
			 * Step 5: Query consumer posts tagged with this person's
			 * shadow term.
			 *
			 * WHY a single WP_Query with multiple post types?
			 * WP_Query supports an array of post types, producing a
			 * single SQL query with a UNION-like IN clause. This is
			 * more efficient than N separate queries.
			 *
			 * WHY no_found_rows?
			 * We don't need pagination — we cap at maxRoles results.
			 * Skipping SQL_CALC_FOUND_ROWS improves performance.
			 */
			$max_roles = max( 1, min( 50, (int) ( $attributes['maxRoles'] ?? 10 ) ) );

			$query = new WP_Query(
				array(
					'post_type'      => $consumer_types,
					'posts_per_page' => $max_roles * 3, // Over-fetch to compensate for entries with multiple roles.
					'no_found_rows'  => true,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => $shadow_taxonomy,
							'terms'    => $shadow_term->term_id,
						),
					),
				)
			);

			if ( ! $query->have_posts() ) {
				return '';
			}

			/*
			 * Step 6: Extract role entries from each consumer post's
			 * _gatherpress_relations meta.
			 */
			$roles = array();

			foreach ( $query->posts as $consumer_post ) {
				$raw_meta  = get_post_meta( $consumer_post->ID, '_gatherpress_relations', true );
				$relations = json_decode( $raw_meta ?: '[]', true );

				if ( ! is_array( $relations ) ) {
					continue;
				}

							$person_entries = array_filter(
				$relations,
				function ( array $entry ) use ( $shadow_slug ): bool {
					return ( $entry['slug'] ?? '' ) === $shadow_slug;
				}
			);

				$post_type_obj = get_post_type_object( $consumer_post->post_type );

				/*
				 * Resolve the display date: use event date when the
				 * consumer post type supports gatherpress-event-date,
				 * otherwise fall back to the post's published date.
				 */
				$display_date = $this->resolve_consumer_date( $consumer_post );

							foreach ( $person_entries as $entry ) {
				$roles[] = array(
					'consumer_id'         => $consumer_post->ID,
					'consumer_title'      => get_the_title( $consumer_post ),
					'consumer_permalink'  => get_permalink( $consumer_post ),
					'consumer_type'       => $consumer_post->post_type,
					'consumer_type_label' => $post_type_obj ? $post_type_obj->labels->singular_name : $consumer_post->post_type,
					'consumer_date'       => $display_date,
					'role'                => $entry['role'] ?? '',
					'department'          => $entry['department'] ?? 'other',
					'order'               => $entry['order'] ?? 99,
					'source_type'         => $entry['type'] ?? $source_post_type,
				);
				}
			}

			/*
			 * Sort by date descending, then cap at maxRoles.
			 */
			usort(
				$roles,
				function ( array $a, array $b ): int {
					return strtotime( $b['consumer_date'] ) <=> strtotime( $a['consumer_date'] );
				}
			);

			$roles = array_slice( $roles, 0, $max_roles );

			if ( empty( $roles ) ) {
				return '';
			}

			/*
			 * Step 7: Render.
			 */
			$show_department    = ! empty( $attributes['showDepartment'] );
			$group_by_dept      = ! empty( $attributes['groupByDepartment'] );
			$show_post_type     = ! empty( $attributes['showPostType'] );
			$show_date          = ! empty( $attributes['showDate'] );
			$date_format        = $attributes['dateFormat'] ?? 'Y';
			/*
			 * Use the source post type for departments config at the block
			 * level. Since the recent-roles block is placed on a source
			 * post (e.g., a person page), all entries share the same source
			 * type. Per-item department labels are resolved individually
			 * in render_role_item() using the entry's source type.
			 */
			$departments_config = $this->get_departments( $source_post_type );

			ob_start();
			?>
			<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="recent-roles">
					<?php if ( $group_by_dept ) { ?>
						<?php
						/*
						 * Group roles by department.
						 */
						$grouped = array();
						foreach ( $roles as $role ) {
							$dept = $role['department'] ?? 'other';
							if ( ! isset( $grouped[ $dept ] ) ) {
								$grouped[ $dept ] = array();
							}
							$grouped[ $dept ][] = $role;
						}

						/*
						 * Iterate in department config order.
						 */
						foreach ( $departments_config as $dept_slug => $dept_label ) {
							if ( empty( $grouped[ $dept_slug ] ) ) {
								continue;
							}
							?>
							<div class="recent-roles__group">
								<h4 class="recent-roles__group-title">
									<?php echo esc_html( $dept_label ); ?>
								</h4>
								<ul class="recent-roles__list">
									<?php foreach ( $grouped[ $dept_slug ] as $role ) { ?>
										<?php $this->render_role_item( $role, $show_department, $show_post_type, $show_date, $date_format ); ?>
									<?php } ?>
								</ul>
							</div>
						<?php } ?>

						<?php
						/*
						 * Render any departments not in the config
						 * (edge case: custom department slug in meta).
						 */
						foreach ( $grouped as $dept_slug => $dept_roles ) {
							if ( isset( $departments_config[ $dept_slug ] ) ) {
								continue;
							}
							?>
							<div class="recent-roles__group">
								<h4 class="recent-roles__group-title">
									<?php echo esc_html( ucfirst( $dept_slug ) ); ?>
								</h4>
								<ul class="recent-roles__list">
									<?php foreach ( $dept_roles as $role ) { ?>
										<?php $this->render_role_item( $role, $show_department, $show_post_type, $show_date, $date_format ); ?>
									<?php } ?>
								</ul>
							</div>
						<?php } ?>

					<?php } else { ?>
						<ul class="recent-roles__list">
							<?php foreach ( $roles as $role ) { ?>
								<?php $this->render_role_item( $role, $show_department, $show_post_type, $show_date, $date_format ); ?>
							<?php } ?>
						</ul>
					<?php } ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Renders a single role list item.
		 *
		 * WHY a separate method?
		 * The same item markup is used in both flat and grouped modes.
		 * Extracting it avoids duplication and keeps each rendering
		 * path focused on its structural concern (flat list vs.
		 * department groups).
		 *
		 * @since  0.1.0
		 * @param  array<string, mixed> $role            Role data array.
		 * @param  bool                 $show_department  Whether to show the department badge.
		 * @param  bool                 $show_post_type   Whether to show the consumer post type label.
		 * @param  bool                 $show_date        Whether to show the date.
		 * @param  string               $date_format      The date format preference.
		 * @return void                                   Outputs HTML directly.
		 */
		private function render_role_item(
			array $role,
			bool $show_department,
			bool $show_post_type,
			bool $show_date,
			string $date_format
		): void {
			/*
			 * Resolve the department label using the source post type
			 * so that per-source-type department overrides are respected.
			 *
			 * For example, a sponsor's "gold" department label comes from
			 * the sponsor source type config, not from the consumer (event).
			 * The source type is stored in each relation entry's `type` field.
			 */
			$source_type_for_dept = $role['source_type'] ?? 'gatherpress_person';
			$departments_config   = $this->get_departments( $source_type_for_dept );
			$dept_label         = $departments_config[ $role['department'] ] ?? ucfirst( $role['department'] );
			$formatted_date     = $show_date ? $this->format_date( $role['consumer_date'], $date_format ) : '';
			?>
			<li class="recent-roles__item">
				<div class="recent-roles__item-main">
					<a class="recent-roles__consumer-link" href="<?php echo esc_url( $role['consumer_permalink'] ); ?>">
						<?php echo esc_html( $role['consumer_title'] ); ?>
					</a>
					<span class="recent-roles__role-name"><?php echo $role['role'] ? esc_html( $role['role'] ) : ''; ?></span>
				</div>
				<div class="recent-roles__item-meta">
					<?php if ( $show_department && $role['department'] ) { ?>
						<span class="recent-roles__department-badge">
							<?php echo esc_html( $dept_label ); ?>
						</span>
					<?php } ?>
					<?php if ( $show_post_type ) { ?>
						<span class="recent-roles__type-badge">
							<?php echo esc_html( $role['consumer_type_label'] ); ?>
						</span>
					<?php } ?>
					<?php if ( $show_date && $formatted_date ) { ?>
						<span class="recent-roles__date">
							<?php echo esc_html( $formatted_date ); ?>
						</span>
					<?php } ?>
				</div>
			</li>
			<?php
		}
	}
}

/*
 * Render the block.
 *
 * WHY pass $block?
 * The renderer needs $block->context['postId'] to resolve the correct
 * person — essential when the block is inside a Query Loop iterating
 * over gatherpress_person posts.
 */
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All output escaped in renderer methods.
echo GatherPress_Relations_Recent_Roles_Renderer::get_instance()->render( $attributes, $block );
