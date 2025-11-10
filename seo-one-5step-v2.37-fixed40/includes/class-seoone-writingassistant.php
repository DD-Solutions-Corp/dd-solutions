<?php
/**
 * 文章アシスタント機能
 *
 * 編集中のテキストをAIに送り、文法や語調を整えた提案を受け取るページを提供します。
 * ユーザーが入力した文章を読みやすく書き換えたり、改善点を箇条書きで示します。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_WritingAssistant {

    /**
     * 初期化処理
     */
    public static function init() {
        $self = new self();
        add_action( 'admin_menu', [ $self, 'register_menu' ] );
    }

    /**
     * メニュー登録
     */
    public function register_menu() {
        add_submenu_page(
            'seoone',
            '文章アシスタント',
            '文章アシスタント',
            SEOONE_CAP,
            'seoone-writingassistant',
            [ $this, 'render_page' ]
        );
    }

    /**
     * ページ描画
     */
    public function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>文章アシスタント</h1>';

        // 送信後にAI提案を表示
        $suggestion = '';
        $source_text = '';
        if ( isset( $_POST['seoone_wa_nonce'] ) && wp_verify_nonce( $_POST['seoone_wa_nonce'], 'seoone_wa_action' ) ) {
            $source_text = isset( $_POST['source_text'] ) ? wp_unslash( $_POST['source_text'] ) : '';
            $source_text = sanitize_textarea_field( $source_text );
            if ( ! empty( $source_text ) ) {
                $suggestion = $this->generate_suggestions( $source_text );
            }
        }

        // フォーム
        echo '<form method="post" action="">';
        wp_nonce_field( 'seoone_wa_action', 'seoone_wa_nonce' );
        echo '<p>改善したい文章を入力してください。</p>';
        echo '<textarea name="source_text" rows="10" style="width:100%;" placeholder="ここに文章を入力">' . esc_textarea( $source_text ) . '</textarea>';
        echo '<p><button type="submit" class="button button-primary">提案を生成</button></p>';
        echo '</form>';

        if ( $suggestion ) {
            echo '<h2>AIからの提案</h2>';
            echo '<div style="margin-top:10px;">';
            echo '<textarea rows="10" style="width:100%;">' . esc_textarea( $suggestion ) . '</textarea>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * AIに文章改善を依頼する
     *
     * @param string $text 元の文章
     * @return string 改善提案
     */
    private function generate_suggestions( $text ) {
        $opt = get_option( 'seoone_settings', array() );
        $api_key = $opt['ai_api_key'] ?? '';
        $model   = $opt['ai_model'] ?? 'gpt-4o';
        if ( empty( $api_key ) ) {
            return 'AI APIキーが設定されていません。\n設定ページでキーを入力してください。';
        }
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        $headers  = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        );
        // システムメッセージ（日本語対応）
        $sys = 'You are a helpful assistant that reviews and revises Japanese text for grammar, tone, and clarity.';
        // プロンプト
        $prompt = '以下の文章を読みやすく修正し、文法や誤字を直し、より明快な文章に整形した上で改善点を箇条書きで提案してください。\n文章:\n' . $text;
        $body = array(
            'model' => $model,
            'messages' => array(
                array('role' => 'system', 'content' => $sys),
                array('role' => 'user', 'content' => $prompt),
            ),
            'max_tokens' => 2048,
            'temperature' => 0.3,
        );
        $response = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 60 ) );
        if ( is_wp_error( $response ) ) {
            return 'AIとの通信に失敗しました: ' . $response->get_error_message();
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( 200 !== $code ) {
            return 'AIから予期せぬ応答: ' . esc_html( $body );
        }
        $j = json_decode( $body, true );
        if ( isset( $j['choices'][0]['message']['content'] ) ) {
            return $j['choices'][0]['message']['content'];
        }
        return 'AIからの提案を取得できませんでした。';
    }
}