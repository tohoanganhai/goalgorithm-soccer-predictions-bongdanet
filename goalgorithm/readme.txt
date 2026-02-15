=== GoalGorithm - Soccer Predictions ===
Contributors: goalgorithm
Tags: soccer, football, predictions, xg, poisson, betting, statistics
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

xG-based soccer match predictions using Poisson distribution model via shortcode.

== Description ==

GoalGorithm uses Expected Goals (xG) data from Understat.com and the Poisson distribution to generate soccer match predictions. Simply add a shortcode to any page or post to display:

* Expected goals for each team
* Win/Draw/Loss probabilities
* Over/Under 2.5 goals probability
* Both Teams To Score (BTTS) probability
* Top 3 most likely exact scores
* Full 6x6 score probability heatmap grid

**Supported Leagues:**

* Premier League
* La Liga
* Serie A
* Bundesliga
* Ligue 1

== Installation ==

1. Upload the `goalgorithm` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GoalGorithm to configure default league and cache duration
4. Add `[goalgorithm home="Team A" away="Team B"]` to any page or post

== Usage ==

**Basic:**
`[goalgorithm home="Arsenal" away="Chelsea"]`

**Specify league:**
`[goalgorithm league="12" home="Barcelona" away="Real Madrid"]`

**League IDs:**
* 9 = Premier League (default)
* 12 = La Liga
* 11 = Serie A
* 20 = Bundesliga
* 13 = Ligue 1

== Changelog ==

= 1.1.0 =
* Switch data source from FBref to Understat.com JSON API
* Fix Cloudflare 403 blocking issue with FBref scraping
* Remove MLS (not available on Understat)
* Remove DOMDocument dependency (no longer parsing HTML)

= 1.0.0 =
* Initial release
* Poisson distribution prediction engine
* Shortcode with styled prediction card
* Admin settings page with cache management
