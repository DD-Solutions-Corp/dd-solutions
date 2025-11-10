<?php
/**
 * SEO ONE Hub
 *
 * 複数の機能ページを1つのタブ付きページにまとめ、ユーザビリティを向上させます。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Hub {
    /**
     * 初期化
     */
    public static function init() {
        // メニューの登録は SeoOne_Admin::register_menu() 内で行われます。
        // ここでは何もしません。
    }

    /**
     * メニューを登録
     */
    public function register_menu() {
        add_submenu_page(
            'seoone',
            'プラグインハブ',
            'プラグインハブ',
            SEOONE_CAP,
            'seoone-hub',
            [ $this, 'render_page' ]
        );
    }

    /**
     * 静的コンテキストからページを表示するためのラッパー。
     * add_menu_page にコールバックとして渡す際に使用します。
     */
    public static function render_page_static() {
        $instance = new self();
        $instance->render_page();
    }
    /**
     * ページを表示
     */
    public function render_page() {
        if ( ! current_user_can( SEOONE_CAP ) ) return;
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        // タブ定義: ダッシュボード、記事生成、メトリクス、設定、ツール。
        // 競合分析は記事生成に統合されたため専用タブは表示しません。
        $tabs = array(
            'dashboard' => 'ダッシュボード',
            'generate'  => '記事生成',
            'metrics'   => 'メトリクス',
            'settings'  => '設定',
            'tools'     => 'ツール',
        );
        echo '<div class="wrap">';
        echo '<h1>SEO ONE プラグインハブ</h1>';
        // タブナビゲーション
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $key => $label ) {
            $active = ( $tab === $key ) ? ' nav-tab-active' : '';
            $url = admin_url( 'admin.php?page=seoone&tab=' . $key );
            echo '<a class="nav-tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</h2>';

        // タブ内容
        echo '<div class="seoone-tab-content">';
        // タブごとの導入説明を表示します。初めて使う人でも迷わないように簡潔に機能を案内します。
        switch ( $tab ) {
            case 'dashboard':
                // ダッシュボード: 最新メトリクスの概要を表示します。
                echo '<p>ダッシュボードでは、最新メトリクスの概要や主要機能への概要が表示されます。</p>';
                if ( class_exists( 'SeoOne_Dashboard' ) && method_exists( 'SeoOne_Dashboard', 'render_page' ) ) {
                    SeoOne_Dashboard::render_page();
                }
                break;
            case 'metrics':
                // メトリクスタブ
                echo '<p>メトリクスでは、Google Search Console や GA4 から取得した指標を一覧表示します。</p>';
                if ( class_exists( 'SeoOne_Metrics' ) && method_exists( 'SeoOne_Metrics', 'render_metrics_page' ) ) {
                    SeoOne_Metrics::render_metrics_page();
                }
                break;
            case 'settings':
                // 設定タブ
                echo '<p>設定ページでは、AI APIキーやモデル、メトリクス連携、ペルソナなどプラグイン全体の動作をカスタマイズできます。各項目の説明を読んで適切に設定してください。</p>';
                SeoOne_Admin::render_settings_page();
                break;
            case 'tools':
                // ツールタブでは、ハブで隠れている各種ツール機能へのリンクを一覧表示します。
                echo '<p>その他のSEO支援ツールをご利用いただけます。以下のリンクをクリックすると各ツールのページへ移動します。</p>';
                echo '<ul style="list-style: disc; padding-left:20px;">';
                // キーワード生成
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-keywords' ) ) . '">キーワード生成</a> – 指定したジャンルやキーワードから関連キーワードを生成します。</li>';
                // トレンドギャップ分析
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-trendgap' ) ) . '">トレンドギャップ分析</a> – 流行のトピックと既存コンテンツのギャップを分析します。</li>';
                // Altテキスト補完
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-alt' ) ) . '">Altテキスト補完</a> – 画像に適切なAltテキストを自動生成します。</li>';
                // 内部リンクチェック
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-internalchecker' ) ) . '">内部リンクチェック</a> – サイト内のリンク切れや内部リンク不足をチェックします。</li>';
                // メタディスクリプション管理
                // 修正: 正しいページスラッグに変更
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-meta-desc' ) ) . '">メタディスクリプション管理</a> – 記事ごとのメタディスクリプションを管理・生成します。</li>';
                // SEOスコア
                // 修正: 正しいページスラッグに変更
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-seo-score' ) ) . '">SEOスコア</a> – 記事のSEOスコアを採点し改善点を提案します。</li>';
                // robots.txt 編集
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-robotstxt' ) ) . '">robots.txt 編集</a> – robots.txt ファイルを編集します。</li>';
                // リンク提案
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-link-suggestions' ) ) . '">リンク提案</a> – 関連記事へのリンク候補を提案します。</li>';
                // 自動内部リンク挿入
                // 修正: 正しいページスラッグに変更
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=seoone-link-auto' ) ) . '">内部リンク自動化</a> – 投稿内の語句に自動的に内部リンクを挿入します。</li>';
                echo '</ul>';
                break;
            case 'generate':
            default:
                // 記事生成タブ
                echo '<p>こちらではAIにテーマやキーワードを指定してブログ記事を生成します。必要な項目を入力して「AIで記事をつくる」ボタンを押してください。</p>';
                SeoOne_Admin::render_generate_page();
                break;
        }
        echo '</div>';
        echo '</div>';
    }
}