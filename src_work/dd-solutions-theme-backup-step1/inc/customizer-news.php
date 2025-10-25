<?php
if (!defined('ABSPATH')) exit;

// 新着情報設定（複数投稿タイプ選択可能）
add_action('customize_register', function($wp_customize) {
    $wp_customize->add_section('dd_news_settings', [
        'title' => '新着情報設定',
        'priority' => 35,
    ]);
    
    // 投稿タイプごとにチェックボックスを作成
    $post_types = get_post_types(['public' => true], 'objects');
    
    foreach ($post_types as $pt) {
        if ($pt->name !== 'attachment' && $pt->name !== 'page') {
            $wp_customize->add_setting("news_type_{$pt->name}", ['default' => ($pt->name === 'post' ? 1 : 0)]);
            $wp_customize->add_control("news_type_{$pt->name}", [
                'label' => $pt->label . 'を表示',
                'section' => 'dd_news_settings',
                'type' => 'checkbox',
            ]);
        }
    }
    
    $wp_customize->add_setting('news_post_count', ['default' => 5]);
    $wp_customize->add_control('news_post_count', [
        'label' => '表示件数',
        'section' => 'dd_news_settings',
        'type' => 'number',
        'input_attrs' => ['min'=>1,'max'=>20]
    ]);
});

// 選択された投稿タイプを取得する関数
function dd_get_selected_news_types() {
    $post_types = get_post_types(['public' => true], 'objects');
    $selected = [];
    
    foreach ($post_types as $pt) {
        if ($pt->name !== 'attachment' && $pt->name !== 'page') {
            if (get_theme_mod("news_type_{$pt->name}", ($pt->name === 'post' ? 1 : 0))) {
                $selected[] = $pt->name;
            }
        }
    }
    
    return empty($selected) ? ['post'] : $selected;
}
