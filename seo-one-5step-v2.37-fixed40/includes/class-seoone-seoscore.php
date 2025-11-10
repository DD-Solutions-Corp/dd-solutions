<?php
/**
 * SEO スコアページ
 *
 * 各記事の基本的な SEO チェックを行い、文字数・見出し数・内部リンク数・メタディスクリプションの有無を評価して簡易スコアを表示します。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_SeoScore {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'SEO スコア',
            'SEO スコア',
            SEOONE_CAP,
            'seoone-seo-score',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * 記事ごとの簡易 SEO 指標を計算します。
     *
     * @param WP_Post $post
     * @return array
     */
    public static function analyze_post( $post ) {
        $content = $post->post_content;
        // 単語数
        $word_count = str_word_count( wp_strip_all_tags( $content ) );
        // 見出し数 (h2,h3)
        preg_match_all( '/<h[2-3][^>]*>/i', $content, $matches );
        $heading_count = isset( $matches[0] ) ? count( $matches[0] ) : 0;
        // 内部リンク数
        preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $link_matches );
        $internal_links = 0;
        $external_links = 0;
        if ( ! empty( $link_matches[1] ) ) {
            foreach ( $link_matches[1] as $url ) {
                if ( strpos( $url, home_url() ) !== false ) {
                    $internal_links++;
                } else {
                    $external_links++;
                }
            }
        }
        // メタディスクリプションの有無（excerpt または Yoast SEO 等）
        $meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
        if ( empty( $meta_desc ) ) {
            // excerpt で代用
            $meta_desc = $post->post_excerpt;
        }
        $has_meta_desc = ! empty( trim( $meta_desc ) );
        // スコア計算
        $score = 0;
        if ( $word_count >= 600 ) $score++;
        if ( $heading_count >= 2 ) $score++;
        if ( $internal_links >= 1 ) $score++;
        if ( $has_meta_desc ) $score++;
        return array(
            'word_count' => $word_count,
            'headings'   => $heading_count,
            'internal_links' => $internal_links,
            'external_links' => $external_links,
            'has_meta_desc'  => $has_meta_desc,
            'score' => $score,
        );
    }

    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        // 直近20件の投稿を取得
        $posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
        echo '<div class="wrap">';
        echo '<h1>SEO スコア</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        echo '<p>公開済み記事の基本的な SEO 指標を表示します。文字数、見出し数、内部リンク数、メタディスクリプションの有無に基づき、最大 4 点のスコアを付与しています。</p>';
        if ( empty( $posts ) ) {
            echo '<p>表示する投稿がありません。</p>';
        } else {
            echo '<table class="widefat fixed"><thead><tr>';
            echo '<th>タイトル</th><th>文字数</th><th>見出し数</th><th>内部リンク数</th><th>外部リンク数</th><th>メタディスクリプション</th><th>スコア</th></tr></thead><tbody>';
            foreach ( $posts as $post ) {
                $analysis = self::analyze_post( $post );
                echo '<tr>';
                echo '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></td>';
                echo '<td>' . esc_html( $analysis['word_count'] ) . '</td>';
                echo '<td>' . esc_html( $analysis['headings'] ) . '</td>';
                echo '<td>' . esc_html( $analysis['internal_links'] ) . '</td>';
                echo '<td>' . esc_html( $analysis['external_links'] ) . '</td>';
                echo '<td>' . ( $analysis['has_meta_desc'] ? 'あり' : 'なし' ) . '</td>';
                echo '<td>' . esc_html( $analysis['score'] ) . '/4</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}