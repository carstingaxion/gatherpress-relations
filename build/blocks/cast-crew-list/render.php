<?php
/**
 * Server-side render callback for the Cast & Crew List block.
 *
 * HOW this file is invoked:
 * ─────────────────────────
 * block.json declares `"render": "file:./render.php"`. WordPress
 * requires this file when the block is encountered in post_content,
 * providing $attributes, $content, and $block in local scope.
 *
 * HOW the data flows:
 * ───────────────────
 * 1. Reads the consumer post's `_gatherpress_relations` meta (JSON).
 * 2. Parses it into an array of person-role assignment objects.
 * 3. Groups entries by department.
 * 4. Sorts each department group by `billing_order`.
 * 5. For each entry, resolves the person post via `person_post_id`
 *    to fetch the name, headshot, and permalink.
 * 6. Renders the grouped roster as accessible, semantic HTML.
 *
 * WHY a Singleton renderer?
 * If the block appears multiple times on a page (e.g., one in the
 * main content, one in a sidebar), we avoid class redeclaration
 * and keep a single instance in memory.
 *
 * AVAILABLE VARIABLES:
 * ```
 * $attributes = [
 *     'showHeadshots'          => bool,
 *     'headShotSize'           => string ('thumbnail'|'medium'),
 *     'showDepartmentHeadings' => bool,
 *     'columns'                => int,
 *     'linkToPersonPage'       => bool,
 * ];
 * $content = '';
 * $block   = WP_Block;
 * ```
 *
 * The block is available on any post type that declares
 * `gatherpress-relations-from` support.
 *
 * @package GatherPressRelations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GatherPress_Relations_Cast_Crew_Renderer' ) ) {

	/**
	 * Class GatherPress_Relations_Cast_Crew_Renderer
	 *
	 * Singleton that produces the Cast & Crew List block HTML.
	 *
	 * @since 0.1.0
	 */
	class GatherPress_Relations_Cast_Crew_Renderer {

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
		 * Returns the default department configuration.
		 *
		 * HOW the filter works:
		 * Themes or plugins can customise the department list and
		 * display order via `gatherpress_relations_departments`.
		 * The array keys are slugs stored in the meta; values are
		 * display labels.
		 *
		 * @since  0.1.0
		 * @return array<string, string> Department slug => label map.
		 */
		/**
		 * Returns the department configuration for a given source type.
		 *
		 * HOW the filter works:
		 * Themes or plugins can customise the department list per
		 * source post type via `gatherpress_relations_departments`.
		 * The second parameter receives the source post type slug
		 * (e.g., `gatherpress_person`, `gatherpress_sponsor`) so the
		 * callback can return different departments for different
		 * connectable types — sponsors get tiers, persons get roles.
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
		 * Renders the Cast & Crew List block.
		 *
		 * @since  0.1.0
		 * @param  array<string, mixed> $attributes Block attributes.
		 * @param  WP_Block             $block      The parsed block instance.
		 * @return string Complete HTML for the block.
		 */
		public function render( array $attributes, WP_Block $block ): string {
			$post_id = $block->context['postId'] ?? get_the_ID();

			if ( ! $post_id ) {
				return '';
			}

			/*
			 * Step 1: Read and parse the relations meta.
			 *
			 * WHY json_decode with `true`?
			 * Forces associative arrays instead of stdClass objects,
			 * making array_filter and usort callbacks cleaner.
			 */
			$raw_meta  = get_post_meta( $post_id, '_gatherpress_relations', true );
			$relations = json_decode( $raw_meta ?: '[]', true );

			if ( ! is_array( $relations ) || empty( $relations ) ) {
				return '';
			}

			/*
			 		 * Filter by sourcePostType attribute.
		 *
		 * HOW this works:
		 * When the editor selects a specific source post type (e.g.,
		 * 'gatherpress_person'), only entries whose `type` field
		 * matches are rendered. Entries without the field default to
		 * 'gatherpress_person' for backward compatibility. When the
		 * attribute is empty or 'all', no filtering occurs.
		 */
		$source_post_type = $attributes['sourcePostType'] ?? '';
		if ( ! empty( $source_post_type ) && 'all' !== $source_post_type ) {
			$relations = array_values(
				array_filter(
					$relations,
					function ( array $entry ) use ( $source_post_type ): bool {
						$entry_source = $entry['type'] ?? 'gatherpress_person';
						return $entry_source === $source_post_type;
					}
				)
			);

				if ( empty( $relations ) ) {
					return '';
				}
			}

			/*
			 * Step 2: Group by department.
			 */
			$grouped = array();
			foreach ( $relations as $entry ) {
				$dept = $entry['department'] ?? 'other';
				if ( ! isset( $grouped[ $dept ] ) ) {
					$grouped[ $dept ] = array();
				}
				$grouped[ $dept ][] = $entry;
			}

					/*
		 * Step 3: Sort each department by order.
		 */
		foreach ( $grouped as &$members ) {
			usort(
				$members,
				function ( array $a, array $b ): int {
					return ( $a['order'] ?? 99 ) <=> ( $b['order'] ?? 99 );
				}
			);
		}
			unset( $members );

			/*
			 * Step 4: Render using the department display order.
			 */
			/*
			 * Resolve the source post type for department configuration.
			 *
			 * WHY source type, not consumer type?
			 * Departments describe the connectable entity (person, sponsor),
			 * not the consumer (event, production). A sponsor always uses
			 * Gold/Silver/Bronze tiers regardless of the consumer.
			 *
			 * When `sourcePostType` is set and not 'all', use it directly.
			 * Otherwise, infer the source type from the entries' `type` field.
			 * Fall back to empty string (global default).
			 */
			$dept_source_type = '';
			if ( ! empty( $source_post_type ) && 'all' !== $source_post_type ) {
				$dept_source_type = $source_post_type;
			} elseif ( ! empty( $relations ) ) {
				// Use the first entry's type as the representative source type.
				$dept_source_type = $relations[0]['type'] ?? 'gatherpress_person';
			}
			$departments = $this->get_departments( $dept_source_type );
			$show_headshots = ! empty( $attributes['showHeadshots'] );
			$headshot_size  = $attributes['headShotSize'] ?? 'thumbnail';
			$show_headings  = ! empty( $attributes['showDepartmentHeadings'] );
			$columns        = max( 1, min( 6, (int) ( $attributes['columns'] ?? 3 ) ) );
			$link_to_person = ! empty( $attributes['linkToPersonPage'] );
			$heading_level  = max( 1, min( 6, (int) ( $attributes['departmentHeadingLevel'] ?? 3 ) ) );
			$heading_tag    = 'h' . $heading_level;

			$wrapper_attrs = get_block_wrapper_attributes(
				array(
					'style' => '--cast-crew-columns:' . $columns . ';',
				)
			);

			ob_start();
			?>
			<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="cast-crew">
					<?php
					/*
					 * Iterate departments in display order.
					 *
					 * WHY $departments (the filtered config) as the iteration source?
					 * It guarantees a consistent display order matching the
					 * theme/plugin configuration, regardless of what order the
					 * entries appear in the JSON meta.
					 */
					foreach ( $departments as $dept_slug => $dept_label ) {
						if ( empty( $grouped[ $dept_slug ] ) ) {
							continue;
						}
						?>
						<section class="cast-crew__department" data-department="<?php echo esc_attr( $dept_slug ); ?>">
							<?php if ( $show_headings ) { ?>
								<?php
								printf(
									'<%1$s class="wp-block-heading">%2$s</%1$s>',
									esc_attr( $heading_tag ),
									esc_html( $dept_label )
								);
								?>
							<?php } ?>

							<div class="cast-crew__grid">
								<?php
								foreach ( $grouped[ $dept_slug ] as $entry ) {
									$person_post_id = $entry['id'] ?? 0;
									$person_post    = $person_post_id ? get_post( $person_post_id ) : null;
									$person_name    = $person_post ? get_the_title( $person_post ) : ( $entry['slug'] ?? '' );
									$role_name      = $entry['role'] ?? '';
									$permalink      = $person_post && $link_to_person ? get_permalink( $person_post ) : '';
									$headshot_url   = '';

									if ( $show_headshots && $person_post_id ) {
										$headshot_url = get_the_post_thumbnail_url( $person_post_id, $headshot_size );
									}

									/*
									 * Fallback avatar: when no featured image exists
									 * and headshots are enabled, use the WordPress
									 * default avatar from Settings → Discussion.
									 *
									 * HOW this works:
									 * `get_avatar_url()` accepts an ID hash or email.
									 * Person posts are not WP users, so we pass a
									 * deterministic hash derived from the person's
									 * post ID. The `default` arg is set to the site's
									 * configured default (from `get_option('avatar_default')`),
									 * and `force_default` ensures the Gravatar service
									 * returns the default image rather than attempting
									 * to look up a real Gravatar for a non-existent email.
									 *
									 * WHY force_default?
									 * Person posts do not have associated email addresses.
									 * Without force_default, get_avatar_url() would hash
									 * the input and query Gravatar, potentially returning
									 * the "mystery person" icon regardless of the site's
									 * Discussion setting. force_default ensures the site
									 * admin's chosen default avatar style is respected.
									 */
									$fallback_avatar_url = '';
									if ( $show_headshots && ! $headshot_url ) {
										$avatar_default = get_option( 'avatar_default', 'mystery' );
										$headshot_px    = 'medium' === $headshot_size ? 300 : 150;
										$fallback_avatar_url = get_avatar_url(
											'person-' . $person_post_id . '@placeholder.invalid',
											array(
												'size'          => $headshot_px,
												'default'       => $avatar_default,
												'force_default' => true,
											)
										);
									}

									/*
									 * Determine the wrapper tag.
									 *
									 * WHY conditional tag?
									 * If linking is enabled and the person has a
									 * permalink, we wrap the entire card in an <a>.
									 * Otherwise we use a <div> to avoid invalid HTML
									 * (an <a> without an href is technically valid
									 * but semantically incorrect for a "card").
									 */
									$tag       = $permalink ? 'a' : 'div';
									$href_attr = $permalink ? ' href="' . esc_url( $permalink ) . '"' : '';
									?>
									<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $href_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="cast-crew__card">
										<?php if ( $show_headshots ) { ?>
											<?php if ( $headshot_url ) { ?>
												<img
													class="cast-crew__headshot"
													src="<?php echo esc_url( $headshot_url ); ?>"
													alt="<?php echo esc_attr( $person_name ); ?>"
													loading="lazy"
													decoding="async"
												/>
											<?php } elseif ( $fallback_avatar_url ) { ?>
												<img
													class="cast-crew__headshot cast-crew__headshot--avatar"
													src="<?php echo esc_url( $fallback_avatar_url ); ?>"
													alt="<?php echo esc_attr( $person_name ); ?>"
													loading="lazy"
													decoding="async"
												/>
											<?php } else { ?>
												<div class="cast-crew__headshot-placeholder" aria-hidden="true">
													<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
														<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
													</svg>
												</div>
											<?php } ?>
										<?php } ?>
										<span class="cast-crew__name"><?php echo esc_html( $person_name ); ?></span>
										<?php if ( $role_name ) { ?>
											<span class="cast-crew__role"><?php echo esc_html( $role_name ); ?></span>
										<?php } ?>
									</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php } ?>
							</div>
						</section>
					<?php } ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}

/*
 * Render the block.
 *
 * WHY pass $block?
 * The renderer needs $block->context['postId'] to read the correct
 * post's meta — especially important when the block is used inside
 * a Query Loop where the post context differs from the main query.
 */
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All output escaped in renderer methods.
echo GatherPress_Relations_Cast_Crew_Renderer::get_instance()->render( $attributes, $block );
