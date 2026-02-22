"""Poisson distribution prediction engine for soccer matches.

Ports GoalGorithm_Prediction_Engine from PHP to Python.
Pure math module — no I/O, no side effects.
"""

from __future__ import annotations

import math

from goalgorithm_mcp.types import LeagueAverages, TeamStats

# Truncation ceiling for Poisson PMF. k=10 ensures P(k>10) < 0.001
# for all realistic soccer xG values (lambda <= 3.5).
MAX_GOALS = 10


def predict(
    home_name: str,
    away_name: str,
    league_data: dict[str, TeamStats],
    league_avgs: LeagueAverages,
) -> dict:
    """Generate full match prediction.

    Returns dict with win/draw/loss %, over/under, BTTS, score matrix,
    top 3 scores, and expected goals.
    """
    home = find_team(home_name, league_data)
    if home is None:
        raise ValueError(f"Home team '{home_name}' not found in league data.")

    away = find_team(away_name, league_data)
    if away is None:
        raise ValueError(f"Away team '{away_name}' not found in league data.")

    home_name_canonical, home_stats = home
    away_name_canonical, away_stats = away

    expected = calc_expected_goals(home_stats, away_stats, league_avgs)
    home_probs = goal_probabilities(expected["home_xg"])
    away_probs = goal_probabilities(expected["away_xg"])
    predictions = build_predictions(home_probs, away_probs)

    return {
        **predictions,
        "home_team": home_name_canonical,
        "away_team": away_name_canonical,
        "home_xg": expected["home_xg"],
        "away_xg": expected["away_xg"],
    }


def find_team(
    name: str, league_data: dict[str, TeamStats]
) -> tuple[str, TeamStats] | None:
    """Find team by name with case-insensitive exact + partial match.

    Returns (canonical_name, stats) or None.
    """
    name_lower = name.strip().lower()

    # Exact match first
    for team, data in league_data.items():
        if team.lower() == name_lower:
            return (team, data)

    # Partial match fallback (substring in either direction)
    for team, data in league_data.items():
        team_lower = team.lower()
        if name_lower in team_lower or team_lower in name_lower:
            return (team, data)

    return None


def calc_expected_goals(
    home: TeamStats, away: TeamStats, avgs: LeagueAverages
) -> dict[str, float]:
    """Calculate expected goals via attack/defense strength model.

    Formula: HomeXG = HomeAttack * AwayDefense * LeagueAvg
    Exact port of PHP calc_expected_goals().
    """
    avg_xg = max(avgs["avg_xg_per90"], 0.1)
    avg_xga = max(avgs["avg_xga_per90"], 0.1)

    home_attack = home["xg_per90"] / avg_xg
    away_defense = away["xga_per90"] / avg_xga
    home_xg = home_attack * away_defense * avg_xg

    away_attack = away["xg_per90"] / avg_xg
    home_defense = home["xga_per90"] / avg_xga
    away_xg = away_attack * home_defense * avg_xg

    return {
        "home_xg": round(home_xg, 3),
        "away_xg": round(away_xg, 3),
    }


def poisson_pmf(k: int, lam: float) -> float:
    """Numerically stable Poisson probability mass function.

    Uses log-space calculation: P(k) = exp(k*ln(λ) - λ - lgamma(k+1))
    """
    if lam <= 0:
        return 1.0 if k == 0 else 0.0
    return math.exp(k * math.log(lam) - lam - math.lgamma(k + 1))


def goal_probabilities(lam: float) -> list[float]:
    """Generate Poisson probability array for 0..MAX_GOALS."""
    return [poisson_pmf(k, lam) for k in range(MAX_GOALS + 1)]


def build_predictions(
    home_probs: list[float], away_probs: list[float]
) -> dict:
    """Build 6x6 score matrix and derive all match outcomes.

    Exact port of PHP build_predictions().
    Returns matrix, W/D/L %, O/U 2.5, BTTS, top 3 scores.
    """
    matrix: list[list[float]] = []
    home_win = 0.0
    draw = 0.0
    away_win = 0.0
    over_25 = 0.0
    btts_yes = 0.0
    scores: list[dict] = []

    for h in range(MAX_GOALS + 1):
        row: list[float] = []
        for a in range(MAX_GOALS + 1):
            prob = home_probs[h] * away_probs[a]
            row.append(round(prob, 6))

            if h > a:
                home_win += prob
            elif h == a:
                draw += prob
            else:
                away_win += prob

            if (h + a) > 2:
                over_25 += prob
            if h >= 1 and a >= 1:
                btts_yes += prob

            scores.append({"home": h, "away": a, "prob": prob})

        matrix.append(row)

    # Sort by probability descending
    scores.sort(key=lambda s: s["prob"], reverse=True)

    return {
        "matrix": matrix,
        "home_win": round(home_win * 100, 1),
        "draw": round(draw * 100, 1),
        "away_win": round(away_win * 100, 1),
        "over_25": round(over_25 * 100, 1),
        "under_25": round((1 - over_25) * 100, 1),
        "btts_yes": round(btts_yes * 100, 1),
        "btts_no": round((1 - btts_yes) * 100, 1),
        "top_scores": scores[:3],
    }
