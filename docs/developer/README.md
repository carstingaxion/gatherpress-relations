# GatherPress Relations — Developer Documentation

## Overview

GatherPress Relations connects **persons** to **productions** (or any special post type) with **roles**, using GatherPress shadow taxonomies as the relationship primitive and structured post meta as junction metadata.

### Fixed Architectural Decisions

1. **Both people and productions are post types** with `gatherpress-shadow-source` support.
2. The production post type (`gatherpress_play`) is **provided by `gatherpress-productions`** — we consume it, never register it.
3. We register a **`gatherpress_person` post type** with `gatherpress-shadow-source` support.
4. All new functionality is gated behind **two `post_type_supports` strings** — nothing is hard-bound to any post type slug:
   - `gatherpress-relations-from` — declares a **consumer** (the post type that carries `_gatherpress_relations` meta and tags itself with shadow terms).
   - `gatherpress-relations-to` — declares a **connectable source** (the post type whose shadow taxonomy gets wired onto consumers).
5. Discovery uses WP core's **`get_post_types_by_support()`** for both strings.
6. Roles are stored as **`_gatherpress_relations` post meta** on the consumer post (junction metadata).
7. All wiring runs on **`init:100`** to ensure every CPT is registered.

---

## Phase 1: Foundation (Implemented)

Phase 1 establishes the data layer — post types, support flags, shadow taxonomy wiring, and junction meta.

### What's Included

| Component | Location | Description |
|-----------|----------|-------------|
| `gatherpress_person` CPT | `includes/classes/class-person-cpt.php` → `Person_CPT` | Registers the person post type with `gatherpress-shadow-source` support |
| Bridge | `includes/classes/class-bridge.php` → `Bridge` | Adds `gatherpress-relations-from` support to `gatherpress_play` (if active) |
| Wiring | `includes/classes/class-wiring.php` → `Wiring` | Discovers consumers, registers `_gatherpress_relations` meta, wires shadow taxonomy |
| Editor hooks | `src/index.js` → `useSupportsRelations()`, `useRelationConsumerTypes()` | React hooks for post type support discovery via core data store |

### Hook Execution Order

```
init:10  → GatherPressRelations\Person_CPT::register()
           Registers gatherpress_person with gatherpress-shadow-source support.
           Adds gatherpress-relations-to support to gatherpress_person.

init:10  → GatherPressRelations\Setup::register_block()
           Registers all plugin blocks from build/.

init:50  → GatherPressRelations\Bridge::add_support_to_external_types()
           Calls add_post_type_support( 'gatherpress_play', 'gatherpress-relations-from' )
           and add_post_type_support( 'gatherpress_event', 'gatherpress-relations-from' )
           if the respective CPTs exist.

init:100 → GatherPressRelations\Wiring::wire()
           1. get_post_types_by_support( 'gatherpress-relations-from' ) → discovers consumers.
           2. get_post_types_by_support( 'gatherpress-relations-to' )   → discovers sources.
           3. register_post_meta() for _gatherpress_relations on each consumer.
           4. For EACH source type, calls
              register_taxonomy_for_object_type( '_<source_type>', $consumer )
              for every consumer — wiring ALL source shadow taxonomies onto
              ALL consumers.
```

### The `gatherpress_person` Post Type

Registered with the following supports:

- `title` — Person name
- `editor` — Rich biography content
- `thumbnail` — Headshot / profile photo
- `excerpt` — Short bio
- `custom-fields` — Additional metadata
- `gatherpress-shadow-source` — Auto-creates `_gatherpress_person` shadow taxonomy

The shadow taxonomy `_gatherpress_person`:
- Is hidden (`show_ui => false`)
- Appears in REST (`show_in_rest => true`)
- Terms mirror published person posts (e.g., post `jane-doe` → term `_jane-doe`)
- Terms sync automatically when posts are renamed or deleted

### The `gatherpress-relations-from` Support String (Consumers)

A feature flag that any post type can declare to become a **consumer** — a post type that can receive person/source connections:

1. Every source type's shadow taxonomy (e.g., `_gatherpress_person`) wired onto it via `register_taxonomy_for_object_type()`
2. The `_gatherpress_relations` post meta registered
3. The Cast & Crew block and sidebar panel available in its editor context

**How to opt in:**

```php
// In your plugin or theme:
add_action( 'init', function(): void {
    add_post_type_support( 'my_custom_type', 'gatherpress-relations-from' );
}, 50 );
```

Or directly in registration:

```php
register_post_type( 'my_custom_type', [
    'supports' => [ 'title', 'editor', 'gatherpress-relations-from' ],
] );
```

### The `gatherpress-relations-to` Support String (Sources)

A feature flag that any post type can declare to become a **connectable source** — a post type whose shadow taxonomy gets wired onto all consumer post types:

1. The post type's shadow taxonomy (`_<post_type>`) is wired onto every consumer via `register_taxonomy_for_object_type()`
2. The post type appears in the sidebar's source type selector
3. The post type's posts are searchable in the "Add entry" UI

**Requirements:**
- The post type **must also** declare `gatherpress-shadow-source` support so GatherPress creates the shadow taxonomy.

**How to opt in:**

```php
register_post_type( 'my_sponsor', [
    'supports' => [ 'title', 'editor', 'thumbnail', 'gatherpress-shadow-source', 'gatherpress-relations-to' ],
    // ...
] );
```

The `gatherpress_person` post type declares both `gatherpress-shadow-source` and `gatherpress-relations-to` out of the box.

#### Example: Registering a Sponsor source type

A companion plugin can register its own connectable source type. The
following example adds a `gatherpress_sponsor` post type whose shadow
taxonomy (`_gatherpress_sponsor`) is automatically wired onto every
consumer post type at `init:100`. It also overrides the department
list so that sponsors are grouped into Gold / Silver / Bronze tiers
instead of the default Speaker / Moderation / Host departments:

```php
<?php
/**
 * Plugin Name: GatherPress Sponsors
 * Requires plugins: gatherpress, gatherpress-relations
 */

add_action( 'init', function (): void {
    register_post_type( 'gatherpress_sponsor', array(
        'label'        => 'Sponsors',
        'labels'       => array(
            'name'          => 'Sponsors',
            'singular_name' => 'Sponsor',
            'add_new_item'  => 'Add New Sponsor',
            'edit_item'     => 'Edit Sponsor',
        ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-money-alt',
        'supports'     => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'custom-fields',
            'gatherpress-shadow-source',
            'gatherpress-relations-to',
        ),
        'rewrite' => array( 'slug' => 'sponsor' ),
    ) );
} );

/**
 * Override departments for the sponsor source type.
 *
 * When the Cast & Crew block (or sidebar panel) works with
 * `gatherpress_sponsor` entries, sponsors are grouped into
 * Gold / Silver / Bronze tiers instead of the default
 * Speaker / Moderation / Host departments.
 *
 * The filter receives the source post type slug as its second
 * parameter. This makes the departments follow the connectable
 * type, not the consumer — so sponsors always use tiers whether
 * they are assigned to events, productions, or any other consumer.
 */
add_filter(
    'gatherpress_relations_departments',
    function ( array $departments, string $source_type ): array {
        if ( 'gatherpress_sponsor' === $source_type ) {
            return array(
                'gold'   => __( 'Gold',   'gatherpress-sponsors' ),
                'silver' => __( 'Silver', 'gatherpress-sponsors' ),
                'bronze' => __( 'Bronze', 'gatherpress-sponsors' ),
            );
        }
        return $departments;
    },
    10,
    2
);
```

After activation the plugin wires automatically:

1. `_gatherpress_sponsor` shadow taxonomy is created by GatherPress
   core (via `gatherpress-shadow-source` support).
2. At `init:100`, `Wiring::wire()` discovers `gatherpress_sponsor`
   via `get_post_types_by_support( 'gatherpress-relations-to' )` and
   calls `register_taxonomy_for_object_type( '_gatherpress_sponsor', $consumer )`
   for every consumer post type.
3. The sidebar plugin lists `gatherpress_sponsor` in the "Source
   type" dropdown. Editors can search for sponsors, assign them to
   events with a tier (Gold / Silver / Bronze), and the Cast & Crew
   block renders them grouped by tier.
4. The `sourcePostType` attribute on the Cast & Crew block can be
   set to `gatherpress_sponsor` to filter the roster to sponsors
   only, or left on "All" to show both people and sponsors.

### The `_gatherpress_relations` Post Meta

Stored on consumer posts. JSON-encoded array of person-role assignments:

```json
[
  {
    "slug": "_jane-doe",
    "id": 15,
    "type": "gatherpress_person",
    "role": "Hamlet",
    "department": "cast",
    "order": 1
  },
  {
    "slug": "_sam-chen",
    "id": 8,
    "type": "gatherpress_person",
    "role": "Director",
    "department": "direction",
    "order": 1
  }
]

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `slug` | `string` | The shadow term slug (e.g., `_jane-doe`). Canonical link that survives re-imports. |
| `id` | `int` | Denormalized source post ID for fast rendering. |
| `type` | `string` | The source post type slug (e.g., `gatherpress_person`). Enables the Cast & Crew block to filter by source type. |
| `role` | `string` | The role name, contextual to this relationship. |
| `department` | `string` | Grouping category: `speaker`, `moderation`, `host`, or any custom department. |
| `order` | `int` | Sort order within the department. |### Editor Hooks (JavaScript)

Two hooks are exported from `src/index.js` for use in block editor components:

#### `useSupportsRelations()`

Checks if the current post type supports `gatherpress-relations-from`.

```js
import { useSupportsRelations } from './index';

function MyComponent() {
    const { supportsRelations, isResolving } = useSupportsRelations();

    if ( isResolving ) return <Spinner />;
    if ( ! supportsRelations ) return <p>Not supported.</p>;

    return <RelationsUI />;
}
```

#### `useRelationConsumerTypes()`

Returns all post types that declare `gatherpress-relations-from` support.

```js
import { useRelationConsumerTypes } from './index';

function ConsumerList() {
    const { consumerTypes, isResolving } = useRelationConsumerTypes();

    if ( isResolving ) return <Spinner />;

    return (
        <ul>
            { consumerTypes.map( ( type ) => (
                <li key={ type.slug }>{ type.name }</li>
            ) ) }
        </ul>
    );
}
```

### Dependency Handling

GatherPress core is a **hard dependency** declared via `Requires plugins: gatherpress` in the plugin header. WordPress prevents activation without it — no admin notice or graceful degradation needed.

| Dependency | Status | Behavior |
|------------|--------|----------|
| GatherPress core | Not active | WordPress prevents plugin activation. |
| gatherpress-productions | Not active | Bridge silently skips `gatherpress_play`. No error. |
| Both active | Full functionality | Shadow taxonomy wired, meta registered, all features available. |

### Class Architecture

All classes use `GatherPress\Core\Traits\Singleton` from GatherPress core
instead of a custom Singleton trait — keeping the codebase consistent with
the GatherPress ecosystem.

```
GatherPress\Core\Traits\Singleton
  ├── GatherPressRelations\Person_CPT      (init:10)
  │     Registers gatherpress_person CPT with gatherpress-shadow-source
  │     + gatherpress-relations-to support.
  ├── GatherPressRelations\Bridge          (init:50)
  │     Adds gatherpress-relations-from to external CPTs.
  ├── GatherPressRelations\Wiring          (init:100)
  │     Discovers consumers (gatherpress-relations-from) and sources
  │     (gatherpress-relations-to), registers meta, wires ALL source
  │     shadow taxonomies onto ALL consumers.
  └── GatherPressRelations\Setup           (init:10 + enqueue_block_editor_assets)
```

All classes live in `includes/classes/` under the `GatherPressRelations` namespace,
autoloaded via the `gatherpress_autoloader` filter.

### Meta Field Naming Convention

The `_gatherpress_relations` meta fields use short, generic names:

| Field | Description |
|-------|-------------|
| `slug` | Shadow term slug (e.g., `_jane-doe`). Canonical identifier. |
| `id` | Source post ID. Denormalized for fast rendering. |
| `type` | Source post type slug (e.g., `gatherpress_person`). |
| `role` | Role name, contextual to this relationship. |
| `department` | Grouping category slug. |
| `order` | Sort position within the department. |

---

## Cast & Crew List Block (Phase 1)

The `gatherpress/cast-crew-list` block is a dynamic block that renders a grouped roster of persons connected to the current consumer post.

### Block Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `showHeadshots` | `boolean` | `true` | Show person featured images as circular headshots |
| `headShotSize` | `string` | `"thumbnail"` | WordPress image size for headshots (`thumbnail` or `medium`) |
| `showDepartmentHeadings` | `boolean` | `true` | Show department section headings |
| `columns` | `number` | `3` | Grid columns (1–6, responsive via container queries) |
| `linkToPersonPage` | `boolean` | `true` | Wrap cards in links to person single pages |
| `departmentHeadingLevel` | `number` | `3` | Heading level (1–6) for department titles |
| `sourcePostType` | `string` | `"gatherpress_person"` | Filter entries to a specific source type or "all" |

### Block Context

The block uses `postId` and `postType` from block context, making it compatible with Query Loop placement where the post context differs from the main query.

### Data Flow (Cast & Crew List)

1. Reads `_gatherpress_relations` post meta from the current post (via block context `postId`).
2. Parses the JSON into an array of person-role assignment objects.
3. Groups entries by `department` field.
4. Sorts each department group by `order`.
5. Resolves each `id` to fetch name, headshot URL, and permalink.
6. Renders grouped HTML with department sections, responsive grid, and card elements.

### Department Configuration

Departments are configurable via the `gatherpress_relations_departments` filter. The filter receives two parameters: the departments array and the **source post type slug** (the connectable type, e.g., `gatherpress_person` or `gatherpress_sponsor`). This allows different departments per source type — so people might use Speaker/Moderation/Host while sponsors use Gold/Silver/Bronze, regardless of which consumer post type they are assigned to.

```php
// Global override — applies to all source types without a specific override.
add_filter( 'gatherpress_relations_departments', function ( array $departments, string $source_type ): array {
    $departments['orchestra'] = __( 'Orchestra', 'my-theme' );
    return $departments;
}, 10, 2 );

// Per-source-type override — persons get meetup roles, sponsors get tiers.
add_filter( 'gatherpress_relations_departments', function ( array $departments, string $source_type ): array {
    if ( 'gatherpress_person' === $source_type ) {
        return [
            'speaker'    => __( 'Speaker', 'my-theme' ),
            'moderation' => __( 'Moderation', 'my-theme' ),
            'host'       => __( 'Host', 'my-theme' ),
        ];
    }
    if ( 'gatherpress_sponsor' === $source_type ) {
        return [
            'gold'   => __( 'Gold', 'my-theme' ),
            'silver' => __( 'Silver', 'my-theme' ),
            'bronze' => __( 'Bronze', 'my-theme' ),
        ];
    }
    return $departments;
}, 10, 2 );
```

Default departments: Speaker, Moderation, Host.

Per-source-type overrides are reflected in the editor sidebar (department dropdown), the block canvas preview (department headings), and the frontend rendering — all three contexts receive the filtered configuration for the active source post type.

### Editor Experience

The sidebar panel provides:

- **Person search** — searches `gatherpress_person` posts via REST API
- **Role input** — free text field for the role name
- **Department selector** — dropdown with configurable options
- **Entry management** — reorder (up/down), edit inline, remove
- **Live preview** — the canvas shows the grouped roster in real-time

### File Structure

```
src/blocks/cast-crew-list/
├── block.json      # Block metadata, attributes, context, assets
├── index.js        # Block registration
├── edit.js         # Editor component with sidebar management UI
├── render.php      # Server-side rendering with Singleton renderer
├── style.scss      # Shared styles (editor + frontend)
└── editor.scss     # Editor-only styles for sidebar UI
```

---

## Recent Relations Block (Phase 2)

The `gatherpress/recent-relations` block is a dynamic block that renders a list of recent relations a source post has across connected consumer posts. All labels are context-aware — they use the registered singular/plural labels for the current post type and for the referenced consumer post types.

### Block Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `maxRoles` | `number` | `10` | Maximum number of roles to display (1–50) |
| `showDepartment` | `boolean` | `true` | Show department badge next to each role |
| `groupByDepartment` | `boolean` | `false` | Group roles by department with section headings |
| `showPostType` | `boolean` | `false` | Show the consumer post type label (Production, Event, etc.) |
| `showDate` | `boolean` | `true` | Show the consumer post's date |
| `dateFormat` | `string` | `"Y"` | Date format: `Y` (year only), `M Y` (month & year), `full` (site date format) |

### Block Context

The block uses `postId` and `postType` from block context, making it work correctly inside Query Loops iterating over any source post type (gatherpress_person, gatherpress_sponsor, etc.).

### Data Flow

1. Resolves the current person post from block context (`postId`).
2. Derives the shadow term slug: `_<post_name>`.
3. Looks up the shadow term in the `_<post_type>` shadow taxonomy.
4. Uses `get_post_types_by_support( 'gatherpress-relations-from' )` to discover all consumer post types — no slug is hard-coded.
5. Queries consumer posts via `WP_Query` with a `tax_query` on the shadow taxonomy.
6. For each consumer post, parses `_gatherpress_relations` meta and filters entries matching the current post's shadow slug.
7. Sorts results by consumer post date (descending) and caps at `maxRoles`.
8. Renders a semantic list with linked production titles, role names, department badges, and dates.

### Editor Experience

The editor preview queries the same data via the core data store:
- Fetches the source post's slug from `getEntityRecord`.
- Looks up the shadow term via `getEntityRecords` on the `_<post_type>` taxonomy.
- Discovers consumer post types via `getPostTypes` filtered by `gatherpress-relations-from` support.
- Queries consumer posts for each type with the shadow term filter.
- Parses `_gatherpress_relations` meta from each consumer post's `meta` REST field.
- All placeholder labels use `sprintf` with the current post type's registered labels.

InspectorControls provide toggles for department badges, grouping, post type labels, date display, and date format.

### File Structure

```
src/blocks/recent-roles/        # Source directory (builds to build/blocks/recent-relations/)
├── block.json      # Block metadata (name: gatherpress/recent-relations)
├── index.js        # Block registration
├── edit.js         # Editor component with context-aware labels
├── render.php      # Server-side rendering with Singleton renderer
├── style.scss      # Shared styles (editor + frontend)
└── editor.scss     # Editor-only style overrides
```

---

## Phase 3–4: Planned

See the roadmap block's rendered output for details on upcoming phases:

- **Phase 2 (remaining):** Ensemble Grid as a core/query pattern with `gatherpress-seasons` for season filtering and `humanmade/query-filter` for the filter UI; Person Production Timeline as a core/query pattern; transient caching; `single-gatherpress_person.html` template.

- **Phase 3 (GA):** Visualization blocks, REST endpoints, Shadow_Source utility integration.
- **Phase 4 (Future):** Denormalized lookup table, GatherPress RSVP integration, recommendation block.
