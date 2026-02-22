"""Tests for the data fetcher module."""

import json
import time
from pathlib import Path
from unittest.mock import patch

from goalgorithm_mcp.data_fetcher import (
    aggregate_team_stats,
    get_current_season,
    get_league_averages,
    DataFetcher,
)
from goalgorithm_mcp.types import LeagueAverages, TeamStats

# --- Sample raw Understat data ---

SAMPLE_RAW_TEAMS = [
    {
        "title": "Arsenal",
        "history": [
            {"xG": "1.8", "xGA": "0.5"},
            {"xG": "2.1", "xGA": "1.2"},
            {"xG": "1.5", "xGA": "0.8"},
        ],
    },
    {
        "title": "Chelsea",
        "history": [
            {"xG": "1.2", "xGA": "1.0"},
            {"xG": "1.5", "xGA": "1.3"},
        ],
    },
]


# --- aggregate_team_stats tests ---

class TestAggregateTeamStats:
    def test_basic_aggregation(self):
        result = aggregate_team_stats(SAMPLE_RAW_TEAMS)
        assert "Arsenal" in result
        assert "Chelsea" in result

        arsenal = result["Arsenal"]
        assert arsenal["mp"] == 3
        # total xG = 1.8 + 2.1 + 1.5 = 5.4
        assert abs(arsenal["xg_total"] - 5.4) < 0.01
        # per90 = 5.4 / 3 = 1.8
        assert abs(arsenal["xg_per90"] - 1.8) < 0.01

    def test_empty_history_skipped(self):
        teams = [{"title": "Empty FC", "history": []}]
        result = aggregate_team_stats(teams)
        assert "Empty FC" not in result

    def test_no_title_skipped(self):
        teams = [{"title": "", "history": [{"xG": "1.0", "xGA": "0.5"}]}]
        result = aggregate_team_stats(teams)
        assert len(result) == 0


# --- get_league_averages tests ---

class TestGetLeagueAverages:
    def test_correct_averaging(self):
        data: dict[str, TeamStats] = {
            "A": TeamStats(xg_per90=2.0, xga_per90=1.0, xg_total=0, xga_total=0, mp=0),
            "B": TeamStats(xg_per90=1.0, xga_per90=1.5, xg_total=0, xga_total=0, mp=0),
        }
        avgs = get_league_averages(data)
        # avg xg = (2.0 + 1.0) / 2 = 1.5
        assert abs(avgs["avg_xg_per90"] - 1.5) < 0.001
        # avg xga = (1.0 + 1.5) / 2 = 1.25
        assert abs(avgs["avg_xga_per90"] - 1.25) < 0.001

    def test_empty_fallback(self):
        avgs = get_league_averages({})
        assert avgs["avg_xg_per90"] == 1.3
        assert avgs["avg_xga_per90"] == 1.3


# --- get_current_season tests ---

class TestGetCurrentSeason:
    def test_august_uses_current_year(self):
        from datetime import datetime, timezone
        with patch("goalgorithm_mcp.data_fetcher.datetime") as mock_dt:
            mock_dt.now.return_value = datetime(2025, 8, 15, tzinfo=timezone.utc)
            mock_dt.side_effect = lambda *a, **kw: datetime(*a, **kw)
            assert get_current_season() == 2025

    def test_february_uses_previous_year(self):
        from datetime import datetime, timezone
        with patch("goalgorithm_mcp.data_fetcher.datetime") as mock_dt:
            mock_dt.now.return_value = datetime(2026, 2, 15, tzinfo=timezone.utc)
            mock_dt.side_effect = lambda *a, **kw: datetime(*a, **kw)
            assert get_current_season() == 2025


# --- Cache tests ---

class TestCache:
    def test_write_and_read(self, tmp_path: Path):
        fetcher = DataFetcher(cache_dir=tmp_path, cache_ttl=3600)
        data = {"Arsenal": {"xg_per90": 1.8, "xga_per90": 0.9, "xg_total": 36.0, "xga_total": 18.0, "mp": 20}}
        fetcher._write_cache("9", 2025, data)
        result = fetcher._read_cache("9", 2025)
        assert result is not None
        assert result["Arsenal"]["xg_per90"] == 1.8

    def test_cache_expiry(self, tmp_path: Path):
        fetcher = DataFetcher(cache_dir=tmp_path, cache_ttl=1)
        data = {"test": {"value": 1}}
        fetcher._write_cache("9", 2025, data)
        # Wait for expiry
        time.sleep(1.5)
        result = fetcher._read_cache("9", 2025)
        assert result is None

    def test_clear_cache(self, tmp_path: Path):
        fetcher = DataFetcher(cache_dir=tmp_path)
        fetcher._write_cache("9", 2025, {"test": True})
        fetcher._write_cache("12", 2025, {"test": True})
        assert len(list(tmp_path.glob("*.json"))) == 2
        fetcher.clear_cache()
        assert len(list(tmp_path.glob("*.json"))) == 0
