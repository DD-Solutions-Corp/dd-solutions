

<?php
/**
 * DD Solutions Theme functions and definitions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function dd_solutions_theme_setup()
{
    // Add default posts and comments RSS feed links to head.
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title.
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support('post-thumbnails');

    // Enable support for custom logo.
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
        'header-text' => array('site-title', 'site-description'),
    ));

    // Register navigation menus.
    register_nav_menus(array(
        'primary' => __('Main Menu', 'dd-solutions'),
        'footer'  => __('Footer Menu', 'dd-solutions'),
    ));
}
add_action('after_setup_theme', 'dd_solutions_theme_setup');

/**
 * Load required files.
 */
require_once get_template_directory() . '/inc/assets.php';

