<?php
/**
 * Plugin Name: My Tiny WPFM Backup
 * Plugin URI: https://example.com/
 * Description: A lightweight backup manager for WordPress with modern UI/UX. Supports Database & Files backup.
 * Version: 1.0.2
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Author: Chat.z.ai
 * Text Domain: my-tiny-wpfm-backup
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// 安全檢查：防止直接存取此檔案
if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 基礎設定與常數定義
// ==========================================

// 定義外掛版本號 (當前版本: 1.0.2)
define('MY_TINY_WPFM_VERSION', '1.0.2');

// 定義外掛目錄路徑 (用於 require/include 檔案)
define('MY_TINY_WPFM_PLUGIN_DIR', plugin_dir_path(__FILE__));

// 定義外掛網址 (用於載入 CSS/JS)
define('MY_TINY_WPFM_PLUGIN_URL', plugin_dir_url(__FILE__));

// ==========================================
// 類別自動載入器
// ==========================================
// 
// 為了保持程式碼乾淨，主要邏輯放在 includes/class-backup-manager.php。
// 當程式嘗試呼叫 My_Tiny_WPFM\Backup_Manager 時，這個函式會自動載入對應檔案。
//
spl_autoload_register(function ($class) {
    // 設定命名空間前綴，只處理我們自己外掛的類別
    $prefix = 'My_Tiny_WPFM\\';
    $base_dir = MY_TINY_WPFM_PLUGIN_DIR . 'includes/';

    // 檢查類別是否使用了我們的命名空間
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // 取得相對類別名稱 (去掉 Namespace)
    $relative_class = substr($class, $len);
    
    // 將類別名稱轉換為檔案格式 (例如 Backup_Manager -> class-backup-manager.php)
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';

    // 如果檔案存在，載入它
    if (file_exists($file)) {
        require $file;
    }
});

// ==========================================
// 初始化外掛
// ==========================================

function my_tiny_wpfm_init(): void {
    // 載入多國語言翻譯檔 (.mo 檔)
    load_plugin_textdomain('my-tiny-wpfm-backup', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // 檢查類別是否存在，避免 Fatal Error
    if (class_exists('My_Tiny_WPFM\Backup_Manager')) {
        $backup_manager = \My_Tiny_WPFM\Backup_Manager::get_instance();
        // 啟動運行 (註冊 Hooks)
        $backup_manager->run();
    }
}
// 在 plugins_loaded Hook 執行，確保 WordPress 核心功能都已載入
add_action('plugins_loaded', 'my_tiny_wpfm_init');

// ==========================================
// 啟用/停用 Hook
// ==========================================

/**
 * 啟用外掛時執行
 * 
 * 1. 設定定時任務 (Cron) 用來清理舊日誌。
 * 2. 建立備份目錄，並寫入 .htaccess 保護該目錄。
 */
register_activation_hook(__FILE__, function () {
    // 註冊每日清理舊日誌的定時任務
    if (!wp_next_scheduled('my_tiny_wpfm_cleanup_old_logs')) {
        wp_schedule_event(time(), 'daily', 'my_tiny_wpfm_cleanup_old_logs');
    }
    
    // 建立備份儲存目錄 (在 wp-content/uploads/my-tiny-wpfm-backups/)
    $upload_dir = wp_upload_dir();
    $backup_dir = $upload_dir['basedir'] . '/my-tiny-wpfm-backups/';
    
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
        
        // 【安全性】寫入 .htaccess 檔案
        // 防止使用者直接透過瀏覽器輸入網址下載備份檔案 (必須登入後台權限驗證後才能下載)
        // 這可以防止備份檔案被搜尋引擎索引或未經授權的存取。
        file_put_contents($backup_dir . '.htaccess', 'Deny from all');
    }
});

/**
 * 停用外掛時執行
 * 
 * 移除定時任務。
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('my_tiny_wpfm_cleanup_old_logs');
});