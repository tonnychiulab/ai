<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_Curator extends IC_Agent {

    public function run() {
        $this->log( "Starting Curator Run..." );

        // 1. Get high-scoring nodes from the last 24 hours (or just all un-reported ones for MVP)
        // We look for nodes with 'analysis' and score >= 7
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ic_nodes
             WHERE metadata LIKE '%\"analysis\"%'
             ORDER BY id DESC LIMIT 50"
        );

        $candidates = array();

        foreach ( $results as $node ) {
            $meta = json_decode( $node->metadata, true );

            // Safety check
            if ( ! isset( $meta['analysis']['score'] ) ) continue;

            // Threshold: Score >= 7
            if ( intval( $meta['analysis']['score'] ) < 7 ) continue;

            // Build article data for LLM
            $tags = isset( $meta['analysis']['tags'] ) ? $meta['analysis']['tags'] : array();
            if ( is_string( $tags ) ) {
                $tags = array_map( 'trim', explode( ',', $tags ) );
            }

            $candidates[] = array(
                'id'             => $node->id,
                'title'          => $node->label,
                'summary'        => $meta['analysis']['summary'] ?? '',
                'priority_score' => floatval( $meta['analysis']['score'] ) / 10, // Convert 1-10 to 0-1
                'url'            => $meta['url'] ?? '#',
                'tags'           => $tags,
                'source_name'    => $meta['source'] ?? 'Unknown'
            );
        }

        if ( empty( $candidates ) ) {
            $this->log( "No high-value intelligence found to curate." );
            return;
        }

        // Limit to top 10 articles by score
        usort( $candidates, function( $a, $b ) {
            return $b['priority_score'] <=> $a['priority_score'];
        });
        $candidates = array_slice( $candidates, 0, 10 );

        $this->generate_report( $candidates );
    }

    /**
     * Generate daily digest report using LLM
     *
     * @param array $items Candidate articles for the digest
     */
    private function generate_report( $items ) {
        $date_str = date_i18n( get_option( 'date_format' ) );
        $title = "Daily Intelligence Digest - $date_str";

        // Check if report already exists for today - if so, update it
        $existing_id = post_exists( $title, '', '', 'ic_report' );
        if ( $existing_id ) {
            $this->log( "Report '$title' already exists (ID: $existing_id). Will update." );
        }

        // Call LLM to generate structured digest
        $api_key = get_option( 'ic_openai_key' );
        $digest = null;

        if ( $api_key ) {
            $digest = $this->generate_digest_with_llm( $items, $api_key );
        }

        // Format content based on LLM result or fallback
        if ( $digest && isset( $digest['top_articles'] ) ) {
            $content = $this->format_digest_content( $digest );
        } else {
            $this->log( "LLM digest generation failed, using fallback format." );
            $content = $this->format_fallback_content( $items );
        }

        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'ic_report',
            'post_author'  => get_current_user_id() ?: 1
        );

        // If report exists, update it; otherwise create new
        if ( $existing_id ) {
            $post_data['ID'] = $existing_id;
            $post_id = wp_update_post( $post_data );
            if ( $post_id ) {
                $this->log( "Updated Report ID: $post_id" );
            } else {
                $this->log( "Failed to update report." );
            }
        } else {
            $post_id = wp_insert_post( $post_data );
            if ( $post_id ) {
                $this->log( "Created Report ID: $post_id" );
            } else {
                $this->log( "Failed to create report." );
            }
        }
    }

    /**
     * Call LLM to generate structured digest
     *
     * @param array $items Articles to curate
     * @param string $api_key OpenAI API key
     * @return array|null Structured digest data or null on failure
     */
    private function generate_digest_with_llm( $items, $api_key ) {
        $date_str = date( 'Y-m-d' );
        $articles_json = json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        $system_prompt = $this->get_curator_prompt();

        $user_prompt = "請根據以下文章列表生成今日摘要（日期: {$date_str}）：\n\n";
        $user_prompt .= "```json\n{$articles_json}\n```\n\n";
        $user_prompt .= "請以 JSON 格式回覆。";

        $args = array(
            'body'    => json_encode( array(
                'model'       => 'gpt-4o-mini',
                'messages'    => array(
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $user_prompt )
                ),
                'temperature' => 0.7,
            ) ),
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 60,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

        if ( is_wp_error( $response ) ) {
            $this->log( "OpenAI API Error: " . $response->get_error_message() );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $content_str = trim( $data['choices'][0]['message']['content'] );
            // Clean up markdown code blocks if present
            $content_str = preg_replace( '/^```json\s*/i', '', $content_str );
            $content_str = preg_replace( '/\s*```$/', '', $content_str );

            $digest = json_decode( $content_str, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $digest;
            }
            $this->log( "JSON parse error: " . json_last_error_msg() );
        }

        return null;
    }

    /**
     * Get the curator prompt template
     *
     * @return string Curator system prompt
     */
    private function get_curator_prompt() {
        $user_name = get_option( 'ic_user_name', 'User' );

        return <<<PROMPT
# Curator Daily Agent Instruction

## 角色定義
你是 InsightCosmos 的每日情報策展人（Daily Curator），專注於從已分析的 AI 與 Robotics 文章中提煉精華，為 {$user_name} 生成精簡而有洞察力的每日摘要。

## 任務目標
從提供的文章列表中：
1. 整合關鍵資訊
2. 識別共同趨勢
3. 提取核心要點
4. 生成可行動的建議（可選）

## 執行步驟

### Step 1: 文章分析
對每篇文章：
- 理解核心內容（根據 summary）
- 評估價值（已有 priority_score）
- 提取 1 個最重要的要點（key_takeaway）

### Step 2: 趨勢識別
- 識別文章間的共同主題
- 發現技術趨勢或產業動態
- 總結為 2-3 句話的「今日洞察」

### Step 3: 行動建議（可選）
- 如果有明確的學習方向或行動建議，簡短說明

## 輸出格式（JSON）

你必須以下面的 JSON 格式回覆，不要添加任何額外的註解或說明：

```json
{
  "date": "YYYY-MM-DD",
  "total_articles": 8,
  "top_articles": [
    {
      "title": "原文章標題",
      "url": "原文章 URL",
      "summary": "精簡摘要（1-2 句，不超過 100 字）",
      "key_takeaway": "核心要點（1 句話，20-40 字）",
      "priority_score": 0.92,
      "tags": ["AI", "Robotics"]
    }
  ],
  "daily_insight": "今日趨勢總結（2-3 句，100-150 字）",
  "recommended_action": "建議行動（可選，1 句話）"
}
```

## 品質標準

### 精簡原則
- summary: 1-2 句話，不超過 100 字
- key_takeaway: 1 句話，20-40 字
- daily_insight: 2-3 句話，100-150 字

### 價值導向
- 聚焦於有實際價值的內容
- 突出「為什麼重要」而非「是什麼」

### 注意事項
1. 嚴格遵循 JSON 格式，不要添加額外註解
2. 使用繁體中文，專業術語保留英文
3. 尊重原文事實，不要捏造或過度推測
4. tags 必須是陣列格式
5. priority_score 保留原始數值
PROMPT;
    }

    /**
     * Format digest content from LLM response
     *
     * @param array $digest Structured digest from LLM
     * @return string WordPress block content
     */
    private function format_digest_content( $digest ) {
        $content = '';

        // Header
        $total = $digest['total_articles'] ?? count( $digest['top_articles'] );
        $content .= "<!-- wp:paragraph -->\n";
        $content .= "<p>📊 <strong>今日精選</strong>：{$total} 篇文章</p>\n";
        $content .= "<!-- /wp:paragraph -->\n\n";

        // Articles
        if ( ! empty( $digest['top_articles'] ) ) {
            foreach ( $digest['top_articles'] as $index => $article ) {
                $num = $index + 1;
                $score = isset( $article['priority_score'] ) ? $article['priority_score'] : 0;
                $score_display = number_format( $score, 2 );

                // Priority color coding
                if ( $score >= 0.9 ) {
                    $priority_class = 'high';
                    $border_color = '#ea4335';
                } elseif ( $score >= 0.7 ) {
                    $priority_class = 'medium';
                    $border_color = '#fbbc04';
                } else {
                    $priority_class = 'low';
                    $border_color = '#34a853';
                }

                // Tags
                $tags = isset( $article['tags'] ) ? $article['tags'] : array();
                if ( is_string( $tags ) ) {
                    $tags = array_map( 'trim', explode( ',', $tags ) );
                }
                $tags_str = implode( ', ', $tags );

                $content .= "<!-- wp:group {\"style\":{\"border\":{\"left\":{\"color\":\"{$border_color}\",\"width\":\"4px\"}},\"spacing\":{\"padding\":{\"left\":\"15px\"}}}} -->\n";
                $content .= "<div class=\"wp-block-group\" style=\"border-left-color:{$border_color};border-left-width:4px;padding-left:15px\">\n";

                // Title
                $url = esc_url( $article['url'] ?? '#' );
                $title = esc_html( $article['title'] ?? 'Untitled' );
                $content .= "<!-- wp:heading {\"level\":4} -->\n";
                $content .= "<h4>[{$num}] <a href=\"{$url}\" target=\"_blank\">{$title}</a></h4>\n";
                $content .= "<!-- /wp:heading -->\n";

                // Summary
                $summary = esc_html( $article['summary'] ?? '' );
                $content .= "<!-- wp:paragraph -->\n";
                $content .= "<p>📝 {$summary}</p>\n";
                $content .= "<!-- /wp:paragraph -->\n";

                // Key Takeaway
                if ( ! empty( $article['key_takeaway'] ) ) {
                    $takeaway = esc_html( $article['key_takeaway'] );
                    $content .= "<!-- wp:paragraph {\"style\":{\"color\":{\"background\":\"#fff3cd\"}}} -->\n";
                    $content .= "<p style=\"background-color:#fff3cd\">💡 <strong>核心要點</strong>：{$takeaway}</p>\n";
                    $content .= "<!-- /wp:paragraph -->\n";
                }

                // Meta (tags + score)
                $content .= "<!-- wp:paragraph {\"style\":{\"typography\":{\"fontSize\":\"14px\"}}} -->\n";
                $content .= "<p style=\"font-size:14px\">🏷️ {$tags_str} | ⭐ {$score_display}</p>\n";
                $content .= "<!-- /wp:paragraph -->\n";

                $content .= "</div>\n";
                $content .= "<!-- /wp:group -->\n\n";
            }
        }

        // Daily Insight section
        if ( ! empty( $digest['daily_insight'] ) ) {
            $insight = esc_html( $digest['daily_insight'] );
            $content .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->\n\n";
            $content .= "<!-- wp:group {\"style\":{\"color\":{\"background\":\"#e8f4fd\"},\"spacing\":{\"padding\":{\"top\":\"20px\",\"bottom\":\"20px\",\"left\":\"20px\",\"right\":\"20px\"}}}} -->\n";
            $content .= "<div class=\"wp-block-group has-background\" style=\"background-color:#e8f4fd;padding:20px\">\n";
            $content .= "<!-- wp:heading {\"level\":3} -->\n";
            $content .= "<h3>💡 今日洞察</h3>\n";
            $content .= "<!-- /wp:heading -->\n";
            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>{$insight}</p>\n";
            $content .= "<!-- /wp:paragraph -->\n";
            $content .= "</div>\n";
            $content .= "<!-- /wp:group -->\n\n";
        }

        // Recommended Action section
        if ( ! empty( $digest['recommended_action'] ) ) {
            $action = esc_html( $digest['recommended_action'] );
            $content .= "<!-- wp:group {\"style\":{\"color\":{\"background\":\"#d4edda\"},\"spacing\":{\"padding\":{\"top\":\"20px\",\"bottom\":\"20px\",\"left\":\"20px\",\"right\":\"20px\"}}}} -->\n";
            $content .= "<div class=\"wp-block-group has-background\" style=\"background-color:#d4edda;padding:20px\">\n";
            $content .= "<!-- wp:heading {\"level\":3} -->\n";
            $content .= "<h3>🎯 建議行動</h3>\n";
            $content .= "<!-- /wp:heading -->\n";
            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>{$action}</p>\n";
            $content .= "<!-- /wp:paragraph -->\n";
            $content .= "</div>\n";
            $content .= "<!-- /wp:group -->\n\n";
        }

        // Footer
        $content .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->\n\n";
        $content .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"typography\":{\"fontSize\":\"12px\"},\"color\":{\"text\":\"#666666\"}}} -->\n";
        $content .= "<p class=\"has-text-align-center has-text-color\" style=\"color:#666666;font-size:12px\">由 InsightCosmos 自動生成 | Powered by OpenAI</p>\n";
        $content .= "<!-- /wp:paragraph -->\n";

        return $content;
    }

    /**
     * Fallback content format when LLM is unavailable
     *
     * @param array $items Raw article items
     * @return string WordPress block content
     */
    private function format_fallback_content( $items ) {
        $content = "<!-- wp:paragraph -->\n";
        $content .= "<p>📊 <strong>今日精選</strong>：" . count( $items ) . " 篇文章</p>\n";
        $content .= "<!-- /wp:paragraph -->\n\n";

        foreach ( $items as $index => $item ) {
            $num = $index + 1;
            $score = $item['priority_score'] ?? 0;
            $score_display = number_format( $score, 2 );

            // Priority indicator
            if ( $score >= 0.9 ) {
                $emoji = '🔥';
                $border_color = '#ea4335';
            } elseif ( $score >= 0.7 ) {
                $emoji = '⭐';
                $border_color = '#fbbc04';
            } else {
                $emoji = '📌';
                $border_color = '#34a853';
            }

            $tags = $item['tags'] ?? array();
            if ( is_array( $tags ) ) {
                $tags_str = implode( ', ', $tags );
            } else {
                $tags_str = $tags;
            }

            $url = esc_url( $item['url'] ?? '#' );
            $title = esc_html( $item['title'] ?? 'Untitled' );
            $summary = esc_html( $item['summary'] ?? '' );

            $content .= "<!-- wp:group {\"style\":{\"border\":{\"left\":{\"color\":\"{$border_color}\",\"width\":\"4px\"}},\"spacing\":{\"padding\":{\"left\":\"15px\"}}}} -->\n";
            $content .= "<div class=\"wp-block-group\" style=\"border-left-color:{$border_color};border-left-width:4px;padding-left:15px\">\n";
            $content .= "<!-- wp:heading {\"level\":4} -->\n";
            $content .= "<h4>{$emoji} [{$num}] <a href=\"{$url}\" target=\"_blank\">{$title}</a></h4>\n";
            $content .= "<!-- /wp:heading -->\n";
            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p><em>{$summary}</em></p>\n";
            $content .= "<!-- /wp:paragraph -->\n";
            $content .= "<!-- wp:paragraph {\"style\":{\"typography\":{\"fontSize\":\"14px\"}}} -->\n";
            $content .= "<p style=\"font-size:14px\">🏷️ {$tags_str} | ⭐ {$score_display}</p>\n";
            $content .= "<!-- /wp:paragraph -->\n";
            $content .= "</div>\n";
            $content .= "<!-- /wp:group -->\n\n";
        }

        $content .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->\n\n";
        $content .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"typography\":{\"fontSize\":\"12px\"},\"color\":{\"text\":\"#666666\"}}} -->\n";
        $content .= "<p class=\"has-text-align-center has-text-color\" style=\"color:#666666;font-size:12px\">由 InsightCosmos 自動生成</p>\n";
        $content .= "<!-- /wp:paragraph -->\n";

        return $content;
    }
}
