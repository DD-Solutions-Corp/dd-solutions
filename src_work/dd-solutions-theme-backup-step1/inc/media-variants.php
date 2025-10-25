<?php
if (!defined('ABSPATH')) exit;

// 画像アップロード時にAVIF/WebP/JPG自動生成
add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id) {
    $file = get_attached_file($attachment_id);
    if (!file_exists($file)) return $metadata;
    
    $info = pathinfo($file);
    if (!in_array(strtolower($info['extension']), ['jpg','jpeg','png'])) return $metadata;
    
    $sizes = [['w'=>1920,'suffix'=>'pc'], ['w'=>768,'suffix'=>'sp']];
    
    foreach ($sizes as $size) {
        $img = wp_get_image_editor($file);
        if (is_wp_error($img)) continue;
        
        $img->resize($size['w'], null, false);
        
        $webp = $info['dirname'].'/'.$info['filename'].'_'.$size['suffix'].'.webp';
        $img->save($webp, 'image/webp');
        
        if (function_exists('imageavif')) {
            $avif = $info['dirname'].'/'.$info['filename'].'_'.$size['suffix'].'.avif';
            $img->save($avif, 'image/avif');
        }
        
        $jpg = $info['dirname'].'/'.$info['filename'].'_'.$size['suffix'].'.jpg';
        $img->save($jpg, 'image/jpeg');
    }
    
    return $metadata;
}, 10, 2);
