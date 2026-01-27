# WP Core Knowledge Base

## Description
包含 WordPress 開發的核心規範、安全標準與最佳實踐。這是所有開發任務的基礎知識來源。

## Usage
當 Agent 編寫任何 PHP、JS 代碼或進行架構規劃時，**必須** 參考此 Skill。

## Knowledge Data

### 安全性 (Security)
- **Nonce 驗證**：所有 Form 提交與 AJAX 請求必須檢查 Nonce (`wp_verify_nonce`).
- **權限檢查**：使用 `current_user_can()` 檢查操作權限。
- **資料庫**：必須使用 `$wpdb->prepare()`。

### 效能 (Performance)
- 避免在迴圈中執行 SQL 查詢。
- 使用 Transients API (`set_transient`, `get_transient`) 緩存昂貴的查詢結果。
- 載入 CSS/JS 時必須使用 `wp_enqueue_scripts` 並指定版本號。

### 架構 (Architecture)
- 採用物件導向 (OOP) 結構。
- 避免使用全域變數，改用依賴注入 (Dependency Injection) 或 Singleton 模式。
- Hook 命名應包含外掛前綴，例如 `do_action('my_plugin_after_save')`。

### 資源
- 參考檔案：[coding-standards.md](./coding-standards.md) (您可將 Everything WP 的規則檔放入同目錄並參照)