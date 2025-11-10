<?php
if ( ! defined('ABSPATH') ) exit;
/** 要約+読了時間の自動出力（簡易） */
class SeoOne_Readtime {
  public static function init(){
    add_filter('the_content', array(__CLASS__,'prepend_summary_and_readtime'));
  }
  public static function prepend_summary_and_readtime( $content ){
    if ( is_singular('post') && in_the_loop() && is_main_query() ){
      $sum = get_post_meta(get_the_ID(),'_seoone_summary', true);
      $min = get_post_meta(get_the_ID(),'_seoone_readtime', true);
      $out = '';
      if ($sum) $out .= '<p class="seoone-summary">'.esc_html($sum).'</p>';
      if ($min) $out .= '<p class="seoone-readtime">この記事は約'.intval($min).'分で読めます。</p>';
      return $out . $content;
    }
    return $content;
  }
}
