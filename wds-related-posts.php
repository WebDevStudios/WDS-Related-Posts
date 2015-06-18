<?php
/**
 * Plugin Name: WebDevStudios Related Posts
 * Plugin URI: http://webdevstudios.com
 * Description: A lightweight plugin to display related posts based on categories.
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * Version: 1.0.0
 * License: GPLv2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WDS_Related_Posts' ) ) {

	class WDS_Related_Posts extends WP_Widget {


		/**
		 * Unique identifier for this widget.
		 *
		 * Will also serve as the widget class.
		 *
		 * @var string
		 */
		protected $widget_slug = 'wds-related-posts-widget';


		/**
		 * Widget name displayed in Widgets dashboard.
		 * Set in __construct since __() shouldn't take a variable.
		 *
		 * @var string
		 */
		protected $widget_name = '(WDS) Related Posts';


		/**
		 * Default widget title displayed in Widgets dashboard.
		 * Set in __construct since __() shouldn't take a variable.
		 *
		 * @var string
		 */
		protected $default_widget_title = 'Related Posts';


		/**
		 * Shortcode name for this widget
		 *
		 * @var string
		 */
		protected static $shortcode = 'wds_related_posts';


		/**
		 * Contruct widget.
		 */
		public function __construct() {

			$this->widget_name          = esc_html__( 'Related Posts (WDS)', 'text-domain' );
			$this->default_widget_title = esc_html__( 'Related Posts', 'text-domain' );

			parent::__construct(
				$this->widget_slug,
				$this->widget_name,
				array(
					'classname'   => $this->widget_slug,
					'description' => esc_html__( 'A widget to display related posts.', 'text-domain' ),
				)
			);

			add_action( 'save_post',    array( $this, 'flush_widget_cache' ) );
			add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
			add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
			add_shortcode( self::$shortcode, array( __CLASS__, 'get_widget' ) );
		}


		/**
		 * Delete this widget's cache.
		 *
		 * Note: Could also delete any transients
		 * delete_transient( 'some-transient-generated-by-this-widget' );
		 */
		public function flush_widget_cache() {
			wp_cache_delete( $this->widget_slug, 'widget' );
		}


		/**
		 * Front-end display of widget.
		 *
		 * @param  array  $args      The widget arguments set up when a sidebar is registered.
		 * @param  array  $instance  The widget settings as set by user.
		 */
		public function widget( $args, $instance ) {

			echo self::get_widget( array(
				'before_widget' => $args['before_widget'],
				'after_widget'  => $args['after_widget'],
				'before_title'  => $args['before_title'],
				'after_title'   => $args['after_title'],
				'title'         => $instance['title'],
				'number'        => $instance['number'],
			) );

		}


		/**
		 * Return the widget/shortcode output
		 *
		 * @param  array  $atts Array of widget/shortcode attributes/args
		 * @return string       Widget output
		 */
		public static function get_widget( $atts ) {

			// Start the widget string
			$widget = '';

			// Set up default values for attributes
			$atts = shortcode_atts(
				array(
					// Ensure variables
					'before_widget' => '',
					'after_widget'  => '',
					'before_title'  => '',
					'after_title'   => '',
					'title'         => '',
					'number'        => '',
				),
				(array) $atts,
				self::$shortcode
			);

			// Go get some news
			$related_query = self::get_related_posts( false, get_the_ID(), $atts['number'] );

			// Before widget hook
			$widget .= $atts['before_widget'];

			// Title
			$widget .= ( $atts['title'] ) ? $atts['before_title'] . esc_html( $atts['title'] ) . $atts['after_title'] : '';

			// Display the related posts
			$widget .= self::do_related_posts( $related_query );

			// After widget hook
			$widget .= $atts['after_widget'];

			return $widget;
		}


		/**
		 * Update form values as they are saved.
		 *
		 * @param  array  $new_instance  New settings for this instance as input by the user.
		 * @param  array  $old_instance  Old settings for this instance.
		 * @return array  Settings to save or bool false to cancel saving.
		 */
		public function update( $new_instance, $old_instance ) {

			// Previously saved values
			$instance = $old_instance;

			// Sanitize title before saving to database
			$instance['title']  = sanitize_text_field( $new_instance['title'] );
			$instance['number'] = absint( $new_instance['number'] );

			// Flush cache
			$this->flush_widget_cache();

			return $instance;
		}


		/**
		 * Back-end widget form with defaults.
		 *
		 * @param  array  $instance  Current settings.
		 */
		public function form( $instance ) {

			// If there are no settings, set up defaults
			$instance = wp_parse_args( (array) $instance,
				array(
					'title'  => $this->default_widget_title,
					'number' => 4,
				)
			);

			?>

			<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'text-domain' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_html( $instance['title'] ); ?>" placeholder="optional" /></p>

			<p><label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of posts to show:', 'text-domain' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text" value="<?php echo esc_html( $instance['number'] ); ?>" placeholder="4" size="3"/></p>

			<?php
		}


		/**
		 * Display related articles.
		 *
		 * @param  obj    $news_query  The queried object full of related articles.
		 * @return string $widget      HTML markup of news stories.
		 */
		public static function do_related_posts( $related_query ) {

			// Start the widget string
			$widget = '';

			// Set up our counter
			$i = 0;

			if ( $related_query->have_posts() ) :

				$widget .= '<ul class="related-posts-list">';

				while ( $related_query->have_posts() ) : $related_query->the_post();

				++$i;
				$even_odd_class = ( ($i % 2) == 0 ) ? 'even' : 'odd';

					$widget .=	'<li class="item related related-' . $i . ' related-' . $even_odd_class . '">';
					$widget .=		'<span class="posted-on"><time class="entry-date published updated" datetime="' . esc_attr( get_the_date( 'c' ) ) . '"></time>' . esc_html( get_the_date() ). '</span>';
					$widget .=		'<h4 class="entry-title"><a href="' . get_the_permalink() . '">' . get_the_title() . '</a></h4>';
					$widget .=		'<a class="more" href="' . get_the_permalink() . '">' . __( 'Read More', 'text-domain' ) . ' </a>';
					$widget .=	'</li>';

				endwhile;

				$widget .= '</ul>';

				else :

				$widget .= __( 'Sorry, I couldn\'t find any related posts.', 'text-domain' );

				endif;

			wp_reset_postdata();

			return $widget;

		}


		/**
		 * Get related articles from the database based on the post ID.
		 *
		 * @param  boolean  $flush    Maybe flush that transient?
		 * @param  int      $post_ID  The post ID.
		 * @return obj      $related  The post object.
		 */
		public static function get_related_posts( $flush = false, $post_ID, $number, $args = array() ) {

			$defaults = array(
				'taxonomy' => 'category',
				'exclude'  => array(),
				'order'    => 'ASC',
				'orderby'  => 'rand',
			);
			$args = wp_parse_args( $args, $defaults );

			// Be sure the passed post ID is excluded
			$exclude = array( $post_ID );
			$args['exclude'] = array_merge( $exclude, $args['exclude'] );

			// Leave a backdoor for flushing transient
			$flush = isset( $_GET['delete-cache'] ) ? true : $flush;

			// Set transient key
			$cache_key = 'wds_related_posts_' . $post_ID;

			// Attempt to fetch the transient
			$related = wp_cache_get( $cache_key );

			// If we're flushing or there isn't a transient, generate one
			if ( $flush || false === ( $related ) ) {

				$q_args = array(
					'post__not_in'           => $args['exclude'],
					'posts_per_page'         => absint( $number ),
					'order'                  => $args['order'],
					'orderby'                => $args['orderby'],
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				);

				if ( 'tag' == $args['taxonomy'] ) {
					$tags = wp_get_post_tags( $post_ID, array( 'fields' => 'ids' ) );
					$q_args['tag__in'] = $tags;
				} else {
					$cats = wp_get_post_categories( $post_ID, array( 'fields' => 'ids' ) );
					$q_args['category__in'] = $cats;
				}

				$related = new WP_Query( $q_args );

				// Set transient, and expire after a max of 4 hours
				wp_cache_set( $cache_key, $related, 4 * HOUR_IN_SECONDS );
			}

			return $related;
		}
	}
}

/**
 * Register this widget with WordPress.
 */
function register_wds_related_posts_widget() {
	register_widget( 'WDS_Related_Posts' );
}
add_action( 'widgets_init', 'register_wds_related_posts_widget' );

/**
 * Template tag.
 */
function wds_related_posts( $post_id = 0, $args = array() ) {
	if ( ! $post_id ) {
		global $post;
		$post_id = $post->ID;
	}
	$defaults = array(
		'exclude' => array( $post_id ),
		'number'  => 5,
		'flush'   => false,
		'order'    => 'ASC',
		'orderby'  => 'rand',
		'taxonomy' => 'category',
	);
	$args = wp_parse_args( $args,$defaults );
	$query = WDS_Related_Posts::get_related_posts( $args['flush'], $post_id, $args['number'], $args );
	echo WDS_Related_Posts::do_related_posts( $query );
}

function wds_test() {
	global $post;
	$args = array(
		'taxonomy' => 'category'
	);
	wds_related_posts( $post->ID, $args );
}
add_action( 'wp_footer', 'wds_test' );