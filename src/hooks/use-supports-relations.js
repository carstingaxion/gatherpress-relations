/**
 * Hook: useSupportsRelations
 *
 * Checks whether a given post type supports 'gatherpress-relations-from'.
 *
 * HOW this hook works:
 * ────────────────────
 * Fetches the post type object from the core data store and inspects
 * its `supports` property.
 *
 * WHY a shared hook?
 * The same check was duplicated in the roadmap block's index.js,
 * the cast-crew-list block, and the sidebar plugin. Extracting it
 * here provides a single, testable implementation.
 *
 * @package GatherPressRelations
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Checks whether a post type supports gatherpress-relations-from.
 *
 * @param {string|null} postType - The post type slug to check, or null.
 * @return {{ supportsRelations: boolean, isResolving: boolean }}
 */
export function useSupportsRelations( postType ) {
	return useSelect(
		( select ) => {
			if ( ! postType ) {
				return { supportsRelations: false, isResolving: true };
			}

			const postTypeObj = select( coreStore ).getPostType( postType );

			if ( ! postTypeObj ) {
				return { supportsRelations: false, isResolving: true };
			}

			return {
				supportsRelations:
					!! postTypeObj.supports?.[ 'gatherpress-relations-from' ],
				isResolving: false,
			};
		},
		[ postType ]
	);
}
