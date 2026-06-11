/**
 * Hook: usePersonSearch
 *
 * Searches connectable source post types via the core data store.
 *
 * HOW this hook works:
 * ────────────────────
 * Wraps a `getEntityRecords` call for a given source post type
 * (defaulting to `gatherpress_person`) with the given search string.
 * Returns results only when the search term is at least 2 characters.
 *
 * WHY the sourcePostType parameter?
 * The plugin is agnostic about which post types are connectable
 * sources. Any post type declaring `gatherpress-relations-to` can
 * be searched. The caller passes the desired source type slug.
 *
 * @package GatherPressRelations
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Searches for source posts by name.
 *
 * @param {string} searchTerm     - The search string (min 2 chars to trigger).
 * @param {string} sourcePostType - The source post type slug to search
 *                                  (default: 'gatherpress_person').
 * @return {{ personResults: Array<Object>, isSearching: boolean }}
 */
export function usePersonSearch( searchTerm, sourcePostType = 'gatherpress_person' ) {
	return useSelect(
		( select ) => {
			if ( ! searchTerm || searchTerm.length < 2 ) {
				return { personResults: [], isSearching: false };
			}

			const results = select( coreStore ).getEntityRecords(
				'postType',
				sourcePostType,
				{
					search: searchTerm,
					per_page: 10,
					_fields: 'id,title,slug',
				}
			);

			return {
				personResults: results || [],
				isSearching: ! results,
			};
		},
		[ searchTerm, sourcePostType ]
	);
}
