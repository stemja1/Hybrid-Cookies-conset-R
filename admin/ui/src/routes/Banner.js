/**
 * Zatiaľ bez funkcie — editor bannera príde v Sesii 8.
 *
 * Obrazovka existuje už teraz, aby bola navigácia kompletná a aby bolo
 * vidieť, že route funguje.
 */

import { Card, CardBody, CardHeader, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useData } from '../store/data';

/**
 * Obrazovka bannera.
 *
 * @return {JSX.Element} Obrazovka.
 */
export default function Banner() {
	const { state } = useData();
	const { banners } = state;

	return (
		<div className="hcc-banner-page">
			<Card>
				<CardHeader>{ __( 'Banner', 'hybrid-cookies-conset-r-plus' ) }</CardHeader>
				<CardBody>
					{ banners.length === 0 ? (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Nie je vytvorený žiadny banner. Aktivujte plugin znova, aby sa vytvoril predvolený.',
								'hybrid-cookies-conset-r-plus'
							) }
						</Notice>
					) : (
						<table className="widefat striped">
							<thead>
								<tr>
									<th>{ __( 'Názov', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Región', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Layout', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Pozícia', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Verzia', 'hybrid-cookies-conset-r-plus' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ banners.map( ( banner ) => (
									<tr key={ banner.id }>
										<td>{ banner.title }</td>
										<td>
											<code>{ banner.region }</code>
										</td>
										<td>{ banner.config?.layout }</td>
										<td>{ banner.config?.position }</td>
										<td>v{ banner.version }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }

					<Notice status="info" isDismissible={ false }>
						{ __(
							'Vizuálny editor s náhľadom príde v nasledujúcej fáze. Konfiguráciu zatiaľ meníte cez REST API.',
							'hybrid-cookies-conset-r-plus'
						) }
					</Notice>
				</CardBody>
			</Card>
		</div>
	);
}