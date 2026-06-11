/**
 * Block registration and editor-side hooks for GatherPress Relations.
 *
 * HOW this file is structured:
 * ────────────────────────────
 * 1. Block registration — registers the roadmap block from block.json.
 * 2. Editor hooks — reusable React hooks for discovering post type
 *    supports via the @wordpress/core-data store.
 *
 * NOTE: The "Manage Cast & Crew" sidebar plugin has been moved to
 * its own entry point at src/plugins/cast-crew-sidebar/index.js.
 * This keeps each entry point focused on a single responsibility.
 *
 * @package GatherPressRelations
 */

import { registerBlockType } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Shared styles applied in both editor and frontend contexts.
 *
 * WHY imported here?
 * Webpack bundles any SCSS imported from the entry point into
 * `style-index.css`, which block.json references as the `style` asset.
 * This ensures the same visual baseline in both environments.
 */
import './style.scss';

/**
 * Internal dependencies — the editor-only component.
 */
import Edit from './edit';
import metadata from './block.json';

/**
 * Register the roadmap block type.
 *
 * PAYLOAD registered:
 * ```
 * {
 *   name: "gatherpress/relations-roadmap",
 *   edit: Edit,      // React component for the editor
 *   save: () => null  // Dynamic block — no saved markup
 * }
 * ```
 */
registerBlockType( metadata.name, {
	/**
	 * Editor component — renders a compact placeholder.
	 *
	 * @see ./edit.js
	 */
	edit: Edit,
} );

/**
 * Hook: useSupportsRelations
 *
 * Checks whether the current post type supports 'gatherpress-relations'.
 *
 * HOW this works:
 * ───────────────
 * 1. Reads the current post type from the editor store via
 *    `select( 'core/editor' ).getCurrentPostType()`.
 * 2. Fetches the post type object from the core data store via
 *    `select( coreStore ).getPostType( slug )`.
 * 3. Inspects the `supports` property for 'gatherpress-relations'.
 *
 * WHY the core data store instead of a custom endpoint?
 * - Zero new REST routes needed.
 * - The data is already cached by the store across components.
 * - Stays in sync with the server's post type registry automatically.
 * - The REST API (/wp/v2/types/{slug}) exposes the supports object
 *   natively since WordPress 5.x.
 *
 * RETURN VALUE example:
 * ```js
 * { supportsRelations: true, isResolving: false }
 * ```
 *
 * @return {{ supportsRelations: boolean, isResolving: boolean }}
 */
export function useSupportsRelations() {
	return useSelect( ( select ) => {
		/**
		 * WHY 'core/editor' instead of importing editorStore?
		 * The editor store string is stable and avoids an extra
		 * import dependency. Both forms resolve to the same store.
		 */
		const currentPostType = select( 'core/editor' ).getCurrentPostType();

		if ( ! currentPostType ) {
			return { supportsRelations: false, isResolving: true };
		}

		const postTypeObj = select( coreStore ).getPostType( currentPostType );

		if ( ! postTypeObj ) {
			return { supportsRelations: false, isResolving: true };
		}

		return {
			supportsRelations: !! postTypeObj.supports?.[ 'gatherpress-relations' ],
			isResolving: false,
		};
	}, [] );
}

/**
 * Hook: useRelationConsumerTypes
 *
 * Returns all post types that support 'gatherpress-relations'.
 *
 * HOW this works:
 * ───────────────
 * Fetches all post type objects via `getPostTypes({ per_page: -1 })`
 * from the core data store, then filters client-side for those whose
 * `supports` object includes 'gatherpress-relations'.
 *
 * WHY client-side filtering?
 * The REST API does not support filtering by `supports` server-side.
 * However, the full post type list is small (typically < 30 items)
 * and is cached by the store, so client-side filtering is negligible.
 *
 * RETURN VALUE example:
 * ```js
 * {
 *   consumerTypes: [
 *     { slug: 'gatherpress_play', name: 'Productions', supports: { ... } },
 *     { slug: 'gatherpress_event', name: 'Events', supports: { ... } },
 *   ],
 *   isResolving: false,
 * }
 * ```
 *
 * @return {{ consumerTypes: Object[]|null, isResolving: boolean }}
 */
export function useRelationConsumerTypes() {
	return useSelect( ( select ) => {
		const { getPostTypes, isResolving } = select( coreStore );
		const allTypes = getPostTypes( { per_page: -1 } );

		if ( ! allTypes ) {
			return { consumerTypes: null, isResolving: true };
		}

		/**
		 * Filter for post types with gatherpress-relations support.
		 *
		 * WHY optional chaining on supports?
		 * Some built-in post types (attachments, nav_menu_item) may
		 * not expose a supports object in the REST response. The
		 * optional chain prevents TypeError on those entries.
		 */
		const consumers = allTypes.filter(
			( type ) => type.supports?.[ 'gatherpress-relations' ]
		);

		return {
			consumerTypes: consumers,
			isResolving: isResolving( 'getPostTypes', [ { per_page: -1 } ] ),
		};
	}, [] );
}
