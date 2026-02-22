"""Understat.com data fetcher with file-based JSON cache.

Ports GoalGorithm_Data_Fetcher from PHP to Python.
Replaces WordPress transients with local file cache.
"""

from __future__ import annotations

import json
import time
from datetime import datetime, timezone
from pathlib import Path

import httpx

from goalgorithm_mcp.types import LEAGUES, LeagueAverages, TeamStats

UNDERSTAT_BASE_URL = "https://understat.com/getLeagueData/"

USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/120.0.0.0 Safari/537.36"
)

# Default cache TTL: 12 hours (in seconds)
DEFAULT_CACHE_TTL = 12 * 3600


class DataFetcher:
    """Fetch and cache xG/xGA team stats from Understat.com."""

    def __init__(
        self,
        cache_dir: Path | None = None,
        cache_ttl: int = DEFAULT_CACHE_TTL,
    ):
        self._cache_dir = cache_dir or Path.home() / ".cache" / "goalgorithm-mcp"
        self._cache_ttl = cache_ttl
        self._cache_dir.mkdir(parents=True, exist_ok=True)

    async def get_league_data(self, league_id: str) -> dict[str, TeamStats]:
        """Get team stats for a league, using cache when available."""
        if league_id not in LEAGUES:
            raise ValueError(f"Unsupported league ID: {league_id}")

        season = get_current_season()

        # Try cache first
        cached = self._read_cache(league_id, season)
        if cached is not None:
            return cached

        # Fetch fresh data
        data = await self._fetch_from_understat(league_id, season)
        if not data:
            raise RuntimeError("No team data available for this league/season.")

        self._write_cache(league_id, season, data)
        return data

    async def _fetch_from_understat(
        self, league_id: str, season: int
    ) -> dict[str, TeamStats]:
        """Fetch team xG data from Understat JSON API."""
        league = LEAGUES[league_id]
        url = f"{UNDERSTAT_BASE_URL}{league.slug}/{season}"

        async with httpx.AsyncClient(timeout=15.0) as client:
            resp = await client.get(
                url,
                headers={
                    "User-Agent": USER_AGENT,
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json",
                    "Accept-Encoding": "gzip, deflate",
                },
            )

        if resp.status_code != 200:
            raise RuntimeError(f"Understat returned HTTP {resp.status_code}")

        body = resp.json()
        if not isinstance(body, dict) or "teams" not in body:
            raise RuntimeError("Could not parse Understat response.")

        return aggregate_team_stats(body["teams"])

    # --- Cache helpers ---

    def _cache_path(self, league_id: str, season: int) -> Path:
        return self._cache_dir / f"{league_id}_{season}.json"

    def _read_cache(self, league_id: str, season: int) -> dict[str, TeamStats] | None:
        path = self._cache_path(league_id, season)
        if not path.exists():
            return None
        # Check TTL based on file mtime
        age = time.time() - path.stat().st_mtime
        if age > self._cache_ttl:
            return None
        try:
            return json.loads(path.read_text())
        except (json.JSONDecodeError, OSError):
            return None

    def _write_cache(self, league_id: str, season: int, data: dict) -> None:
        path = self._cache_path(league_id, season)
        tmp = path.with_suffix(".tmp")
        try:
            tmp.write_text(json.dumps(data))
            tmp.rename(path)
        except OSError:
            tmp.unlink(missing_ok=True)

    def clear_cache(self) -> None:
        """Remove all cached files."""
        for f in self._cache_dir.glob("*.json"):
            f.unlink(missing_ok=True)


def get_current_season() -> int:
    """Determine current football season start year.

    European leagues run Aug-May:
    - If current month >= August: season = this year
    - Else: season = last year
    """
    now = datetime.now(timezone.utc)
    return now.year if now.month >= 8 else now.year - 1


def aggregate_team_stats(teams: list[dict]) -> dict[str, TeamStats]:
    """Aggregate per-match xG/xGA into per-90 averages.

    Exact port of PHP GoalGorithm_Data_Fetcher::aggregate_team_stats().
    """
    result: dict[str, TeamStats] = {}

    for team in teams:
        title = team.get("title", "")
        history = team.get("history", [])

        if not title or not history:
            continue

        total_xg = 0.0
        total_xga = 0.0
        mp = len(history)

        for match in history:
            total_xg += float(match.get("xG", 0))
            total_xga += float(match.get("xGA", 0))

        result[title] = TeamStats(
            xg_per90=round(total_xg / mp, 3) if mp > 0 else 0.0,
            xga_per90=round(total_xga / mp, 3) if mp > 0 else 0.0,
            xg_total=round(total_xg, 3),
            xga_total=round(total_xga, 3),
            mp=mp,
        )

    return result


def get_league_averages(league_data: dict[str, TeamStats]) -> LeagueAverages:
    """Calculate league-wide average xG/90 and xGA/90.

    Exact port of PHP GoalGorithm_Data_Fetcher::get_league_averages().
    Falls back to 1.3 if data is empty.
    """
    if not league_data:
        return LeagueAverages(avg_xg_per90=1.3, avg_xga_per90=1.3)

    count = len(league_data)
    total_xg = sum(t["xg_per90"] for t in league_data.values())
    total_xga = sum(t["xga_per90"] for t in league_data.values())

    return LeagueAverages(
        avg_xg_per90=round(total_xg / count, 4) if count > 0 else 1.3,
        avg_xga_per90=round(total_xga / count, 4) if count > 0 else 1.3,
    )
