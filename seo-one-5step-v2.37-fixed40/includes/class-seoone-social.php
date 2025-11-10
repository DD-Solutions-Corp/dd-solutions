<?php
/**
 * SNS投稿生成機能
 *
 * 記事タイトルや要約から、X（旧Twitter）やLinkedInなどのSNS投稿文をAIで生成します。
 * 投稿ごとに適切な文字数やトーンを考慮し、ハッシュタグも提案します。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Social {
    /**
     * 初期化
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
            'SNS投稿生成',
            'SNS投稿生成',
            SEOONE_CAP,
            'seoone-social',
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
        echo '<h1>SNS投稿生成</h1>';

        $result = '';
        $title  = '';
        $keywords = '';
        $platform = 'twitter';
        if ( isset( $_POST['seoone_social_nonce'] ) && wp_verify_nonce( $_POST['seoone_social_nonce'], 'seoone_social_action' ) ) {
            $title = sanitize_text_field( wp_unslash( $_POST['social_title'] ?? '' ) );
            $keywords = sanitize_text_field( wp_unslash( $_POST['social_keywords'] ?? '' ) );
            $platform = sanitize_text_field( $_POST['social_platform'] ?? 'twitter' );
            if ( ! empty( $title ) ) {
                $result = $this->generate_post( $title, $keywords, $platform );
            }
        }

        echo '<form method="post" action="" class="seoone-admin-form">';
        wp_nonce_field( 'seoone_social_action', 'seoone_social_nonce' );
        echo '<p>記事タイトル:</p>';
        echo '<input type="text" name="social_title" style="width:100%;" value="' . esc_attr( $title ) . '" placeholder="例：SEOプラグインでサイト流入を増やす方法" />';
        echo '<p>強調したいキーワード（任意）:</p>';
        echo '<input type="text" name="social_keywords" style="width:100%;" value="' . esc_attr( $keywords ) . '" placeholder="例：SEO, AI" />';
        echo '<p>投稿先SNS:</p>';
        echo '<select name="social_platform">';
        $platforms = array(
            'twitter'  => 'X（旧Twitter）',
            'linkedin' => 'LinkedIn',
            'facebook' => 'Facebook',
        );
        foreach ( $platforms as $key => $label ) {
            $selected = selected( $platform, $key, false );
            echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p><button type="submit" class="button button-primary">投稿文を生成</button></p>';
        echo '</form>';

        if ( $result ) {
            echo '<h2>生成された投稿文</h2>';
            echo '<pre style="background:#f7f7f7;padding:10px;border:1px solid #eee;overflow:auto;">' . esc_html( $result ) . '</pre>';
        }
        echo '</div>';
    }

    /**
     * AIでSNS投稿文を生成
     *
     * @param string $title 記事タイトル
     * @param string $keywords キーワード
     * @param string $platform SNSプラットフォーム
     * @return string 投稿文
     */
    private function generate_post( $title, $keywords, $platform ) {
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
        // プラットフォーム別の条件
        switch ( $platform ) {
            case 'linkedin':
                $inst = 'Create a professional LinkedIn post summarizing the article titled "' . $title . '".';
                $inst .= ' Include relevant hashtags and a call-to-action to read more.';
                $max_len = 600; // LinkedIn posts can be longer
                break;
            case 'facebook':
                $inst = 'Create an engaging Facebook post about the article titled "' . $title . '". Use a friendly tone and suggest that readers check the full article.';
                $max_len = 500;
                break;
            default: // twitter
                $inst = 'Create a concise X (formerly Twitter) post summarizing the article titled "' . $title . '".';
                $inst .= ' Use emojis if appropriate and add up to three relevant hashtags.';
                $max_len = 280;
                break;
        }
        if ( ! empty( $keywords ) ) {
            $inst .= ' Try to include these keywords: ' . $keywords . '.';
        }
        $prompt = $inst . ' Limit the post to approximately ' . $max_len . ' characters.';
        $body = array(
            'model' => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => 'You are a helpful assistant that writes social media posts.' ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'max_tokens' => 1024,
            'temperature' => 0.5,
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