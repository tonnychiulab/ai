# InsightCosmos User Manual

Welcome to **InsightCosmos**, your AI-powered intelligence gathering assistant. This plugin helps you track RSS feeds, analyze content using AI, and generate summarized intelligence reports automatically.

## 1. Getting Started

After the plugin is installed by your administrator, you will see a new menu item **InsightCosmos** in your WordPress dashboard.

### Initial Setup
Before using the agents, you must configure the basic settings:

1.  Navigate to **InsightCosmos > Settings**.
2.  **OpenAI API Key** (Required):
    *   You need a valid OpenAI API Key for the **Analyst Agent** to analyze content.
    *   Enter your key and click **Test Key** to verify it works.
    *   [Get your OpenAI API Key here](https://platform.openai.com/api-keys).
3.  **Search API Key** (Optional):
    *   If you enable web search features (Scout), enter your Serper.dev or Google Custom Search key.
    *   This is currently optional for RSS-based workflows.
4.  **RSS Feeds**:
    *   Enter the URLs of the RSS feeds you want to monitor.
    *   One URL per line.
    *   *Example:*
        ```
        https://feeds.feedburner.com/TechCrunch
        https://newsletter.pragmaticengineer.com/feed
        ```
5.  Click **Save Changes**.

---

## 2. Operating the Agents (Manual Mode)

InsightCosmos operates using three specialized "Agents". Currently, these are triggered manually from the Settings page.

Navigate to **InsightCosmos > Settings** and scroll down to **Manual Operations**.

### Step 1: Scout Agent (The Gatherer)
*   **Action**: Click **Run Scout**.
*   **What it does**: The Scout checks your configured RSS feeds for new articles. It fetches the latest content and saves it as "Raw Data" into the system database.
*   **When to run**: Run this once a day to gather fresh news.

### Step 2: Analyst Agent (The Thinker)
*   **Action**: Click **Run Analyst**. (This button becomes active after Scout finishes).
*   **What it does**: The Analyst takes the raw data found by the Scout and sends it to OpenAI (GPT). It reads the articles, generates a summary, assigns tags, and gives an "Importance Score" (1-10).
*   **Note**: This step consumes OpenAI API credits.

### Step 3: Curator Agent (The Reporter)
*   **Action**: Click **Run Curator**. (This button becomes active after Analyst finishes).
*   **What it does**: The Curator looks at the analyzed data, picks the most important insights (high score), and compiles them into a formulated Intelligence Report.
*   **Result**: A new "Report" is created in standard WordPress post format.

---

## 3. Viewing Intelligence Reports

Once the Curator has finished its job, you can view the generated reports.

1.  Navigate to **InsightCosmos > Intelligence Reports** (or under the main InsightCosmos menu).
2.  You will see a list of reports sorted by date.
3.  Click on a report title to view it.
4.  Reports contain:
    *   **Executive Summary**: A high-level overview of the day's intelligence.
    *   **Key Insights**: Bullet points of important findings.
    *   **Source Links**: References to the original articles.

## 4. Visualization (Beta)

Navigate to **InsightCosmos > InsightCosmos** (Dashboard).
*   This area displays a "Knowledge Graph" visualization of your data nodes.
*   *Note: This feature is currently in active development.*
