# InsightCosmos Developer Guide

This guide is intended for RD engineers and developers maintaining or extending the InsightCosmos plugin.

## 1. System Architecture

InsightCosmos follows a modular architecture based on the **Agentic Workflow**.

### Core Components
*   **`InsightCosmos` (Main Class)**: Initializes the plugin, hooks, and CPTs.
*   **`IC_Agent` (Abstract Class)**: The base class for all agents. Handles logging and DB operations.
*   **`IC_DB`**: Manages custom database tables.
*   **`IC_Settings`**: Handles the Admin UI and AJAX requests for manual agent execution.

### Database Schema
The plugin uses two custom tables to store the Knowledge Graph:

1.  **`wp_ic_nodes`**:
    *   `id`: Primary Key.
    *   `label`: The name or title of the node.
    *   `metadata`: JSON field storing all flexible attributes (url, content, summary, score, etc.).
2.  **`wp_ic_edges`**:
    *   `id`: Primary Key.
    *   `source_id`: FK to `ic_nodes`.
    *   `target_id`: FK to `ic_nodes`.
    *   *Used to build relationships between entities (e.g., Article -> mentions -> Company).*

---

## 2. Agent Framework

All agents perform a specific unit of work. They extend `IC_Agent`.

### The `IC_Agent` Base Class
File: `includes/abstract-ic-agent.php`

**Key Methods:**
*   `run()`: (Abstract) The main entry point for the agent's logic.
*   `save_node($label, $type, $metadata)`: Helper to insert data into `wp_ic_nodes`.
*   `update_node_metadata($id, $data)`: Helper to update JSON metadata.

### Existing Agents
*   **`IC_Scout`** (`agents/class-ic-scout.php`):
    *   Fetches RSS feeds defined in options.
    *   Limit: Hardcoded to fetch top 5 items per feed (for MVP).
    *   Saves nodes with `type: 'raw_data'`.
*   **`IC_Analyst`** (`agents/class-ic-analyst.php`):
    *   Queries `ic_nodes` for unanalyzed data.
    *   Calls OpenAI API.
    *   Updates nodes with `summary`, `score`, `tags`.
*   **`IC_Curator`** (`agents/class-ic-curator.php`):
    *   Filters analyzed nodes (e.g., score > 7).
    *   Compiles text via LLM? (or template).
    *   Creates a `wp_insert_post` for `ic_report` CPT.

---

## 3. Extending the Plugin

### How to add a new Agent (e.g., "SocialMediaScout")

1.  **Create Class**: Create `includes/agents/class-ic-social-scout.php`.
2.  **Extend**:
    ```php
    class IC_Social_Scout extends IC_Agent {
        public function run() {
            // Your logic here
            // $this->save_node('Post Title', 'social_post', ['url' => ...]);
        }
    }
    ```
3.  **Register**:
    *   Require the file in `insight-cosmos.php`.
    *   Add a trigger button in `IC_Settings::render_settings_page()`.
    *   Add handler in `IC_Settings::ajax_run_agent()`.

### API & Hooks
*   **REST API**: `IC_REST` class registers endpoints at `ic/v1`. Currently used for the frontend visualization to fetch nodes.
*   **Cron Jobs**: Currently not implemented. To automate, you would hook `IC_Agent::run()` methods to `wp_schedule_event`.

## 4. Frontend / Visualization
*   **Tech Stack**: Cytoscape.js (Graph library).
*   **Assets**: `assets/script.js`, `assets/style.css`.
*   **Logic**: The dashboard fetches nodes via REST API and renders the graph.

---

## 5. Development Notes
*   **Security**: Always use `wp_create_nonce` / `check_ajax_referer` for AJAX.
*   **I18n**: All strings should be wrapped in `__('text', 'insight-cosmos')`.
*   **Error Handling**: Use `IC_Agent::log()` (currently writes to debug log if `WP_DEBUG` is true) or `wp_send_json_error` for UI feedback.
