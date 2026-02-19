# GoalGorithm - Soccer Predictions WordPress Plugin

WordPress plugin that uses Expected Goals (xG) data and Poisson distribution to predict soccer match outcomes via shortcode.

## Features

- Fetches xG/xGA data from [Understat.com](https://understat.com) JSON API
- Poisson distribution model for match outcome probabilities
- `[goalgorithm]` shortcode with styled prediction card
- `[goalgorithm_league]` shortcode with league prediction table (Asian Handicap, Over/Under)
- Win/Draw/Loss, Over/Under 2.5, BTTS probabilities
- 6x6 score probability heatmap grid
- Top 3 most likely exact scores
- Admin settings page with cache management
- Supports 5 leagues: Premier League, La Liga, Serie A, Bundesliga, Ligue 1

## Installation

1. Copy the `goalgorithm/` folder to `/wp-content/plugins/`
2. Activate via WordPress Admin > Plugins
3. Configure at Settings > GoalGorithm

## Usage

```
[goalgorithm home="Arsenal" away="Chelsea"]
[goalgorithm league="12" home="Barcelona" away="Real Madrid"]
[goalgorithm league="20" home="Bayern Munich" away="Dortmund"]
```

### League Prediction Table

```
[goalgorithm_league league="9"]
[goalgorithm_league league="9" limit="20"]
[goalgorithm_league league="12" limit="15"]
```

Shows upcoming fixtures with predicted scores, Asian Handicap, and Over/Under picks in a table format.

### League IDs

| ID | League |
|----|--------|
| 9  | Premier League (default) |
| 12 | La Liga |
| 11 | Serie A |
| 20 | Bundesliga |
| 13 | Ligue 1 |

## How It Works

1. **Data Collection**: Fetches team xG/xGA stats from Understat.com JSON API
2. **Strength Calculation**: Computes attack/defense strength relative to league average
3. **Expected Goals**: `HomeXG = HomeAttack * AwayDefense * LeagueAvg`
4. **Poisson Distribution**: Calculates probability of each team scoring 0-5 goals
5. **Score Matrix**: 6x6 grid of all possible scoreline probabilities
6. **Outcome Derivation**: Win/Draw/Loss, Over/Under, BTTS from the matrix

## Requirements

- WordPress 5.0+
- PHP 7.4+

## File Structure

```
goalgorithm/
├── goalgorithm.php                        # Main plugin bootstrap
├── includes/
│   ├── class-data-fetcher.php             # Understat API fetching + caching
│   ├── class-prediction-engine.php        # Poisson math engine
│   ├── class-shortcode-renderer.php       # Single match prediction card
│   ├── class-league-table-renderer.php    # League prediction table
│   └── class-admin-settings.php           # Admin settings page
├── assets/css/
│   └── goalgorithm-frontend.css           # Frontend styles
└── readme.txt                             # WordPress plugin readme
```

## License

GPL v2 or later
