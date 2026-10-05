<?php
/**
 * The plugin's settings, shown on Settings > Media:
 *  - whether deleting the plugin should also delete its documents and
 *    folders (see uninstall.php), and
 *  - whether a document's file should be deleted from the Media Library
 *    when the document is deleted for good (emptied from the trash).
 * Both are off by default, so nothing disappears by surprise.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mulino_register_settings() {
	register_setting(
		'media',
		'mulino_delete_data_on_uninstall',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	register_setting(
		'media',
		'mulino_delete_files_with_documents',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	add_settings_section(
		'mulino_settings',
		__( 'Mulino file show', 'mulino-file-show' ),
		'__return_false',
		'media'
	);

	add_settings_field(
		'mulino_delete_data_on_uninstall',
		__( 'When the plugin is deleted', 'mulino-file-show' ),
		'mulino_render_delete_data_field',
		'media',
		'mulino_settings',
		array( 'label_for' => 'mulino_delete_data_on_uninstall' )
	);

	add_settings_field(
		'mulino_delete_files_with_documents',
		__( 'When a document is deleted', 'mulino-file-show' ),
		'mulino_render_delete_files_field',
		'media',
		'mulino_settings',
		array( 'label_for' => 'mulino_delete_files_with_documents' )
	);
}
add_action( 'admin_init', 'mulino_register_settings' );

function mulino_render_delete_data_field() {
	?>
	<label for="mulino_delete_data_on_uninstall">
		<input type="checkbox" id="mulino_delete_data_on_uninstall" name="mulino_delete_data_on_uninstall" value="1" <?php checked( (bool) get_option( 'mulino_delete_data_on_uninstall', false ) ); ?> />
		<?php esc_html_e( 'Also delete all documents and folders', 'mulino-file-show' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'The uploaded files themselves stay in the Media Library. Deactivating the plugin never deletes anything.', 'mulino-file-show' ); ?></p>
	<?php
}

function mulino_render_delete_files_field() {
	?>
	<label for="mulino_delete_files_with_documents">
		<input type="checkbox" id="mulino_delete_files_with_documents" name="mulino_delete_files_with_documents" value="1" <?php checked( (bool) get_option( 'mulino_delete_files_with_documents', false ) ); ?> />
		<?php esc_html_e( 'Also delete its file from the Media Library', 'mulino-file-show' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'Deleted documents go to the trash first. The file, and any older versions of it, is only deleted when the document is deleted permanently from the trash, and only if no other document uses it. Links to the file from posts and pages will stop working.', 'mulino-file-show' ); ?></p>
	<?php
}

/**
 * With the setting on, delete a document's files together with the
 * document when it is deleted for good. This also covers WordPress
 * emptying the trash by itself after 30 days.
 */
function mulino_delete_document_files( $post_id ) {
	if ( 'mulino_document' !== get_post_type( $post_id ) || ! get_option( 'mulino_delete_files_with_documents', false ) ) {
		return;
	}

	$ids   = array_map( 'intval', (array) get_post_meta( $post_id, '_mulino_previous_file_id', false ) );
	$ids[] = (int) get_post_meta( $post_id, '_mulino_file_id', true );

	foreach ( array_unique( array_filter( $ids ) ) as $attachment_id ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) || mulino_attachment_used_elsewhere( $attachment_id, $post_id ) ) {
			continue;
		}
		wp_delete_attachment( $attachment_id, true );
	}
}
add_action( 'before_delete_post', 'mulino_delete_document_files' );

/**
 * Whether another document uses this file (now or as an older version).
 */
function mulino_attachment_used_elsewhere( $attachment_id, $except_post_id ) {
	$others = get_posts(
		array(
			'post_type'      => 'mulino_document',
			// "any" leaves out the trash, and a document there may be restored.
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => 2, // the document being deleted may be one of them
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- runs only when a document is deleted for good.
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => '_mulino_file_id',
					'value' => (int) $attachment_id,
				),
				array(
					'key'   => '_mulino_previous_file_id',
					'value' => (int) $attachment_id,
				),
			),
		)
	);
	return (bool) array_diff( array_map( 'intval', $others ), array( (int) $except_post_id ) );
}
