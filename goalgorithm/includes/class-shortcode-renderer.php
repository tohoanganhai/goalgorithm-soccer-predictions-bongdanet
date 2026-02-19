<?php
/**
 * Shortcode Renderer - registers [goalgorithm] shortcode and renders prediction card HTML.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_Shortcode_Renderer {

	/**
	 * Render the [goalgorithm] shortcode.
	 * Usage: [goalgorithm home="Arsenal" away="Chelsea" league="9"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output (never echo).
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( [
			'league' => get_option( 'goalgorithm_default_league', '9' ),
			'home'   => '',
			'away'   => '',
		], $atts, 'goalgorithm' );

		$league_id = sanitize_text_field( $atts['league'] );
		$home_name = sanitize_text_field( $atts['home'] );
		$away_name = sanitize_text_field( $atts['away'] );

		if ( empty( $home_name ) || empty( $away_name ) ) {
			return $this->render_error( 'Please specify both home and away teams. Usage: [goalgorithm home="Team A" away="Team B"]' );
		}

		$fetcher     = new GoalGorithm_Data_Fetcher();
		$league_data = $fetcher->get_league_data( $league_id );

		if ( is_wp_error( $league_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GoalGorithm: ' . $league_data->get_error_message() );
			}
			return $this->render_error( 'Could not fetch league data. Please try again later.' );
		}

		$league_avgs = $fetcher->get_league_averages( $league_data );
		$engine      = new GoalGorithm_Prediction_Engine();
		$prediction  = $engine->predict( $home_name, $away_name, $league_data, $league_avgs );

		if ( is_wp_error( $prediction ) ) {
			return $this->render_error( $prediction->get_error_message() );
		}

		return $this->render_card( $prediction );
	}

	/** Render error message box. */
	private function render_error( $message ) {
		return '<div class="goalgorithm-error"><strong>GoalGorithm:</strong> '
			. esc_html( $message ) . '</div>';
	}

	/** Render the full prediction card. */
	private function render_card( $p ) {
		$html  = '<div class="goalgorithm-card">';
		$html .= $this->render_header( $p );
		$html .= $this->render_expected_goals( $p );
		$html .= $this->render_outcome_bar( $p );
		$html .= $this->render_markets( $p );
		$html .= $this->render_top_scores( $p );
		$html .= $this->render_score_grid( $p );
		$html .= '<div class="gg-signature">' . esc_html( GoalGorithm_Translations::get( 'signature' ) ) . ' <a href="https://bongdanet66.com/" target="_blank" rel="noopener">BongdaNET</a></div>';
		$html .= '</div>';
		return $html;
	}

	/** Match header with team names. */
	private function render_header( $p ) {
		return '<div class="goalgorithm-header">'
			. '<span class="goalgorithm-team goalgorithm-home">' . esc_html( $p['home_team'] ) . '</span>'
			. '<span class="goalgorithm-vs">vs</span>'
			. '<span class="goalgorithm-team goalgorithm-away">' . esc_html( $p['away_team'] ) . '</span>'
			. '</div>';
	}

	/** Expected goals display. */
	private function render_expected_goals( $p ) {
		return '<div class="goalgorithm-xg"><div class="goalgorithm-xg-item">'
			. '<span class="goalgorithm-xg-label">' . esc_html( GoalGorithm_Translations::get( 'xg' ) ) . '</span>'
			. '<span class="goalgorithm-xg-value">' . esc_html( $p['home_xg'] ) . '</span>'
			. '<span class="goalgorithm-xg-separator">-</span>'
			. '<span class="goalgorithm-xg-value">' . esc_html( $p['away_xg'] ) . '</span>'
			. '</div></div>';
	}

	/** Win/Draw/Loss probability bar. */
	private function render_outcome_bar( $p ) {
		$html  = '<div class="goalgorithm-outcomes">';
		$html .= '<div class="goalgorithm-outcome-labels">';
		$html .= '<span>' . esc_html( GoalGorithm_Translations::get( 'home_pct' ) ) . ' ' . esc_html( $p['home_win'] ) . '%</span>';
		$html .= '<span>' . esc_html( GoalGorithm_Translations::get( 'draw_pct' ) ) . ' ' . esc_html( $p['draw'] ) . '%</span>';
		$html .= '<span>' . esc_html( GoalGorithm_Translations::get( 'away_pct' ) ) . ' ' . esc_html( $p['away_win'] ) . '%</span>';
		$html .= '</div>';
		$html .= '<div class="goalgorithm-outcome-bar">';
		$html .= '<div class="goalgorithm-bar-home" style="width:' . esc_attr( $p['home_win'] ) . '%"></div>';
		$html .= '<div class="goalgorithm-bar-draw" style="width:' . esc_attr( $p['draw'] ) . '%"></div>';
		$html .= '<div class="goalgorithm-bar-away" style="width:' . esc_attr( $p['away_win'] ) . '%"></div>';
		$html .= '</div></div>';
		return $html;
	}

	/** Market probabilities (Over/Under 2.5, BTTS). */
	private function render_markets( $p ) {
		$t       = GoalGorithm_Translations::class;
		$markets = [
			[ $t::get( 'over_25' ),  $p['over_25'] ],
			[ $t::get( 'under_25' ), $p['under_25'] ],
			[ $t::get( 'btts_yes' ), $p['btts_yes'] ],
			[ $t::get( 'btts_no' ),  $p['btts_no'] ],
		];

		$html = '<div class="goalgorithm-markets">';
		foreach ( $markets as $m ) {
			$html .= '<div class="goalgorithm-market">'
				. '<span class="goalgorithm-market-label">' . esc_html( $m[0] ) . '</span>'
				. '<span class="goalgorithm-market-value">' . esc_html( $m[1] ) . '%</span>'
				. '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/** Top 3 most likely scores. */
	private function render_top_scores( $p ) {
		$html  = '<div class="goalgorithm-top-scores">';
		$html .= '<div class="goalgorithm-section-title">' . esc_html( GoalGorithm_Translations::get( 'top_scores' ) ) . '</div>';
		foreach ( $p['top_scores'] as $score ) {
			$pct   = round( $score['prob'] * 100, 1 );
			$html .= '<div class="goalgorithm-score-item">'
				. '<span class="goalgorithm-score">' . esc_html( $score['home'] ) . ' - ' . esc_html( $score['away'] ) . '</span>'
				. '<span class="goalgorithm-score-pct">' . esc_html( $pct ) . '%</span>'
				. '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/** 6x6 score probability heatmap grid. */
	private function render_score_grid( $p ) {
		$html  = '<div class="goalgorithm-grid-section">';
		$html .= '<div class="goalgorithm-section-title">' . esc_html( GoalGorithm_Translations::get( 'score_grid' ) ) . '</div>';
		$html .= '<table class="goalgorithm-grid"><thead><tr><th></th>';

		for ( $a = 0; $a <= 5; $a++ ) {
			$html .= '<th>' . $a . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		for ( $h = 0; $h <= 5; $h++ ) {
			$html .= '<tr><th>' . $h . '</th>';
			for ( $a = 0; $a <= 5; $a++ ) {
				$val       = isset( $p['matrix'][ $h ][ $a ] ) ? round( $p['matrix'][ $h ][ $a ] * 100, 1 ) : 0;
				$intensity = $this->get_intensity_class( $val );
				$html     .= '<td class="' . esc_attr( $intensity ) . '">' . esc_html( $val ) . '%</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		return $html;
	}

	/** Map probability percentage to CSS heatmap class. */
	private function get_intensity_class( $pct ) {
		if ( $pct >= 10 ) return 'goalgorithm-heat-5';
		if ( $pct >= 7 )  return 'goalgorithm-heat-4';
		if ( $pct >= 5 )  return 'goalgorithm-heat-3';
		if ( $pct >= 3 )  return 'goalgorithm-heat-2';
		if ( $pct >= 1 )  return 'goalgorithm-heat-1';
		return 'goalgorithm-heat-0';
	}
}
