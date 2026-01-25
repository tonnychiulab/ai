# Curator Agent LLM 整合修正

**日期**: 2026-01-25
**修改檔案**: `includes/agents/class-ic-curator.php`
**修改類型**: 功能增強

---

## 問題描述

WordPress 版本的 Curator Agent 在 Generate Daily Digest 階段沒有照原版 Python 的方式產生報表：

### 原版 Python 報表格式
- 每篇文章包含：標題、摘要、**核心要點（key_takeaway）**、標籤、評分
- **💡 今日洞察（daily_insight）**：趨勢分析區塊
- **🎯 建議行動（recommended_action）**：行動建議區塊
- 優先度顏色分類（高/中/低）

### WordPress 版本問題
- ❌ 沒有調用 LLM 生成智慧摘要
- ❌ 缺少今日洞察區塊
- ❌ 缺少建議行動區塊
- ❌ 缺少每篇文章的核心要點
- ❌ 只有簡單的 emoji（⭐/🔥），無優先度顏色分類

---

## 修正內容

### 1. 新增 LLM 智慧分析

**方法**: `generate_digest_with_llm( $items, $api_key )`

- 調用 OpenAI API（使用已配置的 `ic_openai_key`）
- 使用 `gpt-4o-mini` 模型
- 傳送文章列表，要求返回結構化 JSON

### 2. 新增 Curator Prompt

**方法**: `get_curator_prompt()`

移植自原版 `/prompts/daily_prompt.txt`，包含：
- 角色定義：每日情報策展人
- 執行步驟：文章分析 → 趨勢識別 → 行動建議
- JSON 輸出格式規範
- 品質標準：精簡原則、價值導向

### 3. 新增格式化報表

**方法**: `format_digest_content( $digest )`

產生 WordPress Block Editor 相容的 HTML，包含：

| 區塊 | 說明 | 樣式 |
|------|------|------|
| 文章卡片 | 標題、摘要、核心要點、標籤+評分 | 左側顏色邊框 |
| 💡 今日洞察 | 趨勢分析 | 藍色背景 `#e8f4fd` |
| 🎯 建議行動 | 行動建議 | 綠色背景 `#d4edda` |
| 核心要點 | 每篇文章的 key_takeaway | 黃色背景 `#fff3cd` |

**優先度顏色分類**：
- 高優先度 (≥0.9): 紅色邊框 `#ea4335`
- 中優先度 (0.7-0.9): 黃色邊框 `#fbbc04`
- 低優先度 (<0.7): 綠色邊框 `#34a853`

### 4. 新增 Fallback 機制

**方法**: `format_fallback_content( $items )`

當 API Key 未設定或 LLM 調用失敗時：
- 使用改良的 fallback 格式
- 保留優先度顏色分類
- 不包含 LLM 生成的洞察和建議

---

## 程式碼結構

```php
class IC_Curator extends IC_Agent {

    public function run()
        // 1. 查詢高分文章
        // 2. 排序並取前 10 篇
        // 3. 調用 generate_report()

    private function generate_report( $items )
        // 1. 檢查是否已有今日報告
        // 2. 調用 LLM 生成結構化摘要
        // 3. 格式化內容並建立 WordPress 文章

    private function generate_digest_with_llm( $items, $api_key )
        // 調用 OpenAI API

    private function get_curator_prompt()
        // 返回 Curator 系統提示

    private function format_digest_content( $digest )
        // 將 LLM 結果格式化為 WordPress Block HTML

    private function format_fallback_content( $items )
        // Fallback 格式（無 LLM）
}
```

---

## 輸出對照表

| 欄位 | 原版 Python | 修正前 WordPress | 修正後 WordPress |
|------|-------------|------------------|------------------|
| 文章摘要 | ✅ | ✅ | ✅ |
| 核心要點 (key_takeaway) | ✅ | ❌ | ✅ |
| 標籤 + 評分 | ✅ | ✅ | ✅ |
| 今日洞察 (daily_insight) | ✅ | ❌ | ✅ |
| 建議行動 (recommended_action) | ✅ | ❌ | ✅ |
| 優先度顏色分類 | ✅ | ❌ | ✅ |
| LLM 智慧分析 | ✅ | ❌ | ✅ |

---

## 依賴項

- **OpenAI API Key**: 需在 Settings 頁面設定 `ic_openai_key`
- **模型**: `gpt-4o-mini`（可在程式碼中調整）

---

## 測試方式

1. 確認已設定 OpenAI API Key
2. 在 Settings 頁面點擊「Run Curator (Generate Report)」
3. 檢查產生的 `ic_report` 文章是否包含：
   - 💡 今日洞察區塊
   - 🎯 建議行動區塊
   - 每篇文章的核心要點
   - 左側優先度顏色邊框

---

## 相關檔案

- **原版 Curator**: `/InsightCosmos/src/agents/curator_daily.py`
- **原版 Prompt**: `/InsightCosmos/prompts/daily_prompt.txt`
- **原版 Formatter**: `/InsightCosmos/src/tools/digest_formatter.py`
