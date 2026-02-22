# GoalGorithm - Codebase Summary

## Project Structure

```
goalgorithm-soccer-predictions-bongdanet/
├── goalgorithm/                          # WordPress Plugin (~1,343 LOC)
│   ├── goalgorithm.php                   # Plugin bootstrap, shortcode registration
│   ├── includes/
│   │   ├── class-data-fetcher.php        # Understat API client + caching
│   │   ├── class-prediction-engine.php   # Poisson distribution math
│   │   ├── class-shortcode-renderer.php  # Match prediction card UI
│   │   ├── class-league-table-renderer.php # League predictions table
│   │   ├── class-admin-settings.php      # Admin panel + cache control
│   │   └── class-translations.php        # Multi-language text management
│   ├── assets/css/
│   │   └── goalgorithm-frontend.css      # Frontend styling
│   └── readme.txt                        # WordPress plugin readme
│
├── mcp-server/                           # Python MCP Server (~200 LOC)
│   ├── src/goalgorithm_mcp/
│   │   ├── __init__.py                   # Package initialization
│   │   ├── server.py                     # FastMCP server bootstrap
│   │   ├── data_fetcher.py               # Understat API client (Python port)
│   │   ├── prediction_engine.py          # Poisson math (Python port)
│   │   └── types.py                      # Type definitions for tools
│   ├── tests/
│   │   ├── test_data_fetcher.py          # API client tests
│   │   └── test_prediction_engine.py     # Prediction logic tests
│   ├── pyproject.toml                    # Package metadata + dependencies
│   └── README.md                         # MCP server documentation
│
├── docs/                                 # Project documentation
│   ├── project-overview-pdr.md           # This file: PDR + vision
│   ├── codebase-summary.md               # You are here
│   ├── system-architecture.md            # Technical architecture
│   └── code-standards.md                 # Code conventions (optional)
│
└── README.md                             # Main project readme

```

## Core Components

### WordPress Plugin (`goalgorithm/`)

#### Main Entry Point
**File**: `goalgorithm/goalgorithm.php`
- Plugin bootstrap and activation hooks
- Registers `[goalgorithm]` and `[goalgorithm_league]` shortcodes
- Enqueues CSS assets
- Initializes admin settings page

#### Data Fetcher (`class-data-fetcher.php`)
- **Purpose**: Fetch team xG/xGA statistics from Understat.com JSON API
- **Key Methods**:
  - `get_league_stats()`: Fetch all teams' stats for a league
  - `get_cached_data()`: Retrieve from local WordPress cache
  - `set_cache()`: Store data with 12-hour expiration
- **Dependencies**: wp_remote_get(), WordPress transients API
- **Error Handling**: Returns empty array on API failure; uses stale cache as fallback

#### Prediction Engine (`class-prediction-engine.php`)
- **Purpose**: Calculate match outcome probabilities using Poisson distribution
- **Key Methods**:
  - `calculate_strength()`: Compute attack/defense metrics from xG data
  - `calculate_expected_goals()`: Apply formula: `HomeXG = HomeAttack * AwayDefense * LeagueAvg`
  - `poisson_pmf()`: Poisson probability mass function (0-5 goals)
  - `build_score_matrix()`: Generate 6x6 scoreline probability grid
  - `derive_outcomes()`: Calculate W/D/L, Over/Under 2.5, BTTS from matrix
- **Core Formula**: `P(team scores k goals) = (lambda^k * e^-lambda) / k!`
- **Output**: Prediction object with probabilities, top 3 scores, matrix grid

#### Shortcode Renderer (`class-shortcode-renderer.php`)
- **Purpose**: Render single match prediction card via `[goalgorithm]` shortcode
- **Input Parameters**: `home`, `away`, `league` (optional, defaults to 9/EPL)
- **Output**: HTML card with prediction card styling
- **Features**: Display W/D/L %, Over/Under %, BTTS %, top 3 scores, score heatmap

#### League Table Renderer (`class-league-table-renderer.php`)
- **Purpose**: Render upcoming fixtures table via `[goalgorithm_league]` shortcode
- **Input Parameters**: `league` (required), `limit` (optional, default 20)
- **Output**: HTML table with upcoming fixtures and predictions
- **Features**: Team badges, predicted scores, Asian Handicap, Over/Under picks

#### Admin Settings (`class-admin-settings.php`)
- **Purpose**: Plugin configuration and cache management
- **Features**:
  - Cache flush button (clear all data)
  - Displayed cache age and last update timestamp
  - Settings stored in WordPress options table

#### Translations (`class-translations.php`)
- **Purpose**: Manage multi-language support (8 languages)
- **Method**: Load translated strings based on WordPress site language
- **Supported Languages**: EN, ES, IT, DE, FR, PT, JA, VI

#### Styles (`assets/css/goalgorithm-frontend.css`)
- Match prediction card styling
- League table responsive design
- Score heatmap grid styling
- Dark/light mode compatibility

### MCP Server (`mcp-server/`)

#### Server Bootstrap (`server.py`)
- **Purpose**: FastMCP application setup and tool registration
- **Framework**: FastMCP 2.0+
- **Entry Point**: `main()` function called via command `goalgorithm-mcp`
- **Tools Registered**:
  1. `predict_match`: Match outcome prediction
  2. `list_leagues`: Available leagues reference
  3. `get_league_table`: Team statistics for a league

#### Data Fetcher (`data_fetcher.py`)
- **Purpose**: Python port of WordPress DataFetcher class
- **Key Methods**:
  - `fetch_league_stats(league_id)`: Async HTTP request to Understat API
  - `get_cached_data()`: Return cached data from last 12 hours
  - `save_cache()`: Store to local JSON file
- **Dependencies**: httpx (async HTTP), time-based cache validation
- **Error Handling**: Raises exceptions on API failure; client must handle retry

#### Prediction Engine (`prediction_engine.py`)
- **Purpose**: Python port of WordPress PredictionEngine class
- **Key Methods**: (same interface as PHP version)
  - `calculate_strength()`
  - `calculate_expected_goals()`
  - `poisson_pmf()`
  - `build_score_matrix()`
  - `derive_outcomes()`
- **Math Library**: Uses Python's `math.factorial()` and `math.exp()`

#### Type Definitions (`types.py`)
- **Pydantic Models** for tool input/output validation:
  - `PredictMatchInput`: home_team, away_team, league_id/slug
  - `PredictionResult`: Probabilities, scores, matrix
  - `LeagueInfo`, `TeamStats`: Data structures for league/team info

#### Tests
- **test_data_fetcher.py**: Mock Understat API responses, cache behavior
- **test_prediction_engine.py**: Poisson calculations, score matrix generation
- **Framework**: pytest + pytest-asyncio
- **Coverage Target**: >80% of core logic

## Key Algorithms

### Prediction Model

#### 1. Strength Calculation
```
HomeAttack = (HomeTeam.xG / League.AvgGoals)
HomeDefense = (HomeTeam.xGA / League.AvgGoalsAgainst)
AwayAttack = (AwayTeam.xG / League.AvgGoals)
AwayDefense = (AwayTeam.xGA / League.AvgGoalsAgainst)
```

#### 2. Expected Goals
```
HomeXG = HomeAttack * AwayDefense * League.AvgGoals
AwayXG = AwayAttack * HomeDefense * League.AvgGoals
```

#### 3. Poisson Probability
```
P(X = k) = (lambda^k * e^-lambda) / k!
where X = goals scored, k = 0-5, lambda = expected goals
```

#### 4. Match Outcomes
- **Win**: P(Home > Away)
- **Draw**: P(Home = Away)
- **Loss**: P(Home < Away)
- **Over 2.5**: P(Home + Away > 2.5)
- **BTTS**: P(Home > 0 AND Away > 0)

## Dependencies

### WordPress Plugin
- **WordPress**: 5.0+
- **PHP**: 7.4+
- **External APIs**: Understat.com (public, no auth)
- **No External Libraries**: Uses only WordPress built-in functions

### MCP Server
- **Python**: 3.10+
- **fastmcp**: >=2.0, <4
- **httpx**: >=0.27 (async HTTP client)
- **pytest**: >=8.0 (dev dependency)
- **pytest-asyncio**: >=0.23 (dev dependency)

## Data Flow

```
1. User/AI requests prediction or league data
   ↓
2. DataFetcher checks local cache (12-hour expiration)
   ├─ Cache HIT → Return cached data (instant)
   └─ Cache MISS → Fetch from Understat API
   ↓
3. Parse JSON response and store in cache
   ↓
4. PredictionEngine processes stats:
   - Calculate team strength metrics
   - Apply Poisson distribution
   - Build score probability matrix
   ↓
5. Derive match outcomes and top 3 scores
   ↓
6. Format and return to requestor
```

## Testing Strategy

### WordPress Plugin
- Manual testing via shortcodes in test WordPress instance
- Verified against live Understat data
- Admin settings page functionality

### MCP Server
- Unit tests for `data_fetcher.py` (API mocking)
- Unit tests for `prediction_engine.py` (algorithm validation)
- Integration tests for server startup and tool registration
- Run via: `pytest tests/`

## Code Quality Standards

### Both Implementations
1. **Clear naming**: Variable/method names describe purpose
2. **Separation of concerns**: DataFetcher, PredictionEngine, UI are distinct
3. **Error handling**: Graceful failures with informative messages
4. **Comments**: Algorithm steps and non-obvious logic explained
5. **Consistency**: Same algorithm logic across PHP and Python

### Modularization
- **WordPress Plugin**: 1,343 LOC split across 6 PHP classes + CSS
- **MCP Server**: ~200 LOC split across 5 Python modules
- **Test Files**: Comprehensive coverage of core logic

## Integration Points

### Between Components
- **Data Contract**: DataFetcher outputs JSON structure consumed by PredictionEngine
- **Algorithm Consistency**: Same Poisson formula applied in both PHP and Python
- **League IDs**: Fixed IDs (9, 12, 11, 20, 13) used across both systems

### With External Systems
- **Understat.com**: JSON API for team xG/xGA statistics (read-only)
- **WordPress**: Plugin system, shortcodes, admin interface, transients API
- **Claude Desktop/Code**: MCP protocol for tool invocation

## File Size Reference

| File | Lines | Purpose |
|------|-------|---------|
| goalgorithm.php | ~200 | Plugin bootstrap |
| class-data-fetcher.php | ~250 | API client |
| class-prediction-engine.php | ~400 | Poisson math |
| class-shortcode-renderer.php | ~300 | Match card UI |
| class-league-table-renderer.php | ~150 | Table UI |
| class-admin-settings.php | ~40 | Settings page |
| server.py | ~80 | MCP server |
| data_fetcher.py | ~60 | Python API client |
| prediction_engine.py | ~80 | Python Poisson math |

## Future Modularization Opportunities

1. **Common Algorithm Library**: Extract Poisson math to language-agnostic format
2. **API Client Abstraction**: Create interface for alternative data sources
3. **Caching Layer**: Unified cache interface (Redis, file, memory)
4. **Configuration**: External config for league IDs, cache duration, API endpoints
5. **Metrics Collection**: Track prediction accuracy, API performance, user engagement

## How to Navigate the Code

1. **Understanding the Algorithm**: Start with `class-prediction-engine.php` comments
2. **Adding a New League**: Update league IDs in `data_fetcher.php` + language strings
3. **Modifying Prediction Logic**: Update both `class-prediction-engine.php` and `prediction_engine.py`
4. **Adding MCP Tool**: Register in `server.py`, implement logic in appropriate module
5. **Debugging Predictions**: Check cache first, then verify Understat API response in DataFetcher

