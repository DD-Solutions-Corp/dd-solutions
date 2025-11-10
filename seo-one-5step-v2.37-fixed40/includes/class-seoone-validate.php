<?php
if ( ! defined('ABSPATH') ) exit;
/** 公開前検証（簡易） */
class SeoOne_Validate {
  public static function init(){
    // add_action('transition_post_status', array(__CLASS__,'check_before_publish'), 10, 3);
  }
  public static function check_before_publish( $new, $old, $post ){ /* TODO */ }
}
