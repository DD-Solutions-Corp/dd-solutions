<?php
/**
 * タイトル・メタディスクリプション提案
 *
 * 投稿の内容を基にAIが魅力的なタイトルとメタディスクリプションを提案します。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_TitleMeta {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_seoone_titlemeta_suggest', array( __CLASS__, 'handle_suggestion' ) );
        add_action( 'admin_post_seoone_titlemeta_apply', array( __CLASS__, 'handle_apply' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'タイトル・メタ提案',
            'タイトル・メタ提案',
            SEOONE_CAP,
            'seoone-titlemeta',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * 記事リストページと提案表示
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap">';
        echo '<h1>タイトル・メタディスクリプション提案</h1>';
        $post_id = isset( $_GET['suggest_post_id'] ) ? intval( $_GET['suggest_post_id'] ) : 0;
        // 提案がある場合
        if ( $post_id ) {
            $suggest = get_transient( 'seoone_titlemeta_' . $post_id );
            if ( ! $suggest ) {
                echo '<p>提案が見つかりませんでした。期限切れの可能性があります。</p>';
            } else {
                $post = get_post( $post_id );
                echo '<h2>『' . esc_html( $post->post_title ) . '』への提案</h2>';
                echo '<table class="form-table">';
                echo '<tr><th>現在のタイトル</th><td>' . esc_html( $post->post_title ) . '</td></tr>';
                echo '<tr><th>提案されたタイトル</th><td>' . esc_html( $suggest['title'] ) . '</td></tr>';
                $current_meta = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
                echo '<tr><th>現在のメタディスクリプション</th><td>' . esc_html( $current_meta ?: '(未設定)' ) . '</td></tr>';
                echo '<tr><th>提案されたメタディスクリプション</th><td>' . esc_html( $suggest['description'] ) . '</td></tr>';
                echo '</table>';
                // 採用フォーム
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'seoone_titlemeta_apply_' . $post_id );
                echo '<input type="hidden" name="action" value="seoone_titlemeta_apply">';
                echo '<input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '">';
                echo '<input type="hidden" name="new_title" value="' . esc_attr( $suggest['title'] ) . '">';
                echo '<input type="hidden" name="new_meta" value="' . esc_attr( $suggest['description'] ) . '">';
                submit_button( '提案を採用して更新', 'primary' );
                echo '</form>';
                echo '<p><a href="' . esc_url( remove_query_arg( 'suggest_post_id' ) ) . '" class="button">戻る</a></p>';
            }
            echo '</div>';
            return;
        }
        // 記事一覧表示
        echo '<p>タイトルとメタディスクリプションの改善案をAIに依頼できます。改善したい投稿の「提案生成」をクリックしてください。</p>';
        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        if ( empty( $posts ) ) {
            echo '<p>対象の投稿がありません。</p>';
            echo '</div>';
            return;
        }
        echo '<table class="widefat"><thead><tr><th>タイトル</th><th>メタディスクリプション</th><th>アクション</th></tr></thead><tbody>';
        foreach ( $posts as $post ) {
            $meta = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
            echo '<tr>';
            echo '<td>' . esc_html( $post->post_title ) . '</td>';
            echo '<td>' . esc_html( $meta ?: '(未設定)' ) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
            wp_nonce_field( 'seoone_titlemeta_suggest_' . $post->ID );
            echo '<input type="hidden" name="action" value="seoone_titlemeta_suggest">';
            echo '<input type="hidden" name="post_id" value="' . esc_attr( $post->ID ) . '">';
            submit_button( '提案生成', 'small', 'submit', false );
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * 提案生成ハンドラ
     */
    public static function handle_suggestion() {
        if ( ! current_user_can( SEOONE_CAP ) ) {
            wp_die( '権限がありません。' );
        }
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_redirect( admin_url( 'admin.php?page=seoone-titlemeta' ) );
            exit;
        }
        // nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'seoone_titlemeta_suggest_' . $post_id ) ) {
            wp_die( 'セキュリティチェックに失敗しました。' );
        }
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_die( '投稿が見つかりません。' );
        }
        // AI API キー
        $opt = get_option( 'seoone_settings', array() );
        $api_key = $opt['ai_api_key'] ?? '';
        $model = $opt['ai_model'] ?? 'gpt-4o';
        if ( empty( $api_key ) ) {
            wp_die( 'AI API キーが未設定です。SEO ONE 設定ページで設定してください。' );
        }
        // プロンプト作成
        $content = wp_strip_all_tags( $post->post_content );
        $prompt = '以下の日本語ブログ記事の内容を参考に、より魅力的で SEO に強い新しいタイトルと、160字程度のメタディスクリプションを提案してください。出力は JSON 形式で {"title":"タイトル","description":"メタディスクリプション"} としてください。\n\n現在のタイトル: ' . $post->post_title . '\n本文: ' . mb_substr( $content, 0, 2000 ) . '...';
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        );
        $body = array(
            'model' => $model,
            'messages' => array(
                array('role'=>'system','content'=>'You are a helpful assistant that writes concise Japanese SEO content suggestions.'),
                array('role'=>'user','content'=>$prompt),
            ),
            'max_tokens' => 600,
            'temperature' => 0.5,
        );
        $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>120) );
        $suggest = array();
        if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
            $j = json_decode( wp_remote_retrieve_body( $resp ), true );
            $content = $j['choices'][0]['message']['content'] ?? '';
            // JSON 抽出
            if ( $content ) {
                $content = trim( $content );
                // 先頭や末尾にコードブロックが含まれている場合を考慮
                $json_str = preg_replace( '/^```json|```$/m', '', $content );
                $arr = json_decode( $json_str, true );
                if ( is_array( $arr ) && isset( $arr['title'] ) && isset( $arr['description'] ) ) {
                    $suggest = $arr;
                }
            }
        }
        if ( empty( $suggest ) ) {
            // fallback: 元タイトルと記事冒頭抜粋
            $suggest = array(
                'title' => $post->post_title,
                'description' => mb_substr( wp_strip_all_tags( $post->post_content ), 0, 160 ),
            );
        }
        // 一時保存 10 分
        set_transient( 'seoone_titlemeta_' . $post_id, $suggest, 10 * MINUTE_IN_SECONDS );
        wp_redirect( admin_url( 'admin.php?page=seoone-titlemeta&suggest_post_id=' . $post_id ) );
        exit;
    }

    /**
     * 提案採用ハンドラ
     */
    public static function handle_apply() {
        if ( ! current_user_can( SEOONE_CAP ) ) {
            wp_die( '権限がありません。' );
        }
        $post_id    = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_title  = isset( $_POST['new_title'] ) ? sanitize_text_field( wp_unslash( $_POST['new_title'] ) ) : '';
        $new_meta   = isset( $_POST['new_meta'] ) ? sanitize_text_field( wp_unslash( $_POST['new_meta'] ) ) : '';
        if ( ! $post_id || ! $new_title ) {
            wp_die( '必要なデータがありません。' );
        }
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'seoone_titlemeta_apply_' . $post_id ) ) {
            wp_die( 'セキュリティチェックに失敗しました。' );
        }
        // 更新
        $update = array(
            'ID' => $post_id,
            'post_title' => $new_title,
        );
        wp_update_post( $update );
        if ( $new_meta ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', $new_meta );
        }
        delete_transient( 'seoone_titlemeta_' . $post_id );
        wp_redirect( admin_url( 'admin.php?page=seoone-titlemeta&updated=1' ) );
        exit;
    }
}