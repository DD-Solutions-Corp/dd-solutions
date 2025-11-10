<?php
/**
 * トレンドギャップ分析ページ
 *
 * 指定されたジャンルのトレンドキーワードと Search Console のトップクエリを比較し、
 * まだ記事が存在しないキーワードや流入が少ないキーワードを提案します。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_TrendGap {

    /**
     * 管理画面メニュー登録
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    /**
     * サブメニューの追加
     */
    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'トレンドギャップ分析',
            'トレンドギャップ分析',
            SEOONE_CAP,
            'seoone-trendgap',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * ページ描画
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap">';
        echo '<h1>トレンドギャップ分析</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        // フォーム処理
        $genre = '';
        $trending = array();
        if ( isset( $_POST['seoone_trendgap_genre'] ) ) {
            $genre = sanitize_text_field( wp_unslash( $_POST['seoone_trendgap_genre'] ) );
            if ( $genre ) {
                // fetch_trends.py を実行してトレンド取得
                $script_path = SEOONE_PLUGIN_DIR . 'fetch_trends.py';
                if ( file_exists( $script_path ) ) {
                    $genre_arg = escapeshellarg( $genre );
                    $cmd = 'python3 ' . escapeshellcmd( $script_path ) . ' --genre ' . $genre_arg . ' --json 2>/dev/null';
                    $json = shell_exec( $cmd );
                    if ( $json ) {
                        $data = json_decode( $json, true );
                        if ( is_array( $data ) && isset( $data['keywords'] ) && is_array( $data['keywords'] ) ) {
                            $trending = $data['keywords'];
                        }
                    }
                }
            }
        }
        echo '<form method="post">';
        echo '<table class="form-table">';
        echo '<tr><th>ジャンルを入力</th><td><input type="text" name="seoone_trendgap_genre" value="' . esc_attr( $genre ) . '" class="regular-text" placeholder="例：テクノロジー, 旅行"></td></tr>';
        echo '</table>';
        submit_button( 'トレンド取得' );
        echo '</form>';
        // 結果表示
        if ( $genre && empty( $trending ) ) {
            echo '<p>指定したジャンルのトレンドキーワードを取得できませんでした。ジャンル名を見直すか、後ほど再試行してください。</p>';
        }
        if ( ! empty( $trending ) ) {
            echo '<h2>トレンドキーワード一覧（ジャンル: ' . esc_html( $genre ) . '）</h2>';
            // Search Console のトップクエリを取得
            $top_queries = get_option( 'seoone_metrics_top_queries', array() );
            $top_words = array();
            foreach ( $top_queries as $row ) {
                if ( isset( $row['query'] ) ) {
                    $top_words[] = $row['query'];
                }
            }
            echo '<table class="widefat"><thead><tr><th>キーワード</th><th>トップクエリに含まれるか</th><th>既存記事</th><th>アクション</th></tr></thead><tbody>';
            foreach ( $trending as $kw ) {
                $kw = sanitize_text_field( $kw );
                $in_top = in_array( $kw, $top_words, true );
                // 関連投稿を検索
                $args = array(
                    's' => $kw,
                    'post_type' => 'post',
                    'posts_per_page' => 1,
                    'post_status' => 'any',
                    'fields' => 'ids',
                );
                $posts = get_posts( $args );
                $has_post = ! empty( $posts );
                echo '<tr>';
                echo '<td>' . esc_html( $kw ) . '</td>';
                echo '<td>' . ( $in_top ? '<span style="color:green;">◯</span>' : '<span style="color:red;">×</span>' ) . '</td>';
                echo '<td>';
                if ( $has_post ) {
                    $post_id = $posts[0];
                    $edit_url = get_edit_post_link( $post_id );
                    echo '<a href="' . esc_url( $edit_url ) . '" target="_blank">既存記事あり</a>';
                } else {
                    echo 'なし';
                }
                echo '</td>';
                echo '<td>';
                // 記事生成フォーム
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
                wp_nonce_field( 'seoone_generate_article' );
                echo '<input type="hidden" name="action" value="seoone_generate_article">';
                // トレンドキーワードをタイトルとして渡す
                echo '<input type="hidden" name="seoone_topic" value="' . esc_attr( $kw ) . '">';
                // ジャンルをキーワード空欄として自動取得しないよう空で渡す
                echo '<input type="hidden" name="seoone_keywords" value="">';
                // word_count, tone, schedule はデフォルト値に任せる
                submit_button( '生成', 'primary', 'submit', false );
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}