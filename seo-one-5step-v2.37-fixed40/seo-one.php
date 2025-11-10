<?php
/**
 * Plugin Name: SEO ONE (5ステップ構成・記事単位モデル指定 対応版)
 * Description: 情報取得→初稿→コーディング/編集→採点→ファクトチェック（＋要約）の5ステップAIパイプラインを記事単位で制御する総合SEO支援プラグイン。画像最適化、内部リンク、構造化データ、公開前検証、トレンド取得、ジャンル指定キーワード生成、トレンドギャップ分析、Altテキスト補完、内部リンクチェック、ダッシュボード、ライセンス管理・ファイル改ざん検知、タイトルとメタディスクリプションのAI提案、記事生成時の多言語対応、既存記事のAI翻訳、自動内部リンク挿入、日次メールレポート、ダークモード対応、文章アシスタント機能、AIコードスニペット生成機能、競合分析機能を備えています。フォームUIを改善し、入力欄やボタンのサイズを統一してユーザビリティを向上。また、初回インストール時に必要な設定を案内するオンボーディングガイドやハブページによるタブ管理を追加し、初めてのユーザーでも迷わず設定できるようにしました。各ステップで利用するAIモデルを個別に指定でき、推奨モデルとコスト目安の説明が表示されます。最新バージョンではペルソナ設定による自然な文章生成、除外キーワード設定、トレンド候補の表示、複数言語生成および自動スケジューリングなどを追加しています。
 * Version: 2.37.0
 * Author: DD Solutions
 * Text Domain: seo-one
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SEOONE_PLUGIN_FILE', __FILE__ );
define( 'SEOONE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOONE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// プラグインのバージョン番号を定数として定義
define( 'SEOONE_VERSION', '2.37.0' );

//=== 権限定義 ===
// SEO ONE の機能は管理者専用とし、他の権限ではアクセス不可とします。
// デフォルトで 'manage_options' を使用し、フィルタ 'seoone_capability' で変更可能です。
if ( ! defined( 'SEOONE_CAP' ) ) {
    define( 'SEOONE_CAP', apply_filters( 'seoone_capability', 'manage_options' ) );
}

// 上記で SEOONE_CAP を 'manage_options' に固定したため、特別な権限読み替えは不要です。
// 以前のバージョンで実装していた map_meta_cap フィルタは削除しました。

// 主要クラス読み込み
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-admin.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-generator.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-images.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-links.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-readtime.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-toc.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-schema.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-validate.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-trends.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-indexing.php';

// キーワード生成ページ
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-keywords.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-link-suggestions.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-seoscore.php';
// require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-linkchecker.php'; // 削除: リンクチェッカー機能を内部リンクチェックに統合
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-metadesc.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-robotstxt.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-alt.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-internalchecker.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-dashboard.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-license.php';
// require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-titlemeta.php'; // 削除: タイトル・メタ提案機能
// 翻訳機能
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-translate.php';
// 内部リンク自動化機能
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-linkauto.php';

// SNS投稿生成機能
// require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-social.php'; // 削除: SNS投稿生成機能

// 文章アシスタント機能
// require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-writingassistant.php'; // 削除: 文章アシスタント機能

// コードスニペット生成機能
// require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-snippet.php'; // 削除: コードスニペット生成機能
// 競合分析機能
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-competitor.php';
// クイズ生成機能
// require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-quiz.php'; // 削除: クイズ生成機能
// Hubページ（タブ付きUI）
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-hub.php';

// ----- サイドメニュー整理 -----
// 多くの機能を1つのタブ付きハブにまとめ、左メニューをすっきりさせるために
// 不要なサブメニューを非表示にします。残したいページ以外はここで削除してください。
add_action( 'admin_menu', function() {
    // 非表示にするサブメニューのスラッグ一覧
    // Hub に統合された機能のサブメニューは表示しないようにします。
    $hide = array(); // ハブから隠すメニューなし
    foreach ( $hide as $slug ) {
        remove_submenu_page( 'seoone', $slug );
    }
}, 999 );
// オンボーディングガイド
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-onboarding.php';

// 新しい機能: トレンドギャップ分析
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-trendgap.php';

// 新機能クラスを読み込み
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-metrics.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-abtest.php';
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-ads.php';

// ★記事ごとの5ステップ設定メタボックス
require_once SEOONE_PLUGIN_DIR . 'includes/class-seoone-metabox.php';

// 初期化
add_action( 'plugins_loaded', function(){
    SeoOne_Admin::init();
    SeoOne_Readtime::init();
    SeoOne_TOC::init();
    SeoOne_Links::init();
    SeoOne_Validate::init();
    SeoOne_MetaBox::init();
    // 新機能の初期化
    SeoOne_Metrics::init();
    SeoOne_ABTest::init();
    SeoOne_Ads::init();
    SeoOne_Keywords::init();
    SeoOne_Link_Suggestions::init();
    SeoOne_SeoScore::init();
    // SeoOne_LinkChecker::init(); // 削除: リンクチェッカー機能統合
    SeoOne_MetaDesc::init();
    SeoOne_RobotsTxt::init();
    SeoOne_Schema::init();
    SeoOne_TrendGap::init();
    SeoOne_Alt::init();
    SeoOne_InternalChecker::init();
    SeoOne_Dashboard::init();
    SeoOne_License::init();
    // SeoOne_TitleMeta::init(); // 削除: タイトル・メタ提案機能
    SeoOne_Translate::init();
    SeoOne_LinkAuto::init();
    // SeoOne_WritingAssistant::init(); // 削除: 文章アシスタント機能
    // SeoOne_Snippet::init(); // 削除: コードスニペット生成機能
    // SeoOne_Competitor::init(); // 競合分析は記事生成に統合されたため独立したメニューを登録しない
    // SeoOne_Quiz::init(); // 削除: クイズ生成機能
    SeoOne_Hub::init();
    SeoOne_Onboarding::init();
    // ソーシャル投稿生成機能は削除
    // SeoOne_Social::init();
});

// 有効化/無効化フック（簡易）
register_activation_hook( __FILE__, function(){
    // プラグインのファイルハッシュを保存して改変検知に利用
    $dir = SEOONE_PLUGIN_DIR;
    $hashes = array(); // ハブから隠すメニューなし
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
    foreach ( $iterator as $file ) {
        /** @var SplFileInfo $file */
        if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
            $relative = str_replace( $dir, '', $file->getPathname() );
            $hashes[ $relative ] = md5_file( $file->getPathname() );
        }
    }
    update_option( 'seoone_file_hashes', $hashes );

    // オンボーディング未完了フラグを設定
    update_option( 'seoone_onboarded', false );
});
register_deactivation_hook( __FILE__, function(){
    // CRON解除など
});

// 正しい uninstall フックを登録します。WordPress では、uninstall フックには静的なクラスメソッドまたは関数名のみを指定する必要があります。
// この関数はプラグインの削除時に呼び出され、設定やデータをクリーンアップします。
register_uninstall_hook( __FILE__, 'seoone_uninstall_plugin' );

/**
 * SEO ONE プラグインのアンインストール処理。
 * 保存していたオプションやメタデータを削除します。
 */
function seoone_uninstall_plugin() {
    /*
     * アンインストール時に設定や履歴を削除しないようにしています。
     * これにより、プラグインを削除→再インストールしてもユーザーの
     * 設定値やメトリクスが保持されます。以前はここで delete_option() を
     * 行っていましたが、ユーザーの利便性を考慮し削除処理を残しません。
     * 必要に応じて手動でオプションを削除する場合は、管理画面から
     * 「設定エクスポート/インポート」機能を利用するか、DBツールで削除してください。
     */
    return;
}

// 管理画面でファイル改ざんを検知して通知
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $stored = get_option( 'seoone_file_hashes', array() );
    if ( empty( $stored ) ) return;
    $dir = SEOONE_PLUGIN_DIR;
    $current_hashes = array(); // ハブから隠すメニューなし
    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
    foreach ( $iterator as $file ) {
        /** @var SplFileInfo $file */
        if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
            $relative = str_replace( $dir, '', $file->getPathname() );
            $current_hashes[ $relative ] = md5_file( $file->getPathname() );
        }
    }
    $modified = array(); // ハブから隠すメニューなし
    foreach ( $stored as $rel => $hash ) {
        if ( ! isset( $current_hashes[ $rel ] ) ) {
            $modified[] = $rel;
        } elseif ( $current_hashes[ $rel ] !== $hash ) {
            $modified[] = $rel;
        }
    }
    if ( ! empty( $modified ) ) {
        echo '<div class="notice notice-warning"><p>SEO ONE: プラグインファイルが改変された可能性があります。変更されたファイル: ' . esc_html( implode( ', ', $modified ) ) . '</p></div>';
    }
});

// --- メニューの非表示設定 ------------------------------------------------------
// ハブに統合したツールをサイドバーから実際に削除してしまうと、
// WordPress から該当ページが存在しないとみなされてアクセスできなくなる場合があります。
// そのため、サブメニューは登録したまま、CSSで非表示にすることでハブのタブからのアクセスを維持します。
add_action( 'admin_head', function() {
    echo '<style>
    /* SEO ONE メニュー内で非表示にするサブメニューのリスト */
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-settings"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-keywords"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-trendgap"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-alt"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-internalchecker"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-metadesc"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-seoscore"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-robotstxt"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-link-suggestions"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-linkauto"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-metrics"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-abtest"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-ads"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-dashboard"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-license"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-translate"],
    #toplevel_page_seoone .wp-submenu a[href$="page=seoone-internalchecker"] {
        display: none !important;
    }
    </style>';
});
