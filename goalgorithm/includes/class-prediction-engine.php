<?php
/**
 * Prediction Engine - Poisson distribution model for soccer match predictions.
 * Pure math class: no DB, no HTTP, no side effects.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_Prediction_Engine {

	const MAX_GOALS = 5;

	/**
	 * Generate full match prediction.
	 *
	 * @param string $home_name  Home team name.
	 * @param string $away_name  Away team name.
	 * @param array  $league_data All teams data from Data Fetcher.
	 * @param array  $league_avgs League averages ['avg_xg_per90', 'avg_xga_per90'].
	 * @return array|WP_Error Full prediction array or error.
	 */
	public function predict( $home_name, $away_name, $league_data, $league_avgs ) {
		$home = $this->find_team( $home_name, $league_data );
		$away = $this->find_team( $away_name, $league_data );

		if ( ! $home ) {
			return new WP_Error( 'team_not_found', "Home team '{$home_name}' not found in league data." );
		}
		if ( ! $away ) {
			return new WP_Error( 'team_not_found', "Away team '{$away_name}' not found in league data." );
		}

		$expected    = $this->calc_expected_goals( $home['data'], $away['data'], $league_avgs );
		$home_probs  = $this->goal_probabilities( $expected['home_xg'] );
		$away_probs  = $this->goal_probabilities( $expected['away_xg'] );
		$predictions = $this->build_predictions( $home_probs, $away_probs );

		return array_merge( $predictions, [
			'home_team'  => $home['name'],
			'away_team'  => $away['name'],
			'home_xg'    => $expected['home_xg'],
			'away_xg'    => $expected['away_xg'],
			'home_probs' => $home_probs,
			'away_probs' => $away_probs,
		] );
	}

	/**
	 * Calculate expected goals using attack/defense strength model.
	 * Formula: HomeXG = HomeAttack * AwayDefense * LeagueAvg
	 */
	private function calc_expected_goals( $home, $away, $avgs ) {
		$avg_xg  = max( $avgs['avg_xg_per90'], 0.1 );
		$avg_xga = max( $avgs['avg_xga_per90'], 0.1 );

		$home_attack  = $home['xg_per90'] / $avg_xg;
		$away_defense = $away['xga_per90'] / $avg_xga;
		$home_xg      = $home_attack * $away_defense * $avg_xg;

		$away_attack  = $away['xg_per90'] / $avg_xg;
		$home_defense = $home['xga_per90'] / $avg_xga;
		$away_xg      = $away_attack * $home_defense * $avg_xg;

		return [
			'home_xg' => round( $home_xg, 3 ),
			'away_xg' => round( $away_xg, 3 ),
		];
	}

	/**
	 * Generate Poisson probability array for 0 to MAX_GOALS.
	 *
	 * @param float $lambda Expected goals (mean of Poisson distribution).
	 * @return array [0 => P(0), 1 => P(1), ..., 5 => P(5)].
	 */
	private function goal_probabilities( $lambda ) {
		$probs = [];
		for ( $k = 0; $k <= self::MAX_GOALS; $k++ ) {
			$probs[ $k ] = $this->poisson_pmf( $k, $lambda );
		}
		return $probs;
	}

	/**
	 * Numerically stable Poisson probability mass function.
	 * Uses log-space calculation to avoid overflow with factorials.
	 *
	 * @param int   $k      Number of goals (events).
	 * @param float $lambda  Expected goals (rate parameter).
	 * @return float Probability P(X = k).
	 */
	private function poisson_pmf( $k, $lambda ) {
		if ( $lambda <= 0 ) {
			return ( 0 === $k ) ? 1.0 : 0.0;
		}
		// P(k) = exp( k*ln(lambda) - lambda - lgamma(k+1) )
		return exp( $k * log( $lambda ) - $lambda - $this->log_gamma( $k + 1 ) );
	}

	/**
	 * Log-gamma function. Uses PHP's lgamma() if available (PHP 7.2+),
	 * otherwise falls back to Stirling's approximation.
	 */
	private function log_gamma( $n ) {
		if ( function_exists( 'lgamma' ) ) {
			return lgamma( $n );
		}
		// Stirling's approximation fallback
		if ( $n <= 1 ) {
			return 0;
		}
		return 0.5 * log( 2 * M_PI / $n ) + $n * ( log( $n + 1 / ( 12 * $n - 1 / ( 10 * $n ) ) ) - 1 );
	}

	/**
	 * Build 6x6 score probability matrix and derive all match outcomes.
	 *
	 * @param array $home_probs Poisson probabilities for home team goals.
	 * @param array $away_probs Poisson probabilities for away team goals.
	 * @return array Score matrix + derived outcomes (W/D/L, O/U, BTTS, top scores).
	 */
	private function build_predictions( $home_probs, $away_probs ) {
		$matrix   = [];
		$home_win = 0;
		$draw     = 0;
		$away_win = 0;
		$over_25  = 0;
		$btts_yes = 0;
		$scores   = [];

		for ( $h = 0; $h <= self::MAX_GOALS; $h++ ) {
			for ( $a = 0; $a <= self::MAX_GOALS; $a++ ) {
				$prob              = $home_probs[ $h ] * $away_probs[ $a ];
				$matrix[ $h ][ $a ] = round( $prob, 6 );

				if ( $h > $a ) {
					$home_win += $prob;
				} elseif ( $h === $a ) {
					$draw += $prob;
				} else {
					$away_win += $prob;
				}

				if ( ( $h + $a ) > 2 ) {
					$over_25 += $prob;
				}
				if ( $h >= 1 && $a >= 1 ) {
					$btts_yes += $prob;
				}

				$scores[] = [ 'home' => $h, 'away' => $a, 'prob' => $prob ];
			}
		}

		// Sort scores by probability descending for "most likely" display
		usort( $scores, function ( $a, $b ) {
			return $b['prob'] <=> $a['prob'];
		} );

		return [
			'matrix'     => $matrix,
			'home_win'   => round( $home_win * 100, 1 ),
			'draw'       => round( $draw * 100, 1 ),
			'away_win'   => round( $away_win * 100, 1 ),
			'over_25'    => round( $over_25 * 100, 1 ),
			'under_25'   => round( ( 1 - $over_25 ) * 100, 1 ),
			'btts_yes'   => round( $btts_yes * 100, 1 ),
			'btts_no'    => round( ( 1 - $btts_yes ) * 100, 1 ),
			'top_scores' => array_slice( $scores, 0, 3 ),
		];
	}

	/**
	 * Find team in league data using case-insensitive partial match.
	 * Handles variations like "Arsenal" vs "Arsenal FC".
	 *
	 * @param string $name        User-supplied team name.
	 * @param array  $league_data All teams from Data Fetcher.
	 * @return array|null ['name' => string, 'data' => array] or null if not found.
	 */
	private function find_team( $name, $league_data ) {
		$name_lower = strtolower( trim( $name ) );

		// Exact match first
		foreach ( $league_data as $team => $data ) {
			if ( strtolower( $team ) === $name_lower ) {
				return [ 'name' => $team, 'data' => $data ];
			}
		}

		// Partial match fallback
		foreach ( $league_data as $team => $data ) {
			if ( false !== strpos( strtolower( $team ), $name_lower )
				|| false !== strpos( $name_lower, strtolower( $team ) ) ) {
				return [ 'name' => $team, 'data' => $data ];
			}
		}

		return null;
	}
}
