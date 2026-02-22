# GoalGorithm MCP Server

Soccer match predictions using xG data and Poisson distribution, exposed as MCP tools for Claude Desktop/Code.

> **Proven in production** — This prediction model is actively used on [BongdaNET](https://bongdanet66.com), a football analytics platform that combines expert analysis with data science to deliver accurate match predictions. BongdaNET also serves as a comprehensive football data hub — offering odds from top bookmakers, live results, fixtures, and standings for leagues worldwide — providing a smart betting experience for punters and football enthusiasts alike.

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

## Example Usage

Once configured, just ask Claude naturally:

> **You:** "Predict Arsenal vs Chelsea this weekend"

Claude will call the `predict_match` tool and respond with something like:

> **Claude:** Here's the prediction for **Arsenal vs Chelsea** (Premier League):
>
> | Outcome | Probability |
> |---------|-------------|
> | Arsenal Win | 52.4% |
> | Draw | 22.7% |
> | Chelsea Win | 24.9% |
>
> - **Expected Goals:** Arsenal 1.85 — Chelsea 1.23
> - **Over 2.5 Goals:** 58.3% | **Under 2.5:** 41.7%
> - **Both Teams to Score:** Yes 52.1% | No 47.9%
> - **Most Likely Scores:** 1-0 (12.8%), 1-1 (11.2%), 2-1 (10.5%)
>
> Arsenal are clear favorites at home with stronger attacking xG.

Other things you can ask:

- *"Show me the La Liga xG table"* — calls `get_league_table`
- *"Which leagues are available?"* — calls `list_leagues`
- *"Who's more likely to win, Bayern or Dortmund?"* — calls `predict_match`

## Tools

### `predict_match`

Predict soccer match outcome using xG-based Poisson model.

```
predict_match(home_team="Arsenal", away_team="Chelsea", league="EPL")
```

**Returns:** win/draw/loss %, over/under 2.5, BTTS, top 3 scores, expected goals, score matrix.

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
