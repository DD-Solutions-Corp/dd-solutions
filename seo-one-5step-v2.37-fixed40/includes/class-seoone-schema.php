<?php
if ( ! defined('ABSPATH') ) exit;
/** 構造化データ（簡易） */
class SeoOne_Schema {
  public static function init(){
    // 設定で有効な場合にのみフックを追加
    add_action('wp_head', array(__CLASS__, 'output_schema'), 30);
  }

  /**
   * 記事ページで JSON-LD の Article schema を出力
   */
  public static function output_schema(){
    if ( ! is_singular('post') ) return;
    $settings = get_option('seoone_settings', array());
    if ( empty( $settings['display_schema'] ) ) return;
    global $post;
    if ( ! $post ) return;
    $title = get_the_title( $post );
    $permalink = get_permalink( $post );
    $date_published = get_the_date( 'c', $post );
    $date_modified  = get_the_modified_date( 'c', $post );
    $author_name = get_the_author_meta( 'display_name', $post->post_author );
    $excerpt = get_the_excerpt( $post );
    // アイキャッチ画像
    $img_url = '';
    if ( has_post_thumbnail( $post ) ) {
      $thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'full' );
      if ( $thumb ) $img_url = $thumb[0];
    }
    // サイト名・ロゴ
    $site_name = get_bloginfo('name');
    $logo = '';
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
      $logo_data = wp_get_attachment_image_src( $custom_logo_id, 'full' );
      if ( $logo_data ) $logo = $logo_data[0];
    }
    $data = array(
      '@context' => 'https://schema.org',
      '@type'    => 'Article',
      'headline' => $title,
      'mainEntityOfPage' => array(
        '@type' => 'WebPage',
        '@id'   => $permalink
      ),
      'author' => array(
        '@type' => 'Person',
        'name'  => $author_name
      ),
      'publisher' => array(
        '@type' => 'Organization',
        'name'  => $site_name,
      ),
      'datePublished' => $date_published,
      'dateModified'  => $date_modified,
    );
    if ( $logo ) {
      $data['publisher']['logo'] = array(
        '@type' => 'ImageObject',
        'url'   => $logo
      );
    }
    if ( $img_url ) {
      $data['image'] = $img_url;
    }
    if ( $excerpt ) {
      $data['description'] = wp_strip_all_tags( $excerpt );
    }
    echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
  }
}
