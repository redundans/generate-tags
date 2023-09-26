import { registerPlugin } from '@wordpress/plugins';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';

import './index.scss';

const { select } = wp.data;

const doOnClick = () => {
	const { editPost } = dispatch( 'core/editor' );
	const content = wp.data.select( "core/editor" ).getEditedPostAttribute('content');

	wp.data.dispatch( 'core/editor' ).lockPostSaving( 'generate-tags' );
	apiFetch( {
		path: '/generate-tags/v1/content',
		method: 'POST',
		data: { content: content },
	} ).then( ( new_tags ) => {
		if ( new_tags.code ) {
			alert( new_tags.message );
		} else {
			let tags = select( 'core/editor' ).getEditedPostAttribute( 'tags' );
			let combined_tags = tags.concat(new_tags);
			dispatch( 'core/editor' ).editPost( { 'tags': combined_tags } );
		}
		wp.data.dispatch( 'core/editor' ).unlockPostSaving( 'generate-tags' );
	} );
};

const GenerateContentSidebar = () => (
	<PluginPostStatusInfo className="edit-post-post-content-variant">
		<button onClick={ doOnClick }>Fetch tags</button>
	</PluginPostStatusInfo>
);

registerPlugin( 'generate-tags', {
	render: GenerateContentSidebar,
	icon: '',
} );
