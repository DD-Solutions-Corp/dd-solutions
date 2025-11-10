<?php
if ( ! defined('ABSPATH') ) exit;
/** 目次ショートコード */
class SeoOne_TOC {
  public static function init(){
    add_shortcode('seoone_toc', array(__CLASS__,'render_toc'));
    add_shortcode('seoone_sidebar_toc', array(__CLASS__,'render_sidebar_toc'));
  }
  public static function render_toc(){ return '<div class="seoone-toc">目次（自動生成の想定）</div>'; }
  public static function render_sidebar_toc(){ return '<div class="seoone-sidebar-toc">サイド目次（自動）</div>'; }
}
