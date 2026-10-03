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
| `mulino_frontend_document_query_args` | The `get_posts()` arguments used to list the documents in the folder a visitor is looking at in `[mulino_documents]` | `apply_filters( 'mulino_frontend_document_query_args', array $query_args, WP_Term\|false $current_term )` (`false` at the top of the whole library) |

Example -- only list documents whose title contains "Approved":

```php
add_filter( 'mulino_frontend_document_query_args', function ( $query_args, $current_term ) {
    $query_args['s'] = 'Approved';
    return $query_args;
}, 10, 2 );
```

## What's intentionally *not* a hook (yet)

Apart from the document query above, the frontend `[mulino_documents]`
shortcode doesn't expose filters yet. If a premium add-on needs to add
something to the public-facing folder browser (not just the admin
screen), that's a reasonable next hook to add -- open an issue on the
repository rather than reading shortcode.php's internals directly, since
those internals aren't a stable contract.
