<?php

require_once __DIR__ . '/TestCase.php';

class HelpersTest extends MULINO_TestCase {

	protected function mock_wp_parse_url() {
		// wp_parse_url() is WordPress's own wrapper around PHP's native
		// parse_url() (it just smooths over some old-PHP edge cases we
		// don't need to care about here), so a real parse_url() call is
		// a perfectly accurate stand-in for it in tests.
		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing(
				function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				}
			);
	}

	public function test_pdf_extension_gets_red_pdf_badge() {
		$this->mock_wp_parse_url();

		$result = mulino_get_file_type( 'https://example.test/uploads/2026/09/minutes.pdf' );

		$this->assertSame( 'PDF', $result['label'] );
		$this->assertSame( '#e2574c', $result['color'] );
	}

	public function test_extension_matching_is_case_insensitive() {
		$this->mock_wp_parse_url();

		$result = mulino_get_file_type( 'https://example.test/uploads/Budget.XLSX' );

		$this->assertSame( 'XLS', $result['label'] );
	}

	public function test_docx_and_doc_share_the_same_badge() {
		$this->mock_wp_parse_url();

		$doc  = mulino_get_file_type( 'https://example.test/uploads/report.doc' );
		$docx = mulino_get_file_type( 'https://example.test/uploads/report.docx' );

		$this->assertSame( $doc, $docx );
	}

	public function test_unknown_extension_falls_back_to_generic_grey_badge() {
		$this->mock_wp_parse_url();

		$result = mulino_get_file_type( 'https://example.test/uploads/archive.rar' );

		$this->assertSame( 'RAR', $result['label'] );
		$this->assertSame( '#607d8b', $result['color'] );
	}

	public function test_url_with_no_extension_returns_fil_placeholder() {
		$this->mock_wp_parse_url();

		$result = mulino_get_file_type( 'https://example.test/uploads/README' );

		$this->assertSame( 'FIL', $result['label'] );
	}

	public function test_query_string_on_the_url_is_ignored() {
		$this->mock_wp_parse_url();

		// The extension check should look at the URL path only, so a
		// cache-busting query string shouldn't confuse it.
		$result = mulino_get_file_type( 'https://example.test/uploads/minutes.pdf?ver=abc123' );

		$this->assertSame( 'PDF', $result['label'] );
	}

	public function test_file_icon_svg_embeds_the_label_and_color() {
		$this->mock_escaping_functions();

		$svg = mulino_file_icon_svg( 'PDF', '#e2574c' );

		$this->assertStringStartsWith( '<svg', trim( $svg ) );
		$this->assertStringContainsString( 'PDF', $svg );
		$this->assertStringContainsString( '#e2574c', $svg );
	}

	protected function mock_sibling_folders( array $siblings ) {
		WP_Mock::userFunction( 'get_terms' )->andReturn( $siblings );
	}

	public function test_folder_name_exists_finds_a_sibling_with_the_same_name() {
		$this->mock_sibling_folders( array( $this->make_term( 5, '2024', '2024' ) ) );

		$this->assertTrue( mulino_folder_name_exists( '2024', 3, 9 ) );
	}

	public function test_folder_name_exists_ignores_case_and_entities() {
		$this->mock_sibling_folders( array( $this->make_term( 5, 'Referater &amp; Regnskab', 'referater-regnskab' ) ) );

		$this->assertTrue( mulino_folder_name_exists( 'referater & regnskab', 3, 9 ) );
	}

	public function test_folder_name_exists_ignores_the_folder_itself() {
		// Renaming "2024" to "2024 " (or just changing its case) must not
		// be refused because of the folder's own current name.
		$this->mock_sibling_folders( array( $this->make_term( 9, '2024', '2024' ) ) );

		$this->assertFalse( mulino_folder_name_exists( '2024', 3, 9 ) );
	}

	public function test_folder_name_exists_is_false_for_a_new_name() {
		$this->mock_sibling_folders( array( $this->make_term( 5, '2023', '2023' ) ) );

		$this->assertFalse( mulino_folder_name_exists( '2024', 3, 9 ) );
	}

	public function test_folder_name_exists_only_asks_for_direct_children_of_the_parent() {
		WP_Mock::userFunction( 'get_terms' )
			->once()
			->with(
				array(
					'taxonomy'   => 'mulino_folder',
					'parent'     => 3,
					'hide_empty' => false,
				)
			)
			->andReturn( array() );

		$this->assertFalse( mulino_folder_name_exists( '2024', 3, 9 ) );
	}

	public function test_ensure_folder_path_reuses_existing_folders_and_creates_the_rest() {
		// "Minutes" exists at the top level (ID 5), "2024" doesn't exist in it yet.
		WP_Mock::userFunction( 'get_terms' )->andReturnUsing(
			function ( $args ) {
				return 0 === $args['parent'] ? array( $this->make_term( 5, 'Minutes', 'minutes' ) ) : array();
			}
		);
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'wp_insert_term' )
			->once()
			->with( '2024', 'mulino_folder', array( 'parent' => 5 ) )
			->andReturn( array( 'term_id' => 9 ) );
		WP_Mock::expectAction( 'mulino_after_folder_created', 9, 5 );

		$this->assertSame( 9, mulino_ensure_folder_path( array( 'minutes', '2024' ), 0 ) );
	}

	public function test_ensure_folder_path_with_no_names_returns_the_parent() {
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$this->assertSame( 7, mulino_ensure_folder_path( array( '', '' ), 7 ) );
	}

	public function test_zip_safe_name_replaces_characters_windows_forbids() {
		$this->assertSame( 'Budget 2024-25 - draft', mulino_zip_safe_name( 'Budget 2024/25 : draft', 'x' ) );
		$this->assertSame( 'Q&A', mulino_zip_safe_name( 'Q&amp;A', 'x' ) );
	}

	public function test_zip_safe_name_drops_trailing_dots_and_falls_back_when_empty() {
		$this->assertSame( 'Notes', mulino_zip_safe_name( 'Notes...', 'x' ) );
		$this->assertSame( 'folder', mulino_zip_safe_name( '..', 'folder' ) );
	}

	public function test_unique_name_numbers_duplicates_case_insensitively() {
		$used = null;

		$this->assertSame( 'Minutes.pdf', mulino_unique_name( 'Minutes', $used, 'pdf' ) );
		$this->assertSame( 'minutes (2).pdf', mulino_unique_name( 'minutes', $used, 'pdf' ) );
		$this->assertSame( 'Minutes (3).pdf', mulino_unique_name( 'Minutes', $used, 'pdf' ) );
		$this->assertSame( 'Minutes', mulino_unique_name( 'Minutes', $used ) );
	}
}
