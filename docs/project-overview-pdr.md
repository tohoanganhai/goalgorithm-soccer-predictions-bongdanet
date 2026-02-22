# GoalGorithm - Project Overview & Product Development Requirements

## Executive Summary

GoalGorithm is a soccer prediction system that applies advanced statistical modeling to generate match outcome probabilities. It uses Expected Goals (xG) data from Understat.com and applies Poisson distribution to calculate precise match predictions.

The project consists of two integrated components:
- **WordPress Plugin**: User-facing shortcodes for match predictions and league tables
- **MCP Server**: Standalone Python implementation exposing prediction engine as MCP tools for Claude Desktop/Code

## Project Vision

Enable data-driven soccer predictions across multiple platforms by separating the prediction engine from WordPress, making it accessible to AI assistants and external applications via standardized MCP interface.

## Core Features

### WordPress Plugin (goalgorithm/)
- **Match Predictions**: Shortcode-based UI for single match predictions
- **League Tables**: Upcoming fixtures with predictions across multiple leagues
- **Multi-League Support**: 5 major European leagues (EPL, La Liga, Serie A, Bundesliga, Ligue 1)
- **Admin Interface**: Cache management and settings configuration
- **Multi-Language Support**: 8 language translations
- **Data Caching**: 12-hour local cache to minimize API calls

### MCP Server (mcp-server/)
- **FastMCP Implementation**: Exposes prediction engine as standardized MCP tools
- **Three Core Tools**:
  - `predict_match`: Single match outcome predictions
  - `list_leagues`: Available leagues and configurations
  - `get_league_table`: Team statistics and xG rankings
- **Standalone Deployment**: Can run independently of WordPress
- **PyPI Distribution**: Installable package for easy integration

## Functional Requirements

### Data Pipeline
1. Fetch team xG/xGA statistics from Understat.com JSON API
2. Calculate attack/defense strength relative to league average
3. Compute expected goals using formula: `HomeXG = HomeAttack * AwayDefense * LeagueAvg`
4. Apply Poisson distribution for goal probability distribution
5. Generate 6x6 score probability matrix covering all scorelines (0-5 goals)
6. Derive match outcomes: Win/Draw/Loss, Over/Under 2.5, BTTS, top 3 likely scores

### Supported Leagues
| ID | League | Slug |
|----|--------|------|
| 9  | Premier League | EPL |
| 12 | La Liga | LaLiga |
| 11 | Serie A | SerieA |
| 20 | Bundesliga | Bundesliga |
| 13 | Ligue 1 | Ligue1 |

### Data Source & Caching
- **Primary Source**: Understat.com public JSON API (no authentication required)
- **Cache Duration**: 12 hours (configurable)
- **Cache Storage**: Local files (WordPress) or in-memory + disk (MCP Server)
- **Fallback**: Returns cached data if API unavailable

## Non-Functional Requirements

### Performance
- Match prediction response: <2 seconds
- League table generation: <5 seconds
- Cache hit response: <500ms

### Reliability
- Graceful API failure handling
- Fallback to cached data when unavailable
- Clear error messaging to users

### Compatibility
- **WordPress Plugin**: PHP 7.4+, WordPress 5.0+
- **MCP Server**: Python 3.10+
- **Data Format**: JSON with consistent schema across implementations

### Security
- No authentication required (uses public APIs)
- Read-only data operations
- No user input affecting calculations (team names normalized)

### Maintenance
- Consistent prediction logic across both implementations
- Shared test coverage (unit tests in both)
- Clear documentation for algorithm changes

## Architecture Decisions

### Separated Implementations
Why maintain two separate codebases (PHP and Python)?

1. **Platform Isolation**: WordPress plugin users don't need Python dependencies
2. **Deployment Flexibility**: MCP server can run independently without WordPress
3. **Technology Appropriateness**: PHP for WordPress ecosystem, Python for AI assistants
4. **Code Reuse**: Core algorithms replicated with language-appropriate implementations

### Understat.com as Sole Data Source
Why not use BongdaNET API?

1. **xG Data Gap**: BongdaNET only exposes 5 curated "hot matches" without xG statistics
2. **League Filtering**: Understat supports all 5 supported leagues; BongdaNET lacks this
3. **Data Quality**: Understat is the established xG data standard for football analytics
4. **Reliability**: Public JSON API with consistent availability

### Poisson Distribution Model
Why Poisson rather than other probabilistic models?

1. **Statistical Fit**: Goal counts in soccer follow near-Poisson distribution
2. **Computational Efficiency**: Fast calculation suitable for real-time requests
3. **Proven Track Record**: Widely used in sports analytics literature
4. **Simplicity**: Understandable to users without deep statistical background

## Success Metrics

1. **Prediction Accuracy**: Track win/draw/loss prediction accuracy month-over-month
2. **API Uptime**: Understat.com API availability and response times
3. **User Adoption**: Active WordPress installations (via stats tracking)
4. **Code Quality**: >80% test coverage in both implementations
5. **Documentation**: All algorithms explained with examples and validation

## Known Limitations

1. **Data Lag**: Understat updates may lag by 1-2 days
2. **In-Season Volatility**: Early season predictions less reliable (small sample size)
3. **Injury Data**: No injury/roster information factored into predictions
4. **Tactical Shifts**: Algorithm can't adapt to sudden tactical or organizational changes
5. **Minor Leagues**: Only 5 major European leagues supported

## Version & Status

- **Current Version**: 0.1.0 (MCP Server), 1.0.0 (WordPress Plugin)
- **Status**: Production (WordPress), Beta (MCP Server)
- **Last Updated**: February 2026
- **Owner**: Tô Hoàng Anh (@tohoanganhai)
- **Repository**: [tohoanganhai/goalgorithm-soccer-predictions-bongdanet](https://github.com/tohoanganhai/goalgorithm-soccer-predictions-bongdanet)

## Roadmap

### Phase 1: Foundation (Complete)
- WordPress plugin with match predictions
- League prediction tables
- Multi-language support
- Admin settings interface

### Phase 2: Modularization (Current)
- Extract core prediction logic to reusable module
- Create MCP server implementation
- Establish test coverage across implementations

### Phase 3: Enhancement (Planned)
- Additional performance metrics (expected shots, assists)
- Historical prediction accuracy tracking
- Advanced filters (recent form, head-to-head)
- API rate limiting and monitoring

### Phase 4: Integration (Future)
- Real-time odds comparison
- Betting recommendation system
- User preference profiles
- Result feedback loop for model improvement
