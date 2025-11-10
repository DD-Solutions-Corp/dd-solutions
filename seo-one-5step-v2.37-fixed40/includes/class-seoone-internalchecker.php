<?php
/**
 * 内部リンクチェックツール
 *
 * 投稿内の内部リンクを解析し、リンク切れの可能性がある URL をリストアップします。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_InternalChecker {
    /**
     * 初期化
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    /**
     * 管理メニューに追加
     */
    public static function register_menu() {
        add_submenu_page(
            'seoone',
            '内部リンクチェック',
            '内部リンクチェック',
            SEOONE_CAP,
            'seoone-internalchecker',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * ページ描画
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap">';
        echo '<h1>内部リンクチェック</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        // 対象投稿取得: サイトのすべての公開ポストタイプ（投稿・固定ページ・カスタム投稿）を対象とし、公開・予約・下書き・非公開ステータスを含めます。
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        unset( $post_types['attachment'] );
        $posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        if ( empty( $posts ) ) {
            echo '<p>チェック対象の投稿がありません。</p>';
            echo '</div>';
            return;
        }
        echo '<p>サイト内すべての公開ポストタイプのコンテンツを解析し、内部リンクの状態を確認します。リンク切れが見つかった場合は修正してください。</p>';
        echo '<table class="widefat"><thead><tr><th>投稿タイトル</th><th>ポストタイプ</th><th>リンクURL</th><th>ステータス</th><th>編集</th></tr></thead><tbody>';
        foreach ( $posts as $post ) {
            setup_postdata( $post );
            $content = apply_filters( 'the_content', $post->post_content );
            // HTML 解析
            libxml_use_internal_errors( true );
            $doc = new DOMDocument();
            $doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );
            $links = $doc->getElementsByTagName( 'a' );
            $site_url = home_url();
            foreach ( $links as $link ) {
                $href = $link->getAttribute( 'href' );
                if ( ! $href ) continue;
                // 内部リンク判定
                $is_internal = false;
                if ( strpos( $href, 'http' ) === 0 ) {
                    if ( strpos( $href, $site_url ) === 0 ) $is_internal = true;
                } elseif ( strpos( $href, '/' ) === 0 ) {
                    $is_internal = true;
                    $href = trailingslashit( $site_url ) . ltrim( $href, '/' );
                }
                if ( $is_internal ) {
                    // ポストIDを取得
                    $post_id = url_to_postid( $href );
                    $status = $post_id ? '<span style="color:green;">OK</span>' : '<span style="color:red;">リンク切れ</span>';
                    echo '<tr>';
                    echo '<td>' . esc_html( get_the_title( $post ) ) . '</td>';
                    echo '<td>' . esc_html( get_post_type( $post ) ) . '</td>';
                    echo '<td><a href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html( $href ) . '</a></td>';
                    echo '<td>' . $status . '</td>';
                    echo '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '" target="_blank">編集</a></td>';
                    echo '</tr>';
                }
            }
        }
        wp_reset_postdata();
        echo '</tbody></table>';
        echo '</div>';
    }
}