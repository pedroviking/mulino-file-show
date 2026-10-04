=== Mulino file show ===
Contributors: pedroviking
Tags: documents, files, folders, file manager, document library
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse documents in nested, drag-and-drop-organized folders, with a simple public shortcode and no custom database tables.

== Description ==

Mulino file show is a lightweight document library for WordPress. Organize
documents into nested folders (Decade > Year, Department > Project, or
any hierarchy you like), manage everything with a drag-and-drop admin
screen, and let visitors browse the same structure on your public site
with a single shortcode.

**Admin features**

* Drag and drop files straight from your computer to upload them into a folder, or use the "Choose files" button (also on phones and tablets)
* Drag a whole folder from your computer to upload it with all its subfolders
* Drag existing documents or whole folders to re-file them
* Tick several documents to move them or move them to the trash together
* Export the whole library as a ZIP file, with the folders intact
* Import from Simple File List, which has been closed on WordPress.org
* Rename or delete folders and documents from the same screen
* Nested folders of unlimited depth
* Uploads run one file at a time with a progress bar, and files that are too big for your web host are caught before they are sent

**Frontend features**

* `[mulino_documents]` shortcode shows a breadcrumb-navigable folder browser
* Show the whole library, or start in one folder with `folder="..."`
* Natural sorting ("Minutes 2" before "Minutes 10"), optionally newest year first
* Optionally hide folders that have no documents yet
* File-type icons (PDF, Word, Excel, images, and more)
* Every folder has its own link, so you can bookmark it or share it directly

**Under the hood**

Mulino file show stores everything using WordPress' own post types and
taxonomies -- no custom database tables, so your data stays portable
and inspectable with standard WordPress tools. It also exposes a small
set of actions and filters for building your own extensions; see
https://github.com/pedroviking/mulino-file-show/blob/main/HOOKS.md

This plugin is free and open source. Source code, issue tracker, and
the automated test suite live at:
https://github.com/pedroviking/mulino-file-show

== Installation ==

1. Upload the `mulino-file-show` folder to `/wp-content/plugins/`, or install
   through the WordPress plugin screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **File Show** in the admin menu to create folders and upload
   documents.
4. Add the `[mulino_documents]` shortcode to any page or post to
   show the public folder browser.

== Frequently Asked Questions ==

= How do I switch from Simple File List? =

Simple File List was closed on WordPress.org in July 2026 and no longer receives security updates. To move your files over:

1. Install and activate Mulino file show. Keep Simple File List installed for now.
2. Go to **File Show > Import** and click **Start import**. The files are copied into the Media Library, and subfolders, file names, descriptions and upload dates are kept. Simple File List's own files are not changed.
3. Replace the `[eeSFL]` shortcode on your pages with `[mulino_documents]`.
4. Check that everything is there, then deactivate and delete Simple File List.

You can run the import again at any time; files that are already imported are skipped.

= Can I get my documents out again? =

Yes. **Export as ZIP** on the File Show screen downloads every document, sorted into the same folders as in the library.

= Which options does the shortcode have? =

All of them are optional:

* `folder="minutes"` starts the browser in the folder with that slug instead of at the top of the library. Visitors can't browse above it, so you can put different folders on different pages.
* `folder_order="desc"` lists folders in reverse order, e.g. the newest year first. Default: `asc`.
* `orderby="date"` sorts documents by upload date instead of by name. Default: `name`.
* `document_order="desc"` reverses the document order, e.g. "Budget 2010" before "Budget 2009". Default: `asc`. (`order` works too.)
* `hide_empty="yes"` hides folders with no documents in them or in any of their subfolders. Default: `no`.

Example: `[mulino_documents folder="minutes" folder_order="desc"]`

To find a folder's slug, open the folder on the **File Show** admin screen and look at the address bar: it's the part after `folder=`, e.g. `board-minutes` for a folder called "Board Minutes".

= Do I need to know how to code to use this? =

No. Creating folders, uploading files, and organizing them is all done
by dragging and dropping in the admin screen.

= Can I nest folders as deep as I like? =

Yes, folders can be nested to any depth (e.g. a decade folder
containing year folders, which could themselves contain sub-folders).

= Where are the actual files stored? =

Uploaded files go through WordPress' normal media library, so they end
up in your regular `wp-content/uploads/` folder, organized by upload
date. The folder structure you see in Mulino file show is a separate
organizational layer on top of that, not a real filesystem folder
structure.

= What happens to my documents if I delete the plugin? =

By default nothing: your documents and folders stay in the database, so you can reinstall the plugin and carry on. If you want them removed too, tick "Also delete all documents and folders" under **Settings > Media** before deleting the plugin. The uploaded files in the Media Library are kept either way.

= Does deleting a document also delete the uploaded file? =

Not currently. Deleting a document removes it from Mulino file show (moving
it to the trash), but the underlying file remains in your media
library. This may change in a future version.

== Screenshots ==

1. The Mulino file show admin screen, with the folder tree and drag-and-drop upload area.
2. The public folder browser shown by the `[mulino_documents]` shortcode.

== Changelog ==

= 1.2.1 =
* New "Choose files" button in the upload area, so documents can also be uploaded from phones and tablets, where dragging and dropping isn't possible, and with the keyboard.
* On phones and tablets, the folder tree is now shown above the documents, and the tick boxes and the rename and delete buttons are always visible (they used to appear only when hovering with a mouse).

= 1.2.0 =
* New: import from Simple File List under File Show > Import, with subfolders, names, descriptions and dates. See the FAQ.
* New: drag a whole folder from your computer onto the upload area to upload it with all its subfolders. Folders that already exist are reused.
* New: tick several documents to move them to another folder or to the trash in one go. Dragging one of the ticked documents onto a folder moves all of them.
* New: "Export as ZIP" downloads the whole library with its folders.
* Renaming or moving a folder no longer allows two folders with the same name side by side.
* The rename buttons now use WordPress' own pencil icon, which is easier to recognise.
* In the admin screen, "All" is now called "Top level", because it shows the documents that aren't in any folder, not all documents.
* New action for developers: `mulino_after_import`.

= 1.1.0 =
* New shortcode options: `folder`, `folder_order`, `orderby`, `document_order` and `hide_empty`. See the FAQ.
* Folders and documents are now sorted naturally ("Minutes 2" before "Minutes 10"), both on the site and in the admin screen.
* Documents that aren't in any folder are now shown at the top of the library on the site, just like under "All" in the admin screen.
* Uploads now run one file at a time with a progress bar. Files larger than your web host's limit are caught before they're sent, and all problems are listed together instead of in one pop-up per file.
* New setting under Settings > Media: delete all documents and folders when the plugin is deleted (off by default).
* New filter for developers: `mulino_frontend_document_query_args`.

= 1.0.2 =
* All code identifiers now use the longer, more distinctive prefix `mulino` instead of `mfs` (functions, constants, hooks, AJAX actions, post type, taxonomy, shortcode, CSS classes and script handles). The shortcode is now `[mulino_documents]`, and add-ons must use the new hook names.

= 1.0.1 =
* Internal cleanup: a few leftover asset handles and one icon CSS class from an earlier plugin name had never been fully renamed. They are harmless in isolation but were replaced for consistency and to remove any theoretical collision risk with another plugin.

= 1.0.0 =
* First release under the name Mulino file show: nested folders, drag-and-drop admin manager, rename/delete support for both folders and documents, frontend shortcode, and extension hooks for building add-ons.
