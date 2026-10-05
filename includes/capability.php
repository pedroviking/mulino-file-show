<?php
/**
 * The plugin's own capability, manage_mulino_documents: who may use the
 * File Show screen, upload, move, rename and delete documents and
 * folders. Administrators and editors get it automatically, and an
 * administrator can give it to any single user (e.g. a board member)
 * on that user's profile screen, without making them an editor of the
 * whole site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MULINO_CAPABILITY', 'manage_mulino_documents' );

/**
 * Give the capability to every role that could manage documents before
 * 1.3 and can upload files (administrator, editor and author on a
 * normal site), so nobody loses access on upgrade. Runs once per site,
 * whenever the stored version is older than 1.3.
 */
function mulino_maybe_grant_capability() {
	if ( version_compare( (string) get_option( 'mulino_capability_version', '0' ), '1.3.0', '>=' ) ) {
		return;
	}

	$roles = wp_roles();
	foreach ( $roles->role_objects as $role ) {
		if ( $role->has_cap( 'edit_posts' ) && $role->has_cap( 'upload_files' ) ) {
			$role->add_cap( MULINO_CAPABILITY );
		}
	}

	update_option( 'mulino_capability_version', '1.3.0' );
}
add_action( 'init', 'mulino_maybe_grant_capability' );

/**
 * Whether the current user may manage the document library.
 */
function mulino_current_user_can_manage() {
	return current_user_can( MULINO_CAPABILITY );
}

/**
 * The post type's and the taxonomy's own capabilities, all mapped to
 * manage_mulino_documents. With map_meta_cap, WordPress still turns
 * "edit_post 123" into one of these, so a board member who has only
 * this one capability can edit every document, but no other posts.
 */
function mulino_post_type_capabilities() {
	return array(
		'edit_posts'             => MULINO_CAPABILITY,
		'edit_others_posts'      => MULINO_CAPABILITY,
		'edit_private_posts'     => MULINO_CAPABILITY,
		'edit_published_posts'   => MULINO_CAPABILITY,
		'publish_posts'          => MULINO_CAPABILITY,
		'read_private_posts'     => MULINO_CAPABILITY,
		'delete_posts'           => MULINO_CAPABILITY,
		'delete_others_posts'    => MULINO_CAPABILITY,
		'delete_private_posts'   => MULINO_CAPABILITY,
		'delete_published_posts' => MULINO_CAPABILITY,
		'create_posts'           => MULINO_CAPABILITY,
	);
}

function mulino_taxonomy_capabilities() {
	return array(
		'manage_terms' => MULINO_CAPABILITY,
		'edit_terms'   => MULINO_CAPABILITY,
		'delete_terms' => MULINO_CAPABILITY,
		'assign_terms' => MULINO_CAPABILITY,
	);
}

/**
 * The "Can manage documents" checkbox on a user's profile, shown to
 * people who may edit users. Users whose role already has the
 * capability see that instead of a checkbox, since unticking it would
 * not take the role's capability away.
 */
function mulino_render_user_capability_field( $user ) {
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'edit_users' ) ) {
		return;
	}

	$from_role = false;
	foreach ( (array) $user->roles as $role_name ) {
		$role = get_role( $role_name );
		if ( $role && $role->has_cap( MULINO_CAPABILITY ) ) {
			$from_role = true;
		}
	}
	?>
	<h2><?php esc_html_e( 'Mulino file show', 'mulino-file-show' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Documents', 'mulino-file-show' ); ?></th>
			<td>
				<?php if ( $from_role ) : ?>
					<p><?php esc_html_e( 'This user can manage the document library through their role.', 'mulino-file-show' ); ?></p>
				<?php else : ?>
					<?php wp_nonce_field( 'mulino_user_capability', 'mulino_user_capability_nonce' ); ?>
					<label for="mulino_manage_documents">
						<input type="checkbox" id="mulino_manage_documents" name="mulino_manage_documents" value="1" <?php checked( ! empty( $user->allcaps[ MULINO_CAPABILITY ] ) ); ?> />
						<?php esc_html_e( 'Can manage the document library (upload, move, rename and delete documents and folders)', 'mulino-file-show' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'For example a board member who should look after the documents without being able to edit the rest of the site.', 'mulino-file-show' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'edit_user_profile', 'mulino_render_user_capability_field' );

function mulino_save_user_capability_field( $user_id ) {
	if ( ! isset( $_POST['mulino_user_capability_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mulino_user_capability_nonce'] ) ), 'mulino_user_capability' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_user', $user_id ) || ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'edit_users' ) ) ) {
		return;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}
	if ( ! empty( $_POST['mulino_manage_documents'] ) ) {
		$user->add_cap( MULINO_CAPABILITY );
	} else {
		$user->remove_cap( MULINO_CAPABILITY );
	}
}
add_action( 'edit_user_profile_update', 'mulino_save_user_capability_field' );
