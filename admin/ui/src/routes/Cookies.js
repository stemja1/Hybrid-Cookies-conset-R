/**
 * Katalóg cookies.
 */

import {
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useData } from '../store/data';

/**
 * Obrazovka katalógu cookies.
 *
 * @return {JSX.Element} Obrazovka.
 */
export default function Cookies() {
	const { state, setFilters } = useData();
	const { cookies, categories, filters } = state;

	const categoryNames = {};
	categories.forEach( ( category ) => {
		categoryNames[ category.id ] = category.name;
	} );

	const categoryOptions = [
		{ label: __( 'Všetky kategórie', 'hybrid-cookies-conset-r-plus' ), value: 0 },
		...categories.map( ( category ) => ( {
			label: category.name,
			value: category.id,
		} ) ),
	];

	return (
		<div className="hcc-cookies">
			<Card>
				<CardHeader>{ __( 'Katalóg cookies', 'hybrid-cookies-conset-r-plus' ) }</CardHeader>
				<CardBody>
					<div className="hcc-filters">
						<TextControl
							label={ __( 'Hľadať', 'hybrid-cookies-conset-r-plus' ) }
							value={ filters.search }
							placeholder={ __(
								'Názov, poskytovateľ alebo doména',
								'hybrid-cookies-conset-r-plus'
							) }
							onChange={ ( value ) => setFilters( { search: value } ) }
						/>
						<SelectControl
							label={ __( 'Kategória', 'hybrid-cookies-conset-r-plus' ) }
							value={ filters.categoryId }
							options={ categoryOptions }
							onChange={ ( value ) =>
								setFilters( { categoryId: Number( value ) } )
							}
						/>
					</div>

					<p className="hcc-result-count">
						{ cookies.total } { __( 'záznamov', 'hybrid-cookies-conset-r-plus' ) }
					</p>
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					{ cookies.rows.length === 0 ? (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Katalóg je prázdny. Spustite aktiváciu pluginu znova, aby sa naimportovali predvolené cookies.',
								'hybrid-cookies-conset-r-plus'
							) }
						</Notice>
					) : (
						<table className="widefat striped hcc-cookies-table">
							<thead>
								<tr>
									<th>{ __( 'Názov', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Poskytovateľ', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Kategória', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Doba', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Doména', 'hybrid-cookies-conset-r-plus' ) }</th>
									<th>{ __( 'Zdroj', 'hybrid-cookies-conset-r-plus' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ cookies.rows.map( ( cookie ) => (
									<tr key={ cookie.id }>
										<td>
											<code>{ cookie.name }</code>
										</td>
										<td>{ cookie.provider || '—' }</td>
										<td>
											<span
												className={ `hcc-badge hcc-badge--${
													categories.find(
														( c ) => c.id === cookie.category_id
													)?.slug ?? 'unknown'
												}` }
											>
												{ categoryNames[ cookie.category_id ] ??
													__( 'Nezaradené', 'hybrid-cookies-conset-r-plus' ) }
											</span>
										</td>
										<td>{ cookie.duration || '—' }</td>
										<td>
											<code>{ cookie.domain_pattern || '—' }</code>
										</td>
										<td>
											{ cookie.is_discovered
												? __( 'Autodetekcia', 'hybrid-cookies-conset-r-plus' )
												: __( 'Ručne', 'hybrid-cookies-conset-r-plus' ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</CardBody>
			</Card>
		</div>
	);
}