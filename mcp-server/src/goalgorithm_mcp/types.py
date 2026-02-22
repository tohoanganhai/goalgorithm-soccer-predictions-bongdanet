"""Shared types and constants for GoalGorithm MCP Server."""

from __future__ import annotations

from dataclasses import dataclass
from typing import TypedDict


class TeamStats(TypedDict):
    """Per-team aggregated xG statistics."""
    xg_per90: float
    xga_per90: float
    xg_total: float
    xga_total: float
    mp: int


class LeagueAverages(TypedDict):
    """League-wide average xG stats."""
    avg_xg_per90: float
    avg_xga_per90: float


@dataclass(frozen=True)
class LeagueInfo:
    """League metadata."""
    id: str
    name: str
    slug: str          # Understat URL slug (e.g. "EPL")
    display_slug: str  # User-facing slug (e.g. "EPL")


# Map numeric league IDs to league info
# IDs match the WordPress plugin for backward compatibility
LEAGUES: dict[str, LeagueInfo] = {
    "9":  LeagueInfo(id="9",  name="Premier League", slug="EPL",        display_slug="EPL"),
    "12": LeagueInfo(id="12", name="La Liga",        slug="La_liga",    display_slug="LaLiga"),
    "11": LeagueInfo(id="11", name="Serie A",        slug="Serie_A",    display_slug="SerieA"),
    "20": LeagueInfo(id="20", name="Bundesliga",     slug="Bundesliga", display_slug="Bundesliga"),
    "13": LeagueInfo(id="13", name="Ligue 1",        slug="Ligue_1",    display_slug="Ligue1"),
}
