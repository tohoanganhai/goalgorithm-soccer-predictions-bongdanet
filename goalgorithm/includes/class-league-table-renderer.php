<?php
/**
 * League Table Renderer - renders [goalgorithm_league] shortcode as a
 * match prediction table grouped by league, similar to Vietnamese betting sites.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_League_Table_Renderer {

	/**
	 * Render [goalgorithm_league] shortcode.
	 * Usage: [goalgorithm_league league="9" limit="10"]
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( [
			'league' => get_option( 'goalgorithm_default_league', '9' ),
			'limit'  => '10',
		], $atts, 'goalgorithm_league' );

		$league_id = sanitize_text_field( $atts['league'] );
		$limit     = max( 1, min( 50, (int) $atts['limit'] ) );

		$fetcher     = new GoalGorithm_Data_Fetcher();
		$league_data = $fetcher->get_league_data( $league_id );
		if ( is_wp_error( $league_data ) ) {
			return $this->render_error( $league_data->get_error_message() );
		}

		$fixtures = $fetcher->get_league_fixtures( $league_id );
		if ( is_wp_error( $fixtures ) || empty( $fixtures ) ) {
			return $this->render_error( 'No fixtures available.' );
		}

		// Filter upcoming fixtures and sort by date
		$upcoming = array_values( array_filter( $fixtures, function ( $m ) {
			return empty( $m['isResult'] );
		} ) );
		usort( $upcoming, function ( $a, $b ) {
			return strcmp( $a['datetime'] ?? '', $b['datetime'] ?? '' );
		} );
		$upcoming = array_slice( $upcoming, 0, $limit );

		if ( empty( $upcoming ) ) {
			return $this->render_error( 'No upcoming fixtures found.' );
		}

		$league_avgs = $fetcher->get_league_averages( $league_data );
		$engine      = new GoalGorithm_Prediction_Engine();
		$league_name = GoalGorithm_Data_Fetcher::LEAGUES[ $league_id ] ?? 'League';

		return $this->render_table( $upcoming, $league_data, $league_avgs, $engine, $league_name );
	}

	/** Render the league prediction table with header and match rows. */
	private function render_table( $fixtures, $league_data, $avgs, $engine, $league_name ) {
		$html  = '<div class="gg-league-wrap">';
		$html .= '<div class="gg-league-header">' . esc_html( $league_name ) . '</div>';
		$html .= '<table class="gg-table"><thead><tr>';
		$html .= '<th class="gg-col-time">Thời gian</th>';
		$html .= '<th class="gg-col-match">Trận đấu</th>';
		$html .= '<th class="gg-col-handicap">Tỷ lệ</th>';
		$html .= '<th class="gg-col-score">Dự Đoán</th>';
		$html .= '<th class="gg-col-pick" colspan="2">Chọn</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $fixtures as $match ) {
			$html .= $this->render_match_row( $match, $league_data, $avgs, $engine );
		}

		$html .= '</tbody></table></div>';
		return $html;
	}

	/** Render a single match prediction row. */
	private function render_match_row( $match, $league_data, $avgs, $engine ) {
		$home = $match['h']['title'] ?? '';
		$away = $match['a']['title'] ?? '';
		$dt   = $match['datetime'] ?? '';
		$ts   = $dt ? strtotime( $dt ) : 0;
		$date = $ts ? gmdate( 'd/m', $ts ) : '';
		$time = $ts ? gmdate( 'H:i', $ts ) : '';

		$pred = $engine->predict( $home, $away, $league_data, $avgs );
		if ( is_wp_error( $pred ) ) {
			return '<tr class="gg-row"><td class="gg-time">' . esc_html( $date ) . '<br>' . esc_html( $time )
				. '</td><td class="gg-match">' . esc_html( $home ) . ' vs ' . esc_html( $away )
				. '</td><td colspan="4">&mdash;</td></tr>';
		}

		$hxg = $pred['home_xg'];
		$axg = $pred['away_xg'];
		$top = $pred['top_scores'][0] ?? null;
		$scr = $top ? $top['home'] . ' - ' . $top['away'] : '—';

		// Predicted winner highlight
		$hw = $pred['home_win'] > $pred['away_win'] && $pred['home_win'] > $pred['draw'];
		$aw = $pred['away_win'] > $pred['home_win'] && $pred['away_win'] > $pred['draw'];

		$winner   = $hw ? $home : ( $aw ? $away : '' );
		$handicap = $this->calc_handicap( $hxg, $axg );
		$ou       = $this->calc_over_under( $hxg, $axg );

		$hc = $hw ? ' gg-predicted' : '';
		$ac = $aw ? ' gg-predicted' : '';

		$html  = '<tr class="gg-row">';
		$html .= '<td class="gg-time">' . esc_html( $date ) . '<br>' . esc_html( $time ) . '</td>';
		$html .= '<td class="gg-match">';
		$html .= '<span class="gg-team' . $hc . '">' . esc_html( $home ) . '</span>';
		$html .= '<span class="gg-team' . $ac . '">' . esc_html( $away ) . '</span>';
		$html .= '<span class="gg-draw">Hòa</span>';
		$html .= '</td>';
		$html .= '<td class="gg-handicap">' . esc_html( $handicap ) . '</td>';
		$html .= '<td class="gg-score">' . esc_html( $scr ) . '</td>';
		$html .= '<td class="gg-pick-team">' . esc_html( $winner ) . '</td>';
		$html .= '<td class="gg-pick-ou">' . esc_html( $ou ) . '</td>';
		$html .= '</tr>';
		return $html;
	}

	/** Calculate Asian Handicap display string from expected goals. */
	private function calc_handicap( $home_xg, $away_xg ) {
		$diff = $home_xg - $away_xg;
		$line = round( $diff * 4 ) / 4;
		if ( abs( $line ) < 0.01 ) {
			return '';
		}
		// Positive diff = home stronger → home gives goals (negative handicap)
		$sign = $line > 0 ? '-' : '+';
		return 'Chủ ' . $sign . $this->format_quarter( abs( $line ) );
	}

	/** Calculate Over/Under recommendation string. */
	private function calc_over_under( $home_xg, $away_xg ) {
		$total = $home_xg + $away_xg;
		// Round down so $total > $line works for "Tài" recommendation
		$line  = floor( $total * 4 ) / 4;
		if ( $line < 0.5 ) {
			$line = 0.5;
		}
		$type = ( $total > $line ) ? 'Tài' : 'Xỉu';
		return $type . ' ' . $this->format_quarter( $line );
	}

	/** Format decimal as Vietnamese fraction: 2.25 → "2 1/4", 2.5 → "2 1/2". */
	private function format_quarter( $value ) {
		$whole    = (int) floor( $value );
		$fraction = $value - $whole;
		if ( $fraction < 0.01 ) {
			return (string) $whole;
		}
		if ( abs( $fraction - 0.25 ) < 0.01 ) {
			return $whole . ' 1/4';
		}
		if ( abs( $fraction - 0.5 ) < 0.01 ) {
			return $whole . ' 1/2';
		}
		if ( abs( $fraction - 0.75 ) < 0.01 ) {
			return $whole . ' 3/4';
		}
		return (string) round( $value, 2 );
	}

	/** Render error message. */
	private function render_error( $message ) {
		return '<div class="goalgorithm-error"><strong>GoalGorithm:</strong> '
			. esc_html( $message ) . '</div>';
	}
}
