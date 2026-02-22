"""GoalGorithm MCP Server — 3 tools for soccer match predictions.

Tools:
  - predict_match: Full match prediction with W/D/L, O/U, BTTS
  - list_leagues: List supported leagues
  - get_league_table: Team xG stats for a league
"""

from __future__ import annotations

from fastmcp import FastMCP

from goalgorithm_mcp.data_fetcher import DataFetcher, get_current_season, get_league_averages
from goalgorithm_mcp.prediction_engine import predict
from goalgorithm_mcp.types import LEAGUES, LeagueInfo

mcp = FastMCP("GoalGorithm")

# Shared instances
_fetcher = DataFetcher()


def resolve_league(query: str) -> LeagueInfo:
    """Resolve league from slug, name, or numeric ID.

    Accepts: "EPL", "Premier League", "9", "LaLiga", etc.
    """
    q = query.strip()
    q_lower = q.lower()

    # Match by numeric ID
    if q in LEAGUES:
        return LEAGUES[q]

    # Match by slug or display_slug (case-insensitive)
    for league in LEAGUES.values():
        if q_lower in (league.slug.lower(), league.display_slug.lower()):
            return league

    # Match by name (case-insensitive, partial)
    for league in LEAGUES.values():
        if q_lower in league.name.lower() or league.name.lower() in q_lower:
            return league

    available = ", ".join(
        f"{lg.display_slug} ({lg.name})" for lg in LEAGUES.values()
    )
    raise ValueError(f"Unknown league '{query}'. Available: {available}")


@mcp.tool()
async def predict_match(
    home_team: str,
    away_team: str,
    league: str = "EPL",
) -> dict:
    """Predict soccer match outcome using xG-based Poisson model.

    Returns win/draw/loss probabilities, over/under 2.5 goals,
    both teams to score, and top 3 most likely scores.

    Args:
        home_team: Home team name (e.g. "Arsenal")
        away_team: Away team name (e.g. "Chelsea")
        league: League slug, name, or ID (default: EPL)
    """
    league_info = resolve_league(league)
    league_data = await _fetcher.get_league_data(league_info.id)
    avgs = get_league_averages(league_data)
    result = predict(home_team, away_team, league_data, avgs)

    # Format top scores for readability
    top_scores = [
        f"{s['home']}-{s['away']} ({round(s['prob'] * 100, 1)}%)"
        for s in result["top_scores"]
    ]

    return {
        "match": f"{result['home_team']} vs {result['away_team']}",
        "league": league_info.name,
        "expected_goals": {
            "home": result["home_xg"],
            "away": result["away_xg"],
        },
        "probabilities": {
            "home_win": f"{result['home_win']}%",
            "draw": f"{result['draw']}%",
            "away_win": f"{result['away_win']}%",
        },
        "over_under_2.5": {
            "over": f"{result['over_25']}%",
            "under": f"{result['under_25']}%",
        },
        "btts": {
            "yes": f"{result['btts_yes']}%",
            "no": f"{result['btts_no']}%",
        },
        "top_3_scores": top_scores,
        "score_matrix": result["matrix"],
    }


@mcp.tool()
async def list_leagues() -> list[dict]:
    """List all supported soccer leagues with IDs and slugs."""
    return [
        {"id": lg.id, "name": lg.name, "slug": lg.display_slug}
        for lg in LEAGUES.values()
    ]


@mcp.tool()
async def get_league_table(league: str = "EPL") -> dict:
    """Get all teams in a league with their xG statistics.

    Returns teams sorted by attacking strength (xG per 90 minutes).

    Args:
        league: League slug, name, or ID (default: EPL)
    """
    league_info = resolve_league(league)
    league_data = await _fetcher.get_league_data(league_info.id)
    avgs = get_league_averages(league_data)

    # Sort by xg_per90 descending
    sorted_teams = sorted(
        league_data.items(),
        key=lambda t: t[1]["xg_per90"],
        reverse=True,
    )

    teams = [
        {
            "rank": i + 1,
            "team": name,
            "xg_per90": stats["xg_per90"],
            "xga_per90": stats["xga_per90"],
            "xg_total": stats["xg_total"],
            "xga_total": stats["xga_total"],
            "matches": stats["mp"],
        }
        for i, (name, stats) in enumerate(sorted_teams)
    ]

    return {
        "league": league_info.name,
        "season": f"{get_current_season()}/{get_current_season() + 1}",
        "averages": {
            "xg_per90": avgs["avg_xg_per90"],
            "xga_per90": avgs["avg_xga_per90"],
        },
        "teams": teams,
    }


def main():
    """Entry point for the goalgorithm-mcp CLI command."""
    mcp.run()


if __name__ == "__main__":
    main()
