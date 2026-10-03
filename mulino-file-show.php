<?php
/**
 * Plugin Name:       Mulino file show
 * Description:       Nested-folder document library (e.g. Decade > Year) with drag-and-drop admin upload and a frontend breadcrumb browser shortcode [mulino_documents].
 * Version:           1.2.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Peder Møller
 * License:           GPL v2 or later
 * Text Domain:       mulino-file-show
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MULINO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MULINO_URL', plugin_dir_url( __FILE__ ) );
define( 'MULINO_VERSION', '1.2.0' );

/**
 * Module map, in load order:
 *  - helpers.php      shared file-type/icon helpers (no dependencies)
 *  - post-type.php    the mulino_document post type + mulino_folder taxonomy
 *  - shortcode.php     [mulino_documents] frontend browser
 *  - admin-manager.php the "Mulino file show" admin screen + its AJAX endpoints
 *  - settings.php      the delete-data-on-uninstall setting (Settings > Media)
 *  - export.php        "Export as ZIP" of the whole library
 *  - import-sfl.php    import from the Simple File List plugin
 */
require_once MULINO_PATH . 'includes/helpers.php';
require_once MULINO_PATH . 'includes/post-type.php';
require_once MULINO_PATH . 'includes/shortcode.php';
require_once MULINO_PATH . 'includes/admin-manager.php';
require_once MULINO_PATH . 'includes/settings.php';
require_once MULINO_PATH . 'includes/export.php';
require_once MULINO_PATH . 'includes/import-sfl.php';
