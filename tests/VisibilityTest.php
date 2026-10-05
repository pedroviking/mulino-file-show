<?php

require_once __DIR__ . '/TestCase.php';

class VisibilityTest extends MULINO_TestCase {

	public function test_a_folder_is_public_unless_set() {
		WP_Mock::userFunction( 'get_term_meta' )->with( 5, 'mulino_visibility', true )->andReturn( '' );

		$this->assertSame( 'public', mulino_get_folder_visibility( 5 ) );
	}

	public function test_logged_out_visitors_cannot_see_a_members_folder() {
		$folder = $this->make_term( 5, 'Bestyrelse', 'bestyrelse' );
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'get_term_meta' )->with( 5, 'mulino_visibility', true )->andReturn( 'members' );

		$this->assertFalse( mulino_user_can_view_folder( $folder, 0 ) );
		$this->assertTrue( mulino_user_can_view_folder( $folder, 7 ) );
	}

	/**
	 * Hiding a folder must hide the folders inside it too, or a visitor
	 * could open ?mulino_folder=<child> directly.
	 */
	public function test_a_members_folder_also_hides_its_subfolders() {
		$child = $this->make_term( 20, '2024', '2024' );
		WP_Mock::userFunction( 'get_ancestors' )->with( 20, 'mulino_folder', 'taxonomy' )->andReturn( array( 5 ) );
		WP_Mock::userFunction( 'get_term_meta' )->with( 20, 'mulino_visibility', true )->andReturn( '' );
		WP_Mock::userFunction( 'get_term_meta' )->with( 5, 'mulino_visibility', true )->andReturn( 'members' );

		$this->assertFalse( mulino_user_can_view_folder( $child, 0 ) );
	}

	public function test_an_add_on_can_decide_who_sees_a_folder() {
		$folder = $this->make_term( 5, 'Bestyrelse', 'bestyrelse' );
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'get_term_meta' )->andReturn( 'board' );
		WP_Mock::onFilter( 'mulino_user_can_view_folder' )->with( true, $folder, 7 )->reply( false );

		$this->assertFalse( mulino_user_can_view_folder( $folder, 7 ) );
	}

	public function test_a_document_in_a_members_folder_is_hidden_from_visitors() {
		$doc    = $this->make_post( 101 );
		$folder = $this->make_term( 5, 'Bestyrelse', 'bestyrelse' );
		WP_Mock::userFunction( 'get_post_status' )->andReturn( 'publish' );
		WP_Mock::userFunction( 'get_the_terms' )->andReturn( array( $folder ) );
		WP_Mock::userFunction( 'get_ancestors' )->andReturn( array() );
		WP_Mock::userFunction( 'get_term_meta' )->andReturn( 'members' );

		$this->assertFalse( mulino_user_can_view_document( $doc, 0 ) );
		$this->assertTrue( mulino_user_can_view_document( $doc, 7 ) );
	}

	public function test_a_document_in_the_trash_is_never_shown() {
		$doc = $this->make_post( 101 );
		WP_Mock::userFunction( 'get_post_status' )->andReturn( 'trash' );

		$this->assertFalse( mulino_user_can_view_document( $doc, 7 ) );
	}

	public function test_document_links_go_through_mulino_document() {
		WP_Mock::userFunction( 'home_url' )->andReturn( 'https://example.test/' );
		WP_Mock::userFunction( 'add_query_arg' )->with( 'mulino_document', 101, 'https://example.test/' )->andReturn( 'https://example.test/?mulino_document=101' );

		$this->assertSame( 'https://example.test/?mulino_document=101', mulino_document_url( $this->make_post( 101 ) ) );
	}
}
