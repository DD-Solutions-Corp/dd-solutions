<?php
/**
 * 多言語翻訳機能：既存投稿をAIで翻訳し、新規投稿として保存
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Translate {
  public static function init(){
    add_action( 'admin_menu', array(__CLASS__, 'register_menu') );
    add_action( 'admin_post_seoone_translate_post', array(__CLASS__, 'handle_translate_post') );
  }

  /**
   * 管理画面にメニューを追加
   */
  public static function register_menu(){
    add_submenu_page('seoone','翻訳/多言語化','翻訳/多言語化',SEOONE_CAP,'seoone-translate', array(__CLASS__,'render_translate_page'));
  }

  /**
   * 翻訳ページを表示
   */
  public static function render_translate_page(){
    echo '<div class="wrap"><h1>翻訳/多言語化</h1>';
    // メッセージ
    if ( isset($_GET['seoone_message']) ) {
      if ( $_GET['seoone_message'] === 'success' ) {
        echo '<div class="notice notice-success"><p>翻訳記事を生成しました（下書き保存）。</p></div>';
      } elseif ( $_GET['seoone_message'] === 'error' ) {
        echo '<div class="notice notice-error"><p>翻訳に失敗しました。API設定や権限をご確認ください。</p></div>';
      }
    }
    echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'">';
    wp_nonce_field('seoone_translate_post');
    echo '<input type="hidden" name="action" value="seoone_translate_post">';
    echo '<table class="form-table">';
    echo '<tr><th>元の記事</th><td><select name="seoone_source_post" required>'; 
    echo '<option value="">選択してください</option>';
    // 最新50件の投稿を取得
    $posts = get_posts( array('numberposts' => 50, 'post_type'=>'post', 'post_status'=>'publish' ) );
    foreach ( $posts as $p ) {
      printf('<option value="%d">%s</option>', $p->ID, esc_html( $p->post_title ));
    }
    echo '</select></td></tr>';
    echo '<tr><th>翻訳先の言語</th><td><select name="seoone_target_language" required>';
    echo '<option value="en">英語</option>';
    echo '<option value="zh">中国語</option>';
    echo '</select></td></tr>';
    echo '</table>';
    submit_button('翻訳して新規作成');
    echo '</form></div>';
  }

  /**
   * 翻訳リクエストの処理
   */
  public static function handle_translate_post(){
    if ( ! current_user_can(SEOONE_CAP) ) wp_die('権限がありません');
    check_admin_referer('seoone_translate_post');
    $post_id = isset($_POST['seoone_source_post']) ? intval( $_POST['seoone_source_post'] ) : 0;
    $target = isset($_POST['seoone_target_language']) ? sanitize_text_field( $_POST['seoone_target_language'] ) : '';
    if ( ! $post_id || ! in_array( $target, array('en','zh'), true ) ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-translate&seoone_message=error') );
      exit;
    }
    $original = get_post( $post_id );
    if ( ! $original || $original->post_type !== 'post' ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-translate&seoone_message=error') );
      exit;
    }
    $content = $original->post_content;
    // 設定読み込み
    $opt = get_option('seoone_settings', array());
    $api_key = $opt['ai_api_key'] ?? '';
    $model   = $opt['ai_model'] ?? 'gpt-4o';
    if ( empty( $api_key ) ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-translate&seoone_message=error') );
      exit;
    }
    // 翻訳プロンプトを言語ごとに準備
    if ( $target === 'en' ) {
      $sys = 'You are a helpful assistant that translates Japanese blog posts into English while preserving structure and style.';
      $prompt = 'Translate the following Japanese blog post into English. Preserve headings (H2/H3), lists, and overall structure. Keep the meaning faithful but make the English natural and readable. Content: ' . $content;
    } else { // zh
      $sys = 'You are a helpful assistant that translates Japanese blog posts into Chinese while preserving structure and style.';
      $prompt = '请将下列日文博客文章翻译成中文。保留标题（H2/H3）、列表及整体结构，忠实传达原意，并使中文自然易读。内容：' . $content;
    }
    $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    $headers  = array(
      'Authorization' => 'Bearer ' . $api_key,
      'Content-Type'  => 'application/json',
    );
    $body = array(
      'model' => $model,
      'messages' => array(
        array('role'=>'system','content'=>$sys),
        array('role'=>'user','content'=>$prompt),
      ),
      'max_tokens' => 4096,
      'temperature' => 0.1
    );
    // APIコール
    $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>180) );
    if ( is_wp_error($resp) || 200 !== wp_remote_retrieve_response_code($resp) ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-translate&seoone_message=error') );
      exit;
    }
    $j = json_decode( wp_remote_retrieve_body($resp), true );
    $translated = $j['choices'][0]['message']['content'] ?? '';
    if ( empty( $translated ) ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-translate&seoone_message=error') );
      exit;
    }
    // 新規投稿作成
    $new_title = $original->post_title;
    if ( $target === 'en' ) {
      $new_title .= ' (English)';
    } else {
      $new_title .= '（中国語）';
    }
    $post_args = array(
      'post_title'   => $new_title,
      'post_content' => $translated,
      'post_status'  => 'draft',
      'post_type'    => 'post',
    );
    $new_id = wp_insert_post( $post_args, true );
    if ( is_wp_error($new_id) ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-translate&seoone_message=error') );
      exit;
    }
    // カテゴリやタグを引き継ぐ
    $taxes = get_object_taxonomies( 'post' );
    foreach ( $taxes as $tax ) {
      $terms = wp_get_object_terms( $post_id, $tax, array('fields'=>'ids') );
      if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        wp_set_object_terms( $new_id, $terms, $tax );
      }
    }
    // メタ情報を部分的にコピー（必要に応じて）
    update_post_meta( $new_id, '_seoone_translated_from', $post_id );
    update_post_meta( $new_id, '_seoone_language', $target );
    // 完了リダイレクト
    wp_safe_redirect( admin_url( 'post.php?post=' . $new_id . '&action=edit&seoone_message=success' ) );
    exit;
  }
}