<?php
/**
 * The "Document library" block: the same browser as the
 * [mulino_documents] shortcode, with its options in the block sidebar.
 * The block is drawn on the server, so the shortcode and the block can
 * never drift apart.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mulino_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	// The same stylesheet as on the site, so the preview in the editor
	// looks like the real thing.
	wp_register_style( 'mulino-frontend', MULINO_URL . 'assets/css/frontend.css', array(), MULINO_VERSION );

	register_block_type(
		MULINO_PATH . 'blocks/documents',
		array(
			'render_callback' => 'mulino_render_block',
			'style'           => 'mulino-frontend',
		)
	);

	$handle = generate_block_asset_handle( 'mulino/documents', 'editorScript' );
	wp_set_script_translations( $handle, 'mulino-file-show' );
}
add_action( 'init', 'mulino_register_block' );

/**
 * Give the block's "Start in folder" list every folder, indented by
 * depth. The folders aren't in the REST API, so they travel along with
 * the script instead.
 */
function mulino_block_editor_data() {
	$handle = generate_block_asset_handle( 'mulino/documents', 'editorScript' );
	$data   = array( 'folders' => mulino_block_folder_options() );
	wp_add_inline_script( $handle, 'window.mulinoBlock = ' . wp_json_encode( $data ) . ';', 'before' );
}
add_action( 'enqueue_block_editor_assets', 'mulino_block_editor_data' );

/**
 * @return array[] array( 'slug' => ..., 'label' => ... ), parents before
 *                 their children, children indented with dashes.
 */
function mulino_block_folder_options( $parent_id = 0, $depth = 0 ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'mulino_folder',
			'parent'     => (int) $parent_id,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$terms = mulino_natural_sort(
		$terms,
		function ( $term ) {
			return $term->name;
		}
	);

	$options = array();
	foreach ( $terms as $term ) {
		$options[] = array(
			'slug'  => $term->slug,
			'label' => str_repeat( '— ', $depth ) . html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
		);
		$options   = array_merge( $options, mulino_block_folder_options( $term->term_id, $depth + 1 ) );
	}
	return $options;
}

/**
 * Turn the block's attributes into shortcode attributes and draw it.
 */
function mulino_render_block( $attributes ) {
	$a = wp_parse_args(
		(array) $attributes,
		array(
			'folder'        => '',
			'layout'        => 'grid',
			'details'       => '',
			'search'        => false,
			'perPage'       => 0,
			'orderby'       => 'name',
			'documentOrder' => 'asc',
			'folderOrder'   => 'asc',
			'hideEmpty'     => false,
			'loggedInOnly'  => false,
		)
	);

	$html = mulino_shortcode(
		array(
			'folder'         => (string) $a['folder'],
			'layout'         => (string) $a['layout'],
			'details'        => (string) $a['details'],
			'search'         => $a['search'] ? 'yes' : 'no',
			'per_page'       => (string) (int) $a['perPage'],
			'orderby'        => (string) $a['orderby'],
			'document_order' => (string) $a['documentOrder'],
			'folder_order'   => (string) $a['folderOrder'],
			'hide_empty'     => $a['hideEmpty'] ? 'yes' : 'no',
			'logged_in_only' => $a['loggedInOnly'] ? 'yes' : 'no',
		)
	);

	return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
}
