<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="format-detection" content="telephone=no">
<meta name="description" content="埼玉県熊谷市にあるDD Solutions株式会社は、家電の出張修理・販売からWEBサイト・ソフトウェアの開発まで、地域に密着した技術サービスを提供しています。お客様第一の姿勢で、暮らしとビジネスの課題を解決します。">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header id="headWrap">
  <div id="headContent">
    <div class="logoArea">
      <h1><a href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo get_template_directory_uri(); ?>/assets/img/header/logo.png" alt="DD Solutions株式会社"></a></h1>
    </div>
    <div class="pcNaviWrap">
      <div class="inner">
        <nav class="NavMenu pcNavi">
          <ul>
            <li><a href="<?php echo esc_url(home_url('/')); ?>">ホーム</a></li>
            <li><a href="<?php echo esc_url(home_url('/products')); ?>">家電販売・修理</a></li>
            <li><a href="<?php echo esc_url(home_url('/services')); ?>">WEB・ソフトウェア開発</a></li>
            <li><a href="<?php echo esc_url(home_url('/news')); ?>">新着情報</a></li>
            <li class="liContact"><a href="<?php echo esc_url(home_url('/contact')); ?>"><i class="fas fa-envelope"></i>&nbsp;お問い合わせ</a></li>
          </ul>
        </nav>
        <div class="Toggle"> <span class="toggle-span"></span> <span></span> <span></span>
          <p>MENU</p>
        </div>
      </div>
    </div>
  </div>
  <div class="contactAreaTab">
    <p class="bnrContact"><a href="<?php echo esc_url(home_url('/contact')); ?>"><i class="fas fa-envelope-square"></i></a></p>
  </div>
</header>
