<?php
/**
 * Post type + hierarchical taxonomy registration, and the
 * "Select File" meta box on the (now hidden) document edit screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 1. CUSTOM POST TYPE: one post = one document
 * -----------------------------------------------------------------
 * We use a real post type (not a raw attachment) so we get the
 * standard WP admin list table, Quick Edit, and Bulk Edit for free.
 */
function mulino_register_post_type() {
	register_post_type(
		'mulino_document',
		array(
			'label'              => __( 'Documents', 'mulino-file-show' ),
			'labels'             => array(
				'name'          => __( 'Documents', 'mulino-file-show' ),
				'singular_name' => __( 'Document', 'mulino-file-show' ),
				'add_new_item'  => __( 'Add New Document', 'mulino-file-show' ),
				'edit_item'     => __( 'Edit Document', 'mulino-file-show' ),
			),
			'public'             => false,      // no single document pages, we render via shortcode
			'show_ui'            => true,
			'show_in_menu'       => false,     // we add our own single top-level "Documents" menu instead
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			'capabilities'       => mulino_post_type_capabilities(), // all mapped to manage_mulino_documents
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'show_in_rest'       => false,
		)
	);
}
add_action( 'init', 'mulino_register_post_type' );

/**
 * -----------------------------------------------------------------
 * 2. HIERARCHICAL TAXONOMY: the "folders" (Decade > Year)
 * -----------------------------------------------------------------
 * Hierarchical, so WP still manages parent/child relationships,
 * counts, get_ancestors(), etc. for us -- we just don't show its
 * own admin screen, since folder management now happens entirely
 * inside the Document Manager screen.
 */
function mulino_register_taxonomy() {
	register_taxonomy(
		'mulino_folder',
		'mulino_document',
		array(
			'label'             => __( 'Folders', 'mulino-file-show' ),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_menu'      => false,     // hide the separate "Folders" term-admin screen
			'show_in_quick_edit'=> true,
			'query_var'         => false,
			'rewrite'           => false,
			'show_in_rest'      => false,
			'capabilities'      => mulino_taxonomy_capabilities(),
		)
	);
}
add_action( 'init', 'mulino_register_taxonomy' );

/**
 * -----------------------------------------------------------------
 * 3. FILE-ATTACH META BOX (admin side)
 * -----------------------------------------------------------------
 * Adds a "Select File" button on the document edit screen that opens
 * the normal WP Media uploader and stores the chosen attachment ID.
 */
function mulino_add_file_meta_box() {
	add_meta_box(
		'mulino_file_box',
		__( 'Document File', 'mulino-file-show' ),
		'mulino_render_file_meta_box',
		'mulino_document',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'mulino_add_file_meta_box' );

function mulino_render_file_meta_box( $post ) {
	wp_nonce_field( 'mulino_save_file', 'mulino_file_nonce' );
	$attachment_id = (int) get_post_meta( $post->ID, '_mulino_file_id', true );
	$file_url      = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$file_name     = $attachment_id ? basename( get_attached_file( $attachment_id ) ) : '';
	?>
	<p>
		<button type="button" class="button" id="mulino_select_file"><?php esc_html_e( 'Select File', 'mulino-file-show' ); ?></button>
		<span id="mulino_file_name"><?php echo esc_html( $file_name ); ?></span>
	</p>
	<input type="hidden" name="mulino_file_id" id="mulino_file_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
	<?php if ( $file_url ) : ?>
		<p><a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View current file', 'mulino-file-show' ); ?></a></p>
	<?php endif; ?>
	<?php
}

function mulino_save_file_meta( $post_id ) {
	if ( ! isset( $_POST['mulino_file_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mulino_file_nonce'] ) ), 'mulino_save_file' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['mulino_file_id'] ) ) {
		update_post_meta( $post_id, '_mulino_file_id', absint( $_POST['mulino_file_id'] ) );
	}
}
add_action( 'save_post_mulino_document', 'mulino_save_file_meta' );

function mulino_admin_enqueue( $hook ) {
	global $post_type;
	if ( 'mulino_document' === $post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();

		wp_enqueue_script(
			'mulino-media-picker',
			MULINO_URL . 'assets/js/media-picker.js',
			array( 'jquery', 'media-editor' ),
			MULINO_VERSION,
			true
		);
		wp_localize_script(
			'mulino-media-picker',
			'mulinoMediaPicker',
			array(
				'title'      => __( 'Select or upload a document', 'mulino-file-show' ),
				'buttonText' => __( 'Use this file', 'mulino-file-show' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'mulino_admin_enqueue' );
