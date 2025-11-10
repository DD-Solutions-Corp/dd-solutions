<?php
/**
 * クイズ生成機能
 *
 * 指定したトピックや記事タイトルを基に、AIが複数選択式のクイズを生成します。
 * 生成されたクイズはHTML形式で出力され、そのまま記事やページに貼り付けることができます。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Quiz {
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
            'クイズ生成',
            'クイズ生成',
            SEOONE_CAP,
            'seoone-quiz',
            [ $this, 'render_page' ]
        );
    }

    /**
     * ページ描画
     */
    public function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $topic = '';
        $num   = 3;
        $quiz_html = '';
        $error_msg = '';
        if ( isset( $_POST['seoone_quiz_nonce'] ) && wp_verify_nonce( $_POST['seoone_quiz_nonce'], 'seoone_quiz_action' ) ) {
            $topic = sanitize_text_field( wp_unslash( $_POST['quiz_topic'] ?? '' ) );
            $num   = intval( $_POST['quiz_num'] ?? 3 );
            if ( $num < 1 ) $num = 1;
            if ( $num > 10 ) $num = 10;
            if ( ! empty( $topic ) ) {
                $result = $this->generate_quiz( $topic, $num );
                if ( is_wp_error( $result ) ) {
                    $error_msg = $result->get_error_message();
                } else {
                    $quiz_html = $result;
                }
            }
        }
        echo '<div class="wrap">';
        echo '<h1>クイズ生成</h1>';
        echo '<p>トピックや記事タイトルを入力すると、その内容に基づいた複数選択式のクイズをAIが生成します。</p>';
        echo '<form method="post" action="" class="seoone-admin-form">';
        wp_nonce_field( 'seoone_quiz_action', 'seoone_quiz_nonce' );
        echo '<p>トピック/キーワード:</p>';
        echo '<input type="text" name="quiz_topic" style="width:100%;" value="' . esc_attr( $topic ) . '" placeholder="例：WordPress SEO" />';
        echo '<p>問題数 (1〜10):</p>';
        echo '<input type="number" name="quiz_num" min="1" max="10" value="' . esc_attr( $num ) . '" />';
        echo '<p><button type="submit" class="button button-primary">クイズを生成</button></p>';
        echo '</form>';
        if ( $error_msg ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error_msg ) . '</p></div>';
        }
        if ( $quiz_html ) {
            echo '<h2>生成されたクイズ</h2>';
            echo '<p>以下のHTMLをコピーして投稿や固定ページに貼り付けてください。</p>';
            echo '<textarea readonly style="width:100%;height:250px;">' . esc_textarea( $quiz_html ) . '</textarea>';
            // 表示版
            echo '<div style="margin-top:20px;">';
            echo $quiz_html;
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * AIにクイズを生成させ、HTMLを返す
     *
     * @param string $topic
     * @param int    $num
     * @return string|WP_Error HTMLまたはエラー
     */
    private function generate_quiz( $topic, $num ) {
        $opt = get_option( 'seoone_settings', array() );
        $api_key = $opt['ai_api_key'] ?? '';
        $model   = $opt['ai_model'] ?? 'gpt-4o';
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_key', 'AI APIキーが設定されていません。設定ページでキーを入力してください。' );
        }
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        $headers  = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        );
        // プロンプト：クイズ生成用JSON
        $prompt = 'Create a multiple-choice quiz in JSON format for the topic "' . $topic . '".';
        $prompt .= ' Provide ' . $num . ' questions. Each question should have four options labelled A, B, C and D.';
        $prompt .= ' For each question, specify which option is correct and include a short explanation.';
        $prompt .= ' Respond in JSON with the structure: {"questions": [ {"question": "...", "options": {"A": "...", "B": "...", "C": "...", "D": "..."}, "answer": "A", "explanation": "..."}, ... ] }. Use Japanese for the question text, options and explanation.';
        $body = array(
            'model' => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => 'You are an assistant that creates educational multiple-choice quizzes.' ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'max_tokens'   => 1024,
            'temperature'  => 0.5,
        );
        $response = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 60 ) );
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'AIとの通信に失敗しました: ' . $response->get_error_message() );
        }
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return new WP_Error( 'api_error', 'AIからの応答に問題があります: ' . wp_remote_retrieve_body( $response ) );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $content = $data['choices'][0]['message']['content'] ?? '';
        // JSONを解析
        $quiz_data = json_decode( $content, true );
        if ( ! $quiz_data || ! isset( $quiz_data['questions'] ) || ! is_array( $quiz_data['questions'] ) ) {
            return new WP_Error( 'parse_error', 'AIからのクイズデータを解析できませんでした。応答内容: ' . $content );
        }
        // HTML組み立て
        $html  = '<div class="seoone-quiz">';
        $i = 1;
        foreach ( $quiz_data['questions'] as $q ) {
            $question = esc_html( $q['question'] ?? '' );
            $options  = $q['options'] ?? array();
            $answer   = $q['answer'] ?? '';
            $explain  = esc_html( $q['explanation'] ?? '' );
            $qid = uniqid( 'quiz_' );
            $html .= '<div class="seoone-quiz-question" style="margin-bottom:20px;">';
            $html .= '<p><strong>Q' . $i . '.</strong> ' . $question . '</p>';
            if ( is_array( $options ) ) {
                foreach ( $options as $opt_key => $opt_val ) {
                    $opt_val = esc_html( $opt_val );
                    $input_id = $qid . '_' . $opt_key;
                    $html .= '<div><label for="' . $input_id . '">';
                    $html .= '<input type="radio" id="' . $input_id . '" name="' . $qid . '" value="' . esc_attr( $opt_key ) . '" /> ';
                    $html .= $opt_key . '. ' . $opt_val;
                    $html .= '</label></div>';
                }
                // 解答と解説を隠し要素として用意
                $html .= '<div id="' . $qid . '_answer" style="display:none;margin-top:5px;padding:5px;background:#f1f1f1;">正解: ' . esc_html( $answer ) . ' / ' . $explain . '</div>';
                $html .= '<button type="button" onclick="document.getElementById(\'' . $qid . '_answer\').style.display = \'' . "block" . '\';">解答を見る</button>';
            }
            $html .= '</div>';
            $i++;
        }
        $html .= '</div>';
        return $html;
    }
}