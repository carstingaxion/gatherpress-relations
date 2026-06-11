/**
 * Edit component for the GatherPress Relations Roadmap block.
 *
 * HOW this component works:
 * ─────────────────────────
 * In the editor canvas it renders a compact placeholder card that
 * shows the roadmap title, a brief description, and a list of the
 * sections that are currently enabled. This keeps the editor
 * lightweight — the full roadmap (with collapsible sections, code
 * samples, diagrams) is rendered only on the frontend via render.php.
 *
 * WHY a placeholder instead of the full roadmap?
 * 1. The roadmap is long (~3 000 words). Rendering it inside the
 *    editor would make the post canvas unwieldy and slow to scroll.
 * 2. The frontend version uses vanilla JS interactivity (collapsible
 *    panels, sticky TOC) that has no editor equivalent.
 * 3. A compact card communicates "this block exists and is configured"
 *    without overwhelming the editing experience.
 *
 * The sidebar (InspectorControls) exposes six ToggleControls — one
 * per roadmap section — so the author can choose which sections
 * appear on the frontend.
 *
 * @package GatherPressRelations
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	Icon,
} from '@wordpress/components';

import './editor.scss';

/**
 * Section metadata used to render both the sidebar toggles and the
 * placeholder summary list.
 *
 * Each entry maps a block attribute key to a human-readable label
 * and a Dashicon slug so the placeholder card can show a visual
 * checklist of enabled sections.
 *
 * @type {Array<{key: string, label: string, icon: string}>}
 *
 * Example entry:
 * ```
 * { key: 'showDataModeling', label: 'Data Modeling & Architecture', icon: 'database' }
 * ```
 */
const SECTIONS = [
	{
		key: 'showDataModeling',
		label: __( 'Data Modeling & Architecture', 'gatherpress-relations' ),
		icon: 'database',
	},
	{
		key: 'showCastCrew',
		label: __( 'Cast & Crew List Block', 'gatherpress-relations' ),
		icon: 'groups',
	},
	{
		key: 'showEnsemble',
		label: __( 'People / Ensemble Blocks', 'gatherpress-relations' ),
		icon: 'id-alt',
	},
	{
		key: 'showVisualizations',
		label: __( 'Smart Data-Relation Visualizations', 'gatherpress-relations' ),
		icon: 'chart-area',
	},
	{
		key: 'showScaling',
		label: __( 'Scaling & Performance', 'gatherpress-relations' ),
		icon: 'performance',
	},
	{
		key: 'showImplementation',
		label: __( 'Implementation Roadmap', 'gatherpress-relations' ),
		icon: 'flag',
	},
];

/**
 * Editor UI for the GatherPress Relations Roadmap block.
 *
 * @param {Object}   props               - Block props provided by the editor.
 * @param {Object}   props.attributes    - Current attribute values.
 * @param {boolean}  props.attributes.showDataModeling    - Whether the Data Modeling section is visible.
 * @param {boolean}  props.attributes.showCastCrew        - Whether the Cast & Crew section is visible.
 * @param {boolean}  props.attributes.showEnsemble        - Whether the Ensemble section is visible.
 * @param {boolean}  props.attributes.showVisualizations  - Whether the Visualizations section is visible.
 * @param {boolean}  props.attributes.showScaling         - Whether the Scaling section is visible.
 * @param {boolean}  props.attributes.showImplementation  - Whether the Implementation section is visible.
 * @param {Function} props.setAttributes - Setter for block attributes.
 * @returns {Element} The editor placeholder element.
 */
export default function Edit( { attributes, setAttributes } ) {
	/**
	 * `useBlockProps()` returns the HTML attributes (className, id, etc.)
	 * that the block wrapper needs so that the editor can:
	 * - apply the block's selection outline,
	 * - handle drag/drop and focus,
	 * - inject generated class names matching the `wp-block-*` pattern.
	 */
	const blockProps = useBlockProps();

	/**
	 * Count how many sections are currently enabled.
	 * Used in the placeholder to give the author a quick status line
	 * like "5 of 6 sections enabled".
	 *
	 * @type {number}
	 */
	const enabledCount = SECTIONS.filter(
		( section ) => attributes[ section.key ]
	).length;

	return (
		<>
			{ /*
			  * InspectorControls — rendered in the sidebar panel.
			  *
			  * WHY here?
			  * Placing toggles in the sidebar keeps the canvas clean
			  * while giving authors full control over which sections
			  * render on the frontend.
			  */ }
			<InspectorControls>
				<PanelBody
					title={ __(
						'Roadmap Sections',
						'gatherpress-relations'
					) }
					initialOpen={ true }
				>
					{ SECTIONS.map( ( section ) => (
						<ToggleControl
							__nextHasNoMarginBottom
							key={ section.key }
							label={ section.label }
							checked={ !! attributes[ section.key ] }
							onChange={ ( value ) =>
								setAttributes( {
									[ section.key ]: value,
								} )
							}
						/>
					) ) }
				</PanelBody>
			</InspectorControls>

			{ /*
			  * Editor placeholder — a compact card.
			  *
			  * HOW it communicates:
			  * - Title with a networking icon confirms the block type.
			  * - Description explains what renders on the frontend.
			  * - Section checklist shows which sections are on/off.
			  * - A status bar summarises "X of 6 sections enabled".
			  */ }
			<div { ...blockProps }>
				<div className="gatherpress-relations__editor-placeholder">
					<div className="gatherpress-relations__editor-placeholder-header">
						<Icon icon="networking" size={ 28 } />
						<h3 className="gatherpress-relations__editor-placeholder-title">
							{ __(
								'Shadow Taxonomy Relations Roadmap',
								'gatherpress-relations'
							) }
						</h3>
					</div>

					<p className="gatherpress-relations__editor-placeholder-description">
						{ __(
							'This block renders a comprehensive architectural roadmap on the frontend — covering shadow taxonomy data modeling, block design patterns, and scaling strategies for connecting persons to productions (or any special post type) with roles, powered by GatherPress shadow taxonomies.',
							'gatherpress-relations'
						) }
					</p>

					<ul className="gatherpress-relations__editor-placeholder-sections">
						{ SECTIONS.map( ( section ) => (
							<li
								key={ section.key }
								className={ `gatherpress-relations__editor-placeholder-section ${
									attributes[ section.key ]
										? 'gatherpress-relations__editor-placeholder-section--enabled'
										: 'gatherpress-relations__editor-placeholder-section--disabled'
								}` }
							>
								<Icon
									icon={ section.icon }
									size={ 16 }
								/>
								<span>{ section.label }</span>
							</li>
						) ) }
					</ul>

					<div className="gatherpress-relations__editor-placeholder-status">
						{ enabledCount } / { SECTIONS.length }{ ' ' }
						{ __(
							'sections enabled',
							'gatherpress-relations'
						) }
					</div>
				</div>
			</div>
		</>
	);
}
