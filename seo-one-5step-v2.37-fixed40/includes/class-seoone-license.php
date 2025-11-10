<?php
/**
 * ライセンスおよびアップデート管理
 *
 * ユーザーがライセンスキーを入力・保存できる設定ページを提供し、
 * 将来的な自動更新機構との連携を想定したクラスです。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_License {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * 設定ページをメニューに追加
     */
    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'ライセンス/アップデート',
            'ライセンス/アップデート',
            SEOONE_CAP,
            'seoone-license',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * 設定値登録
     */
    public static function register_settings() {
        register_setting( 'seoone_license_group', 'seoone_license_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
    }

    /**
     * 設定ページ描画
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $license = get_option( 'seoone_license_key', '' );
        echo '<div class="wrap">';
        echo '<h1>ライセンス / アップデート</h1>';
        echo '<p>今後、このプラグインを月額制で提供する際にライセンスキーを管理するためのページです。現在はプレースホルダーとして機能します。</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
        settings_fields( 'seoone_license_group' );
        do_settings_sections( 'seoone-license' );
        echo '<table class="form-table">';
        echo '<tr><th scope="row">ライセンスキー</th><td><input type="text" name="seoone_license_key" value="' . esc_attr( $license ) . '" class="regular-text" placeholder="例: ABCD-1234-EFGH-5678"></td></tr>';
        echo '<tr><th scope="row">現在のバージョン</th><td>' . esc_html( SEOONE_VERSION ) . '</td></tr>';
        echo '</table>';
        submit_button( 'ライセンスを保存' );
        echo '</form>';
        echo '</div>';
    }
}