<?php
/**
 * Small shared helpers used by both the frontend shortcode and the
 * admin Document Manager screen -- mapping a file URL to a short
 * type badge (PDF/DOC/XLS/...) and rendering the icon SVGs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map a file extension to a short label + colour, so files get a
 * recognisable little badge instead of a generic icon.
 */
function mulino_get_file_type( $url ) {
	$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

	$map = array(
		'pdf'  => array( 'label' => 'PDF', 'color' => '#e2574c' ),
		'doc'  => array( 'label' => 'DOC', 'color' => '#2b579a' ),
		'docx' => array( 'label' => 'DOC', 'color' => '#2b579a' ),
		'xls'  => array( 'label' => 'XLS', 'color' => '#217346' ),
		'xlsx' => array( 'label' => 'XLS', 'color' => '#217346' ),
		'ppt'  => array( 'label' => 'PPT', 'color' => '#d24726' ),
		'pptx' => array( 'label' => 'PPT', 'color' => '#d24726' ),
		'zip'  => array( 'label' => 'ZIP', 'color' => '#8a8a8a' ),
		'jpg'  => array( 'label' => 'IMG', 'color' => '#9c27b0' ),
		'jpeg' => array( 'label' => 'IMG', 'color' => '#9c27b0' ),
		'png'  => array( 'label' => 'IMG', 'color' => '#9c27b0' ),
	);

	if ( isset( $map[ $ext ] ) ) {
		return $map[ $ext ];
	}

	return array(
		'label' => $ext ? strtoupper( substr( $ext, 0, 4 ) ) : 'FIL',
		'color' => '#607d8b',
	);
}

function mulino_file_icon_svg( $label, $color ) {
	return '<svg class="mulino-icon" viewBox="0 0 48 60" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M4 2h24l16 16v36a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V6a4 4 0 0 1 4-4Z" fill="#ffffff" stroke="#cfd4d8" stroke-width="1.5"/>
		<path d="M28 2v12a4 4 0 0 0 4 4h12Z" fill="#e9edf0" stroke="#cfd4d8" stroke-width="1.5"/>
		<rect x="0" y="42" width="48" height="18" rx="3" fill="' . esc_attr( $color ) . '"/>
		<text x="24" y="55" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="12" font-weight="bold" fill="#ffffff">' . esc_html( $label ) . '</text>
	</svg>';
}

/**
 * Sort folders or documents the way people read numbers: "Minutes 2"
 * before "Minutes 10", and "2009" before "2010". The database's own
 * alphabetical ORDER BY can't do this, so it happens here in PHP.
 *
 * @param array    $items The terms or posts to sort.
 * @param callable $key   Returns the text to sort one item by.
 * @param string   $order "asc" or "desc".
 * @return array The same items, sorted and re-indexed.
 */
function mulino_natural_sort( $items, $key, $order = 'asc' ) {
	if ( ! is_array( $items ) ) {
		return array();
	}

	$direction = 'desc' === $order ? -1 : 1;
	usort(
		$items,
		function ( $a, $b ) use ( $key, $direction ) {
			return $direction * strnatcasecmp( (string) $key( $a ), (string) $key( $b ) );
		}
	);

	return $items;
}

/**
 * Whether a folder with this name already sits directly under $parent_id.
 *
 * wp_insert_term() refuses such duplicates, but wp_update_term() does
 * not, so renaming or moving a folder could otherwise create two
 * sibling folders with the same name. The comparison ignores case,
 * like WordPress' own check when a folder is created.
 *
 * @param string $name       The folder name to look for.
 * @param int    $parent_id  The parent folder's term ID, or 0 for top-level.
 * @param int    $exclude_id A folder to ignore (the one being renamed or moved).
 * @return bool
 */
function mulino_folder_name_exists( $name, $parent_id, $exclude_id = 0 ) {
	$siblings = get_terms(
		array(
			'taxonomy'   => 'mulino_folder',
			'parent'     => (int) $parent_id,
			'hide_empty' => false,
			'exclude'    => $exclude_id ? array( (int) $exclude_id ) : array(),
		)
	);
	if ( is_wp_error( $siblings ) || ! is_array( $siblings ) ) {
		return false;
	}

	$normalize = function ( $text ) {
		$text = trim( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	};

	$wanted = $normalize( $name );
	foreach ( $siblings as $sibling ) {
		if ( (int) $sibling->term_id !== (int) $exclude_id && $normalize( $sibling->name ) === $wanted ) {
			return true;
		}
	}

	return false;
}
