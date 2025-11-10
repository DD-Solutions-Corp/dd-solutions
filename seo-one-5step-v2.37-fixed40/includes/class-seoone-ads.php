<?php
/**
 * 広告・収益タグ統合
 *
 * AdSense やアフィリエイトタグを記事コンテンツに挿入する機能を提供します。
 * CLS（累積レイアウトシフト）に配慮し、固定高さのプレースホルダを使って挿入位置を制御します。
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Ads {
    const OPTION_NAME = 'seoone_ads_settings';

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_filter('the_content', array(__CLASS__, 'insert_ads'));
    }

    /**
     * 設定項目を登録
     */
    public static function register_settings() {
        register_setting(self::OPTION_NAME, self::OPTION_NAME, array(__CLASS__, 'sanitize')); 
        add_settings_section('seoone_ads', '広告設定', function(){
            echo '<p>AdSense やアフィリエイトタグのコードと挿入位置を設定します。</p>';
        }, 'seoone-settings');
        add_settings_field('adsense_code','AdSense コード', array(__CLASS__, 'render_adsense_field'), 'seoone-settings','seoone_ads');
        add_settings_field('affiliate_code','アフィリエイトタグ', array(__CLASS__, 'render_affiliate_field'), 'seoone-settings','seoone_ads');
        add_settings_field('insert_position','挿入位置', array(__CLASS__, 'render_position_field'), 'seoone-settings','seoone_ads');
    }

    public static function sanitize($input) {
        return array(
            'adsense_code'   => wp_kses_post($input['adsense_code'] ?? ''),
            'affiliate_code' => wp_kses_post($input['affiliate_code'] ?? ''),
            'insert_position'=> sanitize_text_field($input['insert_position'] ?? 'middle'),
        );
    }

    public static function render_adsense_field() {
        $opt = get_option(self::OPTION_NAME, array());
        $val = $opt['adsense_code'] ?? '';
        echo '<textarea name="'.self::OPTION_NAME.'[adsense_code]" rows="4" class="large-text code">'.esc_textarea($val).'</textarea>';
    }

    public static function render_affiliate_field() {
        $opt = get_option(self::OPTION_NAME, array());
        $val = $opt['affiliate_code'] ?? '';
        echo '<textarea name="'.self::OPTION_NAME.'[affiliate_code]" rows="4" class="large-text code">'.esc_textarea($val).'</textarea>';
    }

    public static function render_position_field() {
        $opt = get_option(self::OPTION_NAME, array());
        $val = $opt['insert_position'] ?? 'middle';
        echo '<select name="'.self::OPTION_NAME.'[insert_position]">';
        $positions = array('beginning' => '記事冒頭', 'middle' => '記事中間', 'end' => '記事末尾');
        foreach ($positions as $key => $label) {
            echo '<option value="'.esc_attr($key).'" '.selected($val,$key,false).'>'.esc_html($label).'</option>';
        }
        echo '</select>';
    }

    /**
     * コンテンツに広告を挿入する
     */
    public static function insert_ads($content) {
        if ( is_singular('post') ) {
            $opt = get_option(self::OPTION_NAME, array());
            $adsense = $opt['adsense_code'] ?? '';
            $affiliate = $opt['affiliate_code'] ?? '';
            if ( empty($adsense) && empty($affiliate) ) return $content;
            $ad_html = $adsense . "\n" . $affiliate;
            // CLS 対策: 固定高さのコンテナに広告を入れる
            $wrapper = '<div class="seoone-ad-slot" style="width:100%;height:250px;overflow:hidden;">'. $ad_html .'</div>';
            $position = $opt['insert_position'] ?? 'middle';
            if ( $position === 'beginning' ) {
                return $wrapper . $content;
            }
            if ( $position === 'end' ) {
                return $content . $wrapper;
            }
            // middle: 段落分割して真ん中へ
            $paragraphs = explode("\n", $content);
            $mid = floor(count($paragraphs) / 2);
            $paragraphs = array_merge(
                array_slice($paragraphs, 0, $mid),
                array($wrapper),
                array_slice($paragraphs, $mid)
            );
            return implode("\n", $paragraphs);
        }
        return $content;
    }
}