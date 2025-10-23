



<?php
/**
 * Enqueue scripts and styles for DD Solutions Theme.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue scripts and styles.
 */
function dd_solutions_enqueue_assets()
{
    // 共通CSS
    wp_enqueue_style('dd-solutions-common', get_template_directory_uri() . '/assets/css/common.css', array(), '1.0.0');
    
    // 共通JavaScript
    wp_enqueue_script('dd-solutions-common-js', get_template_directory_uri() . '/assets/js/common.js', array('jquery'), '1.0.0', true);
    
    // Font Awesome
    wp_enqueue_style('font-awesome', 'https://use.fontawesome.com/releases/v6.7.2/css/all.css', array(), '6.7.2');
    wp_enqueue_script('font-awesome-kit', 'https://kit.fontawesome.com/4ca21661a0.js', array(), '6.7.2', false);
    
    // ページごとの条件分岐
    if (is_front_page() || is_home()) {
        // トップページ用CSS
        wp_enqueue_style('dd-solutions-home', get_template_directory_uri() . '/assets/css/home.css', array('dd-solutions-common'), '1.0.0');
        
        // トップページ用JavaScript
        wp_enqueue_script('dd-solutions-home-js', get_template_directory_uri() . '/assets/js/home.js', array('dd-solutions-common-js'), '1.0.0', true);
    }
    
    // コメント機能が有効な場合
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'dd_solutions_enqueue_assets');




