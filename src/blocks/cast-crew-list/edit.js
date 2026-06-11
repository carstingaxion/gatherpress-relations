/**
 * Edit component for the Cast & Crew List block.
 *
 * HOW this component works:
 * ─────────────────────────
 * A thin wrapper that composes shared hooks and components for the
 * Cast & Crew List block's editor preview. The "Manage Cast & Crew"
 * sidebar panel is handled by the standalone sidebar plugin — this
 * component focuses on the canvas preview and display settings.
 *
 * DATA FLOW:
 * 1. useRelationsMeta reads `_gatherpress_relations` from post meta.
 * 2. groupByDepartment groups entries for the preview.
 * 3. usePersonDataMap resolves names + headshots.
 * 4. DepartmentGroupedPreview renders the grouped cards.
 * 5. When inside a Query Loop, reads meta from the iterated post
 *    entity record directly (not from useEntityProp).
 *
 * @package GatherPressRelations
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	Placeholder,
} from '@wordpress/components';
import { HeadingLevelDropdown } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { sprintf } from '@wordpress/i18n';

import './editor.scss';

import { useRelationsMeta } from '../../hooks/use-relations-meta';
import { usePersonDataMap } from '../../hooks/use-person-data-map';
import { useSupportsRelations } from '../../hooks/use-supports-relations';
import { useFeaturedImageLabel } from '../../hooks/use-featured-image-label';
import { groupByDepartment } from '../../utils/parse-relations';
import { parseRelations } from '../../utils/parse-relations';
import { DepartmentGroupedPreview } from '../../components/department-grouped-preview';
import { getSourceTypes, getDefaultSourceType, getShadowTaxonomyForSource } from '../../utils/source-types';
import { useMemo } from '@wordpress/element';

/**
 * Editor UI for the Cast & Crew List block.
 *
 * @param {Object}   props               - Block props from the editor.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Attribute setter.
 * @param {Object}   props.context       - Block context (postId, postType, queryId).
 * @return {Element} The editor component.
 */
export default function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();
	const { postType, postId } = context;

	/**
	 * Detect whether the "Cards" block style is active.
	 *
	 * WHY check attributes.className?
	 * WordPress stores the block style class in `attributes.className`
	 * (not as a component prop). When a user picks a style variation,
	 * WordPress sets `is-style-<slug>` in this attribute. The
	 * "Columns" control is only meaningful for the cards layout —
	 * the simple list and compact table styles ignore the columns
	 * attribute entirely.
	 *
	 * @type {boolean}
	 */
	const isCardsStyle = ( attributes.className || '' ).includes( 'is-style-cards' );

	/**
	 * Detect whether the block is inside a core/query (Query Loop).
	 *
	 * @type {boolean}
	 */
	const isInsideQuery = Number.isFinite( context?.queryId );

	const featuredImageLabel = useFeaturedImageLabel();
	const { relations } = useRelationsMeta( postType );
	const { supportsRelations } = useSupportsRelations( postType );

	/*
	 * Resolve source types from the localised config.
	 *
	 * WHY useMemo?
	 * getSourceTypes() reads from a global at call time; wrapping
	 * in useMemo avoids re-reading on every render while still
	 * being lazy enough to catch the global after script load.
	 */
	const sourceTypes = useMemo( () => getSourceTypes(), [] );

	/*
	 * Filter relations by the selected sourcePostType attribute.
	 *
	 * HOW this works:
	 * Each relation entry stores a `type` field with the source post
	 * type slug. To filter by source type, we check whether the
	 * entry's `type` matches the attribute. When `sourcePostType`
	 * is empty or "all", no filtering occurs.
	 *
	 * For backward compatibility (entries that don't carry `type`),
	 * we treat entries without that field as belonging to the default
	 * source type (gatherpress_person).
	 */
	const filteredRelations = useMemo( () => {
		const selectedSource = attributes.sourcePostType || '';
		if ( ! selectedSource || selectedSource === 'all' ) {
			return relations;
		}
		const defaultSlug = getDefaultSourceType().slug;
		return relations.filter( ( entry ) => {
			const entrySource = entry.type || defaultSlug;
			return entrySource === selectedSource;
		} );
	}, [ relations, attributes.sourcePostType ] );

	const personDataMap = usePersonDataMap( filteredRelations );
	const grouped = groupByDepartment( filteredRelations );

	/**
	 * When inside a Query Loop, read meta from the iterated post's
	 * entity record directly.
	 */
	const {
		queryRelations,
		queryPersonDataMap,
		queryGrouped,
	} = useSelect(
		( select ) => {
			if ( ! isInsideQuery || ! postId || ! postType ) {
				return {
					queryRelations: [],
					queryPersonDataMap: new Map(),
					queryGrouped: {},
				};
			}

			const { getEntityRecord, getMedia } = select( coreStore );

			const iteratedPost = getEntityRecord(
				'postType',
				postType,
				postId
			);

			if ( ! iteratedPost ) {
				return {
					queryRelations: [],
					queryPersonDataMap: new Map(),
					queryGrouped: {},
				};
			}

			const parsedRelations = parseRelations(
				iteratedPost.meta?._gatherpress_relations
			);

			const pDataMap = new Map();
			parsedRelations.forEach( ( entry ) => {
				const pid = entry.id;
				if ( ! pid || pDataMap.has( pid ) ) {
					return;
				}

				const sourceType = entry.type || 'gatherpress_person';

				const personPost = getEntityRecord(
					'postType',
					sourceType,
					pid
				);

				if ( ! personPost ) {
					pDataMap.set( pid, { name: '', thumbnailUrl: '' } );
					return;
				}

				const name =
					personPost.title?.rendered ||
					personPost.title?.raw ||
					entry.slug;

				let thumbnailUrl = '';
				const mediaId = personPost.featured_media;
				if ( mediaId ) {
					const media = getMedia( mediaId, { context: 'view' } );
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

				pDataMap.set( pid, { name, thumbnailUrl } );
			} );

			return {
				queryRelations: parsedRelations,
				queryPersonDataMap: pDataMap,
				queryGrouped: groupByDepartment( parsedRelations ),
			};
		},
		[ isInsideQuery, postId, postType ]
	);

	/**
	 * The shared display settings InspectorControls panel.
	 *
	 * @return {Element} The settings panel.
	 */
	/**
	 * Build SelectControl options for the source post type filter.
	 *
	 * WHY useMemo?
	 * Avoids rebuilding the options array on every render.
	 *
	 * @type {Array<{label: string, value: string}>}
	 */
	const sourceTypeOptions = useMemo( () => {
		const opts = [];
		sourceTypes.forEach( ( s ) => {
			opts.push( {
				label: s.label || s.slug,
				value: s.slug,
			} );
		} );
		return opts;
	}, [ sourceTypes ] );

	function DisplaySettings() {
		return (
			<InspectorControls>
				<PanelBody
					title={ __( 'Display Settings', 'gatherpress-relations' ) }
					initialOpen={ true }
				>
					{ sourceTypes.length > 1 && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Source post type',
								'gatherpress-relations'
							) }
							help={ __(
								'Filter the roster to entries from a specific source type.',
								'gatherpress-relations'
							) }
							value={ attributes.sourcePostType || getDefaultSourceType().slug }
							options={ sourceTypeOptions }
							onChange={ ( val ) =>
								setAttributes( { sourcePostType: val } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ sprintf(
							__( 'Show %s', 'gatherpress-relations' ),
							featuredImageLabel.toLowerCase()
						) }
						checked={ attributes.showHeadshots }
						onChange={ ( val ) =>
							setAttributes( { showHeadshots: val } )
						}
					/>
					{ attributes.showHeadshots && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ sprintf(
								__( '%s size', 'gatherpress-relations' ),
								featuredImageLabel
							) }
							value={ attributes.headShotSize }
							options={ [
								{
									label: __(
										'Thumbnail',
										'gatherpress-relations'
									),
									value: 'thumbnail',
								},
								{
									label: __(
										'Medium',
										'gatherpress-relations'
									),
									value: 'medium',
								},
							] }
							onChange={ ( val ) =>
								setAttributes( { headShotSize: val } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show department headings',
							'gatherpress-relations'
						) }
						checked={ attributes.showDepartmentHeadings }
						onChange={ ( val ) =>
							setAttributes( { showDepartmentHeadings: val } )
						}
					/>
					{ isCardsStyle && (
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Columns', 'gatherpress-relations' ) }
							value={ attributes.columns }
							onChange={ ( val ) =>
								setAttributes( { columns: val } )
							}
							min={ 1 }
							max={ 6 }
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Link to person page',
							'gatherpress-relations'
						) }
						checked={ attributes.linkToPersonPage }
						onChange={ ( val ) =>
							setAttributes( { linkToPersonPage: val } )
						}
					/>
					{ attributes.showDepartmentHeadings && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __(
								'Department heading level',
								'gatherpress-relations'
							) }
							value={ String( attributes.departmentHeadingLevel || 3 ) }
							options={ [
								{ label: 'H1', value: '1' },
								{ label: 'H2', value: '2' },
								{ label: 'H3', value: '3' },
								{ label: 'H4', value: '4' },
								{ label: 'H5', value: '5' },
								{ label: 'H6', value: '6' },
							] }
							onChange={ ( val ) =>
								setAttributes( {
									departmentHeadingLevel: parseInt( val, 10 ),
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
		);
	}

	if ( postType && supportsRelations === false ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="groups"
					label={ __( 'Cast & Crew List', 'gatherpress-relations' ) }
					instructions={ __(
						'This post type does not support gatherpress-relations-from. Add support via add_post_type_support() to use this block.',
						'gatherpress-relations'
					) }
				/>
			</div>
		);
	}

	if ( isInsideQuery ) {
		/*
		 * Filter query-context relations by sourcePostType.
		 */
		const selectedSource = attributes.sourcePostType || '';
		const defaultSlug = getDefaultSourceType().slug;
		const activeRelations = ( ! selectedSource || selectedSource === 'all' )
			? queryRelations
			: queryRelations.filter( ( entry ) => {
				const entrySource = entry.type || defaultSlug;
				return entrySource === selectedSource;
			} );
		const activeGrouped = groupByDepartment( activeRelations );
		const activePersonMap = queryPersonDataMap;

		return (
			<>
				<DisplaySettings />
				<div { ...blockProps }>
					{ activeRelations.length === 0 ? (
						<Placeholder
							icon="groups"
							label={ __(
								'Cast & Crew List',
								'gatherpress-relations'
							) }
							instructions={ __(
								'No cast or crew assigned to this post.',
								'gatherpress-relations'
							) }
						/>
					) : (
											<DepartmentGroupedPreview
						grouped={ activeGrouped }
						personDataMap={ activePersonMap }
						showDepartmentHeadings={
							attributes.showDepartmentHeadings
						}
						showHeadshots={ attributes.showHeadshots }
						columns={ attributes.columns }
						headingLevel={ attributes.departmentHeadingLevel || 3 }
						sourceType={ attributes.sourcePostType || '' }
					/>
					) }
				</div>
			</>
		);
	}

	return (
		<>
			<DisplaySettings />
			<div { ...blockProps }>
				{ filteredRelations.length === 0 ? (
					<Placeholder
						icon="groups"
						label={ __(
							'Cast & Crew List',
							'gatherpress-relations'
						) }
						instructions={ __(
							'No cast or crew assigned yet. Use the sidebar panel to search for persons and assign roles.',
							'gatherpress-relations'
						) }
					/>
				) : (
					<DepartmentGroupedPreview
						grouped={ grouped }
						personDataMap={ personDataMap }
						showDepartmentHeadings={
							attributes.showDepartmentHeadings
						}
						showHeadshots={ attributes.showHeadshots }
						columns={ attributes.columns }
						headingLevel={ attributes.departmentHeadingLevel || 3 }
						sourceType={ attributes.sourcePostType || '' }
					/>
				) }
			</div>
		</>
	);
}
