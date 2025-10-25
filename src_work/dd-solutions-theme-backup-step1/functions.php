<?php
/**
 * DD Solutions Theme functions
 * 旧サイトのCSS/JS構造を完全に再現
 */

if (!defined('ABSPATH')) {
    exit;
}

// テーマサポート
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    
    register_nav_menus([
        'primary' => 'Primary Menu',
        'footer'  => 'Footer Menu',
    ]);
});

// CSS/JS読み込み（旧サイトと同じ順序で）
add_action('wp_enqueue_scripts', function () {
    $theme_uri = get_template_directory_uri();
    
    // CSS（旧サイトと同じ順序）
    wp_enqueue_style('dd-reset', $theme_uri . '/assets/css/reset.css', [], '1.0.0');
    wp_enqueue_style('dd-main', $theme_uri . '/assets/css/main.css', ['dd-reset'], '1.0.0');
    wp_enqueue_style('dd-animate', $theme_uri . '/assets/css/animate.css', ['dd-main'], '1.0.0');
    wp_enqueue_style('font-awesome', 'https://use.fontawesome.com/releases/v6.7.2/css/all.css', ['dd-animate'], '6.7.2');
    wp_enqueue_style('dd-lightbox', $theme_uri . '/assets/css/lightbox.css', ['font-awesome'], '1.0.0');
    wp_enqueue_style('dd-scroll-hint', $theme_uri . '/assets/css/scroll-hint.css', ['dd-lightbox'], '1.0.0');
    wp_enqueue_style('dd-add', $theme_uri . '/assets/css/add.css', ['dd-scroll-hint'], '1.0.0');
    
    // JavaScript（旧サイトと同じ順序）
    // Font Awesome Kit
    wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/4ca21661a0.js', [], '6.7.2', false);
    
    // jQuery（CDN版）
    wp_deregister_script('jquery');
    wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js', [], '2.2.4', false);
    wp_enqueue_script('jquery');
    
    // その他のJS（旧サイトと同じ順序）
    wp_enqueue_script('dd-hamburger', $theme_uri . '/assets/js/hamburger.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-match-height', $theme_uri . '/assets/js/jquery.matchHeight.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-js-match-height', $theme_uri . '/assets/js/js-matchHeight.js', ['dd-match-height'], '1.0.0', false);
    wp_enqueue_script('dd-lightbox', $theme_uri . '/assets/js/lightbox.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-topbtn', $theme_uri . '/assets/js/topbtn.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-5-1-1', $theme_uri . '/assets/js/5-1-1.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-scroll-hint', $theme_uri . '/assets/js/scroll-hint.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-accordion', $theme_uri . '/assets/js/accordion.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-wow', $theme_uri . '/assets/js/wow.min.js', ['jquery'], '1.0.0', false);
    wp_enqueue_script('dd-wow-animated', $theme_uri . '/assets/js/wow_animated.js', ['dd-wow'], '1.0.0', false);
    wp_enqueue_script('dd-scroll-class', $theme_uri . '/assets/js/scroll-class.js', ['jquery'], '1.0.0', true);
}, 10);

// カスタマイザー設定は inc/customizer-*.php に移動

// 拡張機能の読み込み（既存デザインを壊さない）
$inc_files = ["custom-post-types","customizer-hero","media-variants","picture-filter","customizer-news","customizer-contact","customizer-recruit","contact-form","performance"];
foreach ($inc_files as $f) {
    $path = get_template_directory()."/inc/{$f}.php";
    if (file_exists($path)) require_once $path;
}
