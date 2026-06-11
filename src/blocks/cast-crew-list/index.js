/**
 * Block registration for the Cast & Crew List block.
 *
 * HOW this file works:
 * ────────────────────
 * Registers the gatherpress/cast-crew-list block from its block.json
 * metadata and wires the Edit component as the editor UI. The block
 * is fully dynamic — `save` returns null because render.php handles
 * all frontend output.
 *
 * WHY a separate entry point from the roadmap block?
 * Each block under src/blocks/<slug>/ has its own webpack entry so
 * that block.json's `file:./index.js` resolves correctly from the
 * build output directory.
 *
 * @package GatherPressRelations
 */

import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import Edit from './edit';
import metadata from './block.json';

/**
 * Register the Cast & Crew List block.
 *
 * PAYLOAD registered:
 * ```
 * {
 *   name: "gatherpress/cast-crew-list",
 *   edit: Edit,       // React component for the editor
 *   save: () => null   // Dynamic block — render.php handles frontend
 * }
 * ```
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
