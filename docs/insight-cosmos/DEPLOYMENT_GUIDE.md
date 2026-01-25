# InsightCosmos Deployment Guide

This document outlines the system requirements and installation steps for System Administrators and DevOps engineers deploying InsightCosmos.

## 1. System Requirements

Ensure the hosting environment meets the following criteria:

*   **WordPress**: Version 6.0 or higher.
*   **PHP**: Version 7.4 or higher (8.0+ recommended).
    *   Extensions: `json`, `mbstring`, `curl` (standard WP requirements).
*   **Database**: MySQL 5.7+ or MariaDB 10.3+.
*   **External Connectivity**:
    *   Server must be able to make outbound HTTPS requests to:
        *   `api.openai.com` (for Analyst Agent)
        *   Target RSS feed domains (for Scout Agent)
        *   `google.serper.dev` (if using Search features)

## 2. Installation Details

### Standard Installation
1.  **Upload**: Upload the `insight-cosmos` folder to `/wp-content/plugins/`.
2.  **Activate**: Activate via WP Admin or CLI:
    ```bash
    wp plugin activate insight-cosmos
    ```

### Database Initialization
Upon activation, the plugin runs `IC_DB::create_tables()` which executes `dbDelta`.
It creates two tables:
*   `[prefix]ic_nodes`
*   `[prefix]ic_edges`

**Troubleshooting**: If agents fail to save data, verify these tables exist. Check database user permissions (CREATE/ALTER).

## 3. Configuration Checkout

After installation, verify the following before handing over to users:

1.  **API Keys**: Ensure a valid OpenAI Key is provisioned.
2.  **Cron / Timeout Settings**:
    *   **Note**: Currently agents are triggered **manually** via AJAX.
    *   **Timeouts**: The "Analyst" agent calls OpenAI, which can be slow. Ensure your server's `max_execution_time` (PHP) and web server (Nginx/Apache) timeouts are sufficient (recommended > 60s) if processing large batches.
    *   *Future Update*: Automated Cron support is planned.

## 4. Debugging & Logs

*   **WP_DEBUG**: If enabled in `wp-config.php`, the `IC_Agent` class will log activity to `debug.log`.
*   **AJAX Errors**: If buttons in the Settings page hang, check the browser console (F12) > Network tab > `admin-ajax.php` response for PHP fatal errors or timeouts.

---
**Version**: 1.0.9
**License**: GPLv2 or later
