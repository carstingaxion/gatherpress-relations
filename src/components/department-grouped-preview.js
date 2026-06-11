/**
 * DepartmentGroupedPreview — shared preview renderer for grouped
 * cast/crew cards.
 *
 * HOW this component works:
 * ─────────────────────────
 * Takes a grouped-by-department object and a person data map, then
 * renders department sections with headings and a grid of PersonCard
 * components. Used by both the main cast-crew-list editor preview
 * and the query-loop contextual preview.
 *
 * WHY a shared component?
 * The same department-grouped rendering logic was duplicated in
 * three render branches inside cast-crew-list/edit.js. Extracting
 * it into a single component reduces lines and ensures visual
 * consistency across all preview contexts.
 *
 * @package GatherPressRelations
 */

import { getDepartmentOrder, getDepartmentLabel } from '../utils/departments';
import { PersonCard } from './person-card';

/**
 * Renders a department-grouped cast/crew preview.
 *
 * @param {Object}   props                       - Component props.
 * @param {Object}   props.grouped               - Entries keyed by department slug.
 * @param {Map}      props.personDataMap          - Map of person_post_id → { name, thumbnailUrl }.
 * @param {boolean}  props.showDepartmentHeadings - Whether to show department headings.
 * @param {boolean}  props.showHeadshots          - Whether to show headshots.
 * @param {number}   props.columns                - Grid column count.
 * @param {number}   props.headingLevel           - Heading level for department titles (1–6).
 * @return {Element} The preview element.
 */
/**
 * Renders a department-grouped cast/crew preview.
 *
 * @param {Object}   props                       - Component props.
 * @param {Object}   props.grouped               - Entries keyed by department slug.
 * @param {Map}      props.personDataMap          - Map of person_post_id → { name, thumbnailUrl }.
 * @param {boolean}  props.showDepartmentHeadings - Whether to show department headings.
 * @param {boolean}  props.showHeadshots          - Whether to show headshots.
 * @param {number}   props.columns                - Grid column count.
 * @param {number}   props.headingLevel           - Heading level for department titles (1–6).
 * @param {string}   props.sourceType              - Source post type slug for per-type departments.
 * @return {Element} The preview element.
 */
export function DepartmentGroupedPreview( {
	grouped,
	personDataMap,
	showDepartmentHeadings,
	showHeadshots,
	columns,
	headingLevel = 3,
	sourceType = '',
} ) {
	const HeadingTag = `h${ Math.min( 6, Math.max( 1, headingLevel ) ) }`;

	/**
	 * Resolve department order at render time (not at import time)
	 * so that the PHP-localised config is guaranteed to be available.
	 * Pass the source post type so per-type overrides are respected.
	 *
	 * @type {string[]}
	 */
	const departmentOrder = getDepartmentOrder( sourceType );

	return (
		<div
			className="cast-crew"
			style={ { '--cast-crew-columns': String( columns ) } }
		>
			{ departmentOrder.filter( ( dept ) => grouped[ dept ] ).map(
				( dept ) => (
					<section key={ dept } className="cast-crew__department">
						{ showDepartmentHeadings && (
							<HeadingTag className="wp-block-heading">
								{ getDepartmentLabel( dept, sourceType ) }
							</HeadingTag>
						) }
						<div className="cast-crew__grid">
							{ grouped[ dept ].map( ( entry ) => {
								const personData = personDataMap.get(
									entry.id
								);
								const displayName =
									personData?.name ||
									entry.slug;
								const thumbUrl =
									personData?.thumbnailUrl || '';

								return (
									<PersonCard
										key={ entry._index }
										name={ displayName }
										role={ entry.role }
										thumbnailUrl={ thumbUrl }
										showHeadshot={ showHeadshots }
									/>
								);
							} ) }
						</div>
					</section>
				)
			) }
		</div>
	);
}
