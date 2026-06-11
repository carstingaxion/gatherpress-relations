/**
 * Block registration for the Person Recent Roles block.
 *
 * HOW this file works:
 * ────────────────────
 * Registers the gatherpress/person-recent-roles block from its
 * block.json metadata and wires the Edit component as the editor UI.
 * The block is fully dynamic — `save` returns null because render.php
 * handles all frontend output.
 *
 * WHY this block exists:
 * A core/query block can list consumer posts tagged with a person's
 * shadow term, but it cannot extract the per-relationship role name
 * from `_gatherpress_relations` meta. This block resolves that by
 * querying consumer posts via the shadow taxonomy and then parsing
 * the junction meta to display each role alongside its linked
 * production.
 *
 * @package GatherPressRelations
 */

import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import Edit from './edit';
import metadata from './block.json';

/**
 * Register the Person Recent Roles block.
 *
 * PAYLOAD registered:
 * ```
 * {
 *   name: "gatherpress/person-recent-roles",
 *   edit: Edit,       // React component for the editor
 *   save: () => null   // Dynamic block — render.php handles frontend
 * }
 * ```
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
