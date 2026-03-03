/**
 * @license Copyright (c) 2003-2014, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	config.language = 'es';
	config.extraPlugins = 'youtube';
	//config.extraPlugins = 'maxlength';

	config.toolbarGroups = [
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		{ name: 'forms' },
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'links' },
		{ name: 'insert' },
		{ name: 'styles' },
		{ name: 'colors' },
		{ name: 'tools' },
		{ name: 'others' }
	];

	config.height = 500;

	config.removeButtons = 'Flash,Smiley,SpecialChar,PageBreak,Iframe,Styles,BidiLtr,BidiRtl,Language,Font,Image,Video,Youtube';

	config.filebrowserBrowseUrl = '../cadm/kcfinder/browse.php?opener=ckeditor&type=files';
    config.filebrowserImageBrowseUrl = '../cadm/kcfinder/browse.php?opener=ckeditor&type=images';
    config.filebrowserFlashBrowseUrl = '../cadm/kcfinder/browse.php?opener=ckeditor&type=flash';
    config.filebrowserUploadUrl = '../cadm/kcfinder/upload.php?opener=ckeditor&type=files';
    config.filebrowserImageUploadUrl = '../cadm/kcfinder/upload.php?opener=ckeditor&type=images';
    config.filebrowserFlashUploadUrl = '../cadm/kcfinder/upload.php?opener=ckeditor&type=flash';
	
	//config.allowedContent = true;
};
