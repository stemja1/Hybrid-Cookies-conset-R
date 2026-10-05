<?php
/**
 * WP_List_Table so zoznamom cookies.
 *
 * @package HybridCookies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tabuľka zoznamu cookies.
 */
class HCC_Cookie_List_Table extends WP_List_Table {

	/**
	 * Konšruktor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'hcc-cookie',
				'plural'   => 'hcc-cookies',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Stĺpce tabuľky.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Názov', 'hybrid-cookies-conset-r-plus' ),
			'provider'   => __( 'Poskytovateľ', 'hybrid-cookies-conset-r-plus' ),
			'category'   => __( 'Kategória', 'hybrid-cookies-conset-r-plus' ),
			'duration'   => __( 'Doba platnosti', 'hybrid-cookies-conset-r-plus' ),
			'is_default' => __( 'Predvolené', 'hybrid-cookies-conset-r-plus' ),
		);
	}

	/**
	 * Príprava dát.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		// TODO: Implementovať stránkovanie a vyhľadávanie.
		$per_page = 20;
		$offset   = 0;

		$this->items = HCC_Cookie_Catalog::all(
			array(
				'number' => $per_page,
			)
		);

		unset( $offset );
	}

	/**
	 * Vykreslenie stĺpca.
	 *
	 * @param array<string,mixed> $item        Záznam.
	 * @param string              $column_name Názov stĺpca.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'cb':
				return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );

			case 'name':
				return sprintf(
					'<strong>%s</strong><br /><small>%s</small>',
					esc_html( $item['name'] ),
					esc_html( $item['slug'] )
				);

			case 'category':
				$categories = HCC_Helpers::get_categories();
				$label      = isset( $categories[ $item['category'] ] ) ? $categories[ $item['category'] ] : $item['category'];

				return '<span class="hcc-badge hcc-badge--' . esc_attr( $item['category'] ) . '">' . esc_html( $label ) . '</span>';

			case 'duration':
				return (int) $item['duration_days'] > 0
					? esc_html( sprintf( '%d dní', (int) $item['duration_days'] ) )
					: esc_html__( 'Session', 'hybrid-cookies-conset-r-plus' );

			case 'is_default':
				return ! empty( $item['is_default'] )
					? esc_html__( 'Áno', 'hybrid-cookies-conset-r-plus' )
					: esc_html__( 'Nie', 'hybrid-cookies-conset-r-plus' );
		}

		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Akcie riadka.
	 *
	 * @param array<string,mixed> $item Záznam.
	 * @return string
	 */
	public function column_name( $item ) {
		$actions = array(
			'edit'   => sprintf(
				'<a href="#" data-id="%d">%s</a>',
				(int) $item['id'],
				esc_html__( 'Upraviť', 'hybrid-cookies-conset-r-plus' )
			),
			'delete' => sprintf(
				'<a href="#" class="hcc-delete" data-id="%d">%s</a>',
				(int) $item['id'],
				esc_html__( 'Zmazať', 'hybrid-cookies-conset-r-plus' )
			),
		);

		return sprintf( '%1$s %2$s', $this->column_default( $item, 'name' ), $this->row_actions( $actions ) );
	}

	/**
	 * Vykreslí celú tabuľku.
	 *
	 * @return void
	 */
	public function display() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Katalóg cookies', 'hybrid-cookies-conset-r-plus' ); '</h1>';
		parent::display();
		echo '</div>';
	}

	/**
	 * Správa, keď nie sú žiadne záznamy.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Zatiaľ nie sú žiadne cookies v katalógu.', 'hybrid-cookies-conset-r-plus' );
	}
}