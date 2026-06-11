/**
 * Sidebar plugin: Manage Cast & Crew.
 *
 * HOW this file works:
 * ────────────────────
 * Registers a PluginDocumentSettingPanel that provides the "Manage
 * Cast & Crew" UI for ALL post types that support `gatherpress-relations`.
 * The actual panel contents are rendered by the shared
 * ManageCastCrewPanel component.
 *
 * WHY a standalone plugin entry point?
 * The sidebar panel is a document-level concern, not tied to any
 * specific block. It loads independently so editors can manage
 * cast & crew even when the block is not in the post content
 * (e.g., when used in a template).
 *
 * @package GatherPressRelations
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

import { useSupportsRelations } from '../../hooks/use-supports-relations';
import { ManageCastCrewPanel } from '../../components/manage-cast-crew-panel';

/**
 * The PluginDocumentSettingPanel wrapper component.
 *
 * HOW this works:
 * ───────────────
 * Uses `PluginDocumentSettingPanel` from `@wordpress/editor` to add
 * a panel in the document sidebar (Post tab). The panel is only
 * rendered when the current post type supports `gatherpress-relations`.
 *
 * @return {Element|null} The panel, or null if not applicable.
 */
function CastCrewDocumentPanel() {
	const { postType, postId, isResolvingPostType } = useSelect(
		( select ) => {
			const currentPostType =
				select( 'core/editor' ).getCurrentPostType();
			const currentPostId =
				select( 'core/editor' ).getCurrentPostId();

			return {
				postType: currentPostType,
				postId: currentPostId,
				isResolvingPostType: ! currentPostType,
			};
		},
		[]
	);

	const { supportsRelations, isResolving } =
		useSupportsRelations( postType );

	if ( isResolvingPostType || isResolving || ! supportsRelations ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="gatherpress-relations-cast-crew"
			title={ __( 'Cast & Crew', 'gatherpress-relations' ) }
			className="gatherpress-relations-cast-crew-panel"
		>
			<ManageCastCrewPanel postType={ postType } postId={ postId } />
		</PluginDocumentSettingPanel>
	);
}

/**
 * Register the sidebar plugin.
 *
 * WHY 'gatherpress-relations-cast-crew' as the name?
 * Plugin names must be unique across the editor. This namespaced
 * string avoids collisions with other plugins.
 */
registerPlugin( 'gatherpress-relations-cast-crew', {
	render: CastCrewDocumentPanel,
	icon: 'groups',
} );
