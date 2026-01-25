---
name: WordPress Code Quality Review
description: A comprehensive guide and checklist for reviewing WordPress plugin code, strictly aligned with the Official Plugin Handbook and Plugin Check (PCP) standards.
---

# WordPress Code Quality Review Skill

This skill unifies the **Official WordPress Plugin Handbook**, **Plugin Check (PCP) Rules**, and modern engineering best practices. Use this rubric for all code audits.

## 1. Structure & Metadata (Plugin Check / Handbook)

### File Structure
- **Folder Requirement**: The ZIP file must unzip into a folder (e.g., `my-plugin/my-plugin.php`), not loose files.
- **Main File Header**: Must contain standard headers (`Plugin Name`, `Version`, `License`, `Text Domain`).
- **Text Domain**: Must match the plugin slug (folder name) exactly.

### Readme Standards (PCP)
- **File Name**: `readme.txt` (Required for .org) or `README.md` (Acceptable for GitHub private repos, but `readme.txt` is preferred for standard compliance).
- **Stable Tag**: Must match the `Version` in the main PHP file header.
- **Sections**: Must include `== Description ==` and `== Installation ==`.

## 2. Security (PCP Critical Checks)

### Nonce Verification
- **Rule**: All state-changing actions (`POST` requests, AJAX calls, form submissions) MUST verify a nonce.
- **Check**: Look for `check_admin_referer()` or `check_ajax_referer()` at the very beginning of the handler.
- **Violation**: `ajax_run_agent` without nonce check is a CRITICAL auto-fail.

### Late Escaping (Output Security)
- **Rule**: All output must be escaped *at the point of printing*.
- **Good**: `echo esc_html( $variable );`
- **Bad**: `echo $variable;` (even if sanitized earlier).
- **Functions**: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` (for HTML).

### Input Sanitization
- **Rule**: All input (`$_GET`, `$_POST`) must be sanitized *before* usage.
- **Functions**: `sanitize_text_field()`, `absint()`, `sanitize_email()`.

### Capabilities (Permissions)
- **Rule**: All admin actions must check `current_user_can()`.
- **Target**: `manage_options` for settings, specialized caps for other roles.

## 3. Performance & Stability (Engineering Skills)

### Database (Critical)
- **No Dynamic LIKE with JSON**: Avoiding `meta_value LIKE '%"key":"value"%'`. This is a full-table scan anti-pattern.
- **Index Usage**: Always query by indexed columns (`post_status`, `post_type`, `meta_key`).
- **Anti-Pattern**: No queries inside loops (N+1 problem).

### HTTP & External Requests
- **Timeouts**: `wp_remote_get/post` MUST define a `timeout` argument (default is often too long).
- **Error Handling**: Must check `is_wp_error()` immediately after request.

### Enqueuing
- **Registration**: Scripts/Styles must be registered/enqueued using `wp_enqueue_script` hooks, NEVER hardcoded in HTML header.
- **Dependencies**: Declare dependencies (e.g., `jquery`) explicitly.

## Usage Protocol

When requested to **"Code Check"** or **"Audit"**:
1.  **Run Manual PCP**: Mentally simulate the "Plugin Check" tool running against the codebase.
2.  **Report Structure**:
    *   **[CRITICAL]**: Security holes, crashes, or strict Handbook violations (e.g., missing license).
    *   **[WARNING]**: Performance issues (LIKE queries), missing text domains, bad practices.
    *   **[INFO]**: UI suggestions, code style.
3.  **Fix Plan**: Provide concrete snippets for fixing Critical/Warning items.
