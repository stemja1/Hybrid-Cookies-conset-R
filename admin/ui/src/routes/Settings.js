/**
 * Nastavenia pluginu.
 */

import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { errorMessage, post } from '../api/rest';
import { useData } from '../store/data';

/**
 * Obrazovka nastavení.
 *
 * @return {JSX.Element} Obrazovka.
 */
export default function Settings() {
	const { state, bootstrap } = useData();
	const settings = state.settings;

	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const [ draft, setDraft ] = useState( {} );

	if ( ! settings ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ bootstrap.l10n?.error ?? __( 'Nastavenia sa nepodarilo načítať.', 'hybrid-cookies-conset-r-plus' ) }
			</Notice>
		);
	}

	/**
	 * Vráti hodnotu z draftu alebo z uloženého stavu.
	 *
	 * @param {string} key Kľúč.
	 * @return {*} Hodnota.
	 */
	function value( key ) {
		return key in draft ? draft[ key ] : settings[ key ];
	}

	/**
	 * Zmení hodnotu v draftu.
	 *
	 * @param {string} key    Kľúč.
	 * @param {*}      newValue Nová hodnota.
	 * @return {void}
	 */
	function setValue( key, newValue ) {
		setDraft( ( current ) => ( { ...current, [ key ]: newValue } ) );
	}

	/**
	 * Uloží zmeny.
	 *
	 * @return {Promise<void>}
	 */
	async function save() {
		setSaving( true );
		setMessage( null );

		try {
			await post( '/settings', draft );

			setMessage( { status: 'success', text: bootstrap.l10n?.saved } );
			setDraft( {} );
		} catch ( error ) {
			setMessage( { status: 'error', text: errorMessage( error ) } );
		} finally {
			setSaving( false );
		}
	}

	const dirty = Object.keys( draft ).length > 0;

	return (
		<div className="hcc-settings-page">
			{ message && (
				<Notice status={ message.status } onRemove={ () => setMessage( null ) }>
					{ message.text }
				</Notice>
			) }

			<Card>
				<CardHeader>{ __( 'Banner a blokovanie', 'hybrid-cookies-conset-r-plus' ) }</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __(
							'Consent banner na frontende',
							'hybrid-cookies-conset-r-plus'
						) }
						help={ __(
							'Ak je vypnutý, návštevník nemá ako udeliť súhlas a načítajú sa iba nevyhnutné skripty.',
							'hybrid-cookies-conset-r-plus'
						) }
						checked={ !! value( 'banner_enabled' ) }
						onChange={ ( checked ) => setValue( 'banner_enabled', checked ) }
					/>

					<ToggleControl
						label={ __(
							'Blokovanie skriptov tretích strán',
							'hybrid-cookies-conset-r-plus'
						) }
						help={ __(
							'Bloker zachytáva skripty vložené aj inými pluginmi, bez manuálneho označovania.',
							'hybrid-cookies-conset-r-plus'
						) }
						checked={ !! value( 'blocker_enabled' ) }
						onChange={ ( checked ) => setValue( 'blocker_enabled', checked ) }
					/>

					<ToggleControl
						label={ __(
							'Zobraziť banner aj prihláseným',
							'hybrid-cookies-conset-r-plus'
						) }
						checked={ !! value( 'show_on_login' ) }
						onChange={ ( checked ) => setValue( 'show_on_login', checked ) }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( 'Súhlas a logy', 'hybrid-cookies-conset-r-plus' ) }</CardHeader>
				<CardBody>
					<TextControl
						type="number"
						label={ __( 'Platnosť súhlasu (dní)', 'hybrid-cookies-conset-r-plus' ) }
						help={ __(
							'Po uplynutí platnosti je súhlas neplatný a banner sa zobrazí znova.',
							'hybrid-cookies-conset-r-plus'
						) }
						value={ value( 'consent_expiry_days' ) }
						min={ 1 }
						max={ 3650 }
						onChange={ ( next ) => setValue( 'consent_expiry_days', next ) }
					/>

					<TextControl
						type="number"
						label={ __(
							'Retencia logov (dní, 0 = bez mazania)',
							'hybrid-cookies-conset-r-plus'
						) }
						help={ __(
							'Log súhlasov je dôkaz pre GDPR — nechajte ho čo najdlhšie.',
							'hybrid-cookies-conset-r-plus'
						) }
						value={ value( 'log_retention_days' ) }
						min={ 0 }
						max={ 3650 }
						onChange={ ( next ) => setValue( 'log_retention_days', next ) }
					/>

					<TextControl
						type="number"
						label={ __(
							'Rate limit (požiadaviek za minútu na IP)',
							'hybrid-cookies-conset-r-plus'
						) }
						help={ __(
							'Bez limitu by bot mohol zapĺňať tabuľku logov.',
							'hybrid-cookies-conset-r-plus'
						) }
						value={ value( 'rate_limit_per_min' ) }
						min={ 0 }
						max={ 120 }
						onChange={ ( next ) => setValue( 'rate_limit_per_min', next ) }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __( 'Región a súkromie', 'hybrid-cookies-conset-r-plus' ) }
				</CardHeader>
				<CardBody>
					<SelectControl
						label={ __(
							'Regionálna detekcia',
							'hybrid-cookies-conset-r-plus'
						) }
						help={ __(
							'Predvolene plugin nikdy nevolá externé API — určí región z jazyka prehliadača, čo je aproximácia.',
							'hybrid-cookies-conset-r-plus'
						) }
						value={ value( 'region_mode' ) }
						options={ [
							{
								label: __(
									'Automaticky (Accept-Language)',
									'hybrid-cookies-conset-r-plus'
								),
								value: 'auto',
							},
							{
								label: __(
									'Ručne (len nastavené regióny)',
									'hybrid-cookies-conset-r-plus'
								),
								value: 'manual',
							},
							{
								label: __(
									'Vypnutá',
									'hybrid-cookies-conset-r-plus'
								),
								value: 'off',
							},
						] }
						onChange={ ( next ) => setValue( 'region_mode', next ) }
					/>

					<ToggleControl
						label={ __(
							'Zmazať dáta pri odinštalácii',
							'hybrid-cookies-conset-r-plus'
						) }
						help={ __(
							'Predvolene vypnuté — log súhlasov je doklad pre GDPR a mal by zostať.',
							'hybrid-cookies-conset-r-plus'
						) }
						checked={ !! value( 'delete_data_on_uninstall' ) }
						onChange={ ( checked ) =>
							setValue( 'delete_data_on_uninstall', checked )
						}
					/>
				</CardBody>
			</Card>

			<div className="hcc-save-bar">
				<button
					type="button"
					className="components-button is-primary"
					disabled={ ! dirty || saving }
					onClick={ save }
				>
					{ saving ? (
						<>
							<Spinner />
							{ bootstrap.l10n?.saving ??
								__( 'Ukladám…', 'hybrid-cookies-conset-r-plus' ) }
						</>
					) : (
						__( 'Uložiť zmeny', 'hybrid-cookies-conset-r-plus' )
					) }
				</button>

				{ dirty && (
					<button
						type="button"
						className="components-button is-secondary"
						onClick={ () => setDraft( {} ) }
					>
						{ __( 'Zrušiť', 'hybrid-cookies-conset-r-plus' ) }
					</button>
				) }
			</div>
		</div>
	);
}