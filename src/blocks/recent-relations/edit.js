/**
 * Edit component for the Recent Relations block.
 *
 * HOW this component works:
 * ─────────────────────────
 * Intended for placement on a single template for any source post
 * type (gatherpress_person, gatherpress_sponsor, etc.) or inside a
 * Query Loop with a source post type. It reads the current post's
 * slug from block context, derives the shadow term slug, and queries
 * consumer posts tagged with that term to extract role data from
 * their `_gatherpress_relations` meta.
 *
 * All labels are context-aware — they use the registered singular
 * and plural labels for the current post type and for the referenced
 * consumer ("relate-from") post types.
 *
 * DATA FLOW (editor preview):
 * 1. Get the current source post from block context (postId).
 * 2. Read the post's slug via getEntityRecord.
 * 3. Derive shadow slug: `_<post_slug>`.
 * 4. Look up the shadow term in the `_<post_type>` taxonomy.
 * 5. Discover the taxonomy's REST base from the taxonomy entity.
 * 6. Discover consumer post types (those with `gatherpress-relations-from`
 *    support) via useConsumerTypes hook.
 * 7. For each consumer type, query posts tagged with the shadow term.
 * 8. Parse _gatherpress_relations meta from each consumer post.
 * 9. Filter entries matching the current post's shadow slug.
 * 10. Render a preview list of role → consumer post pairs.
 *
 * @package GatherPressRelations
 */

import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	Spinner,
	Placeholder,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

import './editor.scss';

import { getDepartmentLabel } from '../../utils/departments';
import { getDefaultSourceType, getShadowTaxonomyForSource } from '../../utils/source-types';
import { useConsumerTypes } from '../../hooks/use-consumer-types';
import { formatRoleDate } from '../../utils/format-role-date';

/**
 * Editor UI for the Recent Relations block.
 *
 * @param {Object}   props               - Block props from the editor.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Attribute setter.
 * @param {Object}   props.context       - Block context (postId, postType).
 * @return {Element} The editor component.
 */
export default function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();
	const { postId, postType } = context;

	/**
	 * Local state for roles fetched via apiFetch.
	 */
	const [ roles, setRoles ] = useState( [] );
	const [ isFetchingRoles, setIsFetchingRoles ] = useState( false );
	const [ hasLoaded, setHasLoaded ] = useState( false );

	/**
	 * Resolve the current post type's labels for context-aware strings.
	 *
	 * WHY useSelect for post type labels?
	 * The block title, placeholder text, and empty-state messaging
	 * should reflect the actual post type the block sits on (e.g.,
	 * "Person", "Sponsor") rather than a hardcoded "Person".
	 */
	const { currentTypeLabels } = useSelect(
		( select ) => {
			if ( ! postType ) {
				return { currentTypeLabels: null };
			}
			const typeObj = select( coreStore ).getPostType( postType );
			return {
				currentTypeLabels: typeObj?.labels || null,
			};
		},
		[ postType ]
	);

	const singularLabel = currentTypeLabels?.singular_name || __( 'Post', 'gatherpress-relations' );

	/**
	 * Step 1: Resolve the source post's slug from block context.
	 */
	const { personSlug, isResolvingPerson } = useSelect(
		( select ) => {
			if ( ! postId ) {
				return { personSlug: null, isResolvingPerson: false };
			}

			const sourcePost = select( coreStore ).getEntityRecord(
				'postType',
				postType || 'gatherpress_person',
				postId
			);

			if ( ! sourcePost ) {
				return { personSlug: null, isResolvingPerson: true };
			}

			return {
				personSlug: sourcePost.slug,
				isResolvingPerson: false,
			};
		},
		[ postId, postType ]
	);

	const shadowSlug = personSlug ? '_' + personSlug : null;

	/*
	 * Derive the shadow taxonomy from the source post's type.
	 */
	const shadowTaxonomy = postType ? '_' + postType : '_gatherpress_person';

	/**
	 * Step 2: Look up the shadow term ID and discover the taxonomy's
	 * REST base.
	 */
	const { shadowTermId, taxonomyRestBase, isResolvingTerm } = useSelect(
		( select ) => {
			if ( ! shadowSlug ) {
				return {
					shadowTermId: null,
					taxonomyRestBase: null,
					isResolvingTerm: false,
				};
			}

			const { getEntityRecords, getTaxonomy } = select( coreStore );
			const taxonomyObj = getTaxonomy( shadowTaxonomy );
			const restBase = taxonomyObj?.rest_base || null;
			const terms = getEntityRecords(
				'taxonomy',
				shadowTaxonomy,
				{ slug: shadowSlug, per_page: 1 }
			);

			if ( ! terms || ! taxonomyObj ) {
				return {
					shadowTermId: null,
					taxonomyRestBase: restBase,
					isResolvingTerm: true,
				};
			}

			return {
				shadowTermId: terms.length > 0 ? terms[ 0 ].id : null,
				taxonomyRestBase: restBase,
				isResolvingTerm: false,
			};
		},
		[ shadowSlug, shadowTaxonomy ]
	);

	/**
	 * Step 3: Discover consumer post types via shared hook.
	 */
	const { consumerTypes, isResolving: isResolvingTypes } =
		useConsumerTypes();

	const resolvedConsumerTypes = consumerTypes || [];

	/**
	 * Build the list of consumer types to query, respecting the
	 * filterPostType attribute.
	 *
	 * @type {Object[]}
	 */
	const filteredConsumerTypes = useMemo( () => {
		if (
			! attributes.filterPostType ||
			resolvedConsumerTypes.length === 0
		) {
			return resolvedConsumerTypes;
		}
		const match = resolvedConsumerTypes.filter(
			( type ) => type.slug === attributes.filterPostType
		);
		return match.length > 0 ? match : resolvedConsumerTypes;
	}, [ attributes.filterPostType, resolvedConsumerTypes ] );

	/**
	 * Build SelectControl options for the post type filter.
	 *
	 * @type {Array<{label: string, value: string}>}
	 */
	const postTypeFilterOptions = useMemo( () => {
		const opts = [
			{
				label: __( 'All', 'gatherpress-relations' ),
				value: '',
			},
		];
		resolvedConsumerTypes.forEach( ( type ) => {
			opts.push( {
				label: type.labels?.name || type.name || type.slug,
				value: type.slug,
			} );
		} );
		return opts;
	}, [ resolvedConsumerTypes ] );

	/**
	 * Step 4: Fetch consumer posts via apiFetch.
	 */
	useEffect( () => {
		if (
			! shadowTermId ||
			! taxonomyRestBase ||
			filteredConsumerTypes.length === 0 ||
			! shadowSlug
		) {
			if ( ! hasLoaded ) {
				setRoles( [] );
			}
			return;
		}

		let cancelled = false;
		setIsFetchingRoles( true );

		const fetches = filteredConsumerTypes.map( ( type ) => {
			const restBase = type.rest_base || type.slug;
			const isEventDateSupported = type?.supports?.[
				'gatherpress-event-date'
			]
				? 'all'
				: null;

			const queryParams = {
				per_page: attributes.maxRoles,
				orderby: 'date',
				order: 'desc',
				_fields: 'id,title,date,meta,type,link',
				gatherpress_event_query: isEventDateSupported,
			};
			queryParams[ taxonomyRestBase ] = shadowTermId;

			const path = addQueryArgs(
				`/wp/v2/${ restBase }`,
				queryParams
			);

			return apiFetch( { path } )
				.then( ( posts ) => {
					if ( ! Array.isArray( posts ) ) {
						return [];
					}

					const typeRoles = [];
					posts.forEach( ( post ) => {
						let relations = [];
						try {
							const raw =
								post.meta?._gatherpress_relations || '[]';
							relations = JSON.parse( raw );
						} catch {
							relations = [];
						}

						const personEntries = relations.filter(
							( e ) =>
								e.slug === shadowSlug
						);

						personEntries.forEach( ( entry ) => {
							let displayDate = post.date;
							if ( isEventDateSupported ) {
								const eventStart =
									post.meta
										?.gatherpress_datetime_start ||
									'';
								if ( eventStart ) {
									displayDate = eventStart;
								}
							}

							typeRoles.push( {
								consumerId: post.id,
								consumerTitle:
									post.title?.rendered ||
									post.title?.raw ||
									'',
								consumerType: type.slug,
								consumerTypeLabel:
									type.labels?.singular_name ||
									type.name,
								consumerDate: displayDate,
								role: entry.role || '',
								department:
									entry.department || 'other',
								billingOrder:
									entry.order || 99,
							} );
						} );
					} );

					return typeRoles;
				} )
				.catch( () => [] );
		} );

		Promise.all( fetches ).then( ( results ) => {
			if ( cancelled ) {
				return;
			}

			const allRoles = results.flat();
			allRoles.sort(
				( a, b ) =>
					new Date( b.consumerDate ) -
					new Date( a.consumerDate )
			);

			setRoles( allRoles.slice( 0, attributes.maxRoles ) );
			setIsFetchingRoles( false );
			setHasLoaded( true );
		} );

		return () => {
			cancelled = true;
		};
	}, [
		shadowTermId,
		taxonomyRestBase,
		filteredConsumerTypes,
		shadowSlug,
		attributes.maxRoles,
	] );

	const isLoading =
		! hasLoaded &&
		( isResolvingPerson ||
			isResolvingTerm ||
			isResolvingTypes ||
			isFetchingRoles );

	/**
	 * Group roles by department if enabled.
	 */
	let grouped = null;
	if ( attributes.groupByDepartment && roles.length > 0 ) {
		grouped = {};
		roles.forEach( ( role ) => {
			const dept = role.department || 'other';
			if ( ! grouped[ dept ] ) {
				grouped[ dept ] = [];
			}
			grouped[ dept ].push( role );
		} );
	}

	/**
	 * Renders a single role item.
	 *
	 * @param {Object} role  - The role object.
	 * @param {number} index - Array index for React key.
	 * @return {Element} The list item element.
	 */
	function renderRoleItem( role, index ) {
		const formattedDate = role.consumerDate
			? formatRoleDate( role.consumerDate, attributes.dateFormat )
			: '';

		return (
			<li key={ index } className="recent-roles__item">
				<div className="recent-roles__item-main">
					<a
						className="recent-roles__consumer-link"
						href={ '#' }
						onClick={ ( e ) => e.preventDefault() }
					>
						{ role.consumerTitle }
					</a>
					<span className="recent-roles__role-name">
						{ role.role || '' }
					</span>
				</div>
				<div className="recent-roles__item-meta">
					{ attributes.showDepartment && role.department && (
						<span className="recent-roles__department-badge">
							{ getDepartmentLabel( role.department, postType || '' ) }
						</span>
					) }
					{ attributes.showPostType && (
						<span className="recent-roles__type-badge">
							{ role.consumerTypeLabel }
						</span>
					) }
					{ attributes.showDate && formattedDate && (
						<span className="recent-roles__date">
							{ formattedDate }
						</span>
					) }
				</div>
			</li>
		);
	}

	/*
	 * Context-aware block title for placeholders.
	 *
	 * Uses the current post type's singular label to produce titles
	 * like "Person Recent Relations" or "Sponsor Recent Relations".
	 */
	const blockTitle = sprintf(
		/* translators: %s: singular post type label (e.g., "Person", "Sponsor") */
		__( '%s Recent Relations', 'gatherpress-relations' ),
		singularLabel
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Display Settings',
						'gatherpress-relations'
					) }
					initialOpen={ true }
				>
					{ postTypeFilterOptions.length > 2 && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Post type',
								'gatherpress-relations'
							) }
							help={ __(
								'Filter roles to a specific post type, or show all.',
								'gatherpress-relations'
							) }
							value={ attributes.filterPostType }
							options={ postTypeFilterOptions }
							onChange={ ( val ) =>
								setAttributes( { filterPostType: val } )
							}
						/>
					) }
					<RangeControl
						__nextHasNoMarginBottom
						label={ __(
							'Maximum entries',
							'gatherpress-relations'
						) }
						value={ attributes.maxRoles }
						onChange={ ( val ) =>
							setAttributes( { maxRoles: val } )
						}
						min={ 1 }
						max={ 50 }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show department badge',
							'gatherpress-relations'
						) }
						checked={ attributes.showDepartment }
						onChange={ ( val ) =>
							setAttributes( { showDepartment: val } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Group by department',
							'gatherpress-relations'
						) }
						checked={ attributes.groupByDepartment }
						onChange={ ( val ) =>
							setAttributes( { groupByDepartment: val } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show post type label',
							'gatherpress-relations'
						) }
						help={ sprintf(
							/* translators: %s: example consumer type labels */
							__( 'Displays the consumer post type label (e.g., %s) next to each entry.', 'gatherpress-relations' ),
							resolvedConsumerTypes.slice( 0, 2 ).map( ( t ) => t.labels?.singular_name || t.name ).join( ', ' ) || __( 'Event, Production', 'gatherpress-relations' )
						) }
						checked={ attributes.showPostType }
						onChange={ ( val ) =>
							setAttributes( { showPostType: val } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show date',
							'gatherpress-relations'
						) }
						checked={ attributes.showDate }
						onChange={ ( val ) =>
							setAttributes( { showDate: val } )
						}
					/>
					{ attributes.showDate && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __(
								'Date format',
								'gatherpress-relations'
							) }
							value={ attributes.dateFormat }
							options={ [
								{
									label: __(
										'Year only (2024)',
										'gatherpress-relations'
									),
									value: 'Y',
								},
								{
									label: __(
										'Month & Year (Mar 2024)',
										'gatherpress-relations'
									),
									value: 'M Y',
								},
								{
									label: __(
										'Full date',
										'gatherpress-relations'
									),
									value: 'full',
								},
							] }
							onChange={ ( val ) =>
								setAttributes( { dateFormat: val } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! postId && (
					<Placeholder
						icon="id-alt"
						label={ __( 'Recent Relations', 'gatherpress-relations' ) }
						instructions={ sprintf(
							/* translators: %s: post type slug */
							__( 'This block requires a post context with gatherpress-relations-to support. Place it on a single template or inside a Query Loop for a supported post type.', 'gatherpress-relations' ),
							postType || ''
						) }
					/>
				) }

				{ postId && isLoading && (
					<div className="recent-roles__loading">
						<Spinner />
						<p>
							{ __(
								'Loading relations…',
								'gatherpress-relations'
							) }
						</p>
					</div>
				) }

				{ postId && ! isLoading && roles.length === 0 && (
					<Placeholder
						icon="id-alt"
						label={ blockTitle }
						instructions={ sprintf(
							/* translators: %s: singular post type label (e.g., "person", "sponsor") */
							__( 'No relations found for this %s. Assign them to consumer posts via the Cast & Crew sidebar panel.', 'gatherpress-relations' ),
							singularLabel.toLowerCase()
						) }
					/>
				) }

				{ postId && ! isLoading && roles.length > 0 && (
					<div className="recent-roles">
						{ grouped ? (
							Object.keys( grouped ).map( ( dept ) => (
								<div
									key={ dept }
									className="recent-roles__group"
								>
									<h4 className="recent-roles__group-title">
										{ getDepartmentLabel( dept ) }
									</h4>
									<ul className="recent-roles__list">
										{ grouped[ dept ].map(
											( role, i ) =>
												renderRoleItem( role, i )
										) }
									</ul>
								</div>
							) )
						) : (
							<ul className="recent-roles__list">
								{ roles.map( ( role, i ) =>
									renderRoleItem( role, i )
								) }
							</ul>
						) }
					</div>
				) }
			</div>
		</>
	);
}
