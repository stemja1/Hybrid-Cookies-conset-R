/**
 * Kategórie súhlasu.
 */

import {
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useData } from '../store/data';

/**
 * Obrazovka kategórií.
 *
 * CRUD príde v Sesii 8. Teraz je to prehľad — aby bolo vidieť, čo
 * seedovanie vytvorilo, a či sú popisy pre oba jazyky naozaj vložené.
 *
 * @return {JSX.Element} Obrazovka.
 */
export default function Categories() {
	const { state } = useData();
	const { categories } = state;

	return (
		<div className="hcc-categories">
			<Card>
				<CardHeader>
					{ __( 'Kategórie súhlasu', 'hybrid-cookies-conset-r-plus' ) }
				</CardHeader>
				<CardBody>
					<table className="widefat striped">
						<thead>
							<tr>
								<th>{ __( 'Názov', 'hybrid-cookies-conset-r-plus' ) }</th>
								<th>{ __( 'Slug', 'hybrid-cookies-conset-r-plus' ) }</th>
								<th>{ __( 'Popis', 'hybrid-cookies-conset-r-plus' ) }</th>
								<th>{ __( 'Nevyhnutná', 'hybrid-cookies-conset-r-plus' ) }</th>
								<th>
									{ __(
										'Predáva osobné údaje',
										'hybrid-cookies-conset-r-plus'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ categories.map( ( category ) => (
								<tr key={ category.id }>
									<td>
										<strong>{ category.name }</strong>
									</td>
									<td>
										<code>{ category.slug }</code>
									</td>
									<td className="hcc-description-cell">
										{ category.description }
									</td>
									<td>
										{ category.is_necessary
											? '✓'
											: '—' }
									</td>
									<td>
										{ category.sell_personal_data
											? '✓'
											: '—' }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</CardBody>
			</Card>

			<Notice status="info" isDismissible={ false }>
				{ __(
					'Nevyhnutná kategória sa nedá premeniť na dobrovoľnú ani zmazať. Kategóriu s priradenými cookies nie je možné odstrániť, kým ich nepriradíte inam.',
					'hybrid-cookies-conset-r-plus'
				) }
			</Notice>
		</div>
	);
}