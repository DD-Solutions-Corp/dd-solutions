<?php
/**
 * DD Solutions - パフォーマンス強化用 追加関数
 * 使い方：本ファイルの内容をテーマの functions.php に追記（または置換）してください。
 * - ページ別CSS/JSの自動読み込み（存在するファイルのみ）
 * - 画像最適化（AVIF/WebP優先の<picture>、lazy/async、LCP画像のfetchpriority=high）
 * - クリティカルCSSのインライン化（存在する場合のみ）
 * - 不要アセットの解除（絵文字など）
 */

// ▼ テーマ基本機能（必要なら有効化）
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');       // <title>をWPに任せる
    add_theme_support('post-thumbnails'); // アイキャッチ
});

// ▼ バージョニング用（更新時刻をバージョンとして付与 → キャッシュ更新）
function dd_theme_ver($rel) {
    $path = get_stylesheet_directory() . $rel;
    return file_exists($path) ? filemtime($path) : false;
}

// ▼ ページスラッグを取得（front pageは 'front' を返す）
function dd_current_slug() {
    if (is_front_page()) return 'front';
    $obj = get_queried_object();
    if (!empty($obj) && !empty($obj->post_name)) {
        return sanitize_title($obj->post_name);
    }
    return '';
}

// ▼ クリティカルCSSがあれば <head> にインライン出力
add_action('wp_head', function () {
    $slug = dd_current_slug();
    $candidates = [];
    // 優先度：ページ別 → テンプレート別（拡張余地） → グローバル
    if ($slug)   $candidates[] = "/assets/css/critical/page-{$slug}.css";
    if (is_front_page()) $candidates[] = "/assets/css/critical/front.css";
    $candidates[] = "/assets/css/critical/global.css";

    foreach ($candidates as $rel) {
        $abs = get_stylesheet_directory() . $rel;
        if (file_exists($abs)) {
            $css = @file_get_contents($abs);
            if ($css) {
                echo "\n<!-- Critical CSS: {$rel} -->\n<style id=\"critical-css\" data-rel=\"{$rel}\">\n" . $css . "\n</style>\n";
                break; // 最初に見つかったものだけ出力
            }
        }
    }
}, 9);

// ▼ フロント側のアセット読み込み（条件付き）
add_action('wp_enqueue_scripts', function () {
    // ---- CSS ----
    $global_css = '/assets/css/dist/global.min.css';
    if ($v = dd_theme_ver($global_css)) {
        wp_enqueue_style('dd-global', get_stylesheet_directory_uri() . $global_css, [], $v);
    }

    // ページ別CSS（/assets/css/pages/page-<slug>.css があれば読み込む）
    $slug = dd_current_slug();
    if ($slug) {
        $page_css_rel = "/assets/css/pages/page-{$slug}.css";
        if ($v = dd_theme_ver($page_css_rel)) {
            wp_enqueue_style("dd-page-{$slug}", get_stylesheet_directory_uri() . $page_css_rel, ['dd-global'], $v);
        }
    }

    // ---- JS ----
    // ヒーローの軽量スライダー（必要時のみ初期化。DOM側で存在検出するので常時読み込みでOK）
    $hero_js = '/assets/js/hero-rotator.js';
    if ($v = dd_theme_ver($hero_js)) {
        wp_enqueue_script('dd-hero-rotator', get_stylesheet_directory_uri() . $hero_js, [], $v, true);
    }

    // ページ別JS（/assets/js/pages/page-<slug>.js があれば読み込む）
    if ($slug) {
        $page_js_rel = "/assets/js/pages/page-{$slug}.js";
        if ($v = dd_theme_ver($page_js_rel)) {
            wp_enqueue_script("dd-page-{$slug}", get_stylesheet_directory_uri() . $page_js_rel, [], $v, true);
        }
    }

    // 可能ならGutenbergのフロントCSS等を外す（ブロックテーマでなければ検討）
    // wp_dequeue_style('wp-block-library');
}, 20);

// ▼ 絵文字スクリプト・スタイルの解除（HTTPリクエスト削減）
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
});

// ▼ <img>タグの既定属性：lazy/async を付与（既に指定があれば温存）
add_filter('wp_content_image_tag', function ($html) {
    if (strpos($html, 'loading=') === false) {
        $html = str_replace('<img', '<img loading="lazy"', $html);
    }
    if (strpos($html, 'decoding=') === false) {
        $html = str_replace('<img', '<img decoding="async"', $html);
    }
    return $html;
}, 10, 1);

// ▼ LCP画像を簡単に出すヘルパー（fetchpriority=high でLCP対策）
function dd_lcp_image($attachment_id, $size = 'full', $attr = []) {
    $attr = array_merge([
        'fetchpriority' => 'high',
        'decoding'      => 'async',
    ], $attr);
    echo wp_get_attachment_image($attachment_id, $size, false, $attr);
}

// ▼ <picture>の簡易出力（AVIF/WebP優先、なければJPG/PNG）
//    $basename_without_ext は assets/images/ からの相対パスで拡張子なし（例：'hero/kv-01'）
function dd_picture_sources($basename_without_ext, $alt = '', $class = '') {
    $base_uri  = get_stylesheet_directory_uri() . '/assets/images/';
    $base_path = get_stylesheet_directory()     . '/assets/images/';

    $candidates = [
        ['ext' => 'avif', 'type' => 'image/avif'],
        ['ext' => 'webp', 'type' => 'image/webp'],
    ];

    $fallbacks = ['jpg', 'jpeg', 'png', 'gif'];
    $fallback_src = '';

    foreach ($fallbacks as $ext) {
        if (file_exists($base_path . $basename_without_ext . '.' . $ext)) {
            $fallback_src = $base_uri . $basename_without_ext . '.' . $ext;
            break;
        }
    }

    // 出力開始
    echo '<picture class="' . esc_attr($class) . '">';
    foreach ($candidates as $c) {
        $p = $base_path . $basename_without_ext . '.' . $c['ext'];
        if (file_exists($p)) {
            $u = $base_uri  . $basename_without_ext . '.' . $c['ext'];
            echo '<source type="' . esc_attr($c['type']) . '" srcset="' . esc_url($u) . '">';
        }
    }
    if ($fallback_src) {
        echo '<img src="' . esc_url($fallback_src) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async">';
    } else {
        // 画像が見つからない場合のフォールバック（意図的に何も出さない）
    }
    echo '</picture>';
}

// ▼ AVIF/WebP のアップロード許可（WP6.5+ならAVIFコア対応。環境依存でGD/Imagick要）
add_filter('mime_types', function ($mimes) {
    $mimes['webp'] = 'image/webp';
    $mimes['avif'] = 'image/avif';
    return $mimes;
});
