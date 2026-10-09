# Extending Mulino file show

Mulino file show fires a handful of WordPress actions and filters that a
separate add-on plugin (e.g. Mulino file show Plus) can hook into, instead of
calling Mulino file show's internal functions directly. Internal function names
can change between releases; these hooks are the stable, supported way in.

## Actions (something happened)

The actions fire after Mulino file show has written its changes to the
database, so a listener can safely read them. Bulk actions fire the
single-document action once per document, and `mulino_after_folder_created`
also fires for folders created by a folder upload or an import.

| Hook | Fires when | Arguments |
|---|---|---|
| `mulino_after_upload` | A document has been uploaded and filed | `$post_id, $attachment_id, $folder_id` (`$folder_id` is `0` for the root view) |
| `mulino_after_move` | A document has been re-filed into a folder | `$doc_id, $folder_id` (`$folder_id` is `0` for the root view) |
| `mulino_after_rename_doc` | A document has been renamed | `$doc_id, $name` |
| `mulino_after_folder_created` | A new folder has been created | `$term_id, $parent_id` (`$parent_id` is `0` for top-level) |
| `mulino_after_folder_moved` | A folder has been re-parented | `$term_id, $new_parent_id` |
| `mulino_after_folder_renamed` | A folder has been renamed | `$term_id, $name` |
| `mulino_after_delete_doc` | A document has been moved to the trash | `$doc_id` |
| `mulino_after_folder_deleted` | An (empty) folder has been deleted | `$term_id` |
| `mulino_after_import` | A document has been imported from another plugin | `$post_id, $attachment_id, $folder_id, $source` (`$source` is e.g. `sfl:1:Minutes/2024.pdf`) |
| `mulino_after_replace_file` | A document's file has been replaced with a new version | `$doc_id, $attachment_id, $old_attachment_id` (`$old_attachment_id` is `0` if there was none) |
| `mulino_after_description_changed` | A document's description has been changed | `$doc_id, $description` (`''` if removed) |
| `mulino_after_folder_visibility_changed` | A folder's "Who can see this folder" setting has been changed | `$term_id, $visibility` (e.g. `public` or `members`) |
| `mulino_before_serve_document` | A visitor who may see a document opens its `?mulino_document=ID` link, just before they are forwarded to the file | `WP_Post $doc, $attachment_id` |

Example:

```php
add_action( 'mulino_after_upload', function ( $post_id, $attachment_id, $folder_id ) {
    // e.g. queue a PDF thumbnail generation job.
}, 10, 3 );
```

## Filters (add your own UI)

| Hook | Where it prints | Signature |
|---|---|---|
| `mulino_manager_toolbar` | Just below the intro text on the Document Manager screen, above the tree/grid | `apply_filters( 'mulino_manager_toolbar', string $html )` |
| `mulino_manager_card_actions` | Inside each document card, after the delete (×) button | `apply_filters( 'mulino_manager_card_actions', string $html, WP_Post $doc )` |

Both are plain string filters -- return HTML. The output is passed
through `wp_kses_post()` before being printed, so only normal
post-safe markup (links, basic formatting, images, etc.) survives;
`<script>` tags and other unsafe content are stripped. Example:

```php
add_filter( 'mulino_manager_toolbar', function ( $html ) {
    return $html . '<p><a href="https://example.com/upgrade" class="button button-primary">Upgrade to Plus</a></p>';
} );
```

## Filters (frontend)

| Hook | What it changes | Signature |
|---|---|---|
| `mulino_frontend_folder_actions` | HTML printed just below the breadcrumb, for the folder the visitor is looking at (e.g. a "Follow this folder" button). Nothing is printed when it returns an empty string | `apply_filters( 'mulino_frontend_folder_actions', string $html, WP_Term\|false $current_term, array $args )` (`false` at the top of the whole library; `$args` are the parsed shortcode attributes, e.g. `folder`, `layout`, `search`) |
| `mulino_frontend_document_query_args` | The `get_posts()` arguments used to list the documents in the folder a visitor is looking at in `[mulino_documents]` | `apply_filters( 'mulino_frontend_document_query_args', array $query_args, WP_Term\|false $current_term )` (`false` at the top of the whole library) |

Example -- only list documents whose title contains "Approved":

```php
add_filter( 'mulino_frontend_document_query_args', function ( $query_args, $current_term ) {
    $query_args['s'] = 'Approved';
    return $query_args;
}, 10, 2 );
```

The output of `mulino_frontend_folder_actions` is wrapped in
`<div class="mulino-folder-actions">` and passed through `wp_kses()`
with the post-safe markup plus `form`, `input`, `button` and `label`
(and `data-*` attributes), so an add-on can print a small form. Scripts
and inline event handlers are stripped -- enqueue your own script and
find the markup by class or `data-*` attribute. The filter also runs
on search results; check `$_GET['mulino_search']` if you don't want it
there. Example -- a "Follow" button that posts to `admin-post.php`:

```php
add_filter( 'mulino_frontend_folder_actions', function ( $html, $current_term, $args ) {
    if ( ! $current_term || ! is_user_logged_in() ) {
        return $html;
    }
    return $html . '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
        . '<input type="hidden" name="action" value="my_follow_folder" />'
        . '<input type="hidden" name="folder" value="' . esc_attr( $current_term->term_id ) . '" />'
        . wp_nonce_field( 'my_follow_folder', '_wpnonce', true, false )
        . '<button type="submit" class="mulino-follow">Follow this folder</button>'
        . '</form>';
}, 10, 3 );
```

## Filters (who may see what)

A folder is shown to everyone or only to logged-in users (term meta
`mulino_visibility`: `public` or `members`), and a hidden folder hides
everything below it. These filters let an add-on add its own choices,
such as "Board members", and decide who may see them. They apply to the
folder browser, its search and the `?mulino_document=ID` links.

| Hook | What it changes | Signature |
|---|---|---|
| `mulino_folder_visibility_options` | The choices under "Who can see this folder on the site" in the Edit folder window | `apply_filters( 'mulino_folder_visibility_options', array $options )` (stored value => label; default `public` and `members`) |
| `mulino_user_can_view_folder` | Whether a visitor may see a folder, its subfolders and its documents | `apply_filters( 'mulino_user_can_view_folder', bool $can, WP_Term $term, int $user_id )` (`$user_id` is `0` when logged out) |
| `mulino_user_can_view_document` | Whether a visitor may see and open one document | `apply_filters( 'mulino_user_can_view_document', bool $can, WP_Post $doc, int $user_id )` |
| `mulino_document_url` | The link to a document in the folder browser | `apply_filters( 'mulino_document_url', string $url, WP_Post $doc )` (default `home_url( '/?mulino_document=ID' )`) |

Example -- a "Board members" choice that only users with the
`board_member` role may see:

```php
add_filter( 'mulino_folder_visibility_options', function ( $options ) {
    $options['board'] = 'Board members';
    return $options;
} );

add_filter( 'mulino_user_can_view_folder', function ( $can, $term, $user_id ) {
    foreach ( array_merge( array( $term->term_id ), get_ancestors( $term->term_id, 'mulino_folder', 'taxonomy' ) ) as $id ) {
        if ( 'board' === get_term_meta( $id, 'mulino_visibility', true ) ) {
            $user = get_userdata( $user_id );
            return $user && in_array( 'board_member', (array) $user->roles, true );
        }
    }
    return $can;
}, 10, 3 );
```

To serve a protected file yourself instead of forwarding to its Media
Library address, send it in `mulino_before_serve_document` and `exit`.

## Capability

Everything on the File Show screen requires `manage_mulino_documents`
(constant `MULINO_CAPABILITY`). The document post type and folder
taxonomy map all their capabilities to it.

## What's intentionally *not* a hook (yet)

Apart from `mulino_frontend_folder_actions`, the markup of the frontend
`[mulino_documents]` browser isn't filterable. If an add-on needs to add
something elsewhere in the public-facing browser (e.g. on each document),
open an issue on the repository rather than reading shortcode.php's
internals directly, since those internals aren't a stable contract.
