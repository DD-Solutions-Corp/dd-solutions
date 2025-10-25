<?php
if (!defined('ABSPATH')) exit;

// ヒーロースライダー設定（WordPress画像選択）
add_action('customize_register', function($wp_customize) {
    $wp_customize->add_section('dd_hero_slider', [
        'title' => 'ヒーロースライダー設定',
        'priority' => 30,
    ]);
    
    // スライド1
    $wp_customize->add_setting('hero_slide_1_pc', ['default' => '']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_slide_1_pc', [
        'label' => 'スライド1（PC用）',
        'section' => 'dd_hero_slider',
        'mime_type' => 'image',
    ]));
    
    $wp_customize->add_setting('hero_slide_1_sp', ['default' => '']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_slide_1_sp', [
        'label' => 'スライド1（スマートフォン用）',
        'section' => 'dd_hero_slider',
        'mime_type' => 'image',
    ]));
    
    // スライド2
    $wp_customize->add_setting('hero_slide_2_pc', ['default' => '']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_slide_2_pc', [
        'label' => 'スライド2（PC用）',
        'section' => 'dd_hero_slider',
        'mime_type' => 'image',
    ]));
    
    $wp_customize->add_setting('hero_slide_2_sp', ['default' => '']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_slide_2_sp', [
        'label' => 'スライド2（スマートフォン用）',
        'section' => 'dd_hero_slider',
        'mime_type' => 'image',
    ]));
    
    // スライド3
    $wp_customize->add_setting('hero_slide_3_pc', ['default' => '']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_slide_3_pc', [
        'label' => 'スライド3（PC用）',
        'section' => 'dd_hero_slider',
        'mime_type' => 'image',
    ]));
    
    $wp_customize->add_setting('hero_slide_3_sp', ['default' => '']);
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_slide_3_sp', [
        'label' => 'スライド3（スマートフォン用）',
        'section' => 'dd_hero_slider',
        'mime_type' => 'image',
    ]));
});
