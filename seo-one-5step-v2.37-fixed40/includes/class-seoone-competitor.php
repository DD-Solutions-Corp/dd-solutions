<?php
/**
 * コンテンツ競合分析機能
 *
 * 指定されたキーワードや記事タイトルに対して、検索上位記事が取り扱っている主な見出しやトピックをAIに分析させ、
 * 本文に不足しているコンテンツや差別化ポイントを提案します。競合調査が難しいユーザーでも、適切なアウトラインが得られます。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Competitor {

    /**
     * 初期化
     */
    public static function init() {
        $self = new self();
        add_action( 'admin_menu', [ $self, 'register_menu' ] );
    }

    /**
     * 管理画面メニュー登録
     */
    public function register_menu() {
        add_submenu_page(
            'seoone',
            '競合分析',
            '競合分析',
            SEOONE_CAP,
            'seoone-competitor',
            [ $this, 'render_page' ]
        );
    }

    /**
     * ページ描画
     */
    public function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $keyword = '';
        $analysis = '';
        if ( isset( $_POST['seoone_competitor_nonce'] ) && wp_verify_nonce( $_POST['seoone_competitor_nonce'], 'seoone_competitor_action' ) ) {
            $keyword = sanitize_text_field( wp_unslash( $_POST['competitor_keyword'] ?? '' ) );
            if ( ! empty( $keyword ) ) {
                $analysis = $this->analyze_keyword( $keyword );
            }
        }
        echo '<div class="wrap">';
        echo '<h1>競合分析</h1>';
        echo '<p>キーワードや記事タイトルを入力してください。AIが検索上位記事の一般的な見出しや取り扱うトピックを推測し、コンテンツの改善点を提案します。</p>';
        echo '<form method="post" action="" class="seoone-admin-form">';
        wp_nonce_field( 'seoone_competitor_action', 'seoone_competitor_nonce' );
        echo '<input type="text" name="competitor_keyword" style="width:100%;" value="' . esc_attr( $keyword ) . '" placeholder="例：SEOプラグイン 比較" />';
        echo '<p><button type="submit" class="button button-primary">分析を実行</button></p>';
        echo '</form>';
        if ( $analysis ) {
            echo '<h2>分析結果</h2>';
            echo '<pre style="background:#f9f9f9;border:1px solid #ddd;padding:10px;overflow:auto;">' . esc_html( $analysis ) . '</pre>';
        }
        echo '</div>';
    }

    /**
     * 指定キーワードについてAIに競合分析をさせる
     *
     * @param string $keyword
     * @return string 分析結果
     */
    private function analyze_keyword( $keyword ) {
        $opt = get_option( 'seoone_settings', array() );
        $api_key = $opt['ai_api_key'] ?? '';
        $model   = $opt['ai_model'] ?? 'gpt-4o';
        if ( empty( $api_key ) ) {
            return 'AI APIキーが設定されていません。設定ページでキーを入力してください。';
        }
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        $headers  = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        );
        $prompt = 'You are an SEO content strategist. For the keyword or topic "' . $keyword . '", analyze the top ranking articles and identify typical headings and subtopics covered.';
        $prompt .= ' Suggest additional angles or gaps that could be addressed to create a more comprehensive and unique article.';
        $prompt .= ' Provide your output in Japanese. Use bullet points for each heading or idea.';
        $body = array(
            'model' => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => 'You are an expert SEO assistant.' ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'max_tokens'   => 1024,
            'temperature'  => 0.5,
        );
        $response = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 60 ) );
        if ( is_wp_error( $response ) ) {
            return 'AIとの通信に失敗しました: ' . $response->get_error_message();
        }
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return 'AIからの応答に問題があります: ' . wp_remote_retrieve_body( $response );
        }
        $j = json_decode( wp_remote_retrieve_body( $response ), true );
        $text = $j['choices'][0]['message']['content'] ?? '';
        return $text;
    }
}