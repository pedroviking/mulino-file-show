/**
 * Editor side of the "Document library" block. Plain JavaScript (no
 * build step): the block is drawn by the server with the same code as
 * the [mulino_documents] shortcode, and the sidebar sets its options.
 */
( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	'use strict';

	var el                = element.createElement;
	var __                = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps     = blockEditor.useBlockProps;
	var PanelBody         = components.PanelBody;
	var SelectControl     = components.SelectControl;
	var ToggleControl     = components.ToggleControl;
	var RangeControl      = components.RangeControl;
	var Disabled          = components.Disabled;
	var data              = window.mulinoBlock || { folders: [] };

	var folderOptions = [ { value: '', label: __( 'The whole library', 'mulino-file-show' ) } ].concat(
		data.folders.map( function ( folder ) {
			return { value: folder.slug, label: folder.label };
		} )
	);

	blocks.registerBlockType( 'mulino/documents', {
		edit: function ( props ) {
			var a   = props.attributes;
			var set = function ( key ) {
				return function ( value ) {
					var change = {};
					change[ key ] = value;
					props.setAttributes( change );
				};
			};

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Content', 'mulino-file-show' ) },
						el( SelectControl, {
							label: __( 'Start in folder', 'mulino-file-show' ),
							help: __( 'Visitors can open the folders below it, but not the ones above.', 'mulino-file-show' ),
							value: a.folder,
							options: folderOptions,
							onChange: set( 'folder' )
						} ),
						el( ToggleControl, {
							label: __( 'Hide empty folders', 'mulino-file-show' ),
							checked: a.hideEmpty,
							onChange: set( 'hideEmpty' )
						} ),
						el( ToggleControl, {
							label: __( 'Only for logged-in users', 'mulino-file-show' ),
							help: __( 'Others are asked to log in. The files themselves can still be opened by anyone who has their direct address.', 'mulino-file-show' ),
							checked: a.loggedInOnly,
							onChange: set( 'loggedInOnly' )
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Appearance', 'mulino-file-show' ) },
						el( SelectControl, {
							label: __( 'Layout', 'mulino-file-show' ),
							value: a.layout,
							options: [
								{ value: 'grid', label: __( 'Grid of icons', 'mulino-file-show' ) },
								{ value: 'list', label: __( 'List', 'mulino-file-show' ) }
							],
							onChange: set( 'layout' )
						} ),
						el( SelectControl, {
							label: __( 'Date, size and description', 'mulino-file-show' ),
							value: a.details,
							options: [
								{ value: '', label: __( 'Automatic (shown in the list)', 'mulino-file-show' ) },
								{ value: 'yes', label: __( 'Show', 'mulino-file-show' ) },
								{ value: 'no', label: __( 'Hide', 'mulino-file-show' ) }
							],
							onChange: set( 'details' )
						} ),
						el( ToggleControl, {
							label: __( 'Search box', 'mulino-file-show' ),
							checked: a.search,
							onChange: set( 'search' )
						} ),
						el( RangeControl, {
							label: __( 'Documents per page (0 = all)', 'mulino-file-show' ),
							value: a.perPage,
							min: 0,
							max: 100,
							onChange: function ( value ) {
								props.setAttributes( { perPage: value || 0 } );
							}
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Sorting', 'mulino-file-show' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Folders', 'mulino-file-show' ),
							value: a.folderOrder,
							options: [
								{ value: 'asc', label: __( 'A to Z, oldest year first', 'mulino-file-show' ) },
								{ value: 'desc', label: __( 'Z to A, newest year first', 'mulino-file-show' ) }
							],
							onChange: set( 'folderOrder' )
						} ),
						el( SelectControl, {
							label: __( 'Sort documents by', 'mulino-file-show' ),
							value: a.orderby,
							options: [
								{ value: 'name', label: __( 'Name', 'mulino-file-show' ) },
								{ value: 'date', label: __( 'Upload date', 'mulino-file-show' ) }
							],
							onChange: set( 'orderby' )
						} ),
						el( SelectControl, {
							label: __( 'Document order', 'mulino-file-show' ),
							value: a.documentOrder,
							options: [
								{ value: 'asc', label: __( 'Ascending', 'mulino-file-show' ) },
								{ value: 'desc', label: __( 'Descending', 'mulino-file-show' ) }
							],
							onChange: set( 'documentOrder' )
						} )
					)
				),
				// Disabled: links in the preview would leave the editor.
				el( Disabled, null, el( ServerSideRender, { block: 'mulino/documents', attributes: a } ) )
			);
		},
		save: function () {
			return null; // drawn on the server
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.serverSideRender );
