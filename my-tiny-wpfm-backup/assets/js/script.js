/**
 * My Tiny WPFM Backup - Frontend Script
 * 
 * 新增功能：支援拖曳排版 個人化設定。
 */

jQuery(document).ready(function($) {
    
    // ==========================================
    // 1. 初始化拖曳功能
    // ==========================================
    // 只有在非觸控裝置且外掛頁面時啟用
    if($('.my-tiny-wpfm-grid').length > 0) {
        $('.my-tiny-wpfm-grid').sortable({
            handle: 'h2', // 按住標題列才能拖曳
            placeholder: 'sortable-placeholder', // 拖曳時的佔位樣式
            connectWith: false, // 限制只能在目前容器內拖曳
            cursor: 'move',
            // 拖曳結束後觸發
            update: function(event, ui) {
                // 取得目前的排序 ID 陣列
                var order = $(this).sortable("toArray");
                // 發送 AJAX 儲存個人化設定
                $.ajax({
                    url: myTinyWPFM.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'my_tiny_wpfm_save_layout',
                        nonce: myTinyWPFM.nonce,
                        layout: order
                    },
                    success: function(response) {
                        if(!response.success) {
                            console.error("Layout save failed:", response.data);
                        }
                    }
                });
            }
        });
    }

    // ==========================================
    // 2. 原有功能綁定 (按鈕狀態、備份邏輯)
    // ==========================================

    /**
     * 函式：切換備份按鈕狀態
     */
    function toggleBackupButton() {
        var dbChecked = $('input[name="backup_database"]').is(':checked');
        var filesChecked = $('input[name="backup_files"]').is(':checked');

        if (dbChecked || filesChecked) {
            $('#btn-check-and-backup').prop('disabled', false).removeClass('button-disabled');
        } else {
            $('#btn-check-and-backup').prop('disabled', true).addClass('button-disabled');
        }
    }

    loadBackups();
    toggleBackupButton();

    $('input[name="backup_database"], input[name="backup_files"]').on('change', function() {
        toggleBackupButton();
    });

    $('#btn-check-and-backup').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text(myTinyWPFM.strings.checking);
        
        $.ajax({
            url: myTinyWPFM.ajaxUrl,
            type: 'POST',
            data: {
                action: 'my_tiny_wpfm_check_system',
                nonce: myTinyWPFM.nonce
            },
            success: function(response) {
                if (response.success) {
                    showConfirmModal(response.data);
                } else {
                    alert('<?php _e("System check failed.", "my-tiny-wpfm-backup"); ?>');
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                alert('<?php _e("Server connection failed.", "my-tiny-wpfm-backup"); ?>');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    function showConfirmModal(data) {
        var modalHtml = '<div class="my-tiny-modal-overlay" style="display:flex;">' +
                   '<div class="my-tiny-modal">' +
                   '<h3>' + (data.status === 'critical' ? 'Warning!' : 'Ready?') + '</h3>' +
                   '<p>' + data.message + '</p>' +
                   '<div class="modal-actions">' +
                   '<button class="button button-secondary" id="modal-cancel">Cancel</button>' +
                   '<button class="button button-primary" id="modal-confirm">Start Backup</button>' +
                   '</div></div></div>';
        
        $('body').append(modalHtml);

        $('#modal-cancel').on('click', function() {
            $('.my-tiny-modal-overlay').remove();
            toggleBackupButton();
            $('#btn-check-and-backup').text('<?php _e("Check Resources & Backup Now", "my-tiny-wpfm-backup"); ?>');
        });

        $('#modal-confirm').on('click', function() {
            $('.my-tiny-modal-overlay').remove();
            startBackup();
        });
    }

    function startBackup() {
        $('#progress-container').slideDown();
        
        var width = 0;
        var interval = setInterval(function() {
            if (width >= 95) {
                clearInterval(interval);
                $('#progress-bar-fill').addClass('processing-active');
                $('#progress-status').text('<?php _e("Server compressing files... Please wait, this may take time.", "my-tiny-wpfm-backup"); ?>');
            } else {
                width +=5; 
                $('#progress-bar-fill').css('width', width + '%');
                if(width < 30) {
                    $('#progress-status').text('<?php _e("Dumping Database...", "my-tiny-wpfm-backup"); ?>');
                } else if(width < 60) {
                    $('#progress-status').text('<?php _e("Reading Files...", "my-tiny-wpfm-backup"); ?>');
                } else {
                    $('#progress-status').text('<?php _e("Compressing...", "my-tiny-wpfm-backup"); ?>');
                }
            }
        }, 200);

        var backupDb = $('input[name="backup_database"]').is(':checked') ? '1' : '0';
        var backupFiles = $('input[name="backup_files"]').is(':checked') ? '1' : '0';

        $.ajax({
            url: myTinyWPFM.ajaxUrl,
            type: 'POST',
            data: {
                action: 'my_tiny_wpfm_perform_backup',
                nonce: myTinyWPFM.nonce,
                backup_database: backupDb,
                backup_files: backupFiles
            },
            success: function(response) {
                clearInterval(interval);
                $('#progress-bar-fill').removeClass('processing-active').css('width', '100%');
                $('#progress-status').text(myTinyWPFM.strings.backup_complete);
                
                setTimeout(function() {
                    $('#progress-container').slideUp();
                    loadBackups();
                    location.reload(); 
                }, 1000);
            },
            error: function(xhr, status, error) {
                clearInterval(interval);
                $('#progress-bar-fill').removeClass('processing-active').css('background-color', '#ef4444');
                $('#progress-status').text(myTinyWPFM.strings.backup_failed + ' (' + error + ')');
                
                setTimeout(function() {
                    $('#progress-container').slideUp();
                    toggleBackupButton();
                    $('#btn-check-and-backup').text('<?php _e("Check Resources & Backup Now", "my-tiny-wpfm-backup"); ?>');
                }, 3000);
            }
        });
    }

    function loadBackups() {
        $.ajax({
            url: myTinyWPFM.ajaxUrl,
            type: 'POST',
            data: {
                action: 'my_tiny_wpfm_get_backups',
                nonce: myTinyWPFM.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#backup-list-body').html(response.data.html);
                }
            }
        });
    }

    $(document).on('click', '.delete-backup', function() {
        if(!confirm('<?php _e("Are you sure you want to delete this backup?", "my-tiny-wpfm-backup"); ?>')) return;
        
        var $btn = $(this);
        var filename = $btn.data('file');
        
        $.ajax({
            url: myTinyWPFM.ajaxUrl,
            type: 'POST',
            data: {
                action: 'my_tiny_wpfm_delete_backup',
                nonce: myTinyWPFM.nonce,
                filename: filename
            },
            success: function(response) {
                if (response.success) {
                    loadBackups();
                } else {
                    alert('<?php _e("Error deleting backup.", "my-tiny-wpfm-backup"); ?>');
                }
            }
        });
    });

});