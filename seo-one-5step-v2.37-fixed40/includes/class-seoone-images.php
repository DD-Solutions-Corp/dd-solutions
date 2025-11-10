<?php
if ( ! defined('ABSPATH') ) exit;
/** 画像生成と <picture> 出力（簡易版） */
class SeoOne_Images {

  /**
   * Download an image from a given URL and register it in the WordPress media library.
   *
   * @param string $img_url   The remote image URL
   * @param string $desc      Description/alt text (used for attachment title)
   * @return int Attachment ID on success, 0 on failure
   */
  public static function download_and_register_image( $img_url, $desc = '' ) {
    if ( empty( $img_url ) ) {
      return 0;
    }
    // Include required WordPress files for media handling
    if ( ! function_exists( 'media_handle_sideload' ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';
      require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    // Download the file to a temporary location
    $tmp = download_url( $img_url );
    if ( is_wp_error( $tmp ) ) {
      return 0;
    }
    $file_array = array();
    // Set filename to something based on description, fallback to md5 of URL
    $name = sanitize_title_with_dashes( $desc );
    if ( empty( $name ) ) {
      $name = md5( $img_url );
    }
    // Use the original file extension if possible
    $ext = pathinfo( parse_url( $img_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
    if ( empty( $ext ) ) {
      $ext = 'jpg';
    }
    $file_array['name'] = $name . '.' . $ext;
    $file_array['tmp_name'] = $tmp;
    // Let WordPress handle the sideloading
    $attachment_id = 0;
    $attachment_id = media_handle_sideload( $file_array, 0, $desc );
    // Cleanup temporary file if handle failed
    if ( is_wp_error( $attachment_id ) ) {
      @unlink( $tmp );
      return 0;
    }
    return (int) $attachment_id;
  }

  /**
   * Build a <picture> tag with optional AVIF and WebP sources if image conversion is enabled.
   * Falls back to a single <img> tag if conversions are unavailable or disabled.
   *
   * @param int    $attachment_id Attachment ID
   * @param string $alt          Alt text
   * @return string Picture HTML fragment
   */
  public static function get_picture_html( $attachment_id, $alt = '' ) {
    if ( ! $attachment_id ) {
      return '';
    }
    $html = '';
    // Retrieve original image URL
    $full = wp_get_attachment_image_src( $attachment_id, 'full' );
    if ( ! $full ) {
      return '';
    }
    $src = $full[0];
    $settings = get_option( 'seoone_settings', array() );
    $use_conversion = ! empty( $settings['display_image_conversion'] );
    $alt = esc_attr( $alt );
    if ( $use_conversion ) {
      // Attempt to build AVIF and WebP URLs by replacing extension. Note: This assumes conversion plugins/processes exist.
      $url_parts = pathinfo( $src );
      $base = $url_parts['dirname'] . '/' . $url_parts['filename'];
      $html .= '<picture class="seoone-image">';
      $html .= '<source type="image/avif" srcset="' . esc_url( $base . '.avif' ) . '">';
      $html .= '<source type="image/webp" srcset="' . esc_url( $base . '.webp' ) . '">';
      $html .= '<img src="' . esc_url( $src ) . '" alt="' . $alt . '" loading="lazy">';
      $html .= '</picture>';
    } else {
      // Simple <figure><img><figcaption> structure
      $html .= '<figure class="seoone-image">';
      $html .= '<img src="' . esc_url( $src ) . '" alt="' . $alt . '" loading="lazy">';
      $html .= '<figcaption>' . esc_html( $alt ) . '</figcaption>';
      $html .= '</figure>';
    }
    return $html;
  }

  // Placeholder generate_image remains unused (image generation via AI not implemented)
  public static function generate_image( $prompt ) {
    return 0;
  }

  /**
   * Generate a simple SVG placeholder image when no real image is available.
   * The text will appear centered on a light grey background. The output is
   * wrapped in a <picture> element so it can be used interchangeably with
   * real images.
   *
   * @param string $text The caption or alt text to display in the placeholder
   * @return string HTML <picture> element with embedded SVG data URI
   */
  public static function generate_placeholder_svg( $text ) {
    $safe_text = esc_html( $text );
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450">'
         . '<rect width="100%" height="100%" fill="#f0f0f0"/>'
         . '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="24" fill="#555">'
         . htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
         . '</text></svg>';
    $data = base64_encode( $svg );
    $html  = '<picture class="seoone-image">';
    $html .= '<img src="data:image/svg+xml;base64,' . $data . '" alt="' . esc_attr( $safe_text ) . '" loading="lazy">';
    $html .= '</picture>';
    return $html;
  }
}
