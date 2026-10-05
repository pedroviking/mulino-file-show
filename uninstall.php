<?php
/**
 * Runs when the plugin is deleted from the Plugins screen (not when
 * it's merely deactivated).
 *
 * Documents and folders are only removed if the site owner opted in
 * under Settings > Media. The files in the Media Library are never
 * touched -- other content may link to them.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( get_option( 'mulino_delete_data_on_uninstall', false ) ) {
	// The plugin's own code isn't loaded during uninstall, so the post
	// type and taxonomy have to be registered again for the normal
	// WordPress query and delete functions to recognise them.
	register_post_type( 'mulino_document', array( 'public' => false ) );
	register_taxonomy( 'mulino_folder', 'mulino_document', array( 'hierarchical' => true ) );

	$mulino_doc_ids = get_posts(
		array(
			'post_type'      => 'mulino_document',
			'post_status'    => array_keys( get_post_stati() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $mulino_doc_ids as $mulino_doc_id ) {
		wp_delete_post( $mulino_doc_id, true );
	}

	$mulino_folder_ids = get_terms(
		array(
			'taxonomy'   => 'mulino_folder',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $mulino_folder_ids ) ) {
		foreach ( $mulino_folder_ids as $mulino_folder_id ) {
			wp_delete_term( $mulino_folder_id, 'mulino_folder' );
		}
	}
}

delete_option( 'mulino_delete_data_on_uninstall' );
delete_option( 'mulino_delete_files_with_documents' );
delete_option( 'mulino_capability_version' );

// The manage_mulino_documents capability, from roles and from single users.
foreach ( wp_roles()->role_objects as $mulino_role ) {
	$mulino_role->remove_cap( 'manage_mulino_documents' );
}
foreach ( get_users( array( 'capability' => 'manage_mulino_documents', 'fields' => 'all' ) ) as $mulino_user ) {
	$mulino_user->remove_cap( 'manage_mulino_documents' );
}
