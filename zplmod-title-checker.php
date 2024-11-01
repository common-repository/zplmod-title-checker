<?php

/**
* Plugin Name: ZPLMOd - Title Checker
* Description: A simple plugin that checks the title of any post, page or custom post type to ensure it is unique title, provides alert message for prevent duplicate post title, publish unique post title when adding new post and does not hurt SEO.
* Version: 1.7.24
* Author: <Strong>ZPLMOd</Strong>
* Author URI: https://github.com/naksheth
* Plugin URI: https://github.com/Naksheth/ZPLMOd_Title-Checker
* License: GPLv3
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
**/

add_action(	'plugins_loaded', array( ZPLMOd_Title_Checker::get_instance(), 'plugin_setup' ) );

class ZPLMOd_Title_Checker {

	protected static $instance = null;
	public $plugin_url = '';
	public $plugin_path = '';
	public $nonce_action = 'zplmod_title_check_nonce';
	public $ajax_nonce = '';
	public $post_title = '';

	public static function get_instance() {	null === self::$instance and self::$instance = new self; return self::$instance; }

	public function plugin_setup() {
		$this->plugin_url  = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_zplmod_title_check', array( $this, 'zplmod_title_check' ) );
		add_filter( 'admin_notices', array( $this, 'zplmod_tc_admin_notice' ) );
		$this->ajax_nonce = wp_create_nonce( $this->nonce_action );
	}

	public function __construct() {  }

	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'zplmod_title_checker', plugins_url( 'js/zplmod-title-checker.js', __FILE__ ), 'jquery', false, true );
		wp_localize_script( 'zplmod_title_checker', 'zplmod_title_checker', array( 'nonce' => $this->ajax_nonce ) );
		wp_enqueue_script( 'zplmod_title_checker' );
	}

	public function zplmod_title_check() {
		check_ajax_referer( $this->nonce_action, 'ajax_nonce' );
		$args = wp_parse_args(
			$_REQUEST,
			array( 'action', 'ajax_nonce', 'post__not_in', 'post_type',	'post_title', )
		);
		$response = $this->check_uniqueness( $args );
		echo wp_json_encode( $response );
		die();
	}

	public function zplmod_tc_admin_notice() {
		global $post, $pagenow;
		if ( 'post.php' !== $pagenow ) {
			return;
		}
		if ( empty( $post->post_title ) ) {
			return;
		}
		$args = array(
			'post__not_in' => array( $post->ID ),
			'post_type'    => $post->post_type,
			'post_title'   => $post->post_title,
		);
		$response = $this->check_uniqueness( $args );
		if ( 'error' !== $response['status'] ) {
			return;
		}
		echo '<div id="zplmod-title-message" class="' . esc_attr( $response['status'] ) . '"><p>' . esc_html( $response['message'] ) . '</p></div>';
	}

	public function check_uniqueness( $args ) {
		add_filter( 'posts_where', array( $this, 'post_title_where' ), 10, 1 );
		$args = apply_filters( 'zplmod_title_checker_arguments', $args );

		if ( $post_type_object = get_post_type_object( $args['post_type'] ) ) {
			$post_type_singular_name = $post_type_object->labels->singular_name;
			$post_type_name          = $post_type_object->labels->name;
		} else {
			$post_type_singular_name = __( 'post', 'zplmod-title-checker' );
			$post_type_name = __( 'posts', 'zplmod-title-checker' );
		}

		$this->post_title = $args['post_title'];
		$query = new WP_Query( $args );
		$posts_count = $query->post_count;

		if ( empty( $posts_count ) ) {
			$response = array(
				'message' => __( '<span class="dashicons dashicons-yes" style="color:#46b450;"></span> The chosen title is unique.', 'unique-title-checker' ),
				'status'  => 'updated',
			);
		} else {
			$response = array(
				'message' => sprintf( _n( '<span class="dashicons dashicons-no" style="color:#dc3232;"></span> There is 1 %2$s with the same title!', '<span class="dashicons dashicons-no" style="color: #dc3232;"></span> There are %1$d other %3$s with the same title!', $posts_count, 'unique-title-checker' ), $posts_count, $post_type_singular_name, $post_type_name ),
				'status'  => 'error',
			);
		}

		remove_filter( 'posts_where', array( $this, 'post_title_where' ), 10 );
		return $response;
	}

	public function post_title_where( $where ) {
		global $wpdb;
		return $where . " AND $wpdb->posts.post_title = '" . esc_sql( $this->post_title ) . "'";
	}
}