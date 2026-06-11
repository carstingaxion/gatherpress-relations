/**
 * Hook: usePersonDataMap
 *
 * Resolves person post data (title + featured image thumbnail URL)
 * for every entry in a relations array.
 *
 * HOW this hook works:
 * ────────────────────
 * For each unique `id` (source post ID) in the relations array,
 * reads the person post entity and its featured media from the core
 * data store. Returns a Map keyed by source post ID.
 *
 * WHY a shared hook?
 * Both the cast-crew-list block and the sidebar plugin need to
 * display person names and headshots. The same resolution logic
 * was duplicated in three places.
 *
 * @package GatherPressRelations
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Resolves person data for a list of relation entries.
 *
 * @param {Array<Object>} relations      - Array of relation entry objects,
 *                                         each with an `id` field (source
 *                                         post ID) and optionally a `type`
 *                                         field (source post type slug).
 * @param {string}        defaultPostType - Fallback source post type when entries
 *                                          don't carry `type`
 *                                          (default: 'gatherpress_person').
 * @return {Map<number, {name: string, thumbnailUrl: string}>}
 */
export function usePersonDataMap( relations, defaultPostType = 'gatherpress_person' ) {
	return useSelect(
		( select ) => {
			const { getEntityRecord, getMedia } = select( coreStore );
			const map = new Map();

			relations.forEach( ( entry ) => {
				const pid = entry.id;
				if ( ! pid || map.has( pid ) ) {
					return;
				}

				/*
				 * Resolve which post type to query for this entry.
				 *
				 * WHY entry.type?
				 * Relations may reference source types other than
				 * gatherpress_person (e.g., gatherpress_sponsor). Each
				 * entry carries its own `type`. When absent, we fall
				 * back to the provided default.
				 */
				const sourceType = entry.type || defaultPostType;

				const personPost = getEntityRecord(
					'postType',
					sourceType,
					pid
				);

				if ( ! personPost ) {
					map.set( pid, { name: '', thumbnailUrl: '' } );
					return;
				}

				const name =
					personPost.title?.rendered ||
					personPost.title?.raw ||
					entry.slug;

				let thumbnailUrl = '';
				const mediaId = personPost.featured_media;
				if ( mediaId ) {
					const media = getMedia( mediaId, {
						context: 'view',
					} );
					if ( media ) {
						thumbnailUrl =
							media.media_details?.sizes?.thumbnail
								?.source_url ||
							media.media_details?.sizes?.medium
								?.source_url ||
							media.source_url ||
							'';
					}
				}

				map.set( pid, { name, thumbnailUrl } );
			} );

			return map;
		},
		[ relations ]
	);
}
