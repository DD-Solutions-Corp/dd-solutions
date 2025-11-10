<?php
/**
 * 内部リンク提案ページ
 *
 * Search Console に基づく改善キーワードを元に、既存記事への内部リンク候補を探し、
 * 管理者に提案するページです。SEO の内部リンク強化に役立ちます。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Link_Suggestions {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            '内部リンク提案',
            '内部リンク提案',
            SEOONE_CAP,
            'seoone-link-suggestions',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $top_queries = get_option( 'seoone_metrics_top_queries', array() );
        // 改善候補を絞り込む
        $improvement = array();
        foreach ( $top_queries as $row ) {
            $ctr_pct = isset( $row['ctr'] ) ? (float)$row['ctr'] * 100 : 0;
            $pos = isset( $row['position'] ) ? (float)$row['position'] : 0;
            if ( $ctr_pct > 5 && $pos > 10 ) {
                $improvement[] = $row;
            }
        }
        echo '<div class="wrap">';
        echo '<h1>内部リンク提案</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        if ( empty( $improvement ) ) {
            echo '<p>改善が見込めるキーワードが見つかりませんでした。Search Console データ取得後に自動的に表示されます。</p>';
        } else {
            echo '<p>CTR が高く平均順位が低いキーワードに対して、既存記事内への内部リンク候補を表示します。記事に追記して内部リンクを増やしましょう。</p>';
            foreach ( $improvement as $row ) {
                $query = sanitize_text_field( $row['query'] );
                echo '<h2>' . esc_html( $query ) . '</h2>';
                // 投稿を検索
                $posts = get_posts( array(
                    's' => $query,
                    'post_status' => 'publish',
                    'posts_per_page' => 3,
                ) );
                if ( empty( $posts ) ) {
                    echo '<p>このキーワードを含む既存記事は見つかりませんでした。</p>';
                } else {
                    echo '<ul>';
                    foreach ( $posts as $post ) {
                        $url = get_edit_post_link( $post->ID );
                        echo '<li>';
                        echo '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a> - '; 
                        echo esc_html( mb_strimwidth( wp_strip_all_tags( $post->post_content ), 0, 100, '...' ) );
                        echo '</li>';
                    }
                    echo '</ul>';
                }
            }
        }
        echo '</div>';
    }
}