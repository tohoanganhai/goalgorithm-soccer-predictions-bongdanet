<?php
/**
 * FBRef HTML Parser - extracts xG/xGA data from FBRef HTML using DOMDocument/DOMXPath.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoalGorithm_FBRef_HTML_Parser {

	/**
	 * Parse FBRef HTML to extract team xG/xGA data.
	 * FBRef hides some tables in HTML comments - strip them first.
	 *
	 * @param string $html Raw HTML from FBRef.
	 * @return array|WP_Error Parsed data or error.
	 */
	public function parse( $html ) {
		$html = str_replace( [ '<!--', '-->' ], '', $html );

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Try primary table IDs first, then fallback alternatives
		$team_stats     = $this->parse_stats_table( $xpath, 'stats_squads_standard_for' );
		$opponent_stats = $this->parse_stats_table( $xpath, 'stats_squads_standard_against' );

		if ( empty( $team_stats ) ) {
			$team_stats = $this->parse_stats_table( $xpath, 'stats_standard' );
		}
		if ( empty( $opponent_stats ) ) {
			$opponent_stats = $this->parse_stats_table( $xpath, 'stats_standard_against' );
		}

		if ( empty( $team_stats ) ) {
			return new WP_Error( 'parse_error', 'Could not find squad stats table on FBRef page.' );
		}

		return $this->merge_stats( $team_stats, $opponent_stats );
	}

	/**
	 * Extract xG data rows from a specific FBRef stats table by ID.
	 *
	 * @param DOMXPath $xpath    XPath instance.
	 * @param string   $table_id HTML table ID attribute.
	 * @return array Team data keyed by name.
	 */
	private function parse_stats_table( $xpath, $table_id ) {
		$rows = $xpath->query( "//table[@id='{$table_id}']//tbody/tr[not(contains(@class,'thead'))]" );
		if ( ! $rows || 0 === $rows->length ) {
			return [];
		}

		$data = [];
		foreach ( $rows as $row ) {
			if ( in_array( $row->getAttribute( 'class' ), [ 'spacer', 'thead', 'over_header' ], true ) ) {
				continue;
			}

			$team_name = $this->extract_cell( $xpath, $row, 'team' );
			if ( empty( $team_name ) ) {
				$team_name = $this->extract_cell( $xpath, $row, 'squad' );
			}
			if ( empty( $team_name ) ) {
				continue;
			}

			$mp = (int) $this->extract_cell( $xpath, $row, 'games' );
			$xg = (float) $this->extract_cell( $xpath, $row, 'xg' );

			if ( $xg <= 0 ) {
				$xg = (float) $this->extract_cell( $xpath, $row, 'xg_for' );
			}

			if ( $mp > 0 ) {
				$data[ $team_name ] = [ 'xg' => $xg, 'mp' => $mp ];
			}
		}

		return $data;
	}

	/** Extract text content from a table cell by data-stat attribute. */
	private function extract_cell( $xpath, $row, $stat ) {
		$nodes = $xpath->query( ".//td[@data-stat='{$stat}'] | .//th[@data-stat='{$stat}']", $row );
		return ( $nodes->length > 0 ) ? trim( $nodes->item( 0 )->textContent ) : '';
	}

	/**
	 * Merge team attacking stats with opponent (defensive) stats.
	 * Opponent xG = this team's xGA (goals expected against).
	 */
	private function merge_stats( $team_stats, $opponent_stats ) {
		$merged = [];
		foreach ( $team_stats as $team => $stats ) {
			$xga = isset( $opponent_stats[ $team ] ) ? $opponent_stats[ $team ]['xg'] : 0.0;
			$mp  = $stats['mp'];

			$merged[ $team ] = [
				'xg_per90'  => $mp > 0 ? round( $stats['xg'] / $mp, 3 ) : 0,
				'xga_per90' => $mp > 0 ? round( $xga / $mp, 3 ) : 0,
				'xg_total'  => $stats['xg'],
				'xga_total' => $xga,
				'mp'        => $mp,
			];
		}
		return $merged;
	}
}
