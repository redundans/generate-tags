<?php
/**
 * Gutenberg plugin that adds a PluginDocumentSettingPanel for generate tags function.
 *
 * @package generate-tags
 */

/**
 * Registers all block assets so that they can be enqueued through the block editor
 * in the corresponding context.
 *
 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */
add_action(
	'enqueue_block_editor_assets',
	function(): void {
		$screen = get_current_screen();
		$dir    = __DIR__;

		// Do only show for posts.
		if ( 'post' !== $screen->post_type ) {
			return;
		}

		$script_asset_path = "$dir/build/index.asset.php";
		if ( ! file_exists( $script_asset_path ) ) {
			throw new Error(
				'You need to run `npm start` or `npm run build` for the "create-block/content-variants" block first.'
			);
		}
		$index_js     = 'build/index.js';
		$script_asset = require $script_asset_path;
		wp_enqueue_script(
			'create-block-content-variants-block-editor',
			plugin_dir_url( __FILE__ ) . '/' . $index_js,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		$editor_css = 'build/index.css';
		wp_enqueue_style(
			'create-block-content-variants-block-editor',
			plugin_dir_url( __FILE__ ) . '/' . $editor_css,
			[],
			filemtime( "$dir/$editor_css" )
		);
	},
	999 // Intentionally high number to make this plugin appear last in the document settings panel.
);
