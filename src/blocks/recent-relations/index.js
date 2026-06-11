/**
 * Block registration for the Recent Relations block.
 *
 * HOW this file works:
 * ────────────────────
 * Registers the gatherpress/recent-relations block from its
 * block.json metadata and wires the Edit component as the editor UI.
 * The block is fully dynamic — `save` returns null because render.php
 * handles all frontend output.
 *
 * WHY this block exists:
 * A core/query block can list consumer posts tagged with a source
 * post's shadow term, but it cannot extract the per-relationship
 * role name from `_gatherpress_relations` meta. This block resolves
 * that by querying consumer posts via the shadow taxonomy and then
 * parsing the junction meta to display each role alongside its
 * linked consumer post.
 *
 * @package GatherPressRelations
 */

import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import Edit from './edit';
import metadata from './block.json';

/**
 * Register the Recent Relations block.
 *
 * PAYLOAD registered:
 * ```
 * {
 *   name: "gatherpress/recent-relations",
 *   edit: Edit,       // React component for the editor
 *   save: () => null   // Dynamic block — render.php handles frontend
 * }
 * ```
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
