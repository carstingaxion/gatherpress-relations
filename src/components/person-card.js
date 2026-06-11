/**
 * PersonCard — reusable person card for the Cast & Crew preview.
 *
 * HOW this component works:
 * ─────────────────────────
 * Renders a single person's headshot, name, and role in the editor
 * canvas. Used by both the cast-crew-list block's preview and the
 * query-loop contextual preview.
 *
 * WHY a shared component?
 * The same card markup was duplicated in three render paths inside
 * cast-crew-list/edit.js (main preview, query-loop preview, and
 * potentially the sidebar). Extracting it eliminates duplication.
 *
 * @package GatherPressRelations
 */

import { Icon } from '@wordpress/components';

/**
 * Renders a single person card.
 *
 * @param {Object}  props               - Component props.
 * @param {string}  props.name          - The person's display name.
 * @param {string}  props.role          - The role label.
 * @param {string}  props.thumbnailUrl  - URL to the headshot thumbnail (may be empty).
 * @param {boolean} props.showHeadshot  - Whether to render the headshot area.
 * @return {Element} The card element.
 */
export function PersonCard( { name, role, thumbnailUrl, showHeadshot } ) {
	return (
		<div className="cast-crew__card">
			{ showHeadshot && (
				<>
					{ thumbnailUrl ? (
						<img
							className="cast-crew__headshot"
							src={ thumbnailUrl }
							alt={ name }
						/>
					) : (
						<div className="cast-crew__headshot-placeholder">
							<Icon icon="admin-users" size={ 32 } />
						</div>
					) }
				</>
			) }
			<span className="cast-crew__name">{ name }</span>
			<span className="cast-crew__role">{ role }</span>
		</div>
	);
}
