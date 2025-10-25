<?php
if (!defined('ABSPATH')) exit;

// カスタム投稿タイプの登録
add_action('init', function() {
    // 家電トピックス
    register_post_type('appliance_topics', [
        'labels' => [
            'name' => '家電トピックス',
            'singular_name' => '家電トピック',
            'add_new' => '新規追加',
            'add_new_item' => '新しい家電トピックを追加',
            'edit_item' => '家電トピックを編集',
            'view_item' => '家電トピックを表示',
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-admin-home',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'rewrite' => ['slug' => 'appliance-topics'],
    ]);
    
    // スタッフブログ
    register_post_type('staff_blog', [
        'labels' => [
            'name' => 'スタッフブログ',
            'singular_name' => 'スタッフブログ',
            'add_new' => '新規追加',
            'add_new_item' => '新しいブログ記事を追加',
            'edit_item' => 'ブログ記事を編集',
            'view_item' => 'ブログ記事を表示',
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-admin-users',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author'],
        'rewrite' => ['slug' => 'staff-blog'],
    ]);
});
