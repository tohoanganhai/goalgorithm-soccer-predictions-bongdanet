# GoalGorithm - Code Standards & Conventions

## Overview

This document defines coding standards for GoalGorithm across both WordPress Plugin (PHP) and MCP Server (Python) implementations. While languages differ, core principles remain consistent.

## Core Principles

1. **Readability Over Cleverness**: Code is read more than written; optimize for understanding
2. **Explicit Over Implicit**: Clear intent beats subtle optimizations
3. **Consistency Over Perfection**: Uniform style reduces cognitive load
4. **Documentation Over Code Comments**: Self-documenting code with strategic comments
5. **Separation of Concerns**: Each module has single, clear responsibility

## Naming Conventions

### PHP (WordPress Plugin)

**Classes**: PascalCase with "class-" prefix in filenames
```php
// ✓ Correct
class DataFetcher {
  public function __construct() {}
}
// File: includes/class-data-fetcher.php

// ✗ Wrong
class data_fetcher {
  public function __construct() {}
}
// File: includes/datafetcher.php
```

**Methods & Functions**: camelCase
```php
// ✓ Correct
public function getLeagueStats($leagueId) {}
private function calculateStrength($xG, $avgGoals) {}

// ✗ Wrong
public function get_league_stats($league_id) {}
public function GetLeagueStats($leagueId) {}
```

**Constants**: SCREAMING_SNAKE_CASE
```php
// ✓ Correct
const CACHE_DURATION = 12 * HOUR_IN_SECONDS;
const DEFAULT_LEAGUE = 9;

// ✗ Wrong
const cacheDuration = 12 * HOUR_IN_SECONDS;
const default_league = 9;
```

**Variables**: $camelCase with $ prefix
```php
// ✓ Correct
$homeXG = $homeAttack * $awayDefense * $leagueAverage;
$teamStats = $this->dataFetcher->getLeagueStats(9);

// ✗ Wrong
$home_xg = $home_attack * $away_defense * $league_average;
$HomeXG = $HomeAttack * $AwayDefense * $LeagueAverage;
```

**Hooks & Filters**: snake_case (WordPress convention)
```php
// ✓ Correct
add_action('wp_enqueue_scripts', 'goalgorithm_enqueue_styles');
apply_filters('goalgorithm_prediction_result', $prediction);

// ✗ Wrong
add_action('wpEnqueueScripts', 'goalgorithmEnqueueStyles');
```

### Python (MCP Server)

**Classes**: PascalCase
```python
# ✓ Correct
class DataFetcher:
    def __init__(self): pass

class PredictionEngine:
    async def predict_match(self): pass

# ✗ Wrong
class data_fetcher:
    pass

class dataFetcher:
    pass
```

**Functions & Methods**: snake_case
```python
# ✓ Correct
def get_league_stats(league_id: int) -> dict:
    pass

async def calculate_prediction(home_team: str, away_team: str) -> dict:
    pass

# ✗ Wrong
def getLeagueStats(league_id):
    pass

def GetLeagueStats(league_id):
    pass
```

**Constants**: SCREAMING_SNAKE_CASE
```python
# ✓ Correct
CACHE_DURATION_HOURS = 12
DEFAULT_LEAGUE_ID = 9
SUPPORTED_LEAGUES = {9, 12, 11, 20, 13}

# ✗ Wrong
cache_duration = 12
CacheDuration = 12
```

**Module-level Private**: _leading_underscore
```python
# ✓ Correct
_CACHE_DIR = Path.home() / '.cache' / 'goalgorithm'
_DEFAULT_TIMEOUT = 30

# Public
PUBLIC_CONSTANT = 42
```

## Code Organization

### PHP Class Structure

```php
<?php

namespace GoalGorithm;

use \Exception;

/**
 * DataFetcher: Handles data retrieval and caching from Understat API.
 */
class DataFetcher {

    // Constants (top)
    const CACHE_DURATION = 12 * HOUR_IN_SECONDS;
    const API_BASE_URL = 'https://understat.com/api/v1/';

    // Properties (middle)
    private $cache_key_prefix = 'goalgorithm_';

    /**
     * Constructor.
     */
    public function __construct() {}

    // Public methods (accessible)
    public function getLeagueStats($leagueId) {}

    // Protected methods (inheritance)
    protected function validateLeague($leagueId) {}

    // Private methods (internal)
    private function getCacheKey($leagueId) {}
}
```

### Python Module Structure

```python
"""
Data fetcher for Understat.com soccer statistics.

This module provides async HTTP access to Understat's public JSON API
with local file-based caching for 12 hours.
"""

from __future__ import annotations

import asyncio
import json
from pathlib import Path
from typing import Optional
import logging

import httpx

# Module-level constants
CACHE_DURATION_HOURS = 12
DEFAULT_TIMEOUT = 30
_CACHE_DIR = Path.home() / '.cache' / 'goalgorithm'

logger = logging.getLogger(__name__)


class DataFetcher:
    """Async client for Understat.com JSON API with file-based caching."""

    API_BASE_URL = 'https://understat.com/api/v1/'

    def __init__(self, cache_dir: Optional[Path] = None):
        """Initialize DataFetcher with optional cache directory."""
        self.cache_dir = cache_dir or _CACHE_DIR

    async def get_league_stats(self, league_id: int) -> dict:
        """Fetch team statistics for given league ID."""
        pass

    def _get_cache_path(self, league_id: int) -> Path:
        """Internal method to compute cache file path."""
        pass
```

## Documentation Standards

### PHP Documentation

**Class DocBlocks**:
```php
/**
 * DataFetcher: Fetch and cache team statistics from Understat API.
 *
 * Handles HTTP requests to Understat.com and caches results locally
 * for 12 hours using WordPress transients. Falls back to cached data
 * on API failure.
 *
 * @since 1.0.0
 */
class DataFetcher {
```

**Method DocBlocks**:
```php
/**
 * Get all team statistics for a league.
 *
 * Fetches xG, xGA, goals, and goals_against for all teams in a league.
 * Results are cached for 12 hours.
 *
 * @param int $leagueId League ID (9, 12, 11, 20, or 13)
 * @return array Array of team stats, or empty array on failure
 * @throws InvalidArgumentException If league ID is not supported
 *
 * @since 1.0.0
 */
public function getLeagueStats($leagueId) {
```

**Inline Comments**: Only for non-obvious logic
```php
// Calculate attack strength relative to league average
$homeAttack = $homeTeam['xG'] / $leagueAvgGoals;

// Apply Poisson CDF to calculate cumulative probability
$cumulative = 0;
for ($i = 0; $i <= $goals; $i++) {
    $cumulative += $this->poissonPmf($lambda, $i);
}
```

### Python Documentation

**Module Docstring**:
```python
"""
Prediction engine using Poisson distribution for soccer match outcomes.

This module calculates match probabilities based on Expected Goals (xG)
statistics. It computes team strength metrics, applies Poisson
distribution, and derives match outcomes (W/D/L, Over/Under, BTTS).

Example:
    >>> engine = PredictionEngine()
    >>> result = engine.predict_match(
    ...     home_stats={'xG': 1.5, 'xGA': 0.8, ...},
    ...     away_stats={'xG': 1.2, 'xGA': 1.1, ...},
    ...     league_avg_goals=2.7
    ... )
    >>> print(result['probabilities']['home_win'])
    0.523
"""
```

**Function Docstrings** (Google style):
```python
def poisson_pmf(self, lambda_: float, k: int) -> float:
    """
    Calculate Poisson probability mass function.

    Computes P(X = k) where X ~ Poisson(lambda).
    Used to calculate probability of scoring exactly k goals.

    Args:
        lambda_: Expected value (expected goals)
        k: Number of goals (0-5)

    Returns:
        Probability of exactly k goals occurring

    Raises:
        ValueError: If lambda < 0 or k < 0
    """
```

**Type Hints**: Always include
```python
# ✓ Correct
def calculate_strength(
    self,
    team_xg: float,
    league_avg: float
) -> float:
    """Calculate attack strength metric."""
    return team_xg / league_avg

async def get_league_stats(self, league_id: int) -> dict[str, Any]:
    """Fetch and cache league statistics."""
    pass

# ✗ Wrong
def calculate_strength(team_xg, league_avg):
    """Calculate attack strength metric."""
    return team_xg / league_avg
```

## Error Handling

### PHP Error Handling

**Validate Early**:
```php
// ✓ Correct
public function getLeagueStats($leagueId) {
    if (!in_array($leagueId, [9, 12, 11, 20, 13], true)) {
        throw new InvalidArgumentException("Unsupported league ID: {$leagueId}");
    }
    // Process...
}

// ✗ Wrong
public function getLeagueStats($leagueId) {
    // Process without validation...
    $result = $this->api->fetch("league/{$leagueId}/teams");
}
```

**Graceful Degradation** (WordPress context):
```php
// ✓ Correct - WordPress plugin prioritizes UX
public function getLeagueStats($leagueId) {
    $cached = $this->getCachedData($leagueId);
    if ($cached !== false) {
        return $cached;
    }

    try {
        $fresh = $this->fetchFromApi($leagueId);
        $this->setCacheData($leagueId, $fresh);
        return $fresh;
    } catch (Exception $e) {
        error_log("GoalGorithm fetch failed: {$e->getMessage()}");
        return []; // Empty result, UI handles gracefully
    }
}
```

### Python Error Handling

**Explicit Error Handling** (API context):
```python
# ✓ Correct - MCP server prioritizes clarity
async def get_league_stats(self, league_id: int) -> dict:
    """Fetch league statistics or raise explicit error."""
    if league_id not in SUPPORTED_LEAGUES:
        raise ValueError(f"Unsupported league: {league_id}")

    cached = self._load_cache(league_id)
    if cached and not self._is_cache_expired(cached):
        return cached['data']

    # Cache miss - fetch from API
    async with httpx.AsyncClient() as client:
        response = await client.get(
            f"{self.API_BASE_URL}/leagues/{league_id}/teams",
            timeout=DEFAULT_TIMEOUT
        )
        response.raise_for_status()
        data = response.json()

    self._save_cache(league_id, data)
    return data
```

**No Silent Failures**:
```python
# ✓ Correct
def predict_match(self, home: str, away: str) -> dict:
    if not home or not away:
        raise ValueError("Team names cannot be empty")
    # Process...

# ✗ Wrong
def predict_match(self, home: str, away: str) -> dict:
    if not home or not away:
        return {}  # Silent failure
    # Process...
```

## Testing Standards

### PHP Unit Testing

**Test Organization**:
```php
// tests/unit/DataFetcherTest.php
class DataFetcherTest extends WP_UnitTestCase {

    private DataFetcher $fetcher;

    public function setUp(): void {
        parent::setUp();
        $this->fetcher = new DataFetcher();
    }

    /**
     * Test getLeagueStats returns correct structure.
     */
    public function test_get_league_stats_returns_valid_structure(): void {
        $stats = $this->fetcher->getLeagueStats(9);

        $this->assertIsArray($stats);
        $this->assertNotEmpty($stats);
        $this->assertArrayHasKey('team_name', $stats[0]);
        $this->assertArrayHasKey('xG', $stats[0]);
    }

    /**
     * Test invalid league ID throws exception.
     */
    public function test_get_league_stats_throws_on_invalid_league(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->fetcher->getLeagueStats(999);
    }
}
```

### Python Unit Testing

**Test Organization** (pytest):
```python
# tests/test_data_fetcher.py
import pytest
from unittest.mock import AsyncMock, patch

class TestDataFetcher:
    """Test suite for DataFetcher async client."""

    @pytest.fixture
    async def fetcher(self):
        """Provide DataFetcher instance for tests."""
        return DataFetcher()

    @pytest.mark.asyncio
    async def test_get_league_stats_returns_valid_structure(self, fetcher):
        """Verify league stats have required fields."""
        with patch('httpx.AsyncClient.get') as mock_get:
            mock_get.return_value.json.return_value = [
                {'team_name': 'Arsenal', 'xG': 1.5, 'xGA': 0.8}
            ]

            stats = await fetcher.get_league_stats(9)

            assert isinstance(stats, list)
            assert stats[0]['team_name'] == 'Arsenal'
            assert 'xG' in stats[0]

    @pytest.mark.asyncio
    async def test_get_league_stats_raises_on_invalid_league(self, fetcher):
        """Verify invalid league ID raises ValueError."""
        with pytest.raises(ValueError):
            await fetcher.get_league_stats(999)
```

**Test Naming**:
```python
# ✓ Good: Describes what is tested
def test_poisson_pmf_returns_valid_probability():
    pass

def test_predict_match_raises_on_missing_stats():
    pass

# ✗ Poor: Vague or implementation-focused
def test_poisson():
    pass

def test_valid_input():
    pass
```

## Code Review Checklist

### All Changes
- [ ] Code follows naming conventions for the language
- [ ] Functions/classes have documentation
- [ ] Error handling is appropriate (graceful degradation vs explicit errors)
- [ ] No silent failures or ignored exceptions
- [ ] Tests pass (if applicable)

### Algorithm Changes
- [ ] Poisson math changes applied to BOTH implementations (PHP + Python)
- [ ] Comments explain mathematical reasoning
- [ ] Test cases verify identical results across languages
- [ ] Documentation updated with formula changes

### New Features
- [ ] Code follows separation of concerns (single responsibility)
- [ ] Appropriate error messages for failures
- [ ] Type hints included (Python) or PHPDoc (PHP)
- [ ] Test coverage for new functionality

### Performance-Critical Code
- [ ] Measured performance vs targets
- [ ] Caching strategy documented
- [ ] Large data structures optimized
- [ ] O(n) complexity noted in comments if n is large

## Performance Guidelines

### PHP (WordPress)
```php
// ✓ Correct: Cache loop results
$predictions = [];
foreach ($teams as $team) {
    if (!isset($this->cache[$team['id']])) {
        $this->cache[$team['id']] = $this->expensiveCalculation($team);
    }
    $predictions[] = $this->cache[$team['id']];
}

// ✗ Wrong: Repeated expensive calculations
$predictions = array_map(
    fn($team) => $this->expensiveCalculation($team),
    $teams
);
```

### Python (MCP Server)
```python
# ✓ Correct: Use built-in efficient operations
import json
from functools import lru_cache

@lru_cache(maxsize=5)
async def get_league_stats(self, league_id: int) -> dict:
    """Cache decorator for repeated requests."""
    return await self._fetch_api(league_id)

# ✗ Wrong: Repeated API calls without caching
async def get_league_stats(self, league_id: int) -> dict:
    return await self._fetch_api(league_id)
```

## File Organization Guidelines

### PHP File Size
- **Target**: Keep classes under 300 lines
- **Maximum**: No class should exceed 500 lines
- **Indicator**: If file exceeds 300 lines, split into smaller classes

### Python File Size
- **Target**: Keep modules under 200 lines
- **Maximum**: No module should exceed 400 lines
- **Indicator**: If file exceeds 200 lines, extract utility functions or classes

### When to Split a Class/Module
1. Has multiple responsibilities (SRP violation)
2. Exceeds target line count
3. Tests would be clearer if split
4. Different deployment/reuse scenarios

## Git Commit Standards

### Commit Message Format
```
<type>(<scope>): <subject>

<body>

<footer>
```

**Type**: feat, fix, docs, refactor, test, perf, chore
**Scope**: data-fetcher, prediction-engine, shortcode, mcp-server, etc.
**Subject**: Present tense, imperative mood

**Examples**:
```
feat(prediction-engine): add caching for poisson calculations
fix(data-fetcher): handle missing xGA field from api response
docs(system-architecture): add data flow diagrams
test(prediction-engine): add edge cases for zero goals
```

### Commit Guidelines
- One logical change per commit
- Include tests with code changes
- Link issue number if applicable: `Closes #123`
- Keep commits under 400 lines changed
- Write clear, specific messages (avoid "update stuff")

## Linting & Formatting

### PHP (if linting enabled)
- **Tool**: phpcs with WordPress coding standards
- **Configuration**: phpcs.xml.dist in repo root
- **Bypass**: Only with explicit comment `// phpcs:ignore Rule.Name`

### Python
- **Tool**: pylint or flake8
- **Configuration**: pyproject.toml or setup.cfg
- **Bypass**: Only with explicit comment `# pylint: disable=rule-name`

## Language-Specific Patterns

### PHP Patterns

**Dependency Injection**:
```php
// ✓ Correct: Injected dependencies
class ShortcodeRenderer {
    public function __construct(
        DataFetcher $fetcher,
        PredictionEngine $engine
    ) {
        $this->fetcher = $fetcher;
        $this->engine = $engine;
    }
}

// ✗ Wrong: Hard-coded dependencies
class ShortcodeRenderer {
    public function __construct() {
        $this->fetcher = new DataFetcher();
    }
}
```

### Python Patterns

**Async Context Managers**:
```python
# ✓ Correct: Proper resource cleanup
async def get_league_stats(self, league_id: int) -> dict:
    async with httpx.AsyncClient() as client:
        response = await client.get(f"{self.API_BASE_URL}...")
        return response.json()

# ✗ Wrong: Resource leak
async def get_league_stats(self, league_id: int) -> dict:
    client = httpx.AsyncClient()
    response = await client.get(f"{self.API_BASE_URL}...")
    return response.json()
```

## Deprecation Policy

### How to Deprecate
1. Mark with `@deprecated` (PHP) or `DeprecationWarning` (Python)
2. Add comment explaining replacement
3. Document in changelog
4. Support for 2 minor versions
5. Remove in next major version

**PHP Example**:
```php
/**
 * @deprecated 1.2.0 Use getLeagueStats() instead
 */
public function getLeagueData($leagueId) {
    return $this->getLeagueStats($leagueId);
}
```

**Python Example**:
```python
import warnings

def old_function_name():
    """Deprecated. Use new_function_name() instead."""
    warnings.warn(
        "old_function_name is deprecated, use new_function_name",
        DeprecationWarning,
        stacklevel=2
    )
    return new_function_name()
```

