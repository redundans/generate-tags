<?php
/**
 * Generate tags for WordPress
 *
 * @package   generate-tags
 * @link      https://github.com/redundans/generate-tags
 * @author    Jesper Nilsson <jesper@klandestino.se>
 * @license   GPL v2 or later
 *
 * Plugin Name:  Generate Tags
 * Description:  A plugin to use OpenAI's ChatGPT 3.5 to generate tags from post content.
 * Version:      0.1
 * Plugin URI:   https://github.com/redundans/generate-tags
 * Author:       Jesper Nilsson
 * Author URI:   https://github.com/redundans/
 * Text Domain:  generate-tags
 * Requires PHP: 7.4
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/editor/generate-tags/generate-tags.php';

use Orhanerday\OpenAi\OpenAi;

/**
 * Function to generate tags from post content.
 *
 * @param string $post_content The post content.
 */
function generate_tags_from_content( $post_content ) {
	$cached_tags = get_transient( 'ddtags_from_' . md5( $post_content ) );

	if ( false !== $cached_tags ) {
		return $cached_tags;
	}

	$open_ai_key = getenv( 'OPENAI_API_KEY' );
	if ( empty( $open_ai_key ) ) {
		return new WP_Error( 'no_api_key', 'No API key found.', array( 'status' => 200 ) );
	}

	$open_ai     = new OpenAi( $open_ai_key );
	$args        = array(
		'model' => 'gpt-3.5-turbo-16k',
		'messages' => array(
			array(
				'role' => 'user',
				'content' => 'What tags would you suggest me to add to this post? 
				   Give me the response in swedish. Format the response as json.
				   Post is: ' . $post_content,
			),
		),
	);
	$chat = $open_ai->chat( $args );

	$json = json_decode( $chat );
	if ( ! empty( $json->error ) ) {
		return new WP_Error( $json->error->code, $json->error->message, array( 'status' => 200 ) );
	}

	if ( empty( $json->choices[0]->message->content ) ) {
		return new WP_Error( 'empty', 'No tags created.', array( 'status' => 200 ) );
	}

	$tags = json_decode( $json->choices[0]->message->content );
	set_transient( 'tags_from_' . md5( $post_content ), $tags, 3600 );
	return get_transient( 'tags_from_' . md5( $post_content ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'generate-tags/v1',
			'content',
			array(
				'methods'  => 'POST',
				'callback' => function ( $req ) {
					$generate_tags_post_content        = wp_strip_all_tags( $req['content'], true );
					$generate_tags_post_content_16k    = mb_substr( $generate_tags_post_content, 0, 48000 );
					$generate_tags_post_content_result = generate_tags_from_content( $generate_tags_post_content_16k );

					if ( $generate_tags_post_content_result instanceof WP_Error ) {
						return $generate_tags_post_content_result;
					} elseif ( ! empty( $generate_tags_post_content_result->tags ) ) {
						$new_tags = array();
						foreach ( $generate_tags_post_content_result->tags as $tag ) {
							$new_term = get_term_by( 'name', $tag, 'post_tag', ARRAY_A, 'raw' );
							if ( empty( $new_term ) ) {
								$new_term = wp_insert_term( $tag, 'post_tag' );
							}
							$new_tags[] = $new_term['term_id'];
						}
						return $new_tags;
					}
					return new WP_Error( 'empty', 'No tags created.', array( 'status' => 200 ) );
				},
			)
		);
	}
);
