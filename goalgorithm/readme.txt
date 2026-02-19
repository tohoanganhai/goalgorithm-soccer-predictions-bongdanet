=== GoalGorithm - Soccer Predictions ===
Contributors: goalgorithm
Tags: soccer, football, predictions, xg, poisson, statistics
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

xG-based soccer match predictions using Poisson distribution model via shortcode.

== Description ==

GoalGorithm uses Expected Goals (xG) data and the Poisson distribution to generate soccer match predictions. Simply add a shortcode to any page or post to display:

* Expected goals for each team
* Win/Draw/Loss probabilities
* Over/Under 2.5 goals probability
* Both Teams To Score (BTTS) probability
* Top 3 most likely exact scores
* Full 6x6 score probability heatmap grid

**League Prediction Table:**

* Upcoming fixtures with predicted scores
* Asian Handicap recommendations
* Over/Under picks
* Predicted winner highlights

**Supported Leagues:**

* Premier League
* La Liga
* Serie A
* Bundesliga
* Ligue 1

== Third-Party Services ==

This plugin connects to the following external service:

**Understat.com**

* **Service URL:** [https://understat.com](https://understat.com)
* **What is sent:** HTTP GET requests to fetch league team statistics and fixture data. No personal or user data is transmitted.
* **When:** Data is fetched when a shortcode is rendered and the local cache has expired (default: every 12 hours). Admins can also trigger a manual refresh from Settings > GoalGorithm.
* **Terms of Service:** [https://understat.com](https://understat.com)
* **Privacy Policy:** Understat.com does not publish a separate privacy policy. The plugin does not send any user data to this service.

== Installation ==

1. Upload the `goalgorithm` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GoalGorithm to configure default league and cache duration
4. Add shortcodes to any page or post

== Usage ==

= Single Match Prediction =

`[goalgorithm home="Arsenal" away="Chelsea"]`

`[goalgorithm league="12" home="Barcelona" away="Real Madrid"]`

= League Prediction Table =

`[goalgorithm_league league="9"]`

`[goalgorithm_league league="9" limit="20"]`

= League IDs =

* 9 = Premier League (default)
* 12 = La Liga
* 11 = Serie A
* 20 = Bundesliga
* 13 = Ligue 1

== Frequently Asked Questions ==

= Where does the data come from? =

Match statistics (xG, xGA) are fetched from Understat.com's public JSON API and cached locally using WordPress transients.

= How often is data refreshed? =

By default every 12 hours. You can change this in Settings > GoalGorithm (1-72 hours). You can also manually refresh from the admin settings page.

= Can I use this without an API key? =

Yes. No API key or account is required. The plugin uses Understat's publicly available data.

= What happens if Understat is down? =

The plugin serves cached data until the cache expires. If no cache exists and the fetch fails, a friendly error message is displayed instead of the prediction.

== Screenshots ==

1. Single match prediction card with xG, probabilities, and score grid
2. League prediction table with handicap and over/under picks
3. Admin settings page with cache management

== Changelog ==

= 1.2.0 =
* Add league prediction table shortcode [goalgorithm_league]
* Add Asian Handicap and Over/Under recommendations
* Add author signature footer to shortcode output

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

== Upgrade Notice ==

= 1.2.0 =
New [goalgorithm_league] shortcode for league-wide prediction tables with Asian Handicap and Over/Under.
