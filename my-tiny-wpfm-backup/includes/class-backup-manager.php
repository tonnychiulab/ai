<?php
/**
 * 核心備份管理器類別
 * 
 * v1.0.2 新增：支援使用者個人化排版
 * 
 * @package My_Tiny_WPFM
 * @since 1.0.2
 */

namespace My_Tiny_WPFM;

use WP_Error;

class Backup_Manager {

    private static ?Backup_Manager $instance = null;
    private string $backup_dir;
    private string $option_key = 'my_tiny_wpfm_logs';
    
    // 定義卡片預設 ID 與名稱
    private const CARD_CREATE = 'card-create';
    private const CARD_LOGS = 'card-logs';
    private const CARD_LIST = 'card-list';

    public static function get_instance(): Backup_Manager {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->backup_dir = isset($upload_dir['basedir']) ? $upload_dir['basedir'] . '/my-tiny-wpfm-backups/' : '';
    }

    public function run(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX Handlers
        add_action('wp_ajax_my_tiny_wpfm_check_system', [$this, 'ajax_check_system']);
        add_action('wp_ajax_my_tiny_wpfm_perform_backup', [$this, 'ajax_perform_backup']);
        add_action('wp_ajax_my_tiny_wpfm_delete_backup', [$this, 'ajax_delete_backup']);
        add_action('wp_ajax_my_tiny_wpfm_get_backups', [$this, 'ajax_get_backups']);
        // 新增：儲存排版順序
        add_action('wp_ajax_my_tiny_wpfm_save_layout', [$this, 'ajax_save_layout']);
    }

    public function add_admin_menu(): void {
        add_management_page(
            __('My Tiny WPFM Backup', 'my-tiny-wpfm-backup'),
            __('My Tiny WPFM Backup', 'my-tiny-wpfm-backup'),
            'manage_options',
            'my-tiny-wpfm-backup',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_assets($hook): void {
        if ('tools_page_my-tiny-wpfm-backup' !== $hook) {
            return;
        }

        // 載入 jQuery UI Core & Sortable (WordPress 已內建)
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_style(
            'my-tiny-wpfm-style',
            MY_TINY_WPFM_PLUGIN_URL . 'assets/css/style.css',
            [],
            MY_TINY_WPFM_VERSION
        );

        wp_enqueue_script(
            'my-tiny-wpfm-script',
            MY_TINY_WPFM_PLUGIN_URL . 'assets/js/script.js',
            ['jquery', 'jquery-ui-sortable'],
            MY_TINY_WPFM_VERSION,
            true
        );

        wp_localize_script('my-tiny-wpfm-script', 'myTinyWPFM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my_tiny_wpfm_nonce'),
            'strings' => [
                'confirm_start' => __('System resources are good. Are you sure you want to start backup?', 'my-tiny-wpfm-backup'),
                'confirm_warning' => __('Warning: System load is high. Proceed?', 'my-tiny-wpfm-backup'),
                'backup_complete' => __('Backup completed successfully!', 'my-tiny-wpfm-backup'),
                'backup_failed' => __('Backup failed. Please check logs.', 'my-tiny-wpfm-backup'),
                'checking' => __('Checking...', 'my-tiny-wpfm-backup')
            ]
        ]);
    }

    /**
     * 渲染後台頁面
     * 
     * 修改重點：依照使用者個人化的設定 來排序輸出 HTML 區塊。
     */
    public function render_admin_page(): void {
        $current_time = current_time('D, F j, Y H:i');
        
        // 1. 取得使用者儲存的排版順序
        $user_id = get_current_user_id();
        $saved_order = get_user_meta($user_id, 'my_tiny_wpfm_layout_order', true);
        
        // 2. 預設順序
        $default_order = [self::CARD_CREATE, self::CARD_LOGS, self::CARD_LIST];
        
        // 3. 決定目前使用的順序 (如果有儲存就用儲存的，否則用預設)
        $active_order = (!empty($saved_order) && is_array($saved_order)) ? $saved_order : $default_order;
        
        // 4. 預先定義各個卡片的 HTML 內容
        // 這樣我們可以依照 $active_order 的順序，隨時輸出對應的卡片
        $card_html = [];
        
        // --- 卡片 A: Create Backup ---
        $card_html[self::CARD_CREATE] = '
            <div class="card my-tiny-wpfm-card" id="' . self::CARD_CREATE . '">
                <h2>
                    <span><span class="dashicons dashicons-move mtw-drag-handle" title="' . esc_attr__('Drag to reorder', 'my-tiny-wpfm-backup') . '"></span> ' . __('Create New Backup', 'my-tiny-wpfm-backup') . '</span>
                </h2>
                <div class="system-info-box">
                    <p><strong>' . __('Current Time:', 'my-tiny-wpfm-backup') . '</strong> <span id="current-time">' . esc_html($current_time) . '</span></p>
                </div>
                <form id="backup-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">' . __('Backup Options', 'my-tiny-wpfm-backup') . '</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="backup_database" value="1">
                                        ' . __('Database Backup', 'my-tiny-wpfm-backup') . '
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="backup_files" value="1">
                                        ' . __('Files Backup', 'my-tiny-wpfm-backup') . '
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="button" id="btn-check-and-backup" class="button button-primary button-large">
                            ' . __('Check Resources & Backup Now', 'my-tiny-wpfm-backup') . '
                        </button>
                    </p>
                </form>
                <div id="progress-container" style="display:none;">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="progress-bar-fill"></div>
                    </div>
                    <p id="progress-status" class="description">' . __('Initializing...', 'my-tiny-wpfm-backup') . '</p>
                </div>
            </div>';

        // --- 卡片 B: System Status & Logs ---
        $card_html[self::CARD_LOGS] = '
            <div class="card my-tiny-wpfm-card" id="' . self::CARD_LOGS . '">
                <h2>
                    <span><span class="dashicons dashicons-move mtw-drag-handle" title="' . esc_attr__('Drag to reorder', 'my-tiny-wpfm-backup') . '"></span> ' . __('System Status & Logs', 'my-tiny-wpfm-backup') . '</span>
                </h2>
                <div class="log-box">
                    <h3>' . __('Last Log Message', 'my-tiny-wpfm-backup') . '</h3>
                    <div id="last-log-message">' . $this->get_last_log_message() . '</div>
                </div>
            </div>';

        // --- 卡片 C: Existing Backups ---
        $card_html[self::CARD_LIST] = '
            <div class="card my-tiny-wpfm-card" id="' . self::CARD_LIST . '">
                <h2>
                    <span><span class="dashicons dashicons-move mtw-drag-handle" title="' . esc_attr__('Drag to reorder', 'my-tiny-wpfm-backup') . '"></span> ' . __('Existing Backup(s)', 'my-tiny-wpfm-backup') . '</span>
                </h2>';
                
        if (!empty($this->backup_dir)) {
            $card_html[self::CARD_LIST] .= '
                <div class="storage-path-notice">
                    <span class="dashicons dashicons-admin-generic"></span>
                    ' . __('Storage Location:', 'my-tiny-wpfm-backup') . '
                    <code>' . esc_html($this->backup_dir) . '</code>
                </div>';
        }
        
        $card_html[self::CARD_LIST] .= '
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>' . __('Backup Date', 'my-tiny-wpfm-backup') . '</th>
                            <th>' . __('Backup Data (Filename & Size)', 'my-tiny-wpfm-backup') . '</th>
                            <th>' . __('Action', 'my-tiny-wpfm-backup') . '</th>
                        </tr>
                    </thead>
                    <tbody id="backup-list-body">
                        <tr><td colspan="4">' . __('Loading...', 'my-tiny-wpfm-backup') . '</td></tr>
                    </tbody>
                </table>
                <p class="description" style="margin-top: 15px; text-align: right; color: #64748b;">
                    ' . __('Thank you for using My Tiny WPFM Backup.', 'my-tiny-wpfm-backup') . '
                </p>
            </div>';

        // 5. 開始輸出 HTML
        echo '<div class="wrap my-tiny-wpfm-container">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        echo '<div class="my-tiny-wpfm-grid">';
        
        // 依照順序輸出卡片
        foreach ($active_order as $card_id) {
            if (isset($card_html[$card_id])) {
                echo $card_html[$card_id];
            }
        }
        
        echo '</div>'; // End Grid
        echo '</div>'; // End Container
    }

    /**
     * AJAX: 儲存個人化排版順序
     * 
     * 接收 JS 傳來的 ID 陣列，儲存至目前使用者的 user_meta。
     * 
     * @return void
     */
    public function ajax_save_layout(): void {
        check_ajax_referer('my_tiny_wpfm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'my-tiny-wpfm-backup'));
        }

        if (!isset($_POST['layout']) || !is_array($_POST['layout'])) {
            wp_send_json_error(__('Invalid layout data.', 'my-tiny-wpfm-backup'));
        }

        // 安全過濾：僅允許預定義的卡片 ID 存入資料庫
        $allowed_ids = [self::CARD_CREATE, self::CARD_LOGS, self::CARD_LIST];
        $layout_order = [];
        
        foreach ($_POST['layout'] as $id) {
            if (in_array($id, $allowed_ids, true)) {
                $layout_order[] = $id;
            }
        }

        if (!empty($layout_order)) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'my_tiny_wpfm_layout_order', $layout_order);
            wp_send_json_success();
        } else {
            wp_send_json_error(__('No valid layout data to save.', 'my-tiny-wpfm-backup'));
        }
    }

    // --- 以下為原有的 AJAX 處理函式，保持不變 ---
    
    public function ajax_check_system(): void {
        check_ajax_referer('my_tiny_wpfm_nonce', 'nonce');

        $disk_free = @disk_free_space(ABSPATH);
        $disk_total = function_exists('disk_total_space') ? @disk_total_space(ABSPATH) : 0;
        $required_space = 100 * 1024 * 1024; 
        $disk_ok = ($disk_free && $disk_free > $required_space);

        $load_percent = 0;
        $mem_percent = 0;
        
        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if ($load !== false && is_array($load) && isset($load[0])) {
                $current_load = $load[0];
                $cores = 1; 
                if (is_file('/proc/cpuinfo')) {
                    $proc_cores = @shell_exec('nproc');
                    if (is_numeric($proc_cores)) {
                        $cores = (int)$proc_cores;
                    }
                }
                if ($cores > 0) {
                    $load_percent = ($current_load / $cores) * 100;
                }
            }
        }

        $mem_usage = memory_get_usage(true);
        $mem_limit_val = ini_get('memory_limit');
        $mem_limit_bytes = $this->return_bytes($mem_limit_val);
        if ($mem_limit_bytes > 0) {
            $mem_percent = ($mem_usage / $mem_limit_bytes) * 100;
        }

        $status = 'ok';
        $message = '';

        if (!$disk_ok) {
            $status = 'critical';
            $message = __('Disk space is critically low.', 'my-tiny-wpfm-backup');
        } else {
            if ($load_percent < 50) {
                $status = 'confirm';
                $message = sprintf(__('CPU Load is low (%.1f%%). RAM usage: %.1f%%. Ready to proceed?', 'my-tiny-wpfm-backup'), $load_percent, $mem_percent);
            } else {
                $status = 'warning';
                $message = sprintf(__('Warning: High CPU Load (%.1f%%). RAM usage: %.1f%%. Are you sure?', 'my-tiny-wpfm-backup'), $load_percent, $mem_percent);
            }
        }

        wp_send_json_success([
            'status' => $status,
            'message' => $message,
            'load_percent' => round($load_percent, 2),
            'mem_percent' => round($mem_percent, 2)
        ]);
    }

    public function ajax_perform_backup(): void {
        check_ajax_referer('my_tiny_wpfm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'my-tiny-wpfm-backup'));
        }

        if (!is_dir($this->backup_dir)) {
            wp_send_json_error(__('Backup directory does not exist.', 'my-tiny-wpfm-backup'));
        }

        $this->log(__('Backup started...', 'my-tiny-wpfm-backup'));
        
        $backup_db = isset($_POST['backup_database']) && $_POST['backup_database'] === '1';
        $backup_files = isset($_POST['backup_files']) && $_POST['backup_files'] === '1';

        if (!$backup_db && !$backup_files) {
             wp_send_json_error(__('Nothing selected to backup.', 'my-tiny-wpfm-backup'));
        }

        $type_suffix = 'unknown';
        if ($backup_db && $backup_files) {
            $type_suffix = 'full';
        } elseif ($backup_db) {
            $type_suffix = 'db';
        } elseif ($backup_files) {
            $type_suffix = 'files';
        }

        $filename = 'backup-' . $type_suffix . '-' . date('Y-m-d-H-i-s') . '.zip';
        $filepath = $this->backup_dir . $filename;

        if (!class_exists('ZipArchive')) {
            $this->log(__('ZipArchive class not found.', 'my-tiny-wpfm-backup'), 'error');
            wp_send_json_error(__('Server does not support ZipArchive.', 'my-tiny-wpfm-backup'));
        }

        $zip = new \ZipArchive();
        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            $this->log(__('Could not create zip file.', 'my-tiny-wpfm-backup'), 'error');
            wp_send_json_error(__('Could not create backup file.', 'my-tiny-wpfm-backup'));
        }

        if ($backup_db) {
            global $wpdb;
            $db_dump = "-- Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n";
            $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            if ($tables) {
                foreach ($tables as $table) {
                    $table_name = $table[0];
                    $create_query = $wpdb->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_A);
                    if (isset($create_query['Create Table'])) {
                        $db_dump .= "DROP TABLE IF EXISTS `$table_name`;\n";
                        $db_dump .= $create_query['Create Table'] . ";\n\n";
                    }
                }
            }
            $zip->addFromString('database.sql', $db_dump);
        }

        if ($backup_files) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(WP_CONTENT_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $key => $value) {
                if (strpos($key, $this->backup_dir) !== false) continue;
                if (is_file($key)) {
                    $relativePath = substr($key, strlen(ABSPATH));
                    
                    // 【安全性增強】防止路徑遍歷
                    if (strpos($relativePath, '../') !== false) {
                        continue;
                    }

                    $zip->addFile($key, $relativePath);
                }
            }
        }

        $zip->close();

        if (file_exists($filepath)) {
            $this->log(sprintf(__('Backup successful: %s', 'my-tiny-wpfm-backup'), $filename), 'success');
            wp_send_json_success([
                'message' => __('Backup completed!', 'my-tiny-wpfm-backup'),
                'filename' => $filename
            ]);
        } else {
            $this->log(__('Backup failed: File not created.', 'my-tiny-wpfm-backup'), 'error');
            wp_send_json_error(__('Backup failed.', 'my-tiny-wpfm-backup'));
        }
    }

    public function ajax_get_backups(): void {
        check_ajax_referer('my_tiny_wpfm_nonce', 'nonce');

        if (!is_dir($this->backup_dir)) {
             wp_send_json_success(['html' => '<tr><td colspan="4" style="text-align:center;">' . __('Backup directory missing.', 'my-tiny-wpfm-backup') . '</td></tr>']);
             return;
        }

        $files = glob($this->backup_dir . '*.zip');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $html = '';
        $index = 1;
        
        if (empty($files)) {
            $html = '<tr><td colspan="4" style="text-align:center;">' . __('Currently no backup(s) found.', 'my-tiny-wpfm-backup') . '</td></tr>';
        } else {
            foreach ($files as $file) {
                // 【安全性增強】
                $name = basename($file);
                $name = sanitize_file_name($name);
                
                $full_path = $file; 
                $date = date('Y-m-d H:i:s', filemtime($file));
                $size = size_format(filesize($file));
                $download_url = admin_url('?my_tiny_wpfm_download=' . urlencode($name));
                
                $badge_html = '';
                if (strpos($name, 'backup-full-') !== false) {
                    $badge_html = '<span class="mtw-badge mtw-badge-full">' . __('Full', 'my-tiny-wpfm-backup') . '</span>';
                } elseif (strpos($name, 'backup-db-') !== false) {
                    $badge_html = '<span class="mtw-badge mtw-badge-db">' . __('DB Only', 'my-tiny-wpfm-backup') . '</span>';
                } else {
                    $badge_html = '<span class="mtw-badge mtw-badge-files">' . __('Files Only', 'my-tiny-wpfm-backup') . '</span>';
                }
                
                $tooltip_content = "Path: " . $full_path . "\nFile: " . $name;

                $html .= sprintf(
                    '<tr>
                        <td>%d</td>
                        <td>%s</td>
                        <td class="backup-data-cell">
                            <div class="mtw-file-row">
                                <a href="%s" class="backup-download-link mtw-tooltip-trigger" data-tooltip="%s">
                                    <span class="dashicons dashicons-download"></span>
                                    <span class="mtw-filename-text">%s</span>
                                </a>
                            </div>
                            <div class="mtw-meta-row">
                                %s
                                <small class="text-muted">%s</small>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <button class="mtw-delete-btn delete-backup" data-file="%s" title="' . __('Delete', 'my-tiny-wpfm-backup') . '">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>',
                    $index++,
                    $date,
                    esc_url($download_url),
                    esc_attr($tooltip_content),
                    esc_html($name),
                    $badge_html,
                    $size,
                    esc_attr($name)
                );
            }
        }

        wp_send_json_success(['html' => $html]);
    }

    public function ajax_delete_backup(): void {
        check_ajax_referer('my_tiny_wpfm_nonce', 'nonce');
        // 【安全性增強】雙重過濾
        $filename = basename($_POST['filename']);
        $filename = sanitize_file_name($filename);
        
        $filepath = $this->backup_dir . $filename;

        if (file_exists($filepath) && unlink($filepath)) {
            $this->log(sprintf(__('Backup deleted: %s', 'my-tiny-wpfm-backup'), $filename), 'success');
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Could not delete file.', 'my-tiny-wpfm-backup'));
        }
    }

    private function log(string $message, string $type = 'info'): void {
        $logs = get_option($this->option_key, []);
        $timestamp = date('Y-m-d H:i:s');
        array_unshift($logs, [
            'time' => $timestamp,
            'message' => $message,
            'type' => $type
        ]);
        $logs = array_slice($logs, 0, 50);
        update_option($this->option_key, $logs);
    }

    public function get_last_log_message(): string {
        $logs = get_option($this->option_key, []);
        if (empty($logs)) {
            return __('No log message', 'my-tiny-wpfm-backup');
        }
        $last = $logs[0];
        $class = $last['type'] === 'error' ? 'error' : ($last['type'] === 'success' ? 'success' : 'updated');
        return sprintf('<div class="%s"><strong>[%s]</strong> %s</div>', $class, esc_html($last['time']), esc_html($last['message']));
    }

    private function return_bytes($val): int {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}

add_action('admin_init', function() {
    if (isset($_GET['my_tiny_wpfm_download']) && current_user_can('manage_options')) {
        // 【安全性增強】
        $filename = basename($_GET['my_tiny_wpfm_download']);
        $filename = sanitize_file_name($filename);
        
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/my-tiny-wpfm-backups/' . $filename;
        
        if (file_exists($filepath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
});