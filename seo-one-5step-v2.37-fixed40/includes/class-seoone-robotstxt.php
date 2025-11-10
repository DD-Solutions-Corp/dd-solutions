<?php
/**
 * robots.txt 編集ページ
 *
 * サイトルートに設置されている robots.txt ファイルの内容を管理画面から編集・保存できるようにします。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_RobotsTxt {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_seoone_update_robotstxt', array( __CLASS__, 'handle_update' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'robots.txt 編集',
            'robots.txt 編集',
            SEOONE_CAP,
            'seoone-robotstxt',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * ページ描画
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $path = ABSPATH . 'robots.txt';
        $content = '';
        if ( file_exists( $path ) ) {
            $content = file_get_contents( $path );
        }
        echo '<div class="wrap">';
        echo '<h1>robots.txt 編集</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        if ( isset( $_GET['seoone_message'] ) && $_GET['seoone_message'] === 'updated' ) {
            echo '<div class="notice notice-success"><p>robots.txt を更新しました。</p></div>';
        }
        echo '<p>検索エンジンのクローラーへのアクセス制御を行う robots.txt を編集できます。編集後は「保存」をクリックしてください。</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'seoone_update_robotstxt' );
        echo '<input type="hidden" name="action" value="seoone_update_robotstxt">';
        echo '<textarea name="robots_content" rows="15" style="width:100%;">' . esc_textarea( $content ) . '</textarea>';
        submit_button( '保存', 'primary' );
        echo '</form>';
        echo '</div>';
    }

    /**
     * 保存処理
     */
    public static function handle_update() {
        if ( ! current_user_can( SEOONE_CAP ) ) wp_die( '権限がありません' );
        check_admin_referer( 'seoone_update_robotstxt' );
        $content = isset( $_POST['robots_content'] ) ? wp_unslash( $_POST['robots_content'] ) : '';
        $path = ABSPATH . 'robots.txt';
        // 保存
        $result = file_put_contents( $path, $content );
        if ( $result === false ) {
            wp_safe_redirect( admin_url( 'admin.php?page=seoone-robotstxt&seoone_message=error' ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=seoone-robotstxt&seoone_message=updated' ) );
        }
        exit;
    }
}