<?php
/**
 * "Export as ZIP" on the admin screen: the whole library as one ZIP
 * file, with the folders as real folders, so nobody is locked in and a
 * library can be backed up or moved to another site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Work out what goes where in the export: a ZIP path for every folder
 * (including empty ones) and for every document's file.
 *
 * @return array {
 *     @type string[] $folders ZIP paths of all folders, parents first, e.g. "Minutes/2024/".
 *     @type array[]  $files   array( 'source' => absolute file path, 'name' => ZIP path ).
 *     @type string[] $missing Titles of documents whose file could not be found.
 * }
 */
function mulino_export_plan() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'mulino_folder',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	$children = array();
	foreach ( $terms as $term ) {
		$children[ (int) $term->parent ][] = $term;
	}

	// Give each folder its ZIP path, walking down from the top so that
	// two sibling folders with the same name get "Name" and "Name (2)".
	$folder_paths = array( 0 => '' );
	$plan         = array(
		'folders' => array(),
		'files'   => array(),
		'missing' => array(),
	);
	$queue        = array( 0 );
	$used         = array(); // names taken so far, per ZIP folder
	while ( $queue ) {
		$parent_id = array_shift( $queue );
		if ( empty( $children[ $parent_id ] ) ) {
			continue;
		}
		$siblings = mulino_natural_sort(
			$children[ $parent_id ],
			function ( $term ) {
				return $term->name;
			}
		);
		foreach ( $siblings as $term ) {
			$name = mulino_unique_name( mulino_zip_safe_name( $term->name, 'folder' ), $used[ 'folders:' . $parent_id ] );

			$folder_paths[ $term->term_id ] = $folder_paths[ $parent_id ] . $name . '/';
			$plan['folders'][]              = $folder_paths[ $term->term_id ];
			$queue[]                        = (int) $term->term_id;
		}
	}

	$docs = get_posts(
		array(
			'post_type'      => 'mulino_document',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	foreach ( $docs as $doc ) {
		$title         = get_the_title( $doc );
		$attachment_id = (int) get_post_meta( $doc->ID, '_mulino_file_id', true );
		$source        = $attachment_id ? get_attached_file( $attachment_id ) : '';
		if ( ! $source || ! is_readable( $source ) ) {
			$plan['missing'][] = $title;
			continue;
		}

		$folder_ids = wp_get_object_terms( $doc->ID, 'mulino_folder', array( 'fields' => 'ids' ) );
		$folder_id  = ( ! is_wp_error( $folder_ids ) && $folder_ids ) ? (int) $folder_ids[0] : 0;
		$dir        = isset( $folder_paths[ $folder_id ] ) ? $folder_paths[ $folder_id ] : '';

		// Name the file after the document's title, which is what people
		// see on the site, but keep the real file's extension.
		$extension = strtolower( pathinfo( $source, PATHINFO_EXTENSION ) );
		$base      = mulino_zip_safe_name( $title, pathinfo( $source, PATHINFO_FILENAME ) );
		if ( '' !== $extension && strtolower( substr( $base, -strlen( $extension ) - 1 ) ) === '.' . $extension ) {
			$base = substr( $base, 0, -strlen( $extension ) - 1 );
		}

		$plan['files'][] = array(
			'source' => $source,
			'name'   => $dir . mulino_unique_name( $base, $used[ 'files:' . $folder_id ], $extension ),
		);
	}

	return $plan;
}

/**
 * Write the export to $zip_path.
 *
 * @return array|WP_Error The plan that was written, or an error.
 */
function mulino_write_export_zip( $zip_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'mulino_no_zip', __( 'Your web host\'s PHP does not include the ZIP extension (ZipArchive), so the library cannot be exported as a ZIP file.', 'mulino-file-show' ) );
	}

	$plan = mulino_export_plan();
	$zip  = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'mulino_zip_open', __( 'Could not create the ZIP file.', 'mulino-file-show' ) );
	}

	foreach ( $plan['folders'] as $folder ) {
		$zip->addEmptyDir( rtrim( $folder, '/' ) );
	}
	foreach ( $plan['files'] as $file ) {
		$zip->addFile( $file['source'], $file['name'] );
		// Documents are mostly PDFs, Office files and images, which are
		// compressed already; storing them as-is keeps big exports fast.
		if ( method_exists( $zip, 'setCompressionName' ) ) {
			$zip->setCompressionName( $file['name'], ZipArchive::CM_STORE );
		}
	}
	if ( $plan['missing'] ) {
		$zip->addFromString(
			'missing-files.txt',
			__( 'These documents were not exported because their file could not be found on the server:', 'mulino-file-show' ) . "\n\n" . implode( "\n", $plan['missing'] ) . "\n"
		);
	}

	if ( ! $zip->close() ) {
		return new WP_Error( 'mulino_zip_close', __( 'Could not create the ZIP file.', 'mulino-file-show' ) );
	}

	return $plan;
}

function mulino_handle_export_zip() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'mulino-file-show' ) );
	}
	check_admin_referer( 'mulino_export_zip' );

	$zip_path = wp_tempnam( 'mulino-export.zip' );
	$result   = mulino_write_export_zip( $zip_path );
	if ( is_wp_error( $result ) ) {
		wp_delete_file( $zip_path );
		wp_die( esc_html( $result->get_error_message() ), '', array( 'back_link' => true ) );
	}

	$site     = sanitize_file_name( get_bloginfo( 'name' ) );
	$filename = ( $site ? $site . '-' : '' ) . 'documents-' . wp_date( 'Y-m-d' ) . '.zip';

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . filesize( $zip_path ) );

	// Stream the finished file to the browser; WP_Filesystem has no
	// streaming equivalent, and reading a large ZIP into memory first
	// would run out of memory on big libraries.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	readfile( $zip_path );
	wp_delete_file( $zip_path );
	exit;
}
add_action( 'admin_post_mulino_export_zip', 'mulino_handle_export_zip' );
