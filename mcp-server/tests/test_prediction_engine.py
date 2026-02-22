"""Tests for the Poisson prediction engine."""

import math

from goalgorithm_mcp.prediction_engine import (
    MAX_GOALS,
    build_predictions,
    calc_expected_goals,
    find_team,
    goal_probabilities,
    poisson_pmf,
    predict,
)
from goalgorithm_mcp.types import LeagueAverages, TeamStats

# --- Sample data for tests ---

SAMPLE_LEAGUE_DATA: dict[str, TeamStats] = {
    "Arsenal": TeamStats(xg_per90=1.8, xga_per90=0.9, xg_total=36.0, xga_total=18.0, mp=20),
    "Chelsea": TeamStats(xg_per90=1.5, xga_per90=1.2, xg_total=30.0, xga_total=24.0, mp=20),
    "Manchester United": TeamStats(xg_per90=1.3, xga_per90=1.4, xg_total=26.0, xga_total=28.0, mp=20),
}

SAMPLE_AVGS = LeagueAverages(avg_xg_per90=1.5333, avg_xga_per90=1.1667)


# --- poisson_pmf tests ---

class TestPoissonPMF:
    def test_known_values_lambda_1_5(self):
        """Verify against scipy reference values for lambda=1.5."""
        # scipy.stats.poisson.pmf(0, 1.5) ≈ 0.22313
        assert abs(poisson_pmf(0, 1.5) - 0.22313) < 0.001
        # scipy.stats.poisson.pmf(1, 1.5) ≈ 0.33470
        assert abs(poisson_pmf(1, 1.5) - 0.33470) < 0.001
        # scipy.stats.poisson.pmf(2, 1.5) ≈ 0.25102
        assert abs(poisson_pmf(2, 1.5) - 0.25102) < 0.001

    def test_lambda_zero(self):
        """lambda=0: P(0)=1, P(k>0)=0."""
        assert poisson_pmf(0, 0) == 1.0
        assert poisson_pmf(1, 0) == 0.0
        assert poisson_pmf(5, 0) == 0.0

    def test_lambda_negative(self):
        """Negative lambda treated same as zero."""
        assert poisson_pmf(0, -1.0) == 1.0
        assert poisson_pmf(1, -1.0) == 0.0


class TestGoalProbabilities:
    def test_sum_close_to_one(self):
        """P(0..5) should be close to 1.0 (truncation loses some mass)."""
        probs = goal_probabilities(1.5)
        total = sum(probs)
        # For lambda=1.5, P(0..5) covers ~99.55%
        assert 0.99 < total <= 1.0

    def test_length(self):
        probs = goal_probabilities(2.0)
        assert len(probs) == MAX_GOALS + 1


# --- calc_expected_goals tests ---

class TestCalcExpectedGoals:
    def test_with_known_stats(self):
        home = SAMPLE_LEAGUE_DATA["Arsenal"]
        away = SAMPLE_LEAGUE_DATA["Chelsea"]
        result = calc_expected_goals(home, away, SAMPLE_AVGS)
        assert "home_xg" in result
        assert "away_xg" in result
        assert result["home_xg"] > 0
        assert result["away_xg"] > 0

    def test_guard_against_zero_average(self):
        """avg values of 0 should be guarded to 0.1 minimum."""
        home = SAMPLE_LEAGUE_DATA["Arsenal"]
        away = SAMPLE_LEAGUE_DATA["Chelsea"]
        avgs = LeagueAverages(avg_xg_per90=0, avg_xga_per90=0)
        result = calc_expected_goals(home, away, avgs)
        assert math.isfinite(result["home_xg"])
        assert math.isfinite(result["away_xg"])


# --- build_predictions tests ---

class TestBuildPredictions:
    def setup_method(self):
        self.home_probs = goal_probabilities(1.8)
        self.away_probs = goal_probabilities(1.2)
        self.result = build_predictions(self.home_probs, self.away_probs)

    def test_win_draw_loss_sum(self):
        """home_win + draw + away_win should be ~100% (truncation at k=5 loses some mass)."""
        total = self.result["home_win"] + self.result["draw"] + self.result["away_win"]
        assert 97.0 < total <= 100.0

    def test_over_under_sum(self):
        """over_25 + under_25 = 100%."""
        total = self.result["over_25"] + self.result["under_25"]
        assert abs(total - 100.0) < 0.2

    def test_btts_sum(self):
        """btts_yes + btts_no = 100%."""
        total = self.result["btts_yes"] + self.result["btts_no"]
        assert abs(total - 100.0) < 0.2

    def test_top_scores_count(self):
        assert len(self.result["top_scores"]) == 3

    def test_top_scores_sorted_descending(self):
        probs = [s["prob"] for s in self.result["top_scores"]]
        assert probs == sorted(probs, reverse=True)

    def test_matrix_dimensions(self):
        assert len(self.result["matrix"]) == MAX_GOALS + 1
        for row in self.result["matrix"]:
            assert len(row) == MAX_GOALS + 1


# --- find_team tests ---

class TestFindTeam:
    def test_exact_match(self):
        result = find_team("Arsenal", SAMPLE_LEAGUE_DATA)
        assert result is not None
        assert result[0] == "Arsenal"

    def test_case_insensitive(self):
        result = find_team("arsenal", SAMPLE_LEAGUE_DATA)
        assert result is not None
        assert result[0] == "Arsenal"

    def test_partial_match(self):
        # "Manchester" is a substring of "Manchester United"
        result = find_team("Manchester", SAMPLE_LEAGUE_DATA)
        assert result is not None
        assert result[0] == "Manchester United"

    def test_not_found(self):
        result = find_team("Liverpool", SAMPLE_LEAGUE_DATA)
        assert result is None

    def test_whitespace_trimmed(self):
        result = find_team("  Arsenal  ", SAMPLE_LEAGUE_DATA)
        assert result is not None


# --- Full predict() test ---

class TestPredict:
    def test_end_to_end(self):
        result = predict("Arsenal", "Chelsea", SAMPLE_LEAGUE_DATA, SAMPLE_AVGS)
        assert result["home_team"] == "Arsenal"
        assert result["away_team"] == "Chelsea"
        assert result["home_xg"] > 0
        assert result["away_xg"] > 0
        assert 97.0 < result["home_win"] + result["draw"] + result["away_win"] <= 100.0

    def test_team_not_found_raises(self):
        import pytest
        with pytest.raises(ValueError, match="not found"):
            predict("Nonexistent FC", "Chelsea", SAMPLE_LEAGUE_DATA, SAMPLE_AVGS)
