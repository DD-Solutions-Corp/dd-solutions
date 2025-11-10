<?php
/**
 * メタディスクリプション管理ページ
 *
 * メタディスクリプションが設定されていない投稿を検出し、
 * コンテンツの冒頭から自動生成した候補を提示して保存できるようにします。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_MetaDesc {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_seoone_update_meta_desc', array( __CLASS__, 'handle_update' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'メタディスクリプション管理',
            'メタディスクリプション管理',
            SEOONE_CAP,
            'seoone-meta-desc',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * ページ描画
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap">';
        echo '<h1>メタディスクリプション管理</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        echo '<p>メタディスクリプションが設定されていない投稿を検出し、本文から自動生成した候補を保存できます。Yoast SEO を導入していない場合は、投稿の抜粋をメタディスクリプションとして使用します。</p>';
        // 投稿一覧取得（最大20件）
        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        $has_items = false;
        echo '<table class="widefat"><thead><tr><th>タイトル</th><th>現状</th><th>候補</th><th>操作</th></tr></thead><tbody>';
        foreach ( $posts as $post ) {
            $meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
            $excerpt   = $post->post_excerpt;
            $has_meta  = ! empty( trim( $meta_desc ) ) || ! empty( trim( $excerpt ) );
            if ( $has_meta ) continue; // メタディスクリプションまたは抜粋がある場合はスキップ
            $has_items = true;
            // 候補生成: 本文の先頭160文字
            $clean = wp_strip_all_tags( $post->post_content );
            $candidate = mb_substr( $clean, 0, 160 );
            // 行表示
            echo '<tr>';
            echo '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></td>';
            echo '<td>未設定</td>';
            echo '<td>' . esc_html( $candidate ) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
            wp_nonce_field( 'seoone_update_meta_desc_' . $post->ID );
            echo '<input type="hidden" name="action" value="seoone_update_meta_desc">';
            echo '<input type="hidden" name="post_id" value="' . intval( $post->ID ) . '">';
            echo '<textarea name="meta_desc" style="width:100%;" rows="2">' . esc_textarea( $candidate ) . '</textarea>';
            submit_button( '保存', 'primary', 'submit', false );
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        if ( ! $has_items ) {
            echo '<tr><td colspan="4">メタディスクリプションが未設定の投稿はありません。</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * メタディスクリプション保存処理
     */
    public static function handle_update() {
        if ( ! current_user_can( SEOONE_CAP ) ) wp_die( '権限がありません' );
        $post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $candidate = isset( $_POST['meta_desc'] ) ? wp_unslash( $_POST['meta_desc'] ) : '';
        // ノンスチェック
        check_admin_referer( 'seoone_update_meta_desc_' . $post_id );
        if ( ! $post_id || empty( $candidate ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seoone-meta-desc&seoone_message=error' ) );
            exit;
        }
        // 保存：Yoast のフィールドがあればそこへ、なければ抜粋へ
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $candidate ) );
        // 抜粋も空の場合は更新
        $post = get_post( $post_id );
        if ( empty( $post->post_excerpt ) ) {
            wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => sanitize_text_field( $candidate ) ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=seoone-meta-desc&seoone_message=updated' ) );
        exit;
    }
}