<?php

require_once __DIR__ . '/TestCase.php';

class ShortcodeTest extends MULINO_TestCase {

	const TAXONOMY = 'mulino_folder';

	// ---------------------------------------------------------------
	// mulino_render_breadcrumb()
	// ---------------------------------------------------------------

	public function test_breadcrumb_at_root_only_shows_home_link() {
		$this->mock_escaping_functions();
		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );

		$html = mulino_render_breadcrumb( false, self::TAXONOMY );

		$this->assertStringContainsString( 'mulino-breadcrumb', $html );
		$this->assertStringContainsString( 'Home', $html );
		$this->assertStringContainsString( 'https://example.test/docs', $html );
		// No current-folder marker should be printed when nothing is selected.
		$this->assertStringNotContainsString( 'mulino-current', $html );
	}

	public function test_breadcrumb_lists_ancestors_from_root_down_to_current() {
		$this->mock_escaping_functions();

		$decade = $this->make_term( 5, '2020s', '2020s' );
		$year   = $this->make_term( 10, '2023', '2023' );
		$q1     = $this->make_term( 20, 'Q1', 'q1' );

		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
		// Real WordPress returns ancestors nearest-first ([year, decade]);
		// the function is responsible for reversing that into root-first
		// order for display -- this test would fail if that reversal
		// were ever removed.
		WP_Mock::userFunction( 'get_ancestors' )
			->with( 20, self::TAXONOMY, 'taxonomy' )
			->andReturn( array( 10, 5 ) );
		WP_Mock::userFunction( 'get_term' )->with( 10, self::TAXONOMY )->andReturn( $year );
		WP_Mock::userFunction( 'get_term' )->with( 5, self::TAXONOMY )->andReturn( $decade );
		WP_Mock::userFunction( 'add_query_arg' )
			->andReturnUsing(
				function ( $key, $value, $url ) {
					return $url . '?' . $key . '=' . $value;
				}
			);

		$html = mulino_render_breadcrumb( $q1, self::TAXONOMY );

		$decade_pos  = strpos( $html, '2020s' );
		$year_pos    = strpos( $html, '2023' );
		$current_pos = strpos( $html, 'Q1' );

		$this->assertNotFalse( $decade_pos );
		$this->assertNotFalse( $year_pos );
		$this->assertNotFalse( $current_pos );
		$this->assertTrue( $decade_pos < $year_pos, 'Decade should appear before year in the breadcrumb.' );
		$this->assertTrue( $year_pos < $current_pos, 'Year should appear before the current folder.' );
		$this->assertStringContainsString( 'mulino-current', $html );
	}

	// ---------------------------------------------------------------
	// mulino_render_subfolders()
	// ---------------------------------------------------------------

	public function test_subfolders_returns_empty_string_when_there_are_none() {
		WP_Mock::userFunction( 'get_terms' )->andReturn( array() );

		$html = mulino_render_subfolders( false, self::TAXONOMY );

		$this->assertSame( '', $html );
	}

	public function test_subfolders_renders_a_card_per_folder() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$this->mock_escaping_functions();

		$decades = array(
			$this->make_term( 1, '2010s', '2010s' ),
			$this->make_term( 2, '2020s', '2020s' ),
		);

		WP_Mock::userFunction( 'get_terms' )->andReturn( $decades );
		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
		WP_Mock::userFunction( 'add_query_arg' )
			->andReturnUsing(
				function ( $key, $value, $url ) {
					return $url . '?' . $key . '=' . $value;
				}
			);

		$html = mulino_render_subfolders( false, self::TAXONOMY );

		$this->assertStringContainsString( '2010s', $html );
		$this->assertStringContainsString( '2020s', $html );
		$this->assertStringContainsString( 'mulino_folder=2010s', $html );
		$this->assertStringContainsString( 'mulino_folder=2020s', $html );
		$this->assertSame( 2, substr_count( $html, 'mulino-card--folder' ) );
	}

	// ---------------------------------------------------------------
	// mulino_folder_icon_svg()
	// ---------------------------------------------------------------

	public function test_folder_icon_is_an_svg() {
		$html = mulino_folder_icon_svg();

		$this->assertStringStartsWith( '<svg', trim( $html ) );
	}

	// ---------------------------------------------------------------
	// mulino_render_documents()
	// ---------------------------------------------------------------

	/**
	 * At the top of the whole library, documents that aren't in any
	 * folder are listed (the same ones the admin screen shows under
	 * "All"). Before 1.1 they were silently left out.
	 */
	public function test_documents_at_root_queries_documents_without_a_folder() {
		$captured_args = null;

		WP_Mock::userFunction( 'get_posts' )
			->once()
			->andReturnUsing(
				function ( $args ) use ( &$captured_args ) {
					$captured_args = $args;
					return array();
				}
			);
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'get_terms' )->andReturn( array( $this->make_term( 1, '2010s', '2010s' ), $this->make_term( 2, '2020s', '2020s' ) ) );

		$html = mulino_render_documents( false, self::TAXONOMY );

		$this->assertSame( 'NOT EXISTS', $captured_args['tax_query'][0]['operator'] );
		// The root has folders, so no "empty" message underneath them.
		$this->assertSame( '', $html );
	}

	public function test_empty_message_shown_when_folder_has_no_subfolders_either() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array() );
		WP_Mock::userFunction( 'get_terms' )->andReturn( array() );

		$html = mulino_render_documents( $folder, self::TAXONOMY );

		$this->assertStringContainsString( 'No documents in this folder.', $html );
	}

	/**
	 * Regression test for the bug where a folder containing only
	 * subfolders (no documents of its own) still showed "No documents
	 * in this folder." underneath them.
	 */
	public function test_empty_message_hidden_when_folder_has_subfolders() {
		$folder = $this->make_term( 5, '2020s', '2020s' );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array() );
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array( 5 ) );
		WP_Mock::userFunction( 'get_terms' )->andReturn(
			array(
				$this->make_term( 21, '2021', '2021' ),
				$this->make_term( 22, '2022', '2022' ),
			)
		);

		$html = mulino_render_documents( $folder, self::TAXONOMY );

		$this->assertSame( '', $html );
	}

	/**
	 * Regression test for the bug where a document filed under a child
	 * folder (e.g. "2023") also showed up when viewing the parent
	 * folder (e.g. "2020s"), because tax_query's include_children
	 * defaults to true.
	 */
	public function test_get_posts_is_called_with_include_children_disabled() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$folder        = $this->make_term( 5, '2023', '2023' );
		$captured_args = null;

		WP_Mock::userFunction( 'get_posts' )
			->once()
			->andReturnUsing(
				function ( $args ) use ( &$captured_args ) {
					$captured_args = $args;
					return array();
				}
			);
		WP_Mock::userFunction( 'get_terms' )->andReturn( array() );
		$this->mock_escaping_functions();

		mulino_render_documents( $folder, self::TAXONOMY );

		$this->assertIsArray( $captured_args, 'get_posts() should have been called.' );
		$this->assertFalse( $captured_args['tax_query'][0]['include_children'] );
	}

	public function test_documents_renders_a_card_with_download_link() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );
		$post   = $this->make_post( 101 );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array( $post ) );
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 101, '_mulino_file_id', true )
			->andReturn( 42 );
		WP_Mock::userFunction( 'wp_get_attachment_url' )
			->with( 42 )
			->andReturn( 'https://example.test/uploads/2023/09/minutes.pdf' );
		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing(
				function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				}
			);
		WP_Mock::userFunction( 'get_the_title' )
			->with( $post )
			->andReturn( 'Referat generalforsamling 2023' );

		WP_Mock::userFunction( 'add_query_arg' )->andReturnUsing(
			function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);

		$html = mulino_render_documents( $folder, self::TAXONOMY );

		// The link goes through ?mulino_document=, so it survives a new version of the file.
		$this->assertStringContainsString( 'mulino_document=101', $html );
		$this->assertStringNotContainsString( 'minutes.pdf', $html );
		$this->assertStringContainsString( 'Referat generalforsamling 2023', $html );
		$this->assertStringContainsString( 'mulino-card--file', $html );
	}

	public function test_documents_without_an_attached_file_are_skipped() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );
		$post   = $this->make_post( 102 );

		WP_Mock::userFunction( 'get_posts' )->andReturn( array( $post ) );
		// No file was ever attached to this document post.
		WP_Mock::userFunction( 'get_post_meta' )
			->with( 102, '_mulino_file_id', true )
			->andReturn( 0 );

		$html = mulino_render_documents( $folder, self::TAXONOMY );

		$this->assertStringNotContainsString( 'mulino-card--file', $html );
	}

	// ---------------------------------------------------------------
	// Sorting (1.1)
	// ---------------------------------------------------------------

	public function test_natural_sort_puts_2_before_10() {
		$items = array( 'Minutes 10', 'Minutes 2', 'minutes 1' );

		$sorted = mulino_natural_sort(
			$items,
			function ( $item ) {
				return $item;
			}
		);

		$this->assertSame( array( 'minutes 1', 'Minutes 2', 'Minutes 10' ), $sorted );
	}

	public function test_natural_sort_desc_reverses_the_order() {
		$sorted = mulino_natural_sort(
			array( '2009', '2026', '2010' ),
			function ( $item ) {
				return $item;
			},
			'desc'
		);

		$this->assertSame( array( '2026', '2010', '2009' ), $sorted );
	}

	public function test_subfolders_can_be_shown_newest_year_first() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$this->mock_escaping_functions();

		WP_Mock::userFunction( 'get_terms' )->andReturn(
			array(
				$this->make_term( 1, '2009', '2009' ),
				$this->make_term( 2, '2026', '2026' ),
				$this->make_term( 3, '2010', '2010' ),
			)
		);
		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
		WP_Mock::userFunction( 'add_query_arg' )->andReturn( 'https://example.test/docs' );

		$html = mulino_render_subfolders( false, self::TAXONOMY, array( 'folder_order' => 'desc' ) );

		$this->assertTrue( strpos( $html, '2026' ) < strpos( $html, '2010' ) );
		$this->assertTrue( strpos( $html, '2010' ) < strpos( $html, '2009' ) );
	}

	public function test_documents_are_sorted_naturally_by_name() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$this->mock_escaping_functions();

		$folder = $this->make_term( 5, '2023', '2023' );
		$titles = array(
			201 => 'Referat 10',
			202 => 'Referat 2',
		);

		WP_Mock::userFunction( 'get_posts' )->andReturn( array( $this->make_post( 201 ), $this->make_post( 202 ) ) );
		WP_Mock::userFunction( 'get_post_meta' )->andReturn( 42 );
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

		$html = mulino_render_documents( $folder, self::TAXONOMY );

		$this->assertTrue( strpos( $html, 'Referat 2' ) < strpos( $html, 'Referat 10' ) );
	}

	public function test_orderby_date_is_left_to_the_database() {
		$this->mock_everything_public();
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		$folder        = $this->make_term( 5, '2023', '2023' );
		$captured_args = null;

		WP_Mock::userFunction( 'get_posts' )->andReturnUsing(
			function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return array();
			}
		);
		WP_Mock::userFunction( 'get_terms' )->andReturn( array( $this->make_term( 6, 'Q1', 'q1' ) ) );

		mulino_render_documents(
			$folder,
			self::TAXONOMY,
			array(
				'orderby' => 'date',
				'order'   => 'desc',
			)
		);

		$this->assertSame( 'date', $captured_args['orderby'] );
		$this->assertSame( 'DESC', $captured_args['order'] );
	}

	// ---------------------------------------------------------------
	// Shortcode attributes (1.1)
	// ---------------------------------------------------------------

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

	public function test_atts_default_to_the_1_0_behaviour() {
		$this->mock_shortcode_atts();

		$args = mulino_parse_shortcode_atts( '' );

		$this->assertSame( '', $args['folder'] );
		$this->assertSame( 'name', $args['orderby'] );
		$this->assertSame( 'asc', $args['order'] );
		$this->assertSame( 'asc', $args['folder_order'] );
		$this->assertFalse( $args['hide_empty'] );
	}

	public function test_atts_reject_unknown_values() {
		$this->mock_shortcode_atts();

		$args = mulino_parse_shortcode_atts(
			array(
				'orderby'      => 'random',
				'order'        => 'sideways',
				'folder_order' => 'DESC',
				'hide_empty'   => 'Yes',
			)
		);

		$this->assertSame( 'name', $args['orderby'] );
		$this->assertSame( 'asc', $args['order'] );
		$this->assertSame( 'desc', $args['folder_order'] );
		$this->assertTrue( $args['hide_empty'] );
	}

	public function test_document_order_is_accepted_like_folder_order() {
		$this->mock_shortcode_atts();

		$this->assertSame( 'desc', mulino_parse_shortcode_atts( array( 'document_order' => 'desc' ) )['order'] );
		$this->assertSame( 'desc', mulino_parse_shortcode_atts( array( 'order' => 'desc' ) )['order'] );
		// If both are given, the more specific name wins.
		$this->assertSame(
			'asc',
			mulino_parse_shortcode_atts(
				array(
					'document_order' => 'asc',
					'order'          => 'desc',
				)
			)['order']
		);
	}

	public function test_hide_empty_is_passed_on_to_get_terms() {
		$captured_args = null;

		WP_Mock::userFunction( 'get_terms' )->andReturnUsing(
			function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return array();
			}
		);

		mulino_render_subfolders( false, self::TAXONOMY, array( 'hide_empty' => true ) );

		$this->assertTrue( $captured_args['hide_empty'] );
		$this->assertTrue( $captured_args['hierarchical'], 'Folders with non-empty subfolders must stay visible.' );
	}

	public function test_folder_inside_the_root_folder_is_allowed() {
		$root  = $this->make_term( 5, 'Referater', 'referater' );
		$child = $this->make_term( 20, '2023', '2023' );

		WP_Mock::userFunction( 'get_ancestors' )->with( 20, self::TAXONOMY, 'taxonomy' )->andReturn( array( 10, 5 ) );

		$this->assertTrue( mulino_term_is_within( $child, $root, self::TAXONOMY ) );
	}

	/**
	 * With folder="referater", a visitor must not be able to browse
	 * into another branch by editing ?mulino_folder= in the address.
	 */
	public function test_folder_outside_the_root_folder_is_refused() {
		$root  = $this->make_term( 5, 'Referater', 'referater' );
		$other = $this->make_term( 30, 'Regnskaber', 'regnskaber' );

		WP_Mock::userFunction( 'get_ancestors' )->with( 30, self::TAXONOMY, 'taxonomy' )->andReturn( array() );

		$this->assertFalse( mulino_term_is_within( $other, $root, self::TAXONOMY ) );
	}

	public function test_breadcrumb_starts_below_the_root_folder() {
		$this->mock_escaping_functions();

		$root = $this->make_term( 5, 'Referater', 'referater' );
		$year = $this->make_term( 10, '2020s', '2020s' );
		$q1   = $this->make_term( 20, '2023', '2023' );

		WP_Mock::userFunction( 'remove_query_arg' )->andReturn( 'https://example.test/docs' );
		WP_Mock::userFunction( 'get_ancestors' )->with( 20, self::TAXONOMY, 'taxonomy' )->andReturn( array( 10, 5, 1 ) );
		WP_Mock::userFunction( 'get_term' )->with( 10, self::TAXONOMY )->andReturn( $year );
		WP_Mock::userFunction( 'add_query_arg' )->andReturnUsing(
			function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);

		$html = mulino_render_breadcrumb( $q1, self::TAXONOMY, $root );

		$this->assertStringNotContainsString( 'Referater', $html, 'The root folder is "Home", not a separate crumb.' );
		$this->assertStringContainsString( '2020s', $html );
		$this->assertStringContainsString( 'mulino-current', $html );
	}
}
