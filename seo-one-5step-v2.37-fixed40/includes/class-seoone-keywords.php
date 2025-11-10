<?php
/**
 * キーワードから記事生成ページ
 *
 * Search Console から取得した改善候補キーワードを一覧表示し、
 * ワンクリックで記事を生成できるようにする管理画面ページです。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Keywords {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'seoone',
            'キーワード生成',
            'キーワード生成',
            SEOONE_CAP,
            'seoone-keywords',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        // get improvement queries from top queries
        $top_queries = get_option( 'seoone_metrics_top_queries', array() );
        $improvement = array();
        foreach ( $top_queries as $row ) {
            $ctr_pct = isset( $row['ctr'] ) ? (float)$row['ctr'] * 100 : 0;
            $pos = isset( $row['position'] ) ? (float)$row['position'] : 0;
            if ( $ctr_pct > 5 && $pos > 10 ) {
                $improvement[] = $row;
            }
        }
        echo '<div class="wrap">';
        echo '<h1>キーワードから記事生成</h1>';
        // ツール一覧への戻りリンク
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
        if ( empty( $improvement ) ) {
            echo '<p>現在、改善が見込めるキーワードはありません。Search Console のデータ取得後に自動で提案されます。</p>';
        } else {
            echo '<p>以下のキーワードは CTR が高く平均順位が低いため、記事化することでさらなる集客が期待できます。生成ボタンをクリックすると、AI による記事生成が行われます。</p>';
            echo '<table class="widefat"><thead><tr><th>キーワード</th><th>CTR (%)</th><th>平均順位</th><th>記事生成</th></tr></thead><tbody>';
            foreach ( $improvement as $row ) {
                $ctr_pct = round( (float)$row['ctr'] * 100, 2 );
                $pos = round( (float)$row['position'], 2 );
                echo '<tr>';
                echo '<td>' . esc_html( $row['query'] ) . '</td>';
                echo '<td>' . esc_html( $ctr_pct ) . '%</td>';
                echo '<td>' . esc_html( $pos ) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'seoone_generate_article' );
                echo '<input type="hidden" name="action" value="seoone_generate_article">';
                echo '<input type="hidden" name="seoone_topic" value="' . esc_attr( $row['query'] ) . '">';
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