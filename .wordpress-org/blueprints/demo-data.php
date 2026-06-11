<?php
/**
 * Plugin Name: GatherPress Relations demo data helper
 * Description: Generates demo data for the plugin.
 * Version:     0.1.0
 * Author:      carstenbach
 *
 * Demo data seeder for the GatherPress Relations Playground blueprint.
 *
 * HOW this script is invoked:
 * ───────────────────────────
 * The blueprint's `runPHP` step requires this file after it has been
 * written to `/tmp/demo-data.php` by the preceding `writeFile` step.
 * At this point WordPress, GatherPress core, and gatherpress-relations
 * are all active, and GatherPress demo events have been imported via
 * the WXR step.
 *
 * WHAT it does:
 * 1. Creates 6 gatherpress_person posts with realistic names and bios.
 * 2. Discovers all existing GatherPress events.
 * 3. Assigns persons to events with roles (Speaker, Moderation, Host)
 *    by writing `_gatherpress_relations` meta and wiring the
 *    `_gatherpress_person` shadow taxonomy terms onto each event.
 *    Events opt in via `gatherpress-relations-from` post type support.
 *
 * WHY idempotent by post_name?
 * The blueprint may be re-applied. Each person post is guarded by a
 * `get_page_by_path()` check to avoid duplicate entries.
 *
 * @package GatherPressRelations
 */

// Bootstrap WordPress.
require '/wordpress/wp-load.php';

/**
 * Step 1: Define person data.
 *
 * Each person has a slug, title, and short bio. These are realistic
 * but fictional community members for a WordPress meetup group.
 *
 * @var array<int, array{slug: string, title: string, bio: string}>
 */
$persons = array(
	array(
		'slug'  => 'alex-martinez',
		'title' => 'Alex Martinez',
		'bio'   => 'Full-stack developer and WordPress contributor. Runs the local WP meetup and organises the annual WordCamp. Passionate about open source, accessibility, and mentoring newcomers.',
	),
	array(
		'slug'  => 'priya-sharma',
		'title' => 'Priya Sharma',
		'bio'   => 'UX designer and block editor enthusiast. Regularly speaks about design systems, component libraries, and the intersection of design and development in WordPress.',
	),
	array(
		'slug'  => 'jordan-lee',
		'title' => 'Jordan Lee',
		'bio'   => 'Plugin developer specialising in custom blocks and the Interactivity API. Contributor to Gutenberg and several community plugins. Enjoys live-coding sessions.',
	),
	array(
		'slug'  => 'sam-nakamura',
		'title' => 'Sam Nakamura',
		'bio'   => 'Site reliability engineer with a focus on WordPress performance and hosting. Speaks frequently about caching strategies, Core Web Vitals, and sustainable infrastructure.',
	),
	array(
		'slug'  => 'maria-gonzalez',
		'title' => 'Maria Gonzalez',
		'bio'   => 'Community manager and content strategist. Coordinates volunteer programmes and diversity initiatives across the WordPress ecosystem. Experienced meetup host and moderator.',
	),
	array(
		'slug'  => 'chen-wei',
		'title' => 'Chen Wei',
		'bio'   => 'Theme developer and full-site editing advocate. Builds starter themes and patterns for the WordPress theme directory. Regular contributor to the Create Block Theme plugin.',
	),
);

/**
 * Step 2: Create person posts.
 *
 * Each person is inserted as a `gatherpress_person` post. The
 * `gatherpress-shadow-source` support on the post type auto-creates
 * a shadow term in the `_gatherpress_person` taxonomy when the post
 * is published (handled by GatherPress core's Shadow_Source hooks).
 */
$person_ids = array();

foreach ( $persons as $person ) {
	$existing = get_page_by_path( $person['slug'], OBJECT, 'gatherpress_person' );

	if ( $existing ) {
		$person_ids[ $person['slug'] ] = $existing->ID;
		continue;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'gatherpress_person',
			'post_status'  => 'publish',
			'post_title'   => $person['title'],
			'post_name'    => $person['slug'],
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $person['bio'] ) . '</p><!-- /wp:paragraph -->',
		)
	);

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		$person_ids[ $person['slug'] ] = $post_id;
	}
}

/**
 * Step 3: Discover events.
 *
 * Query all published GatherPress events. The WXR import from
 * gatherpress-demo-data creates several events that we can
 * attach persons to.
 */
$event_post_types = array();
if ( post_type_exists( 'gatherpress_event' ) ) {
	$event_post_types[] = 'gatherpress_event';
}

if ( empty( $event_post_types ) ) {
	// No event post type available — nothing to wire.
	return;
}

$events = get_posts(
	array(
		'post_type'      => $event_post_types,
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'ASC',
	)
);

if ( empty( $events ) ) {
	// No events found — nothing to wire.
	return;
}

/**
 * Step 4: Define role assignments.
 *
 * Maps event indices (0-based position in the $events array) to
 * person-role-department triples. If there are fewer events than
 * expected, assignments for missing indices are silently skipped.
 *
 * The assignments simulate a realistic meetup programme:
 * - Each event has 1–3 speakers, a moderator, and a host.
 * - Some persons appear in multiple events with different roles.
 *
 * @var array<int, array<int, array{person: string, role: string, department: string}>>
 */
$assignments = array(
	0 => array(
		array( 'person' => 'priya-sharma',   'role' => 'Designing with Blocks',        'department' => 'speaker' ),
		array( 'person' => 'maria-gonzalez', 'role' => 'Moderator',                    'department' => 'moderation' ),
		array( 'person' => 'alex-martinez',  'role' => 'Host',                          'department' => 'host' ),
	),
	1 => array(
		array( 'person' => 'jordan-lee',     'role' => 'Live-Coding Custom Blocks',     'department' => 'speaker' ),
		array( 'person' => 'chen-wei',       'role' => 'Theme Patterns Workshop',       'department' => 'speaker' ),
		array( 'person' => 'alex-martinez',  'role' => 'Moderator',                     'department' => 'moderation' ),
		array( 'person' => 'maria-gonzalez', 'role' => 'Host',                          'department' => 'host' ),
	),
	2 => array(
		array( 'person' => 'sam-nakamura',   'role' => 'Core Web Vitals Deep Dive',     'department' => 'speaker' ),
		array( 'person' => 'priya-sharma',   'role' => 'Moderator',                     'department' => 'moderation' ),
		array( 'person' => 'alex-martinez',  'role' => 'Host',                          'department' => 'host' ),
	),
	3 => array(
		array( 'person' => 'chen-wei',       'role' => 'Full-Site Editing Walkthrough', 'department' => 'speaker' ),
		array( 'person' => 'jordan-lee',     'role' => 'Interactivity API in Practice', 'department' => 'speaker' ),
		array( 'person' => 'sam-nakamura',   'role' => 'Moderator',                     'department' => 'moderation' ),
		array( 'person' => 'maria-gonzalez', 'role' => 'Host',                          'department' => 'host' ),
	),
	4 => array(
		array( 'person' => 'alex-martinez',  'role' => 'State of the Word Recap',       'department' => 'speaker' ),
		array( 'person' => 'priya-sharma',   'role' => 'Design Systems in WP',          'department' => 'speaker' ),
		array( 'person' => 'chen-wei',       'role' => 'Moderator',                     'department' => 'moderation' ),
		array( 'person' => 'jordan-lee',     'role' => 'Host',                          'department' => 'host' ),
	),
	5 => array(
		array( 'person' => 'maria-gonzalez', 'role' => 'Building Inclusive Communities', 'department' => 'speaker' ),
		array( 'person' => 'sam-nakamura',   'role' => 'WordPress at Scale',            'department' => 'speaker' ),
		array( 'person' => 'jordan-lee',     'role' => 'Moderator',                     'department' => 'moderation' ),
		array( 'person' => 'alex-martinez',  'role' => 'Host',                          'department' => 'host' ),
	),
);

/**
 * Step 5: Wire persons to events.
 *
 * For each event with assignments:
 * 1. Build the `_gatherpress_relations` meta array.
 * 2. Set the `_gatherpress_person` shadow taxonomy terms on the event.
 * 3. Save the meta.
 *
 * WHY resolve shadow terms manually?
 * The shadow term slug is `_<person_post_name>`. We look it up in
 * the `_gatherpress_person` taxonomy to get the term ID, then assign
 * it to the event post via `wp_set_object_terms()` (appending, not
 * replacing, to preserve any existing terms from the WXR import).
 */
foreach ( $assignments as $event_index => $entries ) {
	if ( ! isset( $events[ $event_index ] ) ) {
		continue;
	}

	$event = $events[ $event_index ];

	// Skip if this event already has relations meta (idempotency).
	$existing_meta = get_post_meta( $event->ID, '_gatherpress_relations', true );
	if ( ! empty( $existing_meta ) ) {
		continue;
	}

	$relations  = array();
	$term_ids   = array();
	$billing    = array(); // Per-department order counters.

	foreach ( $entries as $entry ) {
		$person_slug = $entry['person'];

		if ( ! isset( $person_ids[ $person_slug ] ) ) {
			continue;
		}

		$pid         = $person_ids[ $person_slug ];
		$shadow_slug = '_' . $person_slug;
		$department  = $entry['department'];

		// Increment order per department.
		if ( ! isset( $billing[ $department ] ) ) {
			$billing[ $department ] = 0;
		}
		$billing[ $department ] += 1;

		$relations[] = array(
			'slug'       => $shadow_slug,
			'id'         => $pid,
			'type'       => 'gatherpress_person',
			'role'       => $entry['role'],
			'department' => $department,
			'order'      => $billing[ $department ],
		);

		// Resolve the shadow term ID for taxonomy assignment.
		$shadow_term = get_term_by( 'slug', $shadow_slug, '_gatherpress_person' );
		if ( $shadow_term && ! is_wp_error( $shadow_term ) ) {
			$term_ids[] = (int) $shadow_term->term_id;
		}
	}

	if ( ! empty( $relations ) ) {
		update_post_meta(
			$event->ID,
			'_gatherpress_relations',
			wp_json_encode( $relations )
		);
	}

	if ( ! empty( $term_ids ) ) {
		wp_set_object_terms( $event->ID, $term_ids, '_gatherpress_person', true );
	}
}

/**
 * Step 6: Add the Cast & Crew List block to each event's post_content.
 *
 * HOW this works:
 * ────────────────
 * Appends a `<!-- wp:gatherpress/cast-crew-list /-->` block to each
 * event that received relation assignments. The block is dynamic —
 * it reads `_gatherpress_relations` meta at render time — so adding
 * the delimiter to post_content is sufficient.
 *
 * WHY append instead of replace?
 * Events imported via WXR already have content (description, venue
 * details, etc.). Appending preserves that content and adds the
 * cast & crew roster at the bottom.
 *
 * WHY the simple-list style?
 * The `is-style-simple-list` is the default and most compact style,
 * suitable for event pages where the roster is supplementary to the
 * main event description.
 */
foreach ( $assignments as $event_index => $entries ) {
	if ( ! isset( $events[ $event_index ] ) ) {
		continue;
	}

	$event           = $events[ $event_index ];
	$current_content = $event->post_content ?? '';

	// Skip if the block is already present (idempotency).
	if ( false !== strpos( $current_content, 'wp:gatherpress/cast-crew-list' ) ) {
		continue;
	}

	$block_markup = '<!-- wp:heading {"level":3} -->' . "\n"
		. '<h3 class="wp-block-heading">People</h3>' . "\n"
		. '<!-- /wp:heading -->' . "\n\n"
		. '<!-- wp:gatherpress/cast-crew-list {"showHeadshots":true,"showDepartmentHeadings":true,"linkToPersonPage":true,"departmentHeadingLevel":4} /-->';

	wp_update_post(
		array(
			'ID'           => $event->ID,
			'post_content' => $current_content . "\n\n" . $block_markup,
		)
	);
}

/**
 * Step 7: Add the Recent Roles block to each person's post_content.
 *
 * HOW this works:
 * ────────────────
 * Appends a `<!-- wp:gatherpress/person-recent-roles /-->` block to
 * each person post. The block queries consumer posts (events) tagged
 * with the person's shadow term and displays their role history.
 *
 * WHY append?
 * Person posts already have a bio paragraph from Step 2. The Recent
 * Roles block adds a dynamic "appearances" list below the bio,
 * showcasing the inverse relationship.
 */
foreach ( $person_ids as $person_slug => $pid ) {
	$person_post = get_post( $pid );

	if ( ! $person_post ) {
		continue;
	}

	$current_content = $person_post->post_content ?? '';

	// Skip if the block is already present (idempotency).
	if ( false !== strpos( $current_content, 'wp:gatherpress/recent-relations' ) ) {
		continue;
	}

	$block_markup = '<!-- wp:heading {"level":3} -->' . "\n"
		. '<h3 class="wp-block-heading">Recent Relations</h3>' . "\n"
		. '<!-- /wp:heading -->' . "\n\n"
		. '<!-- wp:gatherpress/recent-relations {"maxRoles":20,"showDepartment":true,"showDate":true,"dateFormat":"M Y"} /-->';

	wp_update_post(
		array(
			'ID'           => $pid,
			'post_content' => $current_content . "\n\n" . $block_markup,
		)
	);
}

/**
 * Step 8: Flush rewrite rules once.
 *
 * WHY flush here?
 * The gatherpress_person post type was just registered (when the
 * plugin activated) and person posts were created. Without a flush,
 * the `/person/<slug>/` URLs return 404 until the next manual
 * permalink save.
 */
flush_rewrite_rules();
