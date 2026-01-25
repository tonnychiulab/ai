=== InsightCosmos ===
Contributors: tonnychiulab
Tags: ai, openai, intelligence, agent, graph
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

An AI agent-powered WordPress plugin for automated intelligence gathering and analysis.

== Description ==

InsightCosmos is a powerful knowledge graph system that utilizes AI agents to fetch, analyze, and curate intelligence automatically.

**Key Features**
*   **Scout Agent**: Fetches contents from RSS feeds automatically.
*   **Analyst Agent**: Analyzes content using OpenAI GPT to extract summaries, scores, and tags.
*   **Curator Agent**: Generates daily intelligence reports based on high-value data.
*   **Knowledge Graph**: Stores data as nodes and edges for visualization (Visualization feature in development).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/insight-cosmos` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the 'InsightCosmos' -> 'Settings' screen to configure your OpenAI API Key and RSS Feeds.
4. Use the Manual Operations in settings to test specific agents.

== Frequently Asked Questions ==

= Do I need an OpenAI API Key? =
Yes, the Analyst Agent requires a valid OpenAI API Key to perform content analysis.

= Can I use other search providers? =
Currently, we support Serper.dev and Google Custom Search for the Scout Agent's search capabilities (if enabled).

== Changelog ==

= 1.0.9 =
*   UI Improvements: Sequential logic for manual operations.
*   UI Improvements: Open Report View in new tab.
*   Fixed: Plugin Headers and README consistency.

= 1.0.8 =
*   Security hardening (Added nonces, input sanitization).
*   Added Internationalization support (i18n).
*   Fixed Plugin Check errors.

= 1.0.0 =
*   Initial release.