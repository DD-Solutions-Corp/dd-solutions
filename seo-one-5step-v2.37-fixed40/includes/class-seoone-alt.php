<?php
/**
 * Altテキスト補完ツール
 *
 * Altテキストが設定されていない画像に対し、ファイル名から自動生成した候補を表示し、ワンクリックで設定できます。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Alt {
    /**
     * 初期化
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_seoone_set_alt', array( __CLASS__, 'handle_set_alt' ) );
    }

    /**
     * メニュー登録
     */
    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'Altテキスト補完',
            'Altテキスト補完',
            SEOONE_CAP,
            'seoone-alt',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * ページ描画
     */
    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap">';
        echo '<h1>Altテキスト補完</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        // 最新20件の画像添付ファイルを取得
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 20,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ) );
        if ( empty( $attachments ) ) {
            echo '<p>画像が見つかりませんでした。</p>';
        } else {
            echo '<p>Altテキストが設定されていない画像に自動生成した候補を表示します。設定ボタンをクリックすると Alt 属性が保存されます。</p>';
            echo '<table class="widefat"><thead><tr><th>ID</th><th>ファイル名</th><th>現在の Alt</th><th>提案 Alt</th><th>アクション</th></tr></thead><tbody>';
            foreach ( $attachments as $att ) {
                $current_alt = get_post_meta( $att->ID, '_wp_attachment_image_alt', true );
                // 提案Alt: 現在のaltが無い場合のみ生成
                $proposed_alt = '';
                if ( empty( $current_alt ) ) {
                    // post_title があればそれを使用、無い場合はファイル名から拡張子を除外して変換
                    if ( $att->post_title ) {
                        $proposed_alt = $att->post_title;
                    } else {
                        $filename = basename( get_attached_file( $att->ID ) );
                        $name = preg_replace( '/\.[^.]+$/', '', $filename );
                        $proposed_alt = str_replace( array( '-', '_' ), ' ', $name );
                    }
                    // 日本語変換のためにハイフンとアンダースコアをスペースに置換
                }
                echo '<tr>';
                echo '<td>' . esc_html( $att->ID ) . '</td>';
                echo '<td>' . esc_html( basename( get_attached_file( $att->ID ) ) ) . '</td>';
                echo '<td>' . esc_html( $current_alt ?: '(未設定)' ) . '</td>';
                echo '<td>' . esc_html( $proposed_alt ?: '-' ) . '</td>';
                echo '<td>';
                if ( empty( $current_alt ) && $proposed_alt ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
                    wp_nonce_field( 'seoone_set_alt_' . $att->ID );
                    echo '<input type="hidden" name="action" value="seoone_set_alt">';
                    echo '<input type="hidden" name="attachment_id" value="' . esc_attr( $att->ID ) . '">';
                    echo '<input type="hidden" name="proposed_alt" value="' . esc_attr( $proposed_alt ) . '">';
                    submit_button( '設定', 'small', 'submit', false );
                    echo '</form>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    /**
     * Alt テキスト保存ハンドラ
     */
    public static function handle_set_alt() {
        if ( ! current_user_can( SEOONE_CAP ) ) {
            wp_die( '権限がありません。' );
        }
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        $proposed_alt  = isset( $_POST['proposed_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['proposed_alt'] ) ) : '';
        // nonce チェック
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'seoone_set_alt_' . $attachment_id ) ) {
            wp_die( 'セキュリティチェックに失敗しました。' );
        }
        if ( $attachment_id && $proposed_alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $proposed_alt );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=seoone-alt' ) );
        exit;
    }
}