<?php
if (!defined('ABSPATH')) exit;

// お問い合わせフォーム送信処理
add_action('wp_ajax_dd_contact_submit', 'dd_handle_contact_form');
add_action('wp_ajax_nopriv_dd_contact_submit', 'dd_handle_contact_form');

function dd_handle_contact_form() {
    check_ajax_referer('dd_contact_nonce', 'nonce');
    
    $name = sanitize_text_field($_POST['name'] ?? '');
    $kana = sanitize_text_field($_POST['kana'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $email_confirm = sanitize_email($_POST['email_confirm'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $fax = sanitize_text_field($_POST['fax'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $inquiry_type = isset($_POST['inquiry_type']) ? implode(', ', array_map('sanitize_text_field', $_POST['inquiry_type'])) : '';
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    // バリデーション
    if (empty($name) || empty($kana) || empty($email) || empty($phone) || empty($message)) {
        wp_send_json_error(['message' => '必須項目を入力してください。']);
    }
    
    if ($email !== $email_confirm) {
        wp_send_json_error(['message' => 'メールアドレスが一致しません。']);
    }
    
    // メール送信
    $to = get_theme_mod('contact_email', get_option('admin_email'));
    $subject = '【お問い合わせ】' . $name . '様より';
    $body = "お問い合わせ種別: {$inquiry_type}\n";
    $body .= "会社名: {$company}\n";
    $body .= "お名前: {$name}\n";
    $body .= "フリガナ: {$kana}\n";
    $body .= "メール: {$email}\n";
    $body .= "電話: {$phone}\n";
    $body .= "FAX: {$fax}\n\n";
    $body .= "お問い合わせ内容:\n{$message}";
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    
    if (wp_mail($to, $subject, $body, $headers)) {
        wp_send_json_success(['message' => get_theme_mod('contact_success_msg', 'お問い合わせありがとうございます。担当者より折り返しご連絡いたします。')]);
    } else {
        wp_send_json_error(['message' => '送信に失敗しました。お手数ですが、お電話にてお問い合わせください。']);
    }
}

// 採用フォーム送信処理
add_action('wp_ajax_dd_career_submit', 'dd_handle_career_form');
add_action('wp_ajax_nopriv_dd_career_submit', 'dd_handle_career_form');

function dd_handle_career_form() {
    check_ajax_referer('dd_career_nonce', 'nonce');
    
    $position = sanitize_text_field($_POST['position'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $kana = sanitize_text_field($_POST['kana'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    
    if (empty($position) || empty($name) || empty($kana) || empty($email) || empty($phone) || empty($message)) {
        wp_send_json_error(['message' => '必須項目を入力してください。']);
    }
    
    $to = get_theme_mod('contact_email', get_option('admin_email'));
    $subject = '【採用応募】' . $name . '様より（' . $position . '）';
    $body = "応募職種: {$position}\n";
    $body .= "お名前: {$name}\n";
    $body .= "フリガナ: {$kana}\n";
    $body .= "メール: {$email}\n";
    $body .= "電話: {$phone}\n\n";
    $body .= "志望動機・自己PR:\n{$message}";
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    
    if (wp_mail($to, $subject, $body, $headers)) {
        wp_send_json_success(['message' => 'ご応募ありがとうございます。担当者より3営業日以内にご連絡いたします。']);
    } else {
        wp_send_json_error(['message' => '送信に失敗しました。お手数ですが、お電話にてお問い合わせください。']);
    }
}
