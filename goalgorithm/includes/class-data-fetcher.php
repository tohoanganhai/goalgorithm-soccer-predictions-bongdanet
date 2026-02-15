<?php
/**
 * Data Fetcher - fetches xG/xGA data from fbref.com and caches via WordPress transients.
 * HTML parsing delegated to GoalGorithm_FBRef_HTML_Parser.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_Data_Fetcher {

	const FBREF_BASE_URL = 'https://fbref.com/en/comps/';

	const LEAGUES = [
		'9'  => 'Premier League',
		'12' => 'La Liga',
		'11' => 'Serie A',
		'20' => 'Bundesliga',
		'13' => 'Ligue 1',
		'22' => 'Major League Soccer',
	];

	const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

	private static $league_slugs = [
		'9'  => 'Premier-League',
		'12' => 'La-Liga',
		'11' => 'Serie-A',
		'20' => 'Bundesliga',
		'13' => 'Ligue-1',
		'22' => 'Major-League-Soccer',
	];

	/**
	 * Get league data from cache or fresh fetch.
	 *
	 * @param string $league_id FBRef league ID.
	 * @return array|WP_Error Team data array or error.
	 */
	public function get_league_data( $league_id ) {
		if ( ! isset( self::LEAGUES[ $league_id ] ) ) {
			return new WP_Error( 'invalid_league', 'Unsupported league ID: ' . $league_id );
		}

		$cache_key = 'goalgorithm_league_' . $league_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->fetch_from_fbref( $league_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$ttl = (int) get_option( 'goalgorithm_cache_duration', 12 );
		set_transient( $cache_key, $data, $ttl * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Fetch FBRef page and delegate HTML parsing.
	 *
	 * @param string $league_id FBRef league ID.
	 * @return array|WP_Error Parsed team data or error.
	 */
	private function fetch_from_fbref( $league_id ) {
		$slug = self::$league_slugs[ $league_id ] ?? 'Stats';
		$url  = self::FBREF_BASE_URL . $league_id . '/' . $slug . '-Stats';

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => self::USER_AGENT,
			'headers'    => [
				'Accept'          => 'text/html,application/xhtml+xml',
				'Accept-Language' => 'en-US,en;q=0.9',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'fbref_http_error', sprintf( 'FBRef returned HTTP %d', $code ) );
		}

		$parser = new GoalGorithm_FBRef_HTML_Parser();
		return $parser->parse( wp_remote_retrieve_body( $response ) );
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
			$total_xg  += $team['xg_per90'];
			$total_xga += $team['xga_per90'];
		}

		return [
			'avg_xg_per90'  => $count > 0 ? round( $total_xg / $count, 4 ) : 1.3,
			'avg_xga_per90' => $count > 0 ? round( $total_xga / $count, 4 ) : 1.3,
		];
	}

	/** Force refresh cache for a specific league. */
	public function refresh_cache( $league_id ) {
		delete_transient( 'goalgorithm_league_' . $league_id );
		return $this->get_league_data( $league_id );
	}

	/** Clear all plugin caches. */
	public function clear_all_caches() {
		foreach ( array_keys( self::LEAGUES ) as $id ) {
			delete_transient( 'goalgorithm_league_' . $id );
		}
	}
}
