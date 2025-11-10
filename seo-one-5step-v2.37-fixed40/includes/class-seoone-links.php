<?php
if ( ! defined('ABSPATH') ) exit;
/** 内部リンク（簡易版） */
class SeoOne_Links {
  public static function init(){ /* フック登録があればここに */ }
  public static function insert_internal_links( $content ){ return $content; }
}
