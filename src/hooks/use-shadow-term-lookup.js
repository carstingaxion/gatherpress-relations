/**
 * Hook: useShadowTermLookup
 *
 * Resolves shadow term IDs for all `slug` values in a relations array.
 *
 * HOW this hook works:
 * ────────────────────
 * For each unique `slug` (shadow term slug) in the relations array,
 * queries the shadow taxonomy via the core data store to find the
 * corresponding term ID.
 *
 * WHY a shared hook?
 * The same lookup was duplicated in cast-crew-list/edit.js and
 * plugins/cast-crew-sidebar/index.js for term sync purposes.
 *
 * @package GatherPressRelations
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Builds a map of shadow slug → term ID for the given relations.
 *
 * @param {Array<Object>} relations      - Array of relation entry objects.
 *                                         Each entry uses `slug` for the
 *                                         shadow term slug.
 * @param {string}        shadowTaxonomy - The shadow taxonomy slug
 *                                         (default: '_gatherpress_person').
 * @return {Object<string, number|null>} Map of shadow slug → term ID (or null).
 */
export function useShadowTermLookup( relations, shadowTaxonomy = '_gatherpress_person' ) {
	return useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const map = {};

			relations.forEach( ( entry ) => {
				const shadowSlug = entry.slug;
				if ( ! shadowSlug || map[ shadowSlug ] !== undefined ) {
					return;
				}

				const terms = getEntityRecords(
					'taxonomy',
					shadowTaxonomy,
					{
						slug: shadowSlug,
						per_page: 1,
					}
				);

				if ( terms && terms.length > 0 ) {
					map[ shadowSlug ] = terms[ 0 ].id;
				} else {
					map[ shadowSlug ] = null;
				}
			} );

			return map;
		},
		[ relations, shadowTaxonomy ]
	);
}
