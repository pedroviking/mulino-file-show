=== Mulino file show ===
Contributors: pedroviking
Tags: documents, files, folders, file manager, document library
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse documents in nested, drag-and-drop-organized folders, shown on your site with a block or shortcode. No custom database tables.

== Description ==

Mulino file show is a lightweight document library for WordPress. Organize
documents into nested folders (Decade > Year, Department > Project, or
any hierarchy you like), manage everything with a drag-and-drop admin
screen, and let visitors browse the same structure on your public site
with a block or a shortcode.

**Admin features**

* Drag and drop files straight from your computer to upload them into a folder, or use the "Choose files" button (also on phones and tablets)
* Drag a whole folder from your computer to upload it with all its subfolders
* Drag existing documents or whole folders to re-file them
* Tick several documents to move them or move them to the trash together
* Export the whole library as a ZIP file, with the folders intact
* Import from Simple File List, which has been closed on WordPress.org
* Rename or delete folders and documents from the same screen
* Give a document a short description, or replace its file with a new version: the link on the site stays the same
* Choose per folder whether everyone or only logged-in users can see it
* Let a board member manage the documents without making them an editor of the whole site: tick "Can manage the document library" on their user profile
* Works with the keyboard and on phones: every action can be done without dragging
* Nested folders of unlimited depth
* Uploads run one file at a time with a progress bar, and files that are too big for your web host are caught before they are sent

**Frontend features**

* A "Document library" block, or the `[mulino_documents]` shortcode, shows a breadcrumb-navigable folder browser
* Show the documents as a grid of icons or as a list with type, size, date and description
* Optional search box, which searches names and descriptions in the open folder and below
* Optional page links for folders with many documents
* Show the whole library to logged-in users only, or hide single folders from visitors who aren't logged in
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
4. Add the **Document library** block (or the `[mulino_documents]`
   shortcode) to any page or post to show the public folder browser.

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

= How do I show the library on a page? =

Add the **Document library** block to the page (search for "documents" in the block inserter) and choose its options in the sidebar. If you use the classic editor or a page builder, add the `[mulino_documents]` shortcode instead. The block and the shortcode show exactly the same thing.

= Which options does the shortcode have? =

The same as the block. All of them are optional:

* `folder="minutes"` starts the browser in the folder with that slug instead of at the top of the library. Visitors can't browse above it, so you can put different folders on different pages.
* `folder_order="desc"` lists folders in reverse order, e.g. the newest year first. Default: `asc`.
* `orderby="date"` sorts documents by upload date instead of by name. Default: `name`.
* `document_order="desc"` reverses the document order, e.g. "Budget 2010" before "Budget 2009". Default: `asc`. (`order` works too.)
* `hide_empty="yes"` hides folders with no documents in them or in any of their subfolders. Default: `no`.
* `layout="list"` shows one line per folder and document instead of a grid of icons. Default: `grid`.
* `details="yes"` shows the file type, size, upload date and description. Default: `yes` in the list, `no` in the grid.
* `search="yes"` adds a search box. Default: `no`.
* `per_page="20"` shows 20 documents at a time, with links to the next pages. Default: `0` (all).
* `logged_in_only="yes"` shows the library to logged-in users only and asks everyone else to log in. Default: `no`.

Example: `[mulino_documents folder="minutes" folder_order="desc" layout="list" search="yes"]`

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

Not by default. Deleting a document moves it to the trash (**File Show > All documents > Trash**), and the file stays in the Media Library. If you want the file deleted too, tick "Also delete its file from the Media Library" under **Settings > Media**. The file, and any older versions of it, is then deleted when the document is deleted permanently from the trash, which WordPress also does by itself after 30 days.

= How do I hide documents from the public? =

Click the pencil next to a folder on the **File Show** screen and choose "Only logged-in users". The folder, its subfolders and their documents are then left out of the library and its search for visitors who aren't logged in, and the links to the documents ask them to log in. To hide the whole library, turn on "Only for logged-in users" on the block, or use `logged_in_only="yes"`.

Note that this hides the documents, it doesn't lock the files: the files are in your normal `wp-content/uploads/` folder, so anyone who already has the direct address of a file can still open it.

= Can I update a document without breaking links to it? =

Yes. Click the pencil on the document and choose a new file under "Replace with a new version". The library links to every document through an address like `example.com/?mulino_document=123`, which always opens the newest file, so links in e-mails and on other pages keep working. The old file stays in the Media Library.

= Who can manage the documents? =

Administrators, editors and authors, plus any user you give access on their profile (**Users > edit the user > "Can manage the document library"**). That way a board member can look after the documents without being able to change the rest of the site.

== Screenshots ==

1. The File Show admin screen: the folder tree, the drag-and-drop upload area and the documents in the open folder.
2. The library on the site in the list layout, with search, file type, size, date and descriptions.
3. The same library in the grid layout.
4. Editing a folder: name, place in the tree, and whether everyone or only logged-in users can see it.
5. Editing a document: name, description, and replacing the file with a new version without breaking links.
6. The Document library block and its settings in the block editor.

== Changelog ==

= 1.3.2 =
* For developers: new filter `mulino_frontend_folder_actions` to add buttons or a small form below the breadcrumb of the library on the site (see HOOKS.md on GitHub).

= 1.3.1 =
* Fixed: the Document library block now looks the same in the block editor as on the site (the stylesheet was missing in the editor).
* New screenshots.

= 1.3.0 =
* New: a "Document library" block with the same options as the shortcode.
* New: list layout, and details under each document: file type, size, upload date and description. Shortcode options `layout` and `details`.
* New: a search box that searches document names and descriptions (`search="yes"`).
* New: page links for folders with many documents (`per_page`).
* New: descriptions for documents. Descriptions imported from Simple File List are shown too.
* New: replace a document's file with a new version. The document keeps its name, folder and link.
* New: choose per folder who can see it on the site: everyone or only logged-in users. The whole library can also be shown to logged-in users only (`logged_in_only="yes"`).
* New: a setting under Settings > Media to delete a document's file from the Media Library when the document is deleted permanently.
* New: the capability `manage_mulino_documents`, so a user can be given access to the documents on their profile without being an editor. Every role that could upload files and manage documents before (normally administrator, editor and author) gets it automatically. Contributors no longer have access.
* New: "All documents" under File Show, with WordPress' list of documents and the trash.
* Changed: the documents on the site now link to `?mulino_document=ID`, which forwards to the file, so links keep working when a file is replaced.
* Changed: renaming and moving a folder now happens in an "Edit folder" window, which also works with the keyboard and on phones. Dragging still works.
* Accessibility: buttons have names for screen readers, the breadcrumb and page links are marked up as navigation, the file type is read out, and hidden buttons appear when they get keyboard focus.
* New hooks for developers: `mulino_user_can_view_folder`, `mulino_user_can_view_document`, `mulino_folder_visibility_options`, `mulino_document_url`, `mulino_before_serve_document`, `mulino_after_replace_file`, `mulino_after_description_changed` and `mulino_after_folder_visibility_changed`. See HOOKS.md.

= 1.2.1 =
* New "Choose files" button in the upload area, so documents can also be uploaded from phones and tablets, where dragging and dropping isn't possible, and with the keyboard.
* Uploading a large photo (for example straight from a phone) could fail with "HTTP 503" on some web hosts, leaving the photo in the Media Library but no document. Mulino now stores uploaded and imported files as they are, without WordPress' extra thumbnail sizes, which the library doesn't use.
* The upload error for "HTTP 5xx" no longer blames the file size; it explains that the server stopped and that the file may be in the Media Library.
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
