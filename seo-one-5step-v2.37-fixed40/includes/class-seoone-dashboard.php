<?php
/**
 * SEO ONE ダッシュボード
 *
 * 主要機能へのクイックリンクと最新メトリクスの概要を表示するページ。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Dashboard {
    public static function init() {
        // Hub のダッシュボードタブに統合するため、管理画面メニューへの登録を行いません。
        // add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu() {
        // ダッシュボードを親メニューの先頭に配置
        add_submenu_page(
            'seoone',
            'SEO ONE ダッシュボード',
            'ダッシュボード',
            SEOONE_CAP,
            'seoone-dashboard',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        echo '<div class="wrap seoone-dashboard">';
        echo '<h1>SEO ONE ダッシュボード</h1>';
        // 最新メトリクスの概要
        $history = get_option( 'seoone_metrics_history', array() );
        $latest  = ! empty( $history ) ? end( $history ) : null;
        echo '<h2>最新メトリクス概要</h2>';
        if ( $latest ) {
            echo '<div class="seoone-metric-cards" style="display:flex; gap:1rem; flex-wrap:wrap;">';
            $metrics = array(
                'クリック数'   => $latest['clicks'] ?? '-',
                '表示回数'     => $latest['impressions'] ?? '-',
                'CTR'         => isset( $latest['ctr'] ) ? round( $latest['ctr'] * 100, 2 ) . '%' : '-',
                '平均順位'     => $latest['position'] ?? '-',
                'セッション'   => $latest['sessions'] ?? '-',
                'アクティブユーザー' => $latest['active_users'] ?? '-',
                'コンバージョン' => $latest['conversions'] ?? '-',
            );
            foreach ( $metrics as $label => $value ) {
                echo '<div class="seoone-card" style="flex:1; min-width:130px; background:#fff; border:1px solid #ccd0d4; padding:1rem; border-radius:4px;">';
                echo '<h3 style="margin-top:0;">' . esc_html( $label ) . '</h3>';
                echo '<p style="font-size:1.5em; margin:0;">' . esc_html( $value ) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>まだメトリクスが取得されていません。<a href="' . esc_url( admin_url( 'admin.php?page=seoone-metrics' ) ) . '">メトリクスページ</a>からデータを取得してください。</p>';
        }
        // クイックアクセス機能はハブに統合したため、ここでは表示しません。
        echo '</div>';
    }
}