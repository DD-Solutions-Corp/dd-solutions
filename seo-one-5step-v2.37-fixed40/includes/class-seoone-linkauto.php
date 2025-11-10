<?php
/**
 * 自動内部リンク挿入機能
 *
 * CTRが高く平均順位が低いキーワードに基づき、既存投稿内の該当語をリンク化します。
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_LinkAuto {
  public static function init() {
    add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    add_action( 'admin_post_seoone_insert_link', array( __CLASS__, 'handle_insert_link' ) );
  }

  public static function register_menu() {
    add_submenu_page(
      'seoone',
      '内部リンク自動化',
      '内部リンク自動化',
      SEOONE_CAP,
      'seoone-link-auto',
      array( __CLASS__, 'render_page' )
    );
  }

  /**
   * 自動化ページ
   */
  public static function render_page() {
    if ( ! current_user_can( SEOONE_CAP ) ) return;
    echo '<div class="wrap"><h1>内部リンク自動化</h1>';
    // ツール一覧への戻りリンク
    echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=seoone&tab=tools' ) ) . '">&larr; ツール一覧へ戻る</a></p>';
    // メッセージ表示
    if ( isset($_GET['seoone_message']) ) {
      if ( $_GET['seoone_message'] === 'link_success' ) {
        echo '<div class="notice notice-success"><p>リンクを挿入しました。</p></div>';
      } elseif ( $_GET['seoone_message'] === 'link_error' ) {
        echo '<div class="notice notice-error"><p>リンク挿入に失敗しました。</p></div>';
      }
    }
    // 改善候補キーワードの取得
    $top_queries = get_option( 'seoone_metrics_top_queries', array() );
    $improvements = array();
    foreach ( $top_queries as $row ) {
      $ctr_pct = isset( $row['ctr'] ) ? (float)$row['ctr'] * 100 : 0;
      $pos = isset( $row['position'] ) ? (float)$row['position'] : 0;
      if ( $ctr_pct > 5 && $pos > 10 ) {
        $improvements[] = $row;
      }
    }
    if ( empty( $improvements ) ) {
      echo '<p>自動化できるキーワードが見つかりませんでした。Search Console データ取得後にご利用ください。</p>';
      echo '</div>';
      return;
    }
    echo '<p>下記のキーワードに対し、対象記事への内部リンクを他の記事に自動挿入します。</p>';
    echo '<table class="widefat fixed"><thead><tr><th>キーワード</th><th>対象記事</th><th>操作</th></tr></thead><tbody>';
    foreach ( $improvements as $row ) {
      $query = sanitize_text_field( $row['query'] );
      // 対象記事を検索（1件目を採用）
      $posts = get_posts( array(
        's' => $query,
        'post_status' => 'publish',
        'posts_per_page' => 1,
      ) );
      $target_post = ! empty( $posts ) ? $posts[0] : null;
      echo '<tr>'; 
      echo '<td>' . esc_html( $query ) . '</td>';
      if ( $target_post ) {
        $target_id = $target_post->ID;
        $link = get_permalink( $target_id );
        echo '<td><a href="' . esc_url( get_edit_post_link( $target_id ) ) . '" target="_blank">' . esc_html( get_the_title( $target_id ) ) . '</a></td>';
        // ボタン
        $url = esc_url( admin_url('admin-post.php') );
        echo '<td><form method="post" action="' . $url . '">';
        wp_nonce_field( 'seoone_insert_link' );
        echo '<input type="hidden" name="action" value="seoone_insert_link">';
        echo '<input type="hidden" name="seoone_query" value="' . esc_attr( $query ) . '">';
        echo '<input type="hidden" name="seoone_target" value="' . intval( $target_id ) . '">';
        echo '<button type="submit" class="button">自動挿入</button>';
        echo '</form></td>';
      } else {
        echo '<td>対象記事なし</td><td> - </td>';
      }
      echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
  }

  /**
   * 内部リンク挿入の処理
   */
  public static function handle_insert_link() {
    if ( ! current_user_can( SEOONE_CAP ) ) wp_die('権限がありません');
    check_admin_referer( 'seoone_insert_link' );
    $query  = isset( $_POST['seoone_query'] ) ? sanitize_text_field( $_POST['seoone_query'] ) : '';
    $target_id = isset( $_POST['seoone_target'] ) ? intval( $_POST['seoone_target'] ) : 0;
    if ( empty( $query ) || ! $target_id ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-link-auto&seoone_message=link_error') );
      exit;
    }
    $target_url = get_permalink( $target_id );
    if ( ! $target_url ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-link-auto&seoone_message=link_error') );
      exit;
    }
    // すべての公開記事を検索して置換
    $posts = get_posts( array(
      'post_type'   => 'post',
      'post_status' => 'publish',
      'posts_per_page' => -1,
    ) );
    $updated = false;
    foreach ( $posts as $p ) {
      // 対象記事自身は除外
      if ( $p->ID == $target_id ) continue;
      $content = $p->post_content;
      if ( stripos( $content, $query ) === false ) continue;
      // 既にリンクが存在する場合はスキップ
      if ( stripos( $content, '<a ' ) !== false && stripos( $content, $query ) < stripos( $content, '<a ' ) ) {
        // 先頭近くにリンクがあると判定してスキップ
        continue;
      }
      // 最初の出現箇所のみ置換
      $pattern = '/' . preg_quote( $query, '/' ) . '/iu';
      $replacement = '<a href="' . esc_url_raw( $target_url ) . '">' . $query . '</a>';
      $new_content = preg_replace( $pattern, $replacement, $content, 1 );
      if ( $new_content && $new_content !== $content ) {
        // 更新
        wp_update_post( array( 'ID' => $p->ID, 'post_content' => $new_content ) );
        $updated = true;
      }
    }
    if ( $updated ) {
      wp_safe_redirect( admin_url('admin.php?page=seoone-link-auto&seoone_message=link_success') );
    } else {
      wp_safe_redirect( admin_url('admin.php?page=seoone-link-auto&seoone_message=link_error') );
    }
    exit;
  }
}