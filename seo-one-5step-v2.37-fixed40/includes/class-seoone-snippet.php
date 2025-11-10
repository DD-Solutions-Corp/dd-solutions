<?php
/**
 * AIコードスニペット生成機能
 *
 * ユーザーが記述した要件に基づき、PHP/JS/CSS などのコードスニペットをAIで生成します。
 * WordPress コードの自動挿入に役立つスニペットを容易に作成できます。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Snippet {
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
            'コード生成',
            'コード生成',
            SEOONE_CAP,
            'seoone-snippet',
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
        echo '<h1>AIコード生成</h1>';

        $output = '';
        $description = '';
        $language = 'php';
        if ( isset( $_POST['seoone_sn_nonce'] ) && wp_verify_nonce( $_POST['seoone_sn_nonce'], 'seoone_sn_action' ) ) {
            $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
            $language    = sanitize_text_field( $_POST['code_lang'] ?? 'php' );
            if ( ! empty( $description ) ) {
                $output = $this->generate_snippet( $description, $language );
            }
        }

        // フォーム
        echo '<form method="post" action="" class="seoone-admin-form">';
        wp_nonce_field( 'seoone_sn_action', 'seoone_sn_nonce' );
        echo '<p>生成したいコードの要件を入力してください。例えば「投稿一覧の最新5件を表示するショートコードを作成」。</p>';
        echo '<textarea name="description" rows="6" style="width:100%;" placeholder="コードの要件を入力">' . esc_textarea( $description ) . '</textarea>';
        echo '<p>言語: <select name="code_lang">';
        $langs = array( 'php' => 'PHP', 'js' => 'JavaScript', 'css' => 'CSS', 'html' => 'HTML' );
        foreach ( $langs as $key => $label ) {
            $selected = selected( $language, $key, false );
            echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></p>';
        echo '<p><button type="submit" class="button button-primary">コードを生成</button></p>';
        echo '</form>';

        if ( $output ) {
            echo '<h2>生成されたコード</h2>';
            echo '<p>以下のコードをfunctions.phpやカスタムプラグインに貼り付けて利用してください。</p>';
            echo '<pre style="background:#f7f7f7;padding:10px;border:1px solid #eee;overflow:auto;">' . esc_html( $output ) . '</pre>';
        }
        echo '</div>';
    }

    /**
     * AIでコードスニペットを生成
     *
     * @param string $description 要件
     * @param string $language    言語
     * @return string 生成コード
     */
    private function generate_snippet( $description, $language ) {
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
        // 言語ごとにシステムメッセージを調整
        $sys = 'You are an expert WordPress developer. Generate clean and safe ' . strtoupper( $language ) . ' code snippets for WordPress.';
        // ユーザーからの指示
        $prompt = '要件: ' . $description . "\n"
                . '以下の言語でコードスニペットを生成してください: ' . strtoupper( $language ) . ".\n"
                . 'コードのみを出力し、コメントも含めてください。';
        $body = array(
            'model' => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => $sys ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'max_tokens' => 2048,
            'temperature' => 0.2,
        );
        $response = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 60 ) );
        if ( is_wp_error( $response ) ) {
            return 'AIとの通信に失敗しました: ' . $response->get_error_message();
        }
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return 'AIからの応答に問題があります: ' . wp_remote_retrieve_body( $response );
        }
        $j = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = $j['choices'][0]['message']['content'] ?? '';
        return $code;
    }
}