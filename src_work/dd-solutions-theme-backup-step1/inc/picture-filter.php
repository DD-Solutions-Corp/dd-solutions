<?php
if (!defined('ABSPATH')) exit;

// コンテンツ内の画像を<picture>タグに変換
add_filter('the_content', function($content) {
    $content = preg_replace_callback('/<img([^>]+)src=["\']([^"\']+)["\']([^>]*)>/i', function($m) {
        $attrs = $m[1].$m[3];
        $src = $m[2];
        $info = pathinfo($src);
        $base = $info['dirname'].'/'.$info['filename'];
        
        $avif = $base.'.avif';
        $webp = $base.'.webp';
        
        $picture = '<picture>';
        $picture .= '<source srcset="'.$avif.'" type="image/avif">';
        $picture .= '<source srcset="'.$webp.'" type="image/webp">';
        $picture .= '<img'.$attrs.'src="'.$src.'" loading="lazy" decoding="async">';
        $picture .= '</picture>';
        
        return $picture;
    }, $content);
    
    return $content;
}, 20);
