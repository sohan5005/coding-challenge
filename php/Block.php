<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {

		$class_name = isset( $attributes['className'] ) ? $attributes['className'] : ''; // We could set a fallback or default className.
		$post_types = get_post_types( [ 'public' => true ] );

		// This may look redundant for now, but if we want to extend the block further, they can be useful.
		$post_tag       = 'foo';
		$post_cat       = 'baz';
		$post_match_max = 5;
		$post_min_hour  = 9;
		$post_max_hour  = 17;

		$output  = ! empty( $class_name ) ? sprintf( '<div class="%s">', esc_attr( $class_name ) ) : '<div>';
		$output .= sprintf( '<h2>%s</h2>', esc_html__( 'Post Counts', 'site-counts' ) );
		$output .= '<ul>';

		foreach ( $post_types as $post_type_slug ) {
			$post_count = ( new WP_Query(
				[
					'post_type'      => $post_type_slug,
					'fields'         => 'ids', // Consume low memory in case of a lot of posts.
					'posts_per_page' => -1,
					'post_status'    => ( 'attachment' === $post_type_slug ) ? 'inherit' : 'publish',
				]
			) )->post_count;
			
			$output .= sprintf(
				/* translators: 1: Number of posts under post type 2: Name of the post type eg. Posts/Pages */
				'<li>' . esc_html__( 'There are %1$s %2$s', 'site-counts' ) . '</li>',
				esc_html( $post_count ),
				esc_html( get_post_type_object( $post_type_slug )->labels->name )
			);
		}

		$output .= '</ul>';

		/* translators: %s: The unique ID of the post or page currently showing this block (number) */
		$output .= sprintf( '<p>' . esc_html__( 'The current post ID is %s', 'site-counts' ) . '</p>', esc_html( get_the_ID() ) );

		$matching_query = new WP_Query(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'any',
				'date_query'     => [
					[
						'hour'    => $post_min_hour,
						'compare' => '>=',
					],
					[
						'hour'    => $post_max_hour,
						'compare' => '<=',
					],
				],
				'posts_per_page' => $post_match_max + 1, // No need to slice array later, 1 extra limit in case of current post is in this query.
				'fields'         => 'ids', // Look for ids only, later we will use get_the_title() to allow filters.
				'tag'            => $post_tag,
				'category_name'  => $post_cat,
			]
		);

		if ( $matching_query->have_posts() ) {
		
			$matching_query_list = [];

			foreach ( $matching_query->posts as $post_id ) {
				if ( count( $matching_query_list ) > $post_match_max ) {
					break;
				}
				if ( get_the_ID() !== $post_id ) {
					$matching_query_list[] = sprintf( '<li>%s</li>', esc_html( get_the_title( $post_id ) ) );
				}
			}

			$output .= '<h2>';

			$output .= sprintf(
				/* translators: 1: Number of post 2: Tag slug 3: Category slug */
				_nx(
					'%1$s post with the tag of %2$s and the category of %3$s',
					'%1$s posts with the tag of %2$s and the category of %3$s',
					count( $matching_query_list ),
					'Number of posts found with the given tag and category',
					'site-counts'
				),
				number_format_i18n( count( $matching_query_list ) ),
				$post_tag,
				$post_cat
			);

			$output .= '</h2>';

			$output .= '<ul>';

			$output .= implode( '', $matching_query_list );

			$output .= '</ul>';

		}

		$output .= '</div>';

		return $output;

	}
}
