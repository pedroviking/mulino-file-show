<?php
/**
 * Frontend [mulino_documents] shortcode: breadcrumb, search, subfolder
 * grid or list, documents, page links, and the styling for the
 * public-facing browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 4. FRONTEND SHORTCODE: [mulino_documents]
 * -----------------------------------------------------------------
 * Renders the current folder's subfolders + documents, with a
 * breadcrumb trail built from the taxonomy's own parent/child data.
 * No filesystem paths are ever read from user input, only a term
 * slug that is looked up against the mulino_folder taxonomy -- so
 * there's no path-traversal surface here.
 *
 * Attributes (all optional):
 *  - folder="slug"         start in this folder instead of the whole library;
 *                          visitors can't browse above it
 *  - orderby="name|date"   how documents are sorted (default: name)
 *  - document_order="asc|desc" document sort direction (default: asc);
 *                          plain order="..." is accepted too
 *  - folder_order="asc|desc" folder sort direction (default: asc), e.g.
 *                          "desc" to show the newest year first
 *  - hide_empty="yes|no"   hide folders with no documents in them or in
 *                          any of their subfolders (default: no)
 *  - layout="grid|list"    icons in a grid, or one line per item (default: grid)
 *  - details="yes|no"      show date, size, type and description (default:
 *                          yes in the list layout, no in the grid)
 *  - search="yes|no"       show a search box (default: no)
 *  - per_page="20"         documents per page, with links to the next pages
 *                          (default: 0, all on one page)
 *  - logged_in_only="yes|no" show the library to logged-in users only (default: no)
 */
function mulino_shortcode( $atts = array() ) {
	$taxonomy = 'mulino_folder';
	$args     = mulino_parse_shortcode_atts( $atts );

	if ( $args['logged_in_only'] && ! is_user_logged_in() ) {
		return mulino_render_login_message();
	}

	$root_term = false;
	if ( '' !== $args['folder'] ) {
		$root_term = get_term_by( 'slug', $args['folder'], $taxonomy );
		if ( ! $root_term || is_wp_error( $root_term ) ) {
			// Only editors see why the browser is missing; visitors just
			// see nothing rather than a half-broken library.
			if ( mulino_current_user_can_manage() ) {
				return '<p class="mulino-empty">' . esc_html(
					sprintf(
						/* translators: %s: the folder slug given in the shortcode's folder="" attribute. */
						__( 'Mulino file show: the folder "%s" was not found.', 'mulino-file-show' ),
						$args['folder']
					)
				) . '</p>';
			}
			return '';
		}
		if ( ! mulino_user_can_view_folder( $root_term ) ) {
			return is_user_logged_in() ? '' : mulino_render_login_message();
		}
	}

	// Sanitize + validate the requested folder slug. This is read-only
	// display filtering (which folder to show), not a state-changing
	// action, so nonce verification doesn't apply here the way it
	// would for a form submission.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$requested_slug = isset( $_GET['mulino_folder'] ) ? sanitize_title( wp_unslash( $_GET['mulino_folder'] ) ) : '';
	$current_term   = $requested_slug ? get_term_by( 'slug', $requested_slug, $taxonomy ) : false;
	if ( ! $current_term || is_wp_error( $current_term ) || ! mulino_term_is_within( $current_term, $root_term, $taxonomy ) || ! mulino_user_can_view_folder( $current_term ) ) {
		$current_term = $root_term;
	}

	$search = '';
	if ( $args['search'] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a search only reads.
		$search = isset( $_GET['mulino_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['mulino_search'] ) ) ) : '';
	}

	$classes = 'mulino-browser mulino-layout-' . $args['layout'];

	ob_start();
	?>
	<div class="<?php echo esc_attr( $classes ); ?>">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mulino_render_breadcrumb( $current_term, $taxonomy, $root_term );
		if ( $args['search'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
			echo mulino_render_search_form( $current_term, $root_term, $search );
		}
		if ( '' !== $search ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
			echo mulino_render_search_results( $current_term, $taxonomy, $search, $args );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
			echo mulino_render_subfolders( $current_term, $taxonomy, $args );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
			echo mulino_render_documents( $current_term, $taxonomy, $args );
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'mulino_documents', 'mulino_shortcode' );

/**
 * Merge the shortcode attributes with their defaults and force every
 * value into its allowed set, so the render functions never have to
 * second-guess what they're given.
 */
function mulino_parse_shortcode_atts( $atts ) {
	$atts = shortcode_atts(
		array(
			'folder'         => '',
			'orderby'        => 'name',
			'document_order' => '',
			'order'          => 'asc',
			'folder_order'   => 'asc',
			'hide_empty'     => 'no',
			'layout'         => 'grid',
			'details'        => '',
			'search'         => 'no',
			'per_page'       => '0',
			'logged_in_only' => 'no',
		),
		$atts,
		'mulino_documents'
	);

	$orderby      = strtolower( trim( (string) $atts['orderby'] ) );
	// document_order mirrors folder_order; order is the shorter alias.
	$order        = strtolower( trim( (string) ( '' !== $atts['document_order'] ? $atts['document_order'] : $atts['order'] ) ) );
	$folder_order = strtolower( trim( (string) $atts['folder_order'] ) );
	$layout       = 'list' === strtolower( trim( (string) $atts['layout'] ) ) ? 'list' : 'grid';
	$details      = strtolower( trim( (string) $atts['details'] ) );

	return array(
		'folder'         => sanitize_title( (string) $atts['folder'] ),
		'orderby'        => in_array( $orderby, array( 'name', 'date' ), true ) ? $orderby : 'name',
		'order'          => 'desc' === $order ? 'desc' : 'asc',
		'folder_order'   => 'desc' === $folder_order ? 'desc' : 'asc',
		'hide_empty'     => mulino_is_yes( $atts['hide_empty'] ),
		'layout'         => $layout,
		// Unless set, details are part of the list layout and not of the grid.
		'details'        => '' === $details ? 'list' === $layout : mulino_is_yes( $details ),
		'search'         => mulino_is_yes( $atts['search'] ),
		'per_page'       => max( 0, (int) $atts['per_page'] ),
		'logged_in_only' => mulino_is_yes( $atts['logged_in_only'] ),
	);
}

/**
 * Whether a shortcode attribute means "yes" (yes, true, 1, on).
 */
function mulino_is_yes( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	return in_array( strtolower( trim( (string) $value ) ), array( 'yes', 'true', '1', 'on' ), true );
}

/**
 * Is $term the root folder itself, or somewhere below it? With no
 * root folder (the whole library), every folder qualifies.
 */
function mulino_term_is_within( $term, $root_term, $taxonomy ) {
	if ( ! $root_term ) {
		return true;
	}
	if ( (int) $term->term_id === (int) $root_term->term_id ) {
		return true;
	}
	return in_array( (int) $root_term->term_id, array_map( 'intval', get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ), true );
}

/**
 * The address of the page the library is on, without the library's own
 * query arguments (folder, search, page number).
 */
function mulino_browser_base_url() {
	return remove_query_arg( array( 'mulino_folder', 'mulino_search', 'mulino_page' ) );
}

/**
 * The link to a folder in the browser.
 */
function mulino_folder_url( $term ) {
	return add_query_arg( 'mulino_folder', $term->slug, mulino_browser_base_url() );
}

function mulino_render_login_message() {
	$here = get_permalink();
	return '<p class="mulino-login">' . esc_html__( 'Log in to see the documents.', 'mulino-file-show' )
		. ' <a href="' . esc_url( wp_login_url( $here ? $here : '' ) ) . '">' . esc_html__( 'Log in', 'mulino-file-show' ) . '</a></p>';
}

function mulino_render_breadcrumb( $current_term, $taxonomy, $root_term = false ) {
	$base_url = mulino_browser_base_url();
	$crumbs   = array();

	$at_root = ! $current_term || ( $root_term && (int) $current_term->term_id === (int) $root_term->term_id );

	if ( $at_root ) {
		$crumbs[] = '<a href="' . esc_url( $base_url ) . '" aria-current="page">' . esc_html__( 'Home', 'mulino-file-show' ) . '</a>';
	} else {
		$crumbs[] = '<a href="' . esc_url( $base_url ) . '">' . esc_html__( 'Home', 'mulino-file-show' ) . '</a>';
	}

	if ( ! $at_root && ! is_wp_error( $current_term ) ) {
		$ancestors = array_reverse( get_ancestors( $current_term->term_id, $taxonomy, 'taxonomy' ) );
		$visible   = ! $root_term; // with a root folder, start listing below it
		foreach ( $ancestors as $ancestor_id ) {
			if ( ! $visible ) {
				$visible = ( (int) $ancestor_id === (int) $root_term->term_id );
				continue;
			}
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$url      = add_query_arg( 'mulino_folder', $ancestor->slug, $base_url );
				$crumbs[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $ancestor->name ) . '</a>';
			}
		}
		$crumbs[] = '<span class="mulino-current" aria-current="page">' . esc_html( $current_term->name ) . '</span>';
	}

	return '<nav class="mulino-breadcrumb" aria-label="' . esc_attr__( 'Folders', 'mulino-file-show' ) . '">' . implode( ' <span aria-hidden="true">&raquo;</span> ', $crumbs ) . '</nav>';
}

/**
 * The folders directly inside $current_term (or at the top of the
 * library) that the visitor may see, sorted.
 *
 * @return WP_Term[]
 */
function mulino_get_visible_subfolders( $current_term, $taxonomy, $args = array() ) {
	$args      = wp_parse_args(
		$args,
		array(
			'folder_order' => 'asc',
			'hide_empty'   => false,
		)
	);
	$parent_id = $current_term ? $current_term->term_id : 0;

	$subfolders = get_terms(
		array(
			'taxonomy'     => $taxonomy,
			'parent'       => $parent_id,
			// With hide_empty, WordPress still keeps a folder whose own
			// count is 0 if one of its subfolders has documents (e.g. a
			// decade folder that only holds year folders), because
			// hierarchical defaults to true.
			'hide_empty'   => (bool) $args['hide_empty'],
			'hierarchical' => true,
		)
	);

	if ( is_wp_error( $subfolders ) || empty( $subfolders ) ) {
		return array();
	}

	$subfolders = array_filter(
		$subfolders,
		function ( $folder ) {
			return mulino_user_can_view_folder( $folder );
		}
	);

	return mulino_natural_sort(
		$subfolders,
		function ( $folder ) {
			return $folder->name;
		},
		$args['folder_order']
	);
}

function mulino_render_subfolders( $current_term, $taxonomy, $args = array() ) {
	$args       = wp_parse_args( $args, array( 'layout' => 'grid' ) );
	$subfolders = mulino_get_visible_subfolders( $current_term, $taxonomy, $args );
	if ( empty( $subfolders ) ) {
		return '';
	}

	$list = 'list' === $args['layout'];
	$out  = $list ? '<ul class="mulino-list mulino-list--folders">' : '<div class="mulino-grid">';
	foreach ( $subfolders as $folder ) {
		$link = '<a class="' . ( $list ? 'mulino-row-link' : 'mulino-card mulino-card--folder' ) . '" href="' . esc_url( mulino_folder_url( $folder ) ) . '">'
			. mulino_folder_icon_svg()
			. '<span class="mulino-name">' . esc_html( $folder->name ) . mulino_members_badge( $folder ) . '</span>'
			. '</a>';
		$out .= $list ? '<li class="mulino-row mulino-row--folder">' . $link . '</li>' : $link;
	}
	$out .= $list ? '</ul>' : '</div>';

	return $out;
}

/**
 * A small lock after the name of a folder that not everyone can see,
 * so logged-in users know why it may be missing for others.
 */
function mulino_members_badge( $folder ) {
	if ( 'public' === mulino_get_folder_visibility( $folder->term_id ) ) {
		return '';
	}
	$options = mulino_folder_visibility_options();
	$value   = mulino_get_folder_visibility( $folder->term_id );
	$label   = isset( $options[ $value ] ) ? $options[ $value ] : __( 'Not visible to everyone', 'mulino-file-show' );
	return ' <span class="mulino-badge" title="' . esc_attr( $label ) . '">'
		. '<svg class="mulino-lock" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 7V5a4 4 0 0 1 8 0v2h1v8H3V7Zm2 0h4V5a2 2 0 0 0-4 0Z" fill="currentColor"/></svg>'
		. '<span class="mulino-sr-only">' . esc_html( $label ) . '</span></span>';
}

function mulino_folder_icon_svg() {
	return '<svg class="mulino-icon" viewBox="0 0 56 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<path d="M2 7a4 4 0 0 1 4-4h13l4 5h27a4 4 0 0 1 4 4v27a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffcf5c" stroke="#e0a52e" stroke-width="1.5"/>
		<path d="M2 14h52v22a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffe08a" stroke="#e0a52e" stroke-width="1.5"/>
	</svg>';
}

function mulino_render_documents( $current_term, $taxonomy, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'orderby'    => 'name',
			'order'      => 'asc',
			'hide_empty' => false,
			'per_page'   => 0,
		)
	);

	if ( $current_term ) {
		// A tax_query is inherently scoped to one specific folder
		// term here (not an open-ended query), so this stays fast
		// even on a large document library.
		$tax_query = array(
			array(
				'taxonomy'         => $taxonomy,
				'field'            => 'term_id',
				'terms'            => $current_term->term_id,
				'include_children' => false,
			),
		);
	} else {
		// Top of the whole library: documents that aren't in any
		// folder, the same ones the admin screen shows under "Top level".
		$tax_query = array(
			array(
				'taxonomy' => $taxonomy,
				'operator' => 'NOT EXISTS',
			),
		);
	}

	$query_args = array(
		'post_type'      => 'mulino_document',
		'posts_per_page' => -1,
		'orderby'        => 'date' === $args['orderby'] ? 'date' : 'title',
		'order'          => strtoupper( $args['order'] ),
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		'tax_query'      => $tax_query,
	);

	/**
	 * Filters the get_posts() arguments used to list the documents in
	 * one folder of the frontend [mulino_documents] browser.
	 *
	 * @param array         $query_args   Arguments passed to get_posts().
	 * @param WP_Term|false $current_term The folder being shown, or false
	 *                                    at the top of the whole library.
	 */
	$query_args = apply_filters( 'mulino_frontend_document_query_args', $query_args, $current_term );

	$documents = array_values(
		array_filter(
			get_posts( $query_args ),
			function ( $doc ) {
				return mulino_user_can_view_document( $doc );
			}
		)
	);

	if ( 'name' === $args['orderby'] ) {
		// The database sorts "Minutes 10" before "Minutes 2"; people
		// expect the other way round.
		$documents = mulino_natural_sort(
			$documents,
			function ( $doc ) {
				return get_the_title( $doc );
			},
			$args['order']
		);
	}

	if ( empty( $documents ) ) {
		// Only show the "empty" message if this folder is completely
		// empty -- if it has subfolders, those are enough to look at,
		// and this message would just be visual noise underneath them.
		if ( mulino_get_visible_subfolders( $current_term, $taxonomy, $args ) ) {
			return '';
		}
		return '<p class="mulino-empty">' . esc_html__( 'No documents in this folder.', 'mulino-file-show' ) . '</p>';
	}

	$pagination = '';
	if ( $args['per_page'] > 0 && count( $documents ) > $args['per_page'] ) {
		$pages = (int) ceil( count( $documents ) / $args['per_page'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a page number only reads.
		$page       = isset( $_GET['mulino_page'] ) ? min( $pages, max( 1, absint( $_GET['mulino_page'] ) ) ) : 1;
		$documents  = array_slice( $documents, ( $page - 1 ) * $args['per_page'], $args['per_page'] );
		$pagination = mulino_render_pagination( $page, $pages );
	}

	return mulino_render_document_items( $documents, $args ) . $pagination;
}

/**
 * The documents as grid cards or list rows.
 *
 * @param WP_Post[] $documents   The documents, already sorted.
 * @param array     $args        Parsed shortcode attributes (layout, details).
 * @param bool      $show_folder Also name each document's folder (search results).
 */
function mulino_render_document_items( $documents, $args, $show_folder = false ) {
	$args = wp_parse_args(
		$args,
		array(
			'layout'  => 'grid',
			'details' => false,
		)
	);
	$list = 'list' === $args['layout'];

	$out = $list ? '<ul class="mulino-list">' : '<div class="mulino-grid">';
	foreach ( $documents as $doc ) {
		$attachment_id = (int) get_post_meta( $doc->ID, '_mulino_file_id', true );
		if ( ! $attachment_id ) {
			continue;
		}
		$file_url = wp_get_attachment_url( $attachment_id );
		if ( ! $file_url ) {
			continue;
		}
		$icon  = mulino_get_file_type( $file_url );
		$title = get_the_title( $doc );

		$details = '';
		if ( $args['details'] ) {
			$parts = array( esc_html( $icon['label'] ) );
			$size  = mulino_document_file_size( $attachment_id );
			if ( $size ) {
				$parts[] = esc_html( size_format( $size, $size < 1048576 ? 0 : 1 ) );
			}
			$parts[] = '<time datetime="' . esc_attr( get_the_date( 'Y-m-d', $doc ) ) . '">' . esc_html( get_the_date( '', $doc ) ) . '</time>';
			$details = '<span class="mulino-meta">' . implode( ' <span aria-hidden="true">&middot;</span> ', $parts ) . '</span>';

			$description = (string) get_post_meta( $doc->ID, '_mulino_description', true );
			if ( '' !== $description ) {
				$details .= '<span class="mulino-description">' . esc_html( $description ) . '</span>';
			}
		}
		if ( $show_folder ) {
			$details .= mulino_document_folder_label( $doc );
		}

		// The type is only drawn inside the icon, so say it to screen
		// readers too (unless the details already do), and that the
		// document opens in a new tab.
		$sr_text = ( $args['details'] ? '' : ' (' . $icon['label'] . ')' ) . ' ' . __( '(opens in a new tab)', 'mulino-file-show' );

		$link_open = '<a class="' . ( $list ? 'mulino-row-link' : 'mulino-card mulino-card--file' ) . '" href="' . esc_url( mulino_document_url( $doc ) ) . '" target="_blank" rel="noopener">';
		$name      = '<span class="mulino-name">' . esc_html( $title ) . '<span class="mulino-sr-only">' . esc_html( $sr_text ) . '</span></span>';
		$icon_svg  = mulino_file_icon_svg( $icon['label'], $icon['color'] );

		if ( $list ) {
			$out .= '<li class="mulino-row mulino-row--file">' . $link_open . $icon_svg
				. '<span class="mulino-row-text">' . $name . $details . '</span></a></li>';
		} else {
			$out .= $link_open . $icon_svg . $name . $details . '</a>';
		}
	}
	$out .= $list ? '</ul>' : '</div>';

	return $out;
}

/**
 * The size of a document's file in bytes, or 0 if unknown.
 */
function mulino_document_file_size( $attachment_id ) {
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( is_array( $meta ) && ! empty( $meta['filesize'] ) ) {
		return (int) $meta['filesize'];
	}
	$path = get_attached_file( $attachment_id );
	return ( $path && file_exists( $path ) ) ? (int) filesize( $path ) : 0;
}

/**
 * "In: Minutes > 2024" under a search result.
 */
function mulino_document_folder_label( $doc ) {
	$terms = get_the_terms( $doc, 'mulino_folder' );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return '<span class="mulino-folder-path">' . esc_html__( 'In: Home', 'mulino-file-show' ) . '</span>';
	}
	$term  = reset( $terms );
	$names = array();
	foreach ( array_reverse( get_ancestors( $term->term_id, 'mulino_folder', 'taxonomy' ) ) as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'mulino_folder' );
		if ( $ancestor && ! is_wp_error( $ancestor ) ) {
			$names[] = $ancestor->name;
		}
	}
	$names[] = $term->name;
	return '<span class="mulino-folder-path">' . esc_html(
		sprintf(
			/* translators: %s: folder path, e.g. "Minutes > 2024". */
			__( 'In: %s', 'mulino-file-show' ),
			implode( ' > ', $names )
		)
	) . '</span>';
}

function mulino_render_pagination( $page, $pages ) {
	$base = remove_query_arg( 'mulino_page' );
	$out  = '<nav class="mulino-pagination" aria-label="' . esc_attr__( 'Pages', 'mulino-file-show' ) . '">';
	if ( $page > 1 ) {
		$prev = 2 === $page ? $base : add_query_arg( 'mulino_page', $page - 1, $base );
		$out .= '<a class="mulino-page-prev" href="' . esc_url( $prev ) . '">' . esc_html__( '&laquo; Previous', 'mulino-file-show' ) . '</a>';
	}
	$out .= '<span class="mulino-page-count">' . esc_html(
		sprintf(
			/* translators: 1: current page number, 2: number of pages. */
			__( 'Page %1$d of %2$d', 'mulino-file-show' ),
			$page,
			$pages
		)
	) . '</span>';
	if ( $page < $pages ) {
		$out .= '<a class="mulino-page-next" href="' . esc_url( add_query_arg( 'mulino_page', $page + 1, $base ) ) . '">' . esc_html__( 'Next &raquo;', 'mulino-file-show' ) . '</a>';
	}
	$out .= '</nav>';
	return $out;
}

/**
 * The search box. It searches the open folder and everything below it,
 * so at the top it searches the whole library.
 */
function mulino_render_search_form( $current_term, $root_term, $search ) {
	static $instance = 0;
	++$instance;

	// A GET form drops the query string of its action address, so the
	// page's own arguments (e.g. ?page_id=12 without pretty permalinks)
	// travel as hidden fields instead.
	$base   = mulino_browser_base_url();
	$action = strtok( $base, '?' );
	$query  = array();
	$raw    = wp_parse_url( $base, PHP_URL_QUERY );
	if ( $raw ) {
		wp_parse_str( $raw, $query );
	}

	$hidden = '';
	foreach ( $query as $key => $value ) {
		if ( is_scalar( $value ) ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}
	}
	$in_root = ! $current_term || ( $root_term && (int) $current_term->term_id === (int) $root_term->term_id );
	if ( ! $in_root ) {
		$hidden .= '<input type="hidden" name="mulino_folder" value="' . esc_attr( $current_term->slug ) . '" />';
	}

	$id          = 'mulino-search-' . $instance;
	$placeholder = $in_root ? __( 'Search documents', 'mulino-file-show' ) : sprintf(
		/* translators: %s: folder name. */
		__( 'Search in %s', 'mulino-file-show' ),
		$current_term->name
	);

	$out = '<form class="mulino-search" role="search" method="get" action="' . esc_url( $action ) . '">'
		. $hidden
		. '<label class="mulino-sr-only" for="' . esc_attr( $id ) . '">' . esc_html( $placeholder ) . '</label>'
		. '<input type="search" id="' . esc_attr( $id ) . '" name="mulino_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr( $placeholder ) . '" />'
		. '<button type="submit">' . esc_html__( 'Search', 'mulino-file-show' ) . '</button>'
		. '</form>';

	return $out;
}

/**
 * Documents in the open folder or below it whose name or description
 * contains $search.
 *
 * @return WP_Post[]
 */
function mulino_search_documents( $current_term, $taxonomy, $search ) {
	$query_args = array(
		'post_type'      => 'mulino_document',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	);

	if ( $current_term ) {
		$folder_ids = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'child_of'   => $current_term->term_id,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$folder_ids   = is_array( $folder_ids ) ? $folder_ids : array();
		$folder_ids[] = (int) $current_term->term_id;
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- limited to one folder and its subfolders.
		$query_args['tax_query'] = array(
			array(
				'taxonomy'         => $taxonomy,
				'field'            => 'term_id',
				'terms'            => array_map( 'intval', $folder_ids ),
				'include_children' => false,
			),
		);
	}

	$needle  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search, 'UTF-8' ) : strtolower( $search );
	$matches = array();
	foreach ( get_posts( $query_args ) as $doc ) {
		$haystack = get_the_title( $doc ) . ' ' . (string) get_post_meta( $doc->ID, '_mulino_description', true );
		$haystack = html_entity_decode( $haystack, ENT_QUOTES, 'UTF-8' );
		$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack, 'UTF-8' ) : strtolower( $haystack );
		if ( false !== strpos( $haystack, $needle ) && mulino_user_can_view_document( $doc ) ) {
			$matches[] = $doc;
		}
	}

	return mulino_natural_sort(
		$matches,
		function ( $doc ) {
			return get_the_title( $doc );
		}
	);
}

function mulino_render_search_results( $current_term, $taxonomy, $search, $args ) {
	$documents = mulino_search_documents( $current_term, $taxonomy, $search );
	$clear_url = $current_term ? add_query_arg( 'mulino_folder', $current_term->slug, mulino_browser_base_url() ) : mulino_browser_base_url();

	$out = '<div class="mulino-search-results" role="status"><p class="mulino-search-summary">'
		. esc_html(
			sprintf(
				/* translators: 1: number of documents found, 2: the search text. */
				_n( '%1$d document found for "%2$s".', '%1$d documents found for "%2$s".', count( $documents ), 'mulino-file-show' ),
				count( $documents ),
				$search
			)
		)
		. ' <a href="' . esc_url( $clear_url ) . '">' . esc_html__( 'Clear search', 'mulino-file-show' ) . '</a></p></div>';

	if ( $documents ) {
		$out .= mulino_render_document_items( $documents, $args, true );
	}
	return $out;
}

/**
 * -----------------------------------------------------------------
 * 5. FRONTEND STYLESHEET
 * -----------------------------------------------------------------
 * Enqueued unconditionally on the frontend: the file is tiny, and
 * this is simpler and more reliable than trying to detect shortcode
 * usage before wp_enqueue_scripts runs (shortcodes render later).
 */
function mulino_enqueue_frontend_styles() {
	wp_enqueue_style(
		'mulino-frontend',
		MULINO_URL . 'assets/css/frontend.css',
		array(),
		MULINO_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'mulino_enqueue_frontend_styles' );
