/**
 * Log súhlasov.
 */

import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useData } from '../store/data';

/**
 * Preložené názvy akcií.
 *
 * @param {string} action Akcia z databázy.
 * @return {string} Text.
 */
function actionLabel( action ) {
	switch ( action ) {
		case 'accept_all':
			return __( 'Prijal všetko', 'hybrid-cookies-conset-r-plus' );
		case 'reject_all':
			return __( 'Odmietol', 'hybrid-cookies-conset-r-plus' );
		default:
			return __( 'Vlastná voľba', 'hybrid-cookies-conset-r-plus' );
	}
}

/**
 * Obrazovka logu súhlasov.
 *
 * @return {JSX.Element} Obrazovka.
 */
export default function Logs() {
	const { state, setFilters } = useData();
	const { logs, filters } = state;

	const actionOptions = [
		{ label: __( 'Všetky akcie', 'hybrid-cookies-conset-r-plus' ), value: '' },
		{ label: actionLabel( 'accept_all' ), value: 'accept_all' },
		{ label: actionLabel( 'reject_all' ), value: 'reject_all' },
		{ label: actionLabel( 'custom' ), value: 'custom' },
	];

	return (
		<div className="hcc-logs">
			<Card>
				<CardHeader>{ __( 'Log súhlasov', 'hybrid-cookies-conset-r-plus' ) }</CardHeader>
				<CardBody>
					<div className="hcc-filters">
						<SelectControl
							label={ __( 'Akcia', 'hybrid-cookies-conset-r-plus' ) }
							value={ filters.logAction }
							options={ actionOptions }
							onChange={ ( value ) => setFilters( { logAction: value } ) }
						/>
					</div>

					<p className="hcc-result-count">
						{ logs.total } { __( 'záznamov', 'hybrid-cookies-conset-r-plus' ) }
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					{ logs.rows.length === 0 ? (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Zatiaľ nie sú žiadne záznamy. Log sa začne plniť, keď návštevník interaguje s bannerom.',
								'hybrid-cookies-conset-r-plus'
							) }
						</Notice>
					) : (
						<table className="widefat striped">
							<thead>
								<tr>
									<th>{ __( 'Dátum', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Akcia', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Región', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Kategórie', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Banner', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Návštevník', 'hybrid-cookies-conset-r-plus' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ logs.rows.map( ( row ) => (
									<tr key={ row.id }>
										<td>{ row.created_at }</td>
										<td>{ actionLabel( row.action ) }</td>
										<td>
											<code>{ row.region }</code>
										</td>
										<td>{ parseCategories( row.categories ) }</td>
										<td>v{ row.banner_version }</td>
										<td>
											<code>
												{ String( row.ip_hash ?? '' ).slice( 0, 12 ) }…
											</code>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</CardBody>
			</Card>

			<Notice status="info" isDismissible={ false }>
				{ __(
					'IP adresy sa nikdy neukladajú v čitateľnej forme — vidíte iba SHA-256 hash so saltom. Log je dôkaz o súhlase a má zostať čo najdlhšie.',
					'hybrid-cookies-conset-r-plus'
				) }
			</Notice>
		</div>
	);
}

/**
 * Dekóduje JSON s kategóriami na čitateľný reťazec.
 *
 * @param {unknown} raw Hodnota z API.
 * @return {string} Zoznam kategórií.
 */
function parseCategories( raw ) {
	if ( ! raw ) {
		return '—';
	}

	try {
		const parsed = JSON.parse( raw );

		if ( Array.isArray( parsed ) ) {
			return parsed.join( ', ' );
		}
	} catch ( error ) {
		// Nie je to JSON — vypíšeme ako je.
	}

	return String( raw );
}