/**
 * ManageCastCrewPanel — the shared "Manage Cast & Crew" UI.
 *
 * HOW this component works:
 * ─────────────────────────
 * Provides the full management interface for adding, editing,
 * reordering, and removing person-role entries. Manages both
 * `_gatherpress_relations` meta and `_gatherpress_person` taxonomy
 * terms in sync.
 *
 * WHY a shared component?
 * This panel is used by both the PluginDocumentSettingPanel (sidebar
 * plugin) and could be used by the cast-crew-list block's
 * InspectorControls. Extracting it avoids the massive duplication
 * that previously existed.
 *
 * @package GatherPressRelations
 */

import { __ } from '@wordpress/i18n';
import {
	TextControl,
	SelectControl,
	Button,
	Spinner,
	Icon,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { dragHandle } from '@wordpress/icons';

import { getDepartmentOptions, getDepartmentLabel } from '../utils/departments';
import { getSourceTypes, getDefaultSourceType } from '../utils/source-types';
import { useRelationsMeta } from '../hooks/use-relations-meta';
import { useShadowTermSync } from '../hooks/use-shadow-term-sync';
import { useShadowTermLookup } from '../hooks/use-shadow-term-lookup';
import { usePersonDataMap } from '../hooks/use-person-data-map';
import { usePersonSearch } from '../hooks/use-person-search';

/**
 * The "Manage Cast & Crew" panel contents.
 *
 * @param {Object}      props          - Component props.
 * @param {string}      props.postType - The current post type slug.
 * @param {number|null} props.postId   - The current post ID.
 * @return {Element} The panel contents.
 */
export function ManageCastCrewPanel( { postType, postId } ) {
	const { relations, setRelations } = useRelationsMeta( postType );

	/*
	 * Resolve source types from the localised config.
	 *
	 * WHY getSourceTypes() at render time?
	 * The list of connectable source types is populated by PHP
	 * via wp_localize_script. Reading it inside the component
	 * ensures the global is available (lazy evaluation).
	 */
	const sourceTypes = getSourceTypes();
	const defaultSource = getDefaultSourceType();

	const [ activeSourceType, setActiveSourceType ] = useState( defaultSource.slug );
	const activeSourceConfig = sourceTypes.find( ( s ) => s.slug === activeSourceType ) || defaultSource;

	const { currentTermIds, addShadowTerm, removeShadowTerm } =
		useShadowTermSync( postType, postId, activeSourceConfig.shadowTaxonomy );
	const shadowTermLookup = useShadowTermLookup( relations, activeSourceConfig.shadowTaxonomy );
	const personDataMap = usePersonDataMap( relations, activeSourceType );

	/*
	 * Resolve department options for the active source post type.
	 *
	 * WHY pass the source type (not consumer type)?
	 * getDepartmentOptions() checks for per-source-type overrides
	 * from the `gatherpress_relations_departments` PHP filter.
	 * A gatherpress_person source may have Speaker/Moderation/Host
	 * while a gatherpress_sponsor source uses Gold/Silver/Bronze —
	 * regardless of which consumer post type they're assigned to.
	 *
	 * NOTE: departmentOptions is recalculated whenever activeSourceType
	 * changes (see the useState + getDepartmentOptions call below the
	 * source type selector).
	 */
	const departmentOptionsDefault = getDepartmentOptions( defaultSource.slug );

	/*
	 * Resolve department options for the currently active source type.
	 * This must be called here (not at the top) so it reacts to
	 * activeSourceType changes.
	 */
	const departmentOptions = getDepartmentOptions( activeSourceType );

	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ dragFromIndex, setDragFromIndex ] = useState( null );
	const [ dragOverIndex, setDragOverIndex ] = useState( null );
	const [ newEntry, setNewEntry ] = useState( {
		role: '',
		department: departmentOptionsDefault.length > 0 ? departmentOptionsDefault[ 0 ].value : 'other',
	} );
	const [ searchTerm, setSearchTerm ] = useState( '' );

	const { personResults, isSearching } = usePersonSearch( searchTerm, activeSourceType );

	/**
	 * Adds a new person-role entry and syncs the shadow term.
	 *
	 * @param {Object} person - The person post object from search results.
	 */
	function addEntry( person ) {
		const shadowSlug = '_' + person.slug;
		const entry = {
			slug: shadowSlug,
			id: person.id,
			type: activeSourceType,
			role: newEntry.role,
			department: newEntry.department,
			order: relations.length + 1,
		};

		setRelations( [ ...relations, entry ] );

		const existingTermId = shadowTermLookup[ shadowSlug ];
		addShadowTerm( shadowSlug, existingTermId );

		setNewEntry( {
			role: '',
			department: departmentOptions.length > 0 ? departmentOptions[ 0 ].value : 'other',
		} );
		setSearchTerm( '' );
	}

	/**
	 * Removes an entry and removes the shadow term if it was the
	 * last occurrence of that person.
	 *
	 * @param {number} index - The array index to remove.
	 */
	function removeEntry( index ) {
		const removedEntry = relations[ index ];
		const updated = relations.filter( ( _, i ) => i !== index );

		setRelations( updated );

		if ( removedEntry ) {
			const shadowSlug = removedEntry.slug;
			const stillPresent = updated.some(
				( e ) => e.slug === shadowSlug
			);

			if ( ! stillPresent ) {
				const termId = shadowTermLookup[ shadowSlug ];
				removeShadowTerm( shadowSlug, termId );
			}
		}

		if ( editingIndex === index ) {
			setEditingIndex( null );
		}
	}

	/**
	 * Updates a single field on an entry.
	 *
	 * @param {number} index - The entry index.
	 * @param {string} field - The field name to update.
	 * @param {*}      value - The new value.
	 */
	function updateEntry( index, field, value ) {
		const updated = relations.map( ( entry, i ) => {
			if ( i !== index ) {
				return entry;
			}
			return { ...entry, [ field ]: value };
		} );
		setRelations( updated );
	}

	/**
	 * Handles drag-and-drop reordering.
	 *
	 * @param {number|null} fromIndex - Source index.
	 * @param {number|null} toIndex   - Destination index.
	 */
	function handleDragReorder( fromIndex, toIndex ) {
		if (
			fromIndex === null ||
			toIndex === null ||
			fromIndex === toIndex
		) {
			setDragFromIndex( null );
			setDragOverIndex( null );
			return;
		}

		const updated = [ ...relations ];
		const [ moved ] = updated.splice( fromIndex, 1 );
		updated.splice(
			toIndex > fromIndex ? toIndex - 1 : toIndex,
			0,
			moved
		);

		setRelations( updated );
		setDragFromIndex( null );
		setDragOverIndex( null );
	}

	return (
		<>
			<div className="cast-crew-editor__add-section">
				{ sourceTypes.length > 1 && (
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Source type', 'gatherpress-relations' ) }
						value={ activeSourceType }
						options={ sourceTypes.map( ( s ) => ( {
							label: s.label,
							value: s.slug,
						} ) ) }
						onChange={ ( val ) => {
							setActiveSourceType( val );
							setSearchTerm( '' );
						} }
					/>
				) }
				<TextControl
					__nextHasNoMarginBottom
					label={ activeSourceConfig.singularLabel
						? `${ __( 'Search', 'gatherpress-relations' ) } ${ activeSourceConfig.singularLabel.toLowerCase() }`
						: __( 'Search person', 'gatherpress-relations' ) }
					value={ searchTerm }
					onChange={ setSearchTerm }
					placeholder={ __( 'Type a name…', 'gatherpress-relations' ) }
				/>
				{ isSearching && <Spinner /> }
				{ personResults.length > 0 && (
					<ul className="cast-crew-editor__search-results">
						{ personResults.map( ( person ) => (
							<li key={ person.id }>
								<Button
									variant="secondary"
									size="small"
									onClick={ () => addEntry( person ) }
								>
									{ person.title.rendered ||
										person.title.raw ||
										person.slug }
								</Button>
							</li>
						) ) }
					</ul>
				) }
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Role', 'gatherpress-relations' ) }
					value={ newEntry.role }
					onChange={ ( val ) =>
						setNewEntry( { ...newEntry, role: val } )
					}
					placeholder={ __(
						'e.g. Hamlet, Director…',
						'gatherpress-relations'
					) }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Department', 'gatherpress-relations' ) }
					value={ newEntry.department }
					options={ departmentOptions }
					onChange={ ( val ) =>
						setNewEntry( { ...newEntry, department: val } )
					}
				/>
			</div>

			{ relations.length > 0 && (
				<div className="cast-crew-editor__entries">
					<h4 className="cast-crew-editor__entries-title">
						{ __( 'Current entries', 'gatherpress-relations' ) }
						<span className="cast-crew-editor__entries-count">
							{ relations.length }
						</span>
					</h4>
					{ relations.map( ( entry, idx ) => {
						const isDragging = dragFromIndex === idx;
						const isDropTarget =
							dragOverIndex === idx &&
							dragFromIndex !== null &&
							dragFromIndex !== idx;

						return (
							<div
								key={ idx }
								className={ `cast-crew-editor__entry${
									isDragging
										? ' cast-crew-editor__entry--dragging'
										: ''
								}${
									isDropTarget
										? ' cast-crew-editor__entry--drop-above'
										: ''
								}` }
								draggable={ true }
								onDragStart={ ( e ) => {
									setDragFromIndex( idx );
									e.dataTransfer.effectAllowed = 'move';
									e.dataTransfer.setData(
										'text/plain',
										String( idx )
									);
								} }
								onDragOver={ ( e ) => {
									e.preventDefault();
									e.dataTransfer.dropEffect = 'move';
									setDragOverIndex( idx );
								} }
								onDrop={ ( e ) => {
									e.preventDefault();
									handleDragReorder( dragFromIndex, idx );
								} }
								onDragEnd={ () => {
									setDragFromIndex( null );
									setDragOverIndex( null );
								} }
							>
								<div className="cast-crew-editor__entry-row">
									<span
										className="cast-crew-editor__drag-handle"
										aria-label={ __(
											'Drag to reorder',
											'gatherpress-relations'
										) }
										role="img"
									>
										<Icon icon={ dragHandle } size={ 18 } />
									</span>
									<div className="cast-crew-editor__entry-info">
										<span className="cast-crew-editor__entry-name">
											{ personDataMap.get(
												entry.id
											)?.name ||
												entry.slug }
										</span>
										<span className="cast-crew-editor__entry-detail">
											{ entry.role ? (
												<>
													<em>{ entry.role }</em>
													{ ' — ' }
												</>
											) : null }
											{ getDepartmentLabel(
												entry.department,
												entry.type || activeSourceType
											) }
										</span>
									</div>
									<div className="cast-crew-editor__entry-actions">
										<Button
											size="small"
											icon="edit"
											label={ __(
												'Edit',
												'gatherpress-relations'
											) }
											onClick={ () =>
												setEditingIndex(
													editingIndex === idx
														? null
														: idx
												)
											}
											isPressed={ editingIndex === idx }
										/>
										<Button
											size="small"
											icon="trash"
											label={ __(
												'Remove',
												'gatherpress-relations'
											) }
											isDestructive
											onClick={ () =>
												removeEntry( idx )
											}
										/>
									</div>
								</div>
								{ editingIndex === idx && (
									<div className="cast-crew-editor__entry-edit">
										<TextControl
											__nextHasNoMarginBottom
											label={ __(
												'Role',
												'gatherpress-relations'
											) }
											value={ entry.role }
											onChange={ ( val ) =>
												updateEntry(
													idx,
													'role',
													val
												)
											}
										/>
										<SelectControl
											__nextHasNoMarginBottom
											label={ __(
												'Department',
												'gatherpress-relations'
											) }
											value={ entry.department }
											options={ getDepartmentOptions( entry.type || activeSourceType ) }
											onChange={ ( val ) =>
												updateEntry(
													idx,
													'department',
													val
												)
											}
										/>
									</div>
								) }
							</div>
						);
					} ) }
				</div>
			) }
		</>
	);
}
