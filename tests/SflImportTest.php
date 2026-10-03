<?php

require_once __DIR__ . '/TestCase.php';

class SflImportTest extends MULINO_TestCase {

	public function test_title_prefers_the_nice_name_without_extension() {
		$this->assertSame( 'Minutes March 2024', mulino_sfl_title( 'minutes-march-2024.pdf', 'Minutes March 2024.pdf' ) );
	}

	public function test_title_falls_back_to_the_file_name() {
		$this->assertSame( 'minutes-march-2024', mulino_sfl_title( 'Minutes/minutes-march-2024.PDF', '' ) );
	}

	public function test_title_keeps_a_nice_name_that_has_no_extension() {
		$this->assertSame( 'Annual report', mulino_sfl_title( 'report.pdf', 'Annual report' ) );
	}

	public function test_normalize_returns_nothing_for_a_missing_option() {
		$this->assertSame( array(), mulino_sfl_normalize_items( false ) );
		$this->assertSame( array(), mulino_sfl_normalize_items( 'garbage' ) );
	}

	public function test_normalize_sorts_folders_before_their_files_and_reads_the_fields() {
		$items = mulino_sfl_normalize_items(
			array(
				array(
					'FilePath'        => 'Minutes/2024/March.pdf',
					'FileExt'         => 'pdf',
					'FileNiceName'    => '',
					'FileDescription' => ' Board meeting ',
					'FileDateAdded'   => '2024-03-15 20:00:00',
				),
				array(
					'FilePath' => 'Minutes/',
					'FileExt'  => 'folder',
				),
				array(
					'FilePath'      => 'Bylaws.pdf',
					'FileExt'       => 'pdf',
					'FileDateAdded' => 'yesterday',
				),
			)
		);

		$this->assertSame( array( 'Bylaws.pdf', 'Minutes', 'Minutes/2024/March.pdf' ), array_column( $items, 'path' ) );
		$this->assertTrue( $items[1]['is_folder'] );
		$this->assertFalse( $items[2]['is_folder'] );
		$this->assertSame( 'March', $items[2]['title'] );
		$this->assertSame( 'Board meeting', $items[2]['description'] );
		$this->assertSame( '2024-03-15 20:00:00', $items[2]['date'] );
		$this->assertSame( '', $items[0]['date'], 'A date SFL did not write in its usual format is ignored.' );
	}

	public function test_normalize_skips_paths_that_leave_the_list_folder() {
		$items = mulino_sfl_normalize_items(
			array(
				array( 'FilePath' => '../../../wp-config.php' ),
				array( 'FilePath' => 'Minutes/../../secret.pdf' ),
				array( 'FilePath' => './hidden.pdf' ),
				array( 'FilePath' => '' ),
				array( 'FileNiceName' => 'no path at all' ),
				'not an array',
				array( 'FilePath' => 'ok.pdf' ),
			)
		);

		$this->assertSame( array( 'ok.pdf' ), array_column( $items, 'path' ) );
	}

	public function test_normalize_accepts_windows_style_paths() {
		$items = mulino_sfl_normalize_items( array( array( 'FilePath' => 'Minutes\\2024.pdf' ) ) );

		$this->assertSame( 'Minutes/2024.pdf', $items[0]['path'] );
	}
}
