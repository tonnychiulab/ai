# WordPress Plugin Initializer

## Description
協助使用者從零開始初始化一個標準的 WordPress 外掛結構。包含目錄結構、Composer 配置、PHPUnit 測試環境與 GitHub Actions 工作流程。

## Usage
當使用者要求「建立新外掛」、「初始化外掛」或輸入 `/init-plugin` 時觸發。

## Instructions
身為 WordPress 外掛架構師，請依照以下步驟執行：

1.  **需求確認**：
    - 詢問外掛名稱 (Plugin Name)
    - 詢問命名空間 (Namespace, e.g., `Vendor\PluginName`)
    - 詢問是否需要安裝開發依賴 (PHPUnit, PHPCS)

2.  **執行動作**：
    - 建立標準目錄結構：
      - `src/` (核心代碼)
      - `assets/` (CSS/JS)
      - `tests/` (測試檔)
      - `languages/` (.pot 檔)
    - 產生 `plugin-name.php` 主檔案，包含標準 Header。
    - 產生 `composer.json` 並設定 PSR-4 Autoload。
    - 產生 `.gitignore` (排除 vendor, node_modules, svn 等)。

3.  **規範**：
    - 所有檔案必須遵循 WordPress Coding Standards。
    - 主檔案必須包含 `ABSPATH` 安全檢查：`defined( 'ABSPATH' ) || exit;`。