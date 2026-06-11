/**
 * Hook: useFeaturedImageLabel
 *
 * Resolves the registered featured image label for the
 * gatherpress_person post type.
 *
 * HOW this hook works:
 * ────────────────────
 * Reads the `labels.featured_image` property from the
 * gatherpress_person post type entity. Falls back to the generic
 * "Featured image" string if unavailable.
 *
 * WHY a shared hook?
 * The cast-crew-list block uses this label for the headshot toggle.
 * Extracting it keeps the block's edit.js leaner and the label
 * resolution reusable.
 *
 * @package GatherPressRelations
 */

import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Returns the featured image label for gatherpress_person.
 *
 * @return {string} The label (e.g., "Portrait", "Featured image").
 */
export function useFeaturedImageLabel() {
	return useSelect( ( select ) => {
		const personType = select( coreStore ).getPostType(
			'gatherpress_person'
		);
		return (
			personType?.labels?.featured_image ||
			__( 'Featured image', 'gatherpress-relations' )
		);
	}, [] );
}
