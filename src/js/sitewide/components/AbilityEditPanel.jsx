/**
 * Slide-in edit panel for a single ability override.
 *
 * Uses createPortal from @wordpress/element to render outside the main app tree.
 * Tri-state fields (null/false/true) are rendered as RadioControl with string
 * values 'null'/'false'/'true' to avoid Boolean()/'!!' collapsing null and false
 * into the same falsy bucket.
 *
 * @since 0.1.0
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TabPanel, RadioControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import McpVisibilityControl from './McpVisibilityControl';

/**
 * Convert a PHP tri-state value (null|true|false) to a radio string value.
 *
 * NEVER use Boolean() or !! here — that collapses null and false into the same
 * falsy bucket, losing the distinction between "Inherit" and "explicit No".
 *
 * @param {*} value PHP-style tri-state: null, true, or false.
 * @return {'null'|'true'|'false'}
 */
function triStateToString( value ) {
	if ( true === value ) return 'true';
	if ( false === value ) return 'false';
	return 'null'; // null or undefined → Inherit
}

/**
 * Convert a radio string value back to a PHP tri-state value.
 *
 * @param {'null'|'true'|'false'} str
 * @return {null|true|false}
 */
function stringToTriState( str ) {
	if ( 'true' === str ) return true;
	if ( 'false' === str ) return false;
	return null; // 'null' or anything else → Inherit
}

/** Shared RadioControl options for every tri-state field. */
const TRI_STATE_OPTIONS = [
	{ value: 'null',  label: __( 'Inherit (use ability default)', 'acrossai-abilities-manager' ) },
	{ value: 'true',  label: __( 'Yes', 'acrossai-abilities-manager' ) },
	{ value: 'false', label: __( 'No', 'acrossai-abilities-manager' ) },
];

/**
 * TriStateControl renders a tri-state field as a RadioControl.
 *
 * @param {Object}   props
 * @param {string}   props.label         Control label.
 * @param {*}        props.value         null | true | false.
 * @param {*}        props.registryValue Registry default for this field (read-only hint).
 * @param {Function} props.onChange      Callback receives null | true | false.
 * @return {JSX.Element}
 */
function TriStateControl( { label, value, registryValue, onChange } ) {
	const defaultHint = triStateToString( registryValue );
	const hintLabels = { 'null': __( 'Inherit', 'acrossai-abilities-manager' ), 'true': __( 'Yes', 'acrossai-abilities-manager' ), 'false': __( 'No', 'acrossai-abilities-manager' ) };

	return (
		<div className="acrossai-tri-state-control">
			<RadioControl
				label={ label }
				selected={ triStateToString( value ) }
				options={ TRI_STATE_OPTIONS }
				onChange={ ( str ) => onChange( stringToTriState( str ) ) }
			/>
			{ undefined !== registryValue && null !== registryValue && (
				<p className="acrossai-tri-state-control__hint description">
					{ __( 'Ability default:', 'acrossai-abilities-manager' ) }{ ' ' }
					<strong>{ hintLabels[ defaultHint ] }</strong>
				</p>
			) }
		</div>
	);
}

/**
 * AbilityEditPanel — slide-in drawer component.
 *
 * @param {Object}   props
 * @param {string}   props.slug         Ability slug.
 * @param {Object}   props.ability      Merged ability data (may be null).
 * @param {Object}   props.registry     Raw registry ability data (for defaults display).
 * @param {Function} props.onClose      Close handler.
 * @return {JSX.Element|null}
 */
export default function AbilityEditPanel( { slug, ability, registry, onClose } ) {
	const dispatch = useDispatch( STORE_NAME );

	const [ isSaving, setIsSaving ]   = useState( false );
	const [ notice, setNotice ]       = useState( null ); // { status: 'success'|'error', message }

	// Local form state — seeded from ability, stored as PHP values (null/true/false).
	const buildFormState = ( src ) => ( {
		site_allowed:  src?.site_allowed  ?? null,
		readonly:      src?.readonly      ?? null,
		destructive:   src?.destructive   ?? null,
		idempotent:    src?.idempotent    ?? null,
		show_in_rest:  src?.show_in_rest  ?? null,
		show_in_mcp:   src?.show_in_mcp  ?? null,
		mcp_type:      src?.mcp_type      ?? null,
		mcp_servers:   src?.mcp_servers   ?? null,
	} );

	const [ formData, setFormData ] = useState( () => buildFormState( ability ) );

	// Re-seed when ability prop changes (e.g. after external save).
	useEffect( () => {
		setFormData( buildFormState( ability ) );
	}, [ ability ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Close on Escape key.
	useEffect( () => {
		function handleKey( e ) {
			if ( 'Escape' === e.key ) onClose();
		}
		document.addEventListener( 'keydown', handleKey );
		return () => document.removeEventListener( 'keydown', handleKey );
	}, [ onClose ] );

	function setField( key, value ) {
		setFormData( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	const handleSave = useCallback( async () => {
		setIsSaving( true );
		setNotice( null );
		try {
			const result = await dispatch.saveOverride( slug, formData );
			if ( result?.unchanged ) {
				setNotice( { status: 'success', message: __( 'No changes made.', 'acrossai-abilities-manager' ) } );
			} else {
				setNotice( { status: 'success', message: __( 'Settings saved.', 'acrossai-abilities-manager' ) } );
			}
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message || String( err ) } );
		} finally {
			setIsSaving( false );
		}
	}, [ dispatch, slug, formData ] );

	const regVal = ( field ) => registry?.[ field ] ?? null;

	const generalTab = (
		<div className="acrossai-ability-edit-panel__tab-content">
			<TriStateControl
				label={ __( 'Site Allowed', 'acrossai-abilities-manager' ) }
				value={ formData.site_allowed }
				registryValue={ regVal( 'site_allowed' ) }
				onChange={ ( v ) => setField( 'site_allowed', v ) }
			/>
			<TriStateControl
				label={ __( 'Read Only', 'acrossai-abilities-manager' ) }
				value={ formData.readonly }
				registryValue={ regVal( 'readonly' ) }
				onChange={ ( v ) => setField( 'readonly', v ) }
			/>
			<TriStateControl
				label={ __( 'Destructive', 'acrossai-abilities-manager' ) }
				value={ formData.destructive }
				registryValue={ regVal( 'destructive' ) }
				onChange={ ( v ) => setField( 'destructive', v ) }
			/>
			<TriStateControl
				label={ __( 'Idempotent', 'acrossai-abilities-manager' ) }
				value={ formData.idempotent }
				registryValue={ regVal( 'idempotent' ) }
				onChange={ ( v ) => setField( 'idempotent', v ) }
			/>
			<TriStateControl
				label={ __( 'Show in REST', 'acrossai-abilities-manager' ) }
				value={ formData.show_in_rest }
				registryValue={ regVal( 'show_in_rest' ) }
				onChange={ ( v ) => setField( 'show_in_rest', v ) }
			/>
		</div>
	);

	const mcpTab = (
		<div className="acrossai-ability-edit-panel__tab-content">
			<McpVisibilityControl
				showInMcp={ formData.show_in_mcp }
				mcpType={ formData.mcp_type }
				mcpServers={ formData.mcp_servers }
				onChange={ ( partial ) => setFormData( ( prev ) => ( { ...prev, ...partial } ) ) }
			/>
		</div>
	);

	const panel = (
		<>
			{ /* Backdrop — click closes without saving */ }
			<div
				className="acrossai-ability-edit-panel__backdrop"
				role="presentation"
				onClick={ onClose }
			/>
			<div
				className="acrossai-ability-edit-panel"
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Edit Ability Override', 'acrossai-abilities-manager' ) }
			>
				<div className="acrossai-ability-edit-panel__header">
					<h2 className="acrossai-ability-edit-panel__title">
						{ __( 'Edit Ability Override', 'acrossai-abilities-manager' ) }
					</h2>
					<p className="acrossai-ability-edit-panel__slug description">{ slug }</p>
					<Button
						icon="no-alt"
						label={ __( 'Close', 'acrossai-abilities-manager' ) }
						className="acrossai-ability-edit-panel__close"
						onClick={ onClose }
					/>
				</div>

				{ notice && (
					<Notice
						status={ notice.status }
						isDismissible
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }

				<TabPanel
					className="acrossai-ability-edit-panel__tabs"
					tabs={ [
						{ name: 'general', title: __( 'General', 'acrossai-abilities-manager' ) },
						{ name: 'mcp',     title: __( 'MCP', 'acrossai-abilities-manager' ) },
					] }
				>
					{ ( tab ) => 'general' === tab.name ? generalTab : mcpTab }
				</TabPanel>

				<div className="acrossai-ability-edit-panel__footer">
					<Button
						variant="primary"
						onClick={ handleSave }
						disabled={ isSaving }
						isBusy={ isSaving }
					>
						{ isSaving
							? __( 'Saving\u2026', 'acrossai-abilities-manager' )
							: __( 'Save Override', 'acrossai-abilities-manager' ) }
					</Button>
					<Button variant="secondary" onClick={ onClose } disabled={ isSaving }>
						{ __( 'Cancel', 'acrossai-abilities-manager' ) }
					</Button>
				</div>
			</div>
		</>
	);

	return createPortal( panel, document.body );
}
