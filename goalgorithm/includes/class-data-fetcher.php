<?php
/**
 * Data Fetcher - fetches xG/xGA data from Understat.com JSON API and caches via WordPress transients.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_Data_Fetcher {

	const UNDERSTAT_BASE_URL = 'https://understat.com/getLeagueData/';

	// Keep backward-compatible numeric league IDs for shortcode usage
	const LEAGUES = [
		'9'  => 'Premier League',
		'12' => 'La Liga',
		'11' => 'Serie A',
		'20' => 'Bundesliga',
		'13' => 'Ligue 1',
	];

	const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

	// Map numeric league IDs to Understat URL slugs
	private static $understat_slugs = [
		'9'  => 'EPL',
		'12' => 'La_liga',
		'11' => 'Serie_A',
		'20' => 'Bundesliga',
		'13' => 'Ligue_1',
	];

	/**
	 * Get league data from cache or fresh fetch.
	 *
	 * @param string $league_id Numeric league ID.
	 * @return array|WP_Error Team data array keyed by team name, or error.
	 */
	public function get_league_data( $league_id ) {
		if ( ! isset( self::LEAGUES[ $league_id ] ) ) {
			return new WP_Error( 'invalid_league', 'Unsupported league ID: ' . $league_id );
		}

		$cache_key = 'goalgorithm_league_' . $league_id . '_' . $this->get_current_season();
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->fetch_from_understat( $league_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_team_data', 'No team data available for this league/season.' );
		}

		$ttl = (int) get_option( 'goalgorithm_cache_duration', 12 );
		set_transient( $cache_key, $data, $ttl * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Fetch team xG data from Understat JSON API and aggregate per-90 stats.
	 *
	 * @param string $league_id Numeric league ID.
	 * @return array|WP_Error Aggregated team data or error.
	 */
	private function fetch_from_understat( $league_id ) {
		$slug   = self::$understat_slugs[ $league_id ] ?? '';
		$season = $this->get_current_season();
		$url    = self::UNDERSTAT_BASE_URL . $slug . '/' . $season;

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => self::USER_AGENT,
			'headers'    => [
				'X-Requested-With' => 'XMLHttpRequest',
				'Accept'           => 'application/json',
				'Accept-Encoding'  => 'gzip, deflate',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'understat_http_error', sprintf( 'Understat returned HTTP %d', $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! is_array( $json ) || empty( $json['teams'] ) ) {
			return new WP_Error( 'understat_parse_error', 'Could not parse Understat response.' );
		}

		return $this->aggregate_team_stats( $json['teams'] );
	}

	/**
	 * Aggregate per-match xG/xGA into per-90 averages for each team.
	 * Understat provides per-match history with xG and xGA fields.
	 *
	 * @param array $teams Raw teams data from Understat JSON.
	 * @return array Team data keyed by name: ['xg_per90', 'xga_per90', 'xg_total', 'xga_total', 'mp'].
	 */
	private function aggregate_team_stats( $teams ) {
		$result = [];

		foreach ( $teams as $team ) {
			$title   = $team['title'] ?? '';
			$history = $team['history'] ?? [];

			if ( empty( $title ) || empty( $history ) ) {
				continue;
			}

			$total_xg  = 0;
			$total_xga = 0;
			$mp        = count( $history );

			foreach ( $history as $match ) {
				$total_xg  += (float) ( $match['xG'] ?? 0 );
				$total_xga += (float) ( $match['xGA'] ?? 0 );
			}

			$result[ $title ] = [
				'xg_per90'  => $mp > 0 ? round( $total_xg / $mp, 3 ) : 0,
				'xga_per90' => $mp > 0 ? round( $total_xga / $mp, 3 ) : 0,
				'xg_total'  => round( $total_xg, 3 ),
				'xga_total' => round( $total_xga, 3 ),
				'mp'        => $mp,
			];
		}

		return $result;
	}

	/**
	 * Determine current football season start year.
	 * European leagues run Aug-May: if current month >= Aug, season = this year; else last year.
	 *
	 * @return int Season start year (e.g., 2024 for the 2024/2025 season).
	 */
	private function get_current_season() {
		$month = (int) gmdate( 'n' );
		$year  = (int) gmdate( 'Y' );
		return ( $month >= 8 ) ? $year : $year - 1;
	}

	/**
	 * Calculate league average xG/90 and xGA/90 across all teams.
	 *
	 * @param array $league_data Output from get_league_data().
	 * @return array Averages with fallback defaults.
	 */
	public function get_league_averages( $league_data ) {
		if ( empty( $league_data ) || is_wp_error( $league_data ) ) {
			return [ 'avg_xg_per90' => 1.3, 'avg_xga_per90' => 1.3 ];
		}

		$total_xg  = 0;
		$total_xga = 0;
		$count     = count( $league_data );

		foreach ( $league_data as $team ) {
			$total_xg  += $team['xg_per90'] ?? 0;
			$total_xga += $team['xga_per90'] ?? 0;
		}

		return [
			'avg_xg_per90'  => $count > 0 ? round( $total_xg / $count, 4 ) : 1.3,
			'avg_xga_per90' => $count > 0 ? round( $total_xga / $count, 4 ) : 1.3,
		];
	}

	/** Force refresh cache for a specific league. */
	public function refresh_cache( $league_id ) {
		$season = $this->get_current_season();
		delete_transient( 'goalgorithm_league_' . $league_id . '_' . $season );
		return $this->get_league_data( $league_id );
	}

	/** Clear all plugin caches for current and previous season. */
	public function clear_all_caches() {
		$season = $this->get_current_season();
		foreach ( array_keys( self::LEAGUES ) as $id ) {
			delete_transient( 'goalgorithm_league_' . $id . '_' . $season );
			delete_transient( 'goalgorithm_league_' . $id . '_' . ( $season - 1 ) );
		}
	}
}
