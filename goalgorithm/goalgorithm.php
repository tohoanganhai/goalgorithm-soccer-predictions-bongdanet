<?php
/**
 * Plugin Name: GoalGorithm - Soccer Predictions
 * Plugin URI:  https://github.com/goalgorithm
 * Description: xG-based soccer match predictions using Poisson distribution model. Use [goalgorithm] shortcode.
 * Version:     1.2.0
 * Author:      GoalGorithm
 * License:     GPL v2 or later
 * Text Domain: goalgorithm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
if ( ! defined( 'GOALGORITHM_VERSION' ) ) {
	define( 'GOALGORITHM_VERSION', '1.2.0' );
	define( 'GOALGORITHM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'GOALGORITHM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Load dependencies
require_once GOALGORITHM_PLUGIN_DIR . 'includes/class-data-fetcher.php';
require_once GOALGORITHM_PLUGIN_DIR . 'includes/class-prediction-engine.php';
require_once GOALGORITHM_PLUGIN_DIR . 'includes/class-shortcode-renderer.php';
require_once GOALGORITHM_PLUGIN_DIR . 'includes/class-league-table-renderer.php';
require_once GOALGORITHM_PLUGIN_DIR . 'includes/class-admin-settings.php';

/**
 * Main plugin class - singleton pattern to prevent multiple initializations.
 */
class GoalGorithm {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/** Register the [goalgorithm] and [goalgorithm_league] shortcodes. */
	public function register_shortcode() {
		$renderer = new GoalGorithm_Shortcode_Renderer();
		add_shortcode( 'goalgorithm', [ $renderer, 'render' ] );

		$league_renderer = new GoalGorithm_League_Table_Renderer();
		add_shortcode( 'goalgorithm_league', [ $league_renderer, 'render' ] );
	}

	/** Enqueue frontend CSS only on pages containing plugin shortcodes. */
	public function enqueue_styles() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && (
			has_shortcode( $post->post_content, 'goalgorithm' )
			|| has_shortcode( $post->post_content, 'goalgorithm_league' )
		) ) {
			wp_enqueue_style(
				'goalgorithm-frontend',
				GOALGORITHM_PLUGIN_URL . 'assets/css/goalgorithm-frontend.css',
				[],
				GOALGORITHM_VERSION
			);
		}
	}

	/** Register admin settings menu. */
	public function register_admin_menu() {
		$admin = new GoalGorithm_Admin_Settings();
		$admin->add_menu_page();
	}

	/** Register admin settings fields. */
	public function register_settings() {
		$admin = new GoalGorithm_Admin_Settings();
		$admin->register_settings();
	}
}

// Activation: set default options
register_activation_hook( __FILE__, function () {
	add_option( 'goalgorithm_default_league', '9' );    // Premier League
	add_option( 'goalgorithm_cache_duration', '12' );  // 12 hours
} );

// Deactivation: clean up transients
register_deactivation_hook( __FILE__, function () {
	$fetcher = new GoalGorithm_Data_Fetcher();
	$fetcher->clear_all_caches();
} );

// Initialize plugin
GoalGorithm::get_instance();
