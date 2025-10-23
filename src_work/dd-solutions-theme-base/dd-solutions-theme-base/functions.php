<?php
/**
 * dd-clean functions.php (stable)
 * - 数字なし＆配列で共通CSSの順序を固定
 * - ページ別CSSは存在チェックで条件読み込み
 * - アニメーションCSS/JSとpagetop.jsを読み込み
 * - メニュー登録・カスタムロゴ対応
 * - 不要な閉じカッコ・重複フックなし
 */

/*--------------------------------------
  Setup
--------------------------------------*/
add_action('after_setup_theme', function () {
  register_nav_menus([
    'primary' => __('Primary Menu', 'dd'),
    'footer'  => __('Footer Menu',  'dd'),
  ]);

  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('custom-logo', [
    'height'      => 60,
    'flex-height' => true,
    'flex-width'  => true,
  ]);
});

/*--------------------------------------
  Enqueue (CSS/JS)
--------------------------------------*/
add_action('wp_enqueue_scripts', function () {
  $uri = get_template_directory_uri();
  $dir = get_template_directory();

  // style.css
  $style = $dir . '/style.css';
  if (file_exists($style)) {
    wp_enqueue_style('dd-style', $uri . '/style.css', [], filemtime($style));
  }

  // 共通CSS（この配列の順序で読み込み。存在するものだけ）
  $order = [
    'reset.css',
    'variables.css',
    'typography.css',
    'layout.css',
    'components.css',
    'forms.css',
    'common.css',
    'legacy.css',
    'footer.css',
  ];
  foreach ($order as $file) {
    $abs = $dir . '/assets/css/common/' . $file;
    if (file_exists($abs)) {
      $handle = 'dd-c-' . pathinfo($file, PATHINFO_FILENAME); // dd-c-common 等
      wp_enqueue_style($handle, $uri . '/assets/css/common/' . $file, [], filemtime($abs));
    }
  }

  // ページ別CSS（存在するものだけ）
  $enq = function ($rel) use ($dir, $uri) {
    $abs = $dir . $rel;
    if (file_exists($abs)) {
      $handle = 'dd-p-' . basename($abs, '.css'); // dd-p-front-page 等
      wp_enqueue_style($handle, $uri . $rel, [], filemtime($abs));
    }
  };

  if (is_front_page()) { $enq('/assets/css/pages/front-page.css'); }
  if (is_home())       { $enq('/assets/css/pages/home.css'); }

  if (is_page()) {
    $enq('/assets/css/pages/page.css');
    $obj = get_queried_object();
    if ($obj && !empty($obj->post_name)) { $enq('/assets/css/pages/page-' . $obj->post_name . '.css'); }
    if ($obj && !empty($obj->ID))        { $enq('/assets/css/pages/page-' . $obj->ID . '.css'); }
  }

  if (is_single()) {
    $enq('/assets/css/pages/single.css');
    $pt = get_post_type();
    if ($pt) { $enq('/assets/css/pages/single-' . $pt . '.css'); }
  }

  if (is_archive()) {
    $enq('/assets/css/pages/archive.css');
    $pt = get_post_type();
    if ($pt) { $enq('/assets/css/pages/archive-' . $pt . '.css'); }

    if (is_category()) {
      $enq('/assets/css/pages/category.css');
      $term = get_queried_object();
      if ($term && !empty($term->slug)) { $enq('/assets/css/pages/category-' . $term->slug . '.css'); }
    }
    if (is_tag()) {
      $enq('/assets/css/pages/tag.css');
      $term = get_queried_object();
      if ($term && !empty($term->slug)) { $enq('/assets/css/pages/tag-' . $term->slug . '.css'); }
    }
  }

  if (is_search()) { $enq('/assets/css/pages/search.css'); }
  if (is_404())    { $enq('/assets/css/pages/404.css'); }

  // アニメーション（CSS/JS）
  $anim_css = $dir . '/assets/css/common/animations.css';
  if (file_exists($anim_css)) {
    wp_enqueue_style('dd-animations', $uri . '/assets/css/common/animations.css', [], filemtime($anim_css));
  }
  $anim_js = $dir . '/assets/js/animator.js';
  if (file_exists($anim_js)) {
    wp_enqueue_script('dd-animator', $uri . '/assets/js/animator.js', [], filemtime($anim_js), true);
  }

  // pagetop
  $pt_js = $dir . '/assets/js/pagetop.js';
  if (file_exists($pt_js)) {
    wp_enqueue_script('dd-pagetop', $uri . '/assets/js/pagetop.js', [], filemtime($pt_js), true);
  }

  /*
   * レガシースクリプトで jQuery の $ シンボルを使用しているため、
   * WordPress が既に登録している jQuery を確実に読み込みます。
   * また、互換のため jQuery Migrate を有効化します。
   */
  wp_enqueue_script('jquery');
  wp_enqueue_script('jquery-migrate');

  // フロントページ向けヒーロースライダー
  if (is_front_page()) {
    $hero_js = $dir . '/assets/js/hero-rotator.js';
    if (file_exists($hero_js)) {
      wp_enqueue_script('dd-hero-rotator', $uri . '/assets/js/hero-rotator.js', ['jquery'], filemtime($hero_js), true);
    }
    $hero_css = $dir . '/assets/css/pages/hero-rotator.css';
    if (file_exists($hero_css)) {
      wp_enqueue_style('dd-hero-rotator', $uri . '/assets/css/pages/hero-rotator.css', [], filemtime($hero_css));
    }
  }
});
/* === Added by patch: register custom menu locations (header/footer) === */
add_action('after_setup_theme', function () {
    register_nav_menus(array(
        'header' => __('グローバルナビ（ヘッダー）', 'dd-theme'),
        'footer' => __('フッターナビ（フッター）', 'dd-theme'),
    ));
});
/* === /Added by patch === */

// Add aria-current for accessibility
add_filter('nav_menu_link_attributes', function ($atts, $item) {
    $classes = isset($item->classes) && is_array($item->classes) ? $item->classes : [];
    if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
        $atts['aria-current'] = 'page';
    }
    return $atts;
}, 10, 2);


/* === Added by patch v3: auto add liContact for お問い合わせ === */
add_filter('nav_menu_css_class', function($classes, $item){
    $title = isset($item->title) ? $item->title : '';
    $url   = isset($item->url) ? $item->url : '';
    // Match Japanese "お問い合わせ" or any string containing 'contact'
    if ($title === 'お問い合わせ' || stripos($url, 'contact') !== false) {
        if (!in_array('liContact', $classes, true)) {
            $classes[] = 'liContact';
        }
    }
    return $classes;
}, 10, 2);
/* === /Added by patch v3 === */

/* === liContact auto-tag for Contact menu item (JP variants) === */
add_filter('nav_menu_css_class', function($classes, $item){
    $title = isset($item->title) ? $item->title : '';
    $url   = isset($item->url) ? $item->url : '';
    // Normalize title (remove spaces)
    $title_norm = str_replace(array(' ', '　'), '', $title);
    // Match common variants: お問い合わせ, お問合せ, お問い合せ, 問い合わせ, 問合せ
    $is_contact_title = preg_match('/(お問い合わせ|お問合せ|お問い合せ|問い合わせ|問合せ)/u', $title_norm) === 1;
    $is_contact_url   = (stripos($url, 'contact') !== false) || (mb_stripos($url, '問い合わせ') !== false);
    if ($is_contact_title || $is_contact_url) {
        if (!in_array('liContact', (array)$classes, true)) {
            $classes[] = 'liContact';
        }
    }
    return $classes;
}, 10, 2);



/* === v6: liContact auto-tag & icon for contact menu === */
add_filter('nav_menu_css_class', function($classes, $item){
    $title = isset($item->title) ? $item->title : '';
    $url   = isset($item->url) ? $item->url : '';
    $title_norm = str_replace(array(' ', '　'), '', $title);
    $is_contact_title = preg_match('/(お問い合わせ|お問合せ|お問い合せ|問い合わせ|問合せ)/u', $title_norm) === 1;
    $is_contact_url   = (stripos($url, 'contact') !== false) || (mb_stripos($url, '問い合わせ') !== false);
    if ($is_contact_title || $is_contact_url) {
        if (!in_array('liContact', (array)$classes, true)) {
            $classes[] = 'liContact';
        }
    }
    return $classes;
}, 10, 2);

// Inject envelope icon for header liContact items
add_filter('walker_nav_menu_start_el', function($item_output, $item, $depth, $args){
    $loc = isset($args->theme_location) ? $args->theme_location : '';
    $classes = isset($item->classes) && is_array($item->classes) ? $item->classes : array();
    if ($loc === 'header' && in_array('liContact', $classes, true)) {
        // Insert icon right after opening <a ...>
        $item_output = preg_replace('/(<a\b[^>]*>)/i', '$1<i class="fas fa-envelope" aria-hidden="true"></i>&nbsp;', $item_output, 1);
    }
    return $item_output;
}, 10, 4);
