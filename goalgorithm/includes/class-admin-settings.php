<?php
/**
 * Admin Settings - WordPress settings page for GoalGorithm plugin configuration.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_Admin_Settings {

	const OPTION_GROUP = 'goalgorithm_settings';
	const PAGE_SLUG    = 'goalgorithm-settings';

	/** Register admin menu page under Settings. */
	public function add_menu_page() {
		add_options_page(
			'GoalGorithm Settings',
			'GoalGorithm',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/** Register settings, sections, and fields with WordPress Settings API. */
	public function register_settings() {
		register_setting( self::OPTION_GROUP, 'goalgorithm_default_league', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_league' ],
			'default'           => '9',
		] );

		register_setting( self::OPTION_GROUP, 'goalgorithm_cache_duration', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 12,
		] );

		add_settings_section( 'goalgorithm_general', 'General Settings', null, self::PAGE_SLUG );

		add_settings_field(
			'goalgorithm_default_league',
			'Default League',
			[ $this, 'render_league_field' ],
			self::PAGE_SLUG,
			'goalgorithm_general'
		);

		add_settings_field(
			'goalgorithm_cache_duration',
			'Cache Duration (hours)',
			[ $this, 'render_cache_field' ],
			self::PAGE_SLUG,
			'goalgorithm_general'
		);
	}

	/** Render league dropdown field. */
	public function render_league_field() {
		$current = get_option( 'goalgorithm_default_league', '9' );
		$leagues = GoalGorithm_Data_Fetcher::LEAGUES;

		echo '<select name="goalgorithm_default_league">';
		foreach ( $leagues as $id => $name ) {
			$selected = selected( $current, $id, false );
			echo '<option value="' . esc_attr( $id ) . '" ' . $selected . '>'
				. esc_html( $name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">Default league when [goalgorithm] shortcode has no league attribute.</p>';
	}

	/** Render cache duration number input. */
	public function render_cache_field() {
		$value = get_option( 'goalgorithm_cache_duration', 12 );
		echo '<input type="number" name="goalgorithm_cache_duration" '
			. 'value="' . esc_attr( $value ) . '" min="1" max="72" step="1" />'
			. '<p class="description">How long to cache Understat data (1-72 hours). Default: 12.</p>';
	}

	/** Render the full settings page with form and data management. */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_refresh_action();

		echo '<div class="wrap">';
		echo '<h1>GoalGorithm Settings</h1>';

		// Settings form
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button( 'Save Settings' );
		echo '</form>';

		// Manual refresh section
		echo '<hr /><h2>Data Management</h2>';
		echo '<p>Manually refresh cached league data from Understat.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'goalgorithm_refresh', 'goalgorithm_refresh_nonce' );

		echo '<select name="goalgorithm_refresh_league">';
		echo '<option value="all">All Leagues</option>';
		foreach ( GoalGorithm_Data_Fetcher::LEAGUES as $id => $name ) {
			echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $name ) . '</option>';
		}
		echo '</select> ';
		submit_button( 'Refresh Data', 'secondary', 'goalgorithm_do_refresh', false );
		echo '</form>';

		$this->render_cache_status();

		// Usage instructions
		echo '<hr /><h2>Shortcode Usage</h2>';
		echo '<code>[goalgorithm home="Arsenal" away="Chelsea"]</code><br>';
		echo '<code>[goalgorithm league="12" home="Barcelona" away="Real Madrid"]</code>';

		echo '</div>';
	}

	/** Process manual data refresh with nonce verification. */
	private function handle_refresh_action() {
		if ( ! isset( $_POST['goalgorithm_do_refresh'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['goalgorithm_refresh_nonce'] ?? '', 'goalgorithm_refresh' ) ) {
			add_settings_error( self::PAGE_SLUG, 'nonce_fail', 'Security check failed.', 'error' );
			settings_errors( self::PAGE_SLUG );
			return;
		}

		$league  = sanitize_text_field( $_POST['goalgorithm_refresh_league'] ?? 'all' );
		$fetcher = new GoalGorithm_Data_Fetcher();

		if ( 'all' === $league ) {
			$fetcher->clear_all_caches();
			foreach ( array_keys( GoalGorithm_Data_Fetcher::LEAGUES ) as $id ) {
				$result = $fetcher->get_league_data( $id );
				if ( is_wp_error( $result ) ) {
					add_settings_error( self::PAGE_SLUG, 'refresh_error',
						'Error refreshing league ' . $id . ': ' . $result->get_error_message(), 'error' );
				}
				sleep( 1 ); // Brief pause between requests
			}
			add_settings_error( self::PAGE_SLUG, 'refresh_ok', 'All leagues refreshed.', 'success' );
		} else {
			$result = $fetcher->refresh_cache( $league );
			if ( is_wp_error( $result ) ) {
				add_settings_error( self::PAGE_SLUG, 'refresh_error', 'Error: ' . $result->get_error_message(), 'error' );
			} else {
				$name = GoalGorithm_Data_Fetcher::LEAGUES[ $league ] ?? $league;
				add_settings_error( self::PAGE_SLUG, 'refresh_ok', $name . ' data refreshed.', 'success' );
			}
		}

		settings_errors( self::PAGE_SLUG );
	}

	/** Display cache status table for all leagues. */
	private function render_cache_status() {
		echo '<h3>Cache Status</h3>';
		echo '<table class="widefat fixed striped"><thead><tr>';
		echo '<th>League</th><th>Status</th><th>Teams</th>';
		echo '</tr></thead><tbody>';

		$month  = (int) gmdate( 'n' );
		$year   = (int) gmdate( 'Y' );
		$season = ( $month >= 8 ) ? $year : $year - 1;

		foreach ( GoalGorithm_Data_Fetcher::LEAGUES as $id => $name ) {
			$cached = get_transient( 'goalgorithm_league_' . $id . '_' . $season );
			$status = ( false !== $cached ) ? 'Cached' : 'Not cached';
			$count  = is_array( $cached ) ? count( $cached ) : 0;

			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '<td>' . esc_html( $count ) . ' teams</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/** Sanitize league ID against allowed values. */
	public function sanitize_league( $value ) {
		$allowed = array_keys( GoalGorithm_Data_Fetcher::LEAGUES );
		return in_array( $value, $allowed, true ) ? $value : '9';
	}
}
