<?php
/**
 * Import from Simple File List (SFL), which was closed on WordPress.org
 * in July 2026 and left its users without updates.
 *
 * SFL keeps its files as plain files in its own folder (by default
 * wp-content/uploads/simple-file-list/) and the list itself in the
 * option "eeSFL_FileList_1" (_2, _3 ... for extra lists in SFL Pro):
 * an array with one entry per file or folder, holding FilePath
 * (relative to the list folder, with subfolders in Pro), FileNiceName,
 * FileDescription and FileDateAdded.
 *
 * The import copies each file into the Media Library, creates a
 * matching Mulino folder for each subfolder, and leaves SFL's own files
 * and settings untouched. Each document remembers where it came from,
 * so running the import again only picks up what's new.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// How many SFL list numbers to look for (SFL Pro numbers its lists 1, 2, 3 ...).
define( 'MULINO_SFL_MAX_LISTS', 20 );

/**
 * Turn SFL's stored list into a clean, sorted list of items to import.
 * Folders come before the files in them.
 *
 * @param mixed $raw The value of an eeSFL_FileList_N option.
 * @return array[] Each: path, is_folder, title, description, date ('' if unknown).
 */
function mulino_sfl_normalize_items( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$items = array();
	foreach ( $raw as $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['FilePath'] ) || ! is_string( $entry['FilePath'] ) ) {
			continue;
		}

		$path = trim( str_replace( '\\', '/', $entry['FilePath'] ), '/' );
		// Never follow a path out of the list folder.
		if ( '' === $path || preg_match( '#(^|/)\.\.?(/|$)#', $path ) ) {
			continue;
		}

		$is_folder = ( isset( $entry['FileExt'] ) && 'folder' === $entry['FileExt'] ) || '/' === substr( $entry['FilePath'], -1 );
		$date      = isset( $entry['FileDateAdded'] ) ? (string) $entry['FileDateAdded'] : '';

		$items[ $path ] = array(
			'path'        => $path,
			'is_folder'   => $is_folder,
			'title'       => mulino_sfl_title( $path, isset( $entry['FileNiceName'] ) ? (string) $entry['FileNiceName'] : '' ),
			'description' => isset( $entry['FileDescription'] ) ? trim( (string) $entry['FileDescription'] ) : '',
			'date'        => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ? $date : '',
		);
	}

	uksort( $items, 'strnatcasecmp' );
	return array_values( $items );
}

/**
 * The title a document gets: SFL's "nice name" if it has one (that's
 * what SFL showed visitors), otherwise the file name -- in both cases
 * without the file extension, like a normal WordPress upload.
 *
 * @param string $path      FilePath, e.g. "Minutes/2024-03-minutes.pdf".
 * @param string $nice_name FileNiceName, may be empty.
 * @return string
 */
function mulino_sfl_title( $path, $nice_name ) {
	$name      = '' !== trim( $nice_name ) ? trim( $nice_name ) : basename( $path );
	$extension = pathinfo( $path, PATHINFO_EXTENSION );
	if ( '' !== $extension && strtolower( substr( $name, -strlen( $extension ) - 1 ) ) === '.' . strtolower( $extension ) ) {
		$name = substr( $name, 0, -strlen( $extension ) - 1 );
	}
	return '' === $name ? basename( $path ) : $name;
}

/**
 * The SFL lists on this site that have anything in them.
 *
 * @return array List number => items (as from mulino_sfl_normalize_items()).
 */
function mulino_sfl_lists() {
	$lists = array();
	for ( $list_id = 1; $list_id <= MULINO_SFL_MAX_LISTS; $list_id++ ) {
		$items = mulino_sfl_normalize_items( get_option( 'eeSFL_FileList_' . $list_id ) );
		if ( $items ) {
			$lists[ $list_id ] = $items;
		}
	}
	return $lists;
}

/**
 * Absolute path of an SFL list's folder, worked out the way SFL itself
 * does: its FileListDir setting is relative to the folder that holds
 * wp-content/uploads (normally the WordPress folder).
 *
 * @return string With a trailing slash, or '' if the folder doesn't exist.
 */
function mulino_sfl_list_dir( $list_id ) {
	$settings = get_option( 'eeSFL_Settings_' . (int) $list_id );
	$relative = ( is_array( $settings ) && ! empty( $settings['FileListDir'] ) ) ? (string) $settings['FileListDir'] : 'wp-content/uploads/simple-file-list/';

	$uploads = wp_upload_dir( null, false );
	$basedir = str_replace( '\\', '/', $uploads['basedir'] );
	$root    = preg_match( '#^(.+)/wp-content/uploads#', $basedir, $matches ) ? $matches[1] . '/' : ABSPATH;

	$dir = realpath( $root . ltrim( $relative, '/' ) );
	return ( $dir && is_dir( $dir ) ) ? trailingslashit( str_replace( '\\', '/', $dir ) ) : '';
}

/**
 * The marker stored on each imported document, so a second run of the
 * import can tell which files are already in.
 */
function mulino_sfl_source_key( $list_id, $path ) {
	return 'sfl:' . (int) $list_id . ':' . $path;
}

function mulino_sfl_is_imported( $list_id, $path ) {
	$found = get_posts(
		array(
			'post_type'      => 'mulino_document',
			'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// One exact-match lookup per file, only while importing.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_key'       => '_mulino_import_source',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value'     => mulino_sfl_source_key( $list_id, $path ),
		)
	);
	return ! empty( $found );
}

/**
 * Number of documents that have been imported from SFL so far.
 */
function mulino_sfl_imported_count() {
	$found = get_posts(
		array(
			'post_type'      => 'mulino_document',
			'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'       => '_mulino_import_source',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_compare
			'meta_compare'   => 'LIKE',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value'     => 'sfl:',
		)
	);
	return count( $found );
}

/**
 * Import one SFL item below the folder $target_id.
 *
 * @return string|WP_Error 'imported', 'folder' or 'skipped'.
 */
function mulino_sfl_import_item( $list_id, $item, $list_dir, $target_id ) {
	$dirs = explode( '/', $item['path'] );
	if ( ! $item['is_folder'] ) {
		array_pop( $dirs ); // the file name
	}
	$folder_id = mulino_ensure_folder_path( $dirs, $target_id );
	if ( is_wp_error( $folder_id ) ) {
		return $folder_id;
	}
	if ( $item['is_folder'] ) {
		return 'folder';
	}

	if ( mulino_sfl_is_imported( $list_id, $item['path'] ) ) {
		return 'skipped';
	}

	$source = realpath( $list_dir . $item['path'] );
	if ( ! $source || 0 !== strpos( str_replace( '\\', '/', $source ), $list_dir ) || ! is_file( $source ) || ! is_readable( $source ) ) {
		return new WP_Error( 'mulino_sfl_missing', __( 'The file was not found in the Simple File List folder.', 'mulino-file-show' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// media_handle_sideload() moves the file it's given, so give it a
	// copy: SFL's own files stay where they are.
	$tmp = wp_tempnam( basename( $source ) );
	if ( ! $tmp || ! copy( $source, $tmp ) ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'mulino_sfl_copy', __( 'The file could not be copied.', 'mulino-file-show' ) );
	}

	$post_dates = array();
	if ( $item['date'] ) {
		$post_dates = array(
			'post_date'     => $item['date'],
			'post_date_gmt' => get_gmt_from_date( $item['date'] ),
		);
	}

	$attachment_id = media_handle_sideload(
		array(
			'name'     => basename( $source ),
			'tmp_name' => $tmp,
		),
		0,
		$item['title'],
		$post_dates
	);
	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return $attachment_id;
	}

	$post_id = wp_insert_post(
		array_merge(
			array(
				'post_type'   => 'mulino_document',
				'post_title'  => $item['title'],
				'post_status' => 'publish',
			),
			$post_dates
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, '_mulino_file_id', $attachment_id );
	update_post_meta( $post_id, '_mulino_import_source', mulino_sfl_source_key( $list_id, $item['path'] ) );
	if ( '' !== $item['description'] ) {
		// Not shown anywhere yet; kept so a later version can show
		// descriptions without anyone having to import again.
		update_post_meta( $post_id, '_mulino_description', sanitize_textarea_field( $item['description'] ) );
	}
	if ( $folder_id ) {
		wp_set_object_terms( $post_id, array( (int) $folder_id ), 'mulino_folder' );
	}

	/**
	 * Fires after a document has been imported from another plugin.
	 *
	 * @param int    $post_id       The new mulino_document post ID.
	 * @param int    $attachment_id The new attachment ID.
	 * @param int    $folder_id     The mulino_folder term ID it was filed into, or 0.
	 * @param string $source        Where it came from, e.g. "sfl:1:Minutes/2024.pdf".
	 */
	do_action( 'mulino_after_import', $post_id, $attachment_id, (int) $folder_id, mulino_sfl_source_key( $list_id, $item['path'] ) );

	return 'imported';
}

/**
 * AJAX: import the next few items of one SFL list. The import page
 * keeps calling this until it answers done: true, so a big library
 * never runs into the web host's time limit in a single request.
 */
function mulino_ajax_sfl_import() {
	check_ajax_referer( 'mulino_import_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
	$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$target  = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';

	$items = $list_id ? mulino_sfl_normalize_items( get_option( 'eeSFL_FileList_' . $list_id ) ) : array();
	if ( ! $items ) {
		wp_send_json_error( array( 'message' => __( 'Simple File List has no files in this list.', 'mulino-file-show' ) ) );
	}
	$list_dir = mulino_sfl_list_dir( $list_id );
	if ( ! $list_dir ) {
		wp_send_json_error( array( 'message' => __( 'The Simple File List folder was not found.', 'mulino-file-show' ) ) );
	}

	$target_id = 0;
	if ( '' !== $target ) {
		$target_id = mulino_ensure_folder_path( array( $target ), 0 );
		if ( is_wp_error( $target_id ) ) {
			wp_send_json_error( array( 'message' => $target_id->get_error_message() ) );
		}
	}

	$counts = array(
		'imported' => 0,
		'skipped'  => 0,
		'folders'  => 0,
	);
	$errors = array();
	$start  = microtime( true );
	$total  = count( $items );

	// A handful of files per request, and stop early if they're big.
	for ( $done = 0; $offset < $total && $done < 10 && microtime( true ) - $start < 15; $done++, $offset++ ) {
		$result = mulino_sfl_import_item( $list_id, $items[ $offset ], $list_dir, $target_id );
		if ( is_wp_error( $result ) ) {
			$errors[] = $items[ $offset ]['path'] . ': ' . $result->get_error_message();
		} elseif ( 'folder' === $result ) {
			++$counts['folders'];
		} else {
			++$counts[ $result ];
		}
	}

	wp_send_json_success(
		array(
			'offset' => $offset,
			'total'  => $total,
			'done'   => $offset >= $total,
			'counts' => $counts,
			'errors' => $errors,
		)
	);
}
add_action( 'wp_ajax_mulino_sfl_import', 'mulino_ajax_sfl_import' );

/**
 * The File Show > Import screen.
 */
function mulino_add_import_page() {
	$hook = add_submenu_page(
		'mulino-manager',
		__( 'Import from Simple File List', 'mulino-file-show' ),
		__( 'Import', 'mulino-file-show' ),
		'upload_files',
		'mulino-import',
		'mulino_render_import_page'
	);
	if ( $hook ) {
		add_action( 'admin_print_scripts-' . $hook, 'mulino_enqueue_import_assets' );
	}
}
add_action( 'admin_menu', 'mulino_add_import_page' );

function mulino_enqueue_import_assets() {
	wp_enqueue_script(
		'mulino-import',
		MULINO_URL . 'assets/js/import.js',
		array(),
		MULINO_VERSION,
		true
	);
	wp_localize_script(
		'mulino-import',
		'mulinoImport',
		array(
			'nonce' => wp_create_nonce( 'mulino_import_nonce' ),
			'i18n'  => array(
				/* translators: 1: number of items handled so far, 2: total number of items. */
				'progress' => __( 'Importing... %1$d of %2$d', 'mulino-file-show' ),
				/* translators: 1: number of files imported, 2: number of files that were already imported, 3: number of problems. */
				'finished' => __( 'Done. Imported: %1$d. Already imported earlier: %2$d. Problems: %3$d.', 'mulino-file-show' ),
				'failed'   => __( 'The import stopped because of an error. You can safely start it again; files that are already imported are skipped.', 'mulino-file-show' ),
			),
		)
	);
}

function mulino_render_import_page() {
	if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'mulino-file-show' ) );
	}

	$lists = mulino_sfl_lists();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import from Simple File List', 'mulino-file-show' ); ?></h1>
		<p><?php esc_html_e( 'Copies the files from Simple File List into the Media Library and files them as Mulino documents. Subfolders become folders, and file names, descriptions and dates are kept. Simple File List\'s own files and settings are not changed or deleted.', 'mulino-file-show' ); ?></p>

		<?php if ( ! $lists ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No Simple File List files were found on this site.', 'mulino-file-show' ); ?></p></div>
		<?php else : ?>
			<p><?php esc_html_e( 'You can run the import more than once: files that are already imported are skipped.', 'mulino-file-show' ); ?></p>
			<form id="mulino-import-form">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="mulino-import-list"><?php esc_html_e( 'List', 'mulino-file-show' ); ?></label></th>
						<td>
							<select id="mulino-import-list">
								<?php foreach ( $lists as $list_id => $items ) : ?>
									<?php
									$files = count(
										array_filter(
											$items,
											function ( $item ) {
												return ! $item['is_folder'];
											}
										)
									);
									?>
									<option value="<?php echo esc_attr( $list_id ); ?>">
										<?php
										/* translators: 1: list number, 2: number of files in it. */
										echo esc_html( sprintf( _n( 'List %1$d (%2$d file)', 'List %1$d (%2$d files)', $files, 'mulino-file-show' ), $list_id, $files ) );
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="mulino-import-target"><?php esc_html_e( 'Put the files in the folder', 'mulino-file-show' ); ?></label></th>
						<td>
							<input type="text" id="mulino-import-target" class="regular-text" value="<?php esc_attr_e( 'Simple File List', 'mulino-file-show' ); ?>" />
							<p class="description"><?php esc_html_e( 'A top-level folder, created if it doesn\'t exist. Leave it empty to put the files and folders at the top level of the library.', 'mulino-file-show' ); ?></p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary" id="mulino-import-start"><?php esc_html_e( 'Start import', 'mulino-file-show' ); ?></button></p>
			</form>
			<div id="mulino-import-status" aria-live="polite" hidden>
				<p class="mulino-import-text"></p>
				<progress class="mulino-import-progress" max="1" value="0" style="width: 100%; max-width: 40em;"></progress>
				<ul class="mulino-import-errors" style="color: #d63638;"></ul>
			</div>
			<div id="mulino-import-done" hidden>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mulino-manager' ) ); ?>"><?php esc_html_e( 'Go to the library', 'mulino-file-show' ); ?></a>
				</p>
				<p><?php esc_html_e( 'When you have checked the imported documents, deactivate and delete Simple File List: it no longer receives security updates. Remember to replace its [eeSFL] shortcode with [mulino_documents] on your pages.', 'mulino-file-show' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * A one-line pointer to the import on the main screen, as long as the
 * site has SFL files and none of them have been imported yet.
 */
function mulino_sfl_import_notice() {
	if ( ! current_user_can( 'upload_files' ) || ! mulino_sfl_lists() || mulino_sfl_imported_count() > 0 ) {
		return;
	}
	printf(
		'<div class="notice notice-info inline"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'This site has files in Simple File List.', 'mulino-file-show' ),
		esc_url( admin_url( 'admin.php?page=mulino-import' ) ),
		esc_html__( 'Import them into Mulino file show', 'mulino-file-show' )
	);
}
