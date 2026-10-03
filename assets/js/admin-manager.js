( function () {
	'use strict';

	var nonce     = mulinoManager.nonce;
	var i18n      = mulinoManager.i18n;
	var dropzone  = document.getElementById( 'mulino-dropzone' );
	var grid      = document.getElementById( 'mulino-file-grid' );
	var tree      = document.getElementById( 'mulino-tree' );

	function postAjax( data ) {
		var formData = new FormData();
		Object.keys( data ).forEach( function ( key ) {
			if ( Array.isArray( data[ key ] ) ) {
				data[ key ].forEach( function ( value ) {
					formData.append( key + '[]', value );
				} );
			} else {
				formData.append( key, data[ key ] );
			}
		} );
		formData.append( 'nonce', nonce );
		return fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: formData } )
			.then( function ( res ) { return res.json(); } );
	}

	// --- Upload via drop zone ---
	[ 'dragenter', 'dragover' ].forEach( function ( evt ) {
		dropzone.addEventListener( evt, function ( e ) {
			e.preventDefault();
			dropzone.classList.add( 'is-dragover' );
		} );
	} );
	[ 'dragleave', 'drop' ].forEach( function ( evt ) {
		dropzone.addEventListener( evt, function () {
			dropzone.classList.remove( 'is-dragover' );
		} );
	} );
	dropzone.addEventListener( 'drop', function ( e ) {
		e.preventDefault();
		var currentFolder = dropzone.getAttribute( 'data-folder-id' ) || '0';
		document.querySelector( '#mulino-upload-status .mulino-upload-errors' ).innerHTML = '';
		collectDropped( e.dataTransfer ).then( function ( dropped ) {
			if ( ! dropped.files.length && ! dropped.dirs.length ) {
				return;
			}
			createFolders( dropped.dirs, currentFolder ).then( function ( folderIds ) {
				var items = dropped.files.map( function ( entry ) {
					return {
						file: entry.file,
						folderId: entry.dir ? folderIds[ entry.dir ] : currentFolder,
						dir: entry.dir
					};
				} );
				uploadFiles( items, currentFolder, dropped.dirs.length > 0 );
			} );
		} );
	} );

	// Files the operating system leaves in folders that nobody means to upload.
	function isJunkFile( name ) {
		return '.' === name.charAt( 0 ) || /^(thumbs\.db|desktop\.ini)$/i.test( name );
	}

	// Turn a drop into a flat list of { file, dir } plus every folder
	// path in it ("Minutes", "Minutes/2024", ...). Whole folders can only
	// be read through the (non-standard but universally supported)
	// webkitGetAsEntry() API, which has to be called while the drop event
	// is still running. Without it, only plain files are uploaded.
	function collectDropped( dataTransfer ) {
		var entries = [];
		if ( dataTransfer.items && dataTransfer.items.length && dataTransfer.items[ 0 ].webkitGetAsEntry ) {
			Array.prototype.forEach.call( dataTransfer.items, function ( item ) {
				var entry = 'file' === item.kind ? item.webkitGetAsEntry() : null;
				if ( entry ) {
					entries.push( entry );
				}
			} );
		}

		if ( ! entries.length ) {
			var plain = Array.prototype.slice.call( dataTransfer.files || [] ).map( function ( file ) {
				return { file: file, dir: '' };
			} );
			return Promise.resolve( { files: plain, dirs: [] } );
		}

		var result = { files: [], dirs: [] };

		function walk( entry, dir ) {
			if ( entry.isFile ) {
				if ( isJunkFile( entry.name ) ) {
					return Promise.resolve();
				}
				return new Promise( function ( resolve ) {
					entry.file( function ( file ) {
						result.files.push( { file: file, dir: dir } );
						resolve();
					}, function () {
						resolve();
					} );
				} );
			}
			if ( ! entry.isDirectory ) {
				return Promise.resolve();
			}

			var path = dir ? dir + '/' + entry.name : entry.name;
			result.dirs.push( path );
			return readAllEntries( entry.createReader() ).then( function ( children ) {
				return children.reduce( function ( chain, child ) {
					return chain.then( function () {
						return walk( child, path );
					} );
				}, Promise.resolve() );
			} );
		}

		return entries.reduce( function ( chain, entry ) {
			return chain.then( function () {
				return walk( entry, '' );
			} );
		}, Promise.resolve() ).then( function () {
			return result;
		} );
	}

	// readEntries() hands out a directory's contents in batches (about
	// 100 at a time in Chrome), so keep calling it until it returns none.
	function readAllEntries( reader ) {
		var all = [];
		return new Promise( function ( resolve ) {
			function readBatch() {
				reader.readEntries( function ( batch ) {
					if ( ! batch.length ) {
						resolve( all );
						return;
					}
					all = all.concat( Array.prototype.slice.call( batch ) );
					readBatch();
				}, function () {
					resolve( all );
				} );
			}
			readBatch();
		} );
	}

	// Create the dropped folders one by one below the open folder, parents
	// first. Resolves to a map from folder path to Mulino folder ID; a
	// path that couldn't be created maps to its nearest created parent.
	function createFolders( dirs, parentId ) {
		var folderIds = {};
		if ( ! dirs.length ) {
			return Promise.resolve( folderIds );
		}

		var status = document.getElementById( 'mulino-upload-status' );
		status.hidden = false;
		status.querySelector( '.mulino-upload-text' ).textContent = i18n.creatingFolders;
		status.querySelector( '.mulino-upload-errors' ).innerHTML = '';

		return dirs.slice().sort().reduce( function ( chain, path ) {
			return chain.then( function () {
				var cut     = path.lastIndexOf( '/' );
				var parent  = cut > -1 ? folderIds[ path.slice( 0, cut ) ] : parentId;
				var name    = cut > -1 ? path.slice( cut + 1 ) : path;
				return postAjax( { action: 'mulino_ensure_folder_path', parent_id: parent, path: name } ).then( function ( json ) {
					if ( json && json.success ) {
						folderIds[ path ] = String( json.data.term_id );
					} else {
						folderIds[ path ] = parent;
						var li = document.createElement( 'li' );
						li.textContent = format( i18n.couldNotCreatePath, path ) + ( json && json.data && json.data.message ? ' ' + json.data.message : '' );
						status.querySelector( '.mulino-upload-errors' ).appendChild( li );
					}
				}, function () {
					folderIds[ path ] = parent;
				} );
			} );
		}, Promise.resolve() ).then( function () {
			return folderIds;
		} );
	}

	// Fill in "%1$s"/"%2$d"-style placeholders in a translated string.
	function format( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var next = 0;
		return str.replace( /%(?:(\d+)\$)?[sd]/g, function ( match, pos ) {
			return String( args[ pos ? pos - 1 : next++ ] );
		} );
	}

	// Upload one file at a time, so dropping fifty files doesn't fire
	// fifty requests at the web host at once, and so the progress bar
	// and the list of failures can be kept in one place.
	// items: [ { file, folderId } ]. Only cards for files that land in
	// the open folder are added to the grid; the rest show up when that
	// folder is opened.
	function uploadFiles( items, currentFolder, refreshFolders ) {
		var status   = document.getElementById( 'mulino-upload-status' );
		var text     = status.querySelector( '.mulino-upload-text' );
		var progress = status.querySelector( '.mulino-upload-progress' );
		var errors   = status.querySelector( '.mulino-upload-errors' );
		var total    = items.length;
		var uploaded = 0;
		var index    = 0;

		status.hidden   = false;
		progress.hidden = false;

		function addError( message ) {
			var li = document.createElement( 'li' );
			li.textContent = message;
			errors.appendChild( li );
		}

		function next() {
			if ( index >= total ) {
				text.textContent = format( i18n.uploadSummary, uploaded, total );
				progress.hidden  = true;
				if ( refreshFolders ) {
					refreshFolderTree();
				}
				return;
			}

			var file     = items[ index ].file;
			var folderId = items[ index ].folderId;
			var label    = items[ index ].dir ? items[ index ].dir + '/' + file.name : file.name;
			text.textContent = format( i18n.uploadingProgress, index + 1, total );
			progress.value   = index / total;

			// Catch files the host will refuse anyway before sending
			// them -- above PHP's post_max_size the request arrives
			// empty and fails with a confusing "-1".
			if ( mulinoManager.maxUploadSize && file.size > mulinoManager.maxUploadSize ) {
				addError( format( i18n.fileTooLarge, label, i18n.maxUploadSizeText ) );
				index++;
				next();
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'mulino_upload' );
			formData.append( 'nonce', nonce );
			formData.append( 'folder_id', folderId );
			formData.append( 'file', file );

			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxurl );
			xhr.upload.addEventListener( 'progress', function ( ev ) {
				if ( ev.lengthComputable ) {
					progress.value = ( index + ev.loaded / ev.total ) / total;
				}
			} );
			xhr.addEventListener( 'load', function () {
				var json = null;
				try {
					json = JSON.parse( xhr.responseText );
				} catch ( err ) {
					json = null;
				}
				if ( json && json.success ) {
					uploaded++;
					if ( String( folderId ) !== String( currentFolder ) ) {
						index++;
						next();
						return;
					}
					var emptyMsg = grid.querySelector( '.mulino-empty' );
					if ( emptyMsg ) {
						emptyMsg.remove();
					}
					var wrapper = document.createElement( 'div' );
					wrapper.innerHTML = json.data.html;
					grid.appendChild( wrapper.firstElementChild );
					updateSelection();
				} else if ( json && json.data && json.data.message ) {
					addError( label + ': ' + json.data.message );
				} else if ( xhr.status >= 400 ) {
					addError( label + ': ' + format( i18n.serverRejected, xhr.status ) );
				} else {
					addError( label + ': ' + i18n.uploadFailed );
				}
				index++;
				next();
			} );
			xhr.addEventListener( 'error', function () {
				addError( label + ': ' + i18n.uploadFailed );
				index++;
				next();
			} );
			xhr.send( formData );
		}

		next();
	}

	// --- Drag existing document cards to move them ---
	grid.addEventListener( 'dragstart', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-manager-card' ) ) {
			return;
		}
		e.target.classList.add( 'is-dragging' );
		// Dragging one of several selected cards moves all of them.
		var ids = selectedIds();
		if ( ids.length > 1 && ids.indexOf( e.target.getAttribute( 'data-doc-id' ) ) > -1 ) {
			e.dataTransfer.setData( 'text/plain', 'docs:' + ids.join( ',' ) );
		} else {
			e.dataTransfer.setData( 'text/plain', 'doc:' + e.target.getAttribute( 'data-doc-id' ) );
		}
	} );
	grid.addEventListener( 'dragend', function ( e ) {
		if ( e.target.classList.contains( 'mulino-manager-card' ) ) {
			e.target.classList.remove( 'is-dragging' );
		}
	} );

	// --- Rename a document ---
	grid.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-rename' ) ) {
			return;
		}
		var card    = e.target.closest( '.mulino-manager-card' );
		var docId   = e.target.getAttribute( 'data-doc-id' );
		var current = card.getAttribute( 'data-doc-name' ) || '';
		var name    = prompt( i18n.renameDocPrompt, current );
		if ( ! name || name === current ) {
			return;
		}
		postAjax( { action: 'mulino_rename_doc', doc_id: docId, name: name } ).then( function ( json ) {
			if ( json.success ) {
				card.setAttribute( 'data-doc-name', name );
				card.querySelector( '.mulino-name' ).textContent = name;
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotRename );
			}
		} );
	} );

	// --- Delete a document ---
	grid.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-delete' ) ) {
			return;
		}
		if ( ! confirm( i18n.deleteDocConfirm ) ) {
			return;
		}
		var docId = e.target.getAttribute( 'data-doc-id' );
		postAjax( { action: 'mulino_delete_doc', doc_id: docId } ).then( function ( json ) {
			if ( json.success ) {
				removeCards( [ docId ] );
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDelete );
			}
		} );
	} );

	// --- Drag existing folders in the tree to re-parent them, and use
	// the tree as drop targets for both documents and folders ---
	tree.addEventListener( 'dragstart', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-tree-row' ) ) {
			return;
		}
		var li = e.target.closest( '.mulino-tree-item' );
		if ( ! li || '0' === li.getAttribute( 'data-term-id' ) ) {
			return; // the "All" root row isn't a real, movable term
		}
		e.target.classList.add( 'is-dragging' );
		e.dataTransfer.setData( 'text/plain', 'folder:' + li.getAttribute( 'data-term-id' ) );
	} );
	tree.addEventListener( 'dragend', function ( e ) {
		if ( e.target.classList.contains( 'mulino-tree-row' ) ) {
			e.target.classList.remove( 'is-dragging' );
		}
	} );

	// Delegated, so folders added to the tree later (see
	// refreshFolderTree) work as drop targets too.
	function treeItemFrom( e ) {
		return e.target.closest ? e.target.closest( '.mulino-tree-item' ) : null;
	}
	tree.addEventListener( 'dragover', function ( e ) {
		var li = treeItemFrom( e );
		if ( ! li ) {
			return;
		}
		e.preventDefault();
		tree.querySelectorAll( '.is-dragover' ).forEach( function ( other ) {
			if ( other !== li ) {
				other.classList.remove( 'is-dragover' );
			}
		} );
		li.classList.add( 'is-dragover' );
	} );
	tree.addEventListener( 'dragleave', function ( e ) {
		var li = treeItemFrom( e );
		if ( li && ! li.contains( e.relatedTarget ) ) {
			li.classList.remove( 'is-dragover' );
		}
	} );
	tree.addEventListener( 'drop', function ( e ) {
		var li = treeItemFrom( e );
		if ( ! li ) {
			return;
		}
		e.preventDefault();
		li.classList.remove( 'is-dragover' );

		var raw = e.dataTransfer.getData( 'text/plain' );
		if ( ! raw || raw.indexOf( ':' ) === -1 ) {
			return;
		}
		var kind         = raw.slice( 0, raw.indexOf( ':' ) );
		var id           = raw.slice( raw.indexOf( ':' ) + 1 );
		var targetFolder = li.getAttribute( 'data-term-id' );

		if ( 'doc' === kind || 'docs' === kind ) {
			moveDocs( id.split( ',' ), targetFolder );
		} else if ( 'folder' === kind ) {
			if ( id === targetFolder ) {
				return; // dropped a folder onto itself
			}
			postAjax( { action: 'mulino_move_folder', term_id: id, new_parent_id: targetFolder } ).then( function ( json ) {
				if ( json.success ) {
					location.reload();
				} else {
					alert( json.data && json.data.message ? json.data.message : i18n.couldNotMoveFolder );
				}
			} );
		}
	} );

	// Move documents to another folder and take them off the grid.
	function moveDocs( ids, folderId ) {
		if ( String( folderId ) === String( dropzone.getAttribute( 'data-folder-id' ) || '0' ) ) {
			return; // already here
		}
		postAjax( { action: 'mulino_bulk_move', doc_ids: ids, folder_id: folderId } ).then( function ( json ) {
			if ( json.success ) {
				removeCards( json.data.doc_ids.map( String ) );
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotMoveDoc );
			}
		} );
	}

	function removeCards( ids ) {
		ids.forEach( function ( id ) {
			var card = grid.querySelector( '.mulino-manager-card[data-doc-id="' + id + '"]' );
			if ( card ) {
				card.remove();
			}
		} );
		if ( ! grid.querySelector( '.mulino-manager-card' ) && ! grid.querySelector( '.mulino-empty' ) ) {
			grid.innerHTML = '<p class="mulino-empty"></p>';
			grid.querySelector( '.mulino-empty' ).textContent = i18n.noDocuments;
		}
		updateSelection();
	}

	// Re-read the folder tree and the "Move to" list from the server
	// after folders were created by a folder drop, without reloading the
	// page (which would also clear the upload summary).
	function refreshFolderTree() {
		fetch( window.location.href, { credentials: 'same-origin' } )
			.then( function ( res ) { return res.text(); } )
			.then( function ( html ) {
				var doc     = new DOMParser().parseFromString( html, 'text/html' );
				var newTree = doc.getElementById( 'mulino-tree' );
				var newList = doc.getElementById( 'mulino-bulk-folder' );
				var oldList = document.getElementById( 'mulino-bulk-folder' );
				if ( newTree ) {
					tree.innerHTML = newTree.innerHTML;
				}
				if ( newList && oldList ) {
					oldList.innerHTML = newList.innerHTML;
				}
			} );
	}

	// --- Select several documents: move or trash them together ---
	var bulkBar   = document.getElementById( 'mulino-bulk-bar' );
	var selectAll = document.getElementById( 'mulino-select-all' );

	function selectedIds() {
		return Array.prototype.map.call( grid.querySelectorAll( '.mulino-select:checked' ), function ( box ) {
			return box.value;
		} );
	}

	function updateSelection() {
		var boxes = grid.querySelectorAll( '.mulino-select' );
		var count = selectedIds().length;
		bulkBar.classList.toggle( 'has-selection', count > 0 );
		bulkBar.hidden = ! boxes.length;
		document.getElementById( 'mulino-selected-count' ).textContent = count ? format( i18n.selectedCount, count ) : '';
		selectAll.checked       = boxes.length > 0 && count === boxes.length;
		selectAll.indeterminate = count > 0 && count < boxes.length;
		grid.querySelectorAll( '.mulino-manager-card' ).forEach( function ( card ) {
			var box = card.querySelector( '.mulino-select' );
			card.classList.toggle( 'is-selected', !! ( box && box.checked ) );
		} );
	}

	grid.addEventListener( 'change', function ( e ) {
		if ( e.target.classList.contains( 'mulino-select' ) ) {
			updateSelection();
		}
	} );
	selectAll.addEventListener( 'change', function () {
		grid.querySelectorAll( '.mulino-select' ).forEach( function ( box ) {
			box.checked = selectAll.checked;
		} );
		updateSelection();
	} );
	document.getElementById( 'mulino-bulk-move' ).addEventListener( 'click', function () {
		var ids = selectedIds();
		if ( ids.length ) {
			moveDocs( ids, document.getElementById( 'mulino-bulk-folder' ).value );
		}
	} );
	document.getElementById( 'mulino-bulk-delete' ).addEventListener( 'click', function () {
		var ids = selectedIds();
		if ( ! ids.length || ! confirm( 1 === ids.length ? i18n.bulkDeleteConfirmOne : format( i18n.bulkDeleteConfirm, ids.length ) ) ) {
			return;
		}
		postAjax( { action: 'mulino_bulk_delete', doc_ids: ids } ).then( function ( json ) {
			if ( json.success ) {
				removeCards( json.data.doc_ids.map( String ) );
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDelete );
			}
		} );
	} );
	updateSelection();

	// --- New folder ---
	document.getElementById( 'mulino-new-folder' ).addEventListener( 'click', function () {
		var name = prompt( i18n.newFolderPrompt );
		if ( ! name ) {
			return;
		}
		var currentFolderId = dropzone.getAttribute( 'data-folder-id' ) || 0;
		postAjax( { action: 'mulino_create_folder', name: name, parent_id: currentFolderId } ).then( function ( json ) {
			if ( json.success ) {
				location.reload();
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotCreateFolder );
			}
		} );
	} );

	// --- Rename folder ---
	tree.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-tree-rename' ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var li      = e.target.closest( '.mulino-tree-item' );
		var link    = li.querySelector( ':scope > .mulino-tree-row > .mulino-tree-link' );
		var termId  = e.target.getAttribute( 'data-term-id' );
		var current = link.getAttribute( 'data-term-name' ) || link.textContent;
		var name    = prompt( i18n.renameFolderPrompt, current );
		if ( ! name || name === current ) {
			return;
		}
		postAjax( { action: 'mulino_rename_folder', term_id: termId, name: name } ).then( function ( json ) {
			if ( json.success ) {
				link.setAttribute( 'data-term-name', name );
				link.textContent = name;
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotRenameFolder );
			}
		} );
	} );

	// --- Delete folder ---
	tree.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'mulino-tree-delete' ) ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var termId = e.target.getAttribute( 'data-term-id' );
		if ( ! confirm( i18n.deleteFolderConfirm ) ) {
			return;
		}
		postAjax( { action: 'mulino_delete_folder', term_id: termId } ).then( function ( json ) {
			if ( json.success ) {
				var wasSelected = e.target.closest( '.mulino-tree-item' ).classList.contains( 'is-selected' );
				e.target.closest( '.mulino-tree-item' ).remove();
				if ( wasSelected ) {
					window.location.href = mulinoManager.rootUrl;
				}
			} else {
				alert( json.data && json.data.message ? json.data.message : i18n.couldNotDeleteFolder );
			}
		} );
	} );
} )();
