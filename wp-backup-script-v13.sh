#!/bin/bash

# 定義顏色代碼
RED='\033[0;31m'      # 錯誤訊息用紅色
YELLOW='\033[1;33m'   # 警告訊息用黃色
GREEN='\033[0;32m'    # 成功訊息用綠色
BLUE='\033[0;34m'     # 資訊訊息用藍色
CYAN='\033[0;36m'     # 進度訊息用青色
NC='\033[0m'          # 重置顏色

# 計算耗時的函數（輸入秒數，輸出格式化時間）
format_time() {
    local seconds=$1
    local hours=$((seconds / 3600))
    local minutes=$(((seconds % 3600) / 60))
    local secs=$((seconds % 60))
    printf "%02d時%02d分%02d秒" $hours $minutes $secs
}

# 轉換位元組到人類可讀格式的函數
format_size() {
    local size="$1"
    
    # 檢查輸入是否為空或非數字
    if [ -z "$size" ] || ! [[ "$size" =~ ^[0-9]+$ ]]; then
        echo "0 B"
        return
    fi
    
    if [ "$size" -ge 1073741824 ]; then
        echo "$(awk "BEGIN {printf \"%.2f\", $size/1073741824}") GB"
    elif [ "$size" -ge 1048576 ]; then
        echo "$(awk "BEGIN {printf \"%.2f\", $size/1048576}") MB"
    elif [ "$size" -ge 1024 ]; then
        echo "$(awk "BEGIN {printf \"%.2f\", $size/1024}") KB"
    else
        echo "${size} B"
    fi
}

# 取得目錄大小（位元組）
get_dir_size_bytes() {
    local dir=$1
    du -sb "$dir" | cut -f1
}

# 取得資料庫大小（位元組）
get_db_size_bytes() {
    local size=$(wp db size --allow-root --size_in_bytes 2>/dev/null)
    if [ $? -eq 0 ]; then
        echo "$size"
    else
        echo "0"
    fi
}

# 取得可用空間（位元組）
get_available_space() {
    local dir=$1
    df --output=avail "$dir" | tail -n 1 | awk '{print $1*1024}'
}

# 顯示系統資源使用狀況的函數
show_system_stats() {
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}')
    MEM_USAGE=$(free -m | awk '/Mem:/ {printf "%.2f%%", $3/$2*100}')
    log "INFO" "CPU 使用率: $CPU_USAGE%"
    log "INFO" "記憶體使用率: $MEM_USAGE"
}

# 設定變數
HOME_DIR="/home"
BACKUP_DIR="/path/to/backup"  # 請修改為您想要的備份路徑
DATE=$(date +%Y%m%d_%H%M%S)
SITE_COUNT=0
DB_COUNT=0
TOTAL_START_TIME=$(date +%s)

# 設定日誌目錄和檔案
LOG_DIR="$BACKUP_DIR/logs"
LOG_FILE="$LOG_DIR/backup_${DATE}.log"
ERROR_LOG="$LOG_DIR/backup_${DATE}_error.log"

# 建立日誌目錄
mkdir -p "$LOG_DIR"

# 日誌函數
log() {
    local level=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    # 根據日誌等級選擇顏色
    case "$level" in
        "INFO")
            local color=$BLUE
            ;;
        "SUCCESS")
            local color=$GREEN
            ;;
        "WARN")
            local color=$YELLOW
            ;;
        "ERROR")
            local color=$RED
            ;;
        "PROGRESS")
            local color=$CYAN
            ;;
        *)
            local color=$NC
            ;;
    esac
    
    # 輸出到終端機（帶顏色）
    echo -e "${color}[$timestamp] [$level] $message${NC}"
    
    # 輸出到日誌檔案（不帶顏色）
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

# 錯誤日誌函數
error_log() {
    local message=$1
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${RED}[$timestamp] [ERROR] $message${NC}" | tee -a "$ERROR_LOG"
    echo "[$timestamp] [ERROR] $message" >> "$LOG_FILE"
}

[previous functions remain the same...]

# 檢查是否為 root 使用者
if [ "$EUID" -ne 0 ]; then
    error_log "請使用 root 權限執行此指令碼"
    exit 1
fi

# 檢查 wp-cli 是否安裝
if ! command -v wp &> /dev/null; then
    error_log "找不到 wp-cli，請先安裝 wp-cli"
    exit 1
fi

# 檢查備份目錄是否存在
if [ ! -d "$BACKUP_DIR" ]; then
    error_log "錯誤: 備份目錄 $BACKUP_DIR 不存在"
    error_log "請先建立備份目錄後再執行指令碼"
    exit 1
fi

# 檢查備份所需空間和可用空間
check_backup_space() {
    local total_required=0
    local available_space=$(get_available_space "$BACKUP_DIR")
    local space_check_failed=false

    log "PROGRESS" "開始檢查空間需求..."
    log "INFO" "-------------------"

    # 遍歷所有 WordPress 站台計算空間需求
    for SITE_DIR in "$HOME_DIR"/*; do
        if [ -d "$SITE_DIR" ] && [ -d "$SITE_DIR/public_html" ] && [ -f "$SITE_DIR/public_html/wp-config.php" ]; then
            local site_name=$(basename "$SITE_DIR")
            log "PROGRESS" "檢查網站: $site_name"

            # 檢查網站檔案大小
            cd "$SITE_DIR/public_html" || continue
            local files_size=$(get_dir_size_bytes "./")
            log "INFO" "- 網站檔案大小: $(format_size $files_size)"

            # 檢查資料庫大小
            local db_size=$(get_db_size_bytes)
            log "INFO" "- 資料庫大小: $(format_size $db_size)"

            # 計算總需求（加上 10% 緩衝）
            local site_total=$(( (files_size + db_size) * 110 / 100 ))
            total_required=$((total_required + site_total))
            
            log "INFO" "- 預估備份所需空間: $(format_size $site_total)"
            log "INFO" "-------------------"
        fi
    done

    log "INFO" "空間需求總結:"
    log "INFO" "總備份空間需求: $(format_size $total_required)"
    log "INFO" "備份目錄可用空間: $(format_size $available_space)"

    if [ $total_required -gt $available_space ]; then
        error_log "警告: 備份目錄空間不足!"
        error_log "還需要額外空間: $(format_size $((total_required - available_space)))"
        return 1
    else
        log "SUCCESS" "空間檢查通過，可以開始備份"
        log "INFO" "預計剩餘空間: $(format_size $((available_space - total_required)))"
        return 0
    fi
}

# 開始備份前檢查空間
log "PROGRESS" "執行備份前空間檢查..."
if ! check_backup_space; then
    error_log "因空間不足，取消備份作業"
    exit 1
fi

log "PROGRESS" "開始備份程序..."
log "INFO" "開始時間: $(date '+%Y-%m-%d %H:%M:%S')"
log "INFO" "-------------------"

# 建立當天的備份目錄
DAILY_BACKUP_DIR="$BACKUP_DIR/$DATE"
mkdir -p "$DAILY_BACKUP_DIR"

# 遍歷 /home 目錄下的所有子目錄
for SITE_DIR in "$HOME_DIR"/*; do
    if [ -d "$SITE_DIR" ]; then
        # 檢查是否為 WordPress 網站
        if [ -d "$SITE_DIR/public_html" ] && [ -f "$SITE_DIR/public_html/wp-config.php" ]; then
            SITE_NAME=$(basename "$SITE_DIR")
            log "PROGRESS" "正在備份網站: $SITE_NAME"
            SITE_START_TIME=$(date +%s)
            
            # 顯示系統資源使用狀況
            show_system_stats
            
            # 建立網站專屬備份目錄
            SITE_BACKUP_DIR="$DAILY_BACKUP_DIR/$SITE_NAME"
            mkdir -p "$SITE_BACKUP_DIR"
            
            # 取得網站網址
            cd "$SITE_DIR/public_html" || continue
            SITE_URL=$(wp option get siteurl --allow-root 2>/dev/null)
            if [ -z "$SITE_URL" ]; then
                SITE_URL="unknown"
                log "WARN" "無法取得網站 URL，使用 'unknown' 作為替代"
            fi
            # 移除 http:// 或 https:// 前綴
            SITE_URL=$(echo "$SITE_URL" | sed 's|^http[s]*://||')
            
            # 備份資料庫
            log "PROGRESS" "開始備份資料庫..."
            DB_START_TIME=$(date +%s)
            if wp db export "$SITE_BACKUP_DIR/${DATE}_${SITE_URL}_database.sql" --allow-root 2>> "$ERROR_LOG"; then
                DB_END_TIME=$(date +%s)
                DB_DURATION=$((DB_END_TIME - DB_START_TIME))
                log "SUCCESS" "資料庫備份完成: $SITE_NAME ($SITE_URL)"
                log "INFO" "資料庫備份耗時: $(format_time $DB_DURATION)"
                ((DB_COUNT++))
            else
                error_log "警告: $SITE_NAME 的資料庫備份失敗"
            fi
            
            # 備份網站檔案
            log "PROGRESS" "開始備份網站檔案..."
            FILES_START_TIME=$(date +%s)
            if tar -czf "$SITE_BACKUP_DIR/${DATE}_${SITE_URL}_files.tar.gz" ./ 2>> "$ERROR_LOG"; then
                FILES_END_TIME=$(date +%s)
                FILES_DURATION=$((FILES_END_TIME - FILES_START_TIME))
                log "SUCCESS" "檔案備份完成: $SITE_NAME ($SITE_URL)"
                log "INFO" "檔案備份耗時: $(format_time $FILES_DURATION)"
                ((SITE_COUNT++))
            else
                error_log "警告: $SITE_NAME 的檔案備份失敗"
            fi
            
            # 計算單一網站總耗時
            SITE_END_TIME=$(date +%s)
            SITE_DURATION=$((SITE_END_TIME - SITE_START_TIME))
            log "INFO" "網站 $SITE_NAME 總備份耗時: $(format_time $SITE_DURATION)"
            log "INFO" "-------------------"
        fi
    fi
done

# 計算總耗時
TOTAL_END_TIME=$(date +%s)
TOTAL_DURATION=$((TOTAL_END_TIME - TOTAL_START_TIME))

# 顯示備份總結
log "SUCCESS" "備份完成!"
log "INFO" "結束時間: $(date '+%Y-%m-%d %H:%M:%S')"
log "SUCCESS" "總共備份了 $SITE_COUNT 個網站"
log "SUCCESS" "總共備份了 $DB_COUNT 個資料庫"
log "INFO" "總耗時: $(format_time $TOTAL_DURATION)"
log "INFO" "備份檔案已儲存在: $DAILY_BACKUP_DIR"

# 計算備份大小並顯示磁碟使用情況
log "INFO" "-------------------"
log "INFO" "備份空間使用統計:"
log "INFO" "今日備份大小: $(du -sh "$DAILY_BACKUP_DIR" | cut -f1)"
log "INFO" "總備份目錄大小: $(du -sh "$BACKUP_DIR" | cut -f1)"
log "INFO" "備份後可用空間: $(df -h "$BACKUP_DIR" | awk 'NR==2 {print $4}')"

# 如果沒有錯誤，移除空的錯誤日誌檔
if [ ! -s "$ERROR_LOG" ]; then
    rm "$ERROR_LOG"
fi

log "SUCCESS" "備份作業完成，日誌檔案位置: $LOG_FILE"
if [ -f "$ERROR_LOG" ]; then
    log "WARN" "備份過程中發生錯誤，請查看錯誤日誌: $ERROR_LOG"
fi
