<?php
if (!defined('ABSPATH')) exit;

// お問い合わせ設定
add_action('customize_register', function($wp_customize) {
    $wp_customize->add_section('dd_contact_settings', [
        'title' => 'お問い合わせ設定',
        'priority' => 45,
    ]);
    
    $wp_customize->add_setting('contact_email', ['default' => get_option('admin_email')]);
    $wp_customize->add_control('contact_email', [
        'label' => '送信先メールアドレス',
        'section' => 'dd_contact_settings',
        'type' => 'email'
    ]);
    
    $wp_customize->add_setting('contact_success_msg', ['default' => 'お問い合わせありがとうございます。']);
    $wp_customize->add_control('contact_success_msg', [
        'label' => '送信完了メッセージ',
        'section' => 'dd_contact_settings',
        'type' => 'textarea'
    ]);
    
    $wp_customize->add_setting('recaptcha_site_key', ['default' => '']);
    $wp_customize->add_control('recaptcha_site_key', [
        'label' => 'reCAPTCHA v3 サイトキー',
        'section' => 'dd_contact_settings',
        'type' => 'text'
    ]);
    
    $wp_customize->add_setting('recaptcha_secret_key', ['default' => '']);
    $wp_customize->add_control('recaptcha_secret_key', [
        'label' => 'reCAPTCHA v3 シークレットキー',
        'section' => 'dd_contact_settings',
        'type' => 'text'
    ]);
});
