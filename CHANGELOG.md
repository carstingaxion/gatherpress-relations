# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased](https://github.com/carstingaxion/gatherpress-relations/compare/0.1.0...HEAD)

## [0.1.0](https://github.com/carstingaxion/gatherpress-relations/compare/0.1.0...0.1.0) - 2026-06-11

* Registers the `gatherpress_person` post type with `gatherpress-shadow-source` support, auto-creating a `_gatherpress_person` shadow taxonomy for many-to-many relationships.
* Introduces two `post_type_supports` strings — `gatherpress-relations-from` (consumers) and `gatherpress-relations-to` (connectable sources) — so any post type can opt in without hardcoded slugs.
* Bridges GatherPress Events and Productions into the relations system automatically when those plugins are active.
* Stores per-relationship roles as structured `_gatherpress_relations` JSON post meta (slug, id, type, role, department, order) on consumer posts.
* Ships the **Cast & Crew List** block with three style variations (Simple List, Compact Table, Cards), configurable columns, headshot display with avatar fallback, department headings with selectable heading level, and source type filtering.
* Ships the **Recent Relations** block with context-aware labels, department badges, date formatting (year / month-year / full), post type filter, and support for GatherPress event dates.
* Adds a **Manage Cast & Crew** sidebar panel available on every supporting post type — person search, role/department assignment, drag-and-drop reordering, and automatic shadow term syncing.
* Departments are filterable per source post type via `gatherpress_relations_departments`, enabling different department sets for different connectable types (e.g., Speaker/Moderation/Host for people, Gold/Silver/Bronze for sponsors).
* Includes developer documentation covering architecture, hook execution order, meta schema, editor hooks, and a full example for registering a custom source type with per-type departments.
* Ships a WordPress Playground blueprint with demo data for instant evaluation — six demo persons wired to GatherPress events with realistic meetup roles.
