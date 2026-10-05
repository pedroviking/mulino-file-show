<?php
/**
 * Who may see what on the site, and the stable link to each document.
 *
 * A folder can be shown to everyone or only to logged-in users. A
 * folder that only logged-in users may see also hides everything below
 * it. This hides the folders and documents in the library and in its
 * search, and the document links refuse logged-out visitors, but the
 * files themselves stay in the Media Library at their usual address:
 * anyone who already has a file's direct address can still open it.
 *
 * Every document is linked as ?mulino_document=ID, which forwards to
 * its current file. The link therefore keeps working when the file is
 * replaced with a new version, and an add-on can serve the file itself
 * instead (e.g. from a protected folder).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The visibility choices for a folder, keyed by the value stored in its
 * "mulino_visibility" term meta. An add-on can add its own (for
 * example "Board members") and decide who may see them with the
 * mulino_user_can_view_folder filter.
 *
 * @return array<string,string>
 */
function mulino_folder_visibility_options() {
	/**
	 * Filters the choices in a folder's "Who can see this folder" setting.
	 *
	 * @param array<string,string> $options Stored value => label.
	 */
	return apply_filters(
		'mulino_folder_visibility_options',
		array(
			'public'  => __( 'Everyone', 'mulino-file-show' ),
			'members' => __( 'Only logged-in users', 'mulino-file-show' ),
		)
	);
}

/**
 * A folder's own visibility setting ("public" unless set).
 */
function mulino_get_folder_visibility( $term_id ) {
	$value = (string) get_term_meta( (int) $term_id, 'mulino_visibility', true );
	return '' === $value ? 'public' : $value;
}

/**
 * Whether the visitor may see this folder: the folder itself and every
 * folder above it must allow it.
 *
 * @param WP_Term $term    The folder.
 * @param int     $user_id The visitor (0 = logged out). Defaults to the current user.
 * @return bool
 */
function mulino_user_can_view_folder( $term, $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	$ids = array_merge( array( (int) $term->term_id ), array_map( 'intval', get_ancestors( $term->term_id, 'mulino_folder', 'taxonomy' ) ) );
	$can = true;
	foreach ( $ids as $id ) {
		$visibility = mulino_get_folder_visibility( $id );
		if ( 'public' !== $visibility && ! $user_id ) {
			$can = false;
			break;
		}
	}

	/**
	 * Filters whether a visitor may see a folder (and so its documents and
	 * subfolders) on the site.
	 *
	 * @param bool    $can     Whether they may see it.
	 * @param WP_Term $term    The folder.
	 * @param int     $user_id The visitor, or 0 if not logged in.
	 */
	return (bool) apply_filters( 'mulino_user_can_view_folder', $can, $term, (int) $user_id );
}

/**
 * Whether the visitor may see a document: it must be published, and
 * the visitor must be allowed to see its folder.
 *
 * @param WP_Post $doc     The mulino_document post.
 * @param int     $user_id The visitor (0 = logged out). Defaults to the current user.
 * @return bool
 */
function mulino_user_can_view_document( $doc, $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	$can = 'publish' === get_post_status( $doc );
	if ( $can ) {
		$terms = get_the_terms( $doc, 'mulino_folder' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! mulino_user_can_view_folder( $term, $user_id ) ) {
					$can = false;
					break;
				}
			}
		}
	}

	/**
	 * Filters whether a visitor may see and open a document on the site.
	 *
	 * @param bool    $can     Whether they may.
	 * @param WP_Post $doc     The mulino_document post.
	 * @param int     $user_id The visitor, or 0 if not logged in.
	 */
	return (bool) apply_filters( 'mulino_user_can_view_document', $can, $doc, (int) $user_id );
}

/**
 * The link visitors click to open a document.
 *
 * @param WP_Post $doc The mulino_document post.
 * @return string
 */
function mulino_document_url( $doc ) {
	$url = add_query_arg( 'mulino_document', (int) $doc->ID, home_url( '/' ) );

	/**
	 * Filters the link to a document in the [mulino_documents] browser.
	 *
	 * @param string  $url The link. By default home_url( '/?mulino_document=ID' ),
	 *                     which forwards to the document's current file.
	 * @param WP_Post $doc The mulino_document post.
	 */
	return (string) apply_filters( 'mulino_document_url', $url, $doc );
}

/**
 * Answer ?mulino_document=ID by forwarding to the document's file, or
 * refuse if the visitor may not see it.
 */
function mulino_serve_document() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a plain link that only reads; nothing is changed.
	if ( ! isset( $_GET['mulino_document'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$doc_id = absint( $_GET['mulino_document'] );
	$doc    = $doc_id ? get_post( $doc_id ) : null;
	if ( ! $doc || 'mulino_document' !== $doc->post_type ) {
		mulino_document_not_found();
	}

	if ( ! mulino_user_can_view_document( $doc ) ) {
		if ( ! is_user_logged_in() && 'publish' === get_post_status( $doc ) ) {
			// Ask a logged-out visitor to log in and come straight back.
			$here = add_query_arg( 'mulino_document', $doc_id, home_url( '/' ) );
			wp_safe_redirect( wp_login_url( $here ) );
			exit;
		}
		mulino_document_not_found();
	}

	$attachment_id = (int) get_post_meta( $doc->ID, '_mulino_file_id', true );

	/**
	 * Fires before a visitor is forwarded to a document's file. An add-on
	 * can send the file itself here (e.g. from a protected folder) and
	 * exit, instead of the default redirect to its Media Library address.
	 *
	 * @param WP_Post $doc           The mulino_document post.
	 * @param int     $attachment_id The document's current file.
	 */
	do_action( 'mulino_before_serve_document', $doc, $attachment_id );

	$file_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	if ( ! $file_url ) {
		mulino_document_not_found();
	}

	// Never let a page cache keep the redirect: the file may be replaced.
	nocache_headers();
	wp_safe_redirect( $file_url, 302, 'Mulino file show' );
	exit;
}
add_action( 'template_redirect', 'mulino_serve_document', 1 );

/**
 * The file may live on another host (a CDN or offloaded media), so
 * allow redirects to wherever the Media Library's files are served from.
 */
function mulino_allow_upload_redirect_host( $hosts ) {
	$uploads = wp_get_upload_dir();
	$host    = wp_parse_url( $uploads['baseurl'], PHP_URL_HOST );
	if ( $host ) {
		$hosts[] = $host;
	}
	return $hosts;
}
add_filter( 'allowed_redirect_hosts', 'mulino_allow_upload_redirect_host' );

function mulino_document_not_found() {
	wp_die(
		esc_html__( 'This document could not be found.', 'mulino-file-show' ),
		esc_html__( 'Document not found', 'mulino-file-show' ),
		array( 'response' => 404 )
	);
}
