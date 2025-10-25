<?php
if (!defined('ABSPATH')) exit;

// 採用情報設定
add_action('customize_register', function($wp_customize) {
    $wp_customize->add_section('dd_recruit_settings', [
        'title' => '採用情報設定',
        'priority' => 50,
    ]);
    
    $wp_customize->add_setting('recruit_position', ['default' => '家電修理スタッフ / WEB開発エンジニア']);
    $wp_customize->add_control('recruit_position', [
        'label' => '募集職種',
        'section' => 'dd_recruit_settings',
        'type' => 'textarea'
    ]);
    
    $wp_customize->add_setting('recruit_location', ['default' => '東京都渋谷区渋谷2丁目19番15号']);
    $wp_customize->add_control('recruit_location', [
        'label' => '勤務地',
        'section' => 'dd_recruit_settings',
        'type' => 'text'
    ]);
    
    $wp_customize->add_setting('recruit_salary', ['default' => '経験・能力に応じて優遇']);
    $wp_customize->add_control('recruit_salary', [
        'label' => '給与',
        'section' => 'dd_recruit_settings',
        'type' => 'text'
    ]);
    
    $wp_customize->add_setting('recruit_method', ['default' => '下記フォームよりご応募ください。']);
    $wp_customize->add_control('recruit_method', [
        'label' => '応募方法',
        'section' => 'dd_recruit_settings',
        'type' => 'textarea'
    ]);
});
