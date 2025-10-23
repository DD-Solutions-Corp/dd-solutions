<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />

<meta charset="utf-8"/>
<meta content="IE=edge" http-equiv="X-UA-Compatible"/>
<meta content="width=device-width,initial-scale=1" name="viewport"/>
<meta content="telephone=no" name="format-detection"/>
<meta content="埼玉県熊谷市にあるDD Solutions株式会社は、家電の出張修理・販売からWEBサイト・ソフトウェアの開発まで、地域に密着した技術サービスを提供しています。お客様第一の姿勢で、暮らしとビジネスの課題を解決します。" name="description"/>
<title>DD Solutions株式会社 埼玉県熊谷市 家電修理 家電販売 ソフトウェア開発 WEB ホームぺージ</title>
<link href="https://use.fontawesome.com/releases/v6.7.2/css/all.css" rel="stylesheet"/>
<script crossorigin="anonymous" src="https://kit.fontawesome.com/4ca21661a0.js" type="text/javascript"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js" type="text/javascript"></script>
<?php wp_head(); ?>

<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_uri() ); ?>">
</head>
<body <?php body_class(); ?>>
  <header id="headWrap" class="site-header">
    <div id="headContent">
    <div class="logoArea">
    <h1><a ><img alt="DD Solutions株式会社" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/common/logo.png"/></a></h1>
    </div>
    <div class="pcNaviWrap">
    <div class="inner">
    <nav class="NavMenu pcNavi">
      <?php
        if (has_nav_menu('header')) {
            wp_nav_menu(array(
                'theme_location' => 'header',
                'container'      => false,
                'depth'          => 2,
                'fallback_cb'    => '__return_empty_string',
                'items_wrap'     => '<ul>%3$s<li class="liContact"><a ><i class="fas fa-envelope"></i> お問い合わせ</a></li></ul>',
            ));
        }
        ?>
    </nav>
    <div class="Toggle"> <span class="toggle-span"></span> <span></span> <span></span>
    <p>MENU</p>
    </div>
    </div>
    </div>
    </div>
    <div class="contactAreaTab">
    <!-- <p class="telArea"><a href="tel:090-3204-3605"><i class="fas fa-phone-square-alt"></i></a></p> -->
    <p class="bnrContact"><a ><i class="fas fa-envelope-square"></i></a></p>
    </div>
  </header>
  <main id="primary mainVisualWrap" class="site-main">
    <div class="dd-legacy-wrap">
