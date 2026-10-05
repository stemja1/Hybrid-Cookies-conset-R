/**
 * Dashboard — prehľad stavu pluginu.
 */

import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useData } from '../store/data';

/**
 * Jedna štatistická karta.
 *
 * @param {Object} props       Props.
 * @param {string} props.value Hodnota.
 * @param {string} props.label Názov.
 * @param {string} props.help  Pomocný text.
 * @return {JSX.Element} Karta.
 */
function StatCard( { value, label, help } ) {
	return (
		<Card className="hcc-stat-card">
			<CardBody>
				<div className="hcc-stat-card__value">{ value }</div>
				<div className="hcc-stat-card__label">{ label }</div>
				<p className="hcc-stat-card__help">{ help }</p>
			</CardBody>
		</Card>
	);
}

/**
 * Prehľadová obrazovka.
 *
 * @return {JSX.Element} Obrazovka.
 */
export default function Dashboard() {
	const { state, loadAll, bootstrap } = useData();

	useEffect( () => {
		loadAll();
	}, [ loadAll ] );

	if ( state.status === 'loading' && ! state.dashboard ) {
		return (
			<div className="hcc-loading">
				<Spinner />
				<p>{ __( 'Načítavam…', 'hybrid-cookies-conset-r-plus' ) }</p>
			</div>
		);
	}

	if ( state.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ state.error }
			</Notice>
		);
	}

	const dashboard = state.dashboard ?? {};
	const summary = dashboard.summary ?? {};
	const categories = state.categories ?? [];

	const categoryNames = {};
	categories.forEach( ( category ) => {
		categoryNames[ category.slug ] = category.name;
	} );

	return (
		<div className="hcc-dashboard">
			<div className="hcc-stats">
				<StatCard
					value={ dashboard.categories ?? 0 }
					label={ __( 'Kategórie', 'hybrid-cookies-conset-r-plus' ) }
					help={ __(
						'Definované kategórie súhlasu',
						'hybrid-cookies-conset-r-plus'
					) }
				/>
				<StatCard
					value={ dashboard.cookies ?? 0 }
					label={ __( 'Cookies', 'hybrid-cookies-conset-r-plus' ) }
					help={ __(
						'Záznamov v katalógu',
						'hybrid-cookies-conset-r-plus'
					) }
				/>
				<StatCard
					value={ dashboard.discovered ?? 0 }
					label={ __(
						'Nezdokumentované',
						'hybrid-cookies-conset-r-plus'
					) }
					help={ __(
						'Čakajú na zaradenie do kategórie',
						'hybrid-cookies-conset-r-plus'
					) }
				/>
				<StatCard
					value={ dashboard.consents ?? 0 }
					label={ __( 'Súhlasy', 'hybrid-cookies-conset-r-plus' ) }
					help={ __(
						'Zaznamenaných rozhodnutí',
						'hybrid-cookies-conset-r-plus'
					) }
				/>
			</div>

			<Card>
				<CardHeader>
					{ __( 'Stav pluginu', 'hybrid-cookies-conset-r-plus' ) }
				</CardHeader>
				<CardBody>
					<table className="hcc-status-table">
						<tbody>
							<tr>
								<td>{ __( 'Consent banner', 'hybrid-cookies-conset-r-plus' ) }</td>
								<td>
									<StatusToggle
										enabled={ !! dashboard.banner_enabled }
										label={
											dashboard.banner_enabled
												? __( 'Zapnutý', 'hybrid-cookies-conset-r-plus' )
												: __( 'Vypnutý', 'hybrid-cookies-conset-r-plus' )
										}
									/>
								</td>
							</tr>
							<tr>
								<td>
									{ __( 'Blokovanie skriptov', 'hybrid-cookies-conset-r-plus' ) }
								</td>
								<td>
									<StatusToggle
										enabled={ !! dashboard.blocker_enabled }
										label={
											dashboard.blocker_enabled
												? __( 'Zapnuté', 'hybrid-cookies-conset-r-plus' )
												: __( 'Vypnuté', 'hybrid-cookies-conset-r-plus' )
										}
									/>
								</td>
							</tr>
							<tr>
								<td>{ __( 'Verzia', 'hybrid-cookies-conset-r-plus' ) }</td>
								<td>
									<code>{ dashboard.version ?? bootstrap.version }</code>
								</td>
							</tr>
						</tbody>
					</table>

					{ ! dashboard.banner_enabled && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Banner je vypnutý — návštevníci nemajú možnosť udeliť súhlas. Bez súhlasu sa načítajú iba nevyhnutné skripty.',
								'hybrid-cookies-conset-r-plus'
							) }
						</Notice>
					) }
				</CardBody>
			</Card>

			{ Object.keys( summary ).length > 0 && (
				<Card>
					<CardHeader>
						{ __( 'Súhlasy podľa kategórie', 'hybrid-cookies-conset-r-plus' ) }
					</CardHeader>
					<CardBody>
						<table className="widefat striped">
							<thead>
								<tr>
									<th>{ __( 'Kategória', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Názov', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Používateľov', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Podiel', 'hybrid-cookies-conset-r-plus' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ Object.entries( summary ).map( ( [ slug, data ] ) => (
									<tr key={ slug }>
										<td>
											<code>{ slug }</code>
										</td>
										<td>{ categoryNames[ slug ] ?? '—' }</td>
										<td>{ data.count }</td>
										<td>{ data.percentage } %</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</CardBody>
				</Card>
			) }

			<Card>
				<CardHeader>{ __( 'Ako ďalej', 'hybrid-cookies-conset-r-plus' ) }</CardHeader>
				<CardBody>
					<ol className="hcc-steps">
						<li>
							{ __(
								'Skontrolujte katalóg cookies — časti chýbať popis alebo sú v zlej kategórii.',
								'hybrid-cookies-conset-r-plus'
							) }
						</li>
						<li>
							{ __(
								'Upravte texty bannera tak, aby boli konkrétne pre váš web, nie všeobecné.',
								'hybrid-cookies-conset-r-plus'
							) }
						</li>
						<li>
							{ __(
								'Vložte revoke widget do päta stránky, aby návštevník mohol súhlas kedykoľvek odvolať.',
								'hybrid-cookies-conset-r-plus'
							) }
						</li>
					</ol>
					<p className="hcc-code-hint">
						<code>[hcc_revoke_consent]</code>
					</p>
				</CardBody>
			</Card>
		</div>
	);
}

/**
 * Indikátor stavu.
 *
 * @param {Object}  props       Props.
 * @param {boolean} props.enabled Či je zapnuté.
 * @param {string}  props.label   Text.
 * @return {JSX.Element} Indikátor.
 */
function StatusToggle( { enabled, label } ) {
	return (
		<span className={ `hcc-status ${ enabled ? 'is-on' : 'is-off' }` }>
			<span className="hcc-status__dot" aria-hidden="true" />
			{ label }
		</span>
	);
}