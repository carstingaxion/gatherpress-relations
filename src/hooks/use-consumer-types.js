/**
 * Hook: useConsumerTypes
 *
 * Returns all post types that support 'gatherpress-relations-from'.
 *
 * HOW this hook works:
 * ────────────────────
 * Fetches all post types from the core data store and filters
 * client-side for those whose `supports` object includes
 * 'gatherpress-relations-from'.
 *
 * WHY client-side filtering?
 * The REST API does not support filtering by `supports`. The full
 * post type list is small (< 30 items) and cached by the store.
 *
 * @package GatherPressRelations
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Discovers all consumer post types.
 *
 * @return {{ consumerTypes: Object[]|null, isResolving: boolean }}
 */
export function useConsumerTypes() {
	return useSelect( ( select ) => {
		const { getPostTypes, isResolving } = select( coreStore );
		const allTypes = getPostTypes( { per_page: -1 } );

		if ( ! allTypes ) {
			return { consumerTypes: null, isResolving: true };
		}

		const consumers = allTypes.filter(
			( type ) => type.supports?.[ 'gatherpress-relations-from' ]
		);

		return {
			consumerTypes: consumers,
			isResolving: isResolving( 'getPostTypes', [ { per_page: -1 } ] ),
		};
	}, [] );
}
