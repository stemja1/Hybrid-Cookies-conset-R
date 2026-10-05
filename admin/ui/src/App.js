/**
 * Hlavná komponenta aplikácie.
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Navigation, useRoute } from './components/Navigation';
import { DataProvider } from './store/data';
import Banner from './routes/Banner';
import Categories from './routes/Categories';
import Cookies from './routes/Cookies';
import Dashboard from './routes/Dashboard';
import Logs from './routes/Logs';
import Settings from './routes/Settings';

/**
 * Obsah podľa trasy.
 *
 * @param {Object} props      Props.
 * @param {string} props.route Aktuálna trasa.
 * @return {JSX.Element} Obrazovka.
 */
function RouteContent( { route } ) {
	switch ( route ) {
		case 'banner':
			return <Banner />;

		case 'categories':
			return <Categories />;

		case 'cookies':
			return <Cookies />;

		case 'logs':
			return <Logs />;

		case 'settings':
			return <Settings />;

		case 'dashboard':
		default:
			return <Dashboard />;
	}
}

/**
 * Aplikácia.
 *
 * @param {Object} props            Props.
 * @param {Object} props.bootstrap  Bootstrap dáta z PHP.
 * @return {JSX.Element} Aplikácia.
 */
export default function App( { bootstrap } ) {
	const { route, navigate } = useRoute( bootstrap.route ?? 'dashboard' );

	return (
		<DataProvider bootstrap={ bootstrap }>
			<div className="hcc-app-wrap">
				<Navigation route={ route } navigate={ navigate } />

				<main className="hcc-app-content">
					{ route !== 'dashboard' && (
						<Notice status="info" isDismissible={ false } className="hcc-route-hint">
							{ __(
								'Vizuálny editor bannera a CRUD kategórií príde v nasledujúcej fáze — zatiaľ je tu prehľad.',
								'hybrid-cookies-conset-r-plus'
							) }
						</Notice>
					) }

					<RouteContent route={ route } />
				</main>
			</div>
		</DataProvider>
	);
}