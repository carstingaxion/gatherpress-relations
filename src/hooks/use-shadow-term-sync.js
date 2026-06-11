/**
 * Hook: useShadowTermSync
 *
 * Manages shadow taxonomy term assignments on the current post
 * entity, keeping them in sync with the `_gatherpress_relations`
 * meta entries (which use `slug` for the shadow term slug).
 *
 * HOW this hook works:
 * ────────────────────
 * Reads the current shadow term IDs from the edited entity record
 * via the core data store, and provides helpers to add or remove
 * terms when relation entries are added or removed.
 *
 * WHY a shared hook?
 * Both the cast-crew-list block's edit.js and the sidebar plugin
 * need to sync taxonomy terms with meta entries. Extracting the
 * logic here avoids duplication and ensures consistent behavior.
 *
 * @package GatherPressRelations
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Provides shadow term sync utilities for the current post.
 *
 * @param {string}      postType        - The current post type slug.
 * @param {number|null} postId          - The current post ID.
 * @param {string}      shadowTaxonomy  - The shadow taxonomy slug
 *                                        (default: '_gatherpress_person').
 * @return {{
 *   currentTermIds: number[],
 *   addShadowTerm: (shadowSlug: string, knownTermId?: number|null) => void,
 *   removeShadowTerm: (shadowSlug: string, knownTermId?: number|null) => void,
 *   updateTerms: (termIds: number[]) => void,
 * }}
 */
export function useShadowTermSync( postType, postId, shadowTaxonomy = '_gatherpress_person' ) {
	const { editEntityRecord } = useDispatch( coreStore );

	/**
	 * Read the current shadow taxonomy term IDs from the edited
	 * entity record.
	 *
	 * WHY getEditedEntityRecord?
	 * The "edited" variant includes staged changes that haven't
	 * been saved yet, so we always see the latest state.
	 *
	 * WHY dynamic taxonomy key?
	 * The entity record stores taxonomy terms under the taxonomy
	 * slug as a key. Using the `shadowTaxonomy` parameter makes
	 * this hook work for any source type, not just
	 * `_gatherpress_person`.
	 *
	 * @type {number[]}
	 */
	const currentTermIds = useSelect(
		( select ) => {
			if ( ! postType || ! postId ) {
				return [];
			}
			const record = select( coreStore ).getEditedEntityRecord(
				'postType',
				postType,
				postId
			);
			return record?.[ shadowTaxonomy ] || [];
		},
		[ postType, postId, shadowTaxonomy ]
	);

	/**
	 * Stages a taxonomy term update on the post entity.
	 *
	 * @param {number[]} termIds - The complete updated array of term IDs.
	 */
	const updateTerms = useCallback(
		( termIds ) => {
			if ( ! postType || ! postId ) {
				return;
			}
			editEntityRecord( 'postType', postType, postId, {
				[ shadowTaxonomy ]: termIds,
			} );
		},
		[ postType, postId, editEntityRecord, shadowTaxonomy ]
	);

	/**
	 * Adds a shadow term to the post if not already present.
	 *
	 * @param {string}      shadowSlug  - The shadow term slug (e.g., '_jane-doe').
	 * @param {number|null} knownTermId - If already known, the term ID to add.
	 */
	const addShadowTerm = useCallback(
		( shadowSlug, knownTermId = null ) => {
			if ( knownTermId && ! currentTermIds.includes( knownTermId ) ) {
				updateTerms( [ ...currentTermIds, knownTermId ] );
			} else if ( ! knownTermId ) {
				apiFetch( {
					path: `/wp/v2/${ shadowTaxonomy }?slug=${ encodeURIComponent( shadowSlug ) }&per_page=1`,
				} )
					.then( ( terms ) => {
						if ( terms && terms.length > 0 ) {
							const termId = terms[ 0 ].id;
							if ( ! currentTermIds.includes( termId ) ) {
								updateTerms( [ ...currentTermIds, termId ] );
							}
						}
					} )
					.catch( () => {} );
			}
		},
		[ currentTermIds, updateTerms, shadowTaxonomy ]
	);

	/**
	 * Removes a shadow term from the post.
	 *
	 * @param {string}      shadowSlug  - The shadow term slug.
	 * @param {number|null} knownTermId - If already known, the term ID to remove.
	 */
	const removeShadowTerm = useCallback(
		( shadowSlug, knownTermId = null ) => {
			if ( knownTermId && currentTermIds.includes( knownTermId ) ) {
				updateTerms(
					currentTermIds.filter( ( id ) => id !== knownTermId )
				);
			}
		},
		[ currentTermIds, updateTerms ]
	);

	return { currentTermIds, addShadowTerm, removeShadowTerm, updateTerms };
}
