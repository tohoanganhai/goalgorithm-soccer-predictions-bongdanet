# GoalGorithm MCP Server

Soccer match predictions using xG data and Poisson distribution, exposed as MCP tools for Claude Desktop/Code.

## Install

```bash
pip install goalgorithm-mcp
```

Or run directly:

```bash
uvx goalgorithm-mcp
```

## Claude Desktop Config

Add to your Claude Desktop config (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "goalgorithm": {
      "command": "goalgorithm-mcp"
    }
  }
}
```

## Tools

### `predict_match`

Predict soccer match outcome using xG-based Poisson model.

```
predict_match(home_team="Arsenal", away_team="Chelsea", league="EPL")
```

Returns win/draw/loss probabilities, over/under 2.5 goals, BTTS, top 3 most likely scores.

### `list_leagues`

List all supported soccer leagues with IDs and slugs.

### `get_league_table`

Get all teams in a league with their xG statistics, sorted by attacking strength.

```
get_league_table(league="EPL")
```

## Supported Leagues

| ID | League | Slug |
|----|--------|------|
| 9  | Premier League | EPL |
| 12 | La Liga | LaLiga |
| 11 | Serie A | SerieA |
| 20 | Bundesliga | Bundesliga |
| 13 | Ligue 1 | Ligue1 |

## How It Works

1. Fetches team xG/xGA stats from [Understat.com](https://understat.com)
2. Computes attack/defense strength relative to league average
3. Applies Poisson distribution to calculate goal probabilities
4. Builds 6x6 score matrix for all possible scorelines (0-5 goals each)
5. Derives match outcomes: W/D/L, Over/Under 2.5, BTTS

## Data Source

All data from [Understat.com](https://understat.com) public JSON API. Results cached locally for 12 hours.

## License

GPL v2 or later
