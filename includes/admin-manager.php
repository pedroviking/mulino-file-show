<?php
/**
 * The "Mulino file show" admin screen: folder tree, drag-and-drop upload
 * zone, file cards, and every admin-ajax.php handler it talks to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 6. ADMIN "DOCUMENT MANAGER" -- drag & drop upload + drag-to-move
 * -----------------------------------------------------------------
 * A dedicated admin screen: folder tree on the left, drop zone +
 * file cards on the right. Dropping OS files onto the drop zone
 * uploads and files them straight into the open folder. Dragging an
 * existing file card onto a folder in the tree re-files it there.
 * Everything goes through admin-ajax.php with nonce + capability
 * checks, never trusting client-supplied folder/doc ownership.
 */
function mulino_add_manager_page() {
	add_menu_page(
		__( 'Mulino file show', 'mulino-file-show' ),
		__( 'File Show', 'mulino-file-show' ),
		MULINO_CAPABILITY,
		'mulino-manager',
		'mulino_render_manager_page',
		'dashicons-media-document',
		100
	);

	// WordPress' own list of documents, with the trash. Users who only
	// have manage_mulino_documents (e.g. a board member) can open it only
	// because it is in the menu.
	add_submenu_page(
		'mulino-manager',
		__( 'All documents', 'mulino-file-show' ),
		__( 'All documents', 'mulino-file-show' ),
		MULINO_CAPABILITY,
		'edit.php?post_type=mulino_document'
	);
}
add_action( 'admin_menu', 'mulino_add_manager_page' );

/**
 * Enqueue the Document Manager screen's stylesheet and drag-and-drop
 * script, but only on that one admin page -- add_menu_page() gives a
 * top-level page the hook suffix "toplevel_page_{menu_slug}".
 */
function mulino_enqueue_manager_assets( $hook ) {
	if ( 'toplevel_page_mulino-manager' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'mulino-manager-assets',
		MULINO_URL . 'assets/css/admin-manager.css',
		array( 'dashicons' ),
		MULINO_VERSION
	);

	wp_enqueue_script(
		'mulino-manager-assets',
		MULINO_URL . 'assets/js/admin-manager.js',
		array(),
		MULINO_VERSION,
		true
	);

	wp_localize_script(
		'mulino-manager-assets',
		'mulinoManager',
		array(
			'nonce'         => wp_create_nonce( 'mulino_manager_nonce' ),
			'rootUrl'       => remove_query_arg( 'folder' ),
			'maxUploadSize' => wp_max_upload_size(),
			'i18n'          => array(
				'uploadFailed'         => __( 'Upload failed.', 'mulino-file-show' ),
				/* translators: 1: file name, 2: maximum upload size, e.g. "8 MB". */
				'fileTooLarge'         => __( '%1$s is larger than the maximum upload size of %2$s.', 'mulino-file-show' ),
				'maxUploadSizeText'    => size_format( wp_max_upload_size() ),
				/* translators: 1: number of the file being uploaded, 2: total number of files. */
				'uploadingProgress'    => __( 'Uploading file %1$d of %2$d...', 'mulino-file-show' ),
				/* translators: 1: number of files uploaded, 2: number of files dropped. */
				'uploadSummary'        => __( 'Uploaded: %1$d of %2$d.', 'mulino-file-show' ),
				/* translators: %d: HTTP status code, e.g. 413. */
				'serverRejected'       => __( 'The server rejected the upload (HTTP %d). The file may be larger than your web host allows.', 'mulino-file-show' ),
				/* translators: %d: HTTP status code, e.g. 503. */
				'serverError'          => __( 'The server stopped while handling the upload (HTTP %d). Large photos can take longer than the web host allows. Check the Media Library: the file may have been saved even so.', 'mulino-file-show' ),
				'couldNotRename'       => __( 'Could not rename.', 'mulino-file-show' ),
				'deleteDocConfirm'     => __( 'Move this document to the trash?', 'mulino-file-show' ),
				'couldNotDelete'       => __( 'Could not delete.', 'mulino-file-show' ),
				'noDocuments'          => __( 'No documents here yet. Drag files onto the drop zone above.', 'mulino-file-show' ),
				'noTopLevelDocuments'  => __( 'No documents at the top level. Open a folder on the left to see its documents, or drag files onto the drop zone above.', 'mulino-file-show' ),
				'couldNotMoveDoc'      => __( 'Could not move document.', 'mulino-file-show' ),
				'couldNotMoveFolder'   => __( 'Could not move folder.', 'mulino-file-show' ),
				'newFolderPrompt'      => __( 'New folder name:', 'mulino-file-show' ),
				'couldNotCreateFolder' => __( 'Could not create folder.', 'mulino-file-show' ),
				'couldNotRenameFolder' => __( 'Could not rename folder.', 'mulino-file-show' ),
				'deleteFolderConfirm'  => __( 'Delete this folder? It must be empty (no subfolders or documents).', 'mulino-file-show' ),
				'couldNotDeleteFolder' => __( 'Could not delete folder.', 'mulino-file-show' ),
				'creatingFolders'      => __( 'Creating folders...', 'mulino-file-show' ),
				/* translators: %s: folder path, e.g. "Minutes/2024". */
				'couldNotCreatePath'   => __( 'Could not create the folder %s.', 'mulino-file-show' ),
				/* translators: %d: number of selected documents. */
				'selectedCount'        => __( '%d selected', 'mulino-file-show' ),
				'bulkDeleteConfirmOne' => __( 'Move the selected document to the trash?', 'mulino-file-show' ),
				/* translators: %d: number of selected documents (always more than one). */
				'bulkDeleteConfirm'    => __( 'Move %d documents to the trash?', 'mulino-file-show' ),
				'filesDeletedToo'      => __( 'Their files will be deleted from the Media Library when the trash is emptied.', 'mulino-file-show' ),
				'couldNotSave'         => __( 'Could not save the changes.', 'mulino-file-show' ),
				'replacingFile'        => __( 'Uploading the new file...', 'mulino-file-show' ),
				'couldNotReplace'      => __( 'Could not replace the file.', 'mulino-file-show' ),
			),
			'deleteFiles'   => (bool) get_option( 'mulino_delete_files_with_documents', false ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mulino_enqueue_manager_assets' );

function mulino_render_folder_tree( $parent_id, $selected_id ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'mulino_folder',
			'parent'     => $parent_id,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	$terms = mulino_natural_sort(
		$terms,
		function ( $term ) {
			return $term->name;
		}
	);

	$base_url = remove_query_arg( 'folder' );
	$out      = '<ul class="mulino-subtree">';
	foreach ( $terms as $term ) {
		$url    = add_query_arg( 'folder', $term->slug, $base_url );
		$is_sel = ( (int) $term->term_id === (int) $selected_id ) ? ' is-selected' : '';
		$visibility = mulino_get_folder_visibility( $term->term_id );
		/* translators: %s: folder name. */
		$edit_label = sprintf( __( 'Edit folder %s', 'mulino-file-show' ), $term->name );
		/* translators: %s: folder name. */
		$delete_label = sprintf( __( 'Delete folder %s', 'mulino-file-show' ), $term->name );
		$lock         = '';
		if ( 'public' !== $visibility ) {
			$options = mulino_folder_visibility_options();
			$lock    = '<span class="mulino-tree-lock dashicons dashicons-lock" title="' . esc_attr( isset( $options[ $visibility ] ) ? $options[ $visibility ] : $visibility ) . '"></span>';
		}
		$out .= '<li class="mulino-tree-item' . esc_attr( $is_sel ) . '" data-term-id="' . esc_attr( $term->term_id ) . '" data-parent-id="' . esc_attr( $term->parent ) . '" data-visibility="' . esc_attr( $visibility ) . '">';
		$out .= '<span class="mulino-tree-row" draggable="true">';
		$out .= '<a href="' . esc_url( $url ) . '" class="mulino-tree-link" draggable="false" data-term-name="' . esc_attr( $term->name ) . '"' . ( $is_sel ? ' aria-current="page"' : '' ) . '>' . esc_html( $term->name ) . $lock . '</a>';
		$out .= '<button type="button" class="mulino-tree-rename dashicons dashicons-edit" data-term-id="' . esc_attr( $term->term_id ) . '" title="' . esc_attr( $edit_label ) . '" aria-label="' . esc_attr( $edit_label ) . '"></button>';
		$out .= '<button type="button" class="mulino-tree-delete" data-term-id="' . esc_attr( $term->term_id ) . '" title="' . esc_attr( $delete_label ) . '" aria-label="' . esc_attr( $delete_label ) . '">&times;</button>';
		$out .= '</span>';
		$out .= mulino_render_folder_tree( $term->term_id, $selected_id );
		$out .= '</li>';
	}
	$out .= '</ul>';

	return $out;
}

function mulino_render_manager_cards( $term ) {
	$args = array(
		'post_type'      => 'mulino_document',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( $term ) {
		// A tax_query is inherently scoped to one specific folder term
		// here (not an open-ended query), so this stays fast even on a
		// large document library.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$args['tax_query'] = array(
			array(
				'taxonomy'         => 'mulino_folder',
				'field'            => 'term_id',
				'terms'            => $term->term_id,
				'include_children' => false,
			),
		);
	} else {
		// Root view: documents that have no folder assigned yet.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'mulino_folder',
				'operator' => 'NOT EXISTS',
			),
		);
	}

	$docs = mulino_natural_sort(
		get_posts( $args ),
		function ( $doc ) {
			return get_the_title( $doc );
		}
	);

	if ( empty( $docs ) ) {
		$message = $term
			? __( 'No documents here yet. Drag files onto the drop zone above.', 'mulino-file-show' )
			: __( 'No documents at the top level. Open a folder on the left to see its documents, or drag files onto the drop zone above.', 'mulino-file-show' );
		return '<p class="mulino-empty">' . esc_html( $message ) . '</p>';
	}

	$out = '';
	foreach ( $docs as $doc ) {
		$out .= mulino_render_one_manager_card( $doc );
	}

	return $out;
}

function mulino_render_one_manager_card( $doc ) {
	$attachment_id = (int) get_post_meta( $doc->ID, '_mulino_file_id', true );
	$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$icon          = $url ? mulino_get_file_type( $url ) : array(
		'label' => '?',
		'color' => '#999999',
	);

	/**
	 * Extra HTML injected into each document card in the Document
	 * Manager, right after the delete button (e.g. an extra icon or
	 * link a premium add-on wants to show per document).
	 *
	 * @param string  $html Extra markup to append. Empty by default.
	 * @param WP_Post $doc  The mulino_document post this card is for.
	 */
	$extra_actions = apply_filters( 'mulino_manager_card_actions', '', $doc );

	$title       = get_the_title( $doc );
	$description = (string) get_post_meta( $doc->ID, '_mulino_description', true );
	$file_path   = $attachment_id ? get_attached_file( $attachment_id ) : '';

	/* translators: %s: document title. */
	$select_label = sprintf( __( 'Select %s', 'mulino-file-show' ), $title );
	/* translators: %s: document title. */
	$edit_label = sprintf( __( 'Edit %s', 'mulino-file-show' ), $title );
	/* translators: %s: document title. */
	$delete_label = sprintf( __( 'Delete %s', 'mulino-file-show' ), $title );

	return '<div class="mulino-card mulino-manager-card" draggable="true" data-doc-id="' . esc_attr( $doc->ID ) . '" data-doc-name="' . esc_attr( $title ) . '" data-doc-description="' . esc_attr( $description ) . '" data-file-name="' . esc_attr( $file_path ? wp_basename( $file_path ) : '' ) . '" data-file-url="' . esc_url( $url ) . '">'
		. '<input type="checkbox" class="mulino-select" value="' . esc_attr( $doc->ID ) . '" aria-label="' . esc_attr( $select_label ) . '" />'
		. mulino_file_icon_svg( $icon['label'], $icon['color'] )
		. '<span class="mulino-name">' . esc_html( $title ) . '</span>'
		. '<span class="mulino-card-description">' . esc_html( $description ) . '</span>'
		. '<button type="button" class="mulino-rename dashicons dashicons-edit" data-doc-id="' . esc_attr( $doc->ID ) . '" title="' . esc_attr( $edit_label ) . '" aria-label="' . esc_attr( $edit_label ) . '"></button>'
		. '<button type="button" class="mulino-delete" data-doc-id="' . esc_attr( $doc->ID ) . '" title="' . esc_attr( $delete_label ) . '" aria-label="' . esc_attr( $delete_label ) . '">&times;</button>'
		. wp_kses_post( $extra_actions )
		. '</div>';
}

/**
 * A <select> of every folder, indented by depth, with "Top level" first.
 * Used by the bulk "Move to" control.
 */
function mulino_folder_dropdown( $id ) {
	return wp_dropdown_categories(
		array(
			'taxonomy'          => 'mulino_folder',
			'hide_empty'        => false,
			'hierarchical'      => true,
			'orderby'           => 'name',
			'name'              => $id,
			'id'                => $id,
			'show_option_none'  => __( 'Top level (no folder)', 'mulino-file-show' ),
			'option_none_value' => '0',
			'echo'              => false,
		)
	);
}

function mulino_folder_dropdown_allowed_html() {
	return array(
		'select' => array(
			'name'  => true,
			'id'    => true,
			'class' => true,
		),
		'option' => array(
			'value'    => true,
			'selected' => true,
			'class'    => true,
		),
	);
}

function mulino_render_manager_page() {
	if ( ! mulino_current_user_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'mulino-file-show' ) );
	}

	// Which folder is currently open. Read-only display filtering, not
	// a state-changing action, so nonce verification doesn't apply.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$selected_slug = isset( $_GET['folder'] ) ? sanitize_title( wp_unslash( $_GET['folder'] ) ) : '';
	$selected_term = $selected_slug ? get_term_by( 'slug', $selected_slug, 'mulino_folder' ) : false;
	$selected_id   = $selected_term ? $selected_term->term_id : 0;
	$root_url      = remove_query_arg( 'folder' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Mulino file show', 'mulino-file-show' ); ?></h1>
		<p><?php esc_html_e( 'Drag files or whole folders onto the drop zone to upload them into the open folder. Drag a file card onto a folder on the left to move it, or tick several cards to move or delete them together.', 'mulino-file-show' ); ?></p>
		<p><?php esc_html_e( 'Show the library on a page with the "Document library" block or the [mulino_documents] shortcode.', 'mulino-file-show' ); ?></p>

		<?php
		/**
		 * Extra HTML printed just below the intro text on the Document
		 * Manager screen -- e.g. an "Upgrade to Pro" notice or extra
		 * global controls from a premium add-on.
		 *
		 * @param string $html Extra markup to print. Empty by default.
		 */
		echo wp_kses_post( apply_filters( 'mulino_manager_toolbar', '' ) );
		?>

		<?php mulino_sfl_import_notice(); ?>

		<div id="mulino-manager">
			<div class="mulino-tree-pane">
				<div class="mulino-tree-actions">
					<button type="button" id="mulino-new-folder" class="button"><?php esc_html_e( '+ New folder', 'mulino-file-show' ); ?></button>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mulino_export_zip' ), 'mulino_export_zip' ) ); ?>" id="mulino-export" class="button"><?php esc_html_e( 'Export as ZIP', 'mulino-file-show' ); ?></a>
				</div>
				<?php
				$mulino_counts = wp_count_posts( 'mulino_document' );
				if ( ! empty( $mulino_counts->trash ) ) :
					?>
					<p class="mulino-trash-link">
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=trash&post_type=mulino_document' ) ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of documents in the trash. */
									_n( 'Trash (%d document)', 'Trash (%d documents)', (int) $mulino_counts->trash, 'mulino-file-show' ),
									(int) $mulino_counts->trash
								)
							);
							?>
						</a>
					</p>
				<?php endif; ?>
				<ul class="mulino-tree" id="mulino-tree">
					<li class="mulino-tree-item<?php echo ( 0 === $selected_id ) ? ' is-selected' : ''; ?>" data-term-id="0">
						<a href="<?php echo esc_url( $root_url ); ?>" class="mulino-tree-link"<?php echo ( 0 === $selected_id ) ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Top level', 'mulino-file-show' ); ?></a>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
						echo mulino_render_folder_tree( 0, $selected_id );
						?>
					</li>
				</ul>
			</div>

			<div class="mulino-files-pane">
				<div id="mulino-dropzone" data-folder-id="<?php echo esc_attr( $selected_id ); ?>">
					<p><?php esc_html_e( 'Drag files or folders here to upload, or', 'mulino-file-show' ); ?></p>
					<p>
						<button type="button" id="mulino-choose-files" class="button"><?php esc_html_e( 'Choose files', 'mulino-file-show' ); ?></button>
						<input type="file" id="mulino-file-input" multiple hidden />
					</p>
					<p class="mulino-dropzone-limit">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: maximum upload size, e.g. "8 MB". */
								__( 'Maximum size per file: %s', 'mulino-file-show' ),
								size_format( wp_max_upload_size() )
							)
						);
						?>
					</p>
				</div>
				<div id="mulino-upload-status" class="mulino-upload-status" aria-live="polite" hidden>
					<p class="mulino-upload-text"></p>
					<progress class="mulino-upload-progress" max="1" value="0"></progress>
					<ul class="mulino-upload-errors"></ul>
				</div>
				<div id="mulino-bulk-bar" class="mulino-bulk-bar">
					<label class="mulino-select-all-label"><input type="checkbox" id="mulino-select-all" /> <?php esc_html_e( 'Select all', 'mulino-file-show' ); ?></label>
					<span id="mulino-selected-count" class="mulino-selected-count" aria-live="polite"></span>
					<span class="mulino-bulk-actions">
						<label for="mulino-bulk-folder"><?php esc_html_e( 'Move to:', 'mulino-file-show' ); ?></label>
						<?php echo wp_kses( mulino_folder_dropdown( 'mulino-bulk-folder' ), mulino_folder_dropdown_allowed_html() ); ?>
						<button type="button" id="mulino-bulk-move" class="button"><?php esc_html_e( 'Move', 'mulino-file-show' ); ?></button>
						<button type="button" id="mulino-bulk-delete" class="button"><?php esc_html_e( 'Move to trash', 'mulino-file-show' ); ?></button>
					</span>
				</div>
				<div class="mulino-grid" id="mulino-file-grid">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
					echo mulino_render_manager_cards( $selected_term );
					?>
				</div>
			</div>
		</div>
		<?php mulino_render_manager_dialogs(); ?>
	</div>
	<?php
}

function mulino_ajax_upload() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}
	if ( empty( $_FILES['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file received.', 'mulino-file-show' ) ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	mulino_skip_image_sizes();
	$attachment_id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
	}

	$title   = get_the_title( $attachment_id );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'mulino_document',
			'post_title'  => $title ? $title : __( 'Untitled document', 'mulino-file-show' ),
			'post_status' => 'publish',
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
	}

	update_post_meta( $post_id, '_mulino_file_id', $attachment_id );

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id && get_term( $folder_id, 'mulino_folder' ) && ! is_wp_error( get_term( $folder_id, 'mulino_folder' ) ) ) {
		wp_set_object_terms( $post_id, array( $folder_id ), 'mulino_folder' );
	}

	/**
	 * Fires after a document has been uploaded and filed, right before
	 * the AJAX response is sent. $folder_id is 0 if it was uploaded to
	 * the root ("no folder") view.
	 *
	 * @param int $post_id       The new mulino_document post ID.
	 * @param int $attachment_id The underlying WordPress attachment ID.
	 * @param int $folder_id     The mulino_folder term ID it was filed into, or 0.
	 */
	do_action( 'mulino_after_upload', $post_id, $attachment_id, $folder_id );

	wp_send_json_success( array( 'html' => mulino_render_one_manager_card( get_post( $post_id ) ) ) );
}
add_action( 'wp_ajax_mulino_upload', 'mulino_ajax_upload' );

function mulino_ajax_move() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'mulino_document' !== get_post_type( $doc_id ) || ! current_user_can( 'edit_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;

	if ( $folder_id ) {
		$term = get_term( $folder_id, 'mulino_folder' );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
		}
		wp_set_object_terms( $doc_id, array( $folder_id ), 'mulino_folder' );
	} else {
		wp_set_object_terms( $doc_id, array(), 'mulino_folder' );
	}

	/**
	 * Fires after a document has been re-filed into a (possibly
	 * different) folder. $folder_id is 0 if it was moved to the root
	 * ("no folder") view.
	 *
	 * @param int $doc_id    The mulino_document post ID that moved.
	 * @param int $folder_id The mulino_folder term ID it now belongs to, or 0.
	 */
	do_action( 'mulino_after_move', $doc_id, $folder_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_mulino_move', 'mulino_ajax_move' );

function mulino_ajax_rename_doc() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'mulino_document' !== get_post_type( $doc_id ) || ! current_user_can( 'edit_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( ! $name ) {
		wp_send_json_error( array( 'message' => __( 'Name cannot be empty.', 'mulino-file-show' ) ) );
	}

	$result = wp_update_post(
		array(
			'ID'         => $doc_id,
			'post_title' => $name,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a document has been renamed.
	 *
	 * @param int    $doc_id The mulino_document post ID that was renamed.
	 * @param string $name   Its new title.
	 */
	do_action( 'mulino_after_rename_doc', $doc_id, $name );

	wp_send_json_success( array( 'name' => $name ) );
}
add_action( 'wp_ajax_mulino_rename_doc', 'mulino_ajax_rename_doc' );

function mulino_ajax_move_folder() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$term_id       = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$new_parent_id = isset( $_POST['new_parent_id'] ) ? absint( $_POST['new_parent_id'] ) : 0;

	$term = $term_id ? get_term( $term_id, 'mulino_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
	}

	if ( $term_id === $new_parent_id ) {
		wp_send_json_error( array( 'message' => __( 'A folder cannot be moved into itself.', 'mulino-file-show' ) ) );
	}

	if ( $new_parent_id ) {
		$new_parent = get_term( $new_parent_id, 'mulino_folder' );
		if ( ! $new_parent || is_wp_error( $new_parent ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid target folder.', 'mulino-file-show' ) ) );
		}

		// Prevent moving a folder into one of its own descendants -- that
		// would create a cycle the taxonomy tree can't represent.
		$descendant_ids = get_terms(
			array(
				'taxonomy'   => 'mulino_folder',
				'child_of'   => $term_id,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $descendant_ids ) && in_array( $new_parent_id, $descendant_ids, true ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot move a folder into one of its own subfolders.', 'mulino-file-show' ) ) );
		}
	}

	if ( mulino_folder_name_exists( $term->name, $new_parent_id, $term_id ) ) {
		wp_send_json_error( array( 'message' => __( 'The target folder already contains a folder with this name.', 'mulino-file-show' ) ) );
	}

	$result = wp_update_term( $term_id, 'mulino_folder', array( 'parent' => $new_parent_id ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a folder has been re-parented.
	 *
	 * @param int $term_id       The mulino_folder term ID that moved.
	 * @param int $new_parent_id Its new parent term ID, or 0 for top-level.
	 */
	do_action( 'mulino_after_folder_moved', $term_id, $new_parent_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_mulino_move_folder', 'mulino_ajax_move_folder' );

function mulino_ajax_create_folder() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;

	if ( ! $name ) {
		wp_send_json_error( array( 'message' => __( 'Folder name is required.', 'mulino-file-show' ) ) );
	}

	$result = wp_insert_term( $name, 'mulino_folder', array( 'parent' => $parent_id ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a new folder has been created.
	 *
	 * @param int $term_id   The new mulino_folder term ID.
	 * @param int $parent_id Its parent term ID, or 0 for top-level.
	 */
	do_action( 'mulino_after_folder_created', $result['term_id'], $parent_id );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_mulino_create_folder', 'mulino_ajax_create_folder' );

function mulino_ajax_rename_folder() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$term    = $term_id ? get_term( $term_id, 'mulino_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( ! $name ) {
		wp_send_json_error( array( 'message' => __( 'Folder name cannot be empty.', 'mulino-file-show' ) ) );
	}

	if ( mulino_folder_name_exists( $name, $term->parent, $term_id ) ) {
		wp_send_json_error( array( 'message' => __( 'A folder with this name already exists here.', 'mulino-file-show' ) ) );
	}

	$result = wp_update_term( $term_id, 'mulino_folder', array( 'name' => $name ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	/**
	 * Fires after a folder has been renamed.
	 *
	 * @param int    $term_id The mulino_folder term ID that was renamed.
	 * @param string $name    Its new name.
	 */
	do_action( 'mulino_after_folder_renamed', $term_id, $name );

	wp_send_json_success( array( 'name' => $name ) );
}
add_action( 'wp_ajax_mulino_rename_folder', 'mulino_ajax_rename_folder' );

function mulino_ajax_delete_doc() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'mulino_document' !== get_post_type( $doc_id ) || ! current_user_can( 'delete_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	wp_trash_post( $doc_id );

	/**
	 * Fires after a document post has been moved to the trash.
	 *
	 * @param int $doc_id The mulino_document post ID that was trashed.
	 */
	do_action( 'mulino_after_delete_doc', $doc_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_mulino_delete_doc', 'mulino_ajax_delete_doc' );

function mulino_ajax_delete_folder() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$term    = $term_id ? get_term( $term_id, 'mulino_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
	}

	// Refuse to delete a folder that still has subfolders.
	$children = get_terms(
		array(
			'taxonomy'   => 'mulino_folder',
			'parent'     => $term_id,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
		wp_send_json_error( array( 'message' => __( 'This folder still has subfolders inside it. Delete or move those first.', 'mulino-file-show' ) ) );
	}

	// Refuse to delete a folder that still has documents directly in it.
	$docs = get_posts(
		array(
			'post_type'      => 'mulino_document',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// Scoped to one specific folder term, and capped at 1
			// result -- this is a cheap existence check, not a broad query.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'tax_query'      => array(
				array(
					'taxonomy'         => 'mulino_folder',
					'field'            => 'term_id',
					'terms'            => $term_id,
					'include_children' => false,
				),
			),
		)
	);
	if ( ! empty( $docs ) ) {
		wp_send_json_error( array( 'message' => __( 'This folder still has documents in it. Move or delete those first.', 'mulino-file-show' ) ) );
	}

	$deleted = wp_delete_term( $term_id, 'mulino_folder' );
	if ( is_wp_error( $deleted ) || ! $deleted ) {
		wp_send_json_error( array( 'message' => __( 'Could not delete folder.', 'mulino-file-show' ) ) );
	}

	/**
	 * Fires after a (now-empty) folder has been deleted.
	 *
	 * @param int $term_id The mulino_folder term ID that was deleted.
	 */
	do_action( 'mulino_after_folder_deleted', $term_id );

	wp_send_json_success();
}
add_action( 'wp_ajax_mulino_delete_folder', 'mulino_ajax_delete_folder' );

/**
 * Turn the doc_ids[] of a bulk request into the documents the current
 * user may actually $capability ('edit_post' or 'delete_post').
 * The calling AJAX handler has already checked the nonce.
 */
function mulino_bulk_doc_ids( $capability ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the caller; every value is cast with absint() below.
	$raw = isset( $_POST['doc_ids'] ) ? (array) wp_unslash( $_POST['doc_ids'] ) : array();
	$ids = array();
	foreach ( array_unique( array_map( 'absint', $raw ) ) as $doc_id ) {
		if ( $doc_id && 'mulino_document' === get_post_type( $doc_id ) && current_user_can( $capability, $doc_id ) ) {
			$ids[] = $doc_id;
		}
	}
	return $ids;
}

function mulino_ajax_bulk_move() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	if ( $folder_id ) {
		$term = get_term( $folder_id, 'mulino_folder' );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
		}
	}

	$moved = array();
	foreach ( mulino_bulk_doc_ids( 'edit_post' ) as $doc_id ) {
		wp_set_object_terms( $doc_id, $folder_id ? array( $folder_id ) : array(), 'mulino_folder' );

		/** This action is documented in includes/admin-manager.php */
		do_action( 'mulino_after_move', $doc_id, $folder_id );

		$moved[] = $doc_id;
	}

	wp_send_json_success( array( 'doc_ids' => $moved ) );
}
add_action( 'wp_ajax_mulino_bulk_move', 'mulino_ajax_bulk_move' );

function mulino_ajax_bulk_delete() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$deleted = array();
	foreach ( mulino_bulk_doc_ids( 'delete_post' ) as $doc_id ) {
		if ( wp_trash_post( $doc_id ) ) {
			/** This action is documented in includes/admin-manager.php */
			do_action( 'mulino_after_delete_doc', $doc_id );

			$deleted[] = $doc_id;
		}
	}

	wp_send_json_success( array( 'doc_ids' => $deleted ) );
}
add_action( 'wp_ajax_mulino_bulk_delete', 'mulino_ajax_bulk_delete' );

/**
 * Create (or reuse) a path of folders such as "Minutes/2024" below
 * parent_id, for a whole folder dragged in from the computer. Returns
 * the term ID of the innermost folder, so the files from that folder
 * can be uploaded straight into it.
 */
function mulino_ajax_ensure_folder_path() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;
	if ( $parent_id ) {
		$parent = get_term( $parent_id, 'mulino_folder' );
		if ( ! $parent || is_wp_error( $parent ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
		}
	}

	// Each part is run through sanitize_text_field() below.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path  = isset( $_POST['path'] ) ? (string) wp_unslash( $_POST['path'] ) : '';
	$names = array_values( array_filter( array_map( 'sanitize_text_field', explode( '/', $path ) ), 'strlen' ) );
	if ( empty( $names ) ) {
		wp_send_json_error( array( 'message' => __( 'Folder name is required.', 'mulino-file-show' ) ) );
	}

	$term_id = mulino_ensure_folder_path( $names, $parent_id );
	if ( is_wp_error( $term_id ) ) {
		wp_send_json_error( array( 'message' => $term_id->get_error_message() ) );
	}

	wp_send_json_success( array( 'term_id' => $term_id ) );
}
add_action( 'wp_ajax_mulino_ensure_folder_path', 'mulino_ajax_ensure_folder_path' );

/**
 * The "Edit document" and "Edit folder" dialogs. They are filled in by
 * admin-manager.js with the clicked document's or folder's details, and
 * give keyboard and touch users a way to move a folder without dragging.
 */
function mulino_render_manager_dialogs() {
	?>
	<dialog id="mulino-doc-dialog" class="mulino-dialog" aria-labelledby="mulino-doc-dialog-title">
		<form method="dialog">
			<h2 id="mulino-doc-dialog-title"><?php esc_html_e( 'Edit document', 'mulino-file-show' ); ?></h2>
			<p>
				<label for="mulino-doc-name"><?php esc_html_e( 'Name', 'mulino-file-show' ); ?></label>
				<input type="text" id="mulino-doc-name" class="widefat" required />
			</p>
			<p>
				<label for="mulino-doc-description"><?php esc_html_e( 'Description (optional)', 'mulino-file-show' ); ?></label>
				<textarea id="mulino-doc-description" class="widefat" rows="3"></textarea>
				<span class="description"><?php esc_html_e( 'Shown under the name when the library on the site shows details.', 'mulino-file-show' ); ?></span>
			</p>
			<fieldset class="mulino-replace">
				<legend><?php esc_html_e( 'File', 'mulino-file-show' ); ?></legend>
				<p><a id="mulino-doc-file" href="#" target="_blank" rel="noopener"></a></p>
				<p>
					<label for="mulino-replace-input"><?php esc_html_e( 'Replace with a new version:', 'mulino-file-show' ); ?></label><br />
					<input type="file" id="mulino-replace-input" />
				</p>
				<p class="description"><?php esc_html_e( 'The document keeps its name, folder and link on the site. The old file stays in the Media Library.', 'mulino-file-show' ); ?></p>
			</fieldset>
			<p class="mulino-dialog-error" role="alert"></p>
			<p class="mulino-dialog-buttons">
				<button type="submit" value="save" class="button button-primary"><?php esc_html_e( 'Save', 'mulino-file-show' ); ?></button>
				<button type="submit" value="cancel" class="button" formnovalidate><?php esc_html_e( 'Cancel', 'mulino-file-show' ); ?></button>
			</p>
		</form>
	</dialog>

	<dialog id="mulino-folder-dialog" class="mulino-dialog" aria-labelledby="mulino-folder-dialog-title">
		<form method="dialog">
			<h2 id="mulino-folder-dialog-title"><?php esc_html_e( 'Edit folder', 'mulino-file-show' ); ?></h2>
			<p>
				<label for="mulino-folder-name"><?php esc_html_e( 'Name', 'mulino-file-show' ); ?></label>
				<input type="text" id="mulino-folder-name" class="widefat" required />
			</p>
			<p>
				<label for="mulino-folder-parent"><?php esc_html_e( 'Place in', 'mulino-file-show' ); ?></label><br />
				<?php echo wp_kses( mulino_folder_dropdown( 'mulino-folder-parent' ), mulino_folder_dropdown_allowed_html() ); ?>
			</p>
			<fieldset>
				<legend><?php esc_html_e( 'Who can see this folder on the site', 'mulino-file-show' ); ?></legend>
				<?php foreach ( mulino_folder_visibility_options() as $mulino_value => $mulino_label ) : ?>
					<label class="mulino-radio"><input type="radio" name="mulino-folder-visibility" value="<?php echo esc_attr( $mulino_value ); ?>" /> <?php echo esc_html( $mulino_label ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Also applies to the folders inside it. Hidden folders and documents are left out of the library and its search, and their links ask visitors to log in. Anyone who already has the direct address of a file can still open it.', 'mulino-file-show' ); ?></p>
			</fieldset>
			<p class="mulino-dialog-error" role="alert"></p>
			<p class="mulino-dialog-buttons">
				<button type="submit" value="save" class="button button-primary"><?php esc_html_e( 'Save', 'mulino-file-show' ); ?></button>
				<button type="submit" value="cancel" class="button" formnovalidate><?php esc_html_e( 'Cancel', 'mulino-file-show' ); ?></button>
			</p>
		</form>
	</dialog>
	<?php
}

/**
 * Save a document's description (shown on the site with the details).
 */
function mulino_ajax_set_description() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'mulino_document' !== get_post_type( $doc_id ) || ! current_user_can( 'edit_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	if ( '' === $description ) {
		delete_post_meta( $doc_id, '_mulino_description' );
	} else {
		update_post_meta( $doc_id, '_mulino_description', $description );
	}

	/**
	 * Fires after a document's description has been changed.
	 *
	 * @param int    $doc_id      The mulino_document post ID.
	 * @param string $description The new description ('' if removed).
	 */
	do_action( 'mulino_after_description_changed', $doc_id, $description );

	wp_send_json_success( array( 'description' => $description ) );
}
add_action( 'wp_ajax_mulino_set_description', 'mulino_ajax_set_description' );

/**
 * Replace a document's file with a new upload. The document keeps its
 * ID, name, folder and ?mulino_document= link; the old file stays in
 * the Media Library (so its direct address keeps working) and is
 * remembered in _mulino_previous_file_id (one meta row per version).
 */
function mulino_ajax_replace_file() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	$doc_id = isset( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
	if ( ! $doc_id || 'mulino_document' !== get_post_type( $doc_id ) || ! current_user_can( 'edit_post', $doc_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}
	if ( empty( $_FILES['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file received.', 'mulino-file-show' ) ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	mulino_skip_image_sizes();
	$attachment_id = media_handle_upload( 'file', 0 );
	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
	}

	$old_id = (int) get_post_meta( $doc_id, '_mulino_file_id', true );
	if ( $old_id ) {
		// One row per older version, newest last.
		add_post_meta( $doc_id, '_mulino_previous_file_id', $old_id );
	}
	update_post_meta( $doc_id, '_mulino_file_id', $attachment_id );

	/**
	 * Fires after a document's file has been replaced with a new version.
	 *
	 * @param int $doc_id            The mulino_document post ID.
	 * @param int $attachment_id     The new file's attachment ID.
	 * @param int $old_attachment_id The previous file's attachment ID (0 if none).
	 */
	do_action( 'mulino_after_replace_file', $doc_id, $attachment_id, $old_id );

	wp_send_json_success( array( 'html' => mulino_render_one_manager_card( get_post( $doc_id ) ) ) );
}
add_action( 'wp_ajax_mulino_replace_file', 'mulino_ajax_replace_file' );

/**
 * Set who can see a folder on the site.
 */
function mulino_ajax_set_folder_visibility() {
	check_ajax_referer( 'mulino_manager_nonce', 'nonce' );

	if ( ! mulino_current_user_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mulino-file-show' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$term    = $term_id ? get_term( $term_id, 'mulino_folder' ) : null;
	if ( ! $term_id || ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid folder.', 'mulino-file-show' ) ) );
	}

	$visibility = isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : '';
	if ( ! array_key_exists( $visibility, mulino_folder_visibility_options() ) ) {
		wp_send_json_error( array( 'message' => __( 'Unknown choice.', 'mulino-file-show' ) ) );
	}

	if ( 'public' === $visibility ) {
		delete_term_meta( $term_id, 'mulino_visibility' );
	} else {
		update_term_meta( $term_id, 'mulino_visibility', $visibility );
	}

	/**
	 * Fires after the "Who can see this folder" setting has been changed.
	 *
	 * @param int    $term_id    The mulino_folder term ID.
	 * @param string $visibility The new setting, e.g. "public" or "members".
	 */
	do_action( 'mulino_after_folder_visibility_changed', $term_id, $visibility );

	wp_send_json_success( array( 'visibility' => $visibility ) );
}
add_action( 'wp_ajax_mulino_set_folder_visibility', 'mulino_ajax_set_folder_visibility' );
