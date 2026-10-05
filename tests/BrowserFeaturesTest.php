<?php

require_once __DIR__ . '/TestCase.php';

/**
 * The 1.3 options of [mulino_documents]: list layout, details, search
 * and page links.
 */
class BrowserFeaturesTest extends MULINO_TestCase {

	private function mock_shortcode_atts() {
		WP_Mock::userFunction( 'shortcode_atts' )->andReturnUsing(
			function ( $defaults, $atts ) {
				return array_merge( $defaults, (array) $atts );
			}
		);
		WP_Mock::userFunction( 'sanitize_title' )->andReturnUsing(
			function ( $title ) {
				return strtolower( trim( $title ) );
			}
		);
	}

	private function mock_document( $titles, $descriptions = array() ) {
		WP_Mock::userFunction( 'get_post_meta' )->andReturnUsing(
			function ( $id, $key ) use ( $descriptions ) {
				if ( '_mulino_description' === $key ) {
					return isset( $descriptions[ $id ] ) ? $descriptions[ $id ] : '';
				}
				return 42;
			}
		);
		WP_Mock::userFunction( 'wp_get_attachment_url' )->andReturn( 'https://example.test/uploads/minutes.pdf' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			function ( $url, $component = -1 ) {
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'get_the_title' )->andReturnUsing(
			function ( $post ) use ( $titles ) {
				return $titles[ $post->ID ];
			}
		);
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		WP_Mock::userFunction( 'add_query_arg' )->andReturnUsing(
			function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);
		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
	}

	public function test_new_options_keep_the_old_look_by_default() {
		$this->mock_shortcode_atts();

		$args = mulino_parse_shortcode_atts( '' );

		$this->assertSame( 'grid', $args['layout'] );
		$this->assertFalse( $args['details'] );
		$this->assertFalse( $args['search'] );
		$this->assertSame( 0, $args['per_page'] );
		$this->assertFalse( $args['logged_in_only'] );
	}

	public function test_the_list_layout_shows_details_unless_told_not_to() {
		$this->mock_shortcode_atts();

		$this->assertTrue( mulino_parse_shortcode_atts( array( 'layout' => 'list' ) )['details'] );
		$this->assertFalse(
			mulino_parse_shortcode_atts(
				array(
					'layout'  => 'list',
					'details' => 'no',
				)
			)['details']
		);
		$this->assertTrue( mulino_parse_shortcode_atts( array( 'details' => 'yes' ) )['details'] );
		$this->assertSame( 'grid', mulino_parse_shortcode_atts( array( 'layout' => 'table' ) )['layout'] );
		$this->assertSame( 0, mulino_parse_shortcode_atts( array( 'per_page' => '-5' ) )['per_page'] );
	}

	public function test_list_rows_show_type_size_date_and_description() {
		$this->mock_escaping_functions();
		$this->mock_document( array( 101 => 'Referat' ), array( 101 => 'Ekstraordinær generalforsamling' ) );
		WP_Mock::userFunction( 'wp_get_attachment_metadata' )->andReturn( array( 'filesize' => 2048 ) );
		WP_Mock::userFunction( 'size_format' )->andReturn( '2 KB' );
		WP_Mock::userFunction( 'get_the_date' )->andReturnUsing(
			function ( $format ) {
				return 'Y-m-d' === $format ? '2026-03-01' : '1. marts 2026';
			}
		);

		$html = mulino_render_document_items(
			array( $this->make_post( 101 ) ),
			array(
				'layout'  => 'list',
				'details' => true,
			)
		);

		$this->assertStringContainsString( 'mulino-list', $html );
		$this->assertStringContainsString( 'PDF', $html );
		$this->assertStringContainsString( '2 KB', $html );
		$this->assertStringContainsString( '<time datetime="2026-03-01">1. marts 2026</time>', $html );
		$this->assertStringContainsString( 'Ekstraordinær generalforsamling', $html );
	}

	public function test_search_matches_names_and_descriptions_ignoring_case() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		$this->mock_document(
			array(
				101 => 'Referat 2024',
				102 => 'Budget 2024',
				103 => 'Vedtægter',
			),
			array( 103 => 'Ændret på generalforsamlingen' )
		);
		WP_Mock::userFunction( 'get_posts' )->andReturn( array( $this->make_post( 101 ), $this->make_post( 102 ), $this->make_post( 103 ) ) );

		$found = mulino_search_documents( false, 'mulino_folder', 'REFERAT' );
		$this->assertSame( array( 101 ), wp_list_pluck_ids( $found ) );

		$found = mulino_search_documents( false, 'mulino_folder', 'ændret' );
		$this->assertSame( array( 103 ), wp_list_pluck_ids( $found ) );
	}

	public function test_search_inside_a_folder_includes_its_subfolders() {
		$this->mock_everything_public();
		$folder   = $this->make_term( 5, 'Referater', 'referater' );
		$captured = null;
		WP_Mock::userFunction( 'get_terms' )->andReturn( array( 20, 21 ) );
		WP_Mock::userFunction( 'get_posts' )->andReturnUsing(
			function ( $args ) use ( &$captured ) {
				$captured = $args;
				return array();
			}
		);

		mulino_search_documents( $folder, 'mulino_folder', 'x' );

		$this->assertSame( array( 20, 21, 5 ), $captured['tax_query'][0]['terms'] );
	}

	public function test_long_folders_are_split_into_pages() {
		$this->mock_escaping_functions();
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		$titles = array();
		$posts  = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$titles[ 100 + $i ] = 'Doc ' . $i;
			$posts[]            = $this->make_post( 100 + $i );
		}
		$this->mock_document( $titles );
		WP_Mock::userFunction( 'get_posts' )->andReturn( $posts );

		WP_Mock::userFunction( 'absint' )->andReturnUsing(
			function ( $n ) {
				return abs( (int) $n );
			}
		);
		$_GET['mulino_page'] = '2';
		$html                = mulino_render_documents( $this->make_term( 5, '2024', '2024' ), 'mulino_folder', array( 'per_page' => 2 ) );
		unset( $_GET['mulino_page'] );

		$this->assertStringContainsString( 'Doc 3', $html );
		$this->assertStringContainsString( 'Doc 4', $html );
		$this->assertStringNotContainsString( 'Doc 1<', $html );
		$this->assertStringNotContainsString( 'Doc 5', $html );
		$this->assertStringContainsString( 'Page 2 of 3', $html );
		$this->assertStringContainsString( 'mulino_page=3', $html );
	}
}

function wp_list_pluck_ids( $posts ) {
	return array_map(
		function ( $post ) {
			return $post->ID;
		},
		$posts
	);
}
